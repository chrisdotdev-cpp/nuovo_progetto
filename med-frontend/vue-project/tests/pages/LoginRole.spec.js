import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { toast } from 'vue3-toastify'

import LoginRole from '@/views/home/LoginRole.vue'
import { useAuthStore } from '@/store/auth.js'
import { axiosOk, apiError, validationError, utente, flush } from '../helpers'

/*
  Si sostituisce il client HTTP, non il modulo API.

  Cosi' la catena reale resta sotto test: componente -> store -> services/api ->
  http. E' l'unico modo per verificare che il login colpisca davvero
  POST /auth/login e che device_name venga aggiunto (senza, Sanctum crea un
  token per dispositivo diverso a ogni accesso e il logout non revoca nulla).
*/
const { httpMock } = vi.hoisted(() => ({
  httpMock: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/services/http', () => ({
  default: httpMock,
  registerAuthStore: vi.fn(),
  registerUnauthorizedHandler: vi.fn(),
}))

/* Il router e' fuori dal perimetro di questa pagina: servono solo le spie. */
const { routerMock, routeMock } = vi.hoisted(() => ({
  routerMock: { push: vi.fn(), replace: vi.fn() },
  routeMock: { params: { role: 'paziente' }, query: {} },
}))

vi.mock('vue-router', () => ({
  useRouter: () => routerMock,
  useRoute: () => routeMock,
}))

describe('LoginRole', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    routeMock.params = { role: 'paziente' }
    routeMock.query = {}
  })

  const monta = () => mount(LoginRole)

  /** Compila il form e invia. */
  const compilaEInvia = async (wrapper, email, password) => {
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue(email)
    await inputs[1].setValue(password)
    await wrapper.find('form').trigger('submit')
    await flush()
  }

  const rispostaLogin = (over = {}) =>
    axiosOk({
      token: 'token-123',
      expires_at: '2030-01-01T12:00:00+01:00',
      user: utente(),
      redirect: '/paziente/panoramica',
      ...over,
    })

  /* =====================================================================
   | Render
   * ===================================================================*/

  it('mostra il titolo del ruolo scelto', () => {
    routeMock.params = { role: 'medico' }

    const wrapper = monta()

    expect(wrapper.text()).toContain('Accedi come Medico')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('un ruolo inesistente nell URL riporta alla scelta', () => {
    routeMock.params = { role: 'sconosciuto' }

    monta()

    expect(routerMock.replace).toHaveBeenCalledWith('/')
  })

  it('il pulsante Indietro torna alla home', async () => {
    const wrapper = monta()

    await wrapper.find('button[type="button"]').trigger('click')

    expect(routerMock.push).toHaveBeenCalledWith('/')
  })

  /* =====================================================================
   | Validazione lato client
   * ===================================================================*/

  it('non chiama il backend se i campi sono vuoti', async () => {
    const wrapper = monta()

    await wrapper.find('form').trigger('submit')
    await flush()

    expect(httpMock.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain("L'email e' obbligatoria.")
    expect(wrapper.text()).toContain("La password e' obbligatoria.")
    expect(wrapper.find('[role="alert"]').text()).toContain('Controlla i campi evidenziati.')
  })

  it('rifiuta un indirizzo email malformato', async () => {
    const wrapper = monta()

    await compilaEInvia(wrapper, 'non-una-email', 'password')

    expect(httpMock.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Inserisci un indirizzo email valido.')
  })

  /* =====================================================================
   | Login riuscito
   * ===================================================================*/

  it('invia credenziali, ruolo e device_name al backend', async () => {
    httpMock.post.mockResolvedValue(rispostaLogin())

    const wrapper = monta()
    await compilaEInvia(wrapper, '  mario.rossi@example.it  ', 'password')

    expect(httpMock.post).toHaveBeenCalledWith('/auth/login', {
      // l'email viene ripulita dagli spazi prima dell'invio
      email: 'mario.rossi@example.it',
      password: 'password',
      role: 'paziente',
      device_name: 'web',
    })
  })

  it('apre la sessione e porta l utente sulla rotta decisa dal backend', async () => {
    httpMock.post.mockResolvedValue(rispostaLogin())

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'password')

    const auth = useAuthStore()
    expect(auth.token).toBe('token-123')
    expect(auth.isAuthenticated).toBe(true)

    expect(toast.success).toHaveBeenCalledWith(
      expect.stringContaining('Mario Rossi'),
      expect.any(Object),
    )

    // replace e non push: il tasto indietro non deve riportare al form
    expect(routerMock.replace).toHaveBeenCalledWith('/paziente/panoramica')
    expect(routerMock.push).not.toHaveBeenCalled()
  })

  /**
   * Se l'utente era stato respinto dalla guard, dopo il login deve tornare dove
   * stava andando invece di atterrare sulla panoramica.
   */
  it('rispetta il redirect memorizzato dalla guard', async () => {
    routeMock.query = { redirect: '/paziente/appuntamenti' }
    httpMock.post.mockResolvedValue(rispostaLogin())

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'password')

    expect(routerMock.replace).toHaveBeenCalledWith('/paziente/appuntamenti')
  })

  /**
   * Un redirect verso un'altra area verrebbe respinto dalla guard un istante
   * dopo: si ignora subito e si usa la destinazione del backend.
   */
  it('ignora un redirect che punta fuori dall area del ruolo', async () => {
    routeMock.query = { redirect: '/admin/utenti' }
    httpMock.post.mockResolvedValue(rispostaLogin())

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'password')

    expect(routerMock.replace).toHaveBeenCalledWith('/paziente/panoramica')
  })

  it('ignora un redirect assoluto verso un dominio esterno', async () => {
    routeMock.query = { redirect: '//evil.example.com' }
    httpMock.post.mockResolvedValue(rispostaLogin())

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'password')

    expect(routerMock.replace).toHaveBeenCalledWith('/paziente/panoramica')
  })

  /* =====================================================================
   | Login fallito
   * ===================================================================*/

  it('mostra le credenziali errate nel riquadro e non naviga', async () => {
    httpMock.post.mockRejectedValue(validationError('email', 'Credenziali non valide.'))

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'sbagliata')

    expect(wrapper.find('[role="alert"]').text()).toContain('Credenziali non valide.')
    expect(routerMock.replace).not.toHaveBeenCalled()
    expect(useAuthStore().isAuthenticated).toBe(false)
  })

  it('riporta il messaggio dell account sospeso', async () => {
    httpMock.post.mockRejectedValue(
      validationError('email', "Account non attivo. Contatta l'amministrazione."),
    )

    const wrapper = monta()
    await compilaEInvia(wrapper, 'sospeso@example.it', 'password')

    expect(wrapper.find('[role="alert"]').text()).toContain('Account non attivo')
  })

  it('spiega che il server non risponde invece di restare in silenzio', async () => {
    httpMock.post.mockRejectedValue(
      apiError({
        status: 0,
        message: 'Server non raggiungibile. Verifica che il backend Laravel sia avviato.',
      }),
    )

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'password')

    expect(wrapper.find('[role="alert"]').text()).toContain('Server non raggiungibile')
  })

  it('segnala il blocco anti brute force', async () => {
    httpMock.post.mockRejectedValue(
      apiError({ status: 429, message: 'Troppe richieste. Riprova tra qualche istante.' }),
    )

    const wrapper = monta()
    await compilaEInvia(wrapper, 'mario.rossi@example.it', 'password')

    expect(wrapper.find('[role="alert"]').text()).toContain('Troppe richieste')
    expect(routerMock.replace).not.toHaveBeenCalled()
  })

  /* =====================================================================
   | Stato di attesa
   * ===================================================================*/

  it('blocca il doppio invio mentre la richiesta e in corso', async () => {
    let sblocca
    httpMock.post.mockReturnValue(new Promise((resolve) => { sblocca = resolve }))

    const wrapper = monta()
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('mario.rossi@example.it')
    await inputs[1].setValue('password')

    await wrapper.find('form').trigger('submit')
    await flush()

    const submit = wrapper.find('button[type="submit"]')
    expect(submit.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Accesso in corso...')

    sblocca(rispostaLogin())
    await flush()

    expect(httpMock.post).toHaveBeenCalledTimes(1)
  })
})
