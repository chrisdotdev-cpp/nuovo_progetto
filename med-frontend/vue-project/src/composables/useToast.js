/**
 * Wrapper unico sui toast.
 *
 * Il progetto aveva due librerie in uso (toastify-js nel router e vue3-toastify
 * nei componenti): qui si standardizza su vue3-toastify, gia' registrata in main.js.
 */
import { toast } from 'vue3-toastify'

const base = {
  autoClose: 2500,
  position: toast.POSITION.TOP_RIGHT,
  hideProgressBar: false,
}

export function useToast() {
  return {
    success: (message, options = {}) => toast.success(message, { ...base, ...options }),
    error: (message, options = {}) => toast.error(message, { ...base, autoClose: 4000, ...options }),
    info: (message, options = {}) => toast.info(message, { ...base, ...options }),
    warning: (message, options = {}) => toast.warning(message, { ...base, ...options }),

    /**
     * Mostra l'errore normalizzato dall'interceptor.
     * Preferisce il primo errore di validazione, piu' utile del messaggio generico.
     */
    apiError: (error, fallback = 'Operazione non riuscita.') =>
      toast.error(error?.firstError || error?.message || fallback, { ...base, autoClose: 4000 }),
  }
}
