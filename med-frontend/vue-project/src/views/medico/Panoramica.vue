<template>
  <div class="flex-1 overflow-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-start mb-2">Panoramica Medico</h1>
        <p class="text-start text-gray-500">Benvenuto {{ nomeMedico }}</p>
      </div>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2 cursor-pointer"
        :disabled="loading"
        @click="dashboard.fetch({ force: true })"
      >
        <i class="fa-solid fa-rotate-right" :class="loading ? 'fa-spin' : ''"></i>
        Aggiorna
      </button>
    </div>

    <!-- ===================== KPI ===================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div
        v-for="kpi in kpiCards"
        :key="kpi.etichetta"
        class="bg-white p-6 rounded-xl shadow-sm border border-gray-200"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-gray-600 text-sm">{{ kpi.etichetta }}</span>
          <i :class="[kpi.icon, kpi.colore]"></i>
        </div>
        <p v-if="loading" class="h-8 w-16 bg-gray-200 rounded animate-pulse"></p>
        <p v-else class="text-2xl font-bold text-gray-900">{{ kpi.valore }}</p>
        <p class="text-gray-600 text-sm mt-1">{{ kpi.dettaglio }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

      <!-- ============ Agenda di oggi ============ -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-gray-900 font-semibold">Appuntamenti di oggi</h2>
          <RouterLink to="/medico/agenda" class="text-sm text-blue-600 hover:underline">
            Apri agenda
          </RouterLink>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
          <div v-for="n in 3" :key="n" class="h-20 bg-gray-100 rounded-lg animate-pulse"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="!agendaOggi.length" class="py-10 text-center">
          <i class="fa-regular fa-calendar-check text-4xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500 text-sm">Nessun appuntamento in programma oggi.</p>
        </div>

        <!-- Lista reale -->
        <ul v-else class="space-y-3">
          <li
            v-for="appuntamento in agendaOggi"
            :key="appuntamento.id"
            class="p-4 rounded-lg border-2"
            :class="bordoStato(appuntamento.status)"
          >
            <div class="flex items-center justify-between gap-2 mb-2">
              <h3 class="text-gray-900 font-medium truncate">
                {{ appuntamento.patient?.name || 'Paziente' }}
              </h3>
              <span class="text-gray-600 text-sm flex-shrink-0">{{ appuntamento.time }}</span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-gray-600 text-sm truncate">
                {{ appuntamento.reason || appuntamento.type }}
              </p>
              <span class="px-2.5 py-0.5 rounded-full text-xs" :class="statusClass(appuntamento.status)">
                {{ statusLabel(appuntamento.status) }}
              </span>
            </div>

            <!-- Teleconsulto: si entra solo nella finestra decisa dal backend -->
            <RouterLink
              v-if="appuntamento.telemedicine?.is_joinable"
              to="/medico/telemedicina"
              class="inline-flex items-center gap-2 mt-3 min-h-[44px] px-3 py-2 rounded-lg bg-blue-600 text-white text-sm"
            >
              <i class="fa-solid fa-video"></i> Entra in stanza
            </RouterLink>
          </li>
        </ul>
      </div>

      <!-- ============ Richieste in triage ============ -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-gray-900 font-semibold">Richieste da gestire</h2>
          <RouterLink to="/medico/richieste" class="text-sm text-blue-600 hover:underline">
            Vedi tutte
          </RouterLink>
        </div>

        <div v-if="loading" class="space-y-3">
          <div v-for="n in 3" :key="n" class="h-16 bg-gray-100 rounded-lg animate-pulse"></div>
        </div>

        <div v-else-if="!richieste.length" class="py-10 text-center">
          <i class="fa-regular fa-hand text-4xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500 text-sm">Nessuna richiesta in coda.</p>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="richiesta in richieste"
            :key="richiesta.id"
            class="p-3 bg-gray-50 rounded-lg border-l-4"
            :class="bordoPriorita(richiesta.priority)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-gray-900 text-sm font-medium truncate">{{ richiesta.subject }}</p>
                <p class="text-gray-500 text-xs mt-0.5 truncate">
                  {{ richiesta.patient?.name || 'Paziente' }} · {{ relativeTime(richiesta.created_at) }}
                </p>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-xs flex-shrink-0"
                :class="classePriorita(richiesta.priority)"
              >
                {{ richiesta.priority }}
              </span>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- ===================== Azioni rapide ===================== -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-6 text-white">
      <h2 class="mb-4 font-semibold">Azioni rapide</h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <RouterLink
          v-for="azione in azioniRapide"
          :key="azione.to"
          :to="azione.to"
          class="bg-white/20 hover:bg-white/30 backdrop-blur-sm p-4 rounded-lg transition-colors text-left block min-h-[44px]"
        >
          <i :class="[azione.icon, 'text-xl mb-2 block']"></i>
          <p>{{ azione.label }}</p>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useDashboardStore } from '@/store/dashboard'
import { useAuthStore } from '@/store/auth'
import { useAppuntamenti } from '@/composables/useAppuntamenti'
import { useFormatters } from '@/composables/useFormatters'

/*
  Tutti i dati vengono da /dashboard: il backend restituisce l'aggregato del
  medico autenticato (stats, agenda_oggi, richieste in triage).
  Prima la vista lavorava su array mock: nessuna chiamata, nessun dato reale.
*/
const dashboard = useDashboardStore()
const auth = useAuthStore()
const { statusClass, statusLabel } = useAppuntamenti()
const { relativeTime } = useFormatters()

const loading = computed(() => dashboard.loading)
const stats = computed(() => dashboard.stats ?? {})
const agendaOggi = computed(() => dashboard.payload?.agenda_oggi ?? [])
const richieste = computed(() => dashboard.payload?.richieste ?? [])

const nomeMedico = computed(() => auth.user?.name ?? '')

const kpiCards = computed(() => [
  {
    etichetta: 'Visite oggi',
    valore: stats.value.appuntamenti_oggi ?? 0,
    dettaglio: 'In agenda',
    icon: 'fa-regular fa-calendar',
    colore: 'text-blue-600',
  },
  {
    etichetta: 'Pazienti seguiti',
    valore: stats.value.pazienti_seguiti ?? 0,
    dettaglio: 'In cura',
    icon: 'fa-regular fa-id-badge',
    colore: 'text-green-600',
  },
  {
    etichetta: 'Prescrizioni',
    valore: stats.value.prescrizioni_mese ?? 0,
    dettaglio: 'Questo mese',
    icon: 'fa-solid fa-capsules',
    colore: 'text-purple-600',
  },
  {
    etichetta: 'Richieste aperte',
    valore: stats.value.richieste_aperte ?? 0,
    dettaglio: 'Da gestire',
    icon: 'fa-regular fa-hand',
    colore: 'text-orange-600',
  },
])

const azioniRapide = [
  { to: '/medico/agenda', label: 'Agenda', icon: 'fa-regular fa-calendar' },
  { to: '/medico/pazienti', label: 'Cartelle cliniche', icon: 'fa-regular fa-id-badge' },
  { to: '/medico/prescrizioni', label: 'Nuova prescrizione', icon: 'fa-solid fa-capsules' },
  { to: '/medico/telemedicina', label: 'Teleconsulti', icon: 'fa-solid fa-video' },
]

/* Bordo colorato per lettura a colpo d'occhio dello stato dell'appuntamento. */
const bordoStato = (stato) =>
  ({
    confermato: 'border-green-200 bg-green-50',
    in_attesa: 'border-amber-200 bg-amber-50',
    completato: 'border-blue-200 bg-blue-50',
    annullato: 'border-red-200 bg-red-50',
  })[stato] ?? 'border-gray-200 bg-gray-50'

const bordoPriorita = (priorita) =>
  ({
    alta: 'border-l-red-500',
    media: 'border-l-amber-400',
    bassa: 'border-l-gray-300',
  })[priorita] ?? 'border-l-gray-300'

const classePriorita = (priorita) =>
  ({
    alta: 'bg-red-100 text-red-700',
    media: 'bg-amber-100 text-amber-700',
    bassa: 'bg-gray-100 text-gray-600',
  })[priorita] ?? 'bg-gray-100 text-gray-600'

onMounted(() => dashboard.fetch())
</script>
