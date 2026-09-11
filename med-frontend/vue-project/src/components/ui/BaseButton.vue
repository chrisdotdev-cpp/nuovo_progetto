<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      // Base: touch target di almeno 44px, mobile-first
      'inline-flex items-center justify-center gap-2 rounded-lg font-semibold min-h-[44px] px-4 py-2.5',
      'transition-all duration-200 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-offset-2',
      'disabled:opacity-60 disabled:cursor-not-allowed disabled:active:scale-100 cursor-pointer',
      block ? 'w-full' : '',
      variants[variant] ?? variants.primary,
      customClass,
    ]"
    @click="$emit('click', $event)"
  >
    <!-- Spinner durante le chiamate API: blocca il doppio invio -->
    <i v-if="loading" class="fa-solid fa-circle-notch fa-spin"></i>
    <i v-else-if="icon" :class="icon"></i>

    <!-- Slot con fallback, cosi' i vecchi usi senza contenuto continuano a funzionare -->
    <span><slot>Conferma</slot></span>
  </button>
</template>

<script setup>
/*
  PROPS:
  - type: tipo HTML del pulsante (button | submit | reset)
  - variant: stile visivo (primary, secondary, danger, ghost, success)
  - loading: mostra lo spinner e disabilita il click
  - block: larghezza piena (default su mobile nei form)
  - icon: classe FontAwesome opzionale
  - customClass: classi extra per casi particolari
*/
defineProps({
  type: { type: String, default: 'button' },
  variant: { type: String, default: 'primary' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  block: { type: Boolean, default: true },
  icon: { type: String, default: '' },
  customClass: { type: String, default: '' },
})

defineEmits(['click'])

// Palette coerente con il resto dell'interfaccia (blu/indigo)
const variants = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 shadow-sm',
  secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-gray-400',
  success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
  ghost: 'bg-transparent text-blue-600 hover:bg-blue-50 focus:ring-blue-400',
}
</script>
