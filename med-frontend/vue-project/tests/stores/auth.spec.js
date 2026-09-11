import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { axiosOk, apiError, utente } from '../helpers'

/*
  Il modulo API viene sostituito in blocco: lo store deve essere provato per la
  logica di sessione, non per il trasporto HTTP. vi.hoisted serve perche' la
  factory di vi.mock viene issata sopra gli import e non puo' leggere costanti
  dichiarate qui sotto.
*/
const { apiMock } = vi.hoisted(() => ({
  apiMock: {
    auth: {
      login: vi.fn(),
      me: vi.fn(),
      refresh: vi.fn(),
      logout: vi.fn(),
      logoutAll: vi.fn(),
    },
  },
}))

vi.mock('@/services/api', () => ({ api: apiMock, default: apiMock }))

import { useAuthStore } from '@/store/auth.js'

describe('store auth', () => {
  let auth

  beforeEach(() => {
    setActivePinia(createPinia())
    auth = useAuthStore()
  })

  afterEach(() => {
    auth.stopRefreshTimer()
    vi.useRealTimers()
  })

  /* =====================================================================
   | Login
   * ===================================================================*/

  describe('login', () => {
    it('salva token, scadenza, utente e restituisce la rotta di destinazione', async () => {
      apiMock.auth.login.mockResolvedValue(
        axiosOk({
          token: 'token-123',
          expires_at: '2030-01-01T12:00:00+01:00',
          user: utente(),
          redirect: '/paziente/panoramica',
        }),
      )

      const destinazione = await auth.login({ email: 'mario.rossi@example.it', password: 'password' })

      expect(apiMock.auth.login).toHaveBeenCalledWith({
        email: 'mario.rossi@example.it',
        password: 'password',
        role: null,
      })

      expect(auth.token).toBe('token-123')
      expect(auth.tokenExpiresAt).toBe('2030-01-01T12:00:00+01:00')
      expect(auth.user).toMatchObject({ id: 1, role: 'paziente' })
      expect(auth.isAuthenticated).toBe(true)
      expect(destinazione).toBe('/paziente/panoramica')
    })

    it('inoltra il ruolo scelto nella schermata di login', async () => {
      apiMock.auth.login.mockResolvedValue(
        axiosOk({ token: 't', user: utente({ role: 'medico' }), redirect: '/medico/panoramica' }),
      )

      await auth.login({ email: 'a@b.it', password: 'x', role: 'medico' })

      expect(apiMock.auth.login).toHaveBeenCalledWith(
        expect.objectContaining({ role: 'medico' }),
      )
    })

    it('propaga l errore di credenziali senza aprire una sessione', async () => {
      apiMock.auth.login.mockRejectedValue(
        apiError({ status: 422, errors: { email: ['Credenziali non valide.'] } }),
      )

      await expect(auth.login({ email: 'a@b.it', password: 'sbagliata' })).rejects.toMatchObject({
        status: 422,
      })

      expect(auth.token).toBeNull()
      expect(auth.user).toBeNull()
      expect(auth.isAuthenticated).toBe(false)
    })
  })

  /* =====================================================================
   | Getter di ruolo e destinazione
   * ===================================================================*/

  describe('getter', () => {
    it('isAuthenticated richiede sia il token sia l utente', () => {
      expect(auth.isAuthenticated).toBe(false)

      auth.token = 'token'
      expect(auth.isAuthenticated).toBe(false)

      auth.user = utente()
      expect(auth.isAuthenticated).toBe(true)
    })

    it('espone il ruolo con getter dedicati che si escludono a vicenda', () => {
      auth.user = utente({ role: 'medico' })

      expect(auth.role).toBe('medico')
      expect(auth.isDoctor).toBe(true)
      expect(auth.isAdmin).toBe(false)
      expect(auth.isPatient).toBe(false)
    })

    it('espone gli id dei profili collegati, null se assenti', () => {
      auth.user = utente({ patient: { id: 42 } })
      expect(auth.patientId).toBe(42)
      expect(auth.doctorId).toBeNull()

      auth.user = utente({ role: 'medico', doctor: { id: 7 } })
      expect(auth.doctorId).toBe(7)
      expect(auth.patientId).toBeNull()
    })

    it('dashboardPath preferisce il redirect deciso dal backend', () => {
      auth.user = utente({ role: 'paziente' })
      auth.redirect = '/paziente/appuntamenti'

      expect(auth.dashboardPath).toBe('/paziente/appuntamenti')
    })

    it('senza redirect ricade sulla home del ruolo', () => {
      auth.user = utente({ role: 'admin' })
      expect(auth.dashboardPath).toBe('/admin/panoramica')

      auth.user = utente({ role: 'medico' })
      expect(auth.dashboardPath).toBe('/medico/panoramica')

      auth.user = null
      expect(auth.dashboardPath).toBe('/')
    })

    /**
     * Open redirect: il backend e' affidabile, ma il redirect finisce in
     * router.replace() e un valore assoluto porterebbe l'utente fuori dal sito.
     * Si accettano solo path interni.
     */
    it('scarta un redirect che punta fuori dall applicazione', () => {
      auth.user = utente({ role: 'paziente' })

      for (const ostile of ['//evil.example.com', 'https://evil.example.com', 'javascript:alert(1)']) {
        auth.applySession({ token: 't', user: auth.user, redirect: ostile })
        expect(auth.redirect).toBeNull()
        expect(auth.dashboardPath).toBe('/paziente/panoramica')
      }
    })
  })

  /* =====================================================================
   | Ripristino sessione
   * ===================================================================*/

  describe('bootstrap', () => {
    it('senza token non chiama il server e chiude l inizializzazione', async () => {
      const esito = await auth.bootstrap()

      expect(esito).toBe(false)
      expect(apiMock.auth.me).not.toHaveBeenCalled()
      expect(auth.initializing).toBe(false)
    })

    it('con token valido rilegge l utente dal server', async () => {
      auth.token = 'token-persistito'
      apiMock.auth.me.mockResolvedValue(
        axiosOk({ user: utente({ role: 'medico' }), redirect: '/medico/panoramica' }),
      )

      const esito = await auth.bootstrap()

      expect(esito).toBe(true)
      expect(auth.user.role).toBe('medico')
      expect(auth.redirect).toBe('/medico/panoramica')
      expect(auth.initializing).toBe(false)
    })

    it('con token revocato pulisce la sessione senza propagare l errore', async () => {
      auth.token = 'token-scaduto'
      apiMock.auth.me.mockRejectedValue(apiError({ status: 401, message: 'Non autenticato.' }))

      const esito = await auth.bootstrap()

      expect(esito).toBe(false)
      expect(auth.token).toBeNull()
      expect(auth.user).toBeNull()
      expect(auth.initializing).toBe(false)
    })
  })

  /* =====================================================================
   | Rinnovo del token
   * ===================================================================*/

  describe('refresh', () => {
    it('senza token non tenta il rinnovo', async () => {
      expect(await auth.refresh()).toBe(false)
      expect(apiMock.auth.refresh).not.toHaveBeenCalled()
    })

    it('sostituisce token e scadenza mantenendo l utente', async () => {
      auth.token = 'vecchio'
      auth.user = utente()

      apiMock.auth.refresh.mockResolvedValue(
        axiosOk({ token: 'nuovo', expires_at: '2030-01-01T12:00:00+01:00' }),
      )

      expect(await auth.refresh()).toBe(true)
      expect(auth.token).toBe('nuovo')
      expect(auth.user).not.toBeNull()
    })

    it('se il rinnovo fallisce chiude la sessione', async () => {
      auth.token = 'vecchio'
      auth.user = utente()
      apiMock.auth.refresh.mockRejectedValue(apiError({ status: 401 }))

      expect(await auth.refresh()).toBe(false)
      expect(auth.token).toBeNull()
      expect(auth.user).toBeNull()
    })

    it('programma il rinnovo due minuti prima della scadenza', async () => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2030-01-01T10:00:00Z'))

      apiMock.auth.refresh.mockResolvedValue(axiosOk({ token: 'rinnovato' }))

      auth.token = 'iniziale'
      auth.tokenExpiresAt = new Date('2030-01-01T10:10:00Z').toISOString()
      auth.scheduleRefresh()

      // 7 minuti: siamo ancora un minuto prima della soglia
      await vi.advanceTimersByTimeAsync(7 * 60 * 1000)
      expect(apiMock.auth.refresh).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(60 * 1000)
      expect(apiMock.auth.refresh).toHaveBeenCalledTimes(1)
      expect(auth.token).toBe('rinnovato')
    })

    it('se la scadenza e gia passata rinnova subito', async () => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2030-01-01T10:00:00Z'))

      apiMock.auth.refresh.mockResolvedValue(axiosOk({ token: 'rinnovato' }))

      auth.token = 'iniziale'
      auth.tokenExpiresAt = new Date('2030-01-01T09:00:00Z').toISOString() // un'ora fa
      auth.scheduleRefresh()

      await vi.advanceTimersByTimeAsync(0)
      expect(apiMock.auth.refresh).toHaveBeenCalledTimes(1)
    })

    it('senza scadenza dichiarata non programma nulla', () => {
      vi.useFakeTimers()

      auth.tokenExpiresAt = null
      auth.scheduleRefresh()

      expect(auth.refreshTimer).toBeNull()
      expect(vi.getTimerCount()).toBe(0)
    })

    it('un nuovo scheduleRefresh sostituisce il timer precedente', async () => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2030-01-01T10:00:00Z'))
      apiMock.auth.refresh.mockResolvedValue(axiosOk({ token: 'rinnovato' }))

      auth.token = 'iniziale'
      auth.tokenExpiresAt = new Date('2030-01-01T10:10:00Z').toISOString()
      auth.scheduleRefresh()
      auth.scheduleRefresh()

      expect(vi.getTimerCount()).toBe(1)

      await vi.advanceTimersByTimeAsync(8 * 60 * 1000)
      expect(apiMock.auth.refresh).toHaveBeenCalledTimes(1)
    })
  })

  /* =====================================================================
   | Logout
   * ===================================================================*/

  describe('logout', () => {
    beforeEach(() => {
      auth.token = 'token'
      auth.user = utente()
      auth.redirect = '/paziente/panoramica'
    })

    it('revoca il dispositivo corrente e svuota lo stato', async () => {
      apiMock.auth.logout.mockResolvedValue(axiosOk({ message: 'Logout effettuato.' }))

      await auth.logout()

      expect(apiMock.auth.logout).toHaveBeenCalledTimes(1)
      expect(apiMock.auth.logoutAll).not.toHaveBeenCalled()
      expect(auth.token).toBeNull()
      expect(auth.user).toBeNull()
      expect(auth.redirect).toBeNull()
      expect(auth.isAuthenticated).toBe(false)
    })

    it('con allDevices revoca tutte le sessioni', async () => {
      apiMock.auth.logoutAll.mockResolvedValue(axiosOk({}))

      await auth.logout({ allDevices: true })

      expect(apiMock.auth.logoutAll).toHaveBeenCalledTimes(1)
      expect(apiMock.auth.logout).not.toHaveBeenCalled()
      expect(auth.token).toBeNull()
    })

    /**
     * Se il token e' gia' scaduto la chiamata di logout fallisce con 401: la
     * sessione locale va comunque chiusa, altrimenti l'utente resta bloccato in
     * un limbo con un token morto in localStorage.
     */
    it('chiude la sessione locale anche se la chiamata fallisce', async () => {
      apiMock.auth.logout.mockRejectedValue(apiError({ status: 401 }))

      await auth.logout()

      expect(auth.token).toBeNull()
      expect(auth.user).toBeNull()
    })

    it('clearSession ferma anche il timer di rinnovo', () => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2030-01-01T10:00:00Z'))

      auth.tokenExpiresAt = new Date('2030-01-01T10:10:00Z').toISOString()
      auth.scheduleRefresh()
      expect(vi.getTimerCount()).toBe(1)

      auth.clearSession()

      expect(vi.getTimerCount()).toBe(0)
      expect(auth.refreshTimer).toBeNull()
    })
  })
})
