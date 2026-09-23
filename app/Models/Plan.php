<?php

namespace App\Models;

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $name
 * @property int|null $screens
 * @property int|null $storage_gb
 * @property int|null $users
 * @property int|null $bandwidth_gb
 * @property int|null $price_cents
 * @property string|null $stripe_price_id
 * @property array<string, bool> $features
 */
#[Fillable(['key', 'name', 'screens', 'storage_gb', 'users', 'bandwidth_gb', 'price_cents', 'stripe_price_id', 'features'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public function planKey(): ?PlanKey
    {
        return PlanKey::tryFrom($this->key);
    }

    public function allows(PlanFeature $feature): bool
    {
        return ($this->features[$feature->value] ?? false) === true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screens' => 'integer',
            'storage_gb' => 'integer',
            'users' => 'integer',
            'bandwidth_gb' => 'integer',
            'price_cents' => 'integer',
            'features' => 'array',
        ];
    }
}
