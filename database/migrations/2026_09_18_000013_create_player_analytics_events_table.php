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
        Schema::create('player_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('message')->nullable();
            $table->string('player_version', 64)->nullable();
            $table->unsignedBigInteger('storage_free')->nullable();
            $table->unsignedBigInteger('storage_total')->nullable();
            $table->boolean('storage_warning')->default(false);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['team_id', 'type', 'recorded_at']);
            $table->index(['team_id', 'screen_id', 'recorded_at']);
            $table->index(['team_id', 'location_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_analytics_events');
    }
};
