<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Team;
use App\Support\PartnerApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class Controller extends BaseController
{
    protected function team(Request $request): Team
    {
        $team = $request->user()?->currentTeam;

        abort_if($team === null, 403);

        return $team;
    }

    protected function perPage(Request $request): int
    {
        return PartnerApi::perPage($request->integer('per_page', 25));
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function findForTeam(Request $request, string $class, int $id): Model
    {
        $model = $class::query()
            ->where('team_id', $this->team($request)->id)
            ->whereKey($id)
            ->first();

        abort_if(! $model instanceof $class, 404);

        return $model;
    }
}
