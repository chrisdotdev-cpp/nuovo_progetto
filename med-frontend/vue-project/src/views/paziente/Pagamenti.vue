<template>
  <div class="flex-1 overflow-auto space-y-6">
    <!-- TITOLO -->
    <div class="flex flex-col justify-between">
      <h2 class="text-gray-900 text-xl sm:text-2xl font-bold mb-1 sm:mb-2">Pagamenti e Fatturazione</h2>
      <h4 class="text-gray-600">Gestisci i tuoi pagamenti e scarica le ricevute</h4>
    </div>

    <!-- RIEPILOGO -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
      <template v-if="loading">
        <div
          v-for="n in 4"
          :key="`skeleton-${n}`"
          class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-pulse"
        >
          <div class="h-4 bg-gray-200 rounded w-2/3 mb-4"></div>
          <div class="h-6 bg-gray-200 rounded w-1/2"></div>
        </div>
      </template>

      <template v-else>
        <!-- Totale pagato -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Totale Pagato</span>
            <i class="fa-solid fa-circle-check text-green-600"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ formatCurrency(riepilogo.pagato) }}</p>
          <p class="text-gray-500 text-sm">{{ riepilogo.numeroPagate }} fatture saldate</p>
        </div>

        <!-- In sospeso -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">In Sospeso</span>
            <i class="fa-regular fa-clock text-amber-500"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ formatCurrency(riepilogo.inSospeso) }}</p>
          <p class="text-gray-500 text-sm">{{ riepilogo.numeroInSospeso }} da saldare</p>
        </div>

        <!-- Scadute -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Scadute</span>
            <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ formatCurrency(riepilogo.scadute) }}</p>
          <p class="text-gray-500 text-sm">{{ riepilogo.numeroScadute }} in ritardo</p>
        </div>

        <!-- Totale fatturato -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Totale Fatturato</span>
            <i class="fa-solid fa-file-invoice text-blue-600"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ formatCurrency(riepilogo.fatturato) }}</p>
          <p class="text-gray-500 text-sm">{{ meta.total }} documenti</p>
        </div>
      </template>
    </div>

    <!-- STORICO -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-4 sm:p-6 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-gray-900 text-lg sm:text-xl font-bold">Storico Pagamenti</h2>

        <div class="flex gap-2 overflow-x-auto">
          <button
            v-for="f in filtriStato"
            :key="f.value"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm transition-colors whitespace-nowrap min-h-[36px] cursor-pointer',
              statoAttivo === f.value ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
            ]"
            @click="filtraPerStato(f.value)"
          >
            {{ f.label }}
          </button>
        </div>
      </div>

      <!-- Caricamento -->
      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="n in 3" :key="`row-skeleton-${n}`" class="h-16 bg-gray-100 rounded-lg animate-pulse"></div>
      </div>

      <!-- Nessuna fattura -->
      <div v-else-if="fatture.length === 0" class="p-10 text-center">
        <i class="fa-regular fa-file-lines text-3xl text-gray-300 mb-3 block"></i>
        <p class="text-gray-500">Nessuna fattura da mostrare.</p>
      </div>

      <template v-else>
        <!-- TABELLA: solo da tablet in su -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-gray-600 text-sm">Numero</th>
                <th class="px-6 py-3 text-left text-gray-600 text-sm">Data</th>
                <th class="px-6 py-3 text-left text-gray-600 text-sm">Scadenza</th>
                <th class="px-6 py-3 text-right text-gray-600 text-sm">Importo</th>
                <th class="px-6 py-3 text-right text-gray-600 text-sm">Residuo</th>
                <th class="px-6 py-3 text-left text-gray-600 text-sm">Stato</th>
                <th class="px-6 py-3 text-right text-gray-600 text-sm">Azioni</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
              <tr v-for="invoice in fatture" :key="invoice.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 font-mono text-sm text-gray-900">{{ invoice.number }}</td>
                <td class="px-6 py-4 text-gray-600">{{ formatDate(invoice.issue_date) }}</td>
                <td class="px-6 py-4 text-gray-600">{{ formatDate(invoice.due_date, '—') }}</td>
                <td class="px-6 py-4 text-right text-gray-900">{{ formatCurrency(invoice.total) }}</td>
                <td class="px-6 py-4 text-right" :class="invoice.balance > 0 ? 'text-red-600' : 'text-gray-400'">
                  {{ formatCurrency(invoice.balance) }}
                </td>
                <td class="px-6 py-4">
                  <span class="px-3 py-1 rounded-full text-xs whitespace-nowrap" :class="statusClass(invoice.status)">
                    {{ statusLabel(invoice.status) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      type="button"
                      class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors min-w-[36px] min-h-[36px] cursor-pointer"
                      aria-label="Dettaglio fattura"
                      @click="apriDettaglio(invoice)"
                    >
                      <i class="fa-regular fa-eye"></i>
                    </button>

                    <!-- Il pulsante di pagamento compare solo se resta un residuo -->
                    <button
                      v-if="invoice.balance > 0"
                      type="button"
                      class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors min-h-[36px] cursor-pointer"
                      @click="apriPagamento(invoice)"
                    >
                      Paga
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- CARD: su mobile la tabella diventa un elenco leggibile -->
        <ul class="md:hidden divide-y divide-gray-200">
          <li v-for="invoice in fatture" :key="invoice.id" class="p-4">
            <div class="flex items-start justify-between gap-3 mb-2">
              <div class="min-w-0">
                <p class="font-mono text-sm text-gray-900">{{ invoice.number }}</p>
                <p class="text-gray-500 text-sm">{{ formatDate(invoice.issue_date) }}</p>
              </div>
              <span class="px-3 py-1 rounded-full text-xs flex-shrink-0" :class="statusClass(invoice.status)">
                {{ statusLabel(invoice.status) }}
              </span>
            </div>

            <div class="flex items-end justify-between gap-3">
              <div>
                <p class="text-gray-900 text-lg font-semibold">{{ formatCurrency(invoice.total) }}</p>
                <p v-if="invoice.balance > 0" class="text-red-600 text-sm">
                  Residuo {{ formatCurrency(invoice.balance) }}
                </p>
              </div>

              <div class="flex gap-2">
                <button
                  type="button"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-lg min-h-[40px]"
                  @click="apriDettaglio(invoice)"
                >
                  Dettagli
                </button>
                <button
                  v-if="invoice.balance > 0"
                  type="button"
                  class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg min-h-[40px]"
                  @click="apriPagamento(invoice)"
                >
                  Paga
                </button>
              </div>
            </div>
          </li>
        </ul>

        <!-- PAGINAZIONE -->
        <div v-if="meta.last_page > 1" class="p-4 border-t border-gray-200 flex items-center justify-between">
          <button
            type="button"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm disabled:opacity-50 min-h-[40px]"
            :disabled="meta.current_page === 1"
            @click="prevPage"
          >
            Precedente
          </button>

          <span class="text-sm text-gray-600">Pagina {{ meta.current_page }} di {{ meta.last_page }}</span>

          <button
            type="button"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm disabled:opacity-50 min-h-[40px]"
            :disabled="meta.current_page === meta.last_page"
            @click="nextPage"
          >
            Successiva
          </button>
        </div>
      </template>
    </div>

    <!-- MODAL PAGAMENTO -->
    <div
      v-if="pagamento.invoice"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4"
      role="dialog"
      aria-modal="true"
      @click.self="chiudiPagamento"
    >
      <div class="bg-white w-full sm:max-w-md p-6 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-4">
          <h2 class="text-xl font-bold text-gray-900">Salda fattura</h2>
          <button
            type="button"
            class="p-2 text-gray-400 hover:text-gray-600 min-w-[40px] min-h-[40px]"
            aria-label="Chiudi"
            @click="chiudiPagamento"
          >
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <!-- Riepilogo fattura -->
        <div class="bg-gray-50 rounded-lg p-4 mb-4 space-y-1">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Numero</span>
            <span class="font-mono text-gray-900">{{ pagamento.invoice.number }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Totale</span>
            <span class="text-gray-900">{{ formatCurrency(pagamento.invoice.total) }}</span>
          </div>
          <div class="flex justify-between text-sm font-semibold">
            <span class="text-gray-700">Residuo</span>
            <span class="text-red-600">{{ formatCurrency(pagamento.invoice.balance) }}</span>
          </div>
        </div>

        <form novalidate @submit.prevent="confermaPagamento">
          <!-- Importo: si può saldare anche parzialmente -->
          <label for="pay-amount" class="block text-gray-700 mb-2 text-sm">Importo da versare</label>
          <input
            id="pay-amount"
            v-model.number="pagamento.amount"
            type="number"
            step="0.01"
            min="0.01"
            :max="pagamento.invoice.balance"
            class="w-full px-4 py-2.5 min-h-[44px] border rounded-lg mb-1 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            :class="fieldError('amount') ? 'border-red-400' : 'border-gray-300'"
          />
          <p v-if="fieldError('amount')" class="text-red-600 text-sm mb-3">{{ fieldError('amount') }}</p>
          <p v-else class="text-gray-500 text-xs mb-3">Puoi versare anche un acconto.</p>

          <!-- Metodo -->
          <label for="pay-method" class="block text-gray-700 mb-2 text-sm">Metodo di pagamento</label>
          <select
            id="pay-method"
            v-model="pagamento.method"
            class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg mb-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
          >
            <option value="carta">Carta di credito</option>
            <option value="bonifico">Bonifico bancario</option>
            <option value="contanti">Contanti in sede</option>
            <option value="satispay">Satispay</option>
            <option value="assicurazione">Assicurazione</option>
          </select>

          <!-- Dati carta: solo simulazione lato client, il PSP reale si integra a parte -->
          <div v-if="pagamento.method === 'carta'" class="space-y-3 mb-4">
            <input
              v-model="pagamento.cardNumber"
              type="text"
              inputmode="numeric"
              maxlength="19"
              placeholder="0000 0000 0000 0000"
              class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg"
            />
            <div class="grid grid-cols-2 gap-3">
              <input
                v-model="pagamento.cardExpiry"
                type="text"
                maxlength="5"
                placeholder="MM/AA"
                class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg"
              />
              <input
                v-model="pagamento.cardCvv"
                type="password"
                inputmode="numeric"
                maxlength="4"
                placeholder="CVV"
                class="w-full px-4 py-2.5 min-h-[44px] border border-gray-300 rounded-lg"
              />
            </div>
            <p class="text-xs text-gray-500">
              <i class="fa-solid fa-lock"></i>
              I dati della carta non vengono salvati: l'incasso viene registrato dal gestionale.
            </p>
          </div>

          <div class="flex flex-col-reverse sm:flex-row gap-3">
            <BaseButton variant="secondary" @click="chiudiPagamento">Annulla</BaseButton>
            <BaseButton type="submit" :loading="saving" :disabled="!importoValido">
              Paga {{ formatCurrency(pagamento.amount || 0) }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL DETTAGLIO -->
    <div
      v-if="dettaglio"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4"
      role="dialog"
      aria-modal="true"
      @click.self="dettaglio = null"
    >
      <div class="bg-white w-full sm:max-w-lg p-6 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h2 class="text-xl font-bold text-gray-900">Fattura {{ dettaglio.number }}</h2>
            <p class="text-gray-500 text-sm">{{ formatDate(dettaglio.issue_date) }}</p>
          </div>
          <button
            type="button"
            class="p-2 text-gray-400 hover:text-gray-600 min-w-[40px] min-h-[40px]"
            aria-label="Chiudi"
            @click="dettaglio = null"
          >
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <!-- Righe -->
        <ul class="divide-y divide-gray-100 mb-4">
          <li v-for="riga in dettaglio.items ?? []" :key="riga.id" class="py-3 flex justify-between gap-4">
            <div class="min-w-0">
              <p class="text-gray-900">{{ riga.description }}</p>
              <p class="text-gray-500 text-sm">{{ riga.quantity }} × {{ formatCurrency(riga.unit_price) }}</p>
            </div>
            <p class="text-gray-900 flex-shrink-0">{{ formatCurrency(riga.total) }}</p>
          </li>
        </ul>

        <!-- Totali -->
        <div class="border-t border-gray-200 pt-3 space-y-1 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600">Imponibile</span>
            <span class="text-gray-900">{{ formatCurrency(dettaglio.subtotal) }}</span>
          </div>
          <div v-if="dettaglio.tax_amount > 0" class="flex justify-between">
            <span class="text-gray-600">IVA ({{ dettaglio.tax_rate }}%)</span>
            <span class="text-gray-900">{{ formatCurrency(dettaglio.tax_amount) }}</span>
          </div>
          <div class="flex justify-between font-semibold text-base pt-2 border-t border-gray-100">
            <span class="text-gray-900">Totale</span>
            <span class="text-gray-900">{{ formatCurrency(dettaglio.total) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Già versato</span>
            <span class="text-green-600">{{ formatCurrency(dettaglio.paid_amount) }}</span>
          </div>
        </div>

        <!-- Pagamenti registrati -->
        <div v-if="dettaglio.payments?.length" class="mt-4 border-t border-gray-200 pt-3">
          <p class="text-gray-500 text-sm mb-2">Pagamenti registrati</p>
          <ul class="space-y-2">
            <li
              v-for="p in dettaglio.payments"
              :key="p.id"
              class="flex justify-between text-sm bg-gray-50 rounded-lg p-2"
            >
              <span class="text-gray-700 capitalize">{{ p.method }} · {{ formatDate(p.paid_at) }}</span>
              <span class="text-gray-900">{{ formatCurrency(p.amount) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import BaseButton from '@/components/ui/BaseButton.vue'
import { api } from '@/services/api'
import { useFatture } from '@/composables/useFatture'
import { useFormatters } from '@/composables/useFormatters'
import { useDashboardStore } from '@/store/dashboard'

const {
  items: fatture,
  meta,
  loading,
  saving,
  fetchAll,
  applyFilters,
  nextPage,
  prevPage,
  paga,
  fieldError,
  statusClass,
  statusLabel,
} = useFatture()

const dashboard = useDashboardStore()
const { formatCurrency, formatDate } = useFormatters()

/* -------------------------------------------------------------------------
 | Filtri
 * ---------------------------------------------------------------------- */
const filtriStato = [
  { value: '', label: 'Tutte' },
  { value: 'emessa', label: 'Da pagare' },
  { value: 'parziale', label: 'Parziali' },
  { value: 'scaduta', label: 'Scadute' },
  { value: 'pagata', label: 'Pagate' },
]

const statoAttivo = ref('')

const filtraPerStato = (value) => {
  statoAttivo.value = value
  applyFilters({ status: value })
}

/* -------------------------------------------------------------------------
 | Riepilogo
 |
 | Calcolato sulla pagina corrente: per un paziente le fatture sono poche.
 | Se il volume crescesse, si sposterebbe su un endpoint di aggregazione.
 * ---------------------------------------------------------------------- */
const riepilogo = computed(() => {
  const lista = fatture.value

  const somma = (predicato, campo = 'total') =>
    lista.filter(predicato).reduce((acc, f) => acc + Number(f[campo] ?? 0), 0)

  const pagate = lista.filter((f) => f.status === 'pagata')
  const inSospeso = lista.filter((f) => ['emessa', 'parziale'].includes(f.status))
  const scadute = lista.filter((f) => f.status === 'scaduta')

  return {
    pagato: somma(() => true, 'paid_amount'),
    numeroPagate: pagate.length,
    inSospeso: inSospeso.reduce((acc, f) => acc + Number(f.balance ?? 0), 0),
    numeroInSospeso: inSospeso.length,
    scadute: scadute.reduce((acc, f) => acc + Number(f.balance ?? 0), 0),
    numeroScadute: scadute.length,
    fatturato: somma(() => true),
  }
})

/* -------------------------------------------------------------------------
 | Pagamento
 * ---------------------------------------------------------------------- */
const pagamento = reactive({
  invoice: null,
  amount: 0,
  method: 'carta',
  cardNumber: '',
  cardExpiry: '',
  cardCvv: '',
})

const importoValido = computed(
  () =>
    pagamento.amount > 0 &&
    pagamento.invoice &&
    pagamento.amount <= Number(pagamento.invoice.balance) + 0.001,
)

const apriPagamento = (invoice) => {
  pagamento.invoice = invoice
  // Preimpostato al residuo: il caso più comune è saldare tutto
  pagamento.amount = Number(invoice.balance)
  pagamento.method = 'carta'
}

const chiudiPagamento = () => {
  pagamento.invoice = null
  pagamento.cardNumber = ''
  pagamento.cardExpiry = ''
  pagamento.cardCvv = ''
}

async function confermaPagamento() {
  if (!importoValido.value) return

  const esito = await paga(pagamento.invoice.id, {
    amount: pagamento.amount,
    method: pagamento.method,
    // Riferimento simulato: con un PSP reale qui arriva l'id della transazione
    transaction_ref: pagamento.method === 'carta' ? `SIM-${Date.now()}` : null,
  })

  if (esito) {
    chiudiPagamento()
    // L'invalidazione della cache la fa gia' useFatture.paga(): qui si ricarica
    // subito perche' la card "da pagare" della Panoramica e' a un click di distanza.
    dashboard.refresh()
  }
}

/* -------------------------------------------------------------------------
 | Dettaglio
 * ---------------------------------------------------------------------- */
const dettaglio = ref(null)

/** Il dettaglio richiede righe e pagamenti, che la lista non include. */
async function apriDettaglio(invoice) {
  const { data } = await api.invoices.get(invoice.id)
  dettaglio.value = data.data ?? data
}

// Blocca lo scroll di fondo quando un modal è aperto
watch([() => pagamento.invoice, dettaglio], ([pay, det]) => {
  document.body.style.overflow = pay || det ? 'hidden' : ''
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})

onMounted(() => fetchAll())
</script>
