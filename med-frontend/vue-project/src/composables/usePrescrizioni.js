import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'

/**
 * Prescrizioni: il medico emette, il paziente consulta.
 * La numerazione della ricetta è generata dal backend.
 */
export function usePrescrizioni(initialFilters = {}) {
  const resource = useResource(api.prescriptions, {
    label: 'Prescrizione',
    labelPlural: 'Prescrizioni',
    perPage: 20,
    defaultFilters: { q: '', status: '', patient_id: '', ...initialFilters },
  })

  /** Riga farmaco vuota: usata dal modal "Nuova prescrizione". */
  const rigaVuota = () => ({
    medicine_id: null,
    name: '',
    dosage: '',
    frequency: '',
    duration_days: null,
    quantity: 1,
    notes: '',
  })

  async function emetti(payload) {
    // Si scartano le righe lasciate vuote dall'utente
    const items = (payload.items ?? []).filter((item) => item.name?.trim())

    return resource.create({ ...payload, items })
  }

  const annulla = (id) => resource.update(id, { status: 'annullata' })
  const completa = (id) => resource.update(id, { status: 'completata' })

  const statusClass = (status) =>
    ({
      attiva: 'bg-green-100 text-green-700',
      completata: 'bg-blue-100 text-blue-700',
      annullata: 'bg-red-100 text-red-700',
      scaduta: 'bg-gray-200 text-gray-600',
    })[status] ?? 'bg-gray-100 text-gray-700'

  return { ...resource, rigaVuota, emetti, annulla, completa, statusClass }
}
