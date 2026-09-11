<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MedicalRecordController;
use App\Http\Controllers\Api\V1\MedicineController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientRequestController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\TelemedicineController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - Gestionale Sanitario
|--------------------------------------------------------------------------
| Autenticazione: Sanctum in modalita' API token (header Authorization: Bearer).
| Ogni rotta protetta passa da 'auth:sanctum' + 'active' (blocca account sospesi).
| I permessi fini sono nelle Policy; i middleware 'role' filtrano solo l'accesso
| alle aree, evitando query inutili.
*/

Route::prefix('v1')->group(function () {

    /* ---------------------- Pubbliche ---------------------- */
    /*
      Il limitatore 'login' ferma solo il flood per IP: il conteggio dei
      tentativi falliti per singolo account sta in AuthController::login,
      che azzera tutto al primo accesso riuscito.
    */
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    /* ---------------------- Protette ----------------------- */
    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        /* --- Sessione e profilo --- */
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::put('password', [AuthController::class, 'updatePassword']);
        });

        /* --- Dashboard: un endpoint, contenuto in base al ruolo --- */
        Route::get('dashboard', DashboardController::class);

        /* --- Notifiche --- */
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
            Route::post('{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::delete('{notification}', [NotificationController::class, 'destroy']);
        });

        /* --- Utenti (amministrazione) --- */
        Route::apiResource('users', UserController::class)->middleware('role:admin');

        /* --- Pazienti --- */
        Route::get('patients/me', [PatientController::class, 'me'])->middleware('role:paziente');
        Route::apiResource('patients', PatientController::class);

        /* --- Medici e disponibilita' --- */
        Route::get('doctors/specializations', [DoctorController::class, 'specializations']);
        Route::get('doctors/{doctor}/availability', [DoctorController::class, 'availability']);
        Route::apiResource('doctors', DoctorController::class);

        /* --- Appuntamenti --- */
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::apiResource('appointments', AppointmentController::class);

        /* --- Cartella clinica --- */
        Route::get('patients/{patient}/timeline', [MedicalRecordController::class, 'timeline']);
        Route::apiResource('medical-records', MedicalRecordController::class)
            ->parameters(['medical-records' => 'medical_record']);

        /* --- Documenti --- */
        Route::prefix('documents')->group(function () {
            Route::get('counters', [DocumentController::class, 'counters']);
            Route::post('sign-bulk', [DocumentController::class, 'signBulk'])->middleware('role:admin');
            Route::get('{document}/download', [DocumentController::class, 'download']);
            Route::post('{document}/sign', [DocumentController::class, 'sign'])->middleware('role:admin');
            Route::post('{document}/archive', [DocumentController::class, 'archive'])->middleware('role:admin');
        });
        Route::apiResource('documents', DocumentController::class)->except(['update']);

        /* --- Prescrizioni --- */
        Route::apiResource('prescriptions', PrescriptionController::class);

        /* --- Farmacia / magazzino --- */
        Route::prefix('medicines')->group(function () {
            Route::get('summary', [MedicineController::class, 'summary']);
            Route::get('{medicine}/movements', [MedicineController::class, 'movements']);
            Route::post('{medicine}/movements', [MedicineController::class, 'move'])->middleware('role:admin');
        });
        Route::apiResource('medicines', MedicineController::class);

        /* --- Richieste paziente (triage) --- */
        Route::prefix('requests')->group(function () {
            Route::get('attachments/{attachment}/download', [PatientRequestController::class, 'downloadAttachment']);
            Route::post('{request}/claim', [PatientRequestController::class, 'claim'])->middleware('role:medico');
            Route::post('{request}/respond', [PatientRequestController::class, 'respond'])->middleware('role:medico');
            Route::post('{request}/convert-appointment', [PatientRequestController::class, 'convertToAppointment'])
                ->middleware('role:medico');
        });
        Route::apiResource('requests', PatientRequestController::class)
            ->parameters(['requests' => 'request']);

        /* --- Telemedicina --- */
        Route::prefix('telemedicine')->group(function () {
            Route::get('/', [TelemedicineController::class, 'index']);
            Route::post('/', [TelemedicineController::class, 'store']);
            Route::get('{session}', [TelemedicineController::class, 'show']);
            Route::post('{session}/join', [TelemedicineController::class, 'join']);
            Route::post('{session}/start', [TelemedicineController::class, 'start'])->middleware('role:medico');
            Route::post('{session}/end', [TelemedicineController::class, 'end'])->middleware('role:medico');
            Route::get('{session}/messages', [TelemedicineController::class, 'messages']);
            Route::post('{session}/messages', [TelemedicineController::class, 'sendMessage']);
        });

        /* --- Fatturazione --- */
        Route::get('invoices/report', [InvoiceController::class, 'report'])->middleware('role:admin');
        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
        Route::apiResource('invoices', InvoiceController::class);
    });
});

// Fallback: qualsiasi rotta API inesistente risponde JSON, mai HTML
Route::fallback(fn () => response()->json(['message' => 'Endpoint non trovato.'], 404));
