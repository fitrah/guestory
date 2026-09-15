<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('terms_version')->nullable()->after('status');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
            $table->string('privacy_version')->nullable()->after('terms_accepted_at');
            $table->timestamp('privacy_accepted_at')->nullable()->after('privacy_version');
            $table->timestamp('activated_at')->nullable()->after('email_verified_at');
            $table->foreignId('provisioned_by')->nullable()->after('activated_at')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->whereIn('email', [
            'fitrahajah@gmail.com',
            'fahrurrozirahmawan@gmail.com',
        ])->update(['role' => 'SUPERADMIN', 'email_verified_at' => now(), 'activated_at' => now()]);
        DB::table('users')->where('role', 'ADMIN')->update(['role' => 'EVENT_OWNER']);
        DB::statement('UPDATE users SET email = LOWER(TRIM(email))');
        DB::statement('CREATE UNIQUE INDEX users_email_normalized_unique ON users (LOWER(email))');

        Schema::create('account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_tokens');
        DB::statement('DROP INDEX IF EXISTS users_email_normalized_unique');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provisioned_by');
            $table->dropColumn(['terms_version', 'terms_accepted_at', 'privacy_version', 'privacy_accepted_at', 'activated_at']);
            $table->string('password')->nullable(false)->change();
        });
        DB::table('users')->where('role', 'EVENT_OWNER')->update(['role' => 'ADMIN']);
        DB::table('users')->where('role', 'SUPERADMIN')->update(['role' => 'ADMIN']);
    }
};
