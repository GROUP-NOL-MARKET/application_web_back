
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Peut être lié ensuite à une commande
            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            // ID transaction FedaPay
            $table->string('transaction_id')->nullable()->unique();

            // Téléphone utilisé pour le paiement
            $table->string('phone')->nullable();

            // Montant payé
            $table->decimal('amount', 10, 2);

            // Statut du paiement
            $table->string('status')->default('pending');
            // pending | approved | canceled | declined

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
