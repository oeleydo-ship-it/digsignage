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
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->unsignedInteger('sequence');
            $table->string('numbering_period');
            $table->string('status', 32);
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->integer('priority')->default(0);
            $table->unsignedInteger('queue_position')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('counter_id')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('waiting_duration_seconds')->nullable();
            $table->unsignedInteger('serving_duration_seconds')->nullable();
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(
                ['team_id', 'queue_service_id', 'number', 'numbering_period'],
                'queue_tickets_period_number_unique',
            );
            $table->index('team_id');
            $table->index('queue_service_id');
            $table->index('status');
            $table->index('created_at');
            $table->index('location_id');
        });

        Schema::create('queue_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('queue_ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_ticket_events');
        Schema::dropIfExists('queue_tickets');
    }
};
