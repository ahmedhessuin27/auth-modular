<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency is tracked per consuming queue: one event fans out to every
     * subscribing module with the same message id, and each must handle it.
     */
    public function up(): void
    {
        Schema::table('processed_messages', function (Blueprint $table) {
            $table->string('queue')->after('id');
            $table->dropUnique(['message_id']);
            $table->unique(['queue', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('processed_messages', function (Blueprint $table) {
            $table->dropUnique(['queue', 'message_id']);
            $table->unique('message_id');
            $table->dropColumn('queue');
        });
    }
};
