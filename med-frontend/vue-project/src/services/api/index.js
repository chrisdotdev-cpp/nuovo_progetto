/**
 * Punto unico di accesso alle API.
 * Nei componenti: import { api } from '@/services/api'  ->  api.appointments.list()
 */
import http from '@/services/http'
import { authApi } from './auth'

/** Rimuove i parametri vuoti: evita querystring sporche come ?status=&q= */
const clean = (params = {}) =>
  Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined),
  )

/** Costruisce un FormData a partire da un oggetto (upload file). */
const toFormData = (payload) => {
  const form = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined) return

    if (Array.isArray(value)) {
      value.forEach((item) => form.append(`${key}[]`, item))
    } else {
      form.append(key, value)
    }
  })

  return form
}

export const api = {
  auth: authApi,

  /** Dashboard: un solo endpoint, il backend decide il contenuto in base al ruolo. */
  dashboard: {
    get: () => http.get('/dashboard'),
  },

  users: {
    list: (params) => http.get('/users', { params: clean(params) }),
    get: (id) => http.get(`/users/${id}`),
    create: (payload) => http.post('/users', payload),
    update: (id, payload) => http.put(`/users/${id}`, payload),
    remove: (id) => http.delete(`/users/${id}`),
  },

  patients: {
    list: (params) => http.get('/patients', { params: clean(params) }),
    get: (id) => http.get(`/patients/${id}`),
    me: () => http.get('/patients/me'),
    create: (payload) => http.post('/patients', payload),
    update: (id, payload) => http.put(`/patients/${id}`, payload),
    remove: (id) => http.delete(`/patients/${id}`),
    timeline: (id) => http.get(`/patients/${id}/timeline`),
  },

  doctors: {
    list: (params) => http.get('/doctors', { params: clean(params) }),
    get: (id) => http.get(`/doctors/${id}`),
    create: (payload) => http.post('/doctors', payload),
    update: (id, payload) => http.put(`/doctors/${id}`, payload),
    remove: (id) => http.delete(`/doctors/${id}`),
    specializations: () => http.get('/doctors/specializations'),
    /** Slot prenotabili: date in formato YYYY-MM-DD */
    availability: (id, date) => http.get(`/doctors/${id}/availability`, { params: { date } }),
  },

  appointments: {
    list: (params) => http.get('/appointments', { params: clean(params) }),
    get: (id) => http.get(`/appointments/${id}`),
    create: (payload) => http.post('/appointments', payload),
    update: (id, payload) => http.put(`/appointments/${id}`, payload),
    cancel: (id, reason) => http.post(`/appointments/${id}/cancel`, { reason }),
    remove: (id) => http.delete(`/appointments/${id}`),
  },

  records: {
    list: (params) => http.get('/medical-records', { params: clean(params) }),
    get: (id) => http.get(`/medical-records/${id}`),
    create: (payload) => http.post('/medical-records', payload),
    update: (id, payload) => http.put(`/medical-records/${id}`, payload),
    remove: (id) => http.delete(`/medical-records/${id}`),
  },

  documents: {
    list: (params) => http.get('/documents', { params: clean(params) }),
    get: (id) => http.get(`/documents/${id}`),
    counters: () => http.get('/documents/counters'),
    upload: (payload, onProgress) =>
      http.post('/documents', toFormData(payload), {
        onUploadProgress: (event) => {
          if (onProgress && event.total) {
            onProgress(Math.round((event.loaded * 100) / event.total))
          }
        },
      }),
    sign: (id, signatureType = 'FEQ') =>
      http.post(`/documents/${id}/sign`, { signature_type: signatureType }),
    signBulk: (ids, signatureType = 'FEQ') =>
      http.post('/documents/sign-bulk', { ids, signature_type: signatureType }),
    archive: (id) => http.post(`/documents/${id}/archive`),
    remove: (id) => http.delete(`/documents/${id}`),
    /** Download come blob: il file non e' pubblico, serve il token */
    download: (id) => http.get(`/documents/${id}/download`, { responseType: 'blob' }),
  },

  prescriptions: {
    list: (params) => http.get('/prescriptions', { params: clean(params) }),
    get: (id) => http.get(`/prescriptions/${id}`),
    create: (payload) => http.post('/prescriptions', payload),
    update: (id, payload) => http.put(`/prescriptions/${id}`, payload),
    remove: (id) => http.delete(`/prescriptions/${id}`),
  },

  medicines: {
    list: (params) => http.get('/medicines', { params: clean(params) }),
    get: (id) => http.get(`/medicines/${id}`),
    create: (payload) => http.post('/medicines', payload),
    update: (id, payload) => http.put(`/medicines/${id}`, payload),
    remove: (id) => http.delete(`/medicines/${id}`),
    summary: () => http.get('/medicines/summary'),
    movements: (id) => http.get(`/medicines/${id}/movements`),
    move: (id, payload) => http.post(`/medicines/${id}/movements`, payload),
  },

  requests: {
    list: (params) => http.get('/requests', { params: clean(params) }),
    get: (id) => http.get(`/requests/${id}`),
    create: (payload) => http.post('/requests', toFormData(payload)),
    update: (id, payload) => http.put(`/requests/${id}`, payload),
    remove: (id) => http.delete(`/requests/${id}`),
    claim: (id) => http.post(`/requests/${id}/claim`),
    respond: (id, payload) => http.post(`/requests/${id}/respond`, payload),
    convertToAppointment: (id, payload) => http.post(`/requests/${id}/convert-appointment`, payload),
    /** Allegato protetto da token: si scarica come blob, non con un <a href>. */
    downloadAttachment: (attachmentId) =>
      http.get(`/requests/attachments/${attachmentId}/download`, { responseType: 'blob' }),
  },

  telemedicine: {
    list: (params) => http.get('/telemedicine', { params: clean(params) }),
    get: (id) => http.get(`/telemedicine/${id}`),
    create: (appointmentId) => http.post('/telemedicine', { appointment_id: appointmentId }),
    join: (id) => http.post(`/telemedicine/${id}/join`),
    start: (id) => http.post(`/telemedicine/${id}/start`),
    end: (id, notes) => http.post(`/telemedicine/${id}/end`, { notes }),
    messages: (id) => http.get(`/telemedicine/${id}/messages`),
    sendMessage: (id, body) => http.post(`/telemedicine/${id}/messages`, { body }),
  },

  invoices: {
    list: (params) => http.get('/invoices', { params: clean(params) }),
    get: (id) => http.get(`/invoices/${id}`),
    create: (payload) => http.post('/invoices', payload),
    update: (id, payload) => http.put(`/invoices/${id}`, payload),
    remove: (id) => http.delete(`/invoices/${id}`),
    pay: (id, payload) => http.post(`/invoices/${id}/pay`, payload),
    report: (params) => http.get('/invoices/report', { params: clean(params) }),
  },

  notifications: {
    list: (params) => http.get('/notifications', { params: clean(params) }),
    unreadCount: () => http.get('/notifications/unread-count'),
    markAsRead: (id) => http.post(`/notifications/${id}/read`),
    markAllAsRead: () => http.post('/notifications/read-all'),
    remove: (id) => http.delete(`/notifications/${id}`),
  },
}

export default api
