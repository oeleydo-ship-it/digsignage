<?php

namespace App\Support;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class AuditLogQuery
{
    /**
     * @return array{
     *     action: string,
     *     resource_type: string,
     *     user_id: string,
     *     search: string,
     *     from: string,
     *     until: string
     * }
     */
    public static function filters(Request $request): array
    {
        return [
            'action' => $request->string('action')->toString(),
            'resource_type' => $request->string('resource_type')->toString(),
            'user_id' => $request->string('user_id')->toString(),
            'search' => $request->string('search')->toString(),
            'from' => $request->string('from')->toString(),
            'until' => $request->string('until')->toString(),
        ];
    }

    /**
     * @param  array{action: string, resource_type: string, user_id: string, search: string, from: string, until: string}  $filters
     * @return Builder<AuditLog>
     */
    public static function filtered(Team $team, array $filters): Builder
    {
        $from = $filters['from'] !== '' ? $filters['from'].' 00:00:00' : null;
        $until = $filters['until'] !== '' ? $filters['until'].' 23:59:59' : null;

        return AuditLog::query()
            ->forTeam($team)
            ->with('user:id,name,email')
            ->when($filters['action'] !== '', function (Builder $query) use ($filters) {
                $query->where('action', $filters['action']);
            })
            ->when($filters['resource_type'] !== '', function (Builder $query) use ($filters) {
                $query->where('resource_type', $filters['resource_type']);
            })
            ->when($filters['user_id'] !== '' && ctype_digit($filters['user_id']), function (Builder $query) use ($filters) {
                $query->where('user_id', (int) $filters['user_id']);
            })
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($until !== null, fn (Builder $query) => $query->where('created_at', '<=', $until))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $inner) use ($term) {
                    $inner->where('action', 'like', $term)
                        ->orWhere('resource_type', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhere('user_agent', 'like', $term)
                        ->orWhere('before', 'like', $term)
                        ->orWhere('after', 'like', $term)
                        ->orWhereHas('user', function (Builder $users) use ($term) {
                            $users->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            });
    }

    /**
     * @param  array{action: string, resource_type: string, user_id: string, search: string, from: string, until: string}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public static function paginate(Team $team, array $filters): LengthAwarePaginator
    {
        return self::filtered($team, $filters)
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function actionOptions(): array
    {
        return collect(AuditAction::cases())
            ->map(fn (AuditAction $action) => [
                'value' => $action->value,
                'label' => $action->label(),
            ])
            ->values()
            ->all();
    }
}
