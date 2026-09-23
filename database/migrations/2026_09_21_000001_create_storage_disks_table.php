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
        Schema::create('storage_disks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('provider', 32);
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->string('root')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('url')->nullable();
            $table->text('access_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->boolean('path_style_endpoint')->default(false);
            $table->string('visibility', 16)->default('private');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_error')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
            $table->index('is_default');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('storage_disk_id')->nullable()->after('suspended_at')
                ->constrained('storage_disks')->nullOnDelete();
        });

        // Named `storage_disk` rather than `disk`: an attribute named `disk`
        // would collide with Media::disk() and be resolved as a relation.
        Schema::table('media', function (Blueprint $table) {
            $table->string('storage_disk')->nullable()->after('thumbnail_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_disk_id');
        });

        Schema::dropIfExists('storage_disks');
    }
};
