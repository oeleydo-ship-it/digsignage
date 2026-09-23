<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('channel', 20);
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('minutes_before')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'event', 'channel']);
        });

        Schema::create('queue_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_notification_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('channel', 20);
            $table->string('destination')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('dedupe_key')->unique();
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_notification_deliveries');
        Schema::dropIfExists('queue_notification_rules');
    }
};
