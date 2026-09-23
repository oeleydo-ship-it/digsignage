<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\InitialAdminSetup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $setup = app(InitialAdminSetup::class);
        $initialAdmin = $setup->required();

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            ...($initialAdmin ? ['setup_key' => ['required', 'string', 'max:255']] : []),
        ])->validate();

        if ($initialAdmin) {
            $rateKey = 'initial-admin-setup:'.request()->ip();

            if (RateLimiter::tooManyAttempts($rateKey, 5)) {
                throw ValidationException::withMessages([
                    'setup_key' => 'Too many setup attempts. Please try again shortly.',
                ]);
            }

            $key = (string) config('setup.initial_admin_key');

            if (! $setup->configured() || ! hash_equals($key, $input['setup_key'])) {
                RateLimiter::hit($rateKey, 60);

                throw ValidationException::withMessages([
                    'setup_key' => 'The server setup key is missing or invalid.',
                ]);
            }

            return Cache::lock('initial-platform-admin-setup', 30)->block(5, function () use ($input, $setup) {
                if (! $setup->required()) {
                    throw ValidationException::withMessages([
                        'email' => 'The platform administrator has already been configured.',
                    ]);
                }

                return $this->createUser($input, platformAdmin: true);
            });
        }

        return $this->createUser($input);
    }

    /** @param array<string, string> $input */
    private function createUser(array $input, bool $platformAdmin = false): User
    {
        return DB::transaction(function () use ($input, $platformAdmin) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            if ($platformAdmin) {
                $user->forceFill(['is_platform_admin' => true])->save();
            }

            $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

            return $user;
        });
    }
}
