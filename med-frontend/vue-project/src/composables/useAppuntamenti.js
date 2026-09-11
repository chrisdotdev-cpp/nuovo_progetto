import { ref, computed } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'
import { useDashboardStore } from '@/store/dashboard'

/**
 * Appuntamenti: lista filtrata, slot disponibili e azioni di prenotazione.
 * Il backend filtra già per ruolo, qui restano solo i filtri di interfaccia.
 */
export function useAppuntamenti(initialFilters = {}) {
  const resource = useResource(api.appointments, {
    label: 'Appuntamento',
    labelPlural: 'Appuntamenti',
    perPage: 20,
    defaultFilters: {
      status: '',
      type: '',
      scope: '',        // upcoming | past | ''
      doctor_id: '',
      patient_id: '',
      from: '',
      to: '',
      ...initialFilters,
    },
  })

  /* ---------------- Slot disponibili ---------------- */
  const slots = ref([])
  const { loading: loadingSlots, run: runSlots } = useApiRequest()

  const availableSlots = computed(() => slots.value.filter((slot) => slot.available))

  /** Slot di un medico in una data (YYYY-MM-DD). */
  async function fetchSlots(doctorId, date) {
    slots.value = []

    if (!doctorId || !date) return []

    await runSlots(() => api.doctors.availability(doctorId, date), {
      showToast: false,
      onSuccess: (payload) => {
        slots.value = payload?.slots ?? []
      },
    })

    return slots.value
  }

  /* ---------------- Azioni ---------------- */

  /** Prenotazione: gli errori di slot occupato arrivano come validazione su scheduled_at. */
  async function prenota(payload) {
    return resource.create(payload)
  }

  /** Spostamento: stesso endpoint di update, il backend ricontrolla la disponibilità. */
  async function sposta(id, scheduledAt, durationMinutes = null) {
    return resource.update(id, {
      scheduled_at: scheduledAt,
      ...(durationMinutes ? { duration_minutes: durationMinutes } : {}),
    })
  }

  async function annulla(id, motivo = null) {
    return resource.run(() => api.appointments.cancel(id, motivo), {
      successMessage: 'Appuntamento annullato.',
      onSuccess: (payload) => {
        const updated = payload?.data ?? payload
        if (updated) resource.replace(updated)
      },
    })
  }

  /**
   * Cambio stato riservato a medico e amministrazione.
   *
   * Chiudere una visita ha un effetto contabile: il backend emette la fattura,
   * quindi "da incassare" e "fatture da saldare" della Panoramica admin non
   * sono piu' aggiornati. Si invalida la cache, senza ricaricare: chi sta
   * lavorando in agenda non ha bisogno di una chiamata in piu' in quel momento.
   */
  async function cambiaStato(id, status) {
    const esito = await resource.update(id, { status })

    if (esito && ['completato', 'assente', 'annullato'].includes(status)) {
      useDashboardStore().invalidate()
    }

    return esito
  }

  /* ---------------- Derivati per la UI ---------------- */

  const prossimi = computed(() =>
    resource.items.value
      .filter((a) => new Date(a.scheduled_at) >= new Date() && a.status !== 'annullato')
      .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at)),
  )

  const passati = computed(() =>
    resource.items.value
      .filter((a) => new Date(a.scheduled_at) < new Date())
      .sort((a, b) => new Date(b.scheduled_at) - new Date(a.scheduled_at)),
  )

  /** Raggruppati per giorno: usato da agenda e liste mobile. */
  const perGiorno = computed(() =>
    resource.items.value.reduce((acc, appointment) => {
      const day = appointment.scheduled_at?.slice(0, 10)
      if (!day) return acc
      ;(acc[day] ||= []).push(appointment)
      return acc
    }, {}),
  )

  /** Colori dei badge di stato, coerenti in tutta l'applicazione. */
  const statusClass = (status) =>
    ({
      in_attesa: 'bg-amber-100 text-amber-700',
      confermato: 'bg-green-100 text-green-700',
      completato: 'bg-blue-100 text-blue-700',
      annullato: 'bg-red-100 text-red-700',
      assente: 'bg-gray-200 text-gray-600',
    })[status] ?? 'bg-gray-100 text-gray-700'

  const statusLabel = (status) =>
    ({
      in_attesa: 'In attesa',
      confermato: 'Confermato',
      completato: 'Completato',
      annullato: 'Annullato',
      assente: 'Non presentato',
    })[status] ?? status

  return {
    ...resource,
    slots, availableSlots, loadingSlots, fetchSlots,
    prenota, sposta, annulla, cambiaStato,
    prossimi, passati, perGiorno,
    statusClass, statusLabel,
  }
}
