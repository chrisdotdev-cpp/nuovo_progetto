# Test End-to-End (Playwright)

Questi test girano contro **backend Laravel reale e database reale**: nessun mock,
nessuna intercettazione di rete. Prenotano visite vere, che restano in `appointments`.

## Prima volta

```bash
cd med-frontend/vue-project
npm install                 # installa @playwright/test
npx playwright install chromium
```

## Database

I test si aspettano gli account creati dal seeder. **Usa un database separato da
quello di sviluppo**: la suite scrive dati.

In `med-backend/.env` (o meglio in un `.env.e2e`):

```
DB_DATABASE=med_gestionale_e2e
```

Poi:

```bash
cd med-backend
php artisan migrate:fresh --seed
```

Il seeder crea tre account con password `password`:

| Ruolo    | Email                  |
|----------|------------------------|
| admin    | admin@clinica.it       |
| medico   | medico@clinica.it      |
| paziente | paziente@clinica.it    |

e tre medici con agenda **lunedì-venerdì, 09:00-13:00 e 14:30-18:30**. I test
scelgono sempre un giorno feriale futuro (`prossimoGiornoFeriale()`).

### Limite di tentativi sul login

La suite fa una trentina di login veri. Il backend conta i soli tentativi
**falliti** per coppia email+IP (`LOGIN_MAX_ATTEMPTS`, default 5) e azzera il
contatore a ogni accesso riuscito: gli accessi legittimi della suite non
producono piu' il `Too Many Attempts.` che rendeva rossa meta' dei test.

Resta un tetto grezzo per IP sulla rotta di login (`LOGIN_FLOOD_PER_MINUTE`,
default 60/minuto): sufficiente per la suite, che ne fa una decina al minuto.

Per ripristinare solo gli account senza toccare il resto:

```bash
php artisan users:demo --reset
```

## Esecuzione

```bash
npm run e2e            # tutti i test, Chromium desktop + Pixel 7
npm run e2e:ui         # modalità interattiva, utile per il layout
npm run e2e:report     # apre il report HTML con screenshot e video
```

I due server (Laravel su :8000, Vite su :5173) vengono avviati da Playwright se non
sono già in ascolto (`reuseExistingServer: true`).

Se qualcosa manca, `global-setup.js` fallisce **prima** di aprire il browser con un
messaggio che dice quale comando eseguire.

## Perché un solo worker

`fullyParallel: false`, `workers: 1`. I test condividono un database vero: due
worker che prenotano lo stesso slot si darebbero fastidio a vicenda producendo
fallimenti intermittenti impossibili da diagnosticare.

## Struttura

```
tests/e2e/
├── auth.spec.js          login 3 ruoli, guard, logout, persistenza sessione
├── prenotazione.spec.js  percorso completo di prenotazione + annullamento
├── dashboard.spec.js     le tre Panoramiche e la navigazione di sezione
├── layout.spec.js        overflow, z-index, bersagli touch, troncamenti
└── support/
    ├── global-setup.js   verifica precondizioni e fallisce con istruzioni
    ├── utenti.js         credenziali, login/logout, helper data
    └── layout.js         misure di layout eseguite nel browser
```

## Materiale per l'analisi del layout

- **screenshot**: allegati al report per ogni test che chiama `istantanea()`,
  più cattura automatica su fallimento
- **video**: `retain-on-failure`
- **trace**: `retain-on-failure` — apribile con `npx playwright show-trace`,
  contiene DOM, rete e timeline azione per azione

I controlli di layout in `support/layout.js` **misurano** invece di limitarsi a
fotografare: overflow orizzontale con l'elenco degli elementi colpevoli,
copertura di un elemento da parte di un altro via `elementFromPoint`, altezza dei
bersagli touch, testo troncato senza ellissi.
