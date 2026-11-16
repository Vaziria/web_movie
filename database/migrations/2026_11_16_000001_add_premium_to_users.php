<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Premium members get the ad-free player until this time (set by an admin; payments come later).
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('premium_until')->nullable()->index()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['premium_until']);
            $table->dropColumn('premium_until');
        });
    }
};
