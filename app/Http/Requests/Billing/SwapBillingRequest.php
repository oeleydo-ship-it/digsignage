<?php

namespace App\Http\Requests\Billing;

use App\Enums\PlanKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapBillingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_key' => ['required', 'string', Rule::enum(PlanKey::class)],
        ];
    }
}
