<?php

use App\Enums\PlanKey;
use App\Models\Plan;
use App\Support\BillingCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('name');
            $table->unsignedInteger('screens')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->unsignedInteger('users')->nullable();
            $table->unsignedInteger('bandwidth_gb')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->json('features');
            $table->timestamps();
        });

        foreach (PlanKey::cases() as $key) {
            $defaults = BillingCatalog::defaults($key);

            Plan::query()->create([
                'key' => $key->value,
                ...$defaults,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
