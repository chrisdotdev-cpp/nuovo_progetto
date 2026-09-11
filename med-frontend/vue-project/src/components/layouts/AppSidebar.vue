<template>
  <!-- PULSANTE HAMBURGER: solo sotto i 901px -->
  <button
    type="button"
    @click="isSidebarOpen = !isSidebarOpen"
    class="min-[901px]:hidden fixed top-3 left-1 z-50 p-2 bg-white border border-gray-200 rounded-xl shadow-sm text-gray-700 hover:bg-gray-100 transition cursor-pointer"
    :aria-expanded="isSidebarOpen"
    aria-label="Apri o chiudi il menu"
  >
    <i :class="['fa-solid text-xl', isSidebarOpen ? 'fa-xmark' : 'fa-bars']"></i>
  </button>

  <!-- Overlay scuro su mobile -->
  <div
    v-if="isSidebarOpen"
    @click="isSidebarOpen = false"
    class="min-[901px]:hidden fixed inset-0 bg-black/40 z-30 transition-opacity"
  ></div>

  <!-- SIDEBAR -->
  <aside
    :class="[
      'flex flex-col bg-white border-r border-gray-200 w-56 h-full z-40 transition-transform duration-300',
      'fixed top-0 left-0',
      'min-[901px]:sticky min-[901px]:top-0 min-[901px]:translate-x-0',
      isSidebarOpen ? 'translate-x-0' : 'max-[900px]:-translate-x-full',
    ]"
  >
    <!-- PROFILO -->
    <div class="border-b border-gray-200 p-4 mb-4 pt-16 min-[901px]:pt-4 flex-shrink-0">
      <div class="flex items-center gap-3">
        <img :src="avatar" alt="Profilo" class="w-12 h-12 flex-shrink-0 rounded-full object-cover">
        <div class="min-w-0">
          <h3 class="font-medium text-gray-900 leading-tight truncate">
            {{ authStore.user?.name || 'Utente' }}
          </h3>
          <p class="text-sm text-gray-500 capitalize">
            {{ authStore.role }}
          </p>
        </div>
      </div>
    </div>

    <!-- MENU dinamico per ruolo -->
    <nav class="flex-1 overflow-y-auto p-4">
      <ul class="flex flex-col gap-2">
        <li v-for="item in links" :key="item.to">
          <RouterLink
            :to="item.to"
            class="flex items-center gap-2 px-3 py-2.5 rounded-xl hover:bg-blue-50 transition min-h-[44px]"
            active-class="bg-blue-100 text-blue-600 font-semibold"
            @click="closeSidebarOnMobile"
          >
            <i :class="[item.icon, 'w-5 text-center']"></i>
            <span>{{ item.label }}</span>

            <!-- Badge notifiche sulla voce dedicata -->
            <span
              v-if="item.to.endsWith('/notifiche') && notifications.hasUnread"
              class="ml-auto min-w-[20px] h-5 px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-[11px] font-bold"
            >
              {{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}
            </span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <!-- FOOTER -->
    <div class="mt-auto p-4 border-t border-gray-200 flex flex-col gap-2 flex-shrink-0">
      <RouterLink
        :to="`/${authStore.role}/impostazioni`"
        class="flex items-center gap-2 px-3 py-2.5 rounded-xl hover:bg-gray-100 transition min-h-[44px]"
        active-class="bg-blue-100 text-blue-600 font-semibold"
        @click="closeSidebarOnMobile"
      >
        <i class="fa-solid fa-gear w-5 text-center"></i>
        <span>Impostazioni</span>
      </RouterLink>

      <button
        type="button"
        class="w-full text-red-500 text-left flex items-center gap-2 px-3 py-2.5 rounded-xl hover:bg-red-100 transition min-h-[44px] disabled:opacity-60 cursor-pointer"
        :disabled="loggingOut"
        @click="handleLogout"
      >
        <i :class="['w-5 text-center', loggingOut ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-power-off']"></i>
        <span>{{ loggingOut ? 'Uscita...' : 'Esci' }}</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'
import { sidebarLinks } from '@/config/sidebar'

// Avatar per ruolo
import avatarPaziente from '@/assets/pazienti/patient_avatar.png'
import avatarMedico from '@/assets/doctor/doctor_avatar.png'
import avatarAdmin from '@/assets/admin/admin_avatar.png'

const authStore = useAuthStore()
const notifications = useNotificationStore()
const router = useRouter()
const route = useRoute()

/*
  computed e non costante: se il ruolo cambia (login/logout senza reload)
  menu e avatar si aggiornano da soli.
*/
const links = computed(() => sidebarLinks[authStore.role] ?? [])

const avatars = {
  paziente: avatarPaziente,
  medico: avatarMedico,
  admin: avatarAdmin,
}

const avatar = computed(() => {
  // Avatar caricato dall'utente, altrimenti quello di default del ruolo
  return authStore.user?.avatar_url || avatars[authStore.role] || avatarPaziente
})

const isSidebarOpen = ref(false)
const loggingOut = ref(false)

const closeSidebarOnMobile = () => {
  isSidebarOpen.value = false
}

// Chiusura automatica anche quando si naviga da codice (non solo dal click)
watch(() => route.fullPath, closeSidebarOnMobile)

const handleLogout = async () => {
  loggingOut.value = true

  try {
    notifications.reset()
    await authStore.logout()
    router.push('/')
  } finally {
    loggingOut.value = false
  }
}
</script>
