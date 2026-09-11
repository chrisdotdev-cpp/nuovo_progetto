<template>
  <div class="bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition-all hover:-translate-y-1 text-center cursor-pointer w-64 mx-auto" @click="$emit('click')">
     <!-- Icona dentro cerchio -->
    <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center"
         :class="iconBg">
      <i :class="icon" class="text-white fa-2x"></i>
    </div>

    <h3 class="text-gray-800 font-semibold text-lg m-2">{{ title }}</h3>
    <p class="text-gray-600 text-sm text-center"> {{ about }}</p>
  </div>
</template>

<script setup>
/*
  PROPS DEL COMPONENTE:
  ---------------------
  - title: testo del titolo della card
  - icon: classe dell'icona (FontAwesome)
*/

defineProps({
  title: String,
  icon: String,
  iconBg: String,
  about: String
})
</script>

<style scoped>
.card-hover {
  cursor: pointer;
  transition: 0.2s;
}
.card-hover:hover {
  transform: scale(1.03);
}
</style>
