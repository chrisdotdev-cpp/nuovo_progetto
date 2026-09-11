import { request } from '@playwright/test'
import { UTENTI } from './utenti.js'

const BACKEND = process.env.E2E_API_URL || 'http://localhost:8000'

/**
 * Verifica le precondizioni PRIMA di aprire il browser.
 *
 * Senza questo controllo un database non seminato produce venti test rossi con
 * "timeout waiting for URL", che non dice nulla. Qui il messaggio spiega cosa
 * manca e quale comando lo risolve.
 */
export default async function globalSetup() {
  const api = await request.newContext({ baseURL: BACKEND })

  /* 1. Il backend risponde? */
  try {
    const salute = await api.get('/up')
    if (!salute.ok()) throw new Error(`stato ${salute.status()}`)
  } catch (errore) {
    throw new Error(
      `\n\nBackend non raggiungibile su ${BACKEND} (${errore.message}).\n` +
        `Avvialo con:  cd med-backend && php artisan serve --port=8000\n`,
    )
  }

  /* 2. Gli account demo esistono e la password e' quella attesa? */
  for (const [chiave, utente] of Object.entries(UTENTI)) {
    const risposta = await api.post('/api/v1/auth/login', {
      data: { email: utente.email, password: utente.password, device_name: 'e2e-setup' },
      headers: { Accept: 'application/json' },
    })

    if (!risposta.ok()) {
      throw new Error(
        `\n\nL'account E2E "${chiave}" (${utente.email}) non riesce ad autenticarsi ` +
          `(HTTP ${risposta.status()}).\n` +
          `Semina il database di test con:\n` +
          `  cd med-backend && php artisan migrate:fresh --seed\n` +
          `oppure ripristina solo gli account:\n` +
          `  php artisan users:demo --reset\n`,
      )
    }

    const corpo = await risposta.json()

    if (corpo.user?.role !== utente.ruolo) {
      throw new Error(
        `\n\nL'account ${utente.email} ha ruolo "${corpo.user?.role}" invece di "${utente.ruolo}".\n` +
          `Il seed e' disallineato con tests/e2e/support/utenti.js.\n`,
      )
    }

    /* 3. Il medico deve avere un'agenda, altrimenti nessuno slot e' prenotabile */
    if (chiave === 'medico' && !corpo.user?.doctor) {
      throw new Error(
        `\n\nL'account medico non ha un profilo in \`doctors\`: senza, l'agenda e' vuota ` +
          `e il test di prenotazione non puo' funzionare.\n` +
          `  cd med-backend && php artisan users:demo\n`,
      )
    }

    // Si chiude subito la sessione di verifica: non deve restare un token vagante
    await api.post('/api/v1/auth/logout', {
      headers: { Authorization: `Bearer ${corpo.token}`, Accept: 'application/json' },
    })
  }

  await api.dispose()
}
