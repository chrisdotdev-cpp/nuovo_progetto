<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifiche in-app. Tabella dedicata (non la notifications polimorfa di Laravel)
 * per avere query semplici, filtri per categoria e link diretto alla risorsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['appuntamento', 'referto', 'prescrizione', 'pagamento', 'sistema', 'messaggio'])
                  ->default('sistema');
            $table->enum('level', ['info', 'successo', 'attenzione', 'errore'])->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();       // rotta frontend da aprire al click
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Query dominante: notifiche non lette dell'utente, ordinate per data
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
