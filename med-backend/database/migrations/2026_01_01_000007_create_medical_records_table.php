<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voci della cartella clinica: diagnosi, referti, esami, note di visita.
 * Append-only per legge: si aggiorna solo entro una finestra temporale (gestita in Policy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['diagnosi', 'referto', 'esame', 'nota', 'vaccinazione', 'intervento'])
                  ->default('nota');
            $table->string('title');
            $table->text('description')->nullable();
            // Parametri vitali / valori di laboratorio strutturati
            $table->json('vitals')->nullable();
            $table->string('icd10_code', 10)->nullable(); // codifica diagnosi
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'recorded_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
