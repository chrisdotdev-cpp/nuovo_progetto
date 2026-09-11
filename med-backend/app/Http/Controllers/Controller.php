<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Classe base dei controller dell'applicazione.
 *
 * PERCHE' ESTENDE Illuminate\Routing\Controller
 * ---------------------------------------------
 * Lo scaffolding di Laravel 11+ genera un `abstract class Controller {}` isolato,
 * che NON eredita piu' da Illuminate\Routing\Controller. Di conseguenza il metodo
 * `middleware()` non esiste piu' sui controller.
 *
 * Il problema e' che authorizeResource() lo usa internamente:
 *
 *   // vendor/laravel/framework/.../AuthorizesRequests.php:104
 *   $this->middleware($middlewareName, $options)->only($methods);
 *
 * Tutti i dieci controller API chiamano authorizeResource() nel costruttore.
 * Senza questa estensione ognuno di essi muore con
 *
 *   Call to undefined method App\Http\Controllers\Api\V1\...::middleware()
 *
 * ...al momento della risoluzione della rotta: HTTP 500 su OGNI endpoint di
 * risorsa. Login, dashboard e notifiche continuavano a funzionare solo perche'
 * sono gli unici controller che non usano authorizeResource().
 *
 * Illuminate\Routing\Controller fornisce middleware() e getMiddleware(), e
 * Route::controllerMiddleware() continua a leggere getMiddleware() anche in
 * Laravel 12 (vedi Route.php:1131), quindi le policy tornano ad applicarsi.
 *
 * Alternativa scartata: implementare HasMiddleware con middleware statico.
 * Avrebbe richiesto di riscrivere a mano la mappa abilita'/metodo in tutti e
 * dieci i controller, duplicando cio' che authorizeResource() gia' deriva.
 */
abstract class Controller extends BaseController
{
    // Abilita $this->authorize() e authorizeResource() in tutti i controller
    use AuthorizesRequests, ValidatesRequests;

    /*
    |--------------------------------------------------------------------------
    | Risoluzione del profilo di dominio
    |--------------------------------------------------------------------------
    | Il ruolo sta in `users`, il profilo in `doctors` / `patients`. Le due cose
    | possono disallinearsi: un account creato a mano (phpMyAdmin, import SQL
    | parziale, registrazione senza profilo) ha role='medico' ma nessun record
    | in `doctors`.
    |
    | Il codice scriveva $request->user()->doctor->id, che in quel caso esplode
    | con "Attempt to read property id on null" -> 500 opaco lato Vue. Questi
    | due helper trasformano l'incidente in un 409 che dice cosa manca e come
    | rimediare, e il messaggio arriva intatto al toast del frontend.
    */

    /** Id del profilo medico dell'utente autenticato. */
    protected function doctorIdOf(User $user): int
    {
        $id = $user->doctor?->id;

        abort_if($id === null, 409, "L'account {$user->email} ha ruolo medico ma non ha un profilo "
            .'in `doctors`. Esegui `php artisan med:diagnosi --fix` per crearlo.');

        return $id;
    }

    /** Id del profilo paziente dell'utente autenticato. */
    protected function patientIdOf(User $user): int
    {
        $id = $user->patient?->id;

        abort_if($id === null, 409, "L'account {$user->email} ha ruolo paziente ma non ha un profilo "
            .'in `patients`. Esegui `php artisan med:diagnosi --fix` per crearlo.');

        return $id;
    }
}
