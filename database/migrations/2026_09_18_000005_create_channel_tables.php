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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('playlist');
            $table->string('status')->default('draft');
            $table->string('live_protocol')->nullable();
            $table->string('live_url')->nullable();
            $table->unsignedInteger('width')->default(1920);
            $table->unsignedInteger('height')->default(1080);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status', 'type']);
        });

        Schema::create('channel_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('x')->default(0);
            $table->unsignedTinyInteger('y')->default(0);
            $table->unsignedTinyInteger('width')->default(100);
            $table->unsignedTinyInteger('height')->default(100);
            $table->unsignedInteger('z_index')->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['channel_id', 'position']);
            $table->index(['team_id', 'channel_id']);
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->foreignId('current_channel_id')->nullable()->after('storage_total')->constrained('channels')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_channel_id');
        });

        Schema::dropIfExists('channel_zones');
        Schema::dropIfExists('channels');
    }
};
