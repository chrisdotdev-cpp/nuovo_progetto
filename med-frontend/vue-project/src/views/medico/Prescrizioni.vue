<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Gestione Prescrizioni</h1>
        <p class="text-start text-gray-500">Crea e gestisci le prescrizioni elettroniche</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 cursor-pointer"
        @click="apriNuova"
      >
        <i class="fa-solid fa-plus"></i>
        Nuova prescrizione
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- ============ ELENCO ============ -->
      <div class="md:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">

          <div class="relative mb-6">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input
              v-model="ricerca"
              type="search"
              placeholder="Cerca per codice ricetta..."
              aria-label="Cerca prescrizione"
              class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>

          <!-- Loading -->
          <div v-if="loading" class="space-y-4">
            <div v-for="n in 4" :key="n" class="h-24 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <!-- Empty state -->
          <div v-else-if="isEmpty" class="py-12 text-center">
            <i class="fa-solid fa-capsules text-5xl text-gray-300 mb-4 block"></i>
            <p class="text-gray-600 font-medium">Nessuna prescrizione</p>
            <p class="text-gray-500 text-sm mt-1">
              {{ hasFilters ? 'Nessun risultato con i filtri attivi.' : 'Emetti la prima ricetta dal pulsante in alto.' }}
            </p>
          </div>

          <!-- Dati reali -->
          <ul v-else class="space-y-4">
            <li
              v-for="ricetta in items"
              :key="ricetta.id"
              class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
            >
              <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <i class="fa-regular fa-user text-gray-600"></i>
                    <h3 class="text-gray-900 font-medium truncate">
                      {{ ricetta.patient?.name || 'Paziente' }}
                    </h3>
                  </div>
                  <div class="flex flex-wrap items-center gap-3 text-gray-600 text-sm">
                    <span><i class="fa-regular fa-calendar mr-1"></i>{{ formatDate(ricetta.issued_at) }}</span>
                    <span class="font-mono text-xs">{{ ricetta.code }}</span>
                  </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                  <span
                    v-if="ricetta.is_expired"
                    class="px-2.5 py-1 rounded-full text-xs bg-gray-200 text-gray-600"
                  >
                    Scaduta
                  </span>
                  <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statusClass(ricetta.status)">
                    {{ ricetta.status }}
                  </span>
                </div>
              </div>

              <!-- Farmaci prescritti -->
              <ul v-if="ricetta.items?.length" class="space-y-1 mb-3">
                <li
                  v-for="riga in ricetta.items"
                  :key="riga.id"
                  class="text-sm text-gray-700 flex flex-wrap gap-x-2"
                >
                  <span class="font-medium">{{ riga.name }}</span>
                  <span v-if="riga.dosage" class="text-gray-500">{{ riga.dosage }}</span>
                  <span v-if="riga.frequency" class="text-gray-500">· {{ riga.frequency }}</span>
                  <span v-if="riga.duration_days" class="text-gray-500">· {{ riga.duration_days }} gg</span>
                </li>
              </ul>
              <p v-else class="text-sm text-gray-400 mb-3">Nessun farmaco associato.</p>

              <!-- Azioni -->
              <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                <button
                  v-if="ricetta.status === 'attiva'"
                  type="button"
                  class="min-h-[44px] px-3 py-2 rounded-lg text-blue-700 hover:bg-blue-50 text-sm font-medium disabled:opacity-40 cursor-pointer"
                  :disabled="saving"
                  @click="completa(ricetta.id)"
                >
                  <i class="fa-solid fa-check mr-1"></i> Segna come completata
                </button>

                <button
                  v-if="ricetta.status === 'attiva'"
                  type="button"
                  class="min-h-[44px] px-3 py-2 rounded-lg text-red-700 hover:bg-red-50 text-sm font-medium disabled:opacity-40 cursor-pointer"
                  :disabled="saving"
                  @click="annullaRicetta(ricetta)"
                >
                  <i class="fa-solid fa-ban mr-1"></i> Annulla
                </button>
              </div>
            </li>
          </ul>

          <!-- Paginazione -->
          <div v-if="!loading && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-6">
            <button
              type="button"
              class="min-h-[44px] px-4 py-2 rounded-lg border border-gray-300 disabled:opacity-40"
              :disabled="meta.current_page <= 1"
              @click="prevPage"
            >
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="text-sm text-gray-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button
              type="button"
              class="min-h-[44px] px-4 py-2 rounded-lg border border-gray-300 disabled:opacity-40"
              :disabled="meta.current_page >= meta.last_page"
              @click="nextPage"
            >
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- ============ COLONNA LATERALE ============ -->
      <div class="space-y-6">
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

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-gray-900 font-semibold mb-4">Riepilogo</h2>
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-600">Totale ricette</dt>
              <dd class="font-semibold text-gray-900">{{ meta.total }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-600">In pagina</dt>
              <dd class="font-semibold text-gray-900">{{ items.length }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>

    <!-- ===================== MODALE NUOVA PRESCRIZIONE ===================== -->
    <div
      v-if="modaleAperta"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModale"
    >
      <form
        class="bg-white w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto"
        novalidate
        @submit.prevent="salvaPrescrizione"
      >
        <h3 class="text-lg font-bold mb-6">Nuova prescrizione</h3>

        <div class="mb-5">
          <label for="pres-paziente" class="block text-gray-700 mb-2 font-medium">
            Paziente <span class="text-red-500">*</span>
          </label>
          <select
            id="pres-paziente"
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

        <!-- Righe farmaco -->
        <h4 class="font-semibold text-gray-800 mb-3">Farmaci</h4>

        <div
          v-for="(riga, indice) in form.items"
          :key="indice"
          class="border border-gray-200 rounded-lg p-4 mb-3"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500">Farmaco {{ indice + 1 }}</span>
            <button
              v-if="form.items.length > 1"
              type="button"
              class="w-11 h-11 rounded-lg text-red-600 hover:bg-red-50"
              :aria-label="`Rimuovi farmaco ${indice + 1}`"
              @click="form.items.splice(indice, 1)"
            >
              <i class="fa-regular fa-trash-can"></i>
            </button>
          </div>

          <!-- Autocompletamento dal magazzino: se il farmaco esiste si collega l'id -->
          <div class="mb-4">
            <label :for="`farmaco-${indice}`" class="block text-gray-700 mb-2 text-sm font-medium">
              Nome del farmaco <span class="text-red-500">*</span>
            </label>
            <input
              :id="`farmaco-${indice}`"
              v-model="riga.name"
              list="elenco-farmaci"
              type="text"
              class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              placeholder="Inizia a digitare..."
              @change="collegaFarmaco(riga)"
            >
            <p v-if="fieldError(`items.${indice}.name`)" class="text-red-600 text-sm mt-1.5">
              {{ fieldError(`items.${indice}.name`) }}
            </p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <input
              v-model="riga.dosage"
              type="text"
              placeholder="Dosaggio"
              aria-label="Dosaggio"
              class="min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
            <input
              v-model="riga.frequency"
              type="text"
              placeholder="Frequenza"
              aria-label="Frequenza"
              class="min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
            <input
              v-model.number="riga.duration_days"
              type="number"
              min="1"
              max="365"
              placeholder="Giorni"
              aria-label="Durata in giorni"
              class="min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
            <input
              v-model.number="riga.quantity"
              type="number"
              min="1"
              placeholder="Q.tà"
              aria-label="Quantità"
              class="min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
          </div>
        </div>

        <!-- Elenco farmaci del magazzino per il datalist -->
        <datalist id="elenco-farmaci">
          <option v-for="farmaco in farmaci" :key="farmaco.id" :value="farmaco.name">
            {{ farmaco.active_ingredient }}
          </option>
        </datalist>

        <button
          type="button"
          class="min-h-[44px] px-4 py-2 rounded-lg border border-dashed border-gray-300 text-gray-600 hover:bg-gray-50 w-full mb-5 cursor-pointer"
          @click="form.items.push(rigaVuota())"
        >
          <i class="fa-solid fa-plus mr-1"></i> Aggiungi farmaco
        </button>

        <div class="mb-5">
          <label for="pres-note" class="block text-gray-700 mb-2 font-medium">Note</label>
          <textarea
            id="pres-note"
            v-model="form.notes"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            placeholder="Indicazioni per il paziente o per il farmacista..."
          ></textarea>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiModale">Annulla</BaseButton>
          <BaseButton type="submit" :loading="saving" :block="false">Emetti prescrizione</BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { api } from '@/services/api'
import { usePrescrizioni } from '@/composables/usePrescrizioni'
import { usePazienti } from '@/composables/usePazienti'
import { useApiRequest } from '@/composables/useApiRequest'
import { useFormatters } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'

import BaseButton from '@/components/ui/BaseButton.vue'

/*
  Il backend filtra le ricette sul medico autenticato e genera da solo il codice
  della ricetta: qui non si costruisce nessuna numerazione lato client.
*/
const {
  items, meta, loading, saving, isEmpty, hasFilters,
  fetchAll, applyFilters, nextPage, prevPage,
  rigaVuota, emetti, annulla, completa,
  statusClass, fieldError, resetErrors,
} = usePrescrizioni()

const { items: pazienti, fetchAll: fetchPazienti } = usePazienti()
const { formatDate } = useFormatters()
const toast = useToast()

/* Catalogo farmaci per l'autocompletamento delle righe. */
const farmaci = ref([])
const { run: runFarmaci } = useApiRequest()

const caricaFarmaci = () =>
  runFarmaci(() => api.medicines.list({ per_page: 200, active: true }), {
    showToast: false,
    onSuccess: (payload) => {
      farmaci.value = payload?.data ?? []
    },
  })

/* ---------------------- Filtri ---------------------- */
const ricerca = ref('')
const statoAttivo = ref('')

const statiFiltro = [
  { valore: '', etichetta: 'Tutte' },
  { valore: 'attiva', etichetta: 'Attive' },
  { valore: 'completata', etichetta: 'Completate' },
  { valore: 'annullata', etichetta: 'Annullate' },
]

const aggiornaFiltri = () => applyFilters({ q: ricerca.value, status: statoAttivo.value })

function filtraStato(valore) {
  statoAttivo.value = valore
  aggiornaFiltri()
}

let debounce = null
watch(ricerca, () => {
  clearTimeout(debounce)
  debounce = setTimeout(aggiornaFiltri, 400)
})

/* ---------------------- Modale ---------------------- */
const modaleAperta = ref(false)

const form = reactive({
  patient_id: '',
  notes: '',
  items: [rigaVuota()],
})

function apriNuova() {
  resetErrors()
  Object.assign(form, { patient_id: '', notes: '', items: [rigaVuota()] })
  modaleAperta.value = true
}

function chiudiModale() {
  modaleAperta.value = false
  resetErrors()
}

/**
 * Se il nome digitato corrisponde a un farmaco a magazzino si collega il
 * medicine_id: il backend potra' cosi' scalare la giacenza alla dispensazione.
 */
function collegaFarmaco(riga) {
  const trovato = farmaci.value.find(
    (farmaco) => farmaco.name.toLowerCase() === riga.name.trim().toLowerCase(),
  )

  riga.medicine_id = trovato?.id ?? null

  // Si precompila il dosaggio solo se il medico non l'ha gia' scritto
  if (trovato?.dosage && !riga.dosage) riga.dosage = trovato.dosage
}

async function salvaPrescrizione() {
  if (!form.patient_id) {
    toast.error('Seleziona un paziente.')
    return
  }

  if (!form.items.some((riga) => riga.name?.trim())) {
    toast.error('Aggiungi almeno un farmaco.')
    return
  }

  const esito = await emetti({ ...form })

  if (esito) {
    chiudiModale()
    await fetchAll()
  }
}

async function annullaRicetta(ricetta) {
  if (!window.confirm(`Annullare la ricetta ${ricetta.code}?`)) return
  await annulla(ricetta.id)
}

onMounted(() => {
  fetchAll()
  fetchPazienti()
  caricaFarmaci()
})
</script>
