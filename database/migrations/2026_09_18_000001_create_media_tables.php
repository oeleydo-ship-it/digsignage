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
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->restrictOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'path']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('source')->default('file');
            $table->string('name');
            $table->string('filename')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('processing_error')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'type', 'archived_at']);
            $table->index(['team_id', 'folder_id']);
            $table->index(['team_id', 'processing_status']);
        });

        Schema::create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('media_media_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('media_tag_id')->constrained('media_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['media_id', 'media_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_media_tag');
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_folders');
    }
};
