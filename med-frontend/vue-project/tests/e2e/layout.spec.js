import { test, expect } from '@playwright/test'
import { UTENTI, login, apriSidebarSeMobile, linkSidebar } from './support/utenti.js'
import {
  nessunOverflowOrizzontale,
  visibileECliccabile,
  bersagliTouchAdeguati,
  testoNonTroncatoSenzaEllissi,
  istantanea,
} from './support/layout.js'

/**
 * Verifica del layout misurata nel browser.
 *
 * Uno screenshot mostra un difetto solo a chi lo guarda; questi test lo
 * misurano e falliscono da soli, indicando l'elemento colpevole. Gli screenshot
 * restano allegati al report per capire il perche'.
 *
 * Il progetto e' mobile-first (touch target 44px, sidebar a scomparsa sotto i
 * 901px): la configurazione lancia questo file sia a 1440px sia su Pixel 7.
 */

const PAGINE_PUBBLICHE = [
  ['home', '/'],
  ['login paziente', '/login/paziente'],
  ['login medico', '/login/medico'],
  ['login admin', '/login/admin'],
]

test.describe('Layout - pagine pubbliche', () => {
  for (const [nome, percorso] of PAGINE_PUBBLICHE) {
    test(`${nome}: nessun overflow orizzontale`, async ({ page }, testInfo) => {
      await page.goto(percorso)
      await page.waitForLoadState('networkidle')

      await nessunOverflowOrizzontale(page, `sulla pagina ${nome}`)
      await istantanea(page, testInfo, `layout-${nome.replace(/\s+/g, '-')}`)
    })
  }

  test('il form di login resta utilizzabile senza scorrere lateralmente', async ({ page }) => {
    await page.goto('/login/paziente')

    await visibileECliccabile(page, page.getByLabel('Email'), 'Campo Email')
    await visibileECliccabile(page, page.getByLabel('Password'), 'Campo Password')
    await visibileECliccabile(page, page.getByRole('button', { name: /accedi/i }), 'Pulsante Accedi')
  })

  test('i bersagli touch rispettano il minimo dichiarato', async ({ page }, testInfo) => {
    await page.goto('/login/paziente')

    const piccoli = await bersagliTouchAdeguati(page, { minimo: 40 })

    /*
      Non e' un fallimento automatico: il link "Password dimenticata?" e' testo
      inline e sotto soglia per natura. Si allega l'elenco al report perche'
      resti visibile, e si fallisce solo se un pulsante vero e' troppo basso.
    */
    await testInfo.attach('bersagli-sotto-soglia', {
      body: JSON.stringify(piccoli, null, 2),
      contentType: 'application/json',
    })

    const pulsantiTroppoBassi = piccoli.filter((el) => el.tag === 'button' || el.tag === 'input')
    expect(pulsantiTroppoBassi, 'Pulsanti e campi sotto i 40px di altezza').toEqual([])
  })
})

test.describe('Layout - area riservata', () => {
  for (const chiave of ['paziente', 'medico', 'admin']) {
    test(`dashboard ${chiave}: struttura e contenuto entro il viewport`, async ({ page }, testInfo) => {
      await login(page, chiave)
      await page.waitForLoadState('networkidle')

      await nessunOverflowOrizzontale(page, `sulla dashboard ${chiave}`)

      // Il titolo di sezione non deve finire sotto la navbar sticky
      const titolo = page.getByRole('heading', { name: UTENTI[chiave].titolo })
      await visibileECliccabile(page, titolo, `Titolo dashboard ${chiave}`)

      const troncati = await testoNonTroncatoSenzaEllissi(page)
      await testInfo.attach(`testo-troncato-${chiave}`, {
        body: JSON.stringify(troncati, null, 2),
        contentType: 'application/json',
      })

      await istantanea(page, testInfo, `layout-dashboard-${chiave}`)
    })
  }

  /**
   * Su mobile la sidebar e' `fixed ... z-40`, l'overlay `z-30` e l'hamburger
   * `z-50`. Bastano tre valori sbagliati perche' il menu resti sotto il
   * contenuto o l'overlay copra i link: qui si verifica l'ordine reale
   * chiedendo al browser chi c'e' sopra a chi.
   */
  test('sidebar mobile: si apre sopra il contenuto e i link sono cliccabili', async ({ page }, testInfo) => {
    await login(page, 'paziente')

    const hamburger = page.getByRole('button', { name: /apri o chiudi il menu/i })

    test.skip(!(await hamburger.isVisible()), 'Solo su viewport mobile (< 901px)')

    await visibileECliccabile(page, hamburger, 'Pulsante hamburger')
    await hamburger.click()

    await expect(hamburger).toHaveAttribute('aria-expanded', 'true')

    const primoLink = linkSidebar(page, 'Appuntamenti')
    await visibileECliccabile(page, primoLink, 'Link Appuntamenti nella sidebar aperta')

    const esci = page.getByRole('button', { name: /esci/i })
    await visibileECliccabile(page, esci, 'Pulsante Esci in fondo alla sidebar')

    await istantanea(page, testInfo, 'layout-sidebar-mobile-aperta')

    // L'overlay chiude il menu senza lasciare il body bloccato
    await page.locator('.fixed.inset-0.bg-black\\/40').click({ position: { x: 300, y: 400 } })
    await expect(hamburger).toHaveAttribute('aria-expanded', 'false')
  })

  /**
   * Il modal di annullamento e' `z-50`, esattamente come l'hamburger fisso.
   * Con lo stesso livello vince l'ultimo nel DOM: funziona, ma e' fragile.
   * Questo test lo verifica sul campo invece di fidarsi dell'ordine dei nodi.
   */
  test('il modal si sovrappone a hamburger e navbar', async ({ page }, testInfo) => {
    await login(page, 'paziente')
    await page.goto('/paziente/appuntamenti')

    const annulla = page.getByRole('button', { name: /^annulla$/i }).first()
    test.skip(!(await annulla.isVisible()), 'Nessun appuntamento annullabile in agenda')

    await annulla.click()

    const modal = page.getByRole('dialog')
    await expect(modal).toBeVisible()

    await visibileECliccabile(
      page,
      modal.getByRole('button', { name: /torna indietro/i }),
      'Pulsante del modal (non deve essere coperto da navbar o hamburger)',
    )

    await nessunOverflowOrizzontale(page, 'con il modal di annullamento aperto')
    await istantanea(page, testInfo, 'layout-modal-annullamento')
  })

  /**
   * Le tabelle admin dichiarano min-w fino a 900px. Devono scorrere dentro il
   * proprio contenitore, non trascinarsi dietro l'intera pagina: e' la
   * differenza fra una tabella navigabile e un sito che balla in orizzontale.
   */
  test('le tabelle admin scorrono nel contenitore, non nella pagina', async ({ page }, testInfo) => {
    await login(page, 'admin')

    for (const [etichetta, percorso] of [
      ['Utenti', '/admin/utenti'],
      ['Prenotazioni', '/admin/prenotazioni'],
      ['Finanziario', '/admin/finanziario'],
    ]) {
      await page.goto(percorso)
      await page.waitForLoadState('networkidle')

      await nessunOverflowOrizzontale(page, `nella sezione admin ${etichetta}`)

      const contenitoreScorre = await page.evaluate(() => {
        const tabella = document.querySelector('table')
        if (!tabella) return null

        const contenitore = tabella.parentElement
        return {
          overflowX: getComputedStyle(contenitore).overflowX,
          scorreDavvero: contenitore.scrollWidth > contenitore.clientWidth,
        }
      })

      if (contenitoreScorre) {
        expect(
          ['auto', 'scroll'].includes(contenitoreScorre.overflowX),
          `La tabella di ${etichetta} deve stare in un contenitore con overflow-x`,
        ).toBe(true)
      }

      await istantanea(page, testInfo, `layout-admin-${etichetta.toLowerCase()}`)
    }
  })

  /**
   * DefaultLayout usa `h-screen`. In headless l'altezza del viewport coincide
   * sempre con 100vh, quindi il difetto noto delle barre dinamiche del browser
   * mobile non e' riproducibile qui: questo test documenta la misura attesa e
   * fallisce se il contenuto principale smette di essere scrollabile
   * autonomamente (regressione strutturale del layout).
   */
  test('il contenuto principale scorre da solo sotto la navbar', async ({ page }) => {
    await login(page, 'paziente')

    const misure = await page.evaluate(() => {
      const main = document.querySelector('main')
      if (!main) return null

      return {
        overflowY: getComputedStyle(main).overflowY,
        altezzaVisibile: main.clientHeight,
        altezzaViewport: window.innerHeight,
      }
    })

    expect(misure, 'Il layout deve avere un <main>').not.toBeNull()
    expect(
      ['auto', 'scroll'].includes(misure.overflowY),
      'Il <main> deve scorrere da solo, altrimenti navbar e sidebar scompaiono scorrendo',
    ).toBe(true)

    expect(
      misure.altezzaVisibile,
      'Il contenuto principale non deve superare il viewport',
    ).toBeLessThanOrEqual(misure.altezzaViewport)
  })
})
