<template>
  <div class="pazienti">
    <div class="flex-1 overflow-auto">
      <h1 class="text-2xl font-bold text-start mb-2">Cartelle Cliniche Pazienti</h1>
      <p class="text-start text-gray-500 mb-6">Consulta e gestisci le cartelle cliniche dei tuoi assistiti</p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- ============ COLONNA SINISTRA: elenco assistiti ============ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-4 border-b border-gray-200">
            <div class="relative">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input
                v-model="ricerca"
                type="search"
                placeholder="Cerca paziente..."
                class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
            </div>
          </div>

          <div class="divide-y divide-gray-200 max-h-[600px] overflow-y-auto">

            <!-- Loading -->
            <div v-if="loading" class="p-4 space-y-4">
              <div v-for="n in 4" :key="n" class="flex items-start gap-3 animate-pulse">
                <div class="w-10 h-10 bg-gray-200 rounded-full flex-shrink-0"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                  <div class="h-3 bg-gray-100 rounded w-1/2"></div>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="isEmpty" class="p-8 text-center">
              <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-3 block"></i>
              <p class="text-gray-500 text-sm">
                {{ ricerca ? 'Nessun paziente trovato per questa ricerca.' : 'Non hai ancora pazienti assegnati.' }}
              </p>
            </div>

            <!-- Lista reale -->
            <button
              v-for="paziente in items"
              :key="paziente.id"
              type="button"
              class="w-full p-4 text-left hover:bg-gray-50 transition-colors min-h-[44px] cursor-pointer"
              :class="pazienteSelezionato?.id === paziente.id ? 'bg-blue-50' : ''"
              @click="apriCartella(paziente)"
            >
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                  <i class="fa-regular fa-user text-blue-600"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="text-gray-900 font-medium truncate">{{ paziente.name || 'Paziente' }}</h3>
                  <p class="text-gray-600 text-sm truncate">{{ paziente.codice_fiscale || '—' }}</p>
                  <p class="text-gray-500 text-xs mt-1">
                    {{ paziente.age !== null && paziente.age !== undefined ? `${paziente.age} anni` : 'Età non disponibile' }}
                    <span v-if="paziente.city"> · {{ paziente.city }}</span>
                  </p>
                </div>
              </div>
            </button>
          </div>

          <!-- Paginazione -->
          <div v-if="meta.last_page > 1" class="flex items-center justify-between p-3 border-t border-gray-200 text-sm">
            <button
              type="button"
              class="px-3 py-2 rounded-lg hover:bg-gray-100 disabled:opacity-40 min-h-[44px]"
              :disabled="meta.current_page <= 1"
              @click="prevPage"
            >
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="text-gray-500">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button
              type="button"
              class="px-3 py-2 rounded-lg hover:bg-gray-100 disabled:opacity-40 min-h-[44px]"
              :disabled="meta.current_page >= meta.last_page"
              @click="nextPage"
            >
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>

        <!-- ============ COLONNA DESTRA: cartella clinica ============ -->
        <div class="md:col-span-2 space-y-6">

          <!-- Nessuna selezione -->
          <div
            v-if="!pazienteSelezionato"
            class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center"
          >
            <i class="fa-regular fa-user text-6xl text-gray-300 mb-4 block"></i>
            <p class="text-gray-500">Seleziona un paziente per visualizzare la cartella clinica</p>
          </div>

          <template v-else>
            <!-- Anagrafica -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                <div>
                  <h2 class="text-xl font-bold text-gray-900">{{ pazienteSelezionato.name }}</h2>
                  <p class="text-gray-500 text-sm">{{ pazienteSelezionato.codice_fiscale || '—' }}</p>
                </div>
                <span
                  v-if="pazienteSelezionato.blood_type"
                  class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-semibold"
                >
                  Gruppo {{ pazienteSelezionato.blood_type }}
                </span>
              </div>

              <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                  <dt class="text-gray-500">Età</dt>
                  <dd class="font-medium text-gray-900">{{ pazienteSelezionato.age ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-gray-500">Nascita</dt>
                  <dd class="font-medium text-gray-900">{{ formatDate(pazienteSelezionato.birth_date, '—') }}</dd>
                </div>
                <div>
                  <dt class="text-gray-500">Telefono</dt>
                  <dd class="font-medium text-gray-900">{{ pazienteSelezionato.phone || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-gray-500">Città</dt>
                  <dd class="font-medium text-gray-900">{{ pazienteSelezionato.city || '—' }}</dd>
                </div>
              </dl>

              <!-- Allergie e patologie croniche: informazione critica, sempre in evidenza -->
              <div v-if="pazienteSelezionato.allergies?.length" class="mt-4">
                <p class="text-sm text-gray-500 mb-1">Allergie</p>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="allergia in pazienteSelezionato.allergies"
                    :key="allergia"
                    class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-medium"
                  >
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ allergia }}
                  </span>
                </div>
              </div>

              <div v-if="pazienteSelezionato.chronic_conditions?.length" class="mt-3">
                <p class="text-sm text-gray-500 mb-1">Patologie croniche</p>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="patologia in pazienteSelezionato.chronic_conditions"
                    :key="patologia"
                    class="px-2.5 py-1 rounded-full bg-purple-100 text-purple-800 text-xs font-medium"
                  >
                    {{ patologia }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Timeline clinica -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
              <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-900">Storico clinico</h3>
                <button
                  type="button"
                  class="text-sm text-blue-600 hover:underline min-h-[44px] px-2 cursor-pointer"
                  @click="apriCartella(pazienteSelezionato)"
                >
                  <i class="fa-solid fa-rotate-right mr-1"></i> Aggiorna
                </button>
              </div>

              <!-- Loading timeline -->
              <div v-if="loadingTimeline" class="p-6 space-y-3">
                <div v-for="n in 3" :key="n" class="h-14 bg-gray-100 rounded-lg animate-pulse"></div>
              </div>

              <!-- Empty -->
              <div v-else-if="!vociCliniche.length" class="p-10 text-center">
                <i class="fa-regular fa-clipboard text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 text-sm">Nessuna voce clinica registrata per questo paziente.</p>
              </div>

              <!-- Voci reali -->
              <ul v-else class="divide-y divide-gray-200 max-h-[420px] overflow-y-auto">
                <li v-for="voce in vociCliniche" :key="voce.id" class="p-4 flex items-start gap-3">
                  <span
                    class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                    :class="typeClass(voce.type)"
                  >
                    <i :class="typeIcon(voce.type)"></i>
                  </span>
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <h4 class="font-medium text-gray-900 truncate">{{ voce.title }}</h4>
                      <span class="text-xs px-2 py-0.5 rounded-full" :class="typeClass(voce.type)">{{ voce.type }}</span>
                    </div>
                    <p v-if="voce.description" class="text-sm text-gray-600 mt-1 line-clamp-2">{{ voce.description }}</p>
                    <p class="text-xs text-gray-400 mt-1">
                      {{ formatDate(voce.recorded_at) }}
                      <span v-if="voce.doctor?.name"> · Dr. {{ voce.doctor.name }}</span>
                    </p>
                  </div>
                </li>
              </ul>
            </div>

            <!-- Prescrizioni e documenti dalla timeline -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-3">
                  <i class="fa-solid fa-capsules text-blue-600 mr-2"></i>Prescrizioni
                </h3>
                <p v-if="!prescrizioni.length" class="text-sm text-gray-500">Nessuna prescrizione.</p>
                <ul v-else class="space-y-2">
                  <li
                    v-for="ricetta in prescrizioni.slice(0, 5)"
                    :key="ricetta.id"
                    class="flex items-center justify-between gap-2 text-sm"
                  >
                    <span class="truncate">{{ ricetta.code }}</span>
                    <span class="text-xs text-gray-400 flex-shrink-0">{{ formatDate(ricetta.issued_at) }}</span>
                  </li>
                </ul>
              </div>

              <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-3">
                  <i class="fa-regular fa-file-lines text-blue-600 mr-2"></i>Documenti
                </h3>
                <p v-if="!documenti.length" class="text-sm text-gray-500">Nessun documento.</p>
                <ul v-else class="space-y-2">
                  <li
                    v-for="doc in documenti.slice(0, 5)"
                    :key="doc.id"
                    class="flex items-center justify-between gap-2 text-sm"
                  >
                    <span class="truncate">{{ doc.title }}</span>
                    <span class="text-xs text-gray-400 flex-shrink-0">{{ doc.size_label }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePazienti } from '@/composables/usePazienti'
import { useCartellaClinica } from '@/composables/useCartellaClinica'
import { useFormatters } from '@/composables/useFormatters'

/*
  Il backend restituisce gia' solo gli assistiti del medico autenticato
  (PatientController::index -> scope ofDoctor), quindi qui non serve alcun filtro
  sul doctor_id: la regola di autorizzazione non va duplicata nel client.
*/
const {
  items, meta, loading, isEmpty,
  fetchAll, applyFilters, nextPage, prevPage,
  timeline, loadingTimeline, fetchTimeline,
} = usePazienti()

const { typeIcon, typeClass } = useCartellaClinica()
const { formatDate } = useFormatters()

const ricerca = ref('')
const pazienteSelezionato = ref(null)

/*
  La timeline arriva come { records, prescriptions, documents }: si normalizza
  con fallback perche' il payload puo' essere incapsulato in `data`.
*/
const vociCliniche = computed(() => timeline.value?.records ?? timeline.value?.data?.records ?? [])
const prescrizioni = computed(() => timeline.value?.prescriptions ?? timeline.value?.data?.prescriptions ?? [])
const documenti = computed(() => timeline.value?.documents ?? timeline.value?.data?.documents ?? [])

/** Ricerca con debounce: una richiesta ogni 400ms di pausa, non a ogni tasto. */
let debounce = null
watch(ricerca, (value) => {
  clearTimeout(debounce)
  debounce = setTimeout(() => applyFilters({ q: value }), 400)
})

async function apriCartella(paziente) {
  pazienteSelezionato.value = paziente
  await fetchTimeline(paziente.id)
}

onMounted(fetchAll)
</script>
