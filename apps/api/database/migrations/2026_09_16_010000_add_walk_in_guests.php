<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('walk_in')->default(false)->after('event_id');
            $table->string('walk_in_idempotency_key', 64)->nullable()->after('walk_in');
            $table->string('walk_in_request_fingerprint', 64)->nullable()->after('walk_in_idempotency_key');
            $table->unique(['event_id', 'walk_in_idempotency_key']);
            $table->index(['event_id', 'walk_in', 'attendance_status']);
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'walk_in', 'attendance_status']);
            $table->dropUnique(['event_id', 'walk_in_idempotency_key']);
            $table->dropColumn(['walk_in', 'walk_in_idempotency_key', 'walk_in_request_fingerprint']);
        });
    }
};
