import { defineStore } from 'pinia'
import { api } from '@/services/api'

/**
 * Notifiche in-app. Il contatore alimenta il badge della navbar,
 * la lista alimenta le viste Notifiche dei tre ruoli.
 */
export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
    meta: { current_page: 1, last_page: 1, total: 0 },
    filters: { category: '', unread_only: false },
    pollingId: null,
  }),

  getters: {
    hasUnread: (state) => state.unreadCount > 0,
    unread: (state) => state.items.filter((item) => !item.is_read),
    byCategory: (state) => (category) =>
      category ? state.items.filter((item) => item.category === category) : state.items,
  },

  actions: {
    async fetch(page = 1) {
      this.loading = true

      try {
        const { data } = await api.notifications.list({ ...this.filters, page })
        this.items = data.data
        this.meta = data.meta ?? this.meta
        return this.items
      } finally {
        this.loading = false
      }
    },

    async fetchUnreadCount() {
      const { data } = await api.notifications.unreadCount()
      this.unreadCount = data.count
      return this.unreadCount
    },

    async markAsRead(id) {
      await api.notifications.markAsRead(id)

      const item = this.items.find((n) => n.id === id)
      if (item && !item.is_read) {
        item.is_read = true
        item.read_at = new Date().toISOString()
        this.unreadCount = Math.max(0, this.unreadCount - 1)
      }
    },

    async markAllAsRead() {
      await api.notifications.markAllAsRead()
      this.items.forEach((item) => {
        item.is_read = true
        item.read_at = new Date().toISOString()
      })
      this.unreadCount = 0
    },

    async remove(id) {
      await api.notifications.remove(id)
      const item = this.items.find((n) => n.id === id)
      if (item && !item.is_read) this.unreadCount = Math.max(0, this.unreadCount - 1)
      this.items = this.items.filter((n) => n.id !== id)
    },

    /**
     * Polling leggero del solo contatore (60s).
     * Quando servira' il realtime si sostituira' con un canale broadcast.
     */
    startPolling(intervalMs = 60000) {
      this.stopPolling()
      this.fetchUnreadCount().catch(() => {})
      this.pollingId = setInterval(() => {
        this.fetchUnreadCount().catch(() => {})
      }, intervalMs)
    },

    stopPolling() {
      if (this.pollingId) {
        clearInterval(this.pollingId)
        this.pollingId = null
      }
    },

    reset() {
      this.stopPolling()
      this.items = []
      this.unreadCount = 0
    },
  },
})
