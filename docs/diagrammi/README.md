# Diagrammi per la relazione

Versione in **immagine** dei diagrammi documentati in [`../02-modello-dati.md`](../02-modello-dati.md) e [`../04-uml.md`](../04-uml.md).

I documenti in `docs/` usano Mermaid, che GitHub renderizza nel browser. Word e i PDF no: per impaginare la relazione servono file immagine, ed è quello che trovi qui.

## I file

| File | Diagramma | Dove va nella relazione |
|---|---|---|
| `01-architettura.png` | Architettura a tre livelli, dal browser al database | Descrizione degli aspetti progettuali — è il diagramma di apertura |
| `02-er-insieme.png` | Mappa delle 22 entità, raggruppate per area | Modello dei dati, vista d'insieme |
| `03-er-agenda.png` | ER di dettaglio del nucleo agenda, con attributi e cardinalità | Modello dei dati, dettaglio |
| `04a-casi-uso-paziente-medico.png` | Casi d'uso dei due attori clinici | Obiettivi del progetto / contestualizzazione |
| `04b-casi-uso-admin.png` | Casi d'uso amministrativi e condivisi | Idem |
| `05-architettura-livelli.png` | Percorso di una richiesta attraverso i livelli | Aspetti progettuali — il pattern che si ripete |
| `06-sequenza-prenotazione.png` | Prenotazione concorrente con blocco pessimistico | Aspetti progettuali — è il diagramma più significativo |
| `07-sequenza-fatturazione.png` | Chiusura visita ed emissione automatica della fattura | Aspetti progettuali |
| `08-stati-appuntamento.png` | Ciclo di vita dell'appuntamento | Aspetti progettuali |

I file sono PNG a **risoluzione doppia** (2×): larghezza reale 2360-2680 px, quindi restano nitidi anche stampati a piena pagina.

## Come inserirli in Word

Trascina il `.png` nel documento e imposta il testo a capo su **In linea con il testo**. Per la larghezza, 15-16 cm riempie la pagina lasciando i margini; i diagrammi larghi (`06`) stanno meglio in orientamento orizzontale o ridotti a 17 cm.

Ogni immagine va accompagnata da una didascalia numerata — *Figura 1: Architettura dell'applicazione* — e richiamata nel testo: «come mostrato in Figura 1». È una convenzione che i valutatori si aspettano.

## Coerenza cromatica

I colori non sono decorativi, identificano le aree e si mantengono in tutti i diagrammi:

| Colore | Area |
|---|---|
| Grigio-ardesia | Anagrafiche, accessi, elementi trasversali |
| Verde | Area medica |
| Blu | Area paziente |
| Verde acqua | Agenda, appuntamenti, teleconsulti |
| Viola | Farmacia, prescrizioni, Observer |
| Rosso | Richieste dei pazienti, percorsi di errore |
| Ambra | Amministrazione, fatturazione, persistenza |

La stessa entità ha lo stesso colore ovunque compaia: `APPOINTMENTS` è verde acqua nella mappa d'insieme, nell'ER di dettaglio e nel diagramma di stato.
