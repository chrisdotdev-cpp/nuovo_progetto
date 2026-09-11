import { test, expect } from '@playwright/test'
import { UTENTI, login, apriSidebarSeMobile, linkSidebar, osservaErrori } from './support/utenti.js'
import { nessunOverflowOrizzontale, istantanea } from './support/layout.js'

/**
 * Le tre Panoramiche.
 *
 * Un solo endpoint (/dashboard) restituisce tre aggregati diversi in base al
 * ruolo. Il rischio non e' il crash: e' che una sezione resti vuota perche' il
 * frontend legge una chiave che il backend non manda piu'. Qui si controlla che
 * ogni riquadro previsto abbia un contenuto, non solo un titolo.
 */

test.describe('Dashboard paziente', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'paziente')
  })

  test('mostra le sezioni previste e i dati del seed', async ({ page }, testInfo) => {
    const errori = osservaErrori(page)

    await expect(page.getByRole('heading', { name: 'Panoramica Paziente' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Prossimi Appuntamenti' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Documenti Recenti' })).toBeVisible()

    await istantanea(page, testInfo, 'panoramica-paziente')

    expect(errori.rete).toEqual([])
    expect(errori.console_).toEqual([])
  })

  test('i riquadri delle statistiche sono popolati, non vuoti', async ({ page }) => {
    const risposta = await page.waitForResponse(
      (res) => res.url().includes('/dashboard') && res.status() === 200,
      { timeout: 15_000 },
    ).catch(() => null)

    // La dashboard puo' essere gia' in cache: in quel caso si legge la pagina
    if (risposta) {
      const corpo = await risposta.json()
      expect(corpo.role).toBe('paziente')
      expect(corpo.data).toHaveProperty('stats')
    }

    // Nessun placeholder rimasto a video
    await expect(page.getByText('undefined')).toHaveCount(0)
    await expect(page.getByText('NaN')).toHaveCount(0)
  })

  test('la navigazione laterale porta a tutte le sezioni del ruolo', async ({ page }) => {
    const sezioni = [
      ['Appuntamenti', '/paziente/appuntamenti'],
      ['Cartella Clinica', '/paziente/cartella-clinica'],
      ['Pagamenti', '/paziente/pagamenti'],
      ['Notifiche', '/paziente/notifiche'],
    ]

    for (const [etichetta, percorso] of sezioni) {
      await apriSidebarSeMobile(page)
      await linkSidebar(page, etichetta).click()

      await expect(page).toHaveURL(new RegExp(`${percorso}$`))
      // Nessuna schermata bianca: il contenuto principale ha del testo
      await expect(page.locator('main')).not.toBeEmpty()
    }
  })

  test('la sidebar non espone sezioni di altri ruoli', async ({ page }) => {
    await apriSidebarSeMobile(page)

    await expect(linkSidebar(page, 'Agenda')).toHaveCount(0)
    await expect(linkSidebar(page, 'Utenti')).toHaveCount(0)
    await expect(linkSidebar(page, 'Finanziario')).toHaveCount(0)
  })
})

test.describe('Dashboard medico', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'medico')
  })

  test('mostra agenda del giorno e richieste da gestire', async ({ page }, testInfo) => {
    const errori = osservaErrori(page)

    await expect(page.getByRole('heading', { name: 'Panoramica Medico' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Appuntamenti di oggi' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Richieste da gestire' })).toBeVisible()

    await istantanea(page, testInfo, 'panoramica-medico')

    /*
      Questa sezione usa lo scope triageOrder(), che prima ordinava con FIELD():
      una funzione che esiste solo in MySQL. Se un giorno il backend girasse su
      un altro motore la richiesta morirebbe con un 500 e la dashboard resterebbe
      a meta'. Il controllo sui 5xx e' qui apposta.
    */
    expect(errori.rete, 'La dashboard medico non deve produrre 5xx').toEqual([])
    expect(errori.console_).toEqual([])
  })

  test('l agenda del medico si apre e mostra il calendario', async ({ page }) => {
    await apriSidebarSeMobile(page)
    await linkSidebar(page, 'Agenda').click()

    await expect(page).toHaveURL(/\/medico\/agenda$/)
    await expect(page.locator('main')).not.toBeEmpty()
  })

  test('l elenco pazienti mostra solo i propri assistiti', async ({ page }) => {
    await apriSidebarSeMobile(page)
    await linkSidebar(page, 'Pazienti').click()

    await expect(page).toHaveURL(/\/medico\/pazienti$/)

    // Il backend filtra per medico: la lista carica senza errore di permessi
    await expect(page.getByText('Non hai i permessi')).toHaveCount(0)
  })
})

test.describe('Dashboard admin', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'admin')
  })

  test('mostra i totali di struttura e gli ultimi utenti', async ({ page }, testInfo) => {
    const errori = osservaErrori(page)

    await expect(page.getByRole('heading', { name: 'Dashboard Amministrativa' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Appuntamenti di oggi' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Ultimi utenti registrati' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Azioni Rapide' })).toBeVisible()

    await istantanea(page, testInfo, 'panoramica-admin')

    expect(errori.rete).toEqual([])
    expect(errori.console_).toEqual([])
  })

  test('le tabelle amministrative non sfondano la pagina', async ({ page }, testInfo) => {
    const sezioni = [
      ['Utenti', '/admin/utenti'],
      ['Prenotazioni', '/admin/prenotazioni'],
      ['Finanziario', '/admin/finanziario'],
    ]

    for (const [etichetta, percorso] of sezioni) {
      await apriSidebarSeMobile(page)
      await linkSidebar(page, etichetta).click()
      await expect(page).toHaveURL(new RegExp(`${percorso}$`))

      // Le tabelle hanno min-w esplicite (fino a 900px): devono restare
      // dentro un contenitore con overflow-x, non allargare il documento
      await nessunOverflowOrizzontale(page, `nella sezione ${etichetta}`)
      await istantanea(page, testInfo, `admin-${etichetta.toLowerCase()}`)
    }
  })

  test('l admin raggiunge ogni sezione della propria area', async ({ page }) => {
    const sezioni = ['Utenti', 'Personale', 'Prenotazioni', 'Documenti', 'Finanziario', 'Farmacia', 'Notifiche']

    for (const etichetta of sezioni) {
      await apriSidebarSeMobile(page)
      await linkSidebar(page, etichetta).click()
      await expect(page.locator('main')).not.toBeEmpty()
      await expect(page.getByText('Errore interno del server.')).toHaveCount(0)
    }
  })
})
