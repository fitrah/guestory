<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('guest_code');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('category')->default('Other');
            $table->string('group_name')->nullable();
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->string('table_number')->nullable();
            $table->string('rsvp_status')->default('PENDING');
            $table->string('invitation_status')->default('DRAFT');
            $table->string('attendance_status')->default('NOT_CHECKED_IN');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'guest_code']);
            $table->index(['event_id', 'name']);
            $table->index(['event_id', 'category']);
            $table->index(['event_id', 'rsvp_status', 'attendance_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
