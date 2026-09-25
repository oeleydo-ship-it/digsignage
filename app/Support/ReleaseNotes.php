<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Pulls a version's notes and feature list out of a Keep a Changelog style
 * CHANGELOG.md, or out of a GitHub release body.
 */
final class ReleaseNotes
{
    private const MAX_FEATURES = 50;

    /**
     * @return array{title: string|null, notes: string|null, features: list<string>}
     */
    public static function fromChangelog(string $changelog, string $version): array
    {
        $lines = preg_split('/\R/', $changelog) ?: [];
        $section = [];
        $title = null;
        $inside = false;

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(?!#)(.*)$/', $line, $match) === 1) {
                if ($inside) {
                    break;
                }

                $heading = trim($match[1]);

                if (preg_match('/^\[?v?'.preg_quote($version, '/').'\]?(?:\s|$)/i', $heading) === 1) {
                    $inside = true;
                    $title = self::headingTitle($heading);
                }

                continue;
            }

            if ($inside) {
                $section[] = $line;
            }
        }

        if (! $inside) {
            return ['title' => null, 'notes' => null, 'features' => []];
        }

        $notes = trim(implode("\n", $section));

        return [
            'title' => $title,
            'notes' => $notes === '' ? null : $notes,
            'features' => self::features($notes),
        ];
    }

    /**
     * @return array{title: string|null, notes: string|null, features: list<string>}
     */
    public static function fromBody(?string $body, ?string $title = null): array
    {
        $notes = trim((string) $body);

        return [
            'title' => filled($title) ? Str::limit((string) $title, 250, '') : null,
            'notes' => $notes === '' ? null : Str::limit($notes, 20_000),
            'features' => self::features($notes),
        ];
    }

    /**
     * Bullets under an "Added" (or "Features"/"New") heading when present,
     * otherwise every bullet in the notes.
     *
     * @return list<string>
     */
    public static function features(string $notes): array
    {
        $all = [];
        $added = [];
        $inAdded = false;

        foreach (preg_split('/\R/', $notes) ?: [] as $line) {
            if (preg_match('/^#{2,6}\s+(.*)$/', $line, $match) === 1) {
                $inAdded = preg_match('/^(added|features?|new|what\'s new)\b/i', trim($match[1])) === 1;

                continue;
            }

            if (preg_match('/^\s{0,3}[-*+]\s+(.+)$/', $line, $match) !== 1) {
                continue;
            }

            $item = Str::limit(trim(strip_tags($match[1])), 300);

            if ($item === '') {
                continue;
            }

            $all[] = $item;

            if ($inAdded) {
                $added[] = $item;
            }
        }

        return array_slice($added !== [] ? $added : $all, 0, self::MAX_FEATURES);
    }

    private static function headingTitle(string $heading): ?string
    {
        // "[1.2.0] - 2026-10-01 - Room booking" → "Room booking"
        $rest = trim((string) preg_replace('/^\[?v?[^\]\s]+\]?\s*/', '', $heading));
        $rest = trim((string) preg_replace('/^[-–—]\s*\d{4}-\d{2}-\d{2}\s*/', '', $rest), " -–—\t");

        return $rest === '' ? null : Str::limit($rest, 250, '');
    }
}
