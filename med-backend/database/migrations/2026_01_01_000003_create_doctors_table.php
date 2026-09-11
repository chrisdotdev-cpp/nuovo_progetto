<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profilo professionale del medico.
 * Creata PRIMA di patients perche' patients.primary_doctor_id la referenzia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('specialization', 120);
            $table->string('license_number', 50)->unique()->nullable(); // numero iscrizione albo
            $table->text('bio')->nullable();
            $table->decimal('consultation_fee', 8, 2)->default(0);
            $table->unsignedSmallInteger('slot_duration')->default(30); // durata standard visita in minuti
            $table->boolean('available_online')->default(true);         // abilitato alla telemedicina
            $table->timestamps();
            $table->softDeletes();

            $table->index('specialization');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
