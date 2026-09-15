<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan_code', 20);
            $table->string('status', 20);
            $table->string('order_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->json('entitlements')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'plan_code']);
        });

        Schema::create('payment_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id')->unique();
            $table->string('order_id')->nullable()->index();
            $table->string('event_type')->nullable();
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_deliveries');
        Schema::dropIfExists('event_billings');
    }
};
