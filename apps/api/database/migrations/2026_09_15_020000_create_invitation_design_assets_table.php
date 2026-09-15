<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_design_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->unique();
            $table->string('file_url');
            $table->string('original_name');
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'position']);
            $table->index(['event_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_design_assets');
    }
};
