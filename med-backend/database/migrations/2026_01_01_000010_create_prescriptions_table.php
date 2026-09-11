<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescrizioni (ricette) con righe di dettaglio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();  // numero ricetta generato dal service
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['attiva', 'completata', 'annullata', 'scaduta'])->default('attiva');
            $table->text('notes')->nullable();
            $table->date('issued_at');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['doctor_id', 'issued_at']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            // Il farmaco puo' non essere in anagrafica: si conserva sempre il nome testuale
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('dosage', 100)->nullable();     // 1 compressa
            $table->string('frequency', 100)->nullable();  // 2 volte al giorno
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
