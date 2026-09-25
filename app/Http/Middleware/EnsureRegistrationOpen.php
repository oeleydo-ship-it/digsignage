<?php

namespace App\Http\Middleware;

use App\Models\TeamInvitation;
use App\Support\InitialAdminSetup;
use App\Support\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes public sign-up when a platform administrator turns it off. People
 * with a pending team invitation, and the very first administrator, can
 * still register.
 */
class EnsureRegistrationOpen
{
    public function __construct(
        protected PlatformSettings $settings,
        protected InitialAdminSetup $setup,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register', 'register.store')
            || $this->settings->registrationOpen()
            || $this->setup->required()
            || $this->invited($request)) {
            return $next($request);
        }

        $message = __('Sign-ups are closed. Ask an administrator for an invitation.');

        if ($request->isMethod('GET')) {
            return redirect()->route('login')->with('status', $message);
        }

        abort(403, $message);
    }

    protected function invited(Request $request): bool
    {
        $code = $request->input('invitation', $request->query('invitation'));

        return is_string($code) && $code !== '' && TeamInvitation::query()
            ->where('code', $code)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->exists();
    }
}
