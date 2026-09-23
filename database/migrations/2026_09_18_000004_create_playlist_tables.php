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
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('loop')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->unsignedInteger('duration_seconds')->default(15);
            $table->string('transition')->default('fade');
            $table->unsignedInteger('transition_ms')->default(400);
            $table->boolean('enabled')->default(true);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->unsignedInteger('position');
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('design_id')->nullable()->constrained('designs')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('url')->nullable();
            $table->string('widget_key')->nullable();
            $table->timestamps();

            $table->index(['playlist_id', 'position']);
            $table->index(['team_id', 'playlist_id']);
        });

        Schema::create('playlist_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('items');
            $table->boolean('loop')->default(true);
            $table->timestamps();

            $table->unique(['playlist_id', 'version']);
            $table->index(['team_id', 'playlist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_revisions');
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
    }
};
