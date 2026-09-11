<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anagrafica farmaci + magazzino interno della clinica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('active_ingredient')->nullable(); // principio attivo
            $table->string('aic_code', 20)->unique()->nullable();
            $table->string('form', 60)->nullable();          // compresse, fiale, sciroppo
            $table->string('dosage', 60)->nullable();        // 500mg
            $table->string('manufacturer')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('requires_prescription')->default(true);
            // Magazzino
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock')->default(10);       // soglia di riordino
            $table->string('batch', 60)->nullable();         // lotto
            $table->date('expiry_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('expiry_date');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // chi ha eseguito il movimento
            $table->enum('type', ['carico', 'scarico', 'reso', 'scaduto', 'rettifica']);
            $table->integer('quantity');            // sempre positivo, il segno lo determina il type
            $table->integer('stock_after');         // giacenza risultante, per audit
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['medicine_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('medicines');
    }
};
