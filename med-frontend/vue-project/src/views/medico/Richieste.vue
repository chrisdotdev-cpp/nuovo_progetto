<!--
  Richieste di consulto.

  Il paziente apre una richiesta descrivendo il problema, sceglie la priorita'
  (bassa/media/alta) e puo' allegare referti o foto.
  Il medico la prende in carico, risponde con un parere scritto e puo' chiudere
  la pratica convertendola in appuntamento (anche di telemedicina).

  L'ordinamento non e' cronologico ma di triage: lo decide il backend
  (PatientRequest::triageOrder), qui non si riordina nulla lato client.
-->

<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Richieste dei pazienti</h1>
        <p class="text-start text-gray-500">Triage delle richieste di consulto in coda</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 cursor-pointer"
        :disabled="loading"
        @click="fetchAll()"
      >
        <i class="fa-solid fa-rotate-right" :class="loading ? 'fa-spin' : ''"></i>
        Aggiorna
      </button>
    </div>

    <!-- FILTRI -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="filtro-stato" class="block text-gray-700 mb-2 text-sm font-medium">Stato</label>
          <select
            id="filtro-stato"
            v-model="filtri.status"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="aggiornaFiltri"
          >
            <option value="">Tutti gli stati</option>
            <option value="aperta">Aperte</option>
            <option value="in_carico">In carico</option>
            <option value="risposta">Con risposta</option>
            <option value="chiusa">Chiuse</option>
          </select>
        </div>

        <div>
          <label for="filtro-priorita" class="block text-gray-700 mb-2 text-sm font-medium">Priorità</label>
          <select
            id="filtro-priorita"
            v-model="filtri.priority"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="aggiornaFiltri"
          >
            <option value="">Tutte le priorità</option>
            <option value="alta">Alta</option>
            <option value="media">Media</option>
            <option value="bassa">Bassa</option>
          </select>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="space-y-4">
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl border border-gray-200 p-6 animate-pulse">
        <div class="h-4 bg-gray-200 rounded w-1/3 mb-3"></div>
        <div class="h-3 bg-gray-100 rounded w-full mb-2"></div>
        <div class="h-3 bg-gray-100 rounded w-2/3"></div>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div
      v-else-if="isEmpty"
      class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center"
    >
      <i class="fa-regular fa-hand text-6xl text-gray-300 mb-4 block"></i>
      <p class="text-gray-600 font-medium">Nessuna richiesta in coda</p>
      <p class="text-gray-500 text-sm mt-1">
        {{ hasFilters ? 'Prova a rimuovere i filtri attivi.' : 'Le nuove richieste dei pazienti compariranno qui.' }}
      </p>
      <button
        v-if="hasFilters"
        type="button"
        class="mt-4 min-h-[44px] px-4 py-2 text-blue-600 hover:bg-blue-50 rounded-lg"
        @click="resetFilters"
      >
        Rimuovi filtri
      </button>
    </div>

    <!-- LISTA RICHIESTE -->
    <ul v-else class="space-y-4">
      <li
        v-for="richiesta in items"
        :key="richiesta.id"
        class="bg-white rounded-xl shadow-sm border-l-4 border border-gray-200 p-4 sm:p-6"
        :class="bordoPriorita(richiesta.priority)"
      >
        <!-- Intestazione -->
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
          <div class="min-w-0">
            <h2 class="font-semibold text-gray-900 truncate">{{ richiesta.subject }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">
              {{ richiesta.patient?.name || 'Paziente' }}
              <span v-if="richiesta.patient?.age"> · {{ richiesta.patient.age }} anni</span>
              · {{ relativeTime(richiesta.created_at) }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold" :class="classePriorita(richiesta.priority)">
              {{ etichettaPriorita(richiesta.priority) }}
            </span>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="classeStato(richiesta.status)">
              {{ etichettaStato(richiesta.status) }}
            </span>
          </div>
        </div>

        <!-- Descrizione -->
        <p class="text-gray-700 text-sm whitespace-pre-line">{{ richiesta.description }}</p>

        <!-- Allegati -->
        <div v-if="richiesta.attachments?.length" class="mt-3 flex flex-wrap gap-2">
          <button
            v-for="allegato in richiesta.attachments"
            :key="allegato.id"
            type="button"
            class="inline-flex items-center gap-2 px-3 py-2 min-h-[44px] rounded-lg bg-gray-50 border border-gray-200 text-sm hover:bg-gray-100 transition-colors"
            @click="scaricaAllegato(allegato)"
          >
            <i class="fa-regular fa-paperclip"></i>
            <span class="truncate max-w-[180px]">{{ allegato.original_name }}</span>
          </button>
        </div>

        <!-- Risposta già inviata -->
        <div v-if="richiesta.response" class="mt-4 rounded-lg bg-green-50 border border-green-200 p-3">
          <p class="text-xs font-semibold text-green-800 mb-1">
            <i class="fa-solid fa-reply mr-1"></i>
            Risposta inviata {{ relativeTime(richiesta.responded_at) }}
          </p>
          <p class="text-sm text-green-900 whitespace-pre-line">{{ richiesta.response }}</p>
        </div>

        <!-- Esiti collegati -->
        <p v-if="richiesta.appointment_id" class="mt-3 text-sm text-blue-700">
          <i class="fa-regular fa-calendar-check mr-1"></i>
          <template v-if="dataAppuntamento(richiesta)">
            Appuntamento fissato per il {{ dataAppuntamento(richiesta) }}
          </template>
          <template v-else>
            Convertita nell'appuntamento #{{ richiesta.appointment_id }}
          </template>
        </p>

        <!-- AZIONI -->
        <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          <button
            v-if="richiesta.status === 'aperta'"
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-60 cursor-pointer"
            :disabled="saving"
            @click="prendiInCarico(richiesta.id)"
          >
            <i class="fa-solid fa-hand-holding-medical mr-1"></i> Prendi in carico
          </button>

          <button
            v-if="richiesta.status !== 'chiusa'"
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg bg-white border border-gray-300 text-sm font-medium hover:bg-gray-50 cursor-pointer"
            @click="apriRisposta(richiesta)"
          >
            <i class="fa-solid fa-reply mr-1"></i> Rispondi
          </button>

          <button
            v-if="richiesta.status !== 'chiusa' && !richiesta.appointment_id"
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg bg-white border border-gray-300 text-sm font-medium hover:bg-gray-50 cursor-pointer"
            @click="apriConversione(richiesta)"
          >
            <i class="fa-solid fa-video mr-1"></i> Converti in teleconsulto
          </button>
        </div>
      </li>
    </ul>

    <!-- PAGINAZIONE -->
    <div v-if="!loading && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-6">
      <button
        type="button"
        class="min-h-[44px] px-4 py-2 rounded-lg bg-white border border-gray-300 disabled:opacity-40"
        :disabled="meta.current_page <= 1"
        @click="prevPage"
      >
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <span class="text-sm text-gray-600">Pagina {{ meta.current_page }} di {{ meta.last_page }}</span>
      <button
        type="button"
        class="min-h-[44px] px-4 py-2 rounded-lg bg-white border border-gray-300 disabled:opacity-40"
        :disabled="meta.current_page >= meta.last_page"
        @click="nextPage"
      >
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>

    <!-- ===================== MODALE RISPOSTA ===================== -->
    <div
      v-if="modaleRisposta"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModali"
    >
      <div class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold mb-1">Rispondi alla richiesta</h3>
        <p class="text-sm text-gray-500 mb-4 truncate">{{ modaleRisposta.subject }}</p>

        <label for="testo-risposta" class="block text-gray-700 mb-2 font-medium text-sm">Parere clinico</label>
        <textarea
          id="testo-risposta"
          v-model="formRisposta.response"
          rows="5"
          class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          :class="fieldError('response') ? 'border-red-400 bg-red-50/40' : 'border-gray-300'"
          placeholder="Descrivi il parere per il paziente (minimo 5 caratteri)..."
        ></textarea>
        <p v-if="fieldError('response')" class="text-red-600 text-sm mt-1">{{ fieldError('response') }}</p>

        <label for="stato-risposta" class="block text-gray-700 mt-4 mb-2 font-medium text-sm">Stato dopo la risposta</label>
        <select
          id="stato-risposta"
          v-model="formRisposta.status"
          class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg cursor-pointer"
        >
          <option value="risposta">Risposta inviata (resta aperta)</option>
          <option value="chiusa">Chiudi la pratica</option>
        </select>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end mt-6">
          <button
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 cursor-pointer"
            @click="chiudiModali"
          >
            Annulla
          </button>
          <button
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60 cursor-pointer"
            :disabled="saving"
            @click="inviaRisposta"
          >
            <i v-if="saving" class="fa-solid fa-circle-notch fa-spin mr-1"></i>
            Invia risposta
          </button>
        </div>
      </div>
    </div>

    <!-- ===================== MODALE CONVERSIONE ===================== -->
    <div
      v-if="modaleConversione"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModali"
    >
      <div class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold mb-1">Converti in appuntamento</h3>
        <p class="text-sm text-gray-500 mb-4 truncate">{{ modaleConversione.subject }}</p>

        <label for="data-appuntamento" class="block text-gray-700 mb-2 font-medium text-sm">Data e ora</label>
        <input
          id="data-appuntamento"
          v-model="formConversione.scheduled_at"
          type="datetime-local"
          :min="minimoAppuntamento"
          class="w-full min-h-[44px] px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
          :class="fieldError('scheduled_at') ? 'border-red-400 bg-red-50/40' : 'border-gray-300'"
        >
        <p v-if="fieldError('scheduled_at')" class="text-red-600 text-sm mt-1">{{ fieldError('scheduled_at') }}</p>

        <div class="grid grid-cols-2 gap-4 mt-4">
          <div>
            <label for="tipo-appuntamento" class="block text-gray-700 mb-2 font-medium text-sm">Tipo</label>
            <select
              id="tipo-appuntamento"
              v-model="formConversione.type"
              class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg cursor-pointer"
            >
              <option value="telemedicina">Telemedicina</option>
              <option value="visita">Visita</option>
              <option value="controllo">Controllo</option>
              <option value="urgenza">Urgenza</option>
            </select>
          </div>

          <div>
            <label for="durata-appuntamento" class="block text-gray-700 mb-2 font-medium text-sm">Durata (min)</label>
            <input
              id="durata-appuntamento"
              v-model.number="formConversione.duration_minutes"
              type="number"
              min="5"
              max="240"
              step="5"
              class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg"
            >
          </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end mt-6">
          <button
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 cursor-pointer"
            @click="chiudiModali"
          >
            Annulla
          </button>
          <button
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60 cursor-pointer"
            :disabled="saving"
            @click="confermaConversione"
          >
            <i v-if="saving" class="fa-solid fa-circle-notch fa-spin mr-1"></i>
            Crea appuntamento
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useFormatters } from '@/composables/useFormatters'
import { useFileDownload } from '@/composables/useFileDownload'
import { useToast } from '@/composables/useToast'

/*
  useResource fornisce lista paginata, filtri, loading/saving e gli errori 422.
  Le azioni specifiche (claim/respond/convert) passano da resource.run() cosi'
  condividono lo stesso stato di salvataggio e la stessa gestione errori.
*/
const {
  items, meta, filters, loading, saving, isEmpty, hasFilters,
  fetchAll, applyFilters, resetFilters, nextPage, prevPage, replace,
  fieldError, resetErrors, run,
} = useResource(api.requests, {
  label: 'Richiesta',
  labelPlural: 'Richieste',
  perPage: 20,
  defaultFilters: { status: '', priority: '' },
})

const { relativeTime, formatDateTime } = useFormatters()
const { download } = useFileDownload()
const toast = useToast()

const filtri = reactive({ status: '', priority: '' })
const aggiornaFiltri = () => applyFilters({ ...filtri })

/* ---------------------- Modali ---------------------- */
const modaleRisposta = ref(null)
const modaleConversione = ref(null)

const formRisposta = reactive({ response: '', status: 'risposta' })
const formConversione = reactive({ scheduled_at: '', type: 'telemedicina', duration_minutes: 30 })

/** Il backend valida `after:now`: si impedisce a monte di scegliere il passato. */
const minimoAppuntamento = computed(() => new Date(Date.now() + 60000).toISOString().slice(0, 16))

function apriRisposta(richiesta) {
  resetErrors()
  modaleRisposta.value = richiesta
  formRisposta.response = richiesta.response ?? ''
  formRisposta.status = 'risposta'
}

function apriConversione(richiesta) {
  resetErrors()
  modaleConversione.value = richiesta
  formConversione.scheduled_at = ''
  formConversione.type = 'telemedicina'
  formConversione.duration_minutes = 30
}

function chiudiModali() {
  modaleRisposta.value = null
  modaleConversione.value = null
  resetErrors()
}

/* ---------------------- Azioni ---------------------- */

/** Presa in carico: assegna la richiesta al medico autenticato. */
async function prendiInCarico(id) {
  await run(() => api.requests.claim(id), {
    successMessage: 'Richiesta presa in carico.',
    onSuccess: (payload) => {
      const aggiornata = payload?.data ?? payload
      if (aggiornata) replace(aggiornata)
    },
  })
}

async function inviaRisposta() {
  // Validazione lato client allineata alla FormRequest (min:5)
  if (formRisposta.response.trim().length < 5) {
    toast.error('Scrivi un parere di almeno 5 caratteri.')
    return
  }

  const esito = await run(
    () => api.requests.respond(modaleRisposta.value.id, { ...formRisposta }),
    {
      successMessage: 'Risposta inviata al paziente.',
      onSuccess: (payload) => {
        const aggiornata = payload?.data ?? payload
        if (aggiornata) replace(aggiornata)
      },
    },
  )

  if (esito) chiudiModali()
}

async function confermaConversione() {
  if (!formConversione.scheduled_at) {
    toast.error('Scegli data e ora dell\'appuntamento.')
    return
  }

  const esito = await run(
    () => api.requests.convertToAppointment(modaleConversione.value.id, { ...formConversione }),
    { successMessage: 'Appuntamento creato dalla richiesta.' },
  )

  if (esito) {
    chiudiModali()
    // La richiesta passa a "chiusa" lato server: si ricarica la coda
    await fetchAll()
  }
}

/** Gli allegati sono protetti da token: si scaricano come blob. */
const scaricaAllegato = (allegato) =>
  download(
    () => api.requests.downloadAttachment(allegato.id),
    allegato.original_name ?? 'allegato',
    allegato.id,
  )

/* ---------------------- Presentazione ---------------------- */

/**
 * Data dell'appuntamento nato dalla richiesta.
 *
 * Torna null quando la relazione non e' stata caricata (risposte parziali di
 * claim/respond): in quel caso il template ripiega sull'ID, evitando di
 * mostrare una riga vuota o "Invalid Date".
 */
const dataAppuntamento = (richiesta) => {
  const iso = richiesta.appointment?.scheduled_at
  return iso ? formatDateTime(iso, null) : null
}

const classePriorita = (priorita) =>
  ({
    alta: 'bg-red-100 text-red-700',
    media: 'bg-amber-100 text-amber-700',
    bassa: 'bg-gray-100 text-gray-600',
  })[priorita] ?? 'bg-gray-100 text-gray-600'

const bordoPriorita = (priorita) =>
  ({
    alta: 'border-l-red-500',
    media: 'border-l-amber-400',
    bassa: 'border-l-gray-300',
  })[priorita] ?? 'border-l-gray-300'

const etichettaPriorita = (priorita) =>
  ({ alta: 'Alta', media: 'Media', bassa: 'Bassa' })[priorita] ?? priorita

const classeStato = (stato) =>
  ({
    aperta: 'bg-blue-100 text-blue-700',
    in_carico: 'bg-purple-100 text-purple-700',
    risposta: 'bg-green-100 text-green-700',
    chiusa: 'bg-gray-200 text-gray-600',
  })[stato] ?? 'bg-gray-100 text-gray-700'

const etichettaStato = (stato) =>
  ({
    aperta: 'Aperta',
    in_carico: 'In carico',
    risposta: 'Risposta',
    chiusa: 'Chiusa',
  })[stato] ?? stato

onMounted(() => {
  // Si allinea il form dei filtri allo stato iniziale del composable
  filtri.status = filters.status
  filtri.priority = filters.priority
  fetchAll()
})
</script>
