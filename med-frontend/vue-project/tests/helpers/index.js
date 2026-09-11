import { nextTick } from 'vue'

/**
 * Utilita' condivise dai test.
 *
 * Il punto piu' delicato e' la FORMA delle risposte e degli errori: l'intera
 * applicazione passa da useApiRequest().run(), che distingue una risposta axios
 * da un valore gia' pronto guardando le chiavi data/status/config (unwrap()).
 * Un mock che restituisce solo { data } verrebbe trattato come payload grezzo e
 * i test fallirebbero per un motivo che non c'entra nulla con il codice provato.
 */

/** Risposta axios completa: e' cio' che unwrap() si aspetta di ricevere. */
export function axiosOk(data = {}, status = 200) {
  return {
    data,
    status,
    statusText: 'OK',
    headers: {},
    config: { url: '/test', headers: {} },
  }
}

/**
 * Errore gia' normalizzato dall'interceptor di services/http.js.
 * I componenti non vedono mai l'errore axios grezzo: vedono questo.
 */
export function apiError({ status = 422, message = 'I dati inviati non sono validi.', errors = {} } = {}) {
  const first = Object.values(errors)[0]

  return {
    status,
    message,
    errors,
    firstError: Array.isArray(first) && first.length ? first[0] : message,
    raw: new Error(message),
  }
}

/** Scorciatoia per il caso piu' frequente: 422 su un singolo campo. */
export function validationError(field, message) {
  return apiError({ status: 422, errors: { [field]: [message] } })
}

/** Lascia sfogare la coda di microtask + il ciclo di render di Vue. */
export async function flush(cicli = 3) {
  for (let i = 0; i < cicli; i++) {
    await Promise.resolve()
    await nextTick()
  }
}

/* -------------------------------------------------------------------------
 | Dati di esempio coerenti con le Resource di Laravel
 * ---------------------------------------------------------------------- */

export function utente(overrides = {}) {
  return {
    id: 1,
    name: 'Mario Rossi',
    email: 'mario.rossi@example.it',
    role: 'paziente',
    status: 'attivo',
    phone: '3331122334',
    ...overrides,
  }
}

export function medico(overrides = {}) {
  return {
    id: 7,
    user_id: 70,
    name: 'Dott.ssa Anna Bianchi',
    specialization: 'Cardiologia',
    consultation_fee: 80,
    slot_duration: 30,
    available_online: true,
    ...overrides,
  }
}

export function appuntamento(overrides = {}) {
  return {
    id: 100,
    patient_id: 1,
    doctor_id: 7,
    scheduled_at: '2030-09-10T10:00:00+02:00',
    date: '10/09/2030',
    time: '10:00',
    duration_minutes: 30,
    type: 'visita',
    status: 'in_attesa',
    reason: 'Controllo',
    is_editable: true,
    doctor: medico(),
    ...overrides,
  }
}

/** Pagina Laravel: { data: [...], meta: {...} } */
export function paginata(data = [], meta = {}) {
  return {
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: data.length,
      ...meta,
    },
  }
}
