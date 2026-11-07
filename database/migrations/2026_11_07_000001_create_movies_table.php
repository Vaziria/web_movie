<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('synopsis')->nullable();
            $table->string('poster_url', 2048)->nullable();
            $table->string('embed_url', 2048);
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedBigInteger('views')->default(0)->index();
            $table->decimal('rating_avg', 4, 2)->default(0)->index();
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();

            $table->index('created_at');
            $table->index('title');
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->char('voter_id', 64);
            $table->char('ip_hash', 64);
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['movie_id', 'voter_id']);
            $table->index(['movie_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('movies');
    }
};
