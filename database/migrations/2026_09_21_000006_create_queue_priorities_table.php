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
        Schema::create('queue_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->integer('weight')->default(0);
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->unique(['team_id', 'code']);
            $table->index('team_id');
            $table->index('is_active');
        });

        Schema::table('queue_services', function (Blueprint $table) {
            $table->string('queue_strategy', 16)->default('fifo')->after('default_priority');
            $table->json('starvation')->nullable()->after('queue_strategy');
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->foreignId('queue_priority_id')
                ->nullable()
                ->after('priority')
                ->constrained('queue_priorities')
                ->nullOnDelete();
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('queue_priority_id');
            $table->dropIndex(['priority']);
        });

        Schema::table('queue_services', function (Blueprint $table) {
            $table->dropColumn(['queue_strategy', 'starvation']);
        });

        Schema::dropIfExists('queue_priorities');
    }
};
