<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Farmacia e Magazzino</h1>
        <p class="text-start text-gray-500">Giacenze, scadenze e movimenti di carico/scarico</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2 cursor-pointer"
        @click="apriCreazione"
      >
        <i class="fa-solid fa-plus"></i>
        Nuovo farmaco
      </button>
    </div>

    <!-- ===================== RIEPILOGO ===================== -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6">
      <div class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-gray-200">
        <p class="text-gray-600 text-sm mb-2">Farmaci attivi</p>
        <p class="text-2xl font-bold text-gray-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-gray-200 rounded animate-pulse"></span>
          <span v-else>{{ riepilogo.totale_farmaci ?? 0 }}</span>
        </p>
      </div>

      <div class="bg-amber-50 p-4 sm:p-6 rounded-xl shadow-sm border border-amber-200">
        <p class="text-amber-700 text-sm mb-2">In esaurimento</p>
        <p class="text-2xl font-bold text-amber-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-amber-200 rounded animate-pulse"></span>
          <span v-else>{{ riepilogo.in_esaurimento ?? 0 }}</span>
        </p>
      </div>

      <div class="bg-red-50 p-4 sm:p-6 rounded-xl shadow-sm border border-red-200">
        <p class="text-red-700 text-sm mb-2">In scadenza (90 gg)</p>
        <p class="text-2xl font-bold text-red-900">
          <span v-if="loading" class="inline-block w-10 h-6 bg-red-200 rounded animate-pulse"></span>
          <span v-else>{{ riepilogo.in_scadenza_90g ?? 0 }}</span>
        </p>
      </div>

      <div class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-gray-200">
        <p class="text-gray-600 text-sm mb-2">Valore magazzino</p>
        <p class="text-2xl font-bold text-gray-900">
          <span v-if="loading" class="inline-block w-20 h-6 bg-gray-200 rounded animate-pulse"></span>
          <span v-else>{{ formatCurrency(riepilogo.valore_stock ?? 0) }}</span>
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
            placeholder="Nome, principio attivo o codice AIC..."
            aria-label="Cerca farmaco"
            class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
          >
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1">
          <button
            v-for="tab in tabFiltri"
            :key="tab.valore"
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg transition-colors whitespace-nowrap flex-shrink-0 cursor-pointer"
            :class="filtroAttivo === tab.valore
              ? 'bg-purple-100 text-purple-700 font-semibold'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
            @click="filtra(tab.valore)"
          >
            {{ tab.etichetta }}
          </button>
        </div>
      </div>
    </div>

    <!-- ===================== TABELLA ===================== -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="n in 6" :key="n" class="h-12 bg-gray-100 rounded-lg animate-pulse"></div>
      </div>

      <div v-else-if="isEmpty" class="p-12 text-center">
        <i class="fa-solid fa-capsules text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">Nessun farmaco trovato</p>
        <p class="text-gray-500 text-sm mt-1">
          {{ hasFilters ? 'Nessun risultato con i filtri attivi.' : 'Il magazzino è ancora vuoto.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Farmaco</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Principio attivo</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Giacenza</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Stato</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Scadenza</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Prezzo</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Azioni</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="farmaco in items" :key="farmaco.id" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <p class="text-gray-900 font-medium truncate max-w-[220px]">{{ farmaco.name }}</p>
                <p class="text-xs text-gray-500">
                  {{ farmaco.form || '—' }}
                  <span v-if="farmaco.dosage"> · {{ farmaco.dosage }}</span>
                  <span v-if="farmaco.requires_prescription" class="text-red-600 font-medium"> · Ricetta</span>
                </p>
              </td>

              <td class="px-6 py-4 text-gray-600 text-sm truncate max-w-[180px]">
                {{ farmaco.active_ingredient || '—' }}
              </td>

              <td class="px-6 py-4 text-sm">
                <span class="font-semibold text-gray-900">{{ farmaco.stock_quantity }}</span>
                <span class="text-gray-400"> / min {{ farmaco.min_stock }}</span>
              </td>

              <td class="px-6 py-4">
                <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap" :class="stockClass(farmaco.stock_status)">
                  {{ stockLabel(farmaco.stock_status) }}
                </span>
              </td>

              <td class="px-6 py-4 text-sm whitespace-nowrap" :class="inScadenza(farmaco) ? 'text-red-600 font-medium' : 'text-gray-600'">
                {{ formatDate(farmaco.expiry_date, '—') }}
              </td>

              <td class="px-6 py-4 text-gray-900 text-sm whitespace-nowrap">
                {{ formatCurrency(farmaco.price) }}
              </td>

              <td class="px-6 py-4">
                <div class="flex gap-1">
                  <button
                    type="button"
                    class="w-11 h-11 hover:bg-green-50 rounded-lg text-green-600 cursor-pointer"
                    title="Movimento di magazzino"
                    @click="apriMovimento(farmaco)"
                  >
                    <i class="fa-solid fa-right-left"></i>
                  </button>

                  <button
                    type="button"
                    class="w-11 h-11 hover:bg-gray-100 rounded-lg text-gray-600 cursor-pointer"
                    title="Modifica"
                    @click="apriModifica(farmaco)"
                  >
                    <i class="fa-regular fa-pen-to-square"></i>
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
        <span class="text-sm text-gray-500">{{ meta.total }} farmaci</span>
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

    <!-- ===================== MODALE FARMACO ===================== -->
    <div
      v-if="modaleFarmaco"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModali"
    >
      <form
        class="bg-white w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto"
        novalidate
        @submit.prevent="salvaFarmaco"
      >
        <h3 class="text-lg font-bold mb-6">{{ inModifica ? 'Modifica farmaco' : 'Nuovo farmaco' }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
          <BaseInput v-model="form.name" label="Nome commerciale" required :error="fieldError('name')" />
          <BaseInput v-model="form.active_ingredient" label="Principio attivo" :error="fieldError('active_ingredient')" />
          <BaseInput v-model="form.aic_code" label="Codice AIC" :error="fieldError('aic_code')" />
          <BaseInput v-model="form.manufacturer" label="Produttore" :error="fieldError('manufacturer')" />
          <BaseInput v-model="form.form" label="Forma farmaceutica" hint="Compresse, sciroppo, fiale..." />
          <BaseInput v-model="form.dosage" label="Dosaggio" hint="500 mg, 10 ml..." />
          <BaseInput v-model="form.price" label="Prezzo (€)" type="number" :error="fieldError('price')" />
          <BaseInput v-model="form.min_stock" label="Scorta minima" type="number" :error="fieldError('min_stock')" />

          <!-- La giacenza si imposta solo alla creazione: poi si muove con carico/scarico -->
          <BaseInput
            v-if="!inModifica"
            v-model="form.stock_quantity"
            label="Giacenza iniziale"
            type="number"
            :error="fieldError('stock_quantity')"
          />

          <BaseInput v-model="form.batch" label="Lotto" :error="fieldError('batch')" />
          <BaseInput v-model="form.expiry_date" label="Scadenza" type="date" :error="fieldError('expiry_date')" />
        </div>

        <label class="flex items-center gap-2 mb-5 min-h-[44px]">
          <input v-model="form.requires_prescription" type="checkbox" class="w-4 h-4 rounded border-gray-300 cursor-pointer">
          <span class="text-gray-700">Vendibile solo su ricetta</span>
        </label>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiModali">Annulla</BaseButton>
          <BaseButton type="submit" :loading="saving" :block="false">
            {{ inModifica ? 'Salva modifiche' : 'Crea farmaco' }}
          </BaseButton>
        </div>
      </form>
    </div>

    <!-- ===================== MODALE MOVIMENTO ===================== -->
    <div
      v-if="modaleMovimento"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModali"
    >
      <form
        class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto"
        novalidate
        @submit.prevent="registraMovimento"
      >
        <h3 class="text-lg font-bold mb-1">Movimento di magazzino</h3>
        <p class="text-sm text-gray-500 mb-6">
          {{ modaleMovimento.name }} — giacenza attuale: <strong>{{ modaleMovimento.stock_quantity }}</strong>
        </p>

        <div class="mb-5">
          <label for="mov-tipo" class="block text-gray-700 mb-2 font-medium">Tipo di movimento</label>
          <select
            id="mov-tipo"
            v-model="formMovimento.type"
            class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 cursor-pointer"
          >
            <option value="carico">Carico (ingresso merce)</option>
            <option value="scarico">Scarico (uso o vendita)</option>
            <option value="reso">Reso al fornitore</option>
            <option value="scaduto">Scarico per scadenza</option>
            <option value="rettifica">Rettifica inventariale</option>
          </select>
        </div>

        <BaseInput
          v-model.number="formMovimento.quantity"
          label="Quantità"
          type="number"
          required
          :error="fieldError('quantity')"
        />

        <BaseInput
          v-model="formMovimento.reason"
          label="Causale"
          hint="Fornitore, numero DDT, motivo della rettifica..."
          :error="fieldError('reason')"
        />

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiModali">Annulla</BaseButton>
          <BaseButton type="submit" :loading="saving" :block="false">Registra movimento</BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useApiRequest } from '@/composables/useApiRequest'
import { useFormatters } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'

import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const {
  items, meta, loading, saving, isEmpty, hasFilters,
  fetchAll, applyFilters, nextPage, prevPage,
  create, update, replace, fieldError, resetErrors, run,
} = useResource(api.medicines, {
  label: 'Farmaco',
  labelPlural: 'Farmaci',
  perPage: 20,
  defaultFilters: { q: '', filter: '', active: true },
})

const { formatCurrency, formatDate } = useFormatters()
const toast = useToast()

/* Riepilogo aggregato: endpoint dedicato, non si somma nulla lato client. */
const riepilogo = ref({})
const { run: runRiepilogo } = useApiRequest()

const caricaRiepilogo = () =>
  runRiepilogo(() => api.medicines.summary(), {
    showToast: false,
    onSuccess: (payload) => {
      riepilogo.value = payload?.data ?? {}
    },
  })

/* ---------------------- Filtri ---------------------- */
const ricerca = ref('')
const filtroAttivo = ref('')

const tabFiltri = [
  { valore: '', etichetta: 'Tutti' },
  { valore: 'low_stock', etichetta: 'In esaurimento' },
  { valore: 'expiring', etichetta: 'In scadenza' },
]

const aggiornaFiltri = () => applyFilters({ q: ricerca.value, filter: filtroAttivo.value })

function filtra(valore) {
  filtroAttivo.value = valore
  aggiornaFiltri()
}

let debounce = null
watch(ricerca, () => {
  clearTimeout(debounce)
  debounce = setTimeout(aggiornaFiltri, 400)
})

/* ---------------------- Modali ---------------------- */
const modaleFarmaco = ref(false)
const modaleMovimento = ref(null)
const inModifica = ref(false)
const farmacoInModifica = ref(null)

const formVuoto = () => ({
  name: '',
  active_ingredient: '',
  aic_code: '',
  form: '',
  dosage: '',
  manufacturer: '',
  price: '',
  requires_prescription: false,
  stock_quantity: '',
  min_stock: '',
  batch: '',
  expiry_date: '',
})

const form = reactive(formVuoto())
const formMovimento = reactive({ type: 'carico', quantity: 1, reason: '' })

function apriCreazione() {
  Object.assign(form, formVuoto())
  resetErrors()
  inModifica.value = false
  farmacoInModifica.value = null
  modaleFarmaco.value = true
}

function apriModifica(farmaco) {
  Object.assign(form, formVuoto(), {
    name: farmaco.name ?? '',
    active_ingredient: farmaco.active_ingredient ?? '',
    aic_code: farmaco.aic_code ?? '',
    form: farmaco.form ?? '',
    dosage: farmaco.dosage ?? '',
    manufacturer: farmaco.manufacturer ?? '',
    price: farmaco.price ?? '',
    requires_prescription: Boolean(farmaco.requires_prescription),
    min_stock: farmaco.min_stock ?? '',
    batch: farmaco.batch ?? '',
    expiry_date: farmaco.expiry_date ?? '',
  })

  resetErrors()
  inModifica.value = true
  farmacoInModifica.value = farmaco
  modaleFarmaco.value = true
}

function apriMovimento(farmaco) {
  resetErrors()
  Object.assign(formMovimento, { type: 'carico', quantity: 1, reason: '' })
  modaleMovimento.value = farmaco
}

function chiudiModali() {
  modaleFarmaco.value = false
  modaleMovimento.value = null
  resetErrors()
}

/* ---------------------- Azioni ---------------------- */
async function salvaFarmaco() {
  if (!form.name.trim()) {
    toast.error('Il nome del farmaco è obbligatorio.')
    return
  }

  // I campi numerici vuoti vanno inviati come null, non come stringa vuota
  const payload = {
    ...form,
    name: form.name.trim(),
    price: form.price === '' ? null : Number(form.price),
    min_stock: form.min_stock === '' ? null : Number(form.min_stock),
    stock_quantity: form.stock_quantity === '' ? null : Number(form.stock_quantity),
    expiry_date: form.expiry_date || null,
  }

  if (inModifica.value) delete payload.stock_quantity

  const esito = inModifica.value
    ? await update(farmacoInModifica.value.id, payload)
    : await create(payload)

  if (esito) {
    chiudiModali()
    await caricaRiepilogo()
  }
}

/** La giacenza si modifica solo con un movimento tracciato, mai in modifica diretta. */
async function registraMovimento() {
  if (!formMovimento.quantity || formMovimento.quantity < 1) {
    toast.error('Indica una quantità di almeno 1.')
    return
  }

  const esito = await run(
    () => api.medicines.move(modaleMovimento.value.id, { ...formMovimento }),
    {
      successMessage: 'Movimento registrato.',
      onSuccess: (payload) => {
        const aggiornato = payload?.data ?? payload
        if (aggiornato?.id) replace(aggiornato)
      },
    },
  )

  if (esito) {
    chiudiModali()
    await Promise.all([fetchAll(), caricaRiepilogo()])
  }
}

/* ---------------------- Presentazione ---------------------- */
const stockClass = (stato) =>
  ({
    disponibile: 'bg-green-100 text-green-700',
    in_esaurimento: 'bg-amber-100 text-amber-700',
    esaurito: 'bg-red-100 text-red-700',
  })[stato] ?? 'bg-gray-100 text-gray-700'

const stockLabel = (stato) =>
  ({
    disponibile: 'Disponibile',
    in_esaurimento: 'In esaurimento',
    esaurito: 'Esaurito',
  })[stato] ?? stato

/** Evidenzia le scadenze entro 90 giorni, la stessa soglia usata dal backend. */
function inScadenza(farmaco) {
  if (!farmaco.expiry_date) return false
  const giorni = (new Date(farmaco.expiry_date).getTime() - Date.now()) / 86400000
  return giorni <= 90
}

onMounted(() => {
  fetchAll()
  caricaRiepilogo()
})
</script>
