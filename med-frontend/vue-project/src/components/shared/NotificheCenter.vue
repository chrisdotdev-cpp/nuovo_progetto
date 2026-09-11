<template>
  <div class="space-y-6">
    <!-- INTESTAZIONE -->
    <div class="flex flex-col justify-between">
      <h2 class="text-xl sm:text-2xl font-bold mb-1 sm:mb-2">Notifiche</h2>
      <h4 class="text-gray-500">
        <template v-if="loading">Caricamento in corso...</template>
        <template v-else-if="unreadCount === 0">Nessuna notifica non letta</template>
        <template v-else>
          Hai {{ unreadCount }} {{ unreadCount === 1 ? 'notifica non letta' : 'notifiche non lette' }}
        </template>
      </h4>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- COLONNA SINISTRA: LISTA -->
      <div class="lg:col-span-2 space-y-3">
        <!-- Caricamento -->
        <div v-if="loading" class="space-y-3">
          <div
            v-for="n in 4"
            :key="`skeleton-${n}`"
            class="h-24 bg-white rounded-xl border border-gray-200 animate-pulse"
          ></div>
        </div>

        <!-- Nessuna notifica -->
        <div
          v-else-if="notifiche.length === 0"
          class="bg-white rounded-xl border border-gray-200 py-12 text-center"
        >
          <i class="fa-regular fa-bell-slash text-3xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500">
            {{ categoriaAttiva ? 'Nessuna notifica in questa categoria.' : 'Non hai ancora notifiche.' }}
          </p>
        </div>

        <!-- Lista -->
        <article
          v-for="notif in notifiche"
          :key="notif.id"
          :class="[
            'bg-white rounded-xl border p-4 transition-all',
            notif.is_read ? 'border-gray-200' : 'border-blue-200 bg-blue-50/30',
          ]"
        >
          <div class="flex items-start gap-3 sm:gap-4">
            <!-- Icona per categoria -->
            <div
              class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0"
              :class="categoryStyle(notif.category).bg"
            >
              <i :class="[categoryStyle(notif.category).icon, categoryStyle(notif.category).color]"></i>
            </div>

            <!-- Contenuto -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <h3 class="text-gray-900 font-medium">{{ notif.title }}</h3>

                <!-- Pallino: solo se non letta -->
                <div
                  v-if="!notif.is_read"
                  class="w-2 h-2 bg-blue-600 rounded-full flex-shrink-0 ml-2 mt-2"
                  aria-label="Non letta"
                ></div>
              </div>

              <p v-if="notif.body" class="text-gray-600 text-sm mt-1">{{ notif.body }}</p>

              <p class="text-gray-400 text-xs mt-2">
                <i class="fa-regular fa-clock"></i>
                {{ notif.created_label || relativeTime(notif.created_at) }}
              </p>

              <!-- Azioni -->
              <div class="flex flex-wrap items-center gap-2 mt-3">
                <!-- Il link porta direttamente alla risorsa che ha generato la notifica -->
                <button
                  v-if="notif.link"
                  type="button"
                  class="px-3 py-1.5 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors min-h-[36px] cursor-pointer"
                  @click="apri(notif)"
                >
                  <i class="fa-solid fa-arrow-right"></i> Vai
                </button>

                <button
                  v-if="!notif.is_read"
                  type="button"
                  class="px-3 py-1.5 text-sm text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition-colors min-h-[36px] cursor-pointer"
                  @click="segnaLetta(notif.id)"
                >
                  <i class="fa-solid fa-check"></i> Segna come letta
                </button>

                <button
                  type="button"
                  class="px-3 py-1.5 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors min-h-[36px] cursor-pointer"
                  @click="elimina(notif.id)"
                >
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </button>
              </div>
            </div>
          </div>
        </article>

        <!-- Paginazione -->
        <div v-if="!loading && meta.last_page > 1" class="flex items-center justify-between pt-2">
          <button
            type="button"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white disabled:opacity-50 min-h-[40px]"
            :disabled="meta.current_page === 1"
            @click="vaiAPagina(meta.current_page - 1)"
          >
            Precedente
          </button>

          <span class="text-sm text-gray-600">Pagina {{ meta.current_page }} di {{ meta.last_page }}</span>

          <button
            type="button"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white disabled:opacity-50 min-h-[40px]"
            :disabled="meta.current_page === meta.last_page"
            @click="vaiAPagina(meta.current_page + 1)"
          >
            Successiva
          </button>
        </div>
      </div>

      <!-- COLONNA DESTRA: FILTRI -->
      <div class="space-y-6">
        <!-- Filtro per categoria -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:top-6">
          <h2 class="text-gray-900 font-semibold mb-4">Filtra notifiche</h2>

          <div class="space-y-2">
            <button
              type="button"
              :class="[
                'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left min-h-[44px] cursor-pointer',
                categoriaAttiva === '' ? 'bg-blue-100 text-blue-700 font-medium' : 'hover:bg-gray-50 text-gray-700',
              ]"
              @click="filtraCategoria('')"
            >
              <i class="fa-regular fa-bell w-5 text-center"></i>
              <span class="flex-1">Tutte</span>
            </button>

            <button
              v-for="cat in categorie"
              :key="cat.value"
              type="button"
              :class="[
                'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left min-h-[44px] cursor-pointer',
                categoriaAttiva === cat.value
                  ? 'bg-blue-100 text-blue-700 font-medium'
                  : 'hover:bg-gray-50 text-gray-700',
              ]"
              @click="filtraCategoria(cat.value)"
            >
              <i :class="[categoryStyle(cat.value).icon, 'w-5 text-center']"></i>
              <span class="flex-1">{{ cat.label }}</span>
            </button>
          </div>

          <!-- Solo non lette -->
          <label class="flex items-center gap-3 mt-6 px-6 border-t border-gray-200 min-h-[44px]">
            <input
              v-model="soloNonLette"
              type="checkbox"
              class="w-4 h-4 text-blue-600 rounded cursor-pointer"
              @change="filtraNonLette"
            />
            <span class="text-gray-700 text-sm">Mostra solo non lette</span>
          </label>

          <!-- Segna tutte come lette -->
          <button
            type="button"
            class="w-full mt-4 py-2.5 min-h-[44px] bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            :disabled="unreadCount === 0 || segnandoTutte"
            @click="segnaTutteLette"
          >
            <i :class="segnandoTutte ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-check-double'"></i>
            Segna tutte come lette
          </button>
        </div>

        <!-- Canali -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <h2 class="text-gray-900 font-semibold mb-2">Canali di Notifica</h2>
          <p class="text-gray-500 text-sm mb-4">
            Le notifiche in-app sono sempre attive. Email e push saranno configurabili appena
            l'endpoint delle preferenze sarà disponibile.
          </p>

          <ul class="space-y-3">
            <li class="flex items-center justify-between">
              <span class="text-gray-700 text-sm"><i class="fa-regular fa-bell w-5"></i> In app</span>
              <span class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full">Attivo</span>
            </li>
            <li class="flex items-center justify-between opacity-60">
              <span class="text-gray-700 text-sm"><i class="fa-regular fa-envelope w-5"></i> Email</span>
              <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">In arrivo</span>
            </li>
            <li class="flex items-center justify-between opacity-60">
              <span class="text-gray-700 text-sm"><i class="fa-solid fa-mobile-screen w-5"></i> Push</span>
              <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">In arrivo</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { useNotificationStore } from '@/store/notifications'
import { useFormatters } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'

/*
  Centro notifiche condiviso dai tre ruoli.
  Le viste paziente/medico/admin lo incapsulano senza duplicare la logica:
  il backend restituisce già solo le notifiche dell'utente autenticato.
*/
const router = useRouter()
const store = useNotificationStore()
const toast = useToast()
const { relativeTime } = useFormatters()

const categoriaAttiva = ref('')
const soloNonLette = ref(false)
const segnandoTutte = ref(false)

const notifiche = computed(() => store.items)
const loading = computed(() => store.loading)
const meta = computed(() => store.meta)
const unreadCount = computed(() => store.unreadCount)

const categorie = [
  { value: 'appuntamento', label: 'Appuntamenti' },
  { value: 'referto', label: 'Referti' },
  { value: 'prescrizione', label: 'Prescrizioni' },
  { value: 'pagamento', label: 'Pagamenti' },
  { value: 'messaggio', label: 'Messaggi' },
  { value: 'sistema', label: 'Sistema' },
]

/** Icona e colori per categoria, coerenti con il resto dell'interfaccia. */
function categoryStyle(category) {
  return (
    {
      appuntamento: { icon: 'fa-regular fa-calendar', bg: 'bg-blue-100', color: 'text-blue-600' },
      referto: { icon: 'fa-regular fa-file-lines', bg: 'bg-green-100', color: 'text-green-600' },
      prescrizione: { icon: 'fa-solid fa-capsules', bg: 'bg-purple-100', color: 'text-purple-600' },
      pagamento: { icon: 'fa-solid fa-credit-card', bg: 'bg-amber-100', color: 'text-amber-600' },
      messaggio: { icon: 'fa-regular fa-comment', bg: 'bg-teal-100', color: 'text-teal-600' },
      sistema: { icon: 'fa-solid fa-gear', bg: 'bg-gray-100', color: 'text-gray-600' },
    }[category] ?? { icon: 'fa-regular fa-bell', bg: 'bg-gray-100', color: 'text-gray-600' }
  )
}

/* -------------------------------------------------------------------------
 | Filtri (applicati lato server: la lista può essere lunga)
 * ---------------------------------------------------------------------- */
function filtraCategoria(value) {
  categoriaAttiva.value = value
  store.filters.category = value
  store.fetch(1)
}

function filtraNonLette() {
  store.filters.unread_only = soloNonLette.value
  store.fetch(1)
}

const vaiAPagina = (page) => store.fetch(page)

/* -------------------------------------------------------------------------
 | Azioni
 * ---------------------------------------------------------------------- */
async function segnaLetta(id) {
  try {
    await store.markAsRead(id)
  } catch (error) {
    toast.apiError(error)
  }
}

async function segnaTutteLette() {
  segnandoTutte.value = true

  try {
    await store.markAllAsRead()
    toast.success('Tutte le notifiche sono state segnate come lette.')
  } catch (error) {
    toast.apiError(error)
  } finally {
    segnandoTutte.value = false
  }
}

async function elimina(id) {
  try {
    await store.remove(id)
    toast.success('Notifica eliminata.')
  } catch (error) {
    toast.apiError(error)
  }
}

/** Aprendo la risorsa collegata la notifica si considera letta. */
async function apri(notif) {
  if (!notif.is_read) await segnaLetta(notif.id)
  if (notif.link) router.push(notif.link)
}

onMounted(() => {
  store.fetch(1)
  store.fetchUnreadCount()
})
</script>
