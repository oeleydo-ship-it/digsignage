<?php

namespace App\Support;

use Illuminate\Http\Request;

final class StayOnPage
{
    /**
     * Resolve where a POST should send the browser next.
     *
     * Laravel's back() uses the Referer, then the session previous URL, then "/".
     * Inertia visits are XHR, so the session URL often stays on the organization
     * dashboard from login. "/" is handled by HomeController, which also dumps
     * people on the dashboard. Prefer the page they were actually on.
     */
    public static function url(Request $request, mixed $fallback = false): string
    {
        foreach (self::candidates($request) as $url) {
            if (self::isUsable($url, $request)) {
                return $url;
            }
        }

        if ($fallback !== false && $fallback !== null && $fallback !== '') {
            return url()->to($fallback);
        }

        // Redirecting a PUT/POST/DELETE to its own URL makes the browser GET an
        // action-only route (405), so only GET requests may stay put.
        return $request->isMethod('GET') ? $request->fullUrl() : url('/');
    }

    /**
     * @return list<string>
     */
    protected static function candidates(Request $request): array
    {
        $urls = [];

        $stayOnPage = $request->headers->get('X-Stay-On-Page');

        if (is_string($stayOnPage) && $stayOnPage !== '') {
            $urls[] = $stayOnPage;
        }

        $referer = $request->headers->get('referer');

        if (is_string($referer) && $referer !== '') {
            $urls[] = $referer;
        }

        if ($request->hasSession()) {
            $previous = $request->session()->previousUrl();

            if (is_string($previous) && $previous !== '') {
                $urls[] = $previous;
            }
        }

        return array_values(array_unique($urls));
    }

    protected static function isUsable(string $url, Request $request): bool
    {
        if (! self::isSameApplication($url, $request)) {
            return false;
        }

        $path = self::path($url);

        if (in_array($path, ['/', '/dashboard'], true)) {
            return false;
        }

        if (self::isTeamDashboard($path) && ! self::requestIsDashboard($request)) {
            return false;
        }

        return true;
    }

    protected static function isSameApplication(string $url, Request $request): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $host = $parts['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return isset($parts['path']);
        }

        $allowed = array_values(array_filter([
            $request->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ], fn ($value) => is_string($value) && $value !== ''));

        return in_array($host, $allowed, true);
    }

    protected static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    protected static function isTeamDashboard(string $path): bool
    {
        return (bool) preg_match('#^/[^/]+/dashboard$#', $path);
    }

    protected static function requestIsDashboard(Request $request): bool
    {
        $path = rtrim($request->getPathInfo(), '/') ?: '/';

        return $path === '/dashboard' || self::isTeamDashboard($path);
    }
}
