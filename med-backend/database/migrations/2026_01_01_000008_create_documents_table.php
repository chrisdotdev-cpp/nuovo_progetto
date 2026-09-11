<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivio documentale con flusso di firma e conservazione sostitutiva
 * (consensi, fatture, contratti, certificazioni).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // Un documento puo' essere legato a un paziente (consenso, referto) o solo alla struttura (contratto)
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('category', ['consenso', 'referto', 'fattura', 'contratto', 'certificazione', 'altro'])
                  ->default('altro');
            $table->string('title');
            $table->text('description')->nullable();
            // Storage
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable(); // sha256 per integrita'
            // Flusso di firma
            $table->enum('status', ['bozza', 'da_firmare', 'firmato', 'in_conservazione', 'archiviato'])
                  ->default('da_firmare');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_type', 20)->nullable(); // FEA | FEQ
            $table->timestamp('archived_at')->nullable();
            // Versioning: un documento puo' sostituire una versione precedente
            $table->foreignId('parent_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category']);
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
