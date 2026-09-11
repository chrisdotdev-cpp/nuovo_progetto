<template>
  <div class="flex flex-col h-screen overflow-hidden">

    <!-- Navbar -->
    <AppNavbar />

    <div class="flex flex-1 overflow-hidden">

      <!-- Sidebar -->
      <AppSidebar />

      <!-- Contenuto -->
      <main class="flex-1 p-4 bg-gray-100 overflow-y-auto">
        <RouterView />
      </main>

    </div>
  </div>
</template>

<script setup>
import AppNavbar from './AppNavbar.vue'
import AppSidebar from './AppSidebar.vue'
</script>
