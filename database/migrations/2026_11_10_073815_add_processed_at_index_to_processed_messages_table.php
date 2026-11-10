<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the daily model:prune find expired rows without a full scan.
     */
    public function up(): void
    {
        Schema::table('processed_messages', function (Blueprint $table) {
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('processed_messages', function (Blueprint $table) {
            $table->dropIndex(['processed_at']);
        });
    }
};
