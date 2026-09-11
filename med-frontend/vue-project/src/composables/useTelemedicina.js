import { ref } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'

/**
 * Teleconsulti: elenco sessioni, ingresso in stanza e chat.
 * La finestra oraria di accesso è decisa dal backend (is_joinable), non dal client.
 */
export function useTelemedicina(initialFilters = {}) {
  const resource = useResource(api.telemedicine, {
    label: 'Sessione',
    labelPlural: 'Sessioni',
    perPage: 20,
    defaultFilters: { status: '', ...initialFilters },
  })

  const sessioneAttiva = ref(null)
  const messaggi = ref([])
  const roomCode = ref(null)
  const pollingId = ref(null)
  const { loading: loadingChat, run } = useApiRequest()

  /** Ingresso in stanza: il codice arriva solo se si è nella finestra consentita. */
  async function entra(sessionId) {
    const payload = await resource.run(() => api.telemedicine.join(sessionId), {
      errorMessage: 'La stanza non è ancora accessibile.',
    })

    if (payload) {
      roomCode.value = payload.room_code
      sessioneAttiva.value = payload.data ?? null
      await fetchMessaggi(sessionId)
      avviaPollingChat(sessionId)
    }

    return payload
  }

  async function avvia(sessionId) {
    return resource.run(() => api.telemedicine.start(sessionId), {
      successMessage: 'Sessione avviata.',
      onSuccess: (payload) => {
        sessioneAttiva.value = payload?.data ?? sessioneAttiva.value
      },
    })
  }

  async function termina(sessionId, note = null) {
    fermaPollingChat()

    return resource.run(() => api.telemedicine.end(sessionId, note), {
      successMessage: 'Sessione conclusa.',
      onSuccess: (payload) => {
        const updated = payload?.data ?? null
        if (updated) resource.replace(updated)
        sessioneAttiva.value = null
        roomCode.value = null
      },
    })
  }

  /* ---------------- Chat ---------------- */
  async function fetchMessaggi(sessionId) {
    await run(() => api.telemedicine.messages(sessionId), {
      showToast: false,
      onSuccess: (payload) => {
        messaggi.value = payload?.data ?? []
      },
    })

    return messaggi.value
  }

  async function inviaMessaggio(sessionId, testo) {
    if (!testo?.trim()) return null

    return run(() => api.telemedicine.sendMessage(sessionId, testo), {
      showToast: false,
      onSuccess: (payload) => {
        const message = payload?.data ?? payload
        if (message) messaggi.value.push(message)
      },
    })
  }

  /**
   * Polling della chat ogni 5 secondi finché la stanza è aperta.
   * Quando ci sarà il broadcasting si sostituisce con un canale websocket.
   */
  function avviaPollingChat(sessionId, intervalMs = 5000) {
    fermaPollingChat()
    pollingId.value = setInterval(() => fetchMessaggi(sessionId).catch(() => {}), intervalMs)
  }

  function fermaPollingChat() {
    if (pollingId.value) {
      clearInterval(pollingId.value)
      pollingId.value = null
    }
  }

  const statusClass = (status) =>
    ({
      programmata: 'bg-blue-100 text-blue-700',
      in_corso: 'bg-green-100 text-green-700',
      terminata: 'bg-gray-200 text-gray-600',
      annullata: 'bg-red-100 text-red-700',
    })[status] ?? 'bg-gray-100 text-gray-700'

  return {
    ...resource,
    sessioneAttiva, messaggi, roomCode, loadingChat,
    entra, avvia, termina, fetchMessaggi, inviaMessaggio, fermaPollingChat,
    statusClass,
  }
}
