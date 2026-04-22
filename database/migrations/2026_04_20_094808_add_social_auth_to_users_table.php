<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable();       // 'google', 'facebook', 'github'
            $table->string('provider_id')->nullable();    // ID retourné par le provider
            $table->string('avatar')->nullable();         // Photo de profil OAuth
            $table->string('password')->nullable()->change(); // Nullable car pas de password avec OAuth
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_id', 'avatar']);
        });
    }
};
