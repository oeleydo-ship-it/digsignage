<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Team;
use App\Support\BillingCatalog;
use Illuminate\Support\Carbon;

class RecordInvoice
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes = []): Invoice
    {
        $amount = (int) ($attributes['amount_cents'] ?? 0);
        $coupon = $attributes['coupon_code'] ?? $team->coupon_code;
        $amount = is_string($coupon) || $coupon === null
            ? BillingCatalog::discountedAmount($amount, is_string($coupon) ? $coupon : null)
            : $amount;

        $stripeId = is_string($attributes['stripe_id'] ?? null) ? $attributes['stripe_id'] : null;

        if ($stripeId) {
            $existing = Invoice::query()->where('stripe_id', $stripeId)->first();

            if ($existing) {
                $existing->fill([
                    'status' => $attributes['status'] ?? $existing->status,
                    'hosted_invoice_url' => $attributes['hosted_invoice_url'] ?? $existing->hosted_invoice_url,
                    'paid_at' => $attributes['paid_at'] ?? $existing->paid_at,
                    'amount_cents' => $attributes['amount_cents'] ?? $existing->amount_cents,
                ])->save();

                return $existing;
            }
        }

        return Invoice::query()->create([
            'team_id' => $team->id,
            'number' => $attributes['number'] ?? $this->nextNumber($team),
            'stripe_id' => $stripeId,
            'amount_cents' => $amount,
            'currency' => $attributes['currency'] ?? config('billing.currency', 'usd'),
            'status' => $attributes['status'] ?? 'paid',
            'hosted_invoice_url' => $attributes['hosted_invoice_url'] ?? null,
            'period_start' => $attributes['period_start'] ?? now()->startOfMonth(),
            'period_end' => $attributes['period_end'] ?? now()->endOfMonth(),
            'paid_at' => $attributes['paid_at'] ?? (($attributes['status'] ?? 'paid') === 'paid' ? now() : null),
        ]);
    }

    protected function nextNumber(Team $team): string
    {
        $count = Invoice::query()->where('team_id', $team->id)->count() + 1;

        return 'INV-'.Carbon::now()->format('Y').'-'.$team->id.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
