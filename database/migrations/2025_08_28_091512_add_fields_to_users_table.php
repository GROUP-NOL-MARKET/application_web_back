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
        Schema::table('users', function (Blueprint $table) {
            $table->string('lastName')->nullable();
            $table->string('firstName')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('profil')->nullable();
            $table->string('name')->nullable()->change();
            $table->date('dateNaissance')->nullable();
            $table->string('secondName')->nullable(); // Deuxième prénom
            $table->string('addresse')->nullable();     // Adresse
            $table->string('genre')->nullable();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lastName', 'firstName', 'phone', 'profil', 'dateNaissance', 'genre', 'secondName', 'addresse']);
        });
    }
};
