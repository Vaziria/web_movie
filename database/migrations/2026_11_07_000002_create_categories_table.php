<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('category_movie', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'movie_id']);
            $table->index('movie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_movie');
        Schema::dropIfExists('categories');
    }
};
