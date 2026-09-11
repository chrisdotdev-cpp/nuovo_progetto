<!--
  Archivio documentale e firma.

  Flusso reale di una struttura sanitaria:
  - i documenti clinici (referti, consensi informati, privacy/GDPR) e quelli
    amministrativi (fatture, contratti) entrano nello stato "da firmare";
  - l'amministratore firma con FEQ (valore legale della firma autografa),
    tipicamente in blocco: da qui la selezione multipla e "Firma selezionati";
  - dopo la firma il documento va in conservazione sostitutiva, che ne garantisce
    integrita' e reperibilita' per i termini di legge.

  La firma vera passa da un provider certificato (InfoCert, Aruba, ...): il
  frontend si limita a chiamare l'endpoint, la logica resta lato backend.
-->

<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Documenti e firma</h1>
        <p class="text-start text-gray-500">Archivio, firma elettronica qualificata e conservazione</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="min-h-[44px] px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 cursor-pointer"
          :disabled="loading"
          @click="ricarica"
        >
          <i class="fa-solid fa-rotate-right" :class="loading ? 'fa-spin' : ''"></i>
          Aggiorna
        </button>

        <button
          type="button"
          class="min-h-[44px] px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2 disabled:opacity-60 cursor-pointer"
          :disabled="!selezionati.length || saving"
          @click="firmaMassiva"
        >
          <i class="fa-solid fa-signature"></i>
          Firma selezionati
          <span v-if="selezionati.length" class="bg-white/25 rounded-full px-2 text-sm">{{ selezionati.length }}</span>
        </button>
      </div>
    </div>

    <!-- CONTATORI -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6">
      <div
        v-for="card in cards"
        :key="card.etichetta"
        class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-gray-200"
      >
        <p class="text-gray-600 mb-2 text-sm">{{ card.etichetta }}</p>
        <p class="text-2xl font-bold text-gray-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-gray-200 rounded animate-pulse"></span>
          <span v-else>{{ card.valore }}</span>
        </p>
      </div>
    </div>

    <!-- FILTRI -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label for="doc-q" class="block text-gray-700 mb-2 text-sm font-medium">Ricerca</label>
          <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input
              id="doc-q"
              v-model="filtri.q"
              type="search"
              placeholder="Titolo del documento..."
              class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
              @input="cercaConDebounce"
            >
          </div>
        </div>

        <div>
          <label for="doc-stato" class="block text-gray-700 mb-2 text-sm font-medium">Stato</label>
          <select
            id="doc-stato"
            v-model="filtri.status"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent cursor-pointer"
            @change="aggiornaFiltri"
          >
            <option value="">Tutti gli stati</option>
            <option value="bozza">Bozza</option>
            <option value="da_firmare">Da firmare</option>
            <option value="firmato">Firmato</option>
            <option value="in_conservazione">In conservazione</option>
            <option value="archiviato">Archiviato</option>
          </select>
        </div>

        <div>
          <label for="doc-categoria" class="block text-gray-700 mb-2 text-sm font-medium">Categoria</label>
          <select
            id="doc-categoria"
            v-model="filtri.category"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent cursor-pointer"
            @change="aggiornaFiltri"
          >
            <option value="">Tutte le categorie</option>
            <option value="referto">Referti</option>
            <option value="consenso">Consensi informati</option>
            <option value="fattura">Fatture</option>
            <option value="contratto">Contratti</option>
            <option value="certificato">Certificati</option>
          </select>
        </div>
      </div>
    </div>

    <!-- TABELLA -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

      <!-- Loading -->
      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="n in 5" :key="n" class="h-12 bg-gray-100 rounded-lg animate-pulse"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="isEmpty" class="p-12 text-center">
        <i class="fa-regular fa-file-zipper text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">Nessun documento trovato</p>
        <p class="text-gray-500 text-sm mt-1">
          {{ hasFilters ? 'Nessun risultato con i filtri attivi.' : 'L\'archivio è ancora vuoto.' }}
        </p>
      </div>

      <!-- Dati reali -->
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[760px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left w-10">
                <input
                  type="checkbox"
                  class="w-4 h-4 rounded border-gray-300"
                  :checked="tuttiSelezionati"
                  aria-label="Seleziona tutti i documenti firmabili"
                  @change="selezionaTutti(items)"
                >
              </th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Documento</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Categoria</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Paziente</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Stato</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Data</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Azioni</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="documento in items" :key="documento.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  class="w-4 h-4 rounded border-gray-300 disabled:opacity-40"
                  :checked="isSelezionato(documento.id)"
                  :disabled="documento.status !== 'da_firmare'"
                  :aria-label="`Seleziona ${documento.title}`"
                  @change="toggleSelezione(documento.id)"
                >
              </td>

              <td class="px-6 py-3">
                <p class="font-medium text-gray-900 truncate max-w-[240px]">{{ documento.title }}</p>
                <p class="text-xs text-gray-500">{{ documento.original_name }} · {{ documento.size_label }}</p>
              </td>

              <td class="px-6 py-3 text-sm text-gray-700 capitalize">{{ documento.category }}</td>

              <td class="px-6 py-3 text-sm text-gray-700 truncate max-w-[160px]">
                {{ documento.patient?.name || '—' }}
              </td>

              <td class="px-6 py-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="statusClass(documento.status)">
                  {{ statusLabel(documento.status) }}
                </span>
              </td>

              <td class="px-6 py-3 text-sm text-gray-500 whitespace-nowrap">
                {{ formatDate(documento.created_at) }}
              </td>

              <td class="px-6 py-3">
                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    class="w-11 h-11 rounded-lg hover:bg-gray-100 text-gray-600"
                    title="Scarica"
                    @click="scarica(documento)"
                  >
                    <i :class="isDownloading(documento.id) ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-download'"></i>
                  </button>

                  <button
                    v-if="documento.status === 'da_firmare'"
                    type="button"
                    class="w-11 h-11 rounded-lg hover:bg-purple-50 text-purple-600 disabled:opacity-40"
                    title="Firma (FEQ)"
                    :disabled="saving"
                    @click="firma(documento.id)"
                  >
                    <i class="fa-solid fa-signature"></i>
                  </button>

                  <button
                    v-if="documento.status === 'firmato'"
                    type="button"
                    class="w-11 h-11 rounded-lg hover:bg-blue-50 text-blue-600 disabled:opacity-40"
                    title="Invia in conservazione"
                    :disabled="saving"
                    @click="archivia(documento.id)"
                  >
                    <i class="fa-solid fa-box-archive"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginazione -->
      <div
        v-if="!loading && meta.last_page > 1"
        class="flex items-center justify-between gap-3 p-4 border-t border-gray-200"
      >
        <span class="text-sm text-gray-500">{{ meta.total }} documenti</span>
        <div class="flex items-center gap-3">
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
  </div>
</template>

<script setup>
import { reactive, computed, onMounted } from 'vue'
import { useDocumenti } from '@/composables/useDocumenti'
import { useFormatters } from '@/composables/useFormatters'

const {
  items, meta, loading, saving, isEmpty, hasFilters,
  fetchAll, applyFilters, nextPage, prevPage,
  contatori, fetchContatori,
  selezionati, toggleSelezione, isSelezionato, selezionaTutti,
  firma, firmaSelezionati, archivia, scarica, isDownloading,
  statusClass, statusLabel,
} = useDocumenti()

const { formatDate } = useFormatters()

const filtri = reactive({ q: '', status: '', category: '' })

/*
  Chiavi allineate a DocumentController::counters()
  ({ da_firmare, firmati, in_conservazione, per_categoria }).
  Il totale non e' fra i contatori: si usa quello della paginazione.
*/
const cards = computed(() => [
  { etichetta: 'Da firmare', valore: contatori.value?.da_firmare ?? 0 },
  { etichetta: 'Firmati', valore: contatori.value?.firmati ?? 0 },
  { etichetta: 'In conservazione', valore: contatori.value?.in_conservazione ?? 0 },
  { etichetta: 'Totale archivio', valore: meta.total },
])

/** La checkbox di testata riflette solo i documenti effettivamente firmabili. */
const firmabili = computed(() => items.value.filter((doc) => doc.status === 'da_firmare'))
const tuttiSelezionati = computed(
  () => firmabili.value.length > 0 && selezionati.value.length === firmabili.value.length,
)

const aggiornaFiltri = () => applyFilters({ ...filtri })

let debounce = null
function cercaConDebounce() {
  clearTimeout(debounce)
  debounce = setTimeout(aggiornaFiltri, 400)
}

async function ricarica() {
  await Promise.all([fetchAll(), fetchContatori()])
}

/** Firma massiva: il caso d'uso reale dell'amministratore con molte pratiche. */
async function firmaMassiva() {
  await firmaSelezionati('FEQ')
}

onMounted(ricarica)
</script>
