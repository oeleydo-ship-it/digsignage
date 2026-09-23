<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ApiScope;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $name
 * @property string $token_prefix
 * @property string $token_hash
 * @property list<string> $scopes
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'name', 'token_prefix', 'token_hash', 'scopes', 'last_used_at', 'expires_at'])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    /** @use HasFactory<ApiTokenFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function allows(string $scope): bool
    {
        if (in_array('*', $this->scopes, true)) {
            return true;
        }

        if (in_array($scope, $this->scopes, true)) {
            return true;
        }

        if (str_ends_with($scope, ':read')) {
            $write = substr($scope, 0, -4).'write';

            return in_array($write, $this->scopes, true);
        }

        return false;
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: self, 1: string}
     */
    public static function issue(User $user, Team $team, string $name, array $scopes, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = (string) config('partner.token_prefix').Str::random((int) config('partner.token_bytes'));

        $token = static::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $name,
            'token_prefix' => substr($plain, -8),
            'token_hash' => static::hashToken($plain),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    /**
     * Replace the secret while keeping the same token id, name, and scopes.
     *
     * @return array{0: self, 1: string}
     */
    public function rotate(): array
    {
        $plain = (string) config('partner.token_prefix').Str::random((int) config('partner.token_bytes'));

        $this->forceFill([
            'token_prefix' => substr($plain, -8),
            'token_hash' => static::hashToken($plain),
            'last_used_at' => null,
        ])->save();

        return [$this, $plain];
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function validScopeValues(): array
    {
        return array_map(fn (ApiScope $scope) => $scope->value, ApiScope::cases());
    }
}
