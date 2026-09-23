<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

final class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function actorId(Request $request): ?int
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function active(Request $request): bool
    {
        return self::actorId($request) !== null;
    }

    public static function actor(Request $request): ?User
    {
        $id = self::actorId($request);

        return $id ? User::query()->find($id) : null;
    }
}
