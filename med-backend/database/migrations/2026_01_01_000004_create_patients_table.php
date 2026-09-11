<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anagrafica clinica del paziente, separata da users:
 * l'account (autenticazione) e il profilo sanitario hanno cicli di vita diversi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('codice_fiscale', 16)->unique()->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['M', 'F', 'altro'])->nullable();
            $table->string('birth_place')->nullable();
            // Indirizzo di residenza
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 5)->nullable();
            $table->string('postal_code', 10)->nullable();
            // Dati sanitari sintetici: array JSON per allergie e patologie croniche
            $table->string('blood_type', 5)->nullable();
            $table->json('allergies')->nullable();
            $table->json('chronic_conditions')->nullable();
            $table->text('notes')->nullable();
            // Contatto di emergenza
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            // Medico di base assegnato (opzionale)
            $table->foreignId('primary_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
