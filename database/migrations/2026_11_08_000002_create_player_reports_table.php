<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('server')->default(1);
            $table->string('reason', 20)->default('not_loading');
            $table->char('ip_hash', 64);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['movie_id', 'resolved_at']);
            $table->index(['resolved_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_reports');
    }
};
