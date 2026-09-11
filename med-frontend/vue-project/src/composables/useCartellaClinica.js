import { computed } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'

/**
 * Voci della cartella clinica (referti, diagnosi, esami, note).
 * Scrittura riservata ai medici; il paziente ha sola lettura sulla propria cartella.
 */
export function useCartellaClinica(initialFilters = {}) {
  const resource = useResource(api.records, {
    label: 'Voce clinica',
    labelPlural: 'Voci cliniche',
    perPage: 20,
    defaultFilters: { type: '', patient_id: '', from: '', to: '', ...initialFilters },
  })

  /** Raggruppamento per anno: struttura naturale di una cartella clinica. */
  const perAnno = computed(() =>
    resource.items.value.reduce((acc, record) => {
      const anno = record.recorded_at?.slice(0, 4) ?? 'Senza data'
      ;(acc[anno] ||= []).push(record)
      return acc
    }, {}),
  )

  const typeIcon = (type) =>
    ({
      referto: 'fa-regular fa-file-lines',
      diagnosi: 'fa-solid fa-stethoscope',
      esame: 'fa-solid fa-flask',
      nota: 'fa-regular fa-note-sticky',
      vaccinazione: 'fa-solid fa-syringe',
      intervento: 'fa-solid fa-user-doctor',
    })[type] ?? 'fa-regular fa-file'

  const typeClass = (type) =>
    ({
      referto: 'bg-blue-100 text-blue-700',
      diagnosi: 'bg-purple-100 text-purple-700',
      esame: 'bg-teal-100 text-teal-700',
      nota: 'bg-gray-100 text-gray-700',
      vaccinazione: 'bg-green-100 text-green-700',
      intervento: 'bg-amber-100 text-amber-700',
    })[type] ?? 'bg-gray-100 text-gray-700'

  return { ...resource, perAnno, typeIcon, typeClass }
}
