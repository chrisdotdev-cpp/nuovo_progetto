import { vi } from 'vitest'

/**
 * Setup globale della suite.
 *
 * vue3-toastify e' gia' sostituita da un alias in vitest.config.js: qui restano
 * solo le lacune di jsdom che farebbero fallire il mount di componenti che non
 * hanno nulla a che vedere con cio' che si sta provando.
 */

// jsdom non implementa matchMedia: i layout responsive lo interrogano al mount
if (!window.matchMedia) {
  window.matchMedia = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })
}

// Nemmeno scrollTo: usato da scrollBehavior del router e da qualche modal
if (!window.scrollTo) {
  window.scrollTo = vi.fn()
}
