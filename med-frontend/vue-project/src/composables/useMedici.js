import { ref } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'

/**
 * Elenco medici: usato dal paziente per prenotare e dall'admin per la gestione.
 */
export function useMedici(initialFilters = {}) {
  const resource = useResource(api.doctors, {
    label: 'Medico',
    labelPlural: 'Medici',
    perPage: 50, // l'elenco medici è breve: si carica in una pagina sola
    defaultFilters: { q: '', specialization: '', online_only: false, ...initialFilters },
  })

  const specializzazioni = ref([])
  const { run } = useApiRequest()

  /** Popola i filtri senza valori hardcoded in Vue. */
  async function fetchSpecializzazioni() {
    await run(() => api.doctors.specializations(), {
      showToast: false,
      onSuccess: (payload) => {
        specializzazioni.value = payload?.data ?? []
      },
    })

    return specializzazioni.value
  }

  /** Aggiornamento orario settimanale (solo medico proprietario o admin). */
  async function salvaOrari(doctorId, schedules) {
    return resource.update(doctorId, { schedules })
  }

  return { ...resource, specializzazioni, fetchSpecializzazioni, salvaOrari }
}
