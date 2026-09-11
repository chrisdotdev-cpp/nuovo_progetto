import { defineStore } from 'pinia'
import { api } from '@/services/api'
import { registerAuthStore } from '@/services/http'

/**
 * Sessione utente.
 *
 * Il token Sanctum e' persistito (pinia-plugin-persistedstate) cosi' un refresh
 * della pagina non butta fuori l'utente. I dati dell'utente vengono comunque
 * riletti dal server all'avvio: la fonte di verita' resta il backend.
 */

/** Mappa ruolo -> home, allineata a AuthController::dashboardFor(). */
const HOME_BY_ROLE = {
  admin: '/admin/panoramica',
  medico: '/medico/panoramica',
  paziente: '/paziente/panoramica',
}

/** Il redirect arriva dal backend: si accetta solo se e' un path interno. */
function safePath(path, fallback = '/') {
  return typeof path === 'string' && path.startsWith('/') && !path.startsWith('//')
    ? path
    : fallback
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null,
    tokenExpiresAt: null,
    user: null,
    /** Rotta di destinazione decisa dal backend (login e /auth/me) */
    redirect: null,
    /** true finche' non abbiamo verificato il token all'avvio dell'app */
    initializing: true,
    /** timer del rinnovo automatico del token */
    refreshTimer: null,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token && state.user),
    role: (state) => state.user?.role ?? null,
    isAdmin: (state) => state.user?.role === 'admin',
    isDoctor: (state) => state.user?.role === 'medico',
    isPatient: (state) => state.user?.role === 'paziente',

    /** Profilo clinico o professionale collegato all'account */
    patientId: (state) => state.user?.patient?.id ?? null,
    doctorId: (state) => state.user?.doctor?.id ?? null,

    /**
     * Home del ruolo: usata dopo il login e dal click sul logo.
     * Ha la precedenza il `redirect` calcolato dal backend.
     */
    dashboardPath: (state) => safePath(state.redirect, HOME_BY_ROLE[state.user?.role] ?? '/'),
  },

  actions: {
    /**
     * Login reale contro Laravel.
     * Salva token + scadenza + utente + redirect e restituisce la rotta di destinazione.
     * Gli errori (422 credenziali/validazione, 401, rete) vengono propagati
     * gia' normalizzati dall'interceptor: li gestisce useApiRequest.
     */
    async login({ email, password, role = null }) {
      const { data } = await api.auth.login({ email, password, role })

      this.applySession(data)
      this.scheduleRefresh()

      return this.dashboardPath
    },

    /** Scrive in stato la risposta di /auth/login. */
    applySession(data) {
      this.token = data.token
      this.tokenExpiresAt = data.expires_at ?? null
      this.user = data.user ?? null
      this.redirect = safePath(data.redirect, null)
    },

    /**
     * Ripristino sessione all'avvio: se il token e' ancora valido recupera l'utente,
     * altrimenti pulisce tutto. Da chiamare una sola volta in main.js.
     */
    async bootstrap() {
      if (!this.token) {
        this.initializing = false
        return false
      }

      try {
        const { data } = await api.auth.me()
        this.user = data.user
        this.redirect = safePath(data.redirect, null)
        this.scheduleRefresh()
        return true
      } catch {
        // 401 gia' gestito dall'interceptor, qui basta non propagare
        this.clearSession()
        return false
      } finally {
        this.initializing = false
      }
    },

    /**
     * Rinnova il token senza chiedere di nuovo la password.
     * Restituisce true se il rinnovo e' riuscito.
     */
    async refresh() {
      if (!this.token) return false

      try {
        const { data } = await api.auth.refresh()
        this.token = data.token
        this.tokenExpiresAt = data.expires_at ?? null
        this.scheduleRefresh()
        return true
      } catch {
        this.clearSession()
        return false
      }
    },

    /**
     * Programma il rinnovo 2 minuti prima della scadenza dichiarata dal backend.
     * Se la scadenza e' gia' passata (tab riaperta dopo ore) si rinnova subito.
     */
    scheduleRefresh() {
      this.stopRefreshTimer()
      if (!this.tokenExpiresAt) return

      const expiresIn = new Date(this.tokenExpiresAt).getTime() - Date.now()
      if (Number.isNaN(expiresIn)) return

      // setTimeout va in overflow oltre ~24 giorni: si limita il valore
      const delay = Math.min(Math.max(expiresIn - 120000, 0), 2147483000)

      this.refreshTimer = setTimeout(() => this.refresh(), delay)
    },

    stopRefreshTimer() {
      if (this.refreshTimer) {
        clearTimeout(this.refreshTimer)
        this.refreshTimer = null
      }
    },

    /** Aggiorna l'utente in cache dopo una modifica del profilo. */
    setUser(user) {
      this.user = user
    },

    async logout({ allDevices = false } = {}) {
      try {
        await (allDevices ? api.auth.logoutAll() : api.auth.logout())
      } catch {
        // Se il token e' gia' scaduto il logout lato server non serve piu'
      } finally {
        this.clearSession()
      }
    },

    /** Pulizia locale: chiamata anche dall'interceptor sul 401. */
    clearSession() {
      this.stopRefreshTimer()
      this.token = null
      this.tokenExpiresAt = null
      this.user = null
      this.redirect = null
      this.initializing = false
    },
  },

  // Si persiste solo il token: i dati utente si rileggono sempre dal server
  persist: {
    pick: ['token', 'tokenExpiresAt'],
  },
})

/**
 * Collega lo store all'interceptor axios.
 * Va invocata in main.js dopo app.use(pinia).
 */
export function connectAuthStoreToHttp() {
  registerAuthStore(useAuthStore())
}
