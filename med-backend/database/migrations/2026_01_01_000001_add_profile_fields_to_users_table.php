<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estende la tabella users con ruolo, stato e dati di contatto.
 * Il ruolo e' una enum semplice: le autorizzazioni fini sono gestite dalle Policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ruolo applicativo: guida sidebar, rotte e policy
            $table->enum('role', ['admin', 'medico', 'paziente'])->default('paziente')->after('email');
            // Stato account: consente la sospensione senza cancellare i dati clinici
            $table->enum('status', ['attivo', 'sospeso', 'in_attesa'])->default('attivo')->after('role');
            $table->string('phone', 30)->nullable()->after('status');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('avatar_path');
            // Soft delete: obbligatorio in ambito sanitario, i dati non si eliminano davvero
            $table->softDeletes();

            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['role', 'status', 'phone', 'avatar_path', 'last_login_at']);
        });
    }
};
