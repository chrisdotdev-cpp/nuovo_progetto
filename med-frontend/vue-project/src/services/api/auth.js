import http from '@/services/http'

/** Endpoint di autenticazione e profilo. */
export const authApi = {
  login: (credentials) =>
    http.post('/auth/login', {
      ...credentials,
      device_name: import.meta.env.VITE_DEVICE_NAME || 'web',
    }),

  me: () => http.get('/auth/me'),
  refresh: () => http.post('/auth/refresh'),
  logout: () => http.post('/auth/logout'),
  logoutAll: () => http.post('/auth/logout-all'),

  updatePassword: (payload) => http.put('/auth/password', payload),

  /** L'avatar richiede multipart: si costruisce FormData solo se presente. */
  updateProfile: (payload) => {
    if (payload.avatar instanceof File) {
      const form = new FormData()
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined) form.append(key, value)
      })
      // Laravel non legge i file su PUT: si usa POST con _method
      form.append('_method', 'PUT')
      return http.post('/auth/profile', form)
    }

    return http.put('/auth/profile', payload)
  },
}
