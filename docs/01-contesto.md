# Contesto e servizio

Descrizione dell'organizzazione committente, del contesto in cui opera e del servizio che il gestionale sanitario è chiamato a supportare.

> **Nota.** *Medica Digital* è un'organizzazione **di fantasia**, costruita come committente verosimile per questo project work. Nomi, dati e numeri riportati nel documento sono inventati; il contesto di settore descritto è invece reale.

---

## 1. L'organizzazione

**Medica Digital** è un poliambulatorio privato di medie dimensioni. Eroga prestazioni specialistiche ambulatoriali senza ricovero: visite, controlli, esami diagnostici di primo livello, teleconsulti.

| | |
|---|---|
| **Ragione sociale** | Medica Digital S.r.l. |
| **Forma** | Poliambulatorio privato, prestazioni in regime solvente |
| **Personale sanitario** | 12 medici specialisti, in prevalenza liberi professionisti a contratto |
| **Personale amministrativo** | 4 addetti fra segreteria e direzione |
| **Pazienti attivi** | Circa 3.000 |
| **Prestazioni** | Circa 120 al giorno |
| **Specializzazioni** | Medicina generale, cardiologia, dermatologia, ginecologia, ortopedia, endocrinologia |

La struttura **non ha posti letto** e non eroga prestazioni in convenzione con il Servizio Sanitario Nazionale: il paziente paga direttamente, o tramite polizza assicurativa e fondo integrativo. È una precisazione che ha conseguenze concrete sul software — spiegate nella sezione 5 — e che definisce il perimetro del progetto.

Il nome e il marchio esprimono il posizionamento che la struttura si è data: la croce medica attraversata da tracce di circuito, e il pittogramma che accosta il simbolo sanitario tradizionale a quello dell'elettronica. *Medica Digital* non si presenta come uno studio medico che ha aggiunto un sito, ma come una clinica il cui rapporto con il paziente passa in modo naturale dal digitale.

## 2. Il contesto di settore

La sanità privata italiana attraversa una fase di crescita, alimentata dall'allungamento dei tempi di attesa nel servizio pubblico e dalla diffusione di polizze e fondi integrativi aziendali. Per una struttura come Medica Digital ciò significa un bacino in espansione, ma anche una concorrenza che non si gioca più solo sulla qualità clinica.

Il paziente che sceglie una struttura privata **paga**, e chi paga confronta. Confronta i tempi di attesa, ma anche l'esperienza complessiva: quanto è semplice prenotare, se può farlo di sera dal telefono anziché chiamare in orario di segreteria, se riceve il referto in giornata o deve tornare a ritirarlo, se sa quanto spenderà prima di presentarsi.

È il motivo per cui la traccia di questo project work osserva che le imprese sanitarie *«adottano architetture API-based, che permettono l'integrazione tra diversi sistemi e l'accesso ai servizi da parte di applicazioni web e mobile»*. Non è una scelta tecnologica di moda: è la risposta a un'aspettativa che il paziente porta con sé da altri settori — bancario, assicurativo, viaggi — e che applica anche alla salute.

A questo si aggiunge un vincolo normativo stringente. I dati sanitari sono una **categoria particolare** ai sensi del GDPR: richiedono misure tecniche e organizzative rafforzate, tracciabilità degli accessi, conservazione per periodi stabiliti e cancellazione che non può essere immediata né totale. Un gestionale sanitario non è un gestionale come un altro, e alcune scelte progettuali documentate altrove in questo repository — il soft delete su tutte le entità cliniche, il registro delle attività, i download che passano dalle policy invece di essere serviti staticamente — discendono direttamente da qui.

## 3. Il problema

Fino all'avvio del progetto, Medica Digital operava con strumenti separati e non comunicanti:

- **Agenda** su un gestionale acquistato anni prima, accessibile solo dai computer della segreteria
- **Prenotazioni** per telefono, negli orari di apertura della segreteria
- **Referti** consegnati a mano o inviati via email come allegati
- **Contabilità** su un applicativo distinto, alimentato a mano a fine giornata
- **Magazzino farmaci** su foglio di calcolo
- **Teleconsulti** su una piattaforma di videochiamata generica, senza traccia nel fascicolo del paziente

La conseguenza non era tanto l'inefficienza quanto la **frammentazione dell'informazione**. Un medico che apriva la cartella di un paziente non vedeva le prestazioni pagate; la segreteria che emetteva una fattura doveva ricontrollare a mano che la visita fosse stata effettivamente erogata; un referto inviato per email usciva dal perimetro di controllo della struttura nel momento stesso in cui partiva.

Tre criticità in particolare hanno motivato l'intervento.

**Le prenotazioni perse.** La segreteria riceve chiamate solo in orario di apertura. Le richieste che arrivano fuori orario si trasformano in messaggi in segreteria telefonica richiamati il giorno dopo — quando il paziente, spesso, ha già prenotato altrove.

**La doppia prenotazione.** Con un'agenda gestita da più operatori, due addetti che parlano al telefono contemporaneamente possono assegnare lo stesso orario. L'errore emerge in sala d'attesa, con due pazienti e un medico solo.

**La riconciliazione contabile.** Ogni sera qualcuno confrontava a mano l'elenco delle visite erogate con le fatture emesse. Un'ora di lavoro ripetitivo, e un margine di errore che cresceva nelle giornate piene.

## 4. Il servizio

Il gestionale sostituisce gli strumenti separati con un'unica applicazione web, accessibile da qualsiasi dispositivo con un browser, organizzata in tre aree.

### Per il paziente

Prenotazione autonoma **ventiquattro ore su ventiquattro**, scegliendo specialista, giorno e orario fra le disponibilità reali. Consultazione della propria cartella clinica in ordine cronologico, con download dei referti. Invio di richieste di consulto con allegati, per le domande che non richiedono una visita. Partecipazione ai teleconsulti. Pagamento delle prestazioni e consultazione dello storico delle fatture.

### Per il medico

Agenda personale del giorno e della settimana. Elenco dei propri assistiti con le relative cartelle cliniche. Redazione di referti e diagnosi. Emissione di prescrizioni con numerazione automatica. Triage delle richieste dei pazienti, con la possibilità di rispondere, di emettere una prescrizione o di convertire la richiesta in un appuntamento. Gestione del proprio orario settimanale e delle assenze.

### Per l'amministrazione

Gestione di account e personale. Vista completa delle prenotazioni della struttura. Archivio documentale con flusso di firma. Magazzino farmaceutico con movimenti tracciati e soglie di riordino. Fatturazione e reportistica sugli incassi.

### Il punto di contatto fra le tre aree

Il valore non sta nelle singole funzioni ma nel fatto che appartengano allo stesso sistema. Quando un medico chiude una visita, la fattura viene emessa automaticamente e compare sia nella dashboard amministrativa sia nell'area pagamenti del paziente: nessuno la trascrive, nessuno la dimentica. Quando un paziente prenota, il medico lo vede nella propria agenda ed entrambi ricevono una notifica. Quando una richiesta di consulto si trasforma in un appuntamento, il collegamento fra le due cose resta registrato.

È la differenza fra digitalizzare singole attività e digitalizzare un processo.

## 5. Perché un'architettura API-based

La scelta di separare nettamente il backend — che espone API REST — dal frontend — un'applicazione che le consuma — risponde a tre esigenze della struttura, non a una preferenza tecnica.

**Un'app mobile senza riscrivere nulla.** L'applicazione web è responsive e utilizzabile da telefono, ma Medica Digital prevede di pubblicare un'app nativa. Con un backend che espone API, l'app sarà un secondo consumatore dello stesso servizio: nessuna logica da duplicare, nessun rischio che le due versioni divergano.

**L'integrazione con sistemi esterni.** Una struttura sanitaria non vive isolata. Il laboratorio analisi esterno deve poter depositare i referti; il gestore dei pagamenti deve notificare gli incassi; il commercialista deve poter estrarre i dati di fatturazione. Ogni integrazione, in un sistema monolitico, sarebbe un innesto fragile; con un'API versionata è un consumatore in più.

**Il controllo degli accessi in un punto solo.** I dati sanitari richiedono che ogni accesso sia autorizzato e tracciabile. Concentrando la logica dietro l'API, la regola — *il paziente vede solo i propri dati, il medico solo i propri assistiti* — è scritta una volta e vale per ogni client presente e futuro. Un frontend che accedesse direttamente al database dovrebbe reimplementarla, e sarebbe solo questione di tempo prima che le due versioni divergano.

## 6. Il perimetro del progetto

Definire cosa il sistema **non** fa è parte della descrizione del servizio: un perimetro dichiarato è una scelta, un perimetro taciuto è una lacuna.

| Fuori perimetro | Motivo |
|---|---|
| Gestione di posti letto e ricoveri | La struttura è ambulatoriale, non ha degenza |
| Integrazione con il Fascicolo Sanitario Elettronico | Richiede accreditamento regionale e certificazioni fuori dalla portata del progetto |
| Ricetta dematerializzata SSN | Le prescrizioni sono in regime privato |
| Refertazione di immagini diagnostiche (DICOM/PACS) | Gli esami di imaging sono esternalizzati |
| Fatturazione elettronica verso SdI | I dati sono gestiti, la trasmissione resta al commercialista |
| Canale audio/video dei teleconsulti | Il sistema gestisce sessione, stanza e chat; il flusso multimediale richiederebbe un'integrazione WebRTC |
| Contabilità generale | Il sistema fattura e registra gli incassi, non tiene la prima nota |

L'ultimo punto della tabella è il limite più visibile rispetto alle aspettative che la parola "telemedicina" genera, ed è dichiarato anche nel bilancio critico del documento sul processo di sviluppo.

## 7. Sintesi

Medica Digital è una clinica privata ambulatoriale di medie dimensioni che ha scelto di fare della relazione digitale con il paziente un elemento del proprio posizionamento, non un accessorio.

Il gestionale realizzato sostituisce sei strumenti separati con un'unica applicazione full-stack costruita su API REST, che serve tre categorie di utenti con permessi distinti e collega fra loro processi che prima venivano riconciliati a mano. L'architettura scelta risponde a esigenze dichiarate: aprire la strada a un'app nativa, rendere possibili integrazioni con sistemi esterni, e concentrare in un solo punto il controllo degli accessi a dati che la normativa classifica fra quelli di categoria particolare.

---

*Le scelte tecniche discusse in questo documento sono documentate in [`02-modello-dati.md`](02-modello-dati.md), [`03-processo-e-snippet.md`](03-processo-e-snippet.md), [`04-uml.md`](04-uml.md) e [`05-test-funzionale.md`](05-test-funzionale.md).*
