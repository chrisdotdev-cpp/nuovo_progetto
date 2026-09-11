<template>
  <div class="flex-1 overflow-auto">

    <div class="mb-8">
      <h1 class="text-2xl font-bold text-start mb-2">Gestione Personale Sanitario</h1>
      <p class="text-start text-gray-500">Anagrafica medici, specializzazioni e orario settimanale</p>
    </div>

    <!-- ===================== KPI ===================== -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6">
      <div class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-gray-600 text-sm">Medici totali</span>
          <i class="fa-solid fa-user-doctor text-purple-600"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-gray-200 rounded animate-pulse"></span>
          <span v-else>{{ meta.total }}</span>
        </p>
      </div>

      <div class="bg-green-50 p-4 sm:p-6 rounded-xl shadow-sm border border-green-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-green-700 text-sm">In turno oggi</span>
          <i class="fa-solid fa-circle-check text-green-600"></i>
        </div>
        <p class="text-2xl font-bold text-green-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-green-200 rounded animate-pulse"></span>
          <span v-else>{{ inTurnoOggi.length }}</span>
        </p>
      </div>

      <div class="bg-blue-50 p-4 sm:p-6 rounded-xl shadow-sm border border-blue-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-blue-700 text-sm">Abilitati online</span>
          <i class="fa-solid fa-video text-blue-600"></i>
        </div>
        <p class="text-2xl font-bold text-blue-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-blue-200 rounded animate-pulse"></span>
          <span v-else>{{ abilitatiOnline }}</span>
        </p>
      </div>

      <div class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-gray-600 text-sm">Specializzazioni</span>
          <i class="fa-solid fa-list text-blue-600"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-gray-200 rounded animate-pulse"></span>
          <span v-else>{{ specializzazioni.length }}</span>
        </p>
      </div>
    </div>

    <!-- ===================== FILTRI ===================== -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input
            v-model="ricerca"
            type="search"
            placeholder="Cerca medico..."
            aria-label="Cerca medico"
            class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
          >
        </div>

        <div>
          <select
            v-model="specializzazione"
            aria-label="Filtra per specializzazione"
            class="w-full min-h-[44px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent cursor-pointer"
            @change="aggiornaFiltri"
          >
            <option value="">Tutte le specializzazioni</option>
            <option v-for="spec in specializzazioni" :key="spec" :value="spec">{{ spec }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ===================== TABELLA ===================== -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="n in 5" :key="n" class="h-12 bg-gray-100 rounded-lg animate-pulse"></div>
      </div>

      <div v-else-if="isEmpty" class="p-12 text-center">
        <i class="fa-solid fa-user-doctor text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">Nessun medico trovato</p>
        <p class="text-gray-500 text-sm mt-1">
          {{ hasFilters ? 'Nessun risultato con i filtri attivi.' : 'Crea un account con ruolo medico dalla sezione Utenti.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[880px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Personale</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Specializzazione</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Albo</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Turno di oggi</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Ore settimana</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Teleconsulto</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Azioni</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="medico in items" :key="medico.id" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-user-doctor text-gray-400"></i>
                  <div class="min-w-0">
                    <p class="text-gray-900 font-medium truncate">{{ medico.name || '—' }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ medico.email || '' }}</p>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4">
                <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">
                  {{ medico.specialization || 'Non indicata' }}
                </span>
              </td>

              <td class="px-6 py-4 text-gray-600 text-sm">{{ medico.license_number || '—' }}</td>

              <td class="px-6 py-4 text-sm">
                <span v-if="turnoOggi(medico)" class="text-gray-900">
                  {{ turnoOggi(medico).start_time }} – {{ turnoOggi(medico).end_time }}
                </span>
                <span v-else class="text-gray-400">Non in turno</span>
              </td>

              <td class="px-6 py-4 text-gray-600 text-sm">{{ oreSettimanali(medico) }} h</td>

              <td class="px-6 py-4">
                <span
                  class="px-3 py-1 rounded-full text-xs font-medium"
                  :class="medico.available_online ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                >
                  {{ medico.available_online ? 'Attivo' : 'Disattivo' }}
                </span>
              </td>

              <td class="px-6 py-4">
                <button
                  type="button"
                  class="w-11 h-11 hover:bg-gray-100 rounded-lg transition-colors text-gray-600 cursor-pointer"
                  title="Orario settimanale"
                  @click="apriOrari(medico)"
                >
                  <i class="fa-regular fa-calendar-days"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ===================== MODALE ORARI ===================== -->
    <div
      v-if="medicoSelezionato"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiOrari"
    >
      <div class="bg-white w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto">
        <h3 class="text-lg font-bold mb-1">Orario settimanale</h3>
        <p class="text-sm text-gray-500 mb-6">{{ medicoSelezionato.name }} — {{ medicoSelezionato.specialization }}</p>

        <div class="space-y-3">
          <div
            v-for="giorno in bozzaOrari"
            :key="giorno.weekday"
            class="flex flex-wrap items-center gap-3 p-3 rounded-lg border border-gray-200"
          >
            <label class="flex items-center gap-2 w-32 flex-shrink-0 min-h-[44px]">
              <input v-model="giorno.active" type="checkbox" class="w-4 h-4 rounded border-gray-300">
              <span class="font-medium text-gray-800">{{ giorno.nome }}</span>
            </label>

            <input
              v-model="giorno.start_time"
              type="time"
              :disabled="!giorno.active"
              :aria-label="`Ora inizio ${giorno.nome}`"
              class="min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg disabled:bg-gray-100 disabled:text-gray-400"
            >
            <span class="text-gray-400">–</span>
            <input
              v-model="giorno.end_time"
              type="time"
              :disabled="!giorno.active"
              :aria-label="`Ora fine ${giorno.nome}`"
              class="min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg disabled:bg-gray-100 disabled:text-gray-400"
            >
          </div>
        </div>

        <p v-if="fieldError('schedules')" class="text-red-600 text-sm mt-3">{{ fieldError('schedules') }}</p>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end mt-6">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiOrari">
            Annulla
          </BaseButton>
          <BaseButton type="button" :loading="saving" :block="false" @click="salvaOrari">
            Salva orario
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useMedici } from '@/composables/useMedici'
import { useToast } from '@/composables/useToast'

import BaseButton from '@/components/ui/BaseButton.vue'

/*
  Il "personale" del gestionale coincide con i profili Doctor: l'anagrafica
  arriva da /doctors (con user e schedules), le specializzazioni da
  /doctors/specializations. Nessun elenco di reparti hardcoded.
*/
const {
  items, meta, loading, saving, isEmpty, hasFilters,
  fetchAll, applyFilters, replace,
  specializzazioni, fetchSpecializzazioni,
  update, fieldError, resetErrors,
} = useMedici()

const toast = useToast()

const ricerca = ref('')
const specializzazione = ref('')

const GIORNI = [
  { weekday: 1, nome: 'Lunedì' },
  { weekday: 2, nome: 'Martedì' },
  { weekday: 3, nome: 'Mercoledì' },
  { weekday: 4, nome: 'Giovedì' },
  { weekday: 5, nome: 'Venerdì' },
  { weekday: 6, nome: 'Sabato' },
  { weekday: 7, nome: 'Domenica' },
]

/** getDay() restituisce 0 per domenica: il backend usa 1..7 con 7 = domenica. */
const weekdayOggi = computed(() => new Date().getDay() || 7)

const turnoOggi = (medico) =>
  medico.schedules?.find((s) => s.weekday === weekdayOggi.value && s.active) ?? null

const inTurnoOggi = computed(() => items.value.filter((medico) => turnoOggi(medico)))
const abilitatiOnline = computed(() => items.value.filter((m) => m.available_online).length)

/** Somma delle ore settimanali dichiarate negli orari attivi. */
function oreSettimanali(medico) {
  const minuti = (medico.schedules ?? [])
    .filter((s) => s.active)
    .reduce((totale, s) => {
      const [oreInizio, minInizio] = (s.start_time ?? '0:0').split(':').map(Number)
      const [oreFine, minFine] = (s.end_time ?? '0:0').split(':').map(Number)
      return totale + Math.max(0, (oreFine * 60 + minFine) - (oreInizio * 60 + minInizio))
    }, 0)

  return Math.round((minuti / 60) * 10) / 10
}

/* ---------------------- Filtri ---------------------- */
const aggiornaFiltri = () =>
  applyFilters({ q: ricerca.value, specialization: specializzazione.value })

let debounce = null
watch(ricerca, () => {
  clearTimeout(debounce)
  debounce = setTimeout(aggiornaFiltri, 400)
})

/* ---------------------- Modale orari ---------------------- */
const medicoSelezionato = ref(null)
const bozzaOrari = ref([])

function apriOrari(medico) {
  resetErrors()
  medicoSelezionato.value = medico

  // Si parte sempre dai 7 giorni: i giorni senza orario compaiono disattivati
  bozzaOrari.value = GIORNI.map((giorno) => {
    const esistente = medico.schedules?.find((s) => s.weekday === giorno.weekday)

    return {
      ...giorno,
      active: Boolean(esistente?.active),
      start_time: esistente?.start_time ?? '09:00',
      end_time: esistente?.end_time ?? '18:00',
    }
  })
}

function chiudiOrari() {
  medicoSelezionato.value = null
  bozzaOrari.value = []
  resetErrors()
}

async function salvaOrari() {
  // Si inviano solo i giorni attivi: il backend sostituisce l'intero orario
  const schedules = bozzaOrari.value
    .filter((giorno) => giorno.active)
    .map(({ weekday, start_time, end_time }) => ({ weekday, start_time, end_time, active: true }))

  // Controllo a monte: il backend rifiuta end_time <= start_time con un 422
  const inconsistente = schedules.find((s) => s.end_time <= s.start_time)
  if (inconsistente) {
    toast.error('L\'orario di fine deve essere successivo a quello di inizio.')
    return
  }

  const esito = await update(medicoSelezionato.value.id, { schedules })

  if (esito) {
    const aggiornato = esito?.data ?? esito
    if (aggiornato) replace(aggiornato)
    chiudiOrari()
  }
}

onMounted(() => {
  fetchAll()
  fetchSpecializzazioni()
})
</script>
