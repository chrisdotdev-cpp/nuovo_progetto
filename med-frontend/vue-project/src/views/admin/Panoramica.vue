<template>
  <div class="flex-1 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
      <h1 class="text-2xl font-bold text-start">Dashboard Amministrativa</h1>
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
    <!-- 5 card: 3 per riga da lg, 5 in linea da xl -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 md:gap-6 mb-8">
      <div
        v-for="kpi in kpiCards"
        :key="kpi.etichetta"
        class="bg-white p-6 rounded-xl shadow-sm border border-gray-200"
      >
        <div class="flex items-center justify-between mb-4">
          <span class="text-gray-600 text-sm">{{ kpi.etichetta }}</span>
          <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="kpi.iconBg">
            <i :class="kpi.icon"></i>
          </div>
        </div>

        <p v-if="loading" class="h-8 w-24 bg-gray-200 rounded animate-pulse"></p>
        <p v-else class="text-2xl font-bold text-gray-900 mb-2">{{ kpi.valore }}</p>

        <p v-if="!loading && kpi.dettaglio" class="text-gray-500 text-sm">{{ kpi.dettaglio }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

      <!-- ============ Appuntamenti di oggi ============ -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-gray-900 font-semibold">Appuntamenti di oggi</h2>
          <RouterLink to="/admin/prenotazioni" class="text-sm text-blue-600 hover:underline">
            Vedi tutti
          </RouterLink>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
          <div v-for="n in 4" :key="n" class="h-14 bg-gray-100 rounded-lg animate-pulse"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="!appuntamentiOggi.length" class="py-10 text-center">
          <i class="fa-regular fa-calendar-check text-4xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500 text-sm">Nessun appuntamento in programma oggi.</p>
        </div>

        <!-- Lista reale -->
        <ul v-else class="space-y-3">
          <li
            v-for="appuntamento in appuntamentiOggi"
            :key="appuntamento.id"
            class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg"
          >
            <span class="w-14 flex-shrink-0 text-sm font-semibold text-blue-600">
              {{ appuntamento.time }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-gray-900 text-sm truncate">
                {{ appuntamento.patient?.name || 'Paziente' }}
                <span class="text-gray-400">&rarr;</span>
                {{ appuntamento.doctor?.name || 'Medico' }}
              </p>
              <p class="text-gray-500 text-xs mt-1 capitalize">
                {{ appuntamento.type }}
                <span v-if="appuntamento.doctor?.specialization"> · {{ appuntamento.doctor.specialization }}</span>
              </p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs flex-shrink-0" :class="statusClass(appuntamento.status)">
              {{ statusLabel(appuntamento.status) }}
            </span>
          </li>
        </ul>
      </div>

      <!-- ============ Ultimi utenti registrati ============ -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-gray-900 font-semibold">Ultimi utenti registrati</h2>
          <RouterLink to="/admin/utenti" class="text-sm text-blue-600 hover:underline">
            Gestisci
          </RouterLink>
        </div>

        <div v-if="loading" class="space-y-3">
          <div v-for="n in 4" :key="n" class="h-14 bg-gray-100 rounded-lg animate-pulse"></div>
        </div>

        <div v-else-if="!ultimiUtenti.length" class="py-10 text-center">
          <i class="fa-regular fa-address-book text-4xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500 text-sm">Nessun utente registrato di recente.</p>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="utente in ultimiUtenti"
            :key="utente.id"
            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg"
          >
            <span
              class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold"
              :class="ruoloClass(utente.role)"
            >
              {{ iniziali(utente.name) }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-gray-900 text-sm truncate">{{ utente.name }}</p>
              <p class="text-gray-500 text-xs truncate">{{ utente.email }}</p>
            </div>
            <span class="text-xs text-gray-400 flex-shrink-0">{{ relativeTime(utente.created_at) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- ===================== Azioni rapide ===================== -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-6 text-white">
      <h2 class="mb-4 font-semibold">Azioni Rapide</h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <RouterLink
          v-for="azione in azioniRapide"
          :key="azione.to"
          :to="azione.to"
          class="bg-white/20 hover:bg-white/30 backdrop-blur-sm p-4 rounded-lg transition-colors text-left block min-h-[44px]"
        >
          <i :class="[azione.icon, 'text-xl mb-2 block']"></i>
          <p>{{ azione.label }}</p>
          <p v-if="azione.badge" class="text-white/80 text-xs mt-1">{{ azione.badge }}</p>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useDashboardStore } from '@/store/dashboard'
import { useAppuntamenti } from '@/composables/useAppuntamenti'
import { useFormatters } from '@/composables/useFormatters'

/*
  Una sola chiamata (/dashboard): il backend restituisce gia' l'aggregato admin
  (stats, appuntamenti_oggi, ultimi_utenti). Evita 4-5 round-trip all'apertura.
*/
const dashboard = useDashboardStore()
const { statusClass, statusLabel } = useAppuntamenti()
const { formatCurrency, relativeTime } = useFormatters()

const loading = computed(() => dashboard.loading)
const stats = computed(() => dashboard.stats ?? {})
const appuntamentiOggi = computed(() => dashboard.payload?.appuntamenti_oggi ?? [])
const ultimiUtenti = computed(() => dashboard.payload?.ultimi_utenti ?? [])

/* Nessun dato inventato: ogni card mostra un valore realmente calcolato dal backend. */
const kpiCards = computed(() => [
  {
    etichetta: 'Pazienti totali',
    valore: stats.value.pazienti ?? 0,
    dettaglio: `${stats.value.utenti_totali ?? 0} utenti in piattaforma`,
    icon: 'fa-solid fa-users',
    iconBg: 'bg-blue-100 text-blue-600',
  },
  {
    etichetta: 'Medici',
    valore: stats.value.medici ?? 0,
    dettaglio: 'Profili professionali attivi',
    icon: 'fa-solid fa-user-doctor',
    iconBg: 'bg-green-100 text-green-600',
  },
  {
    etichetta: 'Visite oggi',
    valore: stats.value.appuntamenti_oggi ?? 0,
    dettaglio: `${stats.value.documenti_da_firmare ?? 0} documenti da firmare`,
    icon: 'fa-regular fa-calendar',
    iconBg: 'bg-purple-100 text-purple-600',
  },
  {
    etichetta: 'Fatturato del mese',
    valore: formatCurrency(stats.value.fatturato_mese ?? 0),
    dettaglio: `${formatCurrency(stats.value.incassato_mese ?? 0)} gia' incassati`,
    icon: 'fa-solid fa-euro-sign',
    iconBg: 'bg-orange-100 text-orange-600',
  },
  {
    // Card dedicata: e' il numero che si muove a ogni incasso registrato
    etichetta: 'Da incassare',
    valore: formatCurrency(stats.value.da_incassare ?? 0),
    dettaglio: `${stats.value.fatture_da_saldare ?? 0} fatture aperte`,
    icon: 'fa-solid fa-hand-holding-dollar',
    iconBg: 'bg-amber-100 text-amber-600',
  },
])

/* Le azioni rapide portano badge reali dove il dato esiste gia' nelle stats. */
const azioniRapide = computed(() => [
  { to: '/admin/utenti', label: 'Gestisci utenti', icon: 'fa-solid fa-users', badge: null },
  { to: '/admin/prenotazioni', label: 'Calendario', icon: 'fa-regular fa-calendar', badge: null },
  { to: '/admin/finanziario', label: 'Report finanziari', icon: 'fa-solid fa-hand-holding-dollar', badge: null },
  {
    to: '/admin/farmacia',
    label: 'Gestisci scorte',
    icon: 'fa-solid fa-box',
    badge: stats.value.farmaci_in_esaurimento
      ? `${stats.value.farmaci_in_esaurimento} in esaurimento`
      : null,
  },
])

const ruoloClass = (ruolo) =>
  ({
    admin: 'bg-purple-100 text-purple-700',
    medico: 'bg-blue-100 text-blue-700',
    paziente: 'bg-green-100 text-green-700',
  })[ruolo] ?? 'bg-gray-100 text-gray-700'

/** Iniziali per l'avatar testuale: evita immagini mancanti nella lista. */
const iniziali = (nome = '') =>
  nome.split(' ').filter(Boolean).slice(0, 2).map((parte) => parte[0]?.toUpperCase()).join('')

onMounted(() => dashboard.fetch())
</script>
