/**
 * Formattazioni condivise (date, orari, valuta, dimensioni).
 *
 * Il backend invia sempre ISO 8601 e numeri grezzi: la localizzazione italiana
 * avviene qui, una volta sola, invece che in ogni componente.
 */
/*
  Fuso della clinica, non quello del dispositivo.

  Senza timeZone esplicito Intl usa il fuso del browser: un medico collegato da
  un'altra nazione (o con l'orologio del PC su un fuso diverso) leggeva orari
  spostati rispetto a quelli mostrati al paziente, che arrivano gia' formattati
  dal backend nei campi `date` e `time`. Fissandolo qui le due strade coincidono
  sempre, e coincidono con APP_TIMEZONE lato Laravel.
*/
const TZ_CLINICA = 'Europe/Rome'

const dateFormatter = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: TZ_CLINICA })
const timeFormatter = new Intl.DateTimeFormat('it-IT', { hour: '2-digit', minute: '2-digit', timeZone: TZ_CLINICA })
const longFormatter = new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: 'numeric', month: 'long', timeZone: TZ_CLINICA })
const currencyFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' })

/** YYYY-MM-DD nel fuso della clinica: en-CA produce gia' l'ordine ISO. */
const inputDateFormatter = new Intl.DateTimeFormat('en-CA', {
  year: 'numeric', month: '2-digit', day: '2-digit', timeZone: TZ_CLINICA,
})

const toDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

export function useFormatters() {
  /** 02/08/2026 */
  const formatDate = (value, fallback = '--/--/----') => {
    const date = toDate(value)
    return date ? dateFormatter.format(date) : fallback
  }

  /** 14:30 */
  const formatTime = (value, fallback = '--:--') => {
    const date = toDate(value)
    return date ? timeFormatter.format(date) : fallback
  }

  /** domenica 2 agosto */
  const formatLongDate = (value, fallback = '') => {
    const date = toDate(value)
    return date ? longFormatter.format(date) : fallback
  }

  /** 02/08/2026 alle 14:30 */
  const formatDateTime = (value, fallback = '--') => {
    const date = toDate(value)
    return date ? `${dateFormatter.format(date)} alle ${timeFormatter.format(date)}` : fallback
  }

  /** 1.250,00 € */
  const formatCurrency = (value, fallback = '0,00 €') => {
    const amount = Number(value)
    return Number.isFinite(amount) ? currencyFormatter.format(amount) : fallback
  }

  /**
   * Formato input date HTML (YYYY-MM-DD).
   *
   * Prima usava toISOString(), che lavora in UTC: alle 00:30 del 5 agosto a Roma
   * restituiva "2026-08-04". Il calendario dell'agenda e il campo data della
   * prenotazione partivano quindi dal giorno sbagliato. Ora la conversione
   * avviene nel fuso della clinica.
   */
  const toInputDate = (value = new Date()) => {
    const date = toDate(value) ?? new Date()
    return inputDateFormatter.format(date)
  }

  /** "tra 3 giorni" / "2 ore fa" senza dipendenze esterne */
  const relativeTime = (value) => {
    const date = toDate(value)
    if (!date) return ''

    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000)
    const rtf = new Intl.RelativeTimeFormat('it-IT', { numeric: 'auto' })

    const units = [
      ['year', 31536000],
      ['month', 2592000],
      ['day', 86400],
      ['hour', 3600],
      ['minute', 60],
    ]

    for (const [unit, seconds] of units) {
      if (Math.abs(diffSeconds) >= seconds) {
        return rtf.format(Math.round(diffSeconds / seconds), unit)
      }
    }

    return 'adesso'
  }

  /** true se la data è nel passato: usato per disabilitare azioni su eventi conclusi */
  const isPast = (value) => {
    const date = toDate(value)
    return date ? date.getTime() < Date.now() : false
  }

  /** Confronto fatto sul giorno della clinica, non su quello del dispositivo. */
  const isToday = (value) => {
    const date = toDate(value)
    if (!date) return false
    return inputDateFormatter.format(date) === inputDateFormatter.format(new Date())
  }

  return {
    formatDate,
    formatTime,
    formatLongDate,
    formatDateTime,
    formatCurrency,
    toInputDate,
    relativeTime,
    isPast,
    isToday,
  }
}
