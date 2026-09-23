<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_key' => ['required', 'string', Rule::enum(PlanKey::class)],
            'subscription_status' => ['required', 'string', Rule::enum(SubscriptionStatus::class)],
        ];
    }
}
