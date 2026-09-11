<template>
  <!-- Navbar con effetto vetro, ombra morbida e comportamento sticky -->
  <nav
    class="sticky top-0 z-20 backdrop-blur-md bg-white/80 border-b border-gray-100 shadow-sm transition-all duration-300
           flex items-center justify-between gap-4 py-3 pr-3 sm:py-4 sm:pr-4 pl-16 min-[901px]:pl-6 h-20 sm:h-24"
  >
    <!-- Sinistra: logo + brand, porta alla dashboard del ruolo -->
    <button
      type="button"
      class="flex items-center gap-2 sm:gap-3.5 group cursor-pointer min-w-0"
      @click="goDashboard"
      aria-label="Vai alla dashboard"
    >
      <div
        class="flex-shrink-0 w-14 h-14 sm:w-20 sm:h-20 flex items-center justify-center
               transition-all duration-300 group-hover:scale-105 group-hover:rotate-3"
      >
        <img
          :src="logo"
          alt="MedicaDigital Logo"
          class="w-full h-full object-contain"
        >
      </div>

      <h1 class="text-lg sm:text-xl font-extrabold tracking-tight text-gray-900 select-none truncate">
        <span
          class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent
                 transition-all duration-300 group-hover:from-indigo-600 group-hover:to-blue-600"
        >
          Medica
        </span>
        <span class="font-medium text-gray-500 group-hover:text-gray-700 transition-colors">Digital</span>
      </h1>
    </button>

    <!-- Destra: notifiche con contatore reale -->
    <div class="flex items-center flex-shrink-0">
      <button
        type="button"
        @click="goNotifications"
        class="relative flex items-center justify-center font-semibold rounded-2xl transition-all duration-300 group active:scale-95
               bg-blue-50/60 border border-blue-100 text-blue-600 text-base p-3
               hover:bg-blue-600 hover:text-white hover:border-blue-600 hover:shadow-md hover:shadow-blue-200/50
               sm:px-4 sm:py-2.5 sm:text-sm cursor-pointer"
        aria-label="Apri notifiche"
      >
        <i class="fa-regular fa-bell transition-transform duration-300 group-hover:rotate-12"></i>

        <span class="hidden sm:inline ml-2">Notifiche</span>

        <!-- Badge mostrato solo se ci sono davvero notifiche non lette -->
        <span
          v-if="notifications.hasUnread"
          class="absolute -top-1.5 -right-1.5 flex items-center justify-center min-w-[20px] h-5 px-1
                 rounded-full bg-red-500 text-white text-[11px] font-bold border-2 border-white"
        >
          {{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}
        </span>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'

import logo from '@/assets/logo/logo.png'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'

const router = useRouter()
const authStore = useAuthStore()
const notifications = useNotificationStore()

// Il contatore si aggiorna da solo finche' il layout resta montato
onMounted(() => notifications.startPolling())
onBeforeUnmount(() => notifications.stopPolling())

const goNotifications = () => {
  router.push(`/${authStore.role}/notifiche`)
}

// Prima portava anch'esso alle notifiche: ora va davvero alla dashboard
const goDashboard = () => {
  router.push(authStore.dashboardPath)
}
</script>
