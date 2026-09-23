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
        Schema::create('queue_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('ticket_prefix', 8);
            $table->text('description')->nullable();
            $table->json('opening_hours')->nullable();
            $table->unsignedInteger('average_service_duration_seconds')->default(300);
            $table->unsignedInteger('max_queue_capacity')->nullable();
            $table->string('numbering_reset', 16)->default('daily');
            $table->unsignedInteger('next_sequence')->default(1);
            $table->unsignedInteger('last_issued')->nullable();
            $table->string('sequence_period')->nullable();
            $table->json('priority_rules')->nullable();
            $table->integer('default_priority')->default(0);
            $table->string('display_color', 7)->default('#2563eb');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'code']);
            $table->unique(['team_id', 'ticket_prefix']);
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
        Schema::dropIfExists('queue_services');
    }
};
