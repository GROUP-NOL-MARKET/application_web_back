<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->string('name');
            $table->string('family');
            $table->decimal('price', 10, 2);
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('disponibility');
            $table->string('image');
            $table->integer('quantity');
            $table->integer('selled');
            $table->string('sous_category');
            $table->integer('reste');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
