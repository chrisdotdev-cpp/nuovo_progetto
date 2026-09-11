<template>
  <div class="flex-1 overflow-auto">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Gestione Utenti</h1>
        <p class="text-start text-gray-500">Gestisci pazienti, medici e amministratori</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2 cursor-pointer"
        @click="apriCreazione"
      >
        <i class="fa-solid fa-plus"></i>
        Nuovo utente
      </button>
    </div>

    <!-- FILTRI -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="relative flex items-center">
          <i class="fa-solid fa-magnifying-glass absolute left-3 text-gray-400"></i>
          <input
            v-model="ricerca"
            type="search"
            placeholder="Cerca per nome o email..."
            aria-label="Cerca utente"
            class="w-full min-h-[44px] pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
          >
        </div>

        <!-- Filtri per ruolo: scrollabili su mobile invece di andare a capo -->
        <div class="flex gap-2 overflow-x-auto pb-1">
          <button
            v-for="tab in tabRuoli"
            :key="tab.valore"
            type="button"
            class="min-h-[44px] px-4 py-2 rounded-lg transition-colors whitespace-nowrap flex-shrink-0 cursor-pointer" 
            :class="ruoloAttivo === tab.valore
              ? 'bg-purple-100 text-purple-700 font-semibold'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
            @click="filtraPerRuolo(tab.valore)"
          >
            {{ tab.etichetta }}
          </button>
        </div>
      </div>
    </div>

    <!-- TABELLA -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

      <!-- Loading -->
      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="n in 6" :key="n" class="h-12 bg-gray-100 rounded-lg animate-pulse"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="isEmpty" class="p-12 text-center">
        <i class="fa-regular fa-address-book text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">Nessun utente trovato</p>
        <p class="text-gray-500 text-sm mt-1">
          {{ hasFilters ? 'Nessun risultato con i filtri attivi.' : 'Crea il primo account dalla scheda in alto.' }}
        </p>
      </div>

      <!-- Dati reali -->
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Utente</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Email</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Ruolo</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Stato</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Registrazione</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Ultimo accesso</th>
              <th class="px-6 py-3 text-left text-gray-600 text-sm font-medium">Azioni</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200">
            <tr v-for="utente in items" :key="utente.id" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <p class="text-gray-900 font-medium">{{ utente.name }}</p>
                <p v-if="utente.phone" class="text-xs text-gray-500">{{ utente.phone }}</p>
              </td>

              <td class="px-6 py-4">
                <p class="text-gray-600 truncate max-w-[220px]">{{ utente.email }}</p>
              </td>

              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium"
                  :class="ruoloClass(utente.role)"
                >
                  <i :class="ruoloIcon(utente.role)"></i>
                  {{ ruoloLabel(utente.role) }}
                </span>
              </td>

              <td class="px-6 py-4">
                <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statoClass(utente.status)">
                  {{ statoLabel(utente.status) }}
                </span>
              </td>

              <td class="px-6 py-4 text-gray-600 text-sm whitespace-nowrap">
                {{ formatDate(utente.created_at) }}
              </td>

              <td class="px-6 py-4 text-gray-600 text-sm whitespace-nowrap">
                {{ utente.last_login_at ? relativeTime(utente.last_login_at) : 'Mai' }}
              </td>

              <td class="px-6 py-4">
                <div class="flex gap-1">
                  <button
                    type="button"
                    class="w-11 h-11 hover:bg-gray-100 rounded-lg transition-colors text-gray-600 cursor-pointer"
                    title="Modifica"
                    @click="apriModifica(utente)"
                  >
                    <i class="fa-regular fa-pen-to-square"></i>
                  </button>

                  <!-- Sospensione: revoca i token lato server, non e' una semplice flag UI -->
                  <button
                    type="button"
                    class="w-11 h-11 hover:bg-amber-50 rounded-lg transition-colors text-amber-600 disabled:opacity-40 cursor-pointer"
                    :title="utente.status === 'attivo' ? 'Sospendi' : 'Riattiva'"
                    :disabled="saving || utente.id === utenteCorrenteId"
                    @click="cambiaStato(utente)"
                  >
                    <i :class="utente.status === 'attivo' ? 'fa-solid fa-ban' : 'fa-solid fa-check'"></i>
                  </button>

                  <button
                    type="button"
                    class="w-11 h-11 hover:bg-red-50 rounded-lg transition-colors text-red-600 disabled:opacity-40 cursor-pointer"
                    title="Elimina"
                    :disabled="saving || utente.id === utenteCorrenteId"
                    @click="eliminaUtente(utente)"
                  >
                    <i class="fa-regular fa-trash-can"></i>
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
        <span class="text-sm text-gray-500">{{ meta.total }} utenti</span>
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

    <!-- ===================== MODALE UTENTE ===================== -->
    <div
      v-if="modaleAperta"
      class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="chiudiModale"
    >
      <form
        class="bg-white w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl p-6 max-h-[92vh] overflow-y-auto"
        novalidate
        @submit.prevent="salva"
      >
        <h3 class="text-lg font-bold mb-6">
          {{ inModifica ? 'Modifica utente' : 'Nuovo utente' }}
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
          <BaseInput
            v-model="form.name"
            label="Nome e cognome"
            required
            :error="fieldError('name')"
          />

          <BaseInput
            v-model="form.email"
            label="Email"
            type="email"
            required
            autocomplete="email"
            :error="fieldError('email')"
          />

          <!-- Il ruolo non si cambia in modifica: cambierebbe anche il profilo collegato -->
          <div class="mb-5">
            <label for="form-ruolo" class="block text-gray-700 mb-2 font-medium">
              Ruolo <span class="text-red-500">*</span>
            </label>
            <select
              id="form-ruolo"
              v-model="form.role"
              :disabled="inModifica"
              class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 disabled:bg-gray-100 cursor-pointer"
            >
              <option value="paziente">Paziente</option>
              <option value="medico">Medico</option>
              <option value="admin">Amministratore</option>
            </select>
            <p v-if="fieldError('role')" class="text-red-600 text-sm mt-1.5">{{ fieldError('role') }}</p>
          </div>

          <div class="mb-5">
            <label for="form-stato" class="block text-gray-700 mb-2 font-medium">Stato</label>
            <select
              id="form-stato"
              v-model="form.status"
              class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 cursor-pointer" 
            >
              <option value="attivo">Attivo</option>
              <option value="in_attesa">In attesa</option>
              <option value="sospeso">Sospeso</option>
            </select>
          </div>

          <BaseInput
            v-model="form.phone"
            label="Telefono"
            :error="fieldError('phone')"
          />

          <!-- Password solo in creazione: il cambio password ha un endpoint dedicato -->
          <template v-if="!inModifica">
            <BaseInput
              v-model="form.password"
              label="Password"
              type="password"
              required
              autocomplete="new-password"
              hint="Almeno 8 caratteri, con lettere e numeri"
              :error="fieldError('password')"
            />
            <BaseInput
              v-model="form.password_confirmation"
              label="Conferma password"
              type="password"
              required
              autocomplete="new-password"
            />
          </template>

          <!-- Campi condizionali: obbligatori solo per il ruolo medico -->
          <template v-if="!inModifica && form.role === 'medico'">
            <BaseInput
              v-model="form.specialization"
              label="Specializzazione"
              required
              :error="fieldError('specialization')"
            />
            <BaseInput
              v-model="form.license_number"
              label="Numero d'albo"
              :error="fieldError('license_number')"
            />
          </template>

          <template v-if="!inModifica && form.role === 'paziente'">
            <BaseInput
              v-model="form.codice_fiscale"
              label="Codice fiscale"
              hint="16 caratteri"
              :error="fieldError('codice_fiscale')"
            />
            <BaseInput
              v-model="form.birth_date"
              label="Data di nascita"
              type="date"
              :error="fieldError('birth_date')"
            />
          </template>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end mt-4">
          <BaseButton type="button" variant="secondary" :block="false" @click="chiudiModale">
            Annulla
          </BaseButton>
          <BaseButton type="submit" :loading="saving" :block="false">
            {{ inModifica ? 'Salva modifiche' : 'Crea utente' }}
          </BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { api } from '@/services/api'
import { useResource } from '@/composables/useResource'
import { useFormatters } from '@/composables/useFormatters'
import { useAuthStore } from '@/store/auth'
import { useToast } from '@/composables/useToast'

import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const {
  items, meta, loading, saving, isEmpty, hasFilters,
  fetchAll, applyFilters, nextPage, prevPage,
  create, update, remove, replace,
  fieldError, resetErrors, run,
} = useResource(api.users, {
  label: 'Utente',
  labelPlural: 'Utenti',
  perPage: 15,
  defaultFilters: { q: '', role: '', status: '' },
})

const { formatDate, relativeTime } = useFormatters()
const toast = useToast()

/** Un amministratore non deve potersi sospendere o eliminare da solo. */
const utenteCorrenteId = computed(() => useAuthStore().user?.id)

/* ---------------------- Filtri ---------------------- */
const ricerca = ref('')
const ruoloAttivo = ref('')

const tabRuoli = [
  { valore: '', etichetta: 'Tutti' },
  { valore: 'paziente', etichetta: 'Pazienti' },
  { valore: 'medico', etichetta: 'Medici' },
  { valore: 'admin', etichetta: 'Admin' },
]

let debounce = null
watch(ricerca, (valore) => {
  clearTimeout(debounce)
  debounce = setTimeout(() => applyFilters({ q: valore, role: ruoloAttivo.value }), 400)
})

function filtraPerRuolo(ruolo) {
  ruoloAttivo.value = ruolo
  applyFilters({ q: ricerca.value, role: ruolo })
}

/* ---------------------- Modale ---------------------- */
const modaleAperta = ref(false)
const inModifica = ref(false)
const utenteInModifica = ref(null)

const formVuoto = () => ({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'paziente',
  status: 'attivo',
  phone: '',
  specialization: '',
  license_number: '',
  codice_fiscale: '',
  birth_date: '',
})

const form = reactive(formVuoto())

function resetForm() {
  Object.assign(form, formVuoto())
  resetErrors()
}

function apriCreazione() {
  resetForm()
  inModifica.value = false
  utenteInModifica.value = null
  modaleAperta.value = true
}

function apriModifica(utente) {
  resetForm()
  Object.assign(form, {
    name: utente.name ?? '',
    email: utente.email ?? '',
    role: utente.role ?? 'paziente',
    status: utente.status ?? 'attivo',
    phone: utente.phone ?? '',
  })
  inModifica.value = true
  utenteInModifica.value = utente
  modaleAperta.value = true
}

function chiudiModale() {
  modaleAperta.value = false
  resetForm()
}

/* ---------------------- Salvataggio ---------------------- */

/** Validazione minima lato client: il resto lo fa la FormRequest. */
function formValido() {
  if (!form.name.trim()) {
    toast.error('Il nome è obbligatorio.')
    return false
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    toast.error('Inserisci un indirizzo email valido.')
    return false
  }

  if (!inModifica.value && form.password !== form.password_confirmation) {
    toast.error('Le due password non coincidono.')
    return false
  }

  return true
}

async function salva() {
  if (!formValido()) return

  if (inModifica.value) {
    // In aggiornamento si inviano solo i campi modificabili dall'admin
    const esito = await update(utenteInModifica.value.id, {
      name: form.name.trim(),
      email: form.email.trim(),
      phone: form.phone || null,
      role: form.role,
      status: form.status,
    })

    if (esito) chiudiModale()
    return
  }

  // In creazione si scartano i campi profilo non pertinenti al ruolo scelto
  const payload = {
    name: form.name.trim(),
    email: form.email.trim(),
    password: form.password,
    password_confirmation: form.password_confirmation,
    role: form.role,
    status: form.status,
    phone: form.phone || null,
  }

  if (form.role === 'medico') {
    payload.specialization = form.specialization
    payload.license_number = form.license_number || null
  }

  if (form.role === 'paziente') {
    if (form.codice_fiscale) payload.codice_fiscale = form.codice_fiscale.toUpperCase()
    if (form.birth_date) payload.birth_date = form.birth_date
  }

  const esito = await create(payload)
  if (esito) chiudiModale()
}

/** Sospensione/riattivazione: lato server la sospensione revoca anche i token. */
async function cambiaStato(utente) {
  const nuovoStato = utente.status === 'attivo' ? 'sospeso' : 'attivo'

  await run(() => api.users.update(utente.id, { status: nuovoStato }), {
    successMessage: nuovoStato === 'sospeso' ? 'Utente sospeso.' : 'Utente riattivato.',
    onSuccess: (payload) => {
      const aggiornato = payload?.data ?? payload
      if (aggiornato) replace(aggiornato)
    },
  })
}

async function eliminaUtente(utente) {
  if (!window.confirm(`Eliminare definitivamente l'account di ${utente.name}?`)) return
  await remove(utente.id)
}

/* ---------------------- Presentazione ---------------------- */
const ruoloClass = (ruolo) =>
  ({
    admin: 'bg-purple-100 text-purple-700',
    medico: 'bg-blue-100 text-blue-700',
    paziente: 'bg-green-100 text-green-700',
  })[ruolo] ?? 'bg-gray-100 text-gray-700'

const ruoloIcon = (ruolo) =>
  ({
    admin: 'fa-solid fa-user-shield',
    medico: 'fa-solid fa-stethoscope',
    paziente: 'fa-solid fa-user',
  })[ruolo] ?? 'fa-solid fa-user'

const ruoloLabel = (ruolo) =>
  ({ admin: 'Admin', medico: 'Medico', paziente: 'Paziente' })[ruolo] ?? ruolo

const statoClass = (stato) =>
  ({
    attivo: 'bg-green-100 text-green-700',
    sospeso: 'bg-red-100 text-red-700',
    in_attesa: 'bg-amber-100 text-amber-700',
  })[stato] ?? 'bg-gray-100 text-gray-700'

const statoLabel = (stato) =>
  ({ attivo: 'Attivo', sospeso: 'Sospeso', in_attesa: 'In attesa' })[stato] ?? stato

onMounted(fetchAll)
</script>
