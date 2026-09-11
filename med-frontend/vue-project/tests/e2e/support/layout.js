import { expect } from '@playwright/test'

/**
 * Controlli di layout misurati nel browser.
 *
 * Uno screenshot dice che qualcosa e' storto solo a chi lo guarda. Queste
 * funzioni trasformano i difetti tipici (overflow orizzontale, elementi
 * tagliati, bersagli troppo piccoli, elementi coperti da un altro) in
 * asserzioni che falliscono da sole, con il selettore del colpevole nel
 * messaggio. Lo screenshot resta, ma serve a capire il perche', non a scoprire il che.
 */

/**
 * Overflow orizzontale: la causa numero uno delle pagine che "ballano" su
 * mobile. Si misura il documento e si risale all'elemento piu' largo del suo
 * contenitore, cosi' il messaggio dice cosa lo provoca.
 */
export async function nessunOverflowOrizzontale(page, contesto = '') {
  const esito = await page.evaluate(() => {
    const doc = document.documentElement
    const scarto = doc.scrollWidth - doc.clientWidth

    if (scarto <= 1) return { overflow: false, scarto: 0, colpevoli: [] }

    const colpevoli = []

    for (const el of document.querySelectorAll('body *')) {
      const box = el.getBoundingClientRect()
      if (box.width === 0 || box.height === 0) continue

      // Sporge oltre il bordo destro del viewport?
      if (box.right > doc.clientWidth + 1) {
        const genitore = el.parentElement
        const stileGenitore = genitore ? getComputedStyle(genitore) : null

        colpevoli.push({
          tag: el.tagName.toLowerCase(),
          classi: (el.className?.toString?.() ?? '').slice(0, 120),
          destra: Math.round(box.right),
          larghezza: Math.round(box.width),
          // Se un antenato ha overflow-x auto/hidden il difetto e' contenuto
          overflowGenitore: stileGenitore?.overflowX ?? 'n/d',
        })
      }
    }

    return {
      overflow: true,
      scarto,
      viewport: doc.clientWidth,
      colpevoli: colpevoli.slice(0, 5),
    }
  })

  expect(
    esito.overflow,
    `Overflow orizzontale di ${esito.scarto}px ${contesto}.\n` +
      `Viewport ${esito.viewport}px. Elementi che sporgono:\n` +
      esito.colpevoli.map((c) => `  <${c.tag} class="${c.classi}"> right=${c.destra}px overflow-x genitore=${c.overflowGenitore}`).join('\n'),
  ).toBe(false)
}

/**
 * Aspetta che l'elemento smetta di spostarsi e restituisce il box definitivo.
 *
 * Playwright fa questa attesa da solo dentro click(), non dentro boundingBox():
 * misurare a mano durante un'animazione produce fallimenti che spariscono al
 * secondo tentativo, i peggiori da diagnosticare.
 */
async function attendiPosizioneStabile(locator, etichetta, { tentativi = 30 } = {}) {
  let precedente = null

  for (let i = 0; i < tentativi; i++) {
    const box = await locator.boundingBox()

    if (box && precedente && box.x === precedente.x && box.y === precedente.y &&
        box.width === precedente.width && box.height === precedente.height) {
      return box
    }

    precedente = box
    await locator.page().waitForTimeout(50)
  }

  expect(
    precedente,
    `${etichetta}: nessun box misurabile (elemento a dimensione zero)`,
  ).not.toBeNull()

  return precedente
}

/**
 * Un elemento deve stare dentro il viewport ed essere effettivamente cliccabile
 * nel suo centro: se un altro nodo lo copre (z-index, overlay rimasto aperto,
 * sidebar che non si e' richiusa) il click andrebbe a vuoto e il test
 * fallirebbe piu' avanti con un errore incomprensibile.
 */
export async function visibileECliccabile(page, locator, etichetta) {
  await expect(locator, `${etichetta}: dovrebbe essere visibile`).toBeVisible()

  /*
    Due precauzioni prima di misurare, entrambe pagate con test rossi:

    1. la sidebar mobile entra con `transition-transform duration-300`. Misurata
       subito dopo il click sull'hamburger si trovava a meta' strada (x=-201 su
       un viewport da 412px) e il test denunciava un difetto di layout che non
       esisteva. Si aspetta che il box smetta di muoversi.

    2. elementFromPoint lavora in coordinate di viewport e restituisce null per
       cio' che sta sotto la piega: "Conferma prenotazione" risultava "coperto da
       nessun elemento", che vuol dire solo "non l'ho guardato". Si porta in vista
       prima di chiedere chi c'e' sopra.
  */
  await locator.scrollIntoViewIfNeeded()
  const box = await attendiPosizioneStabile(locator, etichetta)

  const viewport = page.viewportSize()

  expect(
    box.x >= -1 && box.x + box.width <= viewport.width + 1,
    `${etichetta}: esce lateralmente dal viewport ` +
      `(x=${Math.round(box.x)}, larghezza=${Math.round(box.width)}, viewport=${viewport.width})`,
  ).toBe(true)

  const coperto = await locator.evaluate((el) => {
    const box = el.getBoundingClientRect()

    // Centro dell'elemento, ma riportato dentro il viewport: un elemento piu'
    // alto dello schermo ha il centro fuori e elementFromPoint darebbe null
    const limite = (valore, max) => Math.min(Math.max(valore, 1), max - 1)
    const cx = limite(box.left + box.width / 2, document.documentElement.clientWidth)
    const cy = limite(box.top + box.height / 2, document.documentElement.clientHeight)

    const sopra = document.elementFromPoint(cx, cy)
    if (!sopra) return { coperto: true, da: 'nessun elemento (fuori dal viewport visibile)' }

    if (el.contains(sopra) || sopra.contains(el)) return { coperto: false }

    return {
      coperto: true,
      da: `<${sopra.tagName.toLowerCase()} class="${(sopra.className?.toString?.() ?? '').slice(0, 100)}"> ` +
        `z-index=${getComputedStyle(sopra).zIndex}`,
    }
  })

  expect(coperto.coperto, `${etichetta}: coperto da ${coperto.da}`).toBe(false)
}

/**
 * Bersagli touch: il progetto dichiara un minimo di 44px (min-h-[44px] nelle
 * classi base). Sotto quella soglia su mobile il click diventa un terno al lotto.
 */
export async function bersagliTouchAdeguati(page, { minimo = 40 } = {}) {
  const piccoli = await page.evaluate((minimo) => {
    const risultato = []

    for (const el of document.querySelectorAll('button, a[href], [role="button"], input, select')) {
      const box = el.getBoundingClientRect()
      if (box.width === 0 || box.height === 0) continue // nascosto: non e' un bersaglio

      const stile = getComputedStyle(el)
      if (stile.visibility === 'hidden' || stile.display === 'none') continue

      if (box.height < minimo) {
        risultato.push({
          tag: el.tagName.toLowerCase(),
          testo: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 40),
          altezza: Math.round(box.height),
        })
      }
    }

    return risultato
  }, minimo)

  return piccoli
}

/** Testo tagliato: contenuto piu' largo del contenitore senza ellissi. */
export async function testoNonTroncatoSenzaEllissi(page) {
  return page.evaluate(() => {
    const problemi = []

    for (const el of document.querySelectorAll('h1, h2, h3, p, span, td, th, label')) {
      if (el.children.length > 0) continue // solo foglie di testo

      const eccede = el.scrollWidth > el.clientWidth + 1
      if (!eccede) continue

      const stile = getComputedStyle(el)
      const gestito = stile.textOverflow === 'ellipsis' || stile.overflowX === 'auto' || stile.overflowX === 'scroll'

      if (!gestito) {
        problemi.push({
          testo: (el.textContent || '').trim().slice(0, 50),
          visibile: el.clientWidth,
          reale: el.scrollWidth,
        })
      }
    }

    return problemi.slice(0, 10)
  })
}

/** Screenshot a pagina intera con nome parlante, allegato al report HTML. */
export async function istantanea(page, testInfo, nome) {
  const file = testInfo.outputPath(`${nome}.png`)
  await page.screenshot({ path: file, fullPage: true })
  await testInfo.attach(nome, { path: file, contentType: 'image/png' })
  return file
}
