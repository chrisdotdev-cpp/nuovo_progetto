import { ref } from 'vue'
import { useToast } from '@/composables/useToast'

/**
 * Download di file protetti da token.
 *
 * I documenti clinici non sono file pubblici: non basta un <a href>, serve la
 * chiamata autenticata. Si scarica il blob e lo si consegna al browser.
 */
export function useFileDownload() {
  const downloading = ref(null) // id della risorsa in download, per lo spinner sulla riga
  const toast = useToast()

  /**
   * @param {Function} request  funzione che restituisce la Promise axios con responseType blob
   * @param {String} filename   nome con cui salvare il file
   * @param {Number|String} id  identificativo usato per lo stato di caricamento
   */
  async function download(request, filename = 'documento', id = null) {
    downloading.value = id ?? filename

    try {
      const response = await request()

      const blob = new Blob([response.data], {
        type: response.headers?.['content-type'] || 'application/octet-stream',
      })

      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()

      // Pulizia: senza revoke il blob resta in memoria
      document.body.removeChild(link)
      URL.revokeObjectURL(url)

      return true
    } catch (error) {
      toast.apiError(error, 'Download non riuscito.')
      return false
    } finally {
      downloading.value = null
    }
  }

  const isDownloading = (id) => downloading.value === id

  return { download, downloading, isDownloading }
}
