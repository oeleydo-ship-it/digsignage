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
        Schema::create('content_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('action', 32);
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'approvable_type', 'approvable_id'], 'content_approvals_subject');
            $table->index(['team_id', 'to_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_approval_events');
    }
};
