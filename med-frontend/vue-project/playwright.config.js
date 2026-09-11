import { defineConfig, devices } from '@playwright/test'

/**
 * Configurazione E2E.
 *
 * I test girano contro il backend Laravel VERO: nessun mock, nessuna
 * intercettazione di rete. Di conseguenza servono due server accesi e un
 * database seminato in modo deterministico (vedi tests/e2e/README.md).
 *
 * Il database di E2E deve essere separato da quello di sviluppo: i test
 * prenotano visite e le lasciano nel calendario.
 */

const FRONTEND = process.env.E2E_BASE_URL || 'http://localhost:5173'
const BACKEND = process.env.E2E_API_URL || 'http://localhost:8000'

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',

  /*
    I test condividono un database reale: due worker che prenotano lo stesso
    slot si darebbero fastidio a vicenda producendo fallimenti intermittenti
    impossibili da diagnosticare. Si paga qualche secondo in piu' e si guadagna
    un risultato riproducibile.
  */
  fullyParallel: false,
  workers: 1,

  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  timeout: 45_000,
  expect: { timeout: 10_000 },

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],

  use: {
    baseURL: FRONTEND,
    locale: 'it-IT',
    timezoneId: 'Europe/Rome',

    // Materiale per l'analisi del layout: si conserva solo dove serve
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',

    actionTimeout: 10_000,
  },

  projects: [
    {
      name: 'chromium-desktop',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
    {
      /*
        Il progetto e' dichiaratamente mobile-first (touch target da 44px,
        sidebar a scomparsa sotto i 901px): il layout va provato anche stretto,
        altrimenti meta' delle regole CSS non viene mai eseguita.
      */
      name: 'chromium-mobile',
      use: { ...devices['Pixel 7'] },
      testMatch: ['**/layout.spec.js', '**/auth.spec.js'],
    },
  ],

  globalSetup: './tests/e2e/support/global-setup.js',

  webServer: [
    {
      command: 'php artisan serve --port=8000',
      cwd: '../../med-backend',
      url: `${BACKEND}/up`,
      reuseExistingServer: true,
      timeout: 60_000,
    },
    {
      command: 'npm run dev',
      url: FRONTEND,
      reuseExistingServer: true,
      timeout: 60_000,
    },
  ],
})
