<?php

namespace App\Support;

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class BillingCatalog
{
    /**
     * @return array{
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     stripe_price_id: string|null,
     *     features: array<string, bool>
     * }
     */
    public static function plan(PlanKey $key): array
    {
        try {
            return Cache::rememberForever('billing:plan:'.$key->value, fn () => self::resolvePlan($key));
        } catch (\Throwable) {
            // Cache store unavailable (e.g. while migrating): resolve directly.
            return self::resolvePlan($key);
        }
    }

    /**
     * Forget cached plan lookups after a plan row is written.
     */
    public static function forgetPlanCache(string $key): void
    {
        try {
            Cache::forget('billing:plan:'.$key);
            Cache::forget('billing:plans:managed');
        } catch (\Throwable) {
            // Cache store unavailable.
        }
    }

    /**
     * @return array{
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     stripe_price_id: string|null,
     *     features: array<string, bool>
     * }
     */
    private static function resolvePlan(PlanKey $key): array
    {
        $defaults = self::defaults($key);
        $row = self::stored($key);

        if ($row === null) {
            return $defaults;
        }

        return [
            'name' => $row->name !== '' ? $row->name : $defaults['name'],
            'screens' => $row->screens,
            'storage_gb' => $row->storage_gb,
            'users' => $row->users,
            'bandwidth_gb' => $row->bandwidth_gb,
            'advanced' => $row->allows(PlanFeature::MultiZone),
            'price_cents' => $row->price_cents,
            'stripe_price_id' => $row->stripe_price_id ?: $defaults['stripe_price_id'],
            'features' => self::normalizeFeatures($row->features ?? $defaults['features']),
        ];
    }

    /**
     * Config defaults used when the plans table is empty or during migrate.
     *
     * @return array{
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     stripe_price_id: string|null,
     *     features: array<string, bool>
     * }
     */
    public static function defaults(PlanKey $key): array
    {
        $plans = config('billing.plans');

        if (! is_array($plans) || ! isset($plans[$key->value]) || ! is_array($plans[$key->value])) {
            throw new InvalidArgumentException("Unknown billing plan [{$key->value}].");
        }

        $plan = $plans[$key->value];
        $advanced = (bool) ($plan['advanced'] ?? false);
        $features = self::normalizeFeatures($plan['features'] ?? null, $advanced);

        return [
            'name' => is_string($plan['name'] ?? null) ? $plan['name'] : $key->label(),
            'screens' => self::nullableInt($plan['screens'] ?? null),
            'storage_gb' => self::nullableInt($plan['storage_gb'] ?? null),
            'users' => self::nullableInt($plan['users'] ?? null),
            'bandwidth_gb' => self::nullableInt($plan['bandwidth_gb'] ?? null),
            'advanced' => $features[PlanFeature::MultiZone->value],
            'price_cents' => self::nullableInt($plan['price_cents'] ?? null),
            'stripe_price_id' => is_string($plan['stripe_price_id'] ?? null) && $plan['stripe_price_id'] !== ''
                ? $plan['stripe_price_id']
                : null,
            'features' => $features,
        ];
    }

    /**
     * Checkout catalog: built-in plan keys only.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     features: array<string, bool>
     * }>
     */
    public static function plans(): array
    {
        $items = [];

        foreach (PlanKey::cases() as $key) {
            $items[] = self::summarize($key->value, self::plan($key));
        }

        return $items;
    }

    /**
     * Platform console: stored rows plus any built-in keys not yet persisted.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     features: array<string, bool>
     * }>
     */
    public static function managedPlans(): array
    {
        try {
            return Cache::rememberForever('billing:plans:managed', fn () => self::resolveManagedPlans());
        } catch (\Throwable) {
            return self::resolveManagedPlans();
        }
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     features: array<string, bool>
     * }>
     */
    private static function resolveManagedPlans(): array
    {
        $stored = [];

        foreach (self::storedRows() as $row) {
            $stored[$row->key] = self::fromStored($row);
        }

        $items = [];

        foreach (PlanKey::cases() as $key) {
            $items[] = $stored[$key->value] ?? self::summarize($key->value, self::defaults($key));
            unset($stored[$key->value]);
        }

        foreach ($stored as $plan) {
            $items[] = $plan;
        }

        return $items;
    }

    public static function allows(PlanKey $key, PlanFeature $feature): bool
    {
        return self::plan($key)['features'][$feature->value] ?? false;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function featureOptions(): array
    {
        return array_map(fn (PlanFeature $feature) => [
            'value' => $feature->value,
            'label' => $feature->label(),
        ], PlanFeature::cases());
    }

    /**
     * @return array{percent_off: int, stripe_promotion_code: string|null}|null
     */
    public static function coupon(?string $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        $coupons = config('billing.coupons');

        if (! is_array($coupons)) {
            return null;
        }

        $normalized = strtoupper($code);

        foreach ($coupons as $name => $coupon) {
            if (! is_string($name) || strtoupper($name) !== $normalized || ! is_array($coupon)) {
                continue;
            }

            $percent = self::nullableInt($coupon['percent_off'] ?? null) ?? 0;

            return [
                'percent_off' => max(0, min(100, $percent)),
                'stripe_promotion_code' => is_string($coupon['stripe_promotion_code'] ?? null) && $coupon['stripe_promotion_code'] !== ''
                    ? $coupon['stripe_promotion_code']
                    : null,
            ];
        }

        return null;
    }

    public static function discountedAmount(int $cents, ?string $code): int
    {
        $coupon = self::coupon($code);

        if ($coupon === null || $coupon['percent_off'] === 0) {
            return max(0, $cents);
        }

        return (int) round($cents * (100 - $coupon['percent_off']) / 100);
    }

    public static function bytesFromGigabytes(?int $gigabytes): ?int
    {
        if ($gigabytes === null) {
            return null;
        }

        return $gigabytes * 1024 * 1024 * 1024;
    }

    /**
     * @param  array<string, mixed>|null  $features
     * @return array<string, bool>
     */
    public static function normalizeFeatures(?array $features, bool $advancedFallback = false): array
    {
        $normalized = [];

        foreach (PlanFeature::cases() as $feature) {
            $default = $feature === PlanFeature::MultiZone ? $advancedFallback : true;
            $normalized[$feature->value] = is_array($features)
                ? (bool) ($features[$feature->value] ?? $default)
                : $default;
        }

        return $normalized;
    }

    /**
     * Treat missing checkboxes as disabled.
     *
     * @param  array<string, mixed>|null  $features
     * @return array<string, bool>
     */
    public static function featuresFromRequest(?array $features): array
    {
        $normalized = [];

        foreach (PlanFeature::cases() as $feature) {
            $value = is_array($features) ? ($features[$feature->value] ?? false) : false;
            $normalized[$feature->value] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    /**
     * @param  array{
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     stripe_price_id?: string|null,
     *     features: array<string, bool>
     * }  $plan
     * @return array{
     *     key: string,
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     features: array<string, bool>
     * }
     */
    private static function summarize(string $key, array $plan): array
    {
        return [
            'key' => $key,
            'name' => $plan['name'],
            'screens' => $plan['screens'],
            'storage_gb' => $plan['storage_gb'],
            'users' => $plan['users'],
            'bandwidth_gb' => $plan['bandwidth_gb'],
            'advanced' => $plan['advanced'],
            'price_cents' => $plan['price_cents'],
            'features' => $plan['features'],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     screens: int|null,
     *     storage_gb: int|null,
     *     users: int|null,
     *     bandwidth_gb: int|null,
     *     advanced: bool,
     *     price_cents: int|null,
     *     features: array<string, bool>
     * }
     */
    private static function fromStored(Plan $row): array
    {
        $enum = PlanKey::tryFrom($row->key);
        $defaults = $enum !== null
            ? self::defaults($enum)
            : [
                'name' => $row->name,
                'screens' => null,
                'storage_gb' => null,
                'users' => null,
                'bandwidth_gb' => null,
                'advanced' => false,
                'price_cents' => null,
                'stripe_price_id' => null,
                'features' => self::normalizeFeatures(null),
            ];

        return self::summarize($row->key, [
            'name' => $row->name !== '' ? $row->name : $defaults['name'],
            'screens' => $row->screens,
            'storage_gb' => $row->storage_gb,
            'users' => $row->users,
            'bandwidth_gb' => $row->bandwidth_gb,
            'advanced' => $row->allows(PlanFeature::MultiZone),
            'price_cents' => $row->price_cents,
            'stripe_price_id' => $row->stripe_price_id ?: ($defaults['stripe_price_id'] ?? null),
            'features' => self::normalizeFeatures($row->features ?? $defaults['features']),
        ]);
    }

    /**
     * @return Collection<int, Plan>
     */
    private static function storedRows(): Collection
    {
        try {
            return Plan::query()->orderBy('name')->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    private static function stored(PlanKey $key): ?Plan
    {
        try {
            return Plan::query()->find($key->value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
