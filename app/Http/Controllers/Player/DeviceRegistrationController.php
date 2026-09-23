<?php

namespace App\Http\Controllers\Player;

use App\Actions\Signage\PairDeviceRegistration;
use App\Actions\Signage\StartDeviceRegistration;
use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Support\RegistrationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceRegistrationController extends Controller
{
    /**
     * Start a pairing session and return a display code.
     */
    public function store(Request $request, StartDeviceRegistration $startDeviceRegistration): JsonResponse
    {
        $result = $startDeviceRegistration->handle($request);

        return response()->json([
            'code' => $result['code'],
            'expires_at' => $result['registration']->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Poll pairing status for a display code.
     */
    public function show(
        Request $request,
        string $code,
        PairDeviceRegistration $pairDeviceRegistration,
    ): JsonResponse {
        $registration = DeviceRegistration::findByCode($code);

        if (! $registration) {
            return response()->json([
                'status' => 'invalid',
                'message' => __('Unknown registration code.'),
            ], 404);
        }

        if ($registration->isExpired()) {
            return response()->json([
                'status' => 'expired',
                'code' => RegistrationCode::format($code),
            ]);
        }

        if ($registration->isPending()) {
            return response()->json([
                'status' => 'pending',
                'code' => RegistrationCode::format($code),
                'expires_at' => $registration->expires_at->toIso8601String(),
            ]);
        }

        $payload = [
            'status' => 'paired',
            'code' => RegistrationCode::format($code),
        ];

        $token = $pairDeviceRegistration->pullDeviceToken($registration);

        if ($token !== null) {
            $registration->loadMissing('screen');

            $payload['device_uuid'] = $registration->screen?->device_uuid;
            $payload['device_token'] = $token;
        }

        return response()->json($payload);
    }
}
