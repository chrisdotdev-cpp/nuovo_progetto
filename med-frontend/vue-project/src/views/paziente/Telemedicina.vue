<template>
  <div class="flex-1 overflow-auto space-y-6">
    <!-- TITOLO -->
    <div class="flex flex-col justify-between">
      <h2 class="text-gray-900 text-xl sm:text-2xl font-bold mb-1 sm:mb-2">Telemedicina</h2>
      <h4 class="text-gray-600">Video-consulti e chat con i tuoi medici</h4>
    </div>

    <!-- PROSSIMO CONSULTO -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
      <h3 class="text-gray-900 font-semibold mb-4">Prossimo consulto</h3>

      <div v-if="loading" class="h-28 bg-gray-100 rounded-lg animate-pulse"></div>

      <div v-else-if="!prossimaSessione" class="text-center py-8">
        <i class="fa-solid fa-video-slash text-3xl text-gray-300 mb-3 block"></i>
        <p class="text-gray-500 mb-4">Non hai teleconsulti programmati.</p>
        <RouterLink
          to="/paziente/appuntamenti"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors min-h-[40px]"
        >
          <i class="fa-solid fa-plus"></i> Prenota un consulto online
        </RouterLink>
      </div>

      <div v-else class="flex flex-col sm:flex-row sm:items-center gap-4">
        <img :src="doctorAvatar" alt="" class="w-16 h-16 rounded-full object-cover flex-shrink-0" />

        <div class="flex-1 min-w-0">
          <h3 class="text-gray-900 font-medium">
            {{ prossimaSessione.appointment?.doctor?.name || 'Medico' }}
          </h3>
          <p class="text-gray-500 text-sm">
            {{ prossimaSessione.appointment?.doctor?.specialization }}
          </p>

          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-sm text-gray-600">
            <span>
              <i class="fa-regular fa-calendar"></i>
              {{ prossimaSessione.appointment?.date }}
            </span>
            <span>
              <i class="fa-regular fa-clock"></i>
              {{ prossimaSessione.appointment?.time }}
            </span>
            <span class="text-xs px-2 py-1 rounded" :class="statusClass(prossimaSessione.status)">
              {{ prossimaSessione.status.replace('_', ' ') }}
            </span>
          </div>
        </div>

        <!--
          L'accesso alla stanza è deciso dal backend (is_joinable): si apre
          10 minuti prima e resta valido per tutta la durata della visita.
        -->
        <button
          type="button"
          class="px-5 py-3 min-h-[44px] rounded-lg font-semibold transition-colors flex-shrink-0"
          :class="
            prossimaSessione.is_joinable
              ? 'bg-blue-600 text-white hover:bg-blue-700'
              : 'bg-gray-200 text-gray-500 cursor-not-allowed'
          "
          :disabled="!prossimaSessione.is_joinable || entrando"
          @click="entraInStanza(prossimaSessione)"
        >
          <i :class="entrando ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-video'"></i>
          {{ prossimaSessione.is_joinable ? 'Entra nella stanza' : 'Non ancora disponibile' }}
        </button>
      </div>

      <p v-if="prossimaSessione && !prossimaSessione.is_joinable" class="text-gray-500 text-xs mt-3">
        <i class="fa-solid fa-circle-info"></i>
        La stanza si apre 10 minuti prima dell'orario previsto.
      </p>
    </div>

    <!-- STANZA ATTIVA -->
    <div v-if="roomCode" class="bg-gray-900 rounded-xl p-4 sm:p-6 text-white">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
          <h3 class="font-semibold">Stanza virtuale</h3>
          <p class="text-gray-300 text-sm">
            Codice: <span class="font-mono">{{ roomCode }}</span>
          </p>
        </div>

        <button
          type="button"
          class="px-4 py-2 min-h-[40px] bg-red-600 rounded-lg hover:bg-red-700 transition-colors text-sm"
          @click="esciDallaStanza"
        >
          <i class="fa-solid fa-phone-slash"></i> Esci
        </button>
      </div>

      <!--
        Segnaposto del flusso video: qui si innesta l'SDK del provider
        (Jitsi, Daily, Twilio) usando roomCode come identificativo della stanza.
      -->
      <div class="aspect-video bg-black/40 rounded-lg flex items-center justify-center">
        <div class="text-center px-4">
          <i class="fa-solid fa-video text-4xl text-gray-500 mb-3 block"></i>
          <p class="text-gray-300 text-sm">Flusso video pronto per l'integrazione del provider.</p>
        </div>
      </div>
    </div>

    <!-- CONVERSAZIONI -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Elenco sessioni -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
          <h2 class="text-gray-900 font-bold">Conversazioni</h2>
        </div>

        <div v-if="loading" class="p-4 space-y-3">
          <div v-for="n in 3" :key="`chat-skeleton-${n}`" class="h-16 bg-gray-100 rounded-lg animate-pulse"></div>
        </div>

        <div v-else-if="sessioni.length === 0" class="p-8 text-center">
          <i class="fa-regular fa-comments text-3xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500 text-sm">Nessuna conversazione.</p>
        </div>

        <div v-else class="max-h-96 lg:max-h-[32rem] overflow-y-auto">
          <button
            v-for="sessione in sessioni"
            :key="sessione.id"
            type="button"
            :class="[
              'w-full text-left p-4 border-b border-gray-100 transition-colors cursor-pointer',
              selezionata?.id === sessione.id ? 'bg-blue-50' : 'hover:bg-gray-50',
            ]"
            @click="apriConversazione(sessione)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <h3 class="text-gray-900 font-semibold text-base truncate">
                  {{ sessione.appointment?.doctor?.name || 'Medico' }}
                </h3>
                <p class="text-gray-500 text-sm truncate">
                  {{ sessione.appointment?.doctor?.specialization }}
                </p>
              </div>

              <span class="text-xs px-2 py-1 rounded flex-shrink-0" :class="statusClass(sessione.status)">
                {{ sessione.status.replace('_', ' ') }}
              </span>
            </div>

            <p class="text-gray-400 text-xs mt-1">
              {{ sessione.appointment?.date }} · {{ sessione.appointment?.time }}
            </p>
          </button>
        </div>
      </div>

      <!-- Chat -->
      <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col min-h-[24rem]">
        <!-- STATO 1: nessuna conversazione selezionata -->
        <div v-if="!selezionata" class="flex-1 flex items-center justify-center text-gray-500 p-8">
          <div class="text-center">
            <i class="fa-regular fa-comments text-3xl text-gray-300 mb-3 block"></i>
            <p>Seleziona una conversazione</p>
          </div>
        </div>

        <!-- STATO 2: conversazione attiva -->
        <template v-else>
          <!-- Header -->
          <div class="p-4 border-b border-gray-200 flex items-center gap-3">
            <img :src="doctorAvatar" alt="" class="w-10 h-10 rounded-full object-cover" />
            <div class="min-w-0">
              <h3 class="text-gray-900 font-bold text-sm truncate">
                {{ selezionata.appointment?.doctor?.name }}
              </h3>
              <p class="text-gray-500 text-xs">{{ selezionata.appointment?.doctor?.specialization }}</p>
            </div>
          </div>

          <!-- Messaggi -->
          <div ref="areaMessaggi" class="flex-1 overflow-y-auto p-4 space-y-3 max-h-96">
            <p v-if="loadingChat && messaggi.length === 0" class="text-center text-gray-400 text-sm py-4">
              Caricamento messaggi...
            </p>

            <p v-else-if="messaggi.length === 0" class="text-center text-gray-400 text-sm py-4">
              Nessun messaggio. Scrivi per iniziare la conversazione.
            </p>

            <div
              v-for="msg in messaggi"
              :key="msg.id"
              :class="['flex', msg.user_id === auth.user?.id ? 'justify-end' : 'justify-start']"
            >
              <div
                :class="[
                  'max-w-[80%] rounded-2xl px-4 py-2',
                  msg.user_id === auth.user?.id
                    ? 'bg-blue-600 text-white rounded-br-sm'
                    : 'bg-gray-100 text-gray-900 rounded-bl-sm',
                ]"
              >
                <p class="text-sm whitespace-pre-line break-words">{{ msg.body }}</p>
                <p :class="['text-[11px] mt-1', msg.user_id === auth.user?.id ? 'text-blue-100' : 'text-gray-400']">
                  {{ formatTime(msg.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Input -->
          <form class="p-3 border-t border-gray-200 flex items-center gap-2" @submit.prevent="invia">
            <input
              v-model="bozza"
              type="text"
              maxlength="2000"
              placeholder="Scrivi un messaggio..."
              class="flex-1 min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-full focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <button
              type="submit"
              class="w-11 h-11 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors disabled:opacity-50 cursor-pointer"
              :disabled="!bozza.trim()"
              aria-label="Invia messaggio"
            >
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import doctorAvatar from '@/assets/doctor/doctor_avatar.png'
import { useTelemedicina } from '@/composables/useTelemedicina'
import { useFormatters } from '@/composables/useFormatters'
import { useAuthStore } from '@/store/auth'

const auth = useAuthStore()
const { formatTime } = useFormatters()

const {
  items: sessioni,
  loading,
  roomCode,
  messaggi,
  loadingChat,
  fetchAll,
  fetchMessaggi,
  entra,
  inviaMessaggio,
  fermaPollingChat,
  statusClass, // usato dai badge di stato: senza questa riga il render esplode
} = useTelemedicina()

const selezionata = ref(null)
const bozza = ref('')
const entrando = ref(false)
const areaMessaggi = ref(null)

/** Prima sessione ancora aperta: è quella che il paziente deve poter avviare. */
const prossimaSessione = computed(
  () => sessioni.value.find((s) => ['programmata', 'in_corso'].includes(s.status)) ?? null,
)

async function entraInStanza(sessione) {
  entrando.value = true
  await entra(sessione.id)
  selezionata.value = sessione
  entrando.value = false
  scrollInFondo()
}

function esciDallaStanza() {
  // Il paziente lascia la stanza: la chiusura formale spetta al medico
  fermaPollingChat()
  roomCode.value = null
}

async function apriConversazione(sessione) {
  selezionata.value = sessione
  await fetchMessaggi(sessione.id)
  scrollInFondo()
}

async function invia() {
  const testo = bozza.value.trim()
  if (!testo || !selezionata.value) return

  bozza.value = ''
  await inviaMessaggio(selezionata.value.id, testo)
  scrollInFondo()
}

/** La chat deve sempre mostrare l'ultimo messaggio. */
function scrollInFondo() {
  nextTick(() => {
    if (areaMessaggi.value) {
      areaMessaggi.value.scrollTop = areaMessaggi.value.scrollHeight
    }
  })
}

// Ogni messaggio nuovo (anche dal polling) riporta la vista in fondo
watch(() => messaggi.value.length, scrollInFondo)

onMounted(() => fetchAll())

// Uscendo dalla pagina il polling della chat va fermato
onBeforeUnmount(() => fermaPollingChat())
</script>
