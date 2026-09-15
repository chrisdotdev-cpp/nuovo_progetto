# Documentazione

Questa cartella contiene la documentazione tecnica del gestionale sanitario: contesto, modello dei dati, processo di sviluppo, diagrammi UML, specifica delle API e verifica funzionale.

Il **codice sorgente non sta qui**: il backend Laravel è in [`../med-backend/`](../med-backend/), il frontend Vue in [`../med-frontend/`](../med-frontend/). Per installare ed eseguire il progetto, il riferimento è il [README principale](../README.md).

---

## Da dove iniziare

**Se vuoi capire cosa fa il software e perché** — leggi [`01-contesto.md`](01-contesto.md), poi il [README principale](../README.md). Bastano dieci minuti.

**Se vuoi valutare il lavoro tecnico** — l'ordine di lettura è quello della numerazione: contesto → modello dei dati → processo e codice → UML → test. Ogni documento è autosufficiente, ma il secondo dà per noti i termini del primo.

**Se cerchi un punto specifico** — la tabella qui sotto dice quale documento risponde a cosa.

**Se stai provando l'API** — apri [`api/`](api/): c'è la specifica OpenAPI e l'interfaccia per interrogarla.

---

## I documenti

| # | Documento | Contenuto | Parole |
|---|---|---|---|
| 01 | [`01-contesto.md`](01-contesto.md) | L'organizzazione committente, il contesto di settore, il problema, il perimetro del progetto e la motivazione dell'architettura API-based | 1.548 |
| 02 | [`02-modello-dati.md`](02-modello-dati.md) | Modello concettuale e relazionale: 6 diagrammi ER per area funzionale, schema relazionale completo, vincoli di integrità, scelte progettuali e denormalizzazioni dichiarate | 3.376 |
| 03 | [`03-processo-e-snippet.md`](03-processo-e-snippet.md) | Resoconto del processo di sviluppo in 8 fasi, difficoltà incontrate con la relativa diagnosi, 6 snippet di codice commentati, bilancio critico con i limiti dichiarati | 5.908 |
| 04 | [`04-uml.md`](04-uml.md) | 17 diagrammi UML: casi d'uso, classi, sequenza, stati, componenti | 3.822 |
| 05 | [`05-test-funzionale.md`](05-test-funzionale.md) | Strategia di verifica, risultati di PHPUnit, Vitest e Playwright, evidenze per caso d'uso con 15 screenshot, copertura e lacune | 3.308 |

Totale: circa **18.000 parole**.

## Le sottocartelle

| Cartella | Contenuto |
|---|---|
| [`api/`](api/) | Specifica **OpenAPI 3.0.3** dell'API REST — 57 percorsi, 89 operazioni, 33 schemi. `openapi.yaml` è la sorgente; `index.html` è un'interfaccia Swagger UI che la rende navigabile nel browser. Istruzioni in [`api/README.md`](api/README.md) |
| [`diagrammi/`](diagrammi/) | I 9 diagrammi principali in formato immagine (PNG a risoluzione doppia), da inserire nella relazione: Word e PDF non renderizzano Mermaid. Corrispondenza diagramma → sezione in [`diagrammi/README.md`](diagrammi/README.md) |
| [`screenshots/`](screenshots/) | 18 schermate dell'applicazione, generate automaticamente dai test Playwright su due viewport (desktop 1440×900 e mobile Pixel 7). Non sono catturate a mano: si rigenerano a ogni esecuzione dei test |

---

## Corrispondenza con i punti della traccia

| Il project work richiede | Documento |
|---|---|
| Descrizione dell'azienda e del servizio | `01-contesto.md` |
| Modello dei dati e schema relazionale | `02-modello-dati.md` |
| Resoconto del processo di sviluppo e snippet di codice significativi | `03-processo-e-snippet.md` |
| Modellazione UML del sistema | `04-uml.md` |
| Descrizione delle API esposte | `api/openapi.yaml` + sezione *API* del [README principale](../README.md#api) |
| Test funzionale con evidenze | `05-test-funzionale.md` |

---

## Convenzioni

**I diagrammi esistono in due forme.** Dentro i documenti `.md` sono scritti in **Mermaid**, che GitHub renderizza direttamente nel browser: si leggono senza scaricare nulla e si modificano come testo. In [`diagrammi/`](diagrammi/) gli stessi diagrammi sono disponibili come immagini, perché Mermaid non funziona in Word né nei PDF.

**I colori non sono decorativi.** Identificano le aree funzionali e restano coerenti fra i diagrammi: la stessa entità ha lo stesso colore ovunque compaia. La legenda è in [`diagrammi/README.md`](diagrammi/README.md).

**Gli snippet sono codice reale.** I frammenti in `03-processo-e-snippet.md` sono estratti dai file del repository, non riscritti per la documentazione: il percorso del file sorgente è indicato sopra ciascuno.

**I numeri sono verificabili.** Conteggi di test, tabelle, endpoint e modelli riportati nei documenti derivano dall'esecuzione delle suite e dall'ispezione del codice, non da stime.

---

## Nota sull'organizzazione committente

**Medica Digital** è un'organizzazione **di fantasia**, costruita come committente verosimile per il project work. Nomi, dati e numeri che la riguardano sono inventati; il contesto di settore descritto in `01-contesto.md` è invece reale e documentato.

---

[← Torna al README principale](../README.md)
