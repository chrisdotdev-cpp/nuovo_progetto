import { test, expect } from '@playwright/test'
import { UTENTI, login, logout, apriSidebarSeMobile, osservaErrori } from './support/utenti.js'
import { visibileECliccabile, istantanea } from './support/layout.js'

/**
 * Autenticazione end-to-end contro il backend reale.
 *
 * Nessun mock: il token e' un token Sanctum vero, il redirect lo decide
 * AuthController::dashboardFor() e la guard del router lo rispetta. E' l'unico
 * livello in cui si vede se le tre cose sono d'accordo fra loro.
 */
test.describe('Accesso e sessione', () => {
  test('la home propone i tre ruoli e porta al form corrispondente', async ({ page }) => {
    await page.goto('/')

    await expect(page.getByText('Paziente')).toBeVisible()
    await expect(page.getByText('Medico')).toBeVisible()
    await expect(page.getByText('Amministrazione')).toBeVisible()

    await page.getByText('Medico').first().click()

    await expect(page).toHaveURL(/\/login\/medico$/)
    await expect(page.getByRole('heading', { name: 'Accedi come Medico' })).toBeVisible()
  })

  for (const chiave of ['paziente', 'medico', 'admin']) {
    const utente = UTENTI[chiave]

    test(`login ${chiave}: atterra sulla propria dashboard`, async ({ page }, testInfo) => {
      const errori = osservaErrori(page)

      await login(page, chiave)

      await expect(page).toHaveURL(new RegExp(`${utente.home}$`))
      await expect(page.getByRole('heading', { name: utente.titolo })).toBeVisible()

      // Il nome nella sidebar conferma che /auth/me e' andato a buon fine
      await apriSidebarSeMobile(page)
      await expect(page.getByText(utente.nome).first()).toBeVisible()

      await istantanea(page, testInfo, `dashboard-${chiave}`)

      expect(errori.rete, `Errori 5xx durante il login ${chiave}`).toEqual([])
      expect(errori.console_, `Errori di console durante il login ${chiave}`).toEqual([])
    })
  }

  test('credenziali errate: messaggio a video, nessuna navigazione', async ({ page }, testInfo) => {
    await page.goto('/login/paziente')

    await page.getByLabel('Email').fill(UTENTI.paziente.email)
    await page.getByLabel('Password').fill('password-sbagliata')
    await page.getByRole('button', { name: /accedi/i }).click()

    const avviso = page.getByRole('alert')
    await expect(avviso).toBeVisible()
    await expect(avviso).toContainText(/credenziali non valide/i)

    await expect(page).toHaveURL(/\/login\/paziente$/)

    // Il riquadro d'errore non deve spingere il form fuori dallo schermo
    await visibileECliccabile(page, page.getByRole('button', { name: /accedi/i }), 'Pulsante Accedi dopo errore')
    await istantanea(page, testInfo, 'login-credenziali-errate')
  })

  test('il ruolo sbagliato nella schermata di login viene rifiutato', async ({ page }) => {
    // Il paziente prova a entrare dalla porta dell'amministrazione
    await page.goto('/login/admin')

    await page.getByLabel('Email').fill(UTENTI.paziente.email)
    await page.getByLabel('Password').fill(UTENTI.paziente.password)
    await page.getByRole('button', { name: /accedi/i }).click()

    await expect(page.getByRole('alert')).toBeVisible()
    await expect(page).toHaveURL(/\/login\/admin$/)
  })

  test('la validazione client blocca il form vuoto senza chiamare il server', async ({ page }) => {
    let chiamate = 0
    page.on('request', (req) => {
      if (req.url().includes('/auth/login')) chiamate++
    })

    await page.goto('/login/paziente')
    await page.getByRole('button', { name: /accedi/i }).click()

    await expect(page.getByText("L'email e' obbligatoria.")).toBeVisible()
    await expect(page.getByText("La password e' obbligatoria.")).toBeVisible()
    expect(chiamate).toBe(0)
  })

  test('la sessione sopravvive al ricaricamento della pagina', async ({ page }) => {
    await login(page, 'paziente')

    await page.reload()

    // Il token e' persistito, l'utente viene riletto da /auth/me
    await expect(page).toHaveURL(new RegExp(`${UTENTI.paziente.home}$`))
    await expect(page.getByRole('heading', { name: UTENTI.paziente.titolo })).toBeVisible()
  })

  test('logout: sessione chiusa e ritorno alla home', async ({ page }) => {
    await login(page, 'paziente')
    await logout(page)

    // Il token non deve sopravvivere in localStorage
    const residuo = await page.evaluate(() => JSON.stringify(window.localStorage))
    expect(residuo).not.toContain('"token":"')

    // E l'area riservata non deve piu' essere raggiungibile
    await page.goto('/paziente/panoramica')
    await expect(page).toHaveURL(/\/\?redirect=/)
  })

  test('il tasto indietro dopo il login non riporta al form', async ({ page }) => {
    /*
      Qui si arriva al form partendo dalla home e cliccando, invece di usare
      login(): il test riguarda la cronologia del browser, quindi la cronologia
      va costruita davvero.

      Con page.goto('/login/paziente') diretto la scheda aveva due sole voci -
      about:blank e il form - e router.replace sostituiva la seconda: goBack()
      usciva dall'applicazione e atterrava su about:blank. Falliva il test, non
      il codice.
    */
    await page.goto('/')
    await page.getByText('Paziente').first().click()
    await expect(page).toHaveURL(/\/login\/paziente$/)

    await page.getByLabel('Email').fill(UTENTI.paziente.email)
    await page.getByLabel('Password').fill(UTENTI.paziente.password)
    await page.getByRole('button', { name: /accedi/i }).click()

    await expect(page).toHaveURL(new RegExp(`${UTENTI.paziente.home}$`))

    await page.goBack()

    /*
      Il login usa router.replace, quindi indietro salta il form e torna alla
      home; la guard rimanda comunque sulla dashboard chi e' gia' autenticato.
      In nessuno dei due casi si deve rivedere il modulo di accesso.
    */
    await expect(page).toHaveURL(new RegExp(`(${UTENTI.paziente.home}|/)$`))
    await expect(page.getByRole('heading', { name: 'Accedi come Paziente' })).toHaveCount(0)
  })
})

/**
 * Guard del router provata dove conta davvero: con URL digitati a mano.
 */
test.describe('Guard di accesso', () => {
  test('un URL protetto senza sessione rimanda alla home conservando la destinazione', async ({ page }) => {
    await page.goto('/paziente/appuntamenti')

    /*
      La barra dentro un query param e' un carattere legale (RFC 3986) e
      vue-router la lascia leggibile: l'URL reale e' `?redirect=/paziente/...`,
      non `%2Fpaziente%2F...`. L'asserzione accetta entrambe le forme, cosi'
      prova quello che deve provare - la destinazione viene conservata - senza
      legarsi a un dettaglio di codifica che il router puo' cambiare.
    */
    await expect(page).toHaveURL(/\/\?redirect=(%2F|\/)paziente(%2F|\/)appuntamenti/)
    await expect(page.getByText('Paziente')).toBeVisible()
  })

  test('dopo il login si torna alla pagina che si stava cercando di aprire', async ({ page }) => {
    await page.goto('/paziente/cartella-clinica')
    await expect(page).toHaveURL(/redirect=/)

    await page.getByText('Paziente').first().click()
    await page.getByLabel('Email').fill(UTENTI.paziente.email)
    await page.getByLabel('Password').fill(UTENTI.paziente.password)
    await page.getByRole('button', { name: /accedi/i }).click()

    await expect(page).toHaveURL(/\/paziente\/cartella-clinica$/)
  })

  test('il paziente non entra nell area medico', async ({ page }) => {
    await login(page, 'paziente')

    await page.goto('/medico/agenda')

    await expect(page).toHaveURL(new RegExp(`${UTENTI.paziente.home}$`))
    await expect(page.getByRole('heading', { name: UTENTI.paziente.titolo })).toBeVisible()
  })

  test('il medico non entra nell area amministrativa', async ({ page }) => {
    await login(page, 'medico')

    await page.goto('/admin/utenti')

    await expect(page).toHaveURL(new RegExp(`${UTENTI.medico.home}$`))
  })

  test('un utente autenticato che torna sulla home viene riportato alla dashboard', async ({ page }) => {
    await login(page, 'admin')

    await page.goto('/')

    await expect(page).toHaveURL(new RegExp(`${UTENTI.admin.home}$`))
  })

  test('una rotta inesistente riporta alla home senza schermata bianca', async ({ page }) => {
    await page.goto('/questa-rotta-non-esiste')

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByText('Paziente')).toBeVisible()
  })
})
