<template>
  <div class="space-y-6">
    <!-- TITOLO -->
    <div class="flex flex-col justify-between">
      <h2 class="text-xl sm:text-2xl font-bold mb-1 sm:mb-2">Cartella Clinica Digitale</h2>
      <h4 class="text-gray-500">Tutti i tuoi documenti medici in un unico posto</h4>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- COLONNA SINISTRA -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Ricerca e filtri -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="relative mb-4">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Cerca nella cartella clinica..."
              class="w-full pl-10 pr-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <!-- Filtri: scorrevoli orizzontalmente su mobile invece di andare a capo -->
          <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            <button
              v-for="cat in filterCategories"
              :key="cat.value"
              type="button"
              :class="[
                'px-4 py-2 rounded-lg transition-colors whitespace-nowrap min-h-[40px] text-sm flex items-center gap-2 cursor-pointer',
                activeCategory === cat.value
                  ? 'bg-blue-100 text-blue-700 font-medium'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
              ]"
              @click="activeCategory = cat.value"
            >
              {{ cat.label }}
              <span class="text-xs opacity-70">{{ conteggi[cat.value] ?? 0 }}</span>
            </button>
          </div>

          <!-- Nessun risultato -->
          <div
            v-if="!loading && filteredItems.length === 0"
            class="mt-4 text-center text-gray-500 bg-gray-50 rounded-xl border border-gray-200 py-8"
          >
            <i class="fa-regular fa-folder-open text-3xl text-gray-300 mb-3 block"></i>
            <p v-if="searchQuery || activeCategory !== 'tutti'">Nessun risultato per i filtri selezionati.</p>
            <p v-else>La tua cartella clinica è ancora vuota.</p>
          </div>
        </div>

        <!-- Lista voci -->
        <div v-if="loading" class="space-y-3">
          <div v-for="n in 4" :key="`skeleton-${n}`" class="h-28 bg-white rounded-xl border border-gray-200 animate-pulse"></div>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="item in filteredItems"
            :key="`${item.kind}-${item.id}`"
            role="button"
            tabindex="0"
            :class="[
              'bg-white rounded-xl border-2 p-4 cursor-pointer transition-all',
              isSelected(item) ? 'border-blue-500 shadow-sm' : 'border-gray-200 hover:border-gray-300',
            ]"
            @click="selectedItem = item"
            @keydown.enter="selectedItem = item"
            @keydown.space.prevent="selectedItem = item"
          >
            <div class="flex items-start gap-3 sm:gap-4">
              <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0" :class="item.iconBg">
                <i :class="[item.icon, item.iconColor]"></i>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <h3 class="text-gray-900 font-medium mb-1 truncate">{{ item.title }}</h3>
                  <span class="text-xs px-2 py-1 rounded flex-shrink-0" :class="item.badgeClass">
                    {{ item.typeLabel }}
                  </span>
                </div>

                <p class="text-gray-500 text-sm truncate">{{ item.subtitle }}</p>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-sm text-gray-600">
                  <span><i class="fa-regular fa-calendar"></i> {{ formatDate(item.date) }}</span>
                  <span v-if="item.sizeLabel"><i class="fa-regular fa-file"></i> {{ item.sizeLabel }}</span>
                </div>

                <!-- Azioni: solo i documenti hanno un file scaricabile -->
                <div v-if="item.kind === 'documento'" class="flex flex-wrap gap-2 mt-3">
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors min-h-[36px]"
                    :disabled="isDownloading(item.id)"
                    @click.stop="scarica(item)"
                  >
                    <i :class="isDownloading(item.id) ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-download'"></i>
                    Scarica
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- COLONNA DESTRA -->
      <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:sticky lg:top-6">
          <!-- STATO 1: nessuna voce selezionata -->
          <div v-if="!selectedItem" class="text-center py-8 text-gray-500">
            <i class="fa-regular fa-file-lines" style="font-size: 28px; margin-bottom: 10px"></i>
            <p>Seleziona una voce per vedere i dettagli</p>
          </div>

          <!-- STATO 2: dettaglio -->
          <div v-else class="space-y-4">
            <div>
              <h3 class="text-lg font-bold text-gray-900">{{ selectedItem.title }}</h3>
              <span class="inline-block text-xs px-2 py-1 rounded mt-2" :class="selectedItem.badgeClass">
                {{ selectedItem.typeLabel }}
              </span>
            </div>

            <dl class="space-y-3 text-sm border-t border-gray-200 pt-4">
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Data</dt>
                <dd class="text-gray-900 text-right">{{ formatDate(selectedItem.date) }}</dd>
              </div>

              <div v-if="selectedItem.subtitle" class="flex justify-between gap-4">
                <dt class="text-gray-500">Medico</dt>
                <dd class="text-gray-900 text-right">{{ selectedItem.subtitle }}</dd>
              </div>

              <div v-if="selectedItem.sizeLabel" class="flex justify-between gap-4">
                <dt class="text-gray-500">Dimensione</dt>
                <dd class="text-gray-900 text-right">{{ selectedItem.sizeLabel }}</dd>
              </div>

              <div v-if="selectedItem.code" class="flex justify-between gap-4">
                <dt class="text-gray-500">Codice</dt>
                <dd class="text-gray-900 text-right font-mono text-xs">{{ selectedItem.code }}</dd>
              </div>
            </dl>

            <!-- Descrizione della voce clinica -->
            <div v-if="selectedItem.description" class="border-t border-gray-200 pt-4">
              <p class="text-gray-500 text-sm mb-1">Descrizione</p>
              <p class="text-gray-800 text-sm whitespace-pre-line">{{ selectedItem.description }}</p>
            </div>

            <!-- Parametri vitali registrati dal medico -->
            <div v-if="hasVitals" class="border-t border-gray-200 pt-4">
              <p class="text-gray-500 text-sm mb-2">Parametri</p>
              <div class="grid grid-cols-2 gap-2">
                <div v-for="(value, key) in selectedItem.vitals" :key="key" class="bg-gray-50 rounded-lg p-2">
                  <p class="text-xs text-gray-500 capitalize">{{ key }}</p>
                  <p class="text-gray-900 font-medium">{{ value }}</p>
                </div>
              </div>
            </div>

            <!-- Farmaci della prescrizione -->
            <div v-if="selectedItem.items?.length" class="border-t border-gray-200 pt-4">
              <p class="text-gray-500 text-sm mb-2">Farmaci prescritti</p>
              <ul class="space-y-2">
                <li v-for="farmaco in selectedItem.items" :key="farmaco.id" class="bg-gray-50 rounded-lg p-3">
                  <p class="text-gray-900 font-medium">{{ farmaco.name }}</p>
                  <p class="text-gray-600 text-sm">
                    {{ [farmaco.dosage, farmaco.frequency].filter(Boolean).join(' · ') }}
                  </p>
                  <p v-if="farmaco.duration_days" class="text-gray-500 text-xs mt-1">
                    Per {{ farmaco.duration_days }} giorni
                  </p>
                </li>
              </ul>
            </div>

            <!-- Azioni -->
            <div v-if="selectedItem.kind === 'documento'" class="border-t border-gray-200 pt-4">
              <BaseButton
                icon="fa-solid fa-download"
                :loading="isDownloading(selectedItem.id)"
                @click="scarica(selectedItem)"
              >
                Scarica documento
              </BaseButton>
            </div>
          </div>
        </div>

        <!-- Riepilogo -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <h3 class="mb-4 font-semibold text-gray-900">Riepilogo documenti</h3>

          <ul class="space-y-2">
            <li
              v-for="cat in filterCategories.filter((c) => c.value !== 'tutti')"
              :key="cat.value"
              class="flex items-center justify-between text-sm"
            >
              <span class="text-gray-600">{{ cat.label }}</span>
              <span class="font-semibold text-gray-900">{{ conteggi[cat.value] ?? 0 }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'

import BaseButton from '@/components/ui/BaseButton.vue'
import { api } from '@/services/api'
import { useAuthStore } from '@/store/auth'
import { usePazienti } from '@/composables/usePazienti'
import { useFormatters } from '@/composables/useFormatters'
import { useFileDownload } from '@/composables/useFileDownload'

const auth = useAuthStore()
const { timeline, loadingTimeline: loading, fetchTimeline } = usePazienti()
const { formatDate } = useFormatters()
const { download, isDownloading } = useFileDownload()

const searchQuery = ref('')
const activeCategory = ref('tutti')
const selectedItem = ref(null)

const filterCategories = [
  { value: 'tutti', label: 'Tutti' },
  { value: 'referto', label: 'Referti' },
  { value: 'esame', label: 'Esami' },
  { value: 'diagnosi', label: 'Diagnosi' },
  { value: 'vaccinazione', label: 'Vaccinazioni' },
  { value: 'prescrizione', label: 'Prescrizioni' },
  { value: 'documento', label: 'Documenti' },
]

/*
  La cartella clinica arriva da un unico endpoint (/patients/{id}/timeline) che
  restituisce tre collezioni diverse. Qui vengono normalizzate in una lista sola
  con campi omogenei, così template e filtri restano semplici.
*/
const items = computed(() => {
  if (!timeline.value) return []

  const records = (timeline.value.records?.data ?? timeline.value.records ?? []).map((record) => ({
    kind: record.type, // referto | esame | diagnosi | nota | vaccinazione | intervento
    id: record.id,
    title: record.title,
    subtitle: record.doctor?.name ?? '',
    date: record.recorded_at,
    description: record.description,
    vitals: record.vitals,
    typeLabel: labelPerTipo(record.type),
    ...stilePerTipo(record.type),
  }))

  const prescriptions = (timeline.value.prescriptions?.data ?? timeline.value.prescriptions ?? []).map((p) => ({
    kind: 'prescrizione',
    id: p.id,
    title: `Ricetta ${p.code}`,
    subtitle: p.doctor?.name ?? '',
    date: p.issued_at,
    description: p.notes,
    code: p.code,
    items: p.items ?? [],
    typeLabel: 'Prescrizione',
    ...stilePerTipo('prescrizione'),
  }))

  const documents = (timeline.value.documents?.data ?? timeline.value.documents ?? []).map((doc) => ({
    kind: 'documento',
    id: doc.id,
    title: doc.title,
    subtitle: doc.uploader?.name ?? '',
    date: doc.created_at,
    description: doc.description,
    sizeLabel: doc.size_label,
    originalName: doc.original_name,
    typeLabel: 'Documento',
    ...stilePerTipo('documento'),
  }))

  // Ordine cronologico decrescente: la voce più recente resta in cima
  return [...records, ...prescriptions, ...documents].sort((a, b) => new Date(b.date) - new Date(a.date))
})

/** Filtro per categoria + ricerca testuale su titolo, medico e descrizione. */
const filteredItems = computed(() => {
  const termine = searchQuery.value.trim().toLowerCase()

  return items.value.filter((item) => {
    const perCategoria = activeCategory.value === 'tutti' || item.kind === activeCategory.value

    const perTermine =
      !termine ||
      item.title?.toLowerCase().includes(termine) ||
      item.subtitle?.toLowerCase().includes(termine) ||
      item.description?.toLowerCase().includes(termine)

    return perCategoria && perTermine
  })
})

/** Contatori mostrati nei filtri e nel riepilogo. */
const conteggi = computed(() => {
  const risultato = { tutti: items.value.length }

  items.value.forEach((item) => {
    risultato[item.kind] = (risultato[item.kind] ?? 0) + 1
  })

  return risultato
})

const hasVitals = computed(
  () => selectedItem.value?.vitals && Object.keys(selectedItem.value.vitals).length > 0,
)

const isSelected = (item) =>
  selectedItem.value?.id === item.id && selectedItem.value?.kind === item.kind

/* -------------------------------------------------------------------------
 | Presentazione per tipo
 * ---------------------------------------------------------------------- */
function labelPerTipo(type) {
  return (
    {
      referto: 'Referto',
      esame: 'Esame',
      diagnosi: 'Diagnosi',
      nota: 'Nota',
      vaccinazione: 'Vaccinazione',
      intervento: 'Intervento',
    }[type] ?? type
  )
}

function stilePerTipo(type) {
  const stili = {
    referto: { icon: 'fa-regular fa-file-lines', iconBg: 'bg-blue-100', iconColor: 'text-blue-600', badgeClass: 'bg-blue-100 text-blue-700' },
    esame: { icon: 'fa-solid fa-flask', iconBg: 'bg-teal-100', iconColor: 'text-teal-600', badgeClass: 'bg-teal-100 text-teal-700' },
    diagnosi: { icon: 'fa-solid fa-stethoscope', iconBg: 'bg-purple-100', iconColor: 'text-purple-600', badgeClass: 'bg-purple-100 text-purple-700' },
    vaccinazione: { icon: 'fa-solid fa-syringe', iconBg: 'bg-green-100', iconColor: 'text-green-600', badgeClass: 'bg-green-100 text-green-700' },
    intervento: { icon: 'fa-solid fa-user-doctor', iconBg: 'bg-amber-100', iconColor: 'text-amber-600', badgeClass: 'bg-amber-100 text-amber-700' },
    prescrizione: { icon: 'fa-solid fa-capsules', iconBg: 'bg-indigo-100', iconColor: 'text-indigo-600', badgeClass: 'bg-indigo-100 text-indigo-700' },
    documento: { icon: 'fa-regular fa-folder-open', iconBg: 'bg-gray-100', iconColor: 'text-gray-600', badgeClass: 'bg-gray-100 text-gray-700' },
  }

  return stili[type] ?? stili.documento
}

/* -------------------------------------------------------------------------
 | Azioni
 * ---------------------------------------------------------------------- */
const scarica = (item) =>
  download(() => api.documents.download(item.id), item.originalName ?? `${item.title}.pdf`, item.id)

onMounted(async () => {
  // Il paziente autenticato conosce già il proprio profilo dallo store auth
  let patientId = auth.patientId

  // Fallback: se il profilo non fosse in cache lo si recupera dall'API
  if (!patientId) {
    const { data } = await api.patients.me()
    patientId = (data.data ?? data)?.id
  }

  if (patientId) await fetchTimeline(patientId)
})
</script>
