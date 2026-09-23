<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiScope;
use App\Http\Controllers\Controller as BaseController;
use App\Models\ApiToken;
use App\Support\PartnerApiDocumentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentationController extends BaseController
{
    public function openapi(): JsonResponse
    {
        return response()->json(PartnerApiDocumentation::spec());
    }

    public function index(Request $request): JsonResponse
    {
        $token = $request->attributes->get('apiToken');
        $team = $request->user()?->currentTeam;
        $tokenName = $token instanceof ApiToken ? $token->name : null;
        $tokenScopes = $token instanceof ApiToken ? $token->scopes : [];

        return response()->json([
            'data' => [
                'organization' => [
                    'id' => $team?->id,
                    'name' => $team?->name,
                    'slug' => $team?->slug,
                ],
                'token' => [
                    'name' => $tokenName,
                    'scopes' => $tokenScopes,
                ],
                'documentation' => url('/api/v1/openapi.json'),
                'scopes' => ApiScope::options(),
            ],
        ]);
    }
}
