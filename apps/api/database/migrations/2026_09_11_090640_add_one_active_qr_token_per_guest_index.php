<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS qr_tokens_one_active_per_guest ON qr_tokens (guest_id) WHERE status = 'ACTIVE'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS qr_tokens_one_active_per_guest');
    }
};
