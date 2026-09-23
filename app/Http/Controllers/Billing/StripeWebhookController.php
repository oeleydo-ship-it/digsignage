<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    /**
     * Apply Stripe subscription and invoice events.
     */
    public function __invoke(Request $request, BillingGateway $billing): JsonResponse
    {
        $billing->handleWebhook(
            $request->all(),
            $request->header('Stripe-Signature'),
            $request->getContent(),
        );

        return response()->json(['received' => true]);
    }
}
