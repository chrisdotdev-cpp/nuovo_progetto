import { ref } from 'vue'
import { useToast } from '@/composables/useToast'

/**
 * Gestione uniforme di loading, errori e validazione per una chiamata API.
 *
 * Uso tipico in un componente:
 *   const { loading, errors, run } = useApiRequest()
 *   const salva = () => run(() => api.patients.update(id, form), {
 *     successMessage: 'Anagrafica salvata',
 *     onSuccess: (data) => emit('saved', data),
 *   })
 */

/*
  BUGFIX: run() faceva sempre `response.data`.
  Se la callback non restituisce una risposta axios ma un valore gia' pronto
  (es. authStore.login() che ritorna la rotta di redirect), `response.data` era
  undefined e run() restituiva null -> il chiamante credeva che la chiamata
  fosse fallita. E' esattamente il motivo per cui il login non redirigeva.

  Ora si scarta l'involucro axios solo quando c'e' davvero.
*/
function isAxiosResponse(value) {
  return (
    value !== null &&
    typeof value === 'object' &&
    'data' in value &&
    'status' in value &&
    'config' in value
  )
}

/** Estrae il payload utile: response.data se axios, altrimenti il valore stesso. */
export function unwrap(value) {
  return isAxiosResponse(value) ? value.data : value
}

export function useApiRequest() {
  const loading = ref(false)
  const errors = ref({})      // { campo: ["messaggio"] } -> per gli input
  const errorMessage = ref(null)
  /** true se l'ultima run() e' andata a buon fine (utile con payload vuoti/void). */
  const lastCallOk = ref(false)
  const toast = useToast()

  const reset = () => {
    errors.value = {}
    errorMessage.value = null
  }

  /** Messaggio del singolo campo, pronto per la prop :error di BaseInput */
  const fieldError = (field) => errors.value?.[field]?.[0] ?? ''

  /** Imposta a mano un errore di campo (validazione lato client). */
  const setFieldError = (field, message) => {
    errors.value = { ...errors.value, [field]: [message] }
  }

  async function run(request, options = {}) {
    const {
      successMessage = null,
      errorMessage: customError = null,
      showToast = true,
      onSuccess = null,
      onError = null,
    } = options

    loading.value = true
    reset()

    try {
      const response = await request()
      const payload = unwrap(response)

      if (successMessage && showToast) toast.success(successMessage)
      if (onSuccess) onSuccess(payload)

      // ok:true permette al chiamante di distinguere "riuscito ma senza payload"
      // da "fallito", senza dipendere dalla verita' del valore restituito.
      lastCallOk.value = true
      return payload
    } catch (error) {
      lastCallOk.value = false

      // 422: errori di validazione per campo (FormRequest Laravel)
      errors.value = error?.errors ?? {}
      errorMessage.value = error?.firstError || error?.message || customError

      // Il 401 e' gia' gestito dall'interceptor: non si duplica il toast
      if (showToast && error?.status !== 401) {
        toast.apiError(error, customError ?? 'Operazione non riuscita.')
      }

      if (onError) onError(error)

      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    errors,
    errorMessage,
    fieldError,
    setFieldError,
    reset,
    run,
    ok: lastCallOk,
  }
}
