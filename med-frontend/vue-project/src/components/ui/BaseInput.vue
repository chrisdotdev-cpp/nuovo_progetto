<template>
  <div class="mb-5">
    <label v-if="label" :for="inputId" class="block text-gray-700 mb-2 font-medium">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="relative">
      <!-- Icona opzionale a sinistra -->
      <i
        v-if="icon"
        :class="[icon, 'absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none']"
      ></i>

      <input
        :id="inputId"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :class="[
          'w-full min-h-[44px] px-4 py-2.5 border rounded-lg transition-colors',
          'focus:ring-2 focus:border-transparent focus:outline-none',
          icon ? 'pl-10' : '',
          error
            ? 'border-red-400 focus:ring-red-400 bg-red-50/40'
            : 'border-gray-300 focus:ring-blue-500',
          disabled ? 'bg-gray-100 cursor-not-allowed' : '',
        ]"
        v-model="model"
        @blur="$emit('blur')"
      />
    </div>

    <!-- Messaggio di errore proveniente dalla validazione Laravel -->
    <p v-if="error" class="text-red-600 text-sm mt-1.5 flex items-center gap-1">
      <i class="fa-solid fa-circle-exclamation"></i>
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-gray-500 text-sm mt-1.5">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed, useId } from 'vue'

/*
  PROPS:
  - modelValue: valore del v-model
  - label / placeholder / hint: testi di supporto
  - type: tipo dell'input
  - error: messaggio di errore (arriva da useApiRequest().fieldError('campo'))
  - icon: classe FontAwesome mostrata dentro il campo
*/
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  icon: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  autocomplete: { type: String, default: 'off' },
})

const emit = defineEmits(['update:modelValue', 'blur'])

// id univoco: collega label e input anche con piu' istanze nella stessa pagina
const inputId = `input-${useId()}`

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
</script>
