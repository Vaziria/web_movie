<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Visitor accounts: email + password and/or Google login. Admins are users with is_admin.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();            // Google-only accounts have none
            $table->string('google_id', 64)->nullable()->unique()->after('password');
            $table->string('avatar_url', 500)->nullable()->after('google_id');
            $table->timestamp('blocked_at')->nullable()->after('is_admin');
            $table->timestamp('last_active_at')->nullable()->index()->after('blocked_at');
        });

        // Small key/value store for admin-editable settings (Google login, guest limit, contact email).
        // Secrets are stored encrypted with APP_KEY (App\Services\Settings).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // One row per visitor per day for daily/monthly active numbers. visitor = "u:<user id>" or "g:<random id>".
        Schema::create('daily_actives', function (Blueprint $table) {
            $table->date('day');
            $table->string('visitor', 40);
            $table->boolean('is_user');
            $table->primary(['day', 'visitor']);
            $table->index(['day', 'is_user']);
        });

        // Movies a logged-in user opened (admin "movies watched"; a history page later).
        Schema::create('movie_watches', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_at');
            $table->timestamp('last_at');
            $table->primary(['user_id', 'movie_id']);
            $table->index(['user_id', 'last_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_watches');
        Schema::dropIfExists('daily_actives');
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropIndex(['last_active_at']);
            $table->dropColumn(['google_id', 'avatar_url', 'blocked_at', 'last_active_at']);
        });
    }
};
