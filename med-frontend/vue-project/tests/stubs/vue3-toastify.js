import { vi } from 'vitest'

/**
 * Sostituto di vue3-toastify per i test.
 *
 * Viene agganciato con un alias in vitest.config.js invece che con vi.mock:
 * la libreria e' importata a catena da useToast -> router, store e quasi ogni
 * vista, quindi serve una sostituzione valida per l'intera suite e non
 * dipendente dall'ordine di hoisting dei singoli file.
 *
 * I metodi sono spie: `clearMocks: true` le azzera prima di ogni test, cosi'
 * le asserzioni restano isolate.
 *
 *   import { toast } from 'vue3-toastify'
 *   expect(toast.error).toHaveBeenCalledWith('Devi effettuare il login per accedere', expect.any(Object))
 */
export const toast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  warn: vi.fn(),
  loading: vi.fn(),
  update: vi.fn(),
  dismiss: vi.fn(),
  clearAll: vi.fn(),
  POSITION: {
    TOP_RIGHT: 'top-right',
    TOP_CENTER: 'top-center',
    TOP_LEFT: 'top-left',
    BOTTOM_RIGHT: 'bottom-right',
    BOTTOM_CENTER: 'bottom-center',
    BOTTOM_LEFT: 'bottom-left',
  },
  TYPE: { SUCCESS: 'success', ERROR: 'error', INFO: 'info', WARNING: 'warning' },
}

/** Alcuni moduli importano il plugin di default: qui basta un no-op installabile. */
export default toast

export const Vue3Toastify = { install: () => {} }
export const updateGlobalOptions = vi.fn()
