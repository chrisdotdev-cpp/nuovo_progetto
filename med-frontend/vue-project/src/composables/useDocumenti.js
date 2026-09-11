import { ref } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'
import { useFileDownload } from '@/composables/useFileDownload'

/**
 * Archivio documentale: upload con progresso, firma singola e massiva,
 * conservazione sostitutiva, download autenticato.
 */
export function useDocumenti(initialFilters = {}) {
  const resource = useResource(api.documents, {
    label: 'Documento',
    labelPlural: 'Documenti',
    perPage: 20,
    defaultFilters: { q: '', category: '', status: '', patient_id: '', ...initialFilters },
  })

  const uploadProgress = ref(0)
  const selezionati = ref([])   // id selezionati per la firma massiva
  const contatori = ref({})
  const { run } = useApiRequest()
  const { download, isDownloading } = useFileDownload()

  /* ---------------- Upload ---------------- */
  async function carica(payload) {
    uploadProgress.value = 0

    const result = await resource.run(
      () => api.documents.upload(payload, (percent) => (uploadProgress.value = percent)),
      {
        successMessage: 'Documento caricato.',
        onSuccess: (data) => {
          const created = data?.data ?? data
          if (created) resource.items.value = [created, ...resource.items.value]
        },
      },
    )

    uploadProgress.value = 0
    return result
  }

  /* ---------------- Firma ---------------- */
  async function firma(id, tipo = 'FEQ') {
    return resource.run(() => api.documents.sign(id, tipo), {
      successMessage: 'Documento firmato.',
      onSuccess: (payload) => {
        const updated = payload?.data ?? payload
        if (updated) resource.replace(updated)
      },
    })
  }

  /** Firma massiva: il flusso reale dell'amministratore con molte pratiche. */
  async function firmaSelezionati(tipo = 'FEQ') {
    if (selezionati.value.length === 0) return null

    const result = await resource.run(() => api.documents.signBulk(selezionati.value, tipo), {
      successMessage: `${selezionati.value.length} documenti firmati.`,
    })

    selezionati.value = []
    await resource.fetchAll()
    await fetchContatori()

    return result
  }

  async function archivia(id) {
    return resource.run(() => api.documents.archive(id), {
      successMessage: 'Documento inviato in conservazione.',
      onSuccess: (payload) => {
        const updated = payload?.data ?? payload
        if (updated) resource.replace(updated)
      },
    })
  }

  /* ---------------- Selezione multipla ---------------- */
  const toggleSelezione = (id) => {
    const index = selezionati.value.indexOf(id)
    if (index === -1) selezionati.value.push(id)
    else selezionati.value.splice(index, 1)
  }

  const isSelezionato = (id) => selezionati.value.includes(id)

  const selezionaTutti = (documenti) => {
    const firmabili = documenti.filter((doc) => doc.status === 'da_firmare').map((doc) => doc.id)
    selezionati.value = selezionati.value.length === firmabili.length ? [] : firmabili
  }

  /* ---------------- Contatori e download ---------------- */
  async function fetchContatori() {
    await run(() => api.documents.counters(), {
      showToast: false,
      onSuccess: (payload) => {
        contatori.value = payload?.data ?? {}
      },
    })

    return contatori.value
  }

  /** Il file non è pubblico: si scarica con il token e si consegna al browser. */
  const scarica = (documento) =>
    download(() => api.documents.download(documento.id), documento.original_name ?? documento.title, documento.id)

  const statusClass = (status) =>
    ({
      da_firmare: 'bg-amber-100 text-amber-700',
      firmato: 'bg-green-100 text-green-700',
      in_conservazione: 'bg-blue-100 text-blue-700',
      archiviato: 'bg-gray-200 text-gray-600',
      bozza: 'bg-gray-100 text-gray-600',
    })[status] ?? 'bg-gray-100 text-gray-700'

  const statusLabel = (status) =>
    ({
      da_firmare: 'Da firmare',
      firmato: 'Firmato',
      in_conservazione: 'In conservazione',
      archiviato: 'Archiviato',
      bozza: 'Bozza',
    })[status] ?? status

  return {
    ...resource,
    uploadProgress, selezionati, contatori,
    carica, firma, firmaSelezionati, archivia, fetchContatori, scarica, isDownloading,
    toggleSelezione, isSelezionato, selezionaTutti,
    statusClass, statusLabel,
  }
}
