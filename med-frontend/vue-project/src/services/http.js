/**
 * Client HTTP unico dell'applicazione.
 *
 * Responsabilita':
 *  - baseURL e header comuni
 *  - iniezione automatica del token Sanctum (Bearer)
 *  - normalizzazione degli errori in una forma sola per tutta la UI
 *  - logout automatico sul 401 e redirect al login
 *
 * Nessun componente deve importare axios direttamente: si passa sempre da qui.
 */
import axios from 'axios'

// Import differito dello store: evita la dipendenza circolare store -> http -> store
let authStoreRef = null
export function registerAuthStore(store) {
  authStoreRef = store
}

// Callback di redirect impostata dal router (evita di importare il router qui)
let onUnauthorized = null
export function registerUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  timeout: 20000,
  // Sanctum in modalita' token: nessun cookie, nessun CSRF
  withCredentials: false,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/* -------------------------------------------------------------------------
 | Request: token in ogni chiamata
 * ---------------------------------------------------------------------- */
http.interceptors.request.use((config) => {
  const token = authStoreRef?.token

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // Con FormData il browser deve impostare da solo il boundary
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }

  return config
})

/* -------------------------------------------------------------------------
 | Response: errore normalizzato
 * ---------------------------------------------------------------------- */
http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status ?? 0
    const payload = error.response?.data ?? {}
    const url = error.config?.url ?? ''

    // Errore in forma canonica: la UI legge sempre le stesse proprieta'
    const normalized = {
      status,
      message: payload.message || fallbackMessage(status),
      // { campo: ["messaggio", ...] } dalle FormRequest Laravel
      errors: payload.errors || {},
      // Primo messaggio utile da mostrare in un toast
      firstError: firstErrorOf(payload),
      raw: error,
    }

    /*
      401: token scaduto o revocato -> sessione chiusa e ritorno al login.
      Si esclude /auth/login: li' un 401 significa "credenziali sbagliate",
      non "sessione scaduta", e non deve provocare un redirect.
    */
    if (status === 401 && !url.includes('/auth/login')) {
      authStoreRef?.clearSession?.()
      onUnauthorized?.()
    }

    return Promise.reject(normalized)
  },
)

function firstErrorOf(payload) {
  const errors = payload?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors)[0]
    if (Array.isArray(first) && first.length) return first[0]
  }
  return payload?.message || null
}

function fallbackMessage(status) {
  switch (status) {
    case 0:
      return 'Server non raggiungibile. Verifica che il backend Laravel sia avviato.'
    case 401:
      return 'Sessione scaduta. Effettua di nuovo l\'accesso.'
    case 403:
      return 'Non hai i permessi per questa operazione.'
    case 404:
      return 'Risorsa non trovata.'
    case 422:
      return 'I dati inviati non sono validi.'
    case 429:
      return 'Troppe richieste. Riprova tra qualche istante.'
    case 500:
      return 'Errore interno del server.'
    default:
      return 'Si e\' verificato un errore imprevisto.'
  }
}

export default http
