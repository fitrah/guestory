<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('q_r_tokens') && ! Schema::hasTable('qr_tokens')) {
            Schema::rename('q_r_tokens', 'qr_tokens');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('qr_tokens') && ! Schema::hasTable('q_r_tokens')) {
            Schema::rename('qr_tokens', 'q_r_tokens');
        }
    }
};
