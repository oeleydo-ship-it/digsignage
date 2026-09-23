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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->nullable();
            $table->string('path');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'path']);
            $table->unique(['team_id', 'parent_id', 'name']);
        });

        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('device_uuid')->nullable()->unique();
            $table->string('device_token')->nullable();
            $table->string('orientation')->default('landscape');
            $table->unsignedInteger('resolution_width')->nullable();
            $table->unsignedInteger('resolution_height')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('app_version')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('storage_available')->nullable();
            $table->unsignedBigInteger('storage_total')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'location_id']);
        });

        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('player_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'consumed_at']);
        });

        Schema::create('screen_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('screen_group_screen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['screen_group_id', 'screen_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_group_screen');
        Schema::dropIfExists('screen_groups');
        Schema::dropIfExists('device_registrations');
        Schema::dropIfExists('screens');
        Schema::dropIfExists('locations');
    }
};
