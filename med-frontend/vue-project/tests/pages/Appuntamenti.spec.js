import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { toast } from 'vue3-toastify'

import Appuntamenti from '@/views/paziente/Appuntamenti.vue'
import { axiosOk, apiError, validationError, medico, appuntamento, paginata, flush } from '../helpers'

/*
  Come per il login si sostituisce il client HTTP e non il modulo API: restano
  sotto test gli URL reali, la pulizia dei parametri di query e la forma dei
  payload, cioe' esattamente il punto di contatto con Laravel.
*/
const { httpMock } = vi.hoisted(() => ({
  httpMock: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/services/http', () => ({
  default: httpMock,
  registerAuthStore: vi.fn(),
  registerUnauthorizedHandler: vi.fn(),
}))

/* -------------------------------------------------------------------------
 | Dati di scena
 * ---------------------------------------------------------------------- */

const CARDIOLOGA = medico({ id: 7, name: 'Dott.ssa Anna Bianchi', specialization: 'Cardiologia' })
const ORTOPEDICO = medico({
  id: 9,
  name: 'Dott. Marco Gialli',
  specialization: 'Ortopedia',
  slot_duration: 20,
  available_online: false,
})

const SLOT = [
  { start: '2030-09-10T09:00:00+02:00', end: '2030-09-10T09:30:00+02:00', label: '09:00', available: false },
  { start: '2030-09-10T09:30:00+02:00', end: '2030-09-10T10:00:00+02:00', label: '09:30', available: true },
  { start: '2030-09-10T10:00:00+02:00', end: '2030-09-10T10:30:00+02:00', label: '10:00', available: true },
]

describe('Prenotazione visite (paziente)', () => {
  /**
   * Le tre chiamate del mount passano tutte da http.get: si smista sull'URL.
   * `overrides` permette a un singolo test di cambiare una sola risposta senza
   * riscrivere le altre due.
   */
  const impostaGet = (overrides = {}) => {
    const risposte = {
      '/doctors': () => axiosOk(paginata([CARDIOLOGA, ORTOPEDICO])),
      '/doctors/specializations': () => axiosOk({ data: ['Cardiologia', 'Ortopedia'] }),
      '/appointments': () => axiosOk(paginata([appuntamento()])),
      availability: () => axiosOk({ date: '2030-09-10', doctor: CARDIOLOGA, slots: SLOT }),
      ...overrides,
    }

    httpMock.get.mockImplementation((url) => {
      const chiave = url.includes('/availability') ? 'availability' : url
      const risposta = risposte[chiave]

      if (!risposta) return Promise.resolve(axiosOk({}))
      return risposta()
    })
  }

  const monta = async () => {
    const wrapper = mount(Appuntamenti, { global: { plugins: [createPinia()] } })
    await flush(5)
    return wrapper
  }

  /** Card dei medici: sono gli unici elementi con role="button". */
  const cardMedici = (wrapper) => wrapper.findAll('[role="button"]')

  const bottoneCon = (wrapper, testo) =>
    wrapper.findAll('button').find((b) => b.text().includes(testo))

  /** Percorso completo fino al form compilato e pronto all'invio. */
  const compilaPrenotazione = async (wrapper, orario = '09:30') => {
    await cardMedici(wrapper)[0].trigger('click')

    const data = wrapper.find('#booking-date')
    await data.setValue('2030-09-10')
    await data.trigger('change')
    await flush(4)

    await bottoneCon(wrapper, orario).trigger('click')
    await flush()

    return wrapper
  }

  beforeEach(() => {
    impostaGet()
    httpMock.post.mockResolvedValue(axiosOk({ data: appuntamento() }))
  })

  /* =====================================================================
   | Caricamento iniziale
   * ===================================================================*/

  it('al mount carica medici, specializzazioni e appuntamenti futuri', async () => {
    await monta()

    expect(httpMock.get).toHaveBeenCalledWith('/doctors', {
      params: expect.objectContaining({ per_page: 50, page: 1 }),
    })
    expect(httpMock.get).toHaveBeenCalledWith('/doctors/specializations')

    // Il paziente parte dai propri appuntamenti in arrivo
    expect(httpMock.get).toHaveBeenCalledWith('/appointments', {
      params: expect.objectContaining({ scope: 'upcoming' }),
    })
  })

  it('mostra l elenco dei medici e i propri appuntamenti', async () => {
    const wrapper = await monta()

    expect(wrapper.text()).toContain('Dott.ssa Anna Bianchi')
    expect(wrapper.text()).toContain('Cardiologia')
    expect(wrapper.text()).toContain('Dott. Marco Gialli')

    // Appuntamento gia' presente in agenda
    expect(wrapper.text()).toContain('10/09/2030')
    expect(wrapper.text()).toContain('In attesa')
  })

  /* =====================================================================
   | Stati vuoti: tre cause diverse, tre messaggi diversi
   * ===================================================================*/

  it('distingue una chiamata fallita da un elenco vuoto', async () => {
    impostaGet({
      '/doctors': () =>
        Promise.reject(apiError({ status: 403, message: 'Non hai i permessi per questa operazione.' })),
    })

    const wrapper = await monta()

    expect(wrapper.text()).toContain("Impossibile caricare l'elenco medici.")
    expect(wrapper.text()).toContain('Non hai i permessi per questa operazione.')
    expect(wrapper.text()).not.toContain('Nessun medico corrisponde alla ricerca.')
  })

  it('il pulsante Riprova ripete la chiamata', async () => {
    impostaGet({
      '/doctors': () => Promise.reject(apiError({ status: 500, message: 'Errore interno del server.' })),
    })

    const wrapper = await monta()
    const chiamatePrima = httpMock.get.mock.calls.filter(([url]) => url === '/doctors').length

    await bottoneCon(wrapper, 'Riprova').trigger('click')
    await flush(3)

    const chiamateDopo = httpMock.get.mock.calls.filter(([url]) => url === '/doctors').length
    expect(chiamateDopo).toBe(chiamatePrima + 1)
  })

  it('un archivio medici vuoto indica un problema di configurazione', async () => {
    impostaGet({ '/doctors': () => axiosOk(paginata([])) })

    const wrapper = await monta()

    expect(wrapper.text()).toContain('Nessun medico disponibile.')
    expect(wrapper.text()).toContain("Contatta l'amministrazione.")
  })

  it('un filtro senza risultati propone di azzerare la ricerca', async () => {
    const wrapper = await monta()

    await wrapper.find('input[type="search"]').setValue('Neurochirurgia')
    await flush()

    expect(wrapper.text()).toContain('Nessun medico corrisponde alla ricerca.')

    await bottoneCon(wrapper, 'Azzera i filtri').trigger('click')
    await flush()

    expect(wrapper.text()).toContain('Dott.ssa Anna Bianchi')
  })

  /* =====================================================================
   | Filtri lato client
   * ===================================================================*/

  it('la ricerca filtra senza interrogare di nuovo il server', async () => {
    const wrapper = await monta()
    const chiamatePrima = httpMock.get.mock.calls.length

    await wrapper.find('input[type="search"]').setValue('gialli')
    await flush()

    /*
      L'asserzione va ristretta alle card: il nome di un medico compare anche
      nella lista "I miei appuntamenti" piu' in basso, che il filtro di ricerca
      non tocca. Guardare wrapper.text() dell'intera pagina farebbe fallire il
      test per un motivo che non c'entra con il filtro.
    */
    const nomiInElenco = cardMedici(wrapper).map((card) => card.text())

    expect(nomiInElenco).toHaveLength(1)
    expect(nomiInElenco[0]).toContain('Dott. Marco Gialli')
    expect(nomiInElenco.join(' ')).not.toContain('Dott.ssa Anna Bianchi')

    // Il filtro e' client-side: nessuna nuova chiamata al backend
    expect(httpMock.get.mock.calls.length).toBe(chiamatePrima)
  })

  it('la ricerca trova anche per specializzazione', async () => {
    const wrapper = await monta()

    await wrapper.find('input[type="search"]').setValue('cardio')
    await flush()

    const nomiInElenco = cardMedici(wrapper).map((card) => card.text())

    expect(nomiInElenco).toHaveLength(1)
    expect(nomiInElenco[0]).toContain('Dott.ssa Anna Bianchi')
    expect(nomiInElenco[0]).toContain('Cardiologia')
  })

  it('il menu specializzazioni si popola dal backend', async () => {
    const wrapper = await monta()

    const opzioni = wrapper.find('select').findAll('option')
    expect(opzioni.map((o) => o.text())).toEqual([
      'Tutte le specializzazioni',
      'Cardiologia',
      'Ortopedia',
    ])

    await wrapper.find('select').setValue('Ortopedia')
    await flush()

    const nomiInElenco = cardMedici(wrapper).map((card) => card.text())

    expect(nomiInElenco).toHaveLength(1)
    expect(nomiInElenco[0]).toContain('Dott. Marco Gialli')
  })

  /* =====================================================================
   | Selezione del medico
   * ===================================================================*/

  it('senza medico selezionato il pannello invita a sceglierne uno', async () => {
    const wrapper = await monta()

    expect(wrapper.text()).toContain('Seleziona un medico per continuare')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('selezionare un medico apre il form di prenotazione', async () => {
    const wrapper = await monta()

    await cardMedici(wrapper)[0].trigger('click')

    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('#booking-date').exists()).toBe(true)
    expect(cardMedici(wrapper)[0].attributes('aria-pressed')).toBe('true')
  })

  it('il teleconsulto compare solo se il medico lo ha abilitato', async () => {
    const wrapper = await monta()

    await cardMedici(wrapper)[0].trigger('click') // cardiologa: online abilitato
    let tipi = wrapper.find('#booking-type').findAll('option').map((o) => o.text())
    expect(tipi).toContain('Telemedicina')

    await cardMedici(wrapper)[1].trigger('click') // ortopedico: solo in sede
    tipi = wrapper.find('#booking-type').findAll('option').map((o) => o.text())
    expect(tipi).not.toContain('Telemedicina')
  })

  it('cambiando medico si azzerano data e slot gia scelti', async () => {
    const wrapper = await monta()
    await compilaPrenotazione(wrapper)

    await cardMedici(wrapper)[1].trigger('click')
    await flush()

    expect(wrapper.find('#booking-date').element.value).toBe('')
    expect(wrapper.text()).toContain('Seleziona prima una data.')
  })

  /* =====================================================================
   | Slot disponibili
   * ===================================================================*/

  it('la scelta della data interroga l agenda reale del medico', async () => {
    const wrapper = await monta()

    await cardMedici(wrapper)[0].trigger('click')
    const data = wrapper.find('#booking-date')
    await data.setValue('2030-09-10')
    await data.trigger('change')
    await flush(3)

    expect(httpMock.get).toHaveBeenCalledWith('/doctors/7/availability', {
      params: { date: '2030-09-10' },
    })
  })

  it('gli slot occupati non sono cliccabili', async () => {
    const wrapper = await monta()

    await cardMedici(wrapper)[0].trigger('click')
    const data = wrapper.find('#booking-date')
    await data.setValue('2030-09-10')
    await data.trigger('change')
    await flush(3)

    expect(bottoneCon(wrapper, '09:00').attributes('disabled')).toBeDefined()
    expect(bottoneCon(wrapper, '09:30').attributes('disabled')).toBeUndefined()
  })

  it('una giornata senza agenda lo dice esplicitamente', async () => {
    impostaGet({ availability: () => axiosOk({ date: '2030-09-10', slots: [] }) })

    const wrapper = await monta()

    await cardMedici(wrapper)[0].trigger('click')
    const data = wrapper.find('#booking-date')
    await data.setValue('2030-09-10')
    await data.trigger('change')
    await flush(3)

    expect(wrapper.text()).toContain('Il medico non riceve in questa data.')
  })

  /* =====================================================================
   | Invio della prenotazione
   * ===================================================================*/

  it('non si prenota finche medico, data e orario non sono scelti', async () => {
    const wrapper = await monta()

    await cardMedici(wrapper)[0].trigger('click')
    await wrapper.find('form').trigger('submit')
    await flush()

    expect(httpMock.post).not.toHaveBeenCalled()
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('invia la prenotazione con la durata dello slot del medico', async () => {
    const wrapper = await monta()
    await compilaPrenotazione(wrapper)

    await wrapper.find('#booking-type').setValue('controllo')
    await wrapper.find('#booking-reason').setValue('Dolore al petto')

    await wrapper.find('form').trigger('submit')
    await flush(4)

    expect(httpMock.post).toHaveBeenCalledWith('/appointments', {
      doctor_id: 7,
      scheduled_at: '2030-09-10T09:30:00+02:00',
      duration_minutes: 30,
      type: 'controllo',
      reason: 'Dolore al petto',
    })
  })

  it('un motivo lasciato vuoto viene inviato come null, non come stringa vuota', async () => {
    const wrapper = await monta()
    await compilaPrenotazione(wrapper)

    await wrapper.find('form').trigger('submit')
    await flush(4)

    expect(httpMock.post).toHaveBeenCalledWith(
      '/appointments',
      expect.objectContaining({ reason: null, type: 'visita' }),
    )
  })

  it('dopo la conferma azzera il form e ricarica l agenda', async () => {
    const wrapper = await monta()
    await compilaPrenotazione(wrapper)

    const appuntamentiPrima = httpMock.get.mock.calls.filter(([u]) => u === '/appointments').length

    await wrapper.find('form').trigger('submit')
    await flush(5)

    expect(toast.success).toHaveBeenCalledWith(
      'Appuntamento creato con successo.',
      expect.any(Object),
    )

    // Si torna allo stato iniziale del pannello
    expect(wrapper.text()).toContain('Seleziona un medico per continuare')
    expect(wrapper.find('form').exists()).toBe(false)

    const appuntamentiDopo = httpMock.get.mock.calls.filter(([u]) => u === '/appointments').length
    expect(appuntamentiDopo).toBe(appuntamentiPrima + 1)
  })

  /**
   * Lo slot viene occupato da un altro paziente fra il caricamento e l'invio:
   * Laravel risponde 422 su scheduled_at. La UI deve dirlo e rimettere in pagina
   * le disponibilita' aggiornate, non lasciare il form come se nulla fosse.
   */
  it('slot occupato nel frattempo: mostra il motivo e ricarica gli orari', async () => {
    const wrapper = await monta()
    await compilaPrenotazione(wrapper)

    httpMock.post.mockRejectedValueOnce(
      validationError('scheduled_at', "Lo slot selezionato non e' piu' disponibile."),
    )

    const availabilityPrima = httpMock.get.mock.calls.filter(([u]) =>
      u.includes('/availability'),
    ).length

    await wrapper.find('form').trigger('submit')
    await flush(5)

    expect(wrapper.text()).toContain("Lo slot selezionato non e' piu' disponibile.")

    // Il medico resta selezionato: l'utente deve solo riscegliere l'orario
    expect(wrapper.find('form').exists()).toBe(true)

    const availabilityDopo = httpMock.get.mock.calls.filter(([u]) =>
      u.includes('/availability'),
    ).length
    expect(availabilityDopo).toBe(availabilityPrima + 1)
  })

  /**
   * Account senza record in `patients`: prepareForValidation non ricava
   * patient_id e il backend risponde 422. Prima l'errore veniva scartato in
   * silenzio e il pulsante sembrava semplicemente non funzionare.
   */
  it('profilo paziente mancante: la causa e scritta a video', async () => {
    const wrapper = await monta()
    await compilaPrenotazione(wrapper)

    httpMock.post.mockRejectedValueOnce(
      validationError('patient_id', 'Profilo paziente non trovato per questo account.'),
    )

    await wrapper.find('form').trigger('submit')
    await flush(5)

    expect(wrapper.text()).toContain('Profilo paziente non trovato per questo account.')
  })

  /* =====================================================================
   | Lista appuntamenti e annullamento
   * ===================================================================*/

  it('il passaggio a Passati richiede al backend lo scope corrispondente', async () => {
    const wrapper = await monta()

    await bottoneCon(wrapper, 'Passati').trigger('click')
    await flush(3)

    expect(httpMock.get).toHaveBeenCalledWith('/appointments', {
      params: expect.objectContaining({ scope: 'past', page: 1 }),
    })
  })

  it('apre il modal di annullamento con i dati della visita', async () => {
    const wrapper = await monta()

    await wrapper.findAll('button').find((b) => b.text().trim() === 'Annulla').trigger('click')
    await flush()

    const modal = wrapper.find('[role="dialog"]')
    expect(modal.exists()).toBe(true)
    expect(modal.text()).toContain("Annullare l'appuntamento?")
    expect(modal.text()).toContain('Dott.ssa Anna Bianchi')
    expect(modal.text()).toContain('10:00')
  })

  it('conferma l annullamento inviando il motivo', async () => {
    httpMock.post.mockResolvedValue(
      axiosOk({ data: appuntamento({ status: 'annullato', is_editable: false }) }),
    )

    const wrapper = await monta()

    await wrapper.findAll('button').find((b) => b.text().trim() === 'Annulla').trigger('click')
    await flush()

    await wrapper.find('#cancel-reason').setValue('Imprevisto di lavoro')
    await bottoneCon(wrapper, 'Annulla appuntamento').trigger('click')
    await flush(4)

    expect(httpMock.post).toHaveBeenCalledWith('/appointments/100/cancel', {
      reason: 'Imprevisto di lavoro',
    })

    // Il modal si chiude e la riga si aggiorna con il nuovo stato
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Annullato')
  })

  it('il modal si chiude senza annullare nulla se si torna indietro', async () => {
    const wrapper = await monta()

    await wrapper.findAll('button').find((b) => b.text().trim() === 'Annulla').trigger('click')
    await flush()

    await bottoneCon(wrapper, 'Torna indietro').trigger('click')
    await flush()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(httpMock.post).not.toHaveBeenCalled()
  })

  it('un appuntamento non piu modificabile non espone il pulsante Annulla', async () => {
    impostaGet({
      '/appointments': () =>
        axiosOk(paginata([appuntamento({ status: 'completato', is_editable: false })])),
    })

    const wrapper = await monta()

    expect(wrapper.findAll('button').some((b) => b.text().trim() === 'Annulla')).toBe(false)
    expect(wrapper.text()).toContain('Completato')
  })

  it('senza appuntamenti mostra lo stato vuoto della sezione', async () => {
    impostaGet({ '/appointments': () => axiosOk(paginata([])) })

    const wrapper = await monta()

    expect(wrapper.text()).toContain('Non hai appuntamenti in programma.')
  })
})
