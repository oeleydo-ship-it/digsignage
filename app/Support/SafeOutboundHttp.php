<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class SafeOutboundHttp
{
    /**
     * Allow only public http(s) URLs for widget data fetches.
     */
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || in_array($host, ['localhost', '0.0.0.0', '::1'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function get(string $url, array $headers = []): string
    {
        if (! self::isAllowed($url)) {
            throw new InvalidArgumentException('Blocked outbound URL.');
        }

        $response = Http::timeout(5)
            ->withHeaders(['User-Agent' => 'DigSignageWidget/1.0', ...$headers])
            ->get($url);

        $response->throw();

        $body = $response->body();

        if (strlen($body) > 512_000) {
            return substr($body, 0, 512_000);
        }

        return $body;
    }

    /**
     * True when the remote document forbids being displayed in a cross-origin iframe.
     */
    public static function blocksFraming(string $url): bool
    {
        if (! self::isAllowed($url)) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'DigSignageWidget/1.0'])
                ->withOptions(['http_errors' => false])
                ->head($url);

            if (in_array($response->status(), [405, 501, 0], true) || $response->status() >= 400) {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'DigSignageWidget/1.0', 'Range' => 'bytes=0-0'])
                    ->withOptions(['http_errors' => false])
                    ->get($url);
            }

            $xfo = strtolower((string) $response->header('X-Frame-Options'));

            if (in_array($xfo, ['deny', 'sameorigin'], true) || str_starts_with($xfo, 'allow-from')) {
                return true;
            }

            $csp = (string) $response->header('Content-Security-Policy');

            if (preg_match('/(?:^|;)\s*frame-ancestors\s+([^;]+)/i', $csp, $match) === 1) {
                $ancestors = strtolower(trim($match[1]));

                if ($ancestors === "'none'" || $ancestors === '"none"') {
                    return true;
                }

                if (! str_contains($ancestors, '*')) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
