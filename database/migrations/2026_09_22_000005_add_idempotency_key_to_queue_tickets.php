<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('public_token');
            $table->unique(
                ['team_id', 'queue_service_id', 'idempotency_key'],
                'queue_tickets_service_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropUnique('queue_tickets_service_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
