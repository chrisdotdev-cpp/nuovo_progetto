<template>
  <div class="space-y-6">
    <h2 class="text-xl sm:text-2xl font-bold">Impostazioni</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- PROFILO UTENTE -->
      <section class="bg-white shadow-sm border border-gray-200 rounded-xl p-4 sm:p-6">
        <h3 class="text-lg font-semibold mb-1">Profilo Utente</h3>
        <p class="text-gray-500 mb-4 text-sm">Gestisci nome, email e recapiti.</p>

        <form novalidate @submit.prevent="salvaProfilo">
          <BaseInput
            v-model="profilo.name"
            label="Nome completo"
            icon="fa-regular fa-user"
            autocomplete="name"
            required
            :error="profiloReq.fieldError('name')"
          />

          <BaseInput
            v-model="profilo.email"
            label="Email"
            type="email"
            icon="fa-regular fa-envelope"
            autocomplete="email"
            required
            :error="profiloReq.fieldError('email')"
          />

          <BaseInput
            v-model="profilo.phone"
            label="Telefono"
            type="tel"
            icon="fa-solid fa-phone"
            autocomplete="tel"
            placeholder="+39 333 1234567"
            :error="profiloReq.fieldError('phone')"
          />

          <BaseButton type="submit" :loading="profiloReq.loading.value" icon="fa-solid fa-floppy-disk">
            Salva modifiche
          </BaseButton>
        </form>
      </section>

      <!-- SICUREZZA -->
      <section class="bg-white shadow-sm border border-gray-200 rounded-xl p-4 sm:p-6">
        <h3 class="text-lg font-semibold mb-1">Sicurezza</h3>
        <p class="text-gray-500 mb-4 text-sm">Aggiorna la password di accesso.</p>

        <form novalidate @submit.prevent="cambiaPassword">
          <BaseInput
            v-model="password.current_password"
            label="Password attuale"
            type="password"
            icon="fa-solid fa-lock"
            autocomplete="current-password"
            required
            :error="passwordReq.fieldError('current_password')"
          />

          <BaseInput
            v-model="password.password"
            label="Nuova password"
            type="password"
            icon="fa-solid fa-key"
            autocomplete="new-password"
            required
            hint="Almeno 8 caratteri, con lettere e numeri."
            :error="passwordReq.fieldError('password')"
          />

          <BaseInput
            v-model="password.password_confirmation"
            label="Conferma nuova password"
            type="password"
            icon="fa-solid fa-key"
            autocomplete="new-password"
            required
          />

          <BaseButton type="submit" :loading="passwordReq.loading.value" icon="fa-solid fa-shield-halved">
            Aggiorna password
          </BaseButton>
        </form>

        <!-- Disconnessione da tutti i dispositivi -->
        <div class="mt-6 pt-6 border-t border-gray-200">
          <p class="text-gray-500 text-sm mb-3">
            Se sospetti un accesso non autorizzato puoi revocare tutte le sessioni attive.
          </p>
          <BaseButton
            variant="secondary"
            icon="fa-solid fa-right-from-bracket"
            :loading="logoutTuttoInCorso"
            @click="disconnettiTutto"
          >
            Esci da tutti i dispositivi
          </BaseButton>
        </div>
      </section>

      <!-- DATI SANITARI: solo per il paziente -->
      <section
        v-if="auth.isPatient"
        class="bg-white shadow-sm border border-gray-200 rounded-xl p-4 sm:p-6 lg:col-span-2"
      >
        <h3 class="text-lg font-semibold mb-1">Dati Sanitari</h3>
        <p class="text-gray-500 mb-4 text-sm">
          Informazioni utili al personale medico in caso di necessità.
        </p>

        <form novalidate @submit.prevent="salvaDatiSanitari">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
            <BaseInput
              v-model="clinico.codice_fiscale"
              label="Codice fiscale"
              icon="fa-regular fa-id-card"
              :error="clinicoReq.fieldError('codice_fiscale')"
            />

            <BaseInput
              v-model="clinico.birth_date"
              label="Data di nascita"
              type="date"
              :error="clinicoReq.fieldError('birth_date')"
            />

            <div class="mb-5">
              <label for="blood-type" class="block text-gray-700 mb-2 font-medium">Gruppo sanguigno</label>
              <select
                id="blood-type"
                v-model="clinico.blood_type"
                class="w-full min-h-[44px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
              >
                <option value="">Non specificato</option>
                <option v-for="gruppo in gruppiSanguigni" :key="gruppo" :value="gruppo">{{ gruppo }}</option>
              </select>
            </div>

            <BaseInput
              v-model="clinico.city"
              label="Città"
              icon="fa-solid fa-location-dot"
              :error="clinicoReq.fieldError('city')"
            />

            <BaseInput
              v-model="clinico.emergency_contact_name"
              label="Contatto di emergenza"
              icon="fa-solid fa-user-shield"
              :error="clinicoReq.fieldError('emergency_contact_name')"
            />

            <BaseInput
              v-model="clinico.emergency_contact_phone"
              label="Telefono di emergenza"
              type="tel"
              icon="fa-solid fa-phone"
              :error="clinicoReq.fieldError('emergency_contact_phone')"
            />
          </div>

          <!-- Allergie e patologie: liste modificabili come "chip" -->
          <ListaTag v-model="clinico.allergies" label="Allergie" placeholder="Es. Penicillina" color="red" />
          <ListaTag
            v-model="clinico.chronic_conditions"
            label="Patologie croniche"
            placeholder="Es. Ipertensione"
            color="amber"
          />

          <BaseButton
            type="submit"
            :loading="clinicoReq.loading.value"
            :block="false"
            icon="fa-solid fa-floppy-disk"
            customClass="w-full sm:w-auto"
          >
            Salva dati sanitari
          </BaseButton>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'

import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ListaTag from '@/components/ui/ListaTag.vue'

import { api } from '@/services/api'
import { useAuthStore } from '@/store/auth'
import { useApiRequest } from '@/composables/useApiRequest'
import { useToast } from '@/composables/useToast'

/*
  Impostazioni condivise dai tre ruoli.
  Il blocco "Dati Sanitari" compare solo al paziente: gli altri ruoli non hanno
  una scheda clinica da compilare.
*/
const auth = useAuthStore()
const toast = useToast()

const profiloReq = useApiRequest()
const passwordReq = useApiRequest()
const clinicoReq = useApiRequest()

const gruppiSanguigni = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', '0+', '0-']

/* -------------------------------------------------------------------------
 | Profilo account
 * ---------------------------------------------------------------------- */
const profilo = reactive({
  name: '',
  email: '',
  phone: '',
})

async function salvaProfilo() {
  const risposta = await profiloReq.run(() => api.auth.updateProfile({ ...profilo }), {
    successMessage: 'Profilo aggiornato.',
  })

  // Lo store va allineato: nome e avatar compaiono in sidebar e navbar
  if (risposta?.user) auth.setUser(risposta.user)
}

/* -------------------------------------------------------------------------
 | Password
 * ---------------------------------------------------------------------- */
const password = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

async function cambiaPassword() {
  const esito = await passwordReq.run(() => api.auth.updatePassword({ ...password }), {
    successMessage: 'Password aggiornata. Le altre sessioni sono state disconnesse.',
  })

  if (esito !== null) {
    password.current_password = ''
    password.password = ''
    password.password_confirmation = ''
  }
}

const logoutTuttoInCorso = ref(false)

async function disconnettiTutto() {
  logoutTuttoInCorso.value = true

  try {
    await api.auth.logoutAll()
    // Revocati tutti i token, anche il proprio: si torna al login
    auth.clearSession()
    window.location.href = '/'
  } catch (error) {
    toast.apiError(error)
  } finally {
    logoutTuttoInCorso.value = false
  }
}

/* -------------------------------------------------------------------------
 | Dati sanitari (solo paziente)
 * ---------------------------------------------------------------------- */
const clinico = reactive({
  codice_fiscale: '',
  birth_date: '',
  blood_type: '',
  city: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  allergies: [],
  chronic_conditions: [],
})

async function salvaDatiSanitari() {
  const patientId = auth.patientId

  if (!patientId) {
    toast.error('Profilo paziente non disponibile.')
    return
  }

  await clinicoReq.run(() => api.patients.update(patientId, { ...clinico }), {
    successMessage: 'Dati sanitari aggiornati.',
  })
}

/* -------------------------------------------------------------------------
 | Caricamento iniziale
 * ---------------------------------------------------------------------- */
onMounted(async () => {
  // I dati dell'account sono già in sessione
  profilo.name = auth.user?.name ?? ''
  profilo.email = auth.user?.email ?? ''
  profilo.phone = auth.user?.phone ?? ''

  if (!auth.isPatient) return

  // La scheda clinica si legge dall'endpoint dedicato
  const { data } = await api.patients.me()
  const scheda = data.data ?? data

  Object.assign(clinico, {
    codice_fiscale: scheda.codice_fiscale ?? '',
    birth_date: scheda.birth_date ?? '',
    blood_type: scheda.blood_type ?? '',
    city: scheda.city ?? '',
    emergency_contact_name: scheda.emergency_contact?.name ?? '',
    emergency_contact_phone: scheda.emergency_contact?.phone ?? '',
    allergies: scheda.allergies ?? [],
    chronic_conditions: scheda.chronic_conditions ?? [],
  })
})
</script>
