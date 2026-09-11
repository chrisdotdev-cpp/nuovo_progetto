<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richieste dei pazienti al medico (triage asincrono).
 * Il medico puo' rispondere, convertire in appuntamento oppure chiudere con prescrizione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // Se null la richiesta e' in coda generale e puo' essere presa in carico da qualsiasi medico
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->enum('priority', ['bassa', 'media', 'alta'])->default('media');
            $table->enum('status', ['aperta', 'in_carico', 'risposta', 'chiusa'])->default('aperta');
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            // Esiti possibili della richiesta
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index('doctor_id');
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_request_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('patient_requests');
    }
};
