<?php

namespace App\Actions\Integrations;

use App\Models\Team;
use App\Support\ContentApps;
use App\Support\SafeOutboundHttp;
use Illuminate\Validation\ValidationException;

class SaveContentIntegrations
{
    /**
     * Persist namespaced team settings.integrations for first-party content apps.
     *
     * @param  array<string, mixed>  $apps
     */
    public function handle(Team $team, array $apps): void
    {
        $previous = ContentApps::stored($team);
        $normalized = [];

        foreach (ContentApps::definitions() as $definition) {
            $key = $definition['key'];
            $input = is_array($apps[$key] ?? null) ? $apps[$key] : [];
            $existing = is_array($previous[$key] ?? null) ? $previous[$key] : [];
            $row = [
                'enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            foreach ($definition['fields'] as $field) {
                $name = $field['name'];
                $value = $input[$name] ?? null;

                if ($name === 'token') {
                    $row['token'] = $this->token($value, $existing['token'] ?? null);

                    continue;
                }

                $row[$name] = match ($field['type']) {
                    'url' => $this->publicUrl($value, "apps.{$key}.{$name}"),
                    'number' => $this->number($value),
                    default => $this->text($value),
                };
            }

            $normalized[$key] = $row;
        }

        $settings = is_array($team->settings) ? $team->settings : [];
        $settings['integrations'] = $normalized;

        $team->forceFill(['settings' => $settings])->save();
    }

    protected function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function number(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function publicUrl(mixed $value, string $field): ?string
    {
        $url = $this->text($value);

        if ($url === null) {
            return null;
        }

        if (! SafeOutboundHttp::isAllowed($url)) {
            throw ValidationException::withMessages([
                $field => __('Use a public http(s) URL.'),
            ]);
        }

        return $url;
    }

    protected function token(mixed $value, mixed $existing): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return is_string($existing) && $existing !== '' ? $existing : null;
    }
}
