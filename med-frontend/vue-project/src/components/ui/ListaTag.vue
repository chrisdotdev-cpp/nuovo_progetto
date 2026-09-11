<template>
  <div class="mb-5">
    <label :for="inputId" class="block text-gray-700 mb-2 font-medium">{{ label }}</label>

    <!-- Voci già inserite -->
    <div v-if="model.length" class="flex flex-wrap gap-2 mb-2">
      <span
        v-for="(voce, index) in model"
        :key="`${voce}-${index}`"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm"
        :class="colorClasses"
      >
        {{ voce }}
        <button
          type="button"
          class="hover:opacity-70 min-w-[20px] cursor-pointer"
          :aria-label="`Rimuovi ${voce}`"
          @click="rimuovi(index)"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </span>
    </div>

    <!-- Aggiunta: Invio conferma la voce, senza inviare il form -->
    <div class="flex gap-2">
      <input
        :id="inputId"
        v-model="bozza"
        type="text"
        :placeholder="placeholder"
        class="flex-1 min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        @keydown.enter.prevent="aggiungi"
      />
      <button
        type="button"
        class="px-4 min-h-[44px] bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 cursor-pointer"
        :disabled="!bozza.trim()"
        @click="aggiungi"
      >
        <i class="fa-solid fa-plus"></i>
      </button>
    </div>

    <p v-if="hint" class="text-gray-500 text-sm mt-1.5">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed, ref, useId } from 'vue'

/*
  Campo per liste di stringhe (allergie, patologie croniche).
  Il backend le riceve come array JSON: qui si gestisce solo l'inserimento.
*/
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'Aggiungi voce' },
  hint: { type: String, default: '' },
  color: { type: String, default: 'blue' },
})

const emit = defineEmits(['update:modelValue'])

const inputId = `tags-${useId()}`
const bozza = ref('')

const model = computed(() => props.modelValue ?? [])

const colorClasses = computed(
  () =>
    ({
      blue: 'bg-blue-100 text-blue-700',
      red: 'bg-red-100 text-red-700',
      amber: 'bg-amber-100 text-amber-700',
      green: 'bg-green-100 text-green-700',
    })[props.color] ?? 'bg-gray-100 text-gray-700',
)

function aggiungi() {
  const valore = bozza.value.trim()

  // Nessun duplicato: la stessa allergia inserita due volte non ha senso
  if (!valore || model.value.includes(valore)) {
    bozza.value = ''
    return
  }

  emit('update:modelValue', [...model.value, valore])
  bozza.value = ''
}

function rimuovi(index) {
  emit(
    'update:modelValue',
    model.value.filter((_, i) => i !== index),
  )
}
</script>
