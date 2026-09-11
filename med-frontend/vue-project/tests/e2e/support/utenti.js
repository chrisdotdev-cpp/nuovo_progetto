import { expect } from '@playwright/test'

/**
 * Account e percorsi condivisi dai test E2E.
 *
 * Le credenziali sono quelle create da `php artisan db:seed` (DatabaseSeeder)
 * oppure da `php artisan users:demo`. Se cambiano li', vanno cambiate qui:
 * e' l'unico punto di duplicazione fra backend e suite E2E, ed e' voluto
 * (i test devono poter fallire se il seed smette di produrre questi account).
 */
export const UTENTI = {
  paziente: {
    email: 'paziente@clinica.it',
    password: 'password',
    ruolo: 'paziente',
    nome: 'Marco Rossi',
    home: '/paziente/panoramica',
    titolo: 'Panoramica Paziente',
  },
  medico: {
    email: 'medico@clinica.it',
    password: 'password',
    ruolo: 'medico',
    nome: 'Marco Ferrari',
    home: '/medico/panoramica',
    titolo: 'Panoramica Medico',
  },
  admin: {
    email: 'admin@clinica.it',
    password: 'password',
    ruolo: 'admin',
    nome: 'Laura Bianchi',
    home: '/admin/panoramica',
    titolo: 'Dashboard Amministrativa',
  },
}

/**
 * Login passando dall'interfaccia, come farebbe una persona.
 *
 * Non si scrive il token in localStorage a mano: sarebbe piu' veloce ma
 * salterebbe esattamente la parte che questi test devono coprire (form,
 * chiamata reale, redirect deciso dal backend, guard del router).
 */
export async function login(page, chiave, { attesa = true } = {}) {
  const utente = UTENTI[chiave]

  await page.goto(`/login/${utente.ruolo}`)

  await page.getByLabel('Email').fill(utente.email)
  await page.getByLabel('Password').fill(utente.password)
  await page.getByRole('button', { name: /accedi/i }).click()

  if (attesa) {
    await expect(page).toHaveURL(new RegExp(`${utente.home}$`))
    // La dashboard ha finito di caricare quando il titolo di sezione e' a video
    await expect(page.getByRole('heading', { name: utente.titolo })).toBeVisible()
  }

  return utente
}

/** Logout dal pulsante "Esci" nella sidebar (su mobile va prima aperta). */
export async function logout(page) {
  await apriSidebarSeMobile(page)
  await page.getByRole('button', { name: /esci/i }).click()
  await expect(page).toHaveURL(/\/$/)
}

/**
 * Voce di menu della sidebar, e nient'altro.
 *
 * `page.getByRole('link', { name: 'Utenti' })` pescava due nodi - la voce di
 * menu e la scorciatoia "Gestisci utenti" nel riquadro Azioni Rapide - e
 * Playwright falliva in strict mode. Restringere il campo all'<aside> dice
 * anche meglio cosa si sta provando: la navigazione laterale.
 */
export function linkSidebar(page, etichetta) {
  /*
    Niente `exact: true`: la voce "Notifiche" porta il badge dei non letti, che
    entra nel nome accessibile ("Notifiche 3"). Si ancora il testo e si tollera
    il numero in coda.
  */
  const testo = etichetta.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const nome = new RegExp(`^\\s*${testo}\\s*\\d*\\s*$`, 'i')

  return page.locator('aside').getByRole('link', { name: nome })
}

/** Sotto i 901px la sidebar e' fuori schermo: serve l'hamburger. */
export async function apriSidebarSeMobile(page) {
  const hamburger = page.getByRole('button', { name: /apri o chiudi il menu/i })

  if (await hamburger.isVisible()) {
    const sidebarGiaAperta = await hamburger.getAttribute('aria-expanded')
    if (sidebarGiaAperta !== 'true') await hamburger.click()
  }
}

/** Data futura in formato input date (YYYY-MM-DD), evitando il weekend. */
export function prossimoGiornoFeriale(traGiorni = 2) {
  const data = new Date()
  data.setDate(data.getDate() + traGiorni)

  // I medici del seed ricevono da lunedi a venerdi
  while (data.getDay() === 0 || data.getDay() === 6) {
    data.setDate(data.getDate() + 1)
  }

  const mese = String(data.getMonth() + 1).padStart(2, '0')
  const giorno = String(data.getDate()).padStart(2, '0')

  return `${data.getFullYear()}-${mese}-${giorno}`
}

/**
 * Raccoglie gli errori di console e le richieste fallite di una pagina.
 * Un 500 silenzioso o un "Cannot read property of undefined" non rompono il
 * test da soli, ma sono esattamente cio' che precede una schermata vuota.
 */
export function osservaErrori(page) {
  const console_ = []
  const rete = []

  page.on('console', (msg) => {
    if (msg.type() === 'error') console_.push(msg.text())
  })

  page.on('pageerror', (err) => console_.push(`[pageerror] ${err.message}`))

  page.on('response', (res) => {
    if (res.status() >= 500) rete.push(`${res.status()} ${res.url()}`)
  })

  return { console_, rete }
}
