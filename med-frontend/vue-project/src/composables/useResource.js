import { ref, reactive, computed } from 'vue'
import { useApiRequest } from '@/composables/useApiRequest'

/**
 * CRUD generico su una risorsa REST.
 *
 * Tutte le sezioni del gestionale hanno lo stesso ciclo (lista paginata con filtri,
 * dettaglio, create/update/delete con toast): questa factory lo implementa una volta,
 * i composable di dominio si limitano a configurarla.
 *
 * ATTENZIONE: non tutte le risorse espongono i cinque metodi.
 * `api.documents` non ha create/update (i documenti si caricano con upload(),
 * e lato Laravel la rotta di update non esiste: apiResource(...)->except(['update'])).
 * `api.telemedicine` non ha update/remove (una sessione si avvia, si chiude, non si
 * modifica). Chiamare quei metodi produceva un `client.update is not a function`,
 * un TypeError opaco a runtime. Ora il limite e' esplicito: vedi `supports()`.
 *
 * @param {Object} client  modulo API con almeno list; get/create/update/remove opzionali
 * @param {Object} options etichette per i messaggi e filtri iniziali
 */
export function useResource(client, options = {}) {
  const {
    label = 'Elemento',
    labelPlural = 'Elementi',
    defaultFilters = {},
    perPage = 15,
  } = options

  const items = ref([])
  const current = ref(null)
  const filters = reactive({ ...defaultFilters })

  const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: perPage,
    total: 0,
  })

  /* -----------------------------------------------------------------------
   | Capacita' della risorsa
   |
   | Si rilevano una volta sola alla creazione: il modulo API e' un oggetto
   | statico, non cambia forma a runtime.
   * -------------------------------------------------------------------- */
  const AZIONI = ['list', 'get', 'create', 'update', 'remove']

  const disponibili = new Set(AZIONI.filter((azione) => typeof client?.[azione] === 'function'))

  /**
   * true se la risorsa espone davvero quell'azione.
   * Usalo nei template per non mostrare pulsanti che non possono funzionare:
   *   <button v-if="supports('update')" @click="modifica">Modifica</button>
   */
  const supports = (azione) => disponibili.has(azione)

  /**
   * Restituisce il metodo del client, oppure lancia un errore leggibile.
   * Viene invocato DENTRO run(): l'errore passa dalla gestione standard
   * (toast + errorMessage + ritorno null) invece di rompere il componente.
   */
  const metodo = (azione) => {
    if (!disponibili.has(azione)) {
      const presenti = [...disponibili].join(', ') || 'nessuno'

      // Messaggio per lo sviluppatore: dice cosa manca e cosa c'e' al suo posto
      console.error(
        `[useResource] "${label}": il modulo API non espone ${azione}(). ` +
          `Metodi disponibili: ${presenti}. ` +
          `Se l'azione esiste con un altro nome (es. upload() per i documenti) chiamala direttamente, ` +
          `altrimenti verifica che la rotta esista lato Laravel.`,
      )

      throw new Error(`Azione "${azione}" non disponibile per ${label}.`)
    }

    return client[azione]
  }

  /*
    Due istanze distinte: le letture non devono attivare lo stato di salvataggio
    e, soprattutto, una create non deve far comparire lo skeleton della lista.
    Gli errori di validazione arrivano sempre dalla richiesta di mutazione.
  */
  const lettura = useApiRequest()
  const mutazione = useApiRequest()

  const loading = lettura.loading
  const saving = mutazione.loading
  const errors = mutazione.errors
  const fieldError = mutazione.fieldError
  const reset = mutazione.reset

  /*
    Errore dell'ultima lettura.

    fetchAll() gira con showToast:false - una lista che non carica non deve
    sparare un toast a ogni cambio di filtro. Il rovescio della medaglia e' che
    un 403, un 500 o un backend spento diventavano INDISTINGUIBILI da "nessun
    risultato": l'utente vedeva lo stato vuoto e basta.

    Esponendo loadError il componente puo' mostrare il motivo reale.
  */
  const loadError = lettura.errorMessage

  const isEmpty = computed(() => !loading.value && items.value.length === 0)
  const hasFilters = computed(() =>
    Object.values(filters).some((value) => value !== '' && value !== null && value !== undefined),
  )

  /** Assorbe una risposta paginata di Laravel. */
  const absorb = (payload) => {
    items.value = payload?.data ?? []
    const source = payload?.meta ?? payload
    meta.current_page = source?.current_page ?? 1
    meta.last_page = source?.last_page ?? 1
    meta.per_page = source?.per_page ?? perPage
    meta.total = source?.total ?? items.value.length
  }

  async function fetchAll(page = meta.current_page, extra = {}) {
    return lettura.run(
      () => metodo('list')({ ...filters, ...extra, per_page: perPage, page }),
      {
        showToast: false, // una lista che non carica mostra lo stato vuoto, non un toast
        onSuccess: absorb,
      },
    )
  }

  async function fetchOne(id) {
    return lettura.run(() => metodo('get')(id), {
      onSuccess: (payload) => {
        current.value = payload?.data ?? payload
      },
    })
  }

  async function create(data, { onDone = null } = {}) {
    return mutazione.run(() => metodo('create')(data), {
      successMessage: `${label} creato con successo.`,
      onSuccess: (payload) => {
        const created = payload?.data ?? payload
        // Inserimento ottimistico in testa: la lista si aggiorna senza refetch
        if (created) items.value = [created, ...items.value]
        meta.total += 1
        onDone?.(created)
      },
    })
  }

  async function update(id, data, { onDone = null } = {}) {
    return mutazione.run(() => metodo('update')(id, data), {
      successMessage: `${label} aggiornato.`,
      onSuccess: (payload) => {
        const updated = payload?.data ?? payload
        if (updated) replace(updated)
        onDone?.(updated)
      },
    })
  }

  async function remove(id, { onDone = null } = {}) {
    return mutazione.run(() => metodo('remove')(id), {
      successMessage: `${label} eliminato.`,
      onSuccess: () => {
        items.value = items.value.filter((item) => item.id !== id)
        meta.total = Math.max(0, meta.total - 1)
        if (current.value?.id === id) current.value = null
        onDone?.()
      },
    })
  }

  /** Sostituisce un elemento in lista mantenendo la posizione. */
  function replace(item) {
    const index = items.value.findIndex((existing) => existing.id === item.id)
    if (index !== -1) items.value.splice(index, 1, item)
    if (current.value?.id === item.id) current.value = item
  }

  /** Applica i filtri ripartendo dalla prima pagina. */
  function applyFilters(newFilters = {}) {
    Object.assign(filters, newFilters)
    meta.current_page = 1
    return fetchAll(1)
  }

  function resetFilters() {
    Object.keys(filters).forEach((key) => {
      filters[key] = defaultFilters[key] ?? ''
    })
    meta.current_page = 1
    return fetchAll(1)
  }

  const goToPage = (page) => fetchAll(page)
  const nextPage = () => (meta.current_page < meta.last_page ? fetchAll(meta.current_page + 1) : null)
  const prevPage = () => (meta.current_page > 1 ? fetchAll(meta.current_page - 1) : null)

  return {
    // stato
    items, current, meta, filters, loading, saving, errors, isEmpty, hasFilters, loadError,
    // azioni
    fetchAll, fetchOne, create, update, remove, replace,
    applyFilters, resetFilters, goToPage, nextPage, prevPage,
    // capacita' effettive della risorsa
    supports,
    // validazione e azioni personalizzate dei composable di dominio
    fieldError, resetErrors: reset, run: mutazione.run,
    labelPlural,
  }
}
