<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            // Extra players shown as "Server 2, 3, ..." (Server 1 is embed_url).
            $table->json('backup_embed_urls')->nullable()->after('embed_url');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('backup_embed_urls');
        });
    }
};
