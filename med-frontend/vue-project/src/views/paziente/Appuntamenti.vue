<template>
  <div class="space-y-6">
    <!-- TITOLO -->
    <div class="flex flex-col justify-between">
      <h2 class="text-gray-900 text-xl sm:text-2xl font-bold mb-1 sm:mb-2">Prenota un Appuntamento</h2>
      <h4 class="text-gray-500">Scegli il medico e l'orario che preferisci</h4>
    </div>

    <!-- MAIN: su mobile il riepilogo va sotto, su desktop resta affiancato e sticky -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <!-- FILTRI MEDICI -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div class="relative">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input
                v-model="ricerca"
                type="search"
                placeholder="Cerca medico..."
                class="w-full pl-10 pr-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <select
              v-model="specializzazione"
              class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
            >
              <option value="">Tutte le specializzazioni</option>
              <option v-for="spec in specializzazioni" :key="spec" :value="spec">{{ spec }}</option>
            </select>
          </div>

          <!-- Caricamento medici -->
          <div v-if="loadingMedici" class="space-y-3">
            <div v-for="n in 3" :key="`doc-skeleton-${n}`" class="h-24 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <!--
            Stato vuoto a tre facce.
            Prima mostrava sempre "Nessun medico corrisponde alla ricerca", anche
            quando la chiamata era fallita o l'archivio medici era proprio vuoto:
            due situazioni con cause e rimedi completamente diversi.
          -->

          <!-- A) La chiamata e' fallita (403, 500, backend spento) -->
          <div
            v-else-if="erroreMedici"
            class="text-center py-8 px-4 bg-red-50 border border-red-200 rounded-lg"
          >
            <i class="fa-solid fa-triangle-exclamation text-3xl text-red-400 mb-3"></i>
            <p class="text-red-700 font-medium mb-1">Impossibile caricare l'elenco medici.</p>
            <p class="text-red-600 text-sm mb-4">{{ erroreMedici }}</p>
            <button
              type="button"
              class="px-4 py-2 min-h-[40px] text-sm text-red-700 border border-red-300 rounded-lg hover:bg-red-100 transition-colors"
              @click="fetchMedici()"
            >
              Riprova
            </button>
          </div>

          <!-- B) Chiamata riuscita ma archivio medici vuoto: e' un problema di dati -->
          <div v-else-if="medici.length === 0" class="text-center py-10 px-4">
            <i class="fa-solid fa-user-doctor text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-700 font-medium mb-1">Nessun medico disponibile.</p>
            <p class="text-gray-500 text-sm">
              La clinica non ha ancora medici configurati. Contatta l'amministrazione.
            </p>
          </div>

          <!-- C) Ci sono medici, ma nessuno passa i filtri attivi -->
          <div v-else-if="mediciFiltrati.length === 0" class="text-center py-10">
            <i class="fa-solid fa-magnifying-glass text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 mb-3">Nessun medico corrisponde alla ricerca.</p>
            <button
              type="button"
              class="text-sm text-blue-600 hover:underline"
              @click="azzeraFiltri"
            >
              Azzera i filtri
            </button>
          </div>

          <!-- Elenco medici -->
          <div
            v-for="doctor in mediciFiltrati"
            :key="doctor.id"
            role="button"
            tabindex="0"
            :aria-pressed="selectedDoctor?.id === doctor.id"
            :class="[
              'p-4 mb-4 rounded-lg border-2 cursor-pointer transition-all',
              selectedDoctor?.id === doctor.id
                ? 'border-blue-500 bg-blue-50/50 shadow-sm'
                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50',
            ]"
            @click="selectDoctor(doctor)"
            @keydown.enter="selectDoctor(doctor)"
            @keydown.space.prevent="selectDoctor(doctor)"
          >
            <div class="flex items-center gap-4">
              <img :src="doctorImage" alt="" class="w-16 h-16 rounded-full object-cover flex-shrink-0" />

              <div class="flex-1 min-w-0">
                <h3 class="text-gray-900 font-medium truncate">{{ doctor.name }}</h3>
                <p class="text-gray-500 truncate">{{ doctor.specialization }}</p>

                <div class="flex flex-wrap items-center gap-2 mt-1">
                  <span class="text-xs text-gray-500">
                    <i class="fa-regular fa-clock"></i> {{ doctor.slot_duration }} min
                  </span>
                  <span v-if="doctor.consultation_fee > 0" class="text-xs text-gray-500">
                    · {{ formatCurrency(doctor.consultation_fee) }}
                  </span>
                  <span
                    v-if="doctor.available_online"
                    class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full"
                  >
                    Telemedicina
                  </span>
                </div>
              </div>

              <i
                v-if="selectedDoctor?.id === doctor.id"
                class="fa-solid fa-circle-check text-blue-500 text-xl flex-shrink-0"
              ></i>
            </div>
          </div>
        </div>

        <!-- I MIEI APPUNTAMENTI -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-gray-900 font-semibold">I miei appuntamenti</h2>

            <div class="flex gap-2">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                :class="[
                  'px-3 py-1.5 rounded-lg text-sm transition-colors min-h-[36px] cursor-pointer',
                  scope === tab.value ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                ]"
                @click="cambiaScope(tab.value)"
              >
                {{ tab.label }}
              </button>
            </div>
          </div>

          <div v-if="loadingAppuntamenti" class="space-y-3">
            <div v-for="n in 2" :key="`app-skeleton-${n}`" class="h-20 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <div v-else-if="appuntamenti.length === 0" class="text-center py-10">
            <i class="fa-regular fa-calendar-xmark text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-500">
              {{ scope === 'past' ? 'Nessun appuntamento passato.' : 'Non hai appuntamenti in programma.' }}
            </p>
          </div>

          <ul v-else class="divide-y divide-gray-100">
            <li v-for="app in appuntamenti" :key="app.id" class="py-3 flex flex-col sm:flex-row sm:items-center gap-3">
              <div
                class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0"
                :class="app.type === 'telemedicina' ? 'bg-purple-100' : 'bg-blue-100'"
              >
                <i
                  :class="
                    app.type === 'telemedicina'
                      ? 'fa-solid fa-video text-purple-600'
                      : 'fa-regular fa-calendar text-blue-600'
                  "
                ></i>
              </div>

              <div class="flex-1 min-w-0">
                <p class="text-gray-900 font-medium truncate">{{ app.doctor?.name }}</p>
                <p class="text-gray-500 text-sm truncate">{{ app.doctor?.specialization }}</p>
                <p class="text-gray-600 text-sm mt-1">
                  <i class="fa-regular fa-calendar"></i> {{ app.date }}
                  <span class="mx-1">·</span>
                  <i class="fa-regular fa-clock"></i> {{ app.time }}
                </p>
              </div>

              <div class="flex items-center gap-2 flex-shrink-0">
                <span class="text-xs px-2 py-1 rounded" :class="statusClass(app.status)">
                  {{ statusLabel(app.status) }}
                </span>

                <!-- L'annullamento è possibile solo finché il backend lo consente -->
                <button
                  v-if="app.is_editable"
                  type="button"
                  class="px-3 py-1.5 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors min-h-[36px] cursor-pointer"
                  @click="apriAnnullamento(app)"
                >
                  Annulla
                </button>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- PANNELLO PRENOTAZIONE -->
      <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:sticky lg:top-6">
          <h2 class="text-gray-900 font-semibold mb-4">Dettagli Prenotazione</h2>

          <!-- STATO 1: nessun medico selezionato -->
          <div v-if="!selectedDoctor" class="text-center py-8 text-gray-500">
            <i class="fa-regular fa-user" style="font-size: 28px; margin-bottom: 10px"></i>
            <p>Seleziona un medico per continuare</p>
          </div>

          <!-- STATO 2: medico selezionato -->
          <form v-else class="space-y-4" novalidate @submit.prevent="submitBooking">
            <div>
              <label class="block text-gray-700 mb-2">Medico selezionato</label>
              <div class="flex flex-col p-3 bg-gray-100 rounded-lg">
                <p class="text-gray-900 font-medium">{{ selectedDoctor.name }}</p>
                <p class="text-gray-600 text-sm">{{ selectedDoctor.specialization }}</p>
              </div>
            </div>

            <!-- Data -->
            <div class="pt-4 border-t border-gray-200">
              <label for="booking-date" class="block text-sm text-gray-700 mb-2">Data</label>
              <input
                id="booking-date"
                v-model="bookingForm.date"
                type="date"
                :min="oggi"
                class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @change="caricaSlot"
              />
            </div>

            <!-- Orario: gli slot arrivano dall'agenda reale del medico -->
            <div>
              <label class="block text-gray-700 mb-2">Orario</label>

              <p v-if="!bookingForm.date" class="text-sm text-gray-500 py-2">Seleziona prima una data.</p>

              <div v-else-if="loadingSlots" class="grid grid-cols-2 gap-2">
                <div v-for="n in 6" :key="`slot-skeleton-${n}`" class="h-11 bg-gray-100 rounded-lg animate-pulse"></div>
              </div>

              <p
                v-else-if="slots.length === 0"
                class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3"
              >
                Il medico non riceve in questa data. Prova con un altro giorno.
              </p>

              <div v-else class="grid grid-cols-2 gap-2 max-h-56 overflow-y-auto">
                <button
                  v-for="slot in slots"
                  :key="slot.start"
                  type="button"
                  :disabled="!slot.available"
                  :class="[
                    'py-2.5 px-3 rounded-lg border-2 transition-all min-h-[44px] text-sm cursor-pointer',
                    !slot.available
                      ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed line-through'
                      : bookingForm.slot === slot.start
                        ? 'border-blue-500 bg-blue-50 text-blue-600'
                        : 'border-gray-200 hover:border-gray-300 text-gray-700',
                  ]"
                  @click="bookingForm.slot = slot.start"
                >
                  <i class="fa-regular fa-clock"></i>
                  {{ slot.label }}
                </button>
              </div>
            </div>

            <!-- Tipo di visita -->
            <div>
              <label for="booking-type" class="block text-gray-700 mb-2">Tipo di visita</label>
              <select
                id="booking-type"
                v-model="bookingForm.type"
                class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
              >
                <option value="visita">In sede</option>
                <option value="controllo">Controllo</option>
                <!-- Il teleconsulto compare solo se il medico lo ha abilitato -->
                <option v-if="selectedDoctor.available_online" value="telemedicina">Telemedicina</option>
              </select>
            </div>

            <!-- Motivo -->
            <div>
              <label for="booking-reason" class="block text-gray-700 mb-2">Motivo della visita (opzionale)</label>
              <textarea
                id="booking-reason"
                v-model="bookingForm.reason"
                rows="3"
                maxlength="255"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Descrivi brevemente il motivo..."
              ></textarea>
            </div>

            <!-- Slot occupato nel frattempo: l'errore arriva dalla validazione Laravel -->
            <p
              v-if="fieldError('scheduled_at')"
              class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"
            >
              {{ fieldError('scheduled_at') }}
            </p>

            <!--
              Profilo paziente mancante: l'account esiste in `users` ma non ha
              un record in `patients`, quindi prepareForValidation non riesce a
              ricavare patient_id. Prima l'errore tornava dal backend e veniva
              scartato in silenzio: il pulsante sembrava semplicemente non
              funzionare. Ora la causa e' scritta a video.
            -->
            <p
              v-if="fieldError('patient_id')"
              class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3"
            >
              {{ fieldError('patient_id') }}
            </p>

            <BaseButton type="submit" :loading="saving" :disabled="isFormInvalid">
              Conferma prenotazione
            </BaseButton>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL ANNULLAMENTO: bottom sheet su mobile, dialog centrato su desktop -->
    <div
      v-if="annullamento.appuntamento"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4"
      role="dialog"
      aria-modal="true"
      @click.self="chiudiAnnullamento"
    >
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md p-6">
        <h2 class="text-gray-900 font-semibold mb-2">Annullare l'appuntamento?</h2>
        <p class="text-gray-600 text-sm mb-4">
          {{ annullamento.appuntamento.doctor?.name }} · {{ annullamento.appuntamento.date }} alle
          {{ annullamento.appuntamento.time }}
        </p>

        <label for="cancel-reason" class="block text-gray-700 mb-2 text-sm">Motivo (opzionale)</label>
        <textarea
          id="cancel-reason"
          v-model="annullamento.motivo"
          rows="3"
          maxlength="500"
          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-transparent mb-4"
          placeholder="Es. imprevisto personale"
        ></textarea>

        <div class="flex flex-col-reverse sm:flex-row gap-3">
          <BaseButton variant="secondary" @click="chiudiAnnullamento">Torna indietro</BaseButton>
          <BaseButton variant="danger" :loading="annullamento.loading" @click="confermaAnnullamento">
            Annulla appuntamento
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import doctorImage from '@/assets/doctor/doctor_avatar.png'
import BaseButton from '@/components/ui/BaseButton.vue'

import { useMedici } from '@/composables/useMedici'
import { useAppuntamenti } from '@/composables/useAppuntamenti'
import { useFormatters } from '@/composables/useFormatters'
import { useDashboardStore } from '@/store/dashboard'

/*
  I composable restituiscono ref: destrutturandoli qui il template resta pulito
  (niente .value sparsi) e la reattività si mantiene.
*/
const {
  items: medici,
  loading: loadingMedici,
  loadError: erroreMedici, // distingue "chiamata fallita" da "nessun risultato"
  specializzazioni,
  fetchAll: fetchMedici,
  fetchSpecializzazioni,
} = useMedici()

const {
  items: appuntamenti,
  loading: loadingAppuntamenti,
  saving,
  slots,
  loadingSlots,
  fetchAll: fetchAppuntamenti,
  fetchSlots,
  applyFilters,
  prenota,
  annulla,
  fieldError,
  statusClass,
  statusLabel,
} = useAppuntamenti({ scope: 'upcoming' }) // il paziente parte dai propri appuntamenti futuri

const dashboard = useDashboardStore()
const { formatCurrency, toInputDate } = useFormatters()

/* -------------------------------------------------------------------------
 | Selezione medico e filtri
 * ---------------------------------------------------------------------- */
const ricerca = ref('')
const specializzazione = ref('')
const selectedDoctor = ref(null)
const oggi = toInputDate()

/**
 * Filtro client-side: l'elenco medici di una clinica è breve e viene caricato
 * in una pagina sola, così la ricerca è istantanea senza chiamate ripetute.
 */
const mediciFiltrati = computed(() => {
  const termine = ricerca.value.trim().toLowerCase()

  return medici.value.filter((doctor) => {
    const perSpecializzazione = !specializzazione.value || doctor.specialization === specializzazione.value

    const perTermine =
      !termine ||
      doctor.name?.toLowerCase().includes(termine) ||
      doctor.specialization?.toLowerCase().includes(termine)

    return perSpecializzazione && perTermine
  })
})

/** Ripulisce ricerca e specializzazione: il filtro e' client-side, nessuna chiamata. */
const azzeraFiltri = () => {
  ricerca.value = ''
  specializzazione.value = ''
}

/* -------------------------------------------------------------------------
 | Form di prenotazione
 * ---------------------------------------------------------------------- */
const bookingForm = reactive({
  date: '',
  slot: '', // ISO 8601 completo restituito dall'endpoint availability
  type: 'visita',
  reason: '',
})

const isFormInvalid = computed(() => !selectedDoctor.value || !bookingForm.date || !bookingForm.slot)

const selectDoctor = (doctor) => {
  selectedDoctor.value = doctor

  // Cambiando medico, data e slot precedenti non sono più validi
  bookingForm.date = ''
  bookingForm.slot = ''
  bookingForm.type = 'visita'
  slots.value = []
}

/** Slot reali dell'agenda del medico per la data scelta. */
const caricaSlot = () => {
  bookingForm.slot = ''

  if (selectedDoctor.value && bookingForm.date) {
    fetchSlots(selectedDoctor.value.id, bookingForm.date)
  }
}

async function submitBooking() {
  if (isFormInvalid.value) return

  const creato = await prenota({
    doctor_id: selectedDoctor.value.id,
    scheduled_at: bookingForm.slot,
    duration_minutes: selectedDoctor.value.slot_duration,
    type: bookingForm.type,
    reason: bookingForm.reason || null,
  })

  // Slot occupato da un altro paziente nel frattempo: si ricaricano le disponibilità
  if (!creato) {
    caricaSlot()
    return
  }

  bookingForm.date = ''
  bookingForm.slot = ''
  bookingForm.reason = ''
  slots.value = []
  selectedDoctor.value = null

  await fetchAppuntamenti(1)
  dashboard.reset() // la panoramica dovrà rileggere i dati aggiornati
}

/* -------------------------------------------------------------------------
 | Lista appuntamenti
 * ---------------------------------------------------------------------- */
const tabs = [
  { value: 'upcoming', label: 'Prossimi' },
  { value: 'past', label: 'Passati' },
]

const scope = ref('upcoming')

const cambiaScope = (value) => {
  scope.value = value
  applyFilters({ scope: value })
}

/* -------------------------------------------------------------------------
 | Annullamento
 * ---------------------------------------------------------------------- */
const annullamento = reactive({
  appuntamento: null,
  motivo: '',
  loading: false,
})

const apriAnnullamento = (app) => {
  annullamento.appuntamento = app
  annullamento.motivo = ''
}

const chiudiAnnullamento = () => {
  annullamento.appuntamento = null
  annullamento.motivo = ''
}

async function confermaAnnullamento() {
  annullamento.loading = true

  const esito = await annulla(annullamento.appuntamento.id, annullamento.motivo || null)

  annullamento.loading = false

  if (esito) {
    chiudiAnnullamento()
    dashboard.reset()
  }
}

// Blocca lo scroll di fondo mentre il modal è aperto: su mobile è indispensabile
watch(
  () => annullamento.appuntamento,
  (aperto) => {
    document.body.style.overflow = aperto ? 'hidden' : ''
  },
)

// Se si lascia la pagina con il modal aperto lo scroll va comunque ripristinato
onBeforeUnmount(() => {
  document.body.style.overflow = ''
})

onMounted(() => {
  fetchMedici()
  fetchSpecializzazioni()
  fetchAppuntamenti()
})
</script>
