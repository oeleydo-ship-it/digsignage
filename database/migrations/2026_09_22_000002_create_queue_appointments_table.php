<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('reference', 32);
            $table->string('status', 24)->default('scheduled');
            $table->string('check_in_source', 24)->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique('reference');
            $table->index(['team_id', 'scheduled_at']);
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_appointments');
    }
};
