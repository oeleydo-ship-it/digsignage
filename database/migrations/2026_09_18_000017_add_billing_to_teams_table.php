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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('plan_key', 32)->default('starter')->after('settings');
            $table->string('subscription_status', 32)->default('trialing')->after('plan_key');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->string('stripe_customer_id')->nullable()->after('subscription_ends_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id');
            $table->string('coupon_code', 64)->nullable()->after('stripe_price_id');
            $table->unsignedBigInteger('bandwidth_used_bytes')->default(0)->after('coupon_code');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 8)->default('usd');
            $table->string('status', 32)->default('open');
            $table->string('hosted_invoice_url')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'plan_key',
                'subscription_status',
                'trial_ends_at',
                'subscription_ends_at',
                'stripe_customer_id',
                'stripe_subscription_id',
                'stripe_price_id',
                'coupon_code',
                'bandwidth_used_bytes',
            ]);
        });
    }
};
