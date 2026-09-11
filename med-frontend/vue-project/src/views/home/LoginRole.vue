<template>
  <div class="relative max-w-lg w-full bg-white rounded-xl shadow-xl p-6 sm:p-10 md:p-12">

    <!--
      min-h/min-w 44px: e' il bersaglio touch minimo dichiarato dal progetto e
      preteso dal test di layout. Il negative margin riallinea il testo al bordo
      della card, cosi' l'area cliccabile cresce senza spostare la grafica.
    -->
    <button
      type="button"
      class="absolute top-2 left-2 sm:top-4 sm:left-4 inline-flex items-center min-h-[44px] min-w-[44px] px-2 text-gray-600 hover:text-gray-900 transition-colors cursor-pointer"
      @click="router.push('/')"
    >
      <i class="fa-solid fa-arrow-left mr-1"></i> Indietro
    </button>

    <div class="text-center mt-8 mb-6">
      <div
        class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center"
        :class="roleData.iconBg"
      >
        <i :class="roleData.icon" class="text-white fa-2x"></i>
      </div>

      <h2 class="text-xl font-semibold mb-2">{{ roleData.loginTitle }}</h2>
      <p class="text-gray-600">Inserisci le tue credenziali</p>
    </div>

    <!-- Errore generale (credenziali errate, account sospeso, server offline) -->
    <div
      v-if="errorMessage"
      class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-start gap-2"
      role="alert"
    >
      <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
      <span>{{ errorMessage }}</span>
    </div>

    <!-- Form -->
    <form class="w-full" novalidate @submit.prevent="handleLogin">
      <BaseInput
        v-model="form.email"
        label="Email"
        type="email"
        placeholder="email@example.com"
        icon="fa-regular fa-envelope"
        autocomplete="username"
        required
        :error="fieldError('email')"
      />

      <BaseInput
        v-model="form.password"
        label="Password"
        type="password"
        placeholder="********"
        icon="fa-solid fa-lock"
        autocomplete="current-password"
        required
        :error="fieldError('password')"
      />

      <BaseButton type="submit" :loading="loading" icon="fa-solid fa-right-to-bracket">
        {{ loading ? 'Accesso in corso...' : 'Accedi' }}
      </BaseButton>
    </form>

    <!-- Footer -->
    <div class="mt-6 text-center">
      <a href="#" class="text-blue-600 hover:underline text-sm">Password dimenticata?</a>
    </div>
  </div>
</template>

<script setup>
import { reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roles, validRoles } from '@/data/roles.js'
import { useAuthStore } from '@/store/auth.js'
import { useApiRequest } from '@/composables/useApiRequest'
import { useToast } from '@/composables/useToast'

import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

// loading, errori di validazione (422) e messaggio generale gestiti dal composable
const { loading, errorMessage, fieldError, setFieldError, reset, run } = useApiRequest()

const role = computed(() => route.params.role)
const roleData = computed(() => roles[role.value] ?? roles.paziente)

const form = reactive({
  email: '',
  password: '',
})

// Ruolo inesistente nell'URL: si torna alla scelta invece di mostrare una pagina rotta
onMounted(() => {
  if (!validRoles.includes(role.value)) {
    router.replace('/')
  }
})

/**
 * Validazione minima lato client: evita un round-trip inutile e mostra
 * l'errore sul campo con lo stesso formato usato dalle FormRequest Laravel.
 */
function validate() {
  reset()
  let valid = true

  if (!form.email.trim()) {
    setFieldError('email', "L'email e' obbligatoria.")
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    setFieldError('email', 'Inserisci un indirizzo email valido.')
    valid = false
  }

  if (!form.password) {
    setFieldError('password', "La password e' obbligatoria.")
    valid = false
  }

  if (!valid) errorMessage.value = 'Controlla i campi evidenziati.'

  return valid
}

/**
 * Sceglie dove atterrare dopo il login:
 * 1. la pagina che l'utente stava cercando di aprire (?redirect=...)
 *    ma solo se appartiene alla sua area, altrimenti la guard lo rimbalzerebbe;
 * 2. altrimenti la rotta decisa dal backend.
 */
function targetAfterLogin(backendRedirect) {
  const intended = route.query.redirect

  if (typeof intended === 'string' && intended.startsWith('/') && !intended.startsWith('//')) {
    const area = `/${authStore.role}`
    if (intended === area || intended.startsWith(`${area}/`)) return intended
  }

  return backendRedirect
}

const handleLogin = async () => {
  if (!validate()) return

  // run() restituisce ora il valore reale di login() (la rotta di destinazione):
  // prima veniva letto response.data su una stringa -> null -> nessuna redirect.
  const redirect = await run(
    () =>
      authStore.login({
        email: form.email.trim(),
        password: form.password,
        role: role.value,
      }),
    {
      // Il toast di errore lo mostriamo noi in modo piu' discreto nel box sopra
      showToast: false,
    },
  )

  // login fallito: errorMessage/errors sono gia' popolati dal composable
  if (!redirect) return

  toast.success(`Bentornato, ${authStore.user?.name ?? ''}`)

  // replace: il tasto "indietro" non deve riportare al form di login
  await router.replace(targetAfterLogin(redirect))
}
</script>
