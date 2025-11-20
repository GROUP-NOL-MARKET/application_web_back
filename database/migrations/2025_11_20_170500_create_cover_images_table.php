<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoverImagesTable extends Migration
{
    public function up()
    {
        Schema::create('cover_images', function (Blueprint $table) {
            $table->id();
            $table->string('path'); // chemin dans storage
            $table->text('description')->nullable();
            $table->boolean('active')->default(false); // si doit apparaitre dans le carousel
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cover_images');
    }
}

