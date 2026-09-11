<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="max-w-4xl w-full">
      <!-- HEADER -->
      <div class="text-center mb-12">
        <div class="max-w-4xl flex items-center justify-center cursor-pointer">
          <img 
            :src="logo"
            alt="MedicaDigital Logo"
            class="w-48 h-48 sm:w-56 sm:h-56 md:w-72 md:h-72 object-contain mx-auto"
          >
        </div>

        <!-- Testo -->
        <h1 class="text-4xl font-bold m-2"><span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent transition-all duration-300 group-hover:from-indigo-600 group-hover:to-blue-600">Clinica</span> Digitale</h1>
        <p class="text-gray-600">Piattaforma Sanitaria Digitale Privata</p>
      </div>

      <!-- CONTENITORE BIANCO -->
      <main class="w-full">
        <!-- GRID RUOLI -->
          <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>

// Importazioni esterne
import logo from '@/assets/logo/logo.png'
</script>
