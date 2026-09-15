# Processo di sviluppo e analisi del codice

Resoconto del percorso seguito per costruire l'applicazione e analisi dettagliata dei frammenti di codice più significativi.

Questo documento risponde al punto della traccia che chiede *«un resoconto del processo seguito per lo sviluppo dei codici, includendo una descrizione di dettaglio degli snippet ritenuti più interessanti»*.

---

## Indice

- [Parte I — Il processo](#parte-i--il-processo)
  - [1. Approccio generale](#1-approccio-generale)
  - [2. Le fasi di lavoro](#2-le-fasi-di-lavoro)
  - [3. Difficoltà incontrate e come sono state superate](#3-difficoltà-incontrate-e-come-sono-state-superate)
  - [4. Strumenti impiegati](#4-strumenti-impiegati)
- [Parte II — Gli snippet](#parte-ii--gli-snippet)
  - [Snippet 1 — Prenotazione concorrente](#snippet-1--prenotazione-concorrente-con-blocco-pessimistico)
  - [Snippet 2 — La guard del router](#snippet-2--la-guard-del-router-come-funzione-pura)
  - [Snippet 3 — Client HTTP con interceptor](#snippet-3--client-http-con-interceptor-e-normalizzazione-degli-errori)
  - [Snippet 4 — Observer per la fatturazione](#snippet-4--observer-il-confine-fra-agenda-e-contabilità)
  - [Snippet 5 — Rate limiting sui soli fallimenti](#snippet-5--rate-limiting-che-conta-i-soli-tentativi-falliti)
  - [Snippet 6 — Factory CRUD](#snippet-6--una-factory-che-fattorizza-il-crud-di-sette-domini)
- [Parte III — Bilancio critico](#parte-iii--bilancio-critico)

---

# Parte I — Il processo

## 1. Approccio generale

Lo sviluppo ha seguito un ordine preciso: **prima il dominio, poi l'API, poi l'interfaccia**. La sequenza non è arbitraria e vale la pena motivarla, perché ha determinato gran parte delle scelte successive.

Partire dal modello dei dati significa che, quando si scrive il primo controller, le entità e i loro vincoli sono già stabili. L'alternativa — partire dalle schermate e far emergere il modello da quello che serve alla UI — produce tipicamente uno schema che riflette il layout delle pagine anziché la struttura del dominio, e che va rifatto alla prima pagina nuova.

La seconda decisione di fondo è stata trattare **frontend e backend come due applicazioni distinte** che comunicano solo via HTTP, non come due metà dello stesso progetto. Ne consegue che il backend non sa nulla del frontend: non serve viste, non conosce le rotte di Vue Router, non ha sessioni. Questa separazione è ciò che la traccia chiede quando parla di architettura API-based, e ha una conseguenza verificabile: il backend è interamente testabile senza mai aprire un browser, ed è quello che fanno i 159 test PHPUnit.

Un terzo principio, meno strutturale ma più pervasivo: **ogni scelta non ovvia è commentata nel punto in cui vive**, spiegando il *perché* e non il *cosa*. Un commento che dice «incrementa il contatore» è rumore; uno che dice «il contatore riparte da zero perché altrimenti cinque accessi legittimi dallo stesso ufficio bloccherebbero tutti» è documentazione. Diversi frammenti analizzati nella Parte II sono leggibili proprio grazie a quei commenti.

## 2. Le fasi di lavoro

### Fase 1 — Modellazione del dominio

Traduzione del dominio sanitario in entità e relazioni: 22 tabelle applicative distribuite su 18 file di migration. Il lavoro è documentato per intero in [`02-modello-dati.md`](02-modello-dati.md).

La decisione più impegnativa di questa fase è stata **non creare una tabella degli slot prenotabili**. La disponibilità di un medico è calcolata a runtime intersecando orario settimanale, assenze e appuntamenti già presi. Una tabella precalcolata sarebbe stata più semplice da interrogare ma soggetta a disallineamento: ogni modifica all'orario avrebbe richiesto di rigenerare le disponibilità future, e una rigenerazione parziale avrebbe prodotto slot fantasma. Il calcolo a runtime non può divergere dalla realtà, ma sposta il problema sulla concorrenza — ed è la ragione dello Snippet 1.

### Fase 2 — Livello di accesso ai dati

Model Eloquent con relazioni, scope e attributi calcolati (`ends_at`, `age`, `stock_status`, `balance`). Gli scope incapsulano le interrogazioni ricorrenti — «i pazienti di questo medico», «gli appuntamenti futuri», «le notifiche non lette» — così la stessa logica di filtro non viene riscritta in ogni controller.

### Fase 3 — Autorizzazione

Undici Policy, una per risorsa, più tre middleware. I due livelli sono indipendenti e servono a cose diverse:

- il **middleware** filtra per area (`role:admin`) e blocca prima di qualsiasi query — è una barriera grossolana ma economica
- la **Policy** verifica che *quell'utente* possa agire su *quel record* — è fine ma richiede di aver già caricato la risorsa

Averli entrambi significa che un paziente che chiama una rotta amministrativa viene fermato senza che il database venga interrogato, mentre un medico che chiede la cartella di un paziente non suo viene fermato dopo il caricamento, perché solo allora si può sapere se gli appartiene.

Questa fase ha prodotto il test `il perimetro di un medico senza profilo e vuoto`, nato da un caso limite emerso solo scrivendo i test: un account con ruolo `medico` ma senza record in `doctors` produceva un errore invece di una lista vuota.

### Fase 4 — Logica di dominio nei service

Otto classi in `app/Services/`. Il criterio per spostare qualcosa dal controller al service è stato: **se la regola vale da più punti di ingresso, non può stare nel controller**.

La prenotazione ne è l'esempio: avviene dall'area paziente, dal pannello amministrativo e dalla conversione di una richiesta di consulto. Se il controllo di sovrapposizione stesse in `AppointmentController`, le altre due strade lo aggirerebbero.

### Fase 5 — Contratto API

Controller sottili, `FormRequest` per la validazione in ingresso, `JsonResource` per la serializzazione in uscita. Le Resource non sono un abbellimento: sono ciò che impedisce che una modifica allo schema del database si propaghi automaticamente al contratto pubblico dell'API. Il campo `notes` di un appuntamento esiste nella tabella ma non viene serializzato per il paziente, e quella decisione vive in una sola riga della Resource.

### Fase 6 — Frontend

Struttura a moduli: un file di rotte per area, viste in lazy loading, store Pinia per lo stato condiviso, composable per la logica riutilizzabile. Il client HTTP è unico e centralizzato (Snippet 3), il CRUD è fattorizzato in una factory (Snippet 6).

### Fase 7 — Test

Tre suite con obiettivi distinti:

| Suite | Strumento | Cosa verifica | Numeri |
|---|---|---|---|
| Backend | PHPUnit | Regole di dominio, autorizzazioni, relazioni, ciclo di fatturazione | 159 test, 574 asserzioni |
| Frontend unitaria | Vitest | Guard del router, store di autenticazione, viste di login e prenotazione | 80 test |
| End-to-end | Playwright | Flussi completi su browser reale, desktop e mobile | 4 suite, screenshot automatici |

I test E2E producono come effetto collaterale la documentazione visiva richiesta dalla traccia al punto sul test funzionale: gli screenshot non sono stati catturati a mano, sono generati a ogni esecuzione.

### Fase 8 — Preparazione alla pubblicazione

Analisi statica del progetto prima del rilascio su Git: verifica che tutti gli import si risolvano (224 controllati, 0 non risolti), individuazione del codice morto, eliminazione dei file duplicati, configurazione di `.gitignore` e `.gitattributes`, stesura della documentazione.

Questa fase ha rimosso sei file identici a due a due (le viste `Impostazioni.vue` e `Notifiche.vue` replicate per i tre ruoli), due file orfani senza estensione, uno store Pinia e un composable con zero riferimenti in tutto il progetto.

## 3. Difficoltà incontrate e come sono state superate

Questa sezione riporta problemi realmente incontrati, con la diagnosi e la soluzione adottata. Sono documentati anche nei commenti del codice, nel punto esatto in cui il problema si manifestava.

### 3.1 Il login non rediregeva — due cause sovrapposte

Il sintomo: credenziali corrette, il backend rispondeva `200` con il token, ma l'applicazione restava ferma sulla schermata di login.

**Prima causa — la guard navigava invece di rispondere.** La guard del router chiamava `router.push()` per dirottare l'utente. Un `push()` dentro una guard avvia una *seconda* navigazione che annulla la prima, producendo un `Navigation aborted` silenzioso: nessun errore visibile, la UI semplicemente non si muove. La soluzione è stata trasformare la guard in una funzione che **restituisce** la destinazione anziché navigare (Snippet 2).

**Seconda causa — un involucro di troppo.** La funzione `run()` di `useApiRequest` estraeva sempre `response.data`, assumendo che la callback restituisse una risposta axios. Ma `authStore.login()` non restituisce una risposta: restituisce direttamente la rotta di redirect. Il risultato era `undefined`, che il chiamante interpretava come fallimento. La correzione:

```js
function isAxiosResponse(value) {
  return (
    value !== null &&
    typeof value === 'object' &&
    'data' in value &&
    'status' in value &&
    'config' in value
  )
}

/** Estrae il payload utile: response.data se axios, altrimenti il valore stesso. */
export function unwrap(value) {
  return isAxiosResponse(value) ? value.data : value
}
```

La lezione, generalizzabile: quando una funzione accetta una callback dal significato variabile, non può fare assunzioni sul tipo di ritorno. Va aggiunto anche `lastCallOk`, perché altrimenti «riuscito ma senza payload» e «fallito» restano indistinguibili.

### 3.2 Il rate limiter bloccava gli utenti legittimi

La configurazione iniziale usava `throttle:6,1` sulla rotta di login: sei richieste al minuto per IP. Il problema è che quel limitatore conta **tutte** le richieste, non solo quelle fallite.

Bastavano pochi accessi legittimi dallo stesso indirizzo — la suite E2E che fa login con tre utenze diverse, un ufficio dietro NAT, un utente che passa da desktop a telefono — per far comparire «Too Many Attempts» a chi non stava indovinando nulla.

La soluzione ha separato le due difese, che rispondono a domande diverse (Snippet 5).

### 3.3 Un solo record incoerente svuotava l'intera lista

`DoctorResource` accedeva a `$this->user->name` per esporre il nome del medico. Se l'account collegato era stato archiviato con soft delete, la relazione risultava caricata ma valeva `null`, e l'accesso diretto faceva fallire con `500` la serializzazione **dell'intera collezione**.

Concretamente: un medico archiviato rendeva vuoto l'elenco medici di tutti i pazienti. La correzione è di un carattere — l'operatore `?->` — ma il commento nel codice spiega perché non è ridondante, così che una futura "pulizia" non lo rimuova.

### 3.4 Le variabili d'ambiente ignorate dal proxy di sviluppo

Il proxy di Vite verso Laravel usava sempre il valore di default invece di quello configurato in `.env`. La causa: dentro `vite.config.js`, `process.env` **non** contiene le variabili `VITE_*`, che vengono iniettate solo nel codice dell'applicazione. Serve leggerle esplicitamente:

```js
const env = loadEnv(mode, process.cwd(), "");
const proxyTarget = env.VITE_PROXY_TARGET || "http://localhost:8000";
```

### 3.5 Tailwind v4: una shorthand che sovrascriveva una longhand

Su una barra di navigazione, la classe `pl-16` che riservava lo spazio per il pulsante hamburger veniva ignorata sopra i 640px, e il logo finiva sotto il pulsante.

La causa non era il breakpoint, come sembrava, ma l'**ordine nel CSS generato**: le utility con variante responsive vengono emesse dopo quelle base, e a parità di specificità la shorthand `sm:p-4` sovrascrive la longhand `pl-16` perché arriva dopo nella cascata. L'ordine in cui le classi compaiono nell'attributo `class` non conta.

La correzione è stata sostituire la shorthand con le longhand dei soli lati che servivano — `py-3 pr-3 sm:py-4 sm:pr-4` — lasciando `pl-*` come unica sorgente del padding sinistro.

### 3.6 La build di produzione rifiutata da Vite 8

A progetto concluso, `npm run build` falliva:

```
Invalid type: Expected Function but received Object.
TypeError: manualChunks is not a function
```

Vite 8 non usa più Rollup ma **rolldown**, che non implementa la forma a oggetto di `manualChunks`. La configurazione era corretta per Vite 7 e ha smesso di esserlo con l'aggiornamento di versione.

Da notare *dove* falliva: dopo `✓ 153 modules transformed`, cioè a trasformazione completata. Non era un problema di codice sorgente ma di configurazione della fase di chunking — distinzione che ha ridotto la ricerca da tutto il progetto a un solo file.

La soluzione è stata rimuovere il blocco: rolldown applica già una suddivisione in chunk ragionevole, e il vendor chunk manuale non portava benefici misurabili.

### 3.7 Un errore a runtime opaco: `client.update is not a function`

La factory `useResource` assumeva che ogni modulo API esponesse i cinque metodi CRUD. Non è vero: i documenti si caricano con `upload()` e non hanno `update` (lato Laravel la rotta non esiste, `apiResource(...)->except(['update'])`), e una sessione di telemedicina si avvia e si chiude ma non si modifica.

Chiamare un metodo assente produceva un `TypeError` generico, senza indicazioni su cosa mancasse. La soluzione è stata rendere il limite esplicito e diagnosticabile (Snippet 6).

## 4. Strumenti impiegati

| Ambito | Strumento | Motivo della scelta |
|---|---|---|
| Backend | Laravel 12 + PHP 8.2 | Framework object-oriented maturo, con Eloquent, Policy e sistema di migration integrati |
| Autenticazione | Laravel Sanctum | Token stateless, adatto a un frontend disaccoppiato: nessun cookie, nessun CSRF |
| Database | MySQL 8 | Supporto a JSON, vincoli di integrità referenziale, `FIELD()` per l'ordinamento di triage |
| Frontend | Vue 3 + Composition API | `<script setup>` riduce la verbosità; i composable permettono di fattorizzare la logica senza gerarchie di componenti |
| Build | Vite 8 | Avvio istantaneo in sviluppo, code splitting automatico in produzione |
| Stile | Tailwind CSS 4 | Compilato in build, include solo le classi realmente usate |
| Stato | Pinia 3 | Store tipizzati, con persistenza selettiva del solo token |
| Test backend | PHPUnit | Integrato con Laravel, database in memoria per l'isolamento |
| Test frontend | Vitest | Condivide la configurazione di Vite, nessuna duplicazione di setup |
| Test E2E | Playwright | Browser reale, desktop e mobile, screenshot automatici |
| Formattazione | oxfmt | Stile uniforme senza discussioni |
| Versionamento | Git + GitHub | Storia del progetto e pubblicazione del codice |

Due note sulle scelte non scontate.

**Perché Vitest e non Jest.** Vitest riusa la configurazione di Vite: alias, plugin e trasformazioni sono già definiti. Con Jest sarebbero da replicare in un secondo file di configurazione, che diverge al primo cambiamento.

**Perché una configurazione Vitest separata da quella di Vite.** Pur potendo riusare `vite.config.js`, esiste un `vitest.config.js` dedicato: quella principale carica i devtools di Vue e il plugin Tailwind, inutili sotto test e responsabili di diversi secondi di avvio a ogni esecuzione. Il file dedicato contiene solo ciò che serve a compilare i `.vue` e risolvere gli alias, più un alias che sostituisce la libreria dei toast con uno stub — più affidabile di un mock ripetuto in ogni file, dato che `useToast` viene importata a catena da router, store e quasi ogni vista.

---

# Parte II — Gli snippet

Sei frammenti scelti perché ciascuno risolve un problema non banale e illustra una decisione progettuale difendibile.

---

## Snippet 1 — Prenotazione concorrente con blocco pessimistico

**File:** `med-backend/app/Services/AppointmentService.php`

### Il problema

Due pazienti aprono la stessa pagina e vedono lo stesso slot libero. Premono "Prenota" nello stesso istante. Senza precauzioni, entrambe le richieste eseguono il controllo di disponibilità, entrambe lo superano — perché nessuna delle due ha ancora scritto — ed entrambe inseriscono l'appuntamento. Il medico si ritrova due pazienti alle 10:30.

Il vincolo di unicità del database, che sarebbe la difesa naturale, **qui non è applicabile**: la sovrapposizione non è un'uguaglianza di valori ma una condizione su un intervallo. Due appuntamenti alle 10:00 e alle 10:15, di trenta minuti ciascuno, hanno `scheduled_at` diversi e si sovrappongono comunque.

### Il codice

```php
public function book(array $data): Appointment
{
    return DB::transaction(function () use ($data) {
        $doctor   = Doctor::lockForUpdate()->findOrFail($data['doctor_id']);
        $start    = CarbonImmutable::parse($data['scheduled_at']);
        $duration = (int) ($data['duration_minutes'] ?? $doctor->slot_duration);

        $this->assertSlotIsFree($doctor, $start, $duration);

        $appointment = Appointment::create([
            'patient_id'       => $data['patient_id'],
            'doctor_id'        => $doctor->id,
            'scheduled_at'     => $start,
            'duration_minutes' => $duration,
            'type'             => $data['type'] ?? 'visita',
            'status'           => $data['status'] ?? Appointment::STATUS_IN_ATTESA,
            'reason'           => $data['reason'] ?? null,
            'notes'            => $data['notes'] ?? null,
        ]);

        // Se e' un teleconsulto si predispone subito la stanza virtuale
        if ($appointment->type === 'telemedicina') {
            app(TelemedicineService::class)->createSessionFor($appointment);
        }

        $this->notifications->appointmentCreated($appointment);

        return $appointment->load(['patient.user', 'doctor.user']);
    });
}
```

E il controllo di sovrapposizione, che è la parte concettualmente interessante:

```php
$overlapping = $doctor->appointments()
    ->where('status', '!=', Appointment::STATUS_ANNULLATO)
    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
    ->whereDate('scheduled_at', $start->toDateString())
    ->get(['id', 'scheduled_at', 'duration_minutes'])
    ->contains(function ($appointment) use ($start, $end) {
        $bookedStart = CarbonImmutable::parse($appointment->scheduled_at);
        $bookedEnd   = $bookedStart->addMinutes($appointment->duration_minutes);

        return $start->lessThan($bookedEnd) && $end->greaterThan($bookedStart);
    });
```

### Perché funziona

**`lockForUpdate()` sulla riga del medico.** La prima transazione che acquisisce il lock lo mantiene fino al `COMMIT`. La seconda si sospende sulla `SELECT ... FOR UPDATE` finché la prima non ha finito, e quando riprende **rilegge** lo stato del database: a quel punto l'appuntamento della prima esiste, e il controllo di sovrapposizione lo trova.

La riga del medico è scelta deliberatamente come punto di serializzazione. Sarebbe stato possibile bloccare la tabella degli appuntamenti, ma ciò avrebbe serializzato le prenotazioni di *tutti* i medici. Bloccando il singolo medico, due pazienti che prenotano con professionisti diversi non si ostacolano a vicenda: la contesa è limitata a chi compete davvero per la stessa risorsa.

**La condizione di sovrapposizione.** L'espressione `$start < $bookedEnd && $end > $bookedStart` è la formulazione canonica dell'intersezione fra intervalli. È più sottile di quanto sembri: verifica la sovrapposizione *parziale*, non solo la coincidenza degli orari di inizio. Un appuntamento alle 10:15 su uno slot 10:00–10:30 viene correttamente respinto.

Il `whereDate` che restringe alla sola giornata non è un dettaglio di ottimizzazione ma una necessità: senza, il confronto dovrebbe caricare l'intero storico degli appuntamenti del medico.

### Come è verificato

Tre test di `PrenotazioniTest` coprono il comportamento:

- `due pazienti non possono occupare lo stesso slot`
- `anche una sovrapposizione parziale viene respinta`
- `uno slot liberato da un annullamento torna prenotabile`

L'ultimo verifica una conseguenza non ovvia: poiché il controllo esclude gli appuntamenti con stato `annullato`, la cancellazione libera lo slot senza bisogno di alcuna operazione aggiuntiva.

### Il limite

Il blocco pessimistico è la scelta giusta per un poliambulatorio, dove la contesa sullo stesso slot è rara ma il costo di un doppio appuntamento è alto. Su un sistema con migliaia di prenotazioni al secondo introdurrebbe un collo di bottiglia, e si valuterebbe un approccio ottimistico con versioning o un vincolo di esclusione a livello di database (`EXCLUDE USING gist` su PostgreSQL, che MySQL non offre).

---

## Snippet 2 — La guard del router come funzione pura

**File:** `med-frontend/vue-project/src/router/guard.js`

### Il problema

La guard è l'unica barriera fra un URL digitato a mano e una sezione riservata. Il backend protegge i dati, ma senza guard un paziente che scrive `/admin/utenti` vedrebbe comunque il guscio della dashboard amministrativa — vuota, ma visibile.

Il primo tentativo chiamava `router.push()` per dirottare l'utente. Non funzionava, e il modo in cui falliva era peggio dell'errore: nessun messaggio, nessuna eccezione, la UI semplicemente ferma.

### Il codice

```js
export function authGuard(to) {
  const auth = useAuthStore()

  const isPublic = to.name === 'home' || to.name === 'loginRole'

  // Utente gia' autenticato che torna su home o login -> alla sua dashboard
  if (auth.isAuthenticated && isPublic) {
    const target = auth.dashboardPath
    return target === to.path ? true : target
  }

  // Rotta protetta senza sessione valida
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Nessun toast al primo avvio (token assente): sarebbe solo rumore
    if (!auth.initializing) {
      useToast().error('Devi effettuare il login per accedere')
    }

    // Si memorizza la destinazione per tornarci dopo il login
    return { name: 'home', query: { redirect: to.fullPath } }
  }

  // Rotta con ruolo specifico: si viene dirottati sulla propria area
  if (to.meta.role && auth.role !== to.meta.role) {
    useToast().error('Non hai i permessi per accedere a questa sezione')

    if (!auth.isAuthenticated) return { name: 'home' }

    const target = auth.dashboardPath
    return target === to.path ? { name: 'home' } : target
  }

  return true
}
```

### Perché è scritta così

**Restituisce, non naviga.** È la regola che risolve il bug. Vue Router interpreta il valore restituito da una guard: `true` lascia passare, un oggetto o una stringa diventano la nuova destinazione. Chiamare `router.push()` dall'interno avvia invece una seconda navigazione che *annulla* la prima, e l'annullamento non è un errore — è il comportamento documentato. Il risultato è una UI immobile senza alcun sintomo diagnostico.

**Vive in un file separato da `router/index.js`.** Non è pignoleria organizzativa: è ciò che la rende testabile. Essendo una funzione che prende `to` e restituisce una destinazione, la si può chiamare direttamente con oggetti costruiti a mano, senza istanziare un router, senza montare layout, senza caricare le viste lazy e gli store di mezza applicazione:

```js
const rotta = (overrides = {}) => ({
  name: 'paziente.panoramica',
  path: '/paziente/panoramica',
  fullPath: '/paziente/panoramica',
  params: {}, query: {}, meta: {},
  ...overrides,
})

it('lascia passare un visitatore sulla home', () => {
  const esito = authGuard(rotta({ name: 'home', path: '/', fullPath: '/' }))

  expect(esito).toBe(true)
  expect(toast.error).not.toHaveBeenCalled()
})
```

Quattordici test coprono la guard. Con una guard che naviga, ciascuno avrebbe richiesto un router reale e l'osservazione di un effetto collaterale invece di un valore di ritorno.

**I due confronti `target === to.path`.** Prevengono un ciclo infinito di redirect. Se la destinazione calcolata coincide con quella richiesta, dirottare significherebbe rimandare l'utente dove già sta andando: il router entrerebbe in loop. Il caso si presenta quando un utente autenticato ricarica la propria dashboard.

**Il controllo su `auth.initializing`.** All'avvio dell'applicazione il token non è ancora stato verificato. Senza questa condizione, ogni ricaricamento di pagina su una rotta protetta mostrerebbe «Devi effettuare il login» per una frazione di secondo, prima che la sessione venga ripristinata. È rumore che fa sembrare rotta un'applicazione che funziona.

### Il limite

La guard protegge la navigazione, non i dati. È corretto che sia così — la sicurezza reale è nelle Policy del backend — ma va detto esplicitamente: un utente che disabilitasse JavaScript o manipolasse lo store aggirerebbe la guard, e otterrebbe comunque `403` su ogni chiamata API. La guard è ergonomia, non sicurezza.

---

## Snippet 3 — Client HTTP con interceptor e normalizzazione degli errori

**File:** `med-frontend/vue-project/src/services/http.js`

### Il problema

Ogni chiamata API ha bisogno delle stesse cose: il token nell'header, la gestione del token scaduto, un formato d'errore prevedibile. Implementarle nei componenti significa ripeterle sessanta volte e dimenticarsene almeno una.

C'è poi un problema più insidioso: gli errori arrivano in forme diverse. Un `422` di Laravel ha `errors` per campo; un `500` ha solo `message`; un server irraggiungibile non ha risposta affatto, e `error.response` è `undefined`. Un componente che debba distinguere questi casi si riempie di controlli difensivi.

### Il codice

```js
const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  timeout: 20000,
  // Sanctum in modalita' token: nessun cookie, nessun CSRF
  withCredentials: false,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

http.interceptors.request.use((config) => {
  const token = authStoreRef?.token

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // Con FormData il browser deve impostare da solo il boundary
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status ?? 0
    const payload = error.response?.data ?? {}
    const url = error.config?.url ?? ''

    // Errore in forma canonica: la UI legge sempre le stesse proprieta'
    const normalized = {
      status,
      message: payload.message || fallbackMessage(status),
      errors: payload.errors || {},
      firstError: firstErrorOf(payload),
      raw: error,
    }

    /*
      401: token scaduto o revocato -> sessione chiusa e ritorno al login.
      Si esclude /auth/login: li' un 401 significa "credenziali sbagliate",
      non "sessione scaduta", e non deve provocare un redirect.
    */
    if (status === 401 && !url.includes('/auth/login')) {
      authStoreRef?.clearSession?.()
      onUnauthorized?.()
    }

    return Promise.reject(normalized)
  },
)
```

### I dettagli che contano

**`status ?? 0` per il server irraggiungibile.** Se il backend è spento non esiste `error.response`, e quindi nemmeno uno status. Il valore `0` non è un codice HTTP: è una convenzione interna che permette a `fallbackMessage()` di produrre «Server non raggiungibile. Verifica che il backend Laravel sia avviato», che è l'unico messaggio utile in quella circostanza.

**L'esclusione di `/auth/login` dalla gestione del 401.** Senza di essa, sbagliare la password provocherebbe un logout e un redirect al login — dalla pagina di login. L'utente vedrebbe la pagina ricaricarsi senza spiegazioni. Lo stesso codice HTTP significa due cose diverse a seconda dell'endpoint, e questa riga codifica quella distinzione.

**L'eliminazione di `Content-Type` con `FormData`.** Quando si carica un file, il browser deve generare da sé l'header `multipart/form-data; boundary=...` con un boundary casuale. Se axios lo imposta a priori, il boundary manca e il server non riesce a interpretare il corpo della richiesta. Il sintomo è un `422` su un form compilato correttamente.

**L'iniezione differita dello store.** Lo store di autenticazione non viene importato: viene registrato dall'esterno tramite `registerAuthStore()`. Un import diretto creerebbe un ciclo — lo store importa `http`, `http` importerebbe lo store — che in un bundler si risolve con uno dei due moduli parzialmente inizializzato. Lo stesso vale per il router, registrato tramite `registerUnauthorizedHandler()`.

### Il limite

L'interceptor non implementa il rinnovo automatico del token alla scadenza: al `401` chiude la sessione e riporta al login. Esiste `POST /auth/refresh`, ed è chiamato da un timer nello store in base a `expires_at`, ma se quel timer non scatta — scheda in background, computer in sospensione — l'utente viene disconnesso. Una gestione più matura intercetterebbe il `401`, tenterebbe un refresh e riproverebbe la richiesta originale una sola volta.

---

## Snippet 4 — Observer: il confine fra agenda e contabilità

**File:** `med-backend/app/Observers/AppointmentObserver.php`

### Il problema

Regola di dominio: quando una visita è erogata, va emessa la fattura. Dove va scritta?

Nel controller degli appuntamenti sarebbe il posto sbagliato, perché lo stato cambia da più punti — l'agenda del medico, il pannello amministrativo, e in prospettiva un job di chiusura automatica delle visite scadute. Ogni punto dovrebbe ricordarsi di fatturare, e prima o poi uno se ne dimenticherebbe.

### Il codice

```php
class AppointmentObserver
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function updated(Appointment $appointment): void
    {
        // isDirty() dentro updated() legge ancora il delta del salvataggio appena fatto
        if (! $appointment->wasChanged('status')) {
            return;
        }

        if (! $this->daFatturare($appointment->status)) {
            return;
        }

        // Non blocca mai la chiusura della visita: il service logga e prosegue
        $this->invoices->createFromAppointment($appointment);
    }

    /**
     * Stati che rendono la prestazione esigibile.
     * Il no-show e' opzionale: alcune strutture addebitano una penale, altre no.
     */
    private function daFatturare(string $status): bool
    {
        if ($status === Appointment::STATUS_COMPLETATO) {
            return true;
        }

        return $status === Appointment::STATUS_ASSENTE
            && (bool) config('billing.invoice_no_show', false);
    }
}
```

### Perché è la struttura giusta

**Una regola, un posto.** L'Observer si attiva sull'evento `updated` del model, indipendentemente da chi lo ha provocato. Qualunque strada porti l'appuntamento a `completato` — controller, comando artisan, seeder, job futuro — passa di qui.

**`wasChanged()` e non `isDirty()`.** Dentro il callback `updated` il salvataggio è già avvenuto: `isDirty()` riporterebbe le modifiche *pendenti*, che a quel punto non ce ne sono più. `wasChanged()` riporta cosa è *effettivamente* cambiato in quel salvataggio. Confonderli produce un Observer che non si attiva mai, ed è un errore difficile da diagnosticare perché il codice sembra corretto.

Il controllo evita anche di riemettere la fattura quando si modifica un appuntamento già completato per cambiarne, per esempio, le note.

**La configurazione come punto di variazione.** Il no-show — paziente che non si presenta — è fatturabile o no a seconda della politica della struttura. Anziché fissare la scelta nel codice, `config('billing.invoice_no_show')` la rende un parametro. È una forma leggera di Strategy: il comportamento cambia senza toccare la logica.

**Il fallimento non propaga.** Se l'emissione della fattura fallisce, il service registra l'errore e prosegue. Impedire a un medico di chiudere una visita perché il modulo di fatturazione ha un problema sarebbe una scelta sbagliata: la prestazione clinica è avvenuta, e la contabilità si recupera dopo.

### Come è verificato

`CicloFatturazioneTest` copre tutte le diramazioni: `completare un appuntamento emette la fattura`, `la fattura non viene duplicata`, `nessuna fattura se il medico non ha tariffa`, `annullare un appuntamento non emette fattura`.

### Il limite

Gli Observer hanno un costo di leggibilità: l'effetto non è visibile nel punto in cui si scrive `$appointment->update(['status' => 'completato'])`. Chi legge quella riga non ha modo di sapere che sta emettendo una fattura. È il compromesso accettato in cambio della garanzia che la regola valga sempre — ma con dieci Observer anziché uno, il sistema diventerebbe difficile da seguire.

---

## Snippet 5 — Rate limiting che conta i soli tentativi falliti

**File:** `med-backend/app/Providers/AppServiceProvider.php` e `app/Http/Controllers/Api/V1/AuthController.php`

### Il problema

Proteggere il login dal brute force è necessario. Ma il limitatore standard di Laravel, `throttle:6,1`, conta tutte le richieste indistintamente, e questo produce falsi positivi: la suite E2E che fa login con tre utenze consecutive, un ufficio dietro NAT, un utente che passa da desktop a telefono. Tutti bloccati con «Too Many Attempts» senza che nessuno stesse indovinando una password.

### Il codice

Difesa grossolana, per IP, contro il flood:

```php
/*
 * Login: qui c'e' solo la protezione grossolana contro il flood (chiunque
 * puo' bussare, ma non mille volte al secondo). La difesa vera dal brute
 * force sta in AuthController::login e conta i soli tentativi FALLITI per
 * coppia email+IP, azzerandoli al primo accesso riuscito.
 */
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute((int) config('auth.login.flood_per_minute', 60))
        ->by($request->ip())
        ->response(fn () => response()->json([
            'message' => 'Troppe richieste di accesso. Riprova tra qualche istante.',
        ], 429));
});
```

Difesa fine, nel controller:

```php
public function login(LoginRequest $request): JsonResponse
{
    $chiave = $this->chiaveTentativi($request);

    if ($bloccato = $this->rispostaSeBloccato($chiave)) {
        return $bloccato;
    }

    $user = User::where('email', $request->email)->first();

    // Messaggio volutamente generico: non si rivela se l'email esiste
    if (! $user || ! Hash::check($request->password, $user->password)) {
        $this->registraTentativoFallito($chiave);

        throw ValidationException::withMessages([
            'email' => 'Credenziali non valide.',
        ]);
    }

    // ... controlli su stato account e ruolo, ciascuno con registraTentativoFallito()

    /*
      Credenziali corrette: il contatore riparte da zero. E' la differenza
      fra "questo IP ha sbagliato cinque volte di fila" (sospetto) e "questo
      IP ha fatto cinque accessi riusciti" (un mercoledi' qualunque).
    */
    RateLimiter::clear($chiave);

    // Un token per dispositivo: il logout su mobile non butta fuori dal desktop
    $device = $request->input('device_name', 'web');
    $user->tokens()->where('name', $device)->delete();

    $token = $user->createToken($device, ['*'], now()->addMinutes(config('sanctum.expiration', 720)));
    // ...
}
```

### Perché due livelli

Rispondono a domande diverse. Il limitatore per IP chiede *«questo indirizzo sta martellando il server?»* ed è una difesa di infrastruttura, con soglia alta. Il contatore per email+IP chiede *«qualcuno sta provando password su questo account?»* ed è una difesa applicativa, con soglia bassa.

La chiave è la coppia **email+IP**, non solo l'email: usare la sola email permetterebbe a un malintenzionato di bloccare l'account di una vittima sbagliando deliberatamente la password cinque volte — un denial of service mirato.

**`RateLimiter::clear()` al successo** è la riga che elimina i falsi positivi. Cinque tentativi falliti *di fila* sono sospetti; cinque accessi riusciti sono una giornata normale.

**Tutti i percorsi di fallimento incrementano il contatore.** Non solo la password sbagliata, ma anche l'account sospeso e il ruolo non corrispondente. Altrimenti quei percorsi offrirebbero un canale non limitato per sondare quali email esistono.

**Il messaggio identico per email inesistente e password errata** completa il quadro: senza, i tempi di risposta o i testi diversi rivelerebbero quali indirizzi sono registrati.

### Come è verificato

`AutenticazioneTest` contiene i tre test che formalizzano il comportamento: `il login e protetto dal brute force`, `gli accessi riusciti non fanno scattare il blocco`, `un accesso riuscito azzera i tentativi falliti`. Il secondo è quello che il vecchio codice non superava.

---

## Snippet 6 — Una factory che fattorizza il CRUD di sette domini

**File:** `med-frontend/vue-project/src/composables/useResource.js`

### Il problema

Sette aree del gestionale — pazienti, medici, appuntamenti, documenti, prescrizioni, fatture, telemedicina — hanno lo stesso ciclo di vita: lista paginata con filtri, caricamento del dettaglio, creazione, modifica, eliminazione, ciascuna con il proprio indicatore di caricamento e il proprio messaggio di conferma.

Scritto sette volte, sono settecento righe che divergono al primo ritocco.

### Il codice

```js
export function useResource(client, options = {}) {
  const {
    label = 'Elemento',
    labelPlural = 'Elementi',
    defaultFilters = {},
    perPage = 15,
  } = options

  const items = ref([])
  const current = ref(null)
  const filters = reactive({ ...defaultFilters })

  const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: perPage,
    total: 0,
  })

  const AZIONI = ['list', 'get', 'create', 'update', 'remove']

  const disponibili = new Set(AZIONI.filter((azione) => typeof client?.[azione] === 'function'))

  /**
   * true se la risorsa espone davvero quell'azione.
   * Usalo nei template per non mostrare pulsanti che non possono funzionare:
   *   <button v-if="supports('update')" @click="modifica">Modifica</button>
   */
  const supports = (azione) => disponibili.has(azione)

  const metodo = (azione) => {
    if (!disponibili.has(azione)) {
      const presenti = [...disponibili].join(', ') || 'nessuno'

      console.error(
        `[useResource] "${label}": il modulo API non espone ${azione}(). ` +
          `Metodi disponibili: ${presenti}. ` +
          `Se l'azione esiste con un altro nome (es. upload() per i documenti) chiamala direttamente, ` +
          `altrimenti verifica che la rotta esista lato Laravel.`,
      )

      throw new Error(`Azione "${azione}" non disponibile per ${label}.`)
    }
    // ...
  }
```

### La parte interessante non è la fattorizzazione

Fattorizzare il CRUD è routine. La parte istruttiva è come la factory gestisce il fatto che **non tutte le risorse hanno tutti i metodi**.

I documenti non hanno `update`: si caricano con `upload()`, e lato Laravel la rotta non esiste (`apiResource(...)->except(['update'])`). Una sessione di telemedicina si avvia e si chiude, non si modifica.

La prima versione assumeva i cinque metodi. Chiamarne uno assente produceva `client.update is not a function`, un `TypeError` che non dice quale risorsa, quale azione, né cosa esista al suo posto.

La soluzione ha tre parti:

**Rilevamento delle capacità alla creazione.** Il modulo API è un oggetto statico: le sue capacità si determinano una volta sola, non a ogni chiamata.

**`supports()` per il template.** Permette di non *mostrare* un pulsante che non può funzionare — è la differenza fra un errore gestito e un errore evitato:

```vue
<button v-if="supports('update')" @click="modifica">Modifica</button>
```

**Il messaggio diagnostico.** Dice quale risorsa, quale azione manca, quali sono disponibili, e suggerisce dove cercare. Chi lo legge alle undici di sera sa cosa fare senza aprire il debugger.

**L'errore lanciato dentro `run()`.** `metodo()` viene invocato all'interno della gestione standard delle chiamate, così l'eccezione passa dal percorso normale — toast, `errorMessage`, ritorno `null` — invece di propagarsi e rompere il rendering del componente.

### Il principio generale

Un'astrazione che nasconde le differenze fra i casi che unifica produce errori opachi nel punto sbagliato. Un'astrazione che *dichiara* i propri limiti resta utile anche quando non si applica del tutto.

---

# Parte III — Bilancio critico

## Punti di forza

**Separazione dei livelli.** Controller sottili, logica nei service, autorizzazione nelle policy, serializzazione nelle resource. Ogni tipo di modifica ha un solo posto dove essere fatta.

**Copertura dei test.** 159 test backend con 574 asserzioni, 80 test frontend, 4 suite end-to-end. Non solo il percorso felice: i casi limite — medico senza profilo, paziente senza anagrafica, appuntamento già chiuso, giacenza insufficiente — sono coperti perché sono emersi scrivendo i test.

**Decisioni motivate nel codice.** I commenti spiegano il perché. Diversi frammenti analizzati qui sono leggibili proprio grazie a quelle note, che impediscono a una futura pulizia di reintrodurre un bug già risolto.

**Sicurezza a più livelli.** Middleware di area e policy per risorsa, token con scadenza, un token per dispositivo, brute force limitato per email+IP, note cliniche non serializzate per il paziente, download che passano dalle policy anziché essere serviti staticamente.

**Coerenza del contratto API.** Un solo formato d'errore, paginazione uniforme, versionamento nel percorso, risposta JSON anche sulle rotte inesistenti.

## Limiti

**La telemedicina è chat, non video.** Esistono la sessione, il codice stanza, la gestione degli stati e la messaggistica, ma non c'è un canale audio/video: servirebbe un'integrazione WebRTC o un servizio esterno. Il modello dati è predisposto, l'implementazione no.

**Nessun rinnovo automatico del token al 401.** Il refresh è programmato su timer; se il timer non scatta l'utente viene disconnesso. La gestione robusta richiederebbe di intercettare il 401, tentare un refresh e riprovare la richiesta originale.

**Dipendenza da MySQL.** Lo scope `triageOrder()` usa `FIELD()`, che SQLite non implementa. Il progetto non è portabile su altri database senza modifiche.

**Nessuna integrazione di pagamento reale.** I pagamenti sono registrati, con tanto di campo per il riferimento del prestatore di servizi, ma nessun gateway è collegato.

**Nessuna pipeline di integrazione continua.** I test si eseguono a mano. Una configurazione GitHub Actions che li lanciasse a ogni push sarebbe un passo naturale.

**Asset non ottimizzati.** Il logo pesa 1,4 MB e viene caricato sulla pagina di accesso: è il 90% del peso della build. Una compressione a 300px lo porterebbe sotto i 50 KB senza differenze visibili.

**Copertura E2E parziale.** Playwright copre accesso, dashboard, layout responsive e prenotazione. Restano scoperti i flussi di fatturazione, magazzino e firma documentale, che sono verificati solo a livello di API.

## Cosa rifarei diversamente

**Scrivere la specifica OpenAPI durante lo sviluppo, non dopo.** È stata redatta a posteriori leggendo rotte, FormRequest e Resource. Scriverla in parallelo avrebbe fatto emergere prima alcune incoerenze di denominazione fra endpoint simili.

**Introdurre prima i test end-to-end.** Sono arrivati tardi, e due dei bug descritti nella sezione 3 — il login che non rediregeva, il rate limiter che bloccava gli utenti legittimi — sarebbero emersi immediatamente con un test E2E di login già in piedi.

**Valutare prima la portabilità del database.** La dipendenza da `FIELD()` è stata scoperta quando ormai lo scope era usato in più punti. Una funzione portabile fin dall'inizio sarebbe costata poco.

---

*Documento redatto a partire dal codice sorgente del progetto. Tutti i frammenti riportati sono citazioni letterali dei file indicati.*
