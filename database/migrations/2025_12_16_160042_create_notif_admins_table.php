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
        Schema::create('notif_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();
            $table->string('image');
            $table->string('sender')->nullable();
            $table->string('title');
            $table->string('content');
            $table->string('type');
            $table->boolean('can_act');
            $table->integer('related_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notif_admins');
    }
};
