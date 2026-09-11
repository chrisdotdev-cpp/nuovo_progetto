import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

/**
 * Configurazione dedicata ai test.
 *
 * Non si riusa vite.config.js di proposito: quella carica vue-devtools e il
 * plugin Tailwind, inutili sotto Vitest e responsabili di parecchi secondi di
 * avvio a ogni run. Qui resta solo cio' che serve a compilare i .vue e a
 * risolvere gli alias.
 */
export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),

      /*
        I toast sono un effetto collaterale da osservare, non da renderizzare.
        L'alias sostituisce la libreria in tutta la suite: piu' affidabile di un
        vi.mock ripetuto file per file, dato che vue3-toastify viene importata a
        catena da useToast e quindi da router, store e quasi ogni vista.
      */
      'vue3-toastify': fileURLToPath(new URL('./tests/stubs/vue3-toastify.js', import.meta.url)),
    },
  },

  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/setup.js'],
    include: ['tests/**/*.spec.js'],
    /*
      I test E2E hanno la stessa estensione ma girano con Playwright, contro
      browser e backend reali: sotto Vitest fallirebbero all'import di
      @playwright/test.
    */
    exclude: ['node_modules/**', 'tests/e2e/**'],
    // Ogni test riparte da spie pulite: niente contaminazione fra casi
    clearMocks: true,
    restoreMocks: true,
  },
})
