<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appuntamenti / prenotazioni. Il vincolo di non sovrapposizione e' applicato
 * a livello di Service (AppointmentService), non di DB, per poter gestire messaggi chiari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->enum('type', ['visita', 'controllo', 'telemedicina', 'urgenza'])->default('visita');
            $table->enum('status', ['in_attesa', 'confermato', 'completato', 'annullato', 'assente'])
                  ->default('in_attesa');
            $table->string('reason')->nullable();      // motivo della visita indicato dal paziente
            $table->text('notes')->nullable();         // note interne del medico
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indice composto: la query piu' frequente e' "agenda del medico per data"
            $table->index(['doctor_id', 'scheduled_at']);
            $table->index(['patient_id', 'scheduled_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
