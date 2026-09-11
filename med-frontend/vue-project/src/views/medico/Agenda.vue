<template>
  <div class="agenda">
    <div class="flex-1 overflow-auto">

      <!-- HEADER -->
      <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
          <h1 class="text-2xl font-bold text-start mb-2">Agenda Personale</h1>
          <p class="text-start text-gray-500">Gestisci i tuoi appuntamenti</p>
        </div>

        <button
          type="button"
          class="min-h-[44px] px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 cursor-pointer"
          @click="apriNuovo"
        >
          <i class="fa-solid fa-plus"></i>
          Nuovo appuntamento
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- ============ COLONNA PRINCIPALE ============ -->
        <div class="md:col-span-2 space-y-6">
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">

            <!-- Navigazione settimana -->
            <div class="flex items-center justify-between mb-6">
              <button
                type="button"
                class="w-11 h-11 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                aria-label="Settimana precedente"
                @click="spostaSettimana(-7)"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>

              <h2 class="text-gray-900 font-medium capitalize text-center">
                {{ formatLongDate(dataSelezionata) }}
              </h2>

              <button
                type="button"
                class="w-11 h-11 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                aria-label="Settimana successiva"
                @click="spostaSettimana(7)"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>

            <!-- Strip dei giorni -->
            <div class="flex gap-2 overflow-x-auto pb-2">
              <button
                v-for="giorno in settimana"
                :key="giorno.iso"
                type="button"
                class="flex-shrink-0 px-4 py-3 rounded-lg border-2 transition-all min-h-[44px] cursor-pointer"
                :class="giorno.iso === dataSelezionata
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'"
                @click="selezionaGiorno(giorno.iso)"
              >
                <p class="text-gray-600 text-sm">{{ giorno.etichettaGiorno }}</p>
                <p class="text-gray-900 font-semibold">{{ giorno.numero }}</p>
              </button>
            </div>

            <!-- Lista appuntamenti del giorno -->
            <div class="mt-6">
              <!-- Loading -->
              <div v-if="loading" class="space-y-3">
                <div v-for="n in 4" :key="n" class="h-20 bg-gray-100 rounded-lg animate-pulse"></div>
              </div>

              <!-- Empty state -->
              <div v-else-if="isEmpty" class="py-12 text-center">
                <i class="fa-regular fa-calendar-xmark text-5xl text-gray-300 mb-4 block"></i>
                <p class="text-gray-600 font-medium">Giornata libera</p>
                <p class="text-gray-500 text-sm mt-1">Nessun appuntamento per questa data.</p>
              </div>

              <!-- Dati reali -->
              <ul v-else class="space-y-3">
                <li
                  v-for="appuntamento in items"
                  :key="appuntamento.id"
                  class="p-4 rounded-lg border-2 transition-shadow hover:shadow-md"
                  :class="bordoStato(appuntamento.status)"
                >
                  <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                    <div class="min-w-0">
                      <h3 class="text-gray-900 font-medium truncate">
                        {{ appuntamento.patient?.name || 'Paziente' }}
                      </h3>
                      <p class="text-gray-600 text-sm truncate">
                        {{ appuntamento.reason || appuntamento.type }}
                      </p>
                    </div>

                    <div class="text-right flex-shrink-0">
                      <p class="text-gray-900 font-semibold">{{ appuntamento.time }}</p>
                      <p class="text-gray-500 text-xs">{{ appuntamento.duration_minutes }} min</p>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-black/5">
                    <span class="px-2.5 py-1 rounded-full text-xs" :class="statusClass(appuntamento.status)">
                      {{ statusLabel(appuntamento.status) }}
                    </span>

                    <div class="ml-auto flex flex-wrap gap-1">
                      <button
                        v-if="appuntamento.status === 'in_attesa'"
                        type="button"
                        class="min-h-[44px] px-3 py-2 rounded-lg text-green-700 hover:bg-green-100 text-sm font-medium disabled:opacity-40 cursor-pointer"
                        :disabled="saving"
                        @click="cambiaStato(appuntamento.id, 'confermato')"
                      >
                        <i class="fa-solid fa-check mr-1"></i> Conferma
                      </button>

                      <button
                        v-if="['confermato', 'in_attesa'].includes(appuntamento.status)"
                        type="button"
                        class="min-h-[44px] px-3 py-2 rounded-lg text-blue-700 hover:bg-blue-100 text-sm font-medium disabled:opacity-40 cursor-pointer"
                        :disabled="saving"
                        @click="cambiaStato(appuntamento.id, 'completato')"
                      >
                        <i class="fa-solid fa-flag-checkered mr-1"></i> Completa
                      </button>

                      <button
                        v-if="appuntamento.is_editable"
                        type="button"
                        class="min-h-[44px] px-3 py-2 rounded-lg text-red-700 hover:bg-red-100 text-sm font-medium disabled:opacity-40 cursor-pointer"
                        :disabled="saving"
                        @click="annullaConMotivo(appuntamento)"
                      >
                        <i class="fa-solid fa-xmark mr-1"></i> Annulla
                      </button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- ============ COLONNA LATERALE ============ -->
        <div class="space-y-6">
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-gray-900 font-semibold mb-4">Riepilogo giornata</h2>

            <dl class="space-y-3 text-sm">
              <div v-for="voce in riepilogo" :key="voce.etichetta" class="flex justify-between">
                <dt class="text-gray-600">{{ voce.etichetta }}</dt>
                <dd class="font-semibold text-gray-900">{{ voce.valore }}</dd>
              </div>
            </dl>
          </div>

          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-gray-900 font-semibold mb-4">Filtra per stato</h2>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="stato in statiFiltro"
                :key="stato.valore"
                type="button"
                class="min-h-[44px] px-3 py-2 rounded-lg text-sm transition-colors cursor-pointer"
                :class="statoAttivo === stato.valore
                  ? 'bg-blue-100 text-blue-700 font-semibold'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                @click="filtraStato(stato.valore)"
              >
                {{ stato.etichetta }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== MODALE NUOVO APPUNTAMENTO ===================== -->
    <div
      v-if="modaleAperta"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModale"
    >
      <form
        class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto"
        novalidate
        @submit.prevent="salvaAppuntamento"
      >
        <h3 class="text-lg font-bold mb-6">Nuovo appuntamento</h3>

        <div class="mb-5">
          <label for="app-paziente" class="block text-gray-700 mb-2 font-medium">
            Paziente <span class="text-red-500">*</span>
          </label>
          <select
            id="app-paziente"
            v-model="form.patient_id"
            class="w-full min-h-[44px] px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 cursor-pointer"
            :class="fieldError('patient_id') ? 'border-red-400 bg-red-50/40' : 'border-gray-300'"
          >
            <option value="">Seleziona un paziente...</option>
            <option v-for="paziente in pazienti" :key="paziente.id" :value="paziente.id">
              {{ paziente.name }} — {{ paziente.codice_fiscale || 'CF non indicato' }}
            </option>
          </select>
          <p v-if="fieldError('patient_id')" class="text-red-600 text-sm mt-1.5">{{ fieldError('patient_id') }}</p>
        </div>

        <div class="mb-5">
          <label for="app-data" class="block text-gray-700 mb-2 font-medium">
            Data e ora <span class="text-red-500">*</span>
          </label>
          <input
            id="app-data"
            v-model="form.scheduled_at"
            type="datetime-local"
            :min="minimoAppuntamento"
            class="w-full min-h-[44px] px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
            :class="fieldError('scheduled_at') ? 'border-red-400 bg-red-50/40' : 'border-gray-300'"
          >
          <p v-if="fieldError('scheduled_at')" class="text-red-600 text-sm mt-1.5">{{ fieldError('scheduled_at') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-5">
          <div>
            <label for="app-tipo" class="block text-gray-700 mb-2 font-medium">Tipo</label>
            <select
              id="app-tipo"
              v-model="form.type"
              class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 cursor-pointer"
            >
              <option value="visita">Visita</option>
              <option value="controllo">Controllo</option>
              <option value="telemedicina">Telemedicina</option>
              <option value="urgenza">Urgenza</option>
            </select>
          </div>

          <div>
            <label for="app-durata" class="block text-gray-700 mb-2 font-medium">Durata (min)</label>
            <input
              id="app-durata"
              v-model.number="form.duration_minutes"
              type="number"
              min="5"
              max="240"
              step="5"
              class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
          </div>
        </div>

        <BaseInput v-model="form.reason" label="Motivo" :error="fieldError('reason')" />

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiModale">Annulla</BaseButton>
          <BaseButton type="submit" :loading="saving" :block="false">Crea appuntamento</BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAppuntamenti } from '@/composables/useAppuntamenti'
import { usePazienti } from '@/composables/usePazienti'
import { useAuthStore } from '@/store/auth'
import { useFormatters } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'

import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

/*
  Il backend filtra gia' l'agenda sul medico autenticato: qui si filtra solo
  per data (from/to sullo stesso giorno) e, opzionalmente, per stato.
*/
const {
  items, loading, saving, isEmpty,
  applyFilters, cambiaStato, annulla, prenota,
  statusClass, statusLabel, fieldError, resetErrors,
} = useAppuntamenti()

// Elenco assistiti per la tendina del nuovo appuntamento
const { items: pazienti, fetchAll: fetchPazienti } = usePazienti()

const auth = useAuthStore()
const { formatLongDate, toInputDate } = useFormatters()
const toast = useToast()

/* ---------------------- Calendario ---------------------- */
const dataSelezionata = ref(toInputDate())
const inizioSettimana = ref(lunediDi(new Date()))

/** Lunedì della settimana che contiene la data indicata. */
function lunediDi(data) {
  const risultato = new Date(data)
  const giorno = risultato.getDay() || 7 // domenica = 7
  risultato.setDate(risultato.getDate() - (giorno - 1))
  risultato.setHours(0, 0, 0, 0)
  return risultato
}

const settimana = computed(() =>
  Array.from({ length: 7 }, (_, indice) => {
    const giorno = new Date(inizioSettimana.value)
    giorno.setDate(giorno.getDate() + indice)

    return {
      iso: toInputDate(giorno),
      numero: giorno.getDate(),
      etichettaGiorno: giorno.toLocaleDateString('it-IT', { weekday: 'short' }),
    }
  }),
)

function spostaSettimana(giorni) {
  const nuovoInizio = new Date(inizioSettimana.value)
  nuovoInizio.setDate(nuovoInizio.getDate() + giorni)
  inizioSettimana.value = nuovoInizio
}

function selezionaGiorno(iso) {
  dataSelezionata.value = iso
  ricarica()
}

/* ---------------------- Filtri ---------------------- */
const statoAttivo = ref('')

const statiFiltro = [
  { valore: '', etichetta: 'Tutti' },
  { valore: 'in_attesa', etichetta: 'In attesa' },
  { valore: 'confermato', etichetta: 'Confermati' },
  { valore: 'completato', etichetta: 'Completati' },
]

const ricarica = () =>
  applyFilters({
    from: dataSelezionata.value,
    to: dataSelezionata.value,
    status: statoAttivo.value,
  })

function filtraStato(valore) {
  statoAttivo.value = valore
  ricarica()
}

/* ---------------------- Riepilogo ---------------------- */
const riepilogo = computed(() => {
  const conta = (stato) => items.value.filter((a) => a.status === stato).length

  // Minuti totali occupati: utile per capire quanto e' piena la giornata
  const minuti = items.value
    .filter((a) => a.status !== 'annullato')
    .reduce((totale, a) => totale + (a.duration_minutes ?? 0), 0)

  return [
    { etichetta: 'Appuntamenti', valore: items.value.length },
    { etichetta: 'Confermati', valore: conta('confermato') },
    { etichetta: 'In attesa', valore: conta('in_attesa') },
    { etichetta: 'Completati', valore: conta('completato') },
    { etichetta: 'Tempo occupato', valore: `${Math.floor(minuti / 60)}h ${minuti % 60}m` },
  ]
})

/* ---------------------- Modale ---------------------- */
const modaleAperta = ref(false)

const form = reactive({
  patient_id: '',
  scheduled_at: '',
  type: 'visita',
  duration_minutes: 30,
  reason: '',
})

const minimoAppuntamento = computed(() => new Date(Date.now() + 60000).toISOString().slice(0, 16))

function apriNuovo() {
  resetErrors()
  Object.assign(form, {
    patient_id: '',
    // Si precompila con il giorno selezionato in agenda: meno click
    scheduled_at: `${dataSelezionata.value}T09:00`,
    type: 'visita',
    duration_minutes: 30,
    reason: '',
  })
  modaleAperta.value = true
}

function chiudiModale() {
  modaleAperta.value = false
  resetErrors()
}

async function salvaAppuntamento() {
  if (!form.patient_id) {
    toast.error('Seleziona un paziente.')
    return
  }

  if (!form.scheduled_at) {
    toast.error('Indica data e ora.')
    return
  }

  const esito = await prenota({
    ...form,
    // Il medico crea sempre per se stesso: il doctor_id arriva dal profilo in sessione
    doctor_id: auth.doctorId,
  })

  if (esito) {
    chiudiModale()
    await ricarica()
  }
}

async function annullaConMotivo(appuntamento) {
  const motivo = window.prompt('Motivo dell\'annullamento (facoltativo):') ?? null
  await annulla(appuntamento.id, motivo)
}

/* ---------------------- Presentazione ---------------------- */
const bordoStato = (stato) =>
  ({
    confermato: 'border-green-200 bg-green-50',
    in_attesa: 'border-amber-200 bg-amber-50',
    completato: 'border-blue-200 bg-blue-50',
    annullato: 'border-red-200 bg-red-50 opacity-70',
    assente: 'border-gray-200 bg-gray-50',
  })[stato] ?? 'border-gray-200 bg-white'

onMounted(() => {
  ricarica()
  fetchPazienti()
})
</script>
