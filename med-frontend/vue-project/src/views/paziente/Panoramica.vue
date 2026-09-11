<template>
  <div class="flex-1 overflow-auto">
    <!-- INTESTAZIONE -->
    <h3 class="text-xl sm:text-2xl font-bold text-start mb-1 sm:mb-2">Panoramica Paziente</h3>
    <p class="text-start text-gray-500 mb-6">Benvenuto {{ patientName }}</p>

    <!-- ERRORE DI CARICAMENTO: si mostra il problema e si offre il riprova -->
    <div
      v-if="loadError"
      class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3"
    >
      <div class="flex items-start gap-3 flex-1">
        <i class="fa-solid fa-triangle-exclamation text-red-500 mt-0.5"></i>
        <p class="text-sm text-red-700">{{ loadError }}</p>
      </div>
      <button
        type="button"
        class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 transition-colors min-h-[40px]"
        @click="carica(true)"
      >
        Riprova
      </button>
    </div>

    <!-- AVVISO SALDO: compare solo se ci sono fatture aperte -->
    <RouterLink
      v-if="!loading && daPagare > 0"
      to="/paziente/pagamenti"
      class="mb-6 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100 transition-colors"
    >
      <i class="fa-solid fa-receipt text-amber-600"></i>
      <p class="text-sm text-amber-800 flex-1">
        Hai <strong>{{ formatCurrency(daPagare) }}</strong> da saldare.
      </p>
      <i class="fa-solid fa-chevron-right text-amber-600"></i>
    </RouterLink>

    <!-- STATISTICHE: 1 colonna su mobile, 2 su tablet, 4 su desktop -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
      <!-- Scheletro durante il caricamento: evita lo sfarfallio dei valori a zero -->
      <template v-if="loading">
        <div
          v-for="n in 4"
          :key="`skeleton-${n}`"
          class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-pulse"
        >
          <div class="h-4 bg-gray-200 rounded w-2/3 mb-4"></div>
          <div class="h-6 bg-gray-200 rounded w-1/2 mb-2"></div>
          <div class="h-3 bg-gray-100 rounded w-1/3"></div>
        </div>
      </template>

      <template v-else>
        <!-- Prossima visita -->
        <RouterLink
          to="/paziente/appuntamenti"
          class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Prossima Visita</span>
            <i class="fa-regular fa-calendar" style="color: #1976d2"></i>
          </div>
          <p class="text-gray-900 font-semibold">{{ nextAppointmentDate }}</p>
          <p class="text-gray-600 text-sm">{{ nextAppointmentTime }}</p>
        </RouterLink>

        <!-- Referti -->
        <RouterLink
          to="/paziente/cartella-clinica"
          class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:border-green-300 hover:shadow-md transition-all"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Referti</span>
            <i class="fa-regular fa-file-lines" style="color: #388e3c"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ stats.referti ?? 0 }}</p>
          <p class="text-gray-600 text-sm">Disponibili</p>
        </RouterLink>

        <!-- Prescrizioni -->
        <RouterLink
          to="/paziente/cartella-clinica"
          class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:border-purple-300 hover:shadow-md transition-all"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Prescrizioni</span>
            <i class="fa-regular fa-file-lines" style="color: #7b1fa2"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ stats.prescrizioni ?? 0 }}</p>
          <p class="text-gray-600 text-sm">Attive</p>
        </RouterLink>

        <!-- Notifiche non lette -->
        <RouterLink
          to="/paziente/notifiche"
          class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:border-orange-300 hover:shadow-md transition-all"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Messaggi</span>
            <i class="fa-regular fa-comment" style="color: #e64a19"></i>
          </div>
          <p class="text-gray-900 text-xl font-semibold">{{ stats.messaggi_non_letti ?? 0 }}</p>
          <p class="text-gray-600 text-sm">Non letti</p>
        </RouterLink>
      </template>
    </div>

    <!-- RIEPILOGO -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Prossimi appuntamenti -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-gray-900 font-semibold">Prossimi Appuntamenti</h2>
          <RouterLink to="/paziente/appuntamenti" class="text-sm text-blue-600 hover:underline">
            Vedi tutti
          </RouterLink>
        </div>

        <div class="space-y-4">
          <!-- Caricamento -->
          <div v-if="loading" class="space-y-3">
            <div v-for="n in 2" :key="`app-skeleton-${n}`" class="h-24 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <!-- Nessun dato -->
          <div v-else-if="appointments.length === 0" class="text-center py-8">
            <i class="fa-regular fa-calendar-xmark text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 text-sm mb-4">Nessun appuntamento in programma.</p>
            <RouterLink
              to="/paziente/appuntamenti"
              class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors min-h-[40px]"
            >
              <i class="fa-solid fa-plus"></i> Prenota una visita
            </RouterLink>
          </div>

          <!-- Lista -->
          <div
            v-for="app in appointments"
            :key="app.id"
            class="flex items-start gap-3 sm:gap-4 p-3 sm:p-4 bg-gray-50 rounded-lg"
          >
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <i
                :class="app.type === 'telemedicina' ? 'fa-solid fa-video' : 'fa-regular fa-calendar'"
                class="text-xl"
                style="color: #1976d2"
              ></i>
            </div>

            <div class="flex-1 min-w-0">
              <h3 class="text-gray-900 font-medium truncate">{{ app.doctor?.name || 'Medico' }}</h3>
              <p class="text-gray-600 text-sm truncate">{{ app.doctor?.specialization }}</p>

              <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                <span class="text-gray-600 text-sm flex items-center gap-1">
                  <i class="fa-regular fa-calendar"></i>
                  {{ app.date }}
                </span>
                <span class="text-gray-600 text-sm flex items-center gap-1">
                  <i class="fa-regular fa-clock"></i>
                  {{ app.time }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="inline-block text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded capitalize">
                  {{ app.type }}
                </span>
                <span class="inline-block text-xs px-2 py-1 rounded" :class="statusClass(app.status)">
                  {{ statusLabel(app.status) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Documenti recenti -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-gray-900 font-semibold">Documenti Recenti</h2>
          <RouterLink to="/paziente/cartella-clinica" class="text-sm text-blue-600 hover:underline">
            Vedi tutti
          </RouterLink>
        </div>

        <div class="space-y-3">
          <div v-if="loading" class="space-y-3">
            <div v-for="n in 3" :key="`doc-skeleton-${n}`" class="h-14 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <div v-else-if="documents.length === 0" class="text-center py-8">
            <i class="fa-regular fa-folder-open text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 text-sm">Nessun documento disponibile.</p>
          </div>

          <div
            v-for="doc in documents"
            :key="doc.id"
            class="flex items-center justify-between gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <div class="flex items-center gap-3 min-w-0">
              <i class="fa-regular fa-file-lines flex-shrink-0" style="color: rgb(30, 48, 80)"></i>
              <div class="min-w-0">
                <p class="text-gray-900 truncate">{{ doc.title }}</p>
                <p class="text-gray-600 text-sm">{{ formatDate(doc.created_at) }} · {{ doc.size_label }}</p>
              </div>
            </div>

            <button
              type="button"
              class="p-2 hover:bg-white rounded-lg transition-colors flex-shrink-0 min-w-[40px] min-h-[40px]"
              :disabled="isDownloading(doc.id)"
              :aria-label="`Scarica ${doc.title}`"
              @click="scaricaDocumento(doc)"
            >
              <i
                :class="isDownloading(doc.id) ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-download'"
                style="color: rgb(30, 48, 80)"
              ></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- AZIONI RAPIDE -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-4 sm:p-6 text-white">
      <h2 class="mb-4 font-semibold">Azioni Rapide</h2>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <button
          type="button"
          class="bg-white/20 hover:bg-white/30 backdrop-blur-sm p-4 rounded-lg transition-colors text-left min-h-[44px] cursor-pointer"
          @click="navigateTo('/paziente/appuntamenti')"
        >
          <i class="fa-regular fa-calendar" style="color: white; font-size: 18px"></i>
          <p>Prenota Visita</p>
        </button>

        <button
          type="button"
          class="bg-white/20 hover:bg-white/30 backdrop-blur-sm p-4 rounded-lg transition-colors text-left min-h-[44px] cursor-pointer"
          @click="navigateTo('/paziente/telemedicina')"
        >
          <i class="fa-solid fa-video" style="color: white; font-size: 18px"></i>
          <p>Consulto Online</p>
        </button>

        <button
          type="button"
          class="bg-white/20 hover:bg-white/30 backdrop-blur-sm p-4 rounded-lg transition-colors text-left min-h-[44px] cursor-pointer"
          @click="navigateTo('/paziente/cartella-clinica')"
        >
          <i class="fa-regular fa-file-lines" style="color: white; font-size: 18px"></i>
          <p>Scarica Referti</p>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { api } from '@/services/api'
import { useAuthStore } from '@/store/auth'
import { useDashboardStore } from '@/store/dashboard'
import { useFormatters } from '@/composables/useFormatters'
import { useFileDownload } from '@/composables/useFileDownload'
import { useAppuntamenti } from '@/composables/useAppuntamenti'

const router = useRouter()
const auth = useAuthStore()
const dashboard = useDashboardStore()

const { formatDate, formatTime, formatCurrency } = useFormatters()
const { download, isDownloading } = useFileDownload()

// Del composable appuntamenti servono qui solo le etichette di stato
const { statusClass, statusLabel } = useAppuntamenti()

const loadError = ref(null)

/* -------------------------------------------------------------------------
 | Dati derivati dallo store dashboard (endpoint unico /dashboard)
 * ---------------------------------------------------------------------- */
const loading = computed(() => dashboard.loading)
const stats = computed(() => dashboard.stats ?? {})
const appointments = computed(() => dashboard.payload?.appuntamenti ?? [])
const documents = computed(() => dashboard.payload?.documenti ?? [])
const daPagare = computed(() => Number(stats.value.da_pagare ?? 0))

// Solo il nome di battesimo, come nel mockup originale
const patientName = computed(() => auth.user?.name?.split(' ')[0] ?? '')

const nextAppointmentDate = computed(() =>
  stats.value.prossima_visita ? formatDate(stats.value.prossima_visita) : '--/--/----',
)

const nextAppointmentTime = computed(() =>
  stats.value.prossima_visita ? formatTime(stats.value.prossima_visita) : 'Nessuna visita',
)

/* -------------------------------------------------------------------------
 | Azioni
 * ---------------------------------------------------------------------- */
const navigateTo = (path) => router.push(path)

/**
 * I documenti clinici non sono file pubblici: il download passa dall'API
 * autenticata e restituisce un blob, non un link diretto.
 */
const scaricaDocumento = (doc) =>
  download(() => api.documents.download(doc.id), doc.original_name ?? `${doc.title}.pdf`, doc.id)

/** force = true bypassa la cache di 60 secondi dello store. */
async function carica(force = false) {
  loadError.value = null

  try {
    await dashboard.fetch({ force })
  } catch (error) {
    loadError.value = error?.message ?? 'Impossibile caricare i dati della dashboard.'
  }
}

onMounted(() => carica())
</script>
