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
        Schema::create('queue_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status', 16)->default('open');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'code']);
            $table->index('team_id');
            $table->index('location_id');
            $table->index('status');
            $table->index('assigned_user_id');
        });

        Schema::create('queue_counter_service', function (Blueprint $table) {
            $table->foreignId('counter_id')->constrained('queue_counters')->cascadeOnDelete();
            $table->foreignId('queue_service_id')->constrained()->cascadeOnDelete();
            $table->primary(['counter_id', 'queue_service_id']);
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->foreign('counter_id')->references('id')->on('queue_counters')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropForeign(['counter_id']);
        });

        Schema::dropIfExists('queue_counter_service');
        Schema::dropIfExists('queue_counters');
    }
};
