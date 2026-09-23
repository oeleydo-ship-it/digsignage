<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string|null $number
 * @property string|null $stripe_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property string|null $hosted_invoice_url
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'team_id',
    'number',
    'stripe_id',
    'amount_cents',
    'currency',
    'status',
    'hosted_invoice_url',
    'period_start',
    'period_end',
    'paid_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
