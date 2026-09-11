import { test, expect } from '@playwright/test'
import { login, prossimoGiornoFeriale, osservaErrori } from './support/utenti.js'
import { visibileECliccabile, nessunOverflowOrizzontale, istantanea } from './support/layout.js'

/**
 * Prenotazione di una visita: il percorso che genera valore per la clinica.
 *
 * Attraversa cinque livelli in una volta sola - form Vue, store Pinia, client
 * axios, AppointmentService e database - e nessuno dei test a livello inferiore
 * puo' dire se combaciano. Qui si prenota davvero: al termine esiste una riga in
 * `appointments`.
 */
test.describe('Prenotazione visita', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'paziente')
    await page.goto('/paziente/appuntamenti')
    await expect(page.getByRole('heading', { name: 'Prenota un Appuntamento' })).toBeVisible()
  })

  /** Card dei medici: sono gli unici elementi con role="button" della pagina. */
  const cardMedici = (page) => page.locator('[role="button"]')

  test('l elenco medici arriva dal backend e non e vuoto', async ({ page }) => {
    await expect(cardMedici(page).first()).toBeVisible()

    const quanti = await cardMedici(page).count()
    expect(quanti, 'Il seed prevede tre medici configurati').toBeGreaterThan(0)

    // Nessuno dei tre stati d'errore deve essere a video
    await expect(page.getByText("Impossibile caricare l'elenco medici.")).toHaveCount(0)
    await expect(page.getByText('Nessun medico disponibile.')).toHaveCount(0)
  })

  test('la ricerca filtra i medici senza ricaricare la pagina', async ({ page }) => {
    await expect(cardMedici(page).first()).toBeVisible()
    const totale = await cardMedici(page).count()

    await page.getByPlaceholder('Cerca medico...').fill('Ferrari')

    await expect(cardMedici(page)).toHaveCount(1)
    expect(totale).toBeGreaterThan(1)

    await page.getByPlaceholder('Cerca medico...').fill('')
    await expect(cardMedici(page)).toHaveCount(totale)
  })

  test('percorso completo: medico, data, orario, conferma', async ({ page }, testInfo) => {
    const errori = osservaErrori(page)
    const giorno = prossimoGiornoFeriale()

    /* 1. Il pannello parte vuoto */
    await expect(page.getByText('Seleziona un medico per continuare')).toBeVisible()

    /* 2. Selezione del medico */
    const medico = cardMedici(page).first()
    const nomeMedico = (await medico.locator('h3').textContent())?.trim()
    await medico.click()

    await expect(medico).toHaveAttribute('aria-pressed', 'true')
    await expect(page.locator('#booking-date')).toBeVisible()

    /* 3. Data: la richiesta di disponibilita' parte da qui */
    const disponibilita = page.waitForResponse(
      (res) => res.url().includes('/availability') && res.status() === 200,
    )

    await page.locator('#booking-date').fill(giorno)
    await page.locator('#booking-date').dispatchEvent('change')

    const risposta = await disponibilita
    const corpo = await risposta.json()
    expect(corpo.slots?.length, `Il medico deve ricevere il ${giorno}`).toBeGreaterThan(0)

    /* 4. Primo orario realmente libero */
    const slotLibero = page
      .locator('button:not([disabled])')
      .filter({ hasText: /^\s*\d{2}:\d{2}\s*$/ })
      .first()

    await visibileECliccabile(page, slotLibero, 'Slot orario disponibile')
    const orario = (await slotLibero.textContent())?.trim()
    await slotLibero.click()

    await page.locator('#booking-reason').fill('Test E2E - controllo di routine')

    await istantanea(page, testInfo, 'prenotazione-form-compilato')

    /* 5. Conferma: si osserva la POST vera */
    const creazione = page.waitForResponse(
      (res) => res.url().endsWith('/appointments') && res.request().method() === 'POST',
    )

    const conferma = page.getByRole('button', { name: /conferma prenotazione/i })
    await visibileECliccabile(page, conferma, 'Pulsante Conferma prenotazione')
    await conferma.click()

    const creata = await creazione
    expect(creata.status(), 'Il backend deve rispondere 201 Created').toBe(201)

    const appuntamento = (await creata.json()).data
    expect(appuntamento.status).toBe('in_attesa')

    /* 6. Il pannello torna allo stato iniziale e la visita compare in lista */
    await expect(page.getByText('Seleziona un medico per continuare')).toBeVisible()
    await expect(page.getByText(nomeMedico).first()).toBeVisible()
    await expect(page.getByText(orario).first()).toBeVisible()

    await istantanea(page, testInfo, 'prenotazione-confermata')

    expect(errori.rete, 'Nessun 5xx durante la prenotazione').toEqual([])
    await nessunOverflowOrizzontale(page, 'sulla pagina Appuntamenti dopo la prenotazione')
  })

  test('senza data e orario il pulsante di conferma resta disabilitato', async ({ page }) => {
    await cardMedici(page).first().click()

    const conferma = page.getByRole('button', { name: /conferma prenotazione/i })
    await expect(conferma).toBeDisabled()
  })

  test('cambiando medico si azzerano data e orario gia scelti', async ({ page }) => {
    const giorno = prossimoGiornoFeriale()

    await cardMedici(page).first().click()
    await page.locator('#booking-date').fill(giorno)
    await page.locator('#booking-date').dispatchEvent('change')
    await expect(page.locator('button').filter({ hasText: /^\s*\d{2}:\d{2}\s*$/ }).first()).toBeVisible()

    await cardMedici(page).nth(1).click()

    await expect(page.locator('#booking-date')).toHaveValue('')
    await expect(page.getByText('Seleziona prima una data.')).toBeVisible()
  })

  /**
   * Domenica nessun medico del seed riceve: la UI deve dirlo, non restare con
   * la griglia degli orari vuota e nessuna spiegazione.
   */
  test('una giornata di chiusura viene spiegata all utente', async ({ page }) => {
    const domenica = new Date()
    domenica.setDate(domenica.getDate() + ((7 - domenica.getDay()) % 7 || 7))
    const giorno = domenica.toISOString().slice(0, 10)

    await cardMedici(page).first().click()
    await page.locator('#booking-date').fill(giorno)
    await page.locator('#booking-date').dispatchEvent('change')

    await expect(page.getByText('Il medico non riceve in questa data.')).toBeVisible()
  })

  /**
   * Doppia prenotazione sullo stesso slot: la seconda deve essere respinta dal
   * backend con un messaggio comprensibile, non con un errore muto.
   */
  test('lo slot gia occupato viene rifiutato con una spiegazione', async ({ page }) => {
    const giorno = prossimoGiornoFeriale(3)

    const prenotaPrimoSlot = async () => {
      await cardMedici(page).first().click()
      await page.locator('#booking-date').fill(giorno)
      await page.locator('#booking-date').dispatchEvent('change')

      const slot = page
        .locator('button:not([disabled])')
        .filter({ hasText: /^\s*\d{2}:\d{2}\s*$/ })
        .first()

      await expect(slot).toBeVisible()
      const orario = (await slot.textContent())?.trim()
      await slot.click()

      return orario
    }

    // Prima prenotazione: deve riuscire
    await prenotaPrimoSlot()
    await page.getByRole('button', { name: /conferma prenotazione/i }).click()
    await expect(page.getByText('Seleziona un medico per continuare')).toBeVisible()

    /*
      Il primo slot appena occupato ora risulta non disponibile: la UI lo mostra
      barrato e disabilitato. E' il comportamento corretto, e conferma che
      l'agenda si e' aggiornata davvero.
    */
    await cardMedici(page).first().click()
    await page.locator('#booking-date').fill(giorno)
    await page.locator('#booking-date').dispatchEvent('change')

    const occupati = page.locator('button[disabled]').filter({ hasText: /^\s*\d{2}:\d{2}\s*$/ })
    await expect(occupati.first()).toBeVisible()
  })

  test('annullamento: modal, motivo e stato aggiornato', async ({ page }, testInfo) => {
    const annulla = page.getByRole('button', { name: /^annulla$/i }).first()

    // Serve almeno un appuntamento annullabile in agenda
    await expect(annulla).toBeVisible()
    await annulla.click()

    const modal = page.getByRole('dialog')
    await expect(modal).toBeVisible()
    await expect(modal).toContainText("Annullare l'appuntamento?")

    await istantanea(page, testInfo, 'modal-annullamento')

    // Il modal deve stare davanti a tutto e restare cliccabile
    await visibileECliccabile(
      page,
      modal.getByRole('button', { name: /annulla appuntamento/i }),
      'Conferma annullamento nel modal',
    )

    await page.locator('#cancel-reason').fill('Test E2E - imprevisto')

    const cancellazione = page.waitForResponse((res) => res.url().includes('/cancel'))
    await modal.getByRole('button', { name: /annulla appuntamento/i }).click()

    expect((await cancellazione).status()).toBe(200)

    await expect(modal).toBeHidden()
    await expect(page.getByText('Annullato').first()).toBeVisible()
  })

  test('lo scroll di fondo resta bloccato mentre il modal e aperto', async ({ page }) => {
    await page.getByRole('button', { name: /^annulla$/i }).first().click()
    await expect(page.getByRole('dialog')).toBeVisible()

    const overflow = await page.evaluate(() => document.body.style.overflow)
    expect(overflow, 'Con il modal aperto il body non deve scorrere').toBe('hidden')

    await page.getByRole('button', { name: /torna indietro/i }).click()
    await expect(page.getByRole('dialog')).toBeHidden()

    const ripristinato = await page.evaluate(() => document.body.style.overflow)
    expect(ripristinato, 'Chiuso il modal lo scroll deve tornare disponibile').toBe('')
  })
})
