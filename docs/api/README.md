# Documentazione API

Specifica **OpenAPI 3.0.3** delle API del gestionale sanitario.

| File | Contenuto |
|---|---|
| [`openapi.yaml`](openapi.yaml) | La specifica: 57 path, 89 operazioni, 33 schemi |
| [`index.html`](index.html) | Interfaccia Swagger UI per consultarla e provare le chiamate |

## Come consultarla

### Opzione 1 — GitHub Pages (consigliata)

Nelle impostazioni del repository, *Settings → Pages*, imposta la sorgente su
`main` / cartella `/docs`. La documentazione diventa raggiungibile a
`https://<utente>.github.io/<repository>/api/`, senza che chi la legge debba
scaricare o installare nulla.

### Opzione 2 — Server locale

```bash
cd docs/api
python -m http.server 8080
```

e apri `http://localhost:8080`.

Il doppio clic su `index.html` **non** funziona: il file `openapi.yaml` viene
richiesto via `fetch`, che i browser bloccano sul protocollo `file://`.

### Opzione 3 — Editor online

Incolla il contenuto di `openapi.yaml` su [editor.swagger.io](https://editor.swagger.io).
Utile anche per verificare la validità della specifica dopo una modifica.

## Come provare le chiamate

1. Avvia il backend: `cd med-backend && php artisan serve`
2. In Swagger UI esegui `POST /auth/login` con un'utenza demo:

   | Ruolo | Email | Password |
   |---|---|---|
   | Paziente | `paziente@clinica.it` | `password` |
   | Medico | `medico@clinica.it` | `password` |
   | Amministrazione | `admin@clinica.it` | `password` |

3. Copia il `token` dalla risposta
4. Premi **Authorize** in alto a destra e incollalo

Da quel momento ogni richiesta parte autenticata. I permessi dipendono dal ruolo
dell'utenza scelta: con l'utenza paziente le rotte amministrative rispondono `403`,
ed è il comportamento atteso.

## Struttura della specifica

Le operazioni sono raggruppate in 14 tag che corrispondono alle aree funzionali:
autenticazione, dashboard, notifiche, utenti, pazienti, medici, appuntamenti,
cartella clinica, documenti, prescrizioni, farmacia, richieste, telemedicina,
fatturazione.

Gli schemi in `components/schemas` sono divisi in due famiglie:

- **entità** (`User`, `Patient`, `Appointment`, …) — riproducono esattamente
  l'output delle classi `JsonResource` del backend
- **corpi di richiesta** (`StoreAppointmentRequest`, `StorePrescriptionRequest`, …) —
  riproducono i vincoli dichiarati nelle classi `FormRequest`

La specifica è quindi derivata dal codice, non scritta a parte: se una regola di
validazione cambia, il punto da aggiornare è uno solo ed è indicato qui.

## Manutenzione

La specifica è scritta a mano e va aggiornata insieme al codice. Le tre fonti da
tenere allineate sono:

| Cosa cambia | Dove si riflette nella specifica |
|---|---|
| `routes/api.php` | i `paths` |
| `app/Http/Requests/*` | gli schemi `Store*Request` |
| `app/Http/Resources/*` | gli schemi delle entità |

Dopo ogni modifica conviene validare il file:

```bash
python -c "import yaml; yaml.safe_load(open('openapi.yaml', encoding='utf-8')); print('ok')"
```

oppure incollarlo su [editor.swagger.io](https://editor.swagger.io), che segnala anche
gli errori semantici oltre a quelli di sintassi YAML.
