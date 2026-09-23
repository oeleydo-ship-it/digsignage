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
        Schema::create('queue_kiosks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('branding')->nullable();
            $table->boolean('printer_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('token', 64)->unique();
            $table->string('pin')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('location_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_kiosks');
    }
};
