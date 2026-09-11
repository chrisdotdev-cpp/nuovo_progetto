<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Calendario Prenotazioni</h1>
        <p class="text-start text-gray-500">Vista centralizzata di tutti gli appuntamenti</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2 disabled:opacity-60 cursor-pointer"
        :disabled="loading || !items.length"
        @click="esportaCsv"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download w-5 h-5">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" x2="12" y1="15" y2="3"></line>
        </svg>
        Esporta
      </button>
    </div>

    <!-- STATISTICHE calcolate sugli appuntamenti della data selezionata -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6">
      <div
        v-for="stat in statistiche"
        :key="stat.etichetta"
        class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-gray-200"
      >
        <p class="text-gray-600 mb-2 text-sm">{{ stat.etichetta }}</p>
        <p class="text-2xl font-bold text-gray-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-gray-200 rounded animate-pulse"></span>
          <span v-else>{{ stat.valore }}</span>
        </p>
      </div>
    </div>

    <!-- FILTRI -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="filtro-data" class="block text-gray-700 mb-2 text-sm font-medium">Data</label>
          <div class="relative">
            <i class="fa-regular fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            <input
              id="filtro-data"
              v-model="data"
              type="date"
              class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
              @change="ricarica"
            />
          </div>
        </div>

        <div>
          <label for="filtro-medico" class="block text-gray-700 mb-2 text-sm font-medium">Medico</label>
          <div class="relative">
            <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            <select
              id="filtro-medico"
              v-model="medicoId"
              class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent cursor-pointer"
              @change="ricarica"
            >
              <option value="">Tutti i medici</option>
              <option v-for="medico in medici" :key="medico.id" :value="medico.id">
                {{ medico.name }} — {{ medico.specialization }}
              </option>
            </select>
          </div>
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
        <i class="fa-regular fa-calendar-xmark text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">Nessuna prenotazione</p>
        <p class="text-gray-500 text-sm mt-1">Non ci sono appuntamenti per la data selezionata.</p>
      </div>

      <!-- Dati reali -->
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[720px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Paziente</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Medico</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Specializzazione</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Orario</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Stato</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Azioni</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="appuntamento in items" :key="appuntamento.id" class="hover:bg-gray-50">
              <td class="px-6 py-3">
                <p class="font-medium text-gray-900 truncate max-w-[200px]">
                  {{ appuntamento.patient?.name || '—' }}
                </p>
                <p class="text-xs text-gray-500">{{ appuntamento.reason || appuntamento.type }}</p>
              </td>

              <td class="px-6 py-3 text-sm text-gray-700 truncate max-w-[180px]">
                {{ appuntamento.doctor?.name || '—' }}
              </td>

              <td class="px-6 py-3 text-sm text-gray-500">
                {{ appuntamento.doctor?.specialization || '—' }}
              </td>

              <td class="px-6 py-3 text-sm text-gray-700 whitespace-nowrap">
                {{ appuntamento.time }}
                <span class="text-gray-400">({{ appuntamento.duration_minutes }}′)</span>
              </td>

              <td class="px-6 py-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="statusClass(appuntamento.status)">
                  {{ statusLabel(appuntamento.status) }}
                </span>
              </td>

              <td class="px-6 py-3">
                <div class="flex items-center gap-1">
                  <button
                    v-if="appuntamento.status === 'in_attesa'"
                    type="button"
                    class="w-11 h-11 rounded-lg hover:bg-green-50 text-green-600 disabled:opacity-40"
                    title="Conferma"
                    :disabled="saving"
                    @click="cambiaStato(appuntamento.id, 'confermato')"
                  >
                    <i class="fa-solid fa-check"></i>
                  </button>

                  <button
                    v-if="appuntamento.is_editable"
                    type="button"
                    class="w-11 h-11 rounded-lg hover:bg-red-50 text-red-600 disabled:opacity-40 cursor-pointer"
                    title="Annulla"
                    :disabled="saving"
                    @click="annullaConMotivo(appuntamento)"
                  >
                    <i class="fa-solid fa-xmark"></i>
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
        <span class="text-sm text-gray-500">{{ meta.total }} prenotazioni</span>
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
import { ref, computed, onMounted } from 'vue'
import { useAppuntamenti } from '@/composables/useAppuntamenti'
import { useMedici } from '@/composables/useMedici'
import { useFormatters } from '@/composables/useFormatters'

const {
  items, meta, loading, saving, isEmpty,
  applyFilters, nextPage, prevPage,
  cambiaStato, annulla, statusClass, statusLabel,
} = useAppuntamenti()

// Elenco medici per il filtro: nessun reparto hardcoded, arriva dal database
const { items: medici, fetchAll: fetchMedici } = useMedici()

const { toInputDate } = useFormatters()

const data = ref(toInputDate())
const medicoId = ref('')

/*
  Il filtro per data usa from/to sullo stesso giorno: l'endpoint richiede
  entrambi i parametri (scope `between`).
*/
const ricarica = () =>
  applyFilters({
    from: data.value,
    to: data.value,
    doctor_id: medicoId.value,
  })

/* Statistiche derivate: nessun numero inventato, si contano gli elementi reali. */
const statistiche = computed(() => {
  const totale = items.value.length
  const confermati = items.value.filter((a) => a.status === 'confermato').length
  const inAttesa = items.value.filter((a) => a.status === 'in_attesa').length
  const annullati = items.value.filter((a) => a.status === 'annullato').length

  // Tasso di occupazione: quota di slot non annullati sul totale della giornata
  const occupazione = totale ? Math.round(((totale - annullati) / totale) * 100) : 0

  return [
    { etichetta: 'Totale giornata', valore: totale },
    { etichetta: 'Confermati', valore: confermati },
    { etichetta: 'In attesa', valore: inAttesa },
    { etichetta: 'Tasso occupazione', valore: `${occupazione}%` },
  ]
})

async function annullaConMotivo(appuntamento) {
  const motivo = window.prompt('Motivo dell\'annullamento (facoltativo):') ?? null
  await annulla(appuntamento.id, motivo)
}

/** Export CSV lato client: nessuna dipendenza esterna per un file di poche righe. */
function esportaCsv() {
  const intestazione = ['Paziente', 'Medico', 'Specializzazione', 'Data', 'Orario', 'Stato']

  const righe = items.value.map((a) => [
    a.patient?.name ?? '',
    a.doctor?.name ?? '',
    a.doctor?.specialization ?? '',
    a.date ?? '',
    a.time ?? '',
    statusLabel(a.status),
  ])

  // Le virgole nei campi vanno protette, altrimenti Excel sfalsa le colonne
  const csv = [intestazione, ...righe]
    .map((riga) => riga.map((cella) => `"${String(cella).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `prenotazioni-${data.value}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  ricarica()
  fetchMedici()
})
</script>
