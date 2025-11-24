<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // User
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Produits commandés (JSON)
            $table->json('produits')->nullable();

            // Total
            $table->decimal('total', 10, 2);

            // Référence unique
            $table->string('reference')->unique();

            // Status commande
            $table->string('status')->default('en attente'); 
            // en attente | payé | annulé

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};


