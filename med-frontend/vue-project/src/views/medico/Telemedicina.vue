<template>
  <div class="flex-1 overflow-auto">
    <h1 class="text-2xl font-bold text-start mb-2">Telemedicina</h1>
    <p class="text-start text-gray-500 mb-6">Teleconsulti programmati e chat con i pazienti</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- ============ COLONNA SINISTRA: sessioni ============ -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">Sessioni</h2>
          <button
            type="button"
            class="w-11 h-11 rounded-lg hover:bg-gray-100 text-gray-600 cursor-pointer"
            aria-label="Aggiorna elenco"
            :disabled="loading"
            @click="fetchAll()"
          >
            <i class="fa-solid fa-rotate-right" :class="loading ? 'fa-spin' : ''"></i>
          </button>
        </div>

        <!-- Filtro stato -->
        <div class="flex gap-2 p-3 overflow-x-auto border-b border-gray-200">
          <button
            v-for="stato in statiFiltro"
            :key="stato.valore"
            type="button"
            class="min-h-[44px] px-3 py-2 rounded-lg text-sm whitespace-nowrap flex-shrink-0 transition-colors cursor-pointer"
            :class="statoAttivo === stato.valore
              ? 'bg-blue-100 text-blue-700 font-semibold'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
            @click="filtraStato(stato.valore)"
          >
            {{ stato.etichetta }}
          </button>
        </div>

        <div class="divide-y divide-gray-200 max-h-[560px] overflow-y-auto">

          <!-- Loading -->
          <div v-if="loading" class="p-4 space-y-4">
            <div v-for="n in 3" :key="n" class="h-16 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <!-- Empty state -->
          <div v-else-if="isEmpty" class="p-8 text-center">
            <i class="fa-solid fa-video-slash text-4xl text-gray-300 mb-3 block"></i>
            <p class="text-gray-500 text-sm">Nessun teleconsulto in programma.</p>
          </div>

          <!-- Sessioni reali -->
          <button
            v-for="sessione in items"
            :key="sessione.id"
            type="button"
            class="w-full p-4 text-left hover:bg-gray-50 transition-colors min-h-[44px] cursor-pointer"
            :class="sessioneSelezionata?.id === sessione.id ? 'bg-blue-50' : ''"
            @click="selezionaSessione(sessione)"
          >
            <div class="flex items-start gap-3">
              <img :src="avatarPaziente" alt="" class="w-10 h-10 rounded-full object-cover flex-shrink-0">

              <div class="flex-1 min-w-0">
                <h3 class="text-gray-900 font-medium truncate">
                  {{ sessione.appointment?.patient?.name || 'Paziente' }}
                </h3>
                <p class="text-gray-500 text-xs truncate">
                  {{ formatDateTime(sessione.appointment?.scheduled_at) }}
                </p>
                <span
                  class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px]"
                  :class="statusClass(sessione.status)"
                >
                  {{ etichettaStato(sessione.status) }}
                </span>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- ============ COLONNA DESTRA: stanza e chat ============ -->
      <div class="md:col-span-2">

        <!-- Nessuna selezione -->
        <div
          v-if="!sessioneSelezionata"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center h-full flex flex-col items-center justify-center"
        >
          <i class="fa-solid fa-video text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500">Seleziona una sessione per aprire la stanza e la chat</p>
        </div>

        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-full min-h-[560px]">

          <!-- Intestazione sessione -->
          <div class="p-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <h2 class="font-semibold text-gray-900 truncate">
                {{ sessioneSelezionata.appointment?.patient?.name || 'Paziente' }}
              </h2>
              <p class="text-sm text-gray-500">
                {{ formatDateTime(sessioneSelezionata.appointment?.scheduled_at) }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <!-- La finestra di accesso la decide il backend con is_joinable -->
              <button
                v-if="sessioneSelezionata.is_joinable && !roomCode"
                type="button"
                class="min-h-[44px] px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-60 cursor-pointer"
                :disabled="saving"
                @click="entraInStanza"
              >
                <i class="fa-solid fa-right-to-bracket mr-1"></i> Entra
              </button>

              <button
                v-if="sessioneSelezionata.status === 'programmata'"
                type="button"
                class="min-h-[44px] px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 disabled:opacity-60 cursor-pointer"
                :disabled="saving"
                @click="avviaSessione"
              >
                <i class="fa-solid fa-play mr-1"></i> Avvia
              </button>

              <button
                v-if="sessioneSelezionata.status === 'in_corso'"
                type="button"
                class="min-h-[44px] px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-60 cursor-pointer"
                :disabled="saving"
                @click="terminaSessione"
              >
                <i class="fa-solid fa-stop mr-1"></i> Termina
              </button>
            </div>
          </div>

          <!-- Codice stanza -->
          <div v-if="roomCode" class="m-4 p-4 rounded-lg bg-blue-50 border border-blue-200">
            <p class="text-xs text-blue-700 font-semibold mb-1">Codice stanza</p>
            <p class="font-mono text-lg text-blue-900 tracking-wider break-all">{{ roomCode }}</p>
            <p class="text-xs text-blue-600 mt-1">
              Comunicalo al paziente solo se non riesce ad accedere dalla sua area.
            </p>
          </div>

          <!-- Avviso: fuori finestra -->
          <div
            v-else-if="!sessioneSelezionata.is_joinable && sessioneSelezionata.status !== 'terminata'"
            class="m-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800"
          >
            <i class="fa-solid fa-clock mr-1"></i>
            La stanza si apre poco prima dell'orario previsto.
          </div>

          <!-- CHAT -->
          <div ref="finestraChat" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
            <div v-if="loadingChat && !messaggi.length" class="space-y-3">
              <div v-for="n in 3" :key="n" class="h-12 bg-gray-200 rounded-lg animate-pulse"></div>
            </div>

            <p v-else-if="!messaggi.length" class="text-center text-gray-400 text-sm py-8">
              Nessun messaggio in questa conversazione.
            </p>

            <div
              v-for="messaggio in messaggi"
              :key="messaggio.id"
              class="flex"
              :class="isMio(messaggio) ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[75%] rounded-2xl px-4 py-2"
                :class="isMio(messaggio)
                  ? 'bg-blue-600 text-white rounded-br-sm'
                  : 'bg-white border border-gray-200 text-gray-800 rounded-bl-sm'"
              >
                <p class="text-sm whitespace-pre-line break-words">{{ messaggio.body }}</p>
                <p class="text-[11px] mt-1" :class="isMio(messaggio) ? 'text-blue-100' : 'text-gray-400'">
                  {{ formatTime(messaggio.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- INPUT -->
          <form class="p-4 border-t border-gray-200 flex gap-2" @submit.prevent="invia">
            <input
              v-model="testoMessaggio"
              type="text"
              maxlength="2000"
              placeholder="Scrivi un messaggio..."
              aria-label="Nuovo messaggio"
              class="flex-1 min-h-[44px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
            <button
              type="submit"
              class="min-h-[44px] w-12 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-40 cursor-pointer"
              :disabled="!testoMessaggio.trim()"
              aria-label="Invia messaggio"
            >
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useTelemedicina } from '@/composables/useTelemedicina'
import { useAuthStore } from '@/store/auth'
import { useFormatters } from '@/composables/useFormatters'

import avatarPaziente from '@/assets/pazienti/patient_avatar.png'

/*
  Chat e sessioni sono reali: prima la vista lavorava su un array mock in memoria
  e usava toastify-js (libreria duplicata, rimossa dal progetto).
  Il polling della chat e' gestito dal composable e viene fermato allo smontaggio.
*/
const {
  items, loading, saving, isEmpty,
  fetchAll, applyFilters,
  messaggi, roomCode, loadingChat,
  entra, avvia, termina, fetchMessaggi, inviaMessaggio, fermaPollingChat,
  statusClass,
} = useTelemedicina()

const auth = useAuthStore()
const { formatDateTime, formatTime } = useFormatters()

const sessioneSelezionata = ref(null)
const testoMessaggio = ref('')
const finestraChat = ref(null)
const statoAttivo = ref('')

const statiFiltro = [
  { valore: '', etichetta: 'Tutte' },
  { valore: 'programmata', etichetta: 'Programmate' },
  { valore: 'in_corso', etichetta: 'In corso' },
  { valore: 'terminata', etichetta: 'Concluse' },
]

function filtraStato(valore) {
  statoAttivo.value = valore
  applyFilters({ status: valore })
}

/** Scorre la chat sull'ultimo messaggio dopo il render. */
async function scorriInFondo() {
  await nextTick()
  if (finestraChat.value) {
    finestraChat.value.scrollTop = finestraChat.value.scrollHeight
  }
}

watch(messaggi, scorriInFondo, { deep: true })

async function selezionaSessione(sessione) {
  fermaPollingChat()
  sessioneSelezionata.value = sessione
  roomCode.value = null
  await fetchMessaggi(sessione.id)
}

async function entraInStanza() {
  // entra() recupera il codice stanza e avvia il polling della chat
  await entra(sessioneSelezionata.value.id)
}

async function avviaSessione() {
  const esito = await avvia(sessioneSelezionata.value.id)
  if (esito) await fetchAll()
}

async function terminaSessione() {
  const note = window.prompt('Note conclusive del teleconsulto (facoltative):') ?? null
  const esito = await termina(sessioneSelezionata.value.id, note)

  if (esito) {
    sessioneSelezionata.value = null
    await fetchAll()
  }
}

async function invia() {
  const testo = testoMessaggio.value.trim()
  if (!testo || !sessioneSelezionata.value) return

  testoMessaggio.value = ''
  await inviaMessaggio(sessioneSelezionata.value.id, testo)
  await scorriInFondo()
}

/** Il messaggio e' "mio" se l'autore coincide con l'utente autenticato. */
const isMio = (messaggio) => messaggio.user_id === auth.user?.id

const etichettaStato = (stato) =>
  ({
    programmata: 'Programmata',
    in_corso: 'In corso',
    terminata: 'Conclusa',
    annullata: 'Annullata',
  })[stato] ?? stato

onMounted(fetchAll)

// Senza questo il polling continuerebbe a girare dopo aver lasciato la pagina
onBeforeUnmount(fermaPollingChat)
</script>
