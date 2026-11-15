<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Browsers that allowed notifications (Web Push API). No account, no IP: just where to send.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->char('endpoint_hash', 64)->unique();   // sha256 of endpoint (endpoints are too long to index)
            $table->text('endpoint');
            $table->string('public_key', 200);              // keys.p256dh
            $table->string('auth_token', 100);              // keys.auth
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->string('locale', 5)->default('id');     // language of the notifications
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // One notification sent to every subscriber: a new movie, several new movies, or a custom message.
        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);                       // movie | movies | custom
            $table->foreignId('movie_id')->nullable()->constrained()->nullOnDelete();
            $table->json('movie_ids')->nullable();            // kind=movies
            $table->string('title', 100)->nullable();         // kind=custom
            $table->string('body', 200)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('status', 10)->default('queued');  // queued | sending | sent
            $table->unsignedBigInteger('cursor')->default(0); // last subscription id sent (resume after a crash)
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('expired')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->boolean('push_notify')->default(true)->after('published_at');
            $table->timestamp('push_notified_at')->nullable()->after('push_notify');
        });

        // Movies that are already live never trigger a notification; scheduled and draft ones do when they go live.
        DB::table('movies')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->update(['push_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['push_notify', 'push_notified_at']);
        });
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('push_subscriptions');
    }
};
