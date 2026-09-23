<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('bandwidth_used_bytes');
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('resource_type', 64)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        $now = now();

        DB::table('feature_flags')->insert([
            [
                'key' => 'registrations_open',
                'name' => 'Registrations open',
                'description' => 'Allow new users to create accounts.',
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'maintenance_banner',
                'name' => 'Maintenance banner',
                'description' => 'Show a maintenance notice on tenant dashboards.',
                'enabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'player_force_update',
                'name' => 'Force player update',
                'description' => 'Signal that players should update before the next sync.',
                'enabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_audits');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('feature_flags');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
    }
};
