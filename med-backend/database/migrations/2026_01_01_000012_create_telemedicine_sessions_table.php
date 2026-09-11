<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessioni di teleconsulto agganciate a un appuntamento di tipo "telemedicina".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemedicine_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('room_code', 40)->unique();  // codice stanza generato dal service
            $table->enum('status', ['programmata', 'in_corso', 'terminata', 'annullata'])->default('programmata');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Chat testuale della sessione (utile anche come storico post-consulto)
        Schema::create('telemedicine_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telemedicine_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_messages');
        Schema::dropIfExists('telemedicine_sessions');
    }
};
