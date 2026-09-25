<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 64);
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->json('features')->nullable();
            $table->string('source', 20);
            $table->string('source_ref', 512)->nullable();
            $table->string('package_path', 512)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('package_size')->nullable();
            $table->string('release_path', 512)->nullable();
            $table->string('status', 20)->index();
            $table->longText('log')->nullable();
            $table->string('error', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
