<?php

/*
|--------------------------------------------------------------------------
| Fatturazione automatica
|--------------------------------------------------------------------------
|
| Parametri del ciclo "appuntamento completato -> fattura -> incasso".
| Tenuti in config e non nel codice cosi' la segreteria puo' cambiare aliquota
| o termini di pagamento senza toccare i Service.
|
*/

return [

    /*
     | Se false l'osservatore non emette nulla: la fattura resta un atto manuale
     | dal pannello Finanziario. Utile in staging o durante le migrazioni dati.
     */
    'auto_invoice' => env('BILLING_AUTO_INVOICE', true),

    /*
     | Stato con cui nasce la fattura automatica.
     | 'emessa' -> visibile subito al paziente in "Pagamenti".
     | 'bozza'  -> resta interna finche' l'admin non la conferma.
     */
    'auto_invoice_status' => env('BILLING_AUTO_INVOICE_STATUS', 'emessa'),

    /*
     | Prestazioni sanitarie: in Italia sono esenti IVA (art. 10 DPR 633/72),
     | quindi il default e' 0. Si alza per le prestazioni non esenti.
     */
    'tax_rate' => (float) env('BILLING_TAX_RATE', 0),

    /* Giorni di dilazione: la fattura scade a issue_date + due_days. */
    'due_days' => (int) env('BILLING_DUE_DAYS', 30),

    /*
     | Rete di sicurezza quando il medico ha consultation_fee = 0
     | (profilo non ancora compilato): evita fatture da 0 euro.
     | A 0 la fattura non viene generata affatto.
     */
    'fallback_fee' => (float) env('BILLING_FALLBACK_FEE', 0),

    /* Descrizione della riga di fattura, per tipo di appuntamento. */
    'descriptions' => [
        'visita'       => 'Visita specialistica',
        'controllo'    => 'Visita di controllo',
        'telemedicina' => 'Teleconsulto',
        'urgenza'      => 'Visita urgente',
    ],

    /*
     | Un appuntamento chiuso come "assente" (no-show) genera comunque fattura?
     | Molte strutture addebitano una penale: qui si sceglie la policy.
     */
    'invoice_no_show' => env('BILLING_INVOICE_NO_SHOW', false),
];
