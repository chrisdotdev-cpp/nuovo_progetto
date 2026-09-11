import { ref } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'
import { useDashboardStore } from '@/store/dashboard'
import { useNotificationStore } from '@/store/notifications'

/**
 * Fatturazione e incassi.
 * Tutti gli importi sono calcolati dal backend: qui non si fa aritmetica sui soldi.
 */
export function useFatture(initialFilters = {}) {
  const resource = useResource(api.invoices, {
    label: 'Fattura',
    labelPlural: 'Fatture',
    perPage: 20,
    defaultFilters: { status: '', from: '', to: '', unpaid: false, patient_id: '', ...initialFilters },
  })

  const report = ref({})
  const { loading: loadingReport, run } = useApiRequest()

  const dashboard = useDashboardStore()
  const notifications = useNotificationStore()

  /**
   * Registra un incasso: lo stato della fattura si aggiorna lato server.
   *
   * L'invalidazione della dashboard sta qui e non nelle viste: un incasso puo'
   * partire da Pagamenti (paziente) o dal Finanziario (admin) e in entrambi i
   * casi gli aggregati di Panoramica (da_incassare, incassato_mese) non sono
   * piu' validi. Centralizzarla evita che una nuova vista se ne dimentichi.
   */
  async function paga(invoiceId, payload) {
    const esito = await resource.run(() => api.invoices.pay(invoiceId, payload), {
      successMessage: 'Pagamento registrato.',
      onSuccess: (data) => {
        const updated = data?.data ?? data
        if (updated) resource.replace(updated)
      },
    })

    if (esito) {
      // Cache 60s scavalcata: la prossima apertura di Panoramica rilegge i KPI
      dashboard.invalidate()
      // Il backend ha appena creato la notifica di incasso: allinea il badge
      notifications.fetchUnreadCount().catch(() => {})
    }

    return esito
  }

  /** Report finanziario (solo amministrazione). */
  async function fetchReport(params = {}) {
    await run(() => api.invoices.report(params), {
      showToast: false,
      onSuccess: (payload) => {
        report.value = payload?.data ?? {}
      },
    })

    return report.value
  }

  const statusClass = (status) =>
    ({
      pagata: 'bg-green-100 text-green-700',
      emessa: 'bg-blue-100 text-blue-700',
      parziale: 'bg-amber-100 text-amber-700',
      scaduta: 'bg-red-100 text-red-700',
      stornata: 'bg-gray-200 text-gray-600',
      bozza: 'bg-gray-100 text-gray-600',
    })[status] ?? 'bg-gray-100 text-gray-700'

  const statusLabel = (status) =>
    ({
      pagata: 'Pagata',
      emessa: 'Da pagare',
      parziale: 'Parziale',
      scaduta: 'Scaduta',
      stornata: 'Stornata',
      bozza: 'Bozza',
    })[status] ?? status

  return { ...resource, report, loadingReport, paga, fetchReport, statusClass, statusLabel }
}
