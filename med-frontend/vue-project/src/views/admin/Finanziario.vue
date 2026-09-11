<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Area Finanziaria</h1>
        <p class="text-start text-gray-500">Fatturato, incassi e scadenzario</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2 disabled:opacity-60 cursor-pointer"
        :disabled="loading || !items.length"
        @click="esportaCsv"
      >
        <i class="fa-solid fa-download"></i>
        Esporta
      </button>
    </div>

    <!-- ===================== PERIODO ===================== -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label for="periodo-da" class="block text-gray-700 mb-2 text-sm font-medium">Dal</label>
          <input
            id="periodo-da"
            v-model="periodo.from"
            type="date"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
            @change="ricarica"
          >
        </div>

        <div>
          <label for="periodo-a" class="block text-gray-700 mb-2 text-sm font-medium">Al</label>
          <input
            id="periodo-a"
            v-model="periodo.to"
            type="date"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
            @change="ricarica"
          >
        </div>

        <div>
          <label for="periodo-stato" class="block text-gray-700 mb-2 text-sm font-medium">Stato fattura</label>
          <select
            id="periodo-stato"
            v-model="statoFattura"
            class="w-full min-h-[44px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 cursor-pointer"
            @change="ricarica"
          >
            <option value="">Tutte</option>
            <option value="emessa">Da pagare</option>
            <option value="parziale">Parziali</option>
            <option value="pagata">Pagate</option>
            <option value="scaduta">Scadute</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ===================== KPI ===================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div
        v-for="kpi in kpiCards"
        :key="kpi.etichetta"
        class="p-6 rounded-xl shadow-sm border"
        :class="kpi.stile"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm opacity-80">{{ kpi.etichetta }}</span>
          <i :class="kpi.icon"></i>
        </div>
        <p v-if="loadingReport" class="h-8 w-28 bg-black/10 rounded animate-pulse"></p>
        <p v-else class="text-2xl font-bold">{{ kpi.valore }}</p>
        <p v-if="!loadingReport && kpi.dettaglio" class="text-sm opacity-70 mt-1">{{ kpi.dettaglio }}</p>
      </div>
    </div>

    <!-- ===================== INCASSI PER METODO ===================== -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
      <h2 class="text-gray-900 font-semibold mb-4">Incassi per metodo di pagamento</h2>

      <div v-if="loadingReport" class="space-y-4">
        <div v-for="n in 3" :key="n" class="h-10 bg-gray-100 rounded animate-pulse"></div>
      </div>

      <p v-else-if="!metodiPagamento.length" class="text-gray-500 text-sm py-6 text-center">
        Nessun incasso registrato nel periodo selezionato.
      </p>

      <div v-else class="space-y-4">
        <div v-for="metodo in metodiPagamento" :key="metodo.nome">
          <div class="flex justify-between mb-2 text-sm">
            <span class="text-gray-600 capitalize">{{ etichettaMetodo(metodo.nome) }}</span>
            <span class="text-gray-900 font-medium">{{ formatCurrency(metodo.totale) }}</span>
          </div>
          <!-- Barra proporzionale al metodo più usato: lettura immediata del mix incassi -->
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div
              class="bg-blue-600 h-2 rounded-full transition-all"
              :style="{ width: `${metodo.percentuale}%` }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== ELENCO FATTURE ===================== -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <h2 class="text-gray-900 font-semibold p-6 pb-4">Fatture del periodo</h2>

      <div v-if="loading" class="p-6 pt-0 space-y-3">
        <div v-for="n in 5" :key="n" class="h-12 bg-gray-100 rounded-lg animate-pulse"></div>
      </div>

      <div v-else-if="isEmpty" class="p-12 text-center">
        <i class="fa-regular fa-file-invoice text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">Nessuna fattura</p>
        <p class="text-gray-500 text-sm mt-1">Non ci sono documenti contabili nel periodo selezionato.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[820px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Numero</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Paziente</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Emissione</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Scadenza</th>
              <th class="px-6 py-3 text-right text-gray-600 text-sm font-medium">Totale</th>
              <th class="px-6 py-3 text-right text-gray-600 text-sm font-medium">Saldo</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Stato</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Azioni</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="fattura in items" :key="fattura.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">{{ fattura.number }}</td>

              <td class="px-6 py-4 text-gray-600 text-sm truncate max-w-[180px]">
                {{ fattura.patient?.name || '—' }}
              </td>

              <td class="px-6 py-4 text-gray-600 text-sm whitespace-nowrap">
                {{ formatDate(fattura.issue_date) }}
              </td>

              <td
                class="px-6 py-4 text-sm whitespace-nowrap"
                :class="scaduta(fattura) ? 'text-red-600 font-medium' : 'text-gray-600'"
              >
                {{ formatDate(fattura.due_date, '—') }}
              </td>

              <td class="px-6 py-4 text-right text-gray-900 whitespace-nowrap">
                {{ formatCurrency(fattura.total) }}
              </td>

              <td class="px-6 py-4 text-right whitespace-nowrap" :class="fattura.balance > 0 ? 'text-amber-700 font-medium' : 'text-green-700'">
                {{ formatCurrency(fattura.balance) }}
              </td>

              <td class="px-6 py-4">
                <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap" :class="statusClass(fattura.status)">
                  {{ statusLabel(fattura.status) }}
                </span>
              </td>

              <td class="px-6 py-4">
                <button
                  v-if="fattura.balance > 0"
                  type="button"
                  class="min-h-[44px] px-3 py-2 rounded-lg text-green-700 hover:bg-green-200 bg-green-100 text-sm font-medium cursor-pointer whitespace-nowrap"
                  @click="apriIncasso(fattura)"
                >
                  <i class="fa-solid fa-euro-sign mr-1"></i> Incassa
                </button>
                <span v-else class="text-gray-400 text-sm">—</span>
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
        <span class="text-sm text-gray-500">{{ meta.total }} fatture</span>
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

    <!-- ===================== MODALE INCASSO ===================== -->
    <div
      v-if="modaleIncasso"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiIncasso"
    >
      <form
        class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto"
        novalidate
        @submit.prevent="registraIncasso"
      >
        <h3 class="text-lg font-bold mb-1">Registra incasso</h3>
        <p class="text-sm text-gray-500 mb-6">
          Fattura {{ modaleIncasso.number }} — saldo residuo
          <strong>{{ formatCurrency(modaleIncasso.balance) }}</strong>
        </p>

        <BaseInput
          v-model.number="formIncasso.amount"
          label="Importo"
          type="number"
          required
          :error="fieldError('amount')"
        />

        <div class="mb-5">
          <label for="incasso-metodo" class="block text-gray-700 mb-2 font-medium">Metodo</label>
          <select
            id="incasso-metodo"
            v-model="formIncasso.method"
            class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 cursor-pointer"
          >
            <option value="contanti">Contanti</option>
            <option value="carta">Carta</option>
            <option value="bonifico">Bonifico</option>
            <option value="assegno">Assegno</option>
          </select>
        </div>

        <BaseInput
          v-model="formIncasso.transaction_ref"
          label="Riferimento transazione"
          hint="CRO, numero POS, numero assegno..."
          :error="fieldError('transaction_ref')"
        />

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiIncasso">Annulla</BaseButton>
          <BaseButton type="submit" :loading="saving" :block="false">Registra</BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useFatture } from '@/composables/useFatture'
import { useFormatters } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'

import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

/*
  Due sorgenti distinte:
  - /invoices/report -> aggregati del periodo (fatturato, incassato, mix pagamenti)
  - /invoices        -> elenco paginato con lo scadenzario
  Tutti gli importi sono calcolati dal backend: qui non si fa aritmetica sui soldi.
*/
const {
  items, meta, loading, saving, isEmpty,
  applyFilters, nextPage, prevPage,
  report, loadingReport, fetchReport, paga,
  statusClass, statusLabel, fieldError, resetErrors,
} = useFatture()

const { formatCurrency, formatDate, toInputDate } = useFormatters()
const toast = useToast()

/* Periodo predefinito: mese corrente, il taglio con cui si ragiona in contabilità. */
const oggi = new Date()
const periodo = reactive({
  from: toInputDate(new Date(oggi.getFullYear(), oggi.getMonth(), 1)),
  to: toInputDate(oggi),
})

const statoFattura = ref('')

const ricarica = () => {
  applyFilters({ from: periodo.from, to: periodo.to, status: statoFattura.value })
  fetchReport({ from: periodo.from, to: periodo.to })
}

/* ---------------------- KPI ---------------------- */
const kpiCards = computed(() => [
  {
    etichetta: 'Fatturato',
    valore: formatCurrency(report.value.fatturato ?? 0),
    dettaglio: `${report.value.fatture_totali ?? 0} fatture emesse`,
    icon: 'fa-solid fa-file-invoice',
    stile: 'bg-white border-gray-200 text-gray-900',
  },
  {
    etichetta: 'Incassato',
    valore: formatCurrency(report.value.incassato ?? 0),
    dettaglio: `${percentualeIncasso.value}% del fatturato`,
    icon: 'fa-solid fa-circle-check',
    stile: 'bg-green-50 border-green-200 text-green-900',
  },
  {
    etichetta: 'Da incassare',
    valore: formatCurrency(report.value.da_incassare ?? 0),
    dettaglio: 'Saldo aperto nel periodo',
    icon: 'fa-solid fa-hourglass-half',
    stile: 'bg-amber-50 border-amber-200 text-amber-900',
  },
  {
    etichetta: 'Fatture scadute',
    valore: report.value.fatture_scadute ?? 0,
    dettaglio: 'Da sollecitare',
    icon: 'fa-solid fa-triangle-exclamation',
    stile: 'bg-red-50 border-red-200 text-red-900',
  },
])

const percentualeIncasso = computed(() => {
  const fatturato = Number(report.value.fatturato ?? 0)
  if (!fatturato) return 0
  return Math.round((Number(report.value.incassato ?? 0) / fatturato) * 100)
})

/* Mix incassi: si normalizza in percentuale sul metodo più usato per la barra. */
const metodiPagamento = computed(() => {
  const perMetodo = report.value.per_metodo ?? {}
  const voci = Object.entries(perMetodo).map(([nome, totale]) => ({ nome, totale: Number(totale) }))

  if (!voci.length) return []

  const massimo = Math.max(...voci.map((v) => v.totale)) || 1

  return voci
    .sort((a, b) => b.totale - a.totale)
    .map((voce) => ({ ...voce, percentuale: Math.round((voce.totale / massimo) * 100) }))
})

const etichettaMetodo = (metodo) =>
  ({
    contanti: 'Contanti',
    carta: 'Carta di credito/debito',
    bonifico: 'Bonifico bancario',
    assegno: 'Assegno',
  })[metodo] ?? metodo

/** Una fattura è in ritardo se la scadenza è passata e resta un saldo aperto. */
const scaduta = (fattura) =>
  Boolean(fattura.due_date) && new Date(fattura.due_date) < new Date() && fattura.balance > 0

/* ---------------------- Incasso ---------------------- */
const modaleIncasso = ref(null)
const formIncasso = reactive({ amount: 0, method: 'contanti', transaction_ref: '' })

function apriIncasso(fattura) {
  resetErrors()
  modaleIncasso.value = fattura
  // Precompilato con il saldo residuo: il caso più frequente è il saldo totale
  Object.assign(formIncasso, { amount: fattura.balance, method: 'contanti', transaction_ref: '' })
}

function chiudiIncasso() {
  modaleIncasso.value = null
  resetErrors()
}

async function registraIncasso() {
  if (!formIncasso.amount || formIncasso.amount <= 0) {
    toast.error('Indica un importo maggiore di zero.')
    return
  }

  if (formIncasso.amount > modaleIncasso.value.balance) {
    toast.error('L\'importo supera il saldo residuo della fattura.')
    return
  }

  const esito = await paga(modaleIncasso.value.id, { ...formIncasso })

  if (esito) {
    chiudiIncasso()
    await fetchReport({ from: periodo.from, to: periodo.to })
  }
}

/* ---------------------- Export ---------------------- */
function esportaCsv() {
  const intestazione = ['Numero', 'Paziente', 'Emissione', 'Scadenza', 'Totale', 'Incassato', 'Saldo', 'Stato']

  const righe = items.value.map((f) => [
    f.number,
    f.patient?.name ?? '',
    f.issue_date ?? '',
    f.due_date ?? '',
    f.total,
    f.paid_amount,
    f.balance,
    statusLabel(f.status),
  ])

  const csv = [intestazione, ...righe]
    .map((riga) => riga.map((cella) => `"${String(cella).replace(/"/g, '""')}"`).join(';'))
    .join('\n')

  // BOM iniziale: senza, Excel in italiano sbaglia la codifica degli accenti
  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `fatture-${periodo.from}_${periodo.to}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(ricarica)
</script>
