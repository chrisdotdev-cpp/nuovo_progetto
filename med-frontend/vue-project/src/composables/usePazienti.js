import { ref } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'

/**
 * Anagrafica pazienti e cartella clinica completa.
 * Il backend restituisce solo gli assistiti del medico autenticato.
 */
export function usePazienti(initialFilters = {}) {
  const resource = useResource(api.patients, {
    label: 'Paziente',
    labelPlural: 'Pazienti',
    perPage: 15,
    defaultFilters: { q: '', city: '', ...initialFilters },
  })

  const timeline = ref(null)
  const { loading: loadingTimeline, run } = useApiRequest()

  /** Cartella completa in una sola chiamata: voci cliniche, prescrizioni, documenti. */
  async function fetchTimeline(patientId) {
    await run(() => api.patients.timeline(patientId), {
      showToast: false,
      onSuccess: (payload) => {
        timeline.value = payload
      },
    })

    return timeline.value
  }

  /** Scheda del paziente autenticato (non serve conoscere il proprio id). */
  async function fetchProprioProfilo() {
    return run(() => api.patients.me(), {
      showToast: false,
      onSuccess: (payload) => {
        resource.current.value = payload?.data ?? payload
      },
    })
  }

  return { ...resource, timeline, loadingTimeline, fetchTimeline, fetchProprioProfilo }
}
