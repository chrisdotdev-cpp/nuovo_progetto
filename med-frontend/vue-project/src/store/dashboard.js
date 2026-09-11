import { defineStore } from 'pinia'
import { api } from '@/services/api'

/**
 * Dati delle viste Panoramica. Un solo endpoint per tutti i ruoli:
 * il backend restituisce gia' l'aggregato corretto.
 */
export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    role: null,
    stats: {},
    payload: {},
    loading: false,
    loadedAt: null,
  }),

  getters: {
    /** Evita ricariche inutili passando da una vista all'altra (cache 60s). */
    isFresh: (state) => state.loadedAt && Date.now() - state.loadedAt < 60000,
  },

  actions: {
    async fetch({ force = false } = {}) {
      if (this.isFresh && !force) return this.payload

      this.loading = true

      try {
        const { data } = await api.dashboard.get()
        this.role = data.role
        this.payload = data.data ?? {}
        this.stats = this.payload.stats ?? {}
        this.loadedAt = Date.now()
        return this.payload
      } finally {
        this.loading = false
      }
    },

    /**
     * Invalida la cache senza svuotare i dati.
     *
     * Differenza rispetto a reset(): la Panoramica continua a mostrare i valori
     * precedenti finche' non arriva la risposta, invece di sfarfallare a zero.
     * Usato dopo un evento che sposta gli aggregati (incasso, fattura emessa).
     */
    invalidate() {
      this.loadedAt = null
    },

    /** Invalida e ricarica subito: per la vista attualmente aperta. */
    async refresh() {
      this.invalidate()
      return this.fetch({ force: true })
    },

    reset() {
      this.payload = {}
      this.stats = {}
      this.loadedAt = null
    },
  },
})
