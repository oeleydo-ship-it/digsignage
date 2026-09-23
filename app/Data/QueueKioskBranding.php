<?php

namespace App\Data;

readonly class QueueKioskBranding
{
    /**
     * @param  array{background: string, primary: string, text: string, button_text: string}  $colors
     */
    public function __construct(
        public ?string $logoUrl,
        public ?string $title,
        public ?string $footer,
        public bool $printQrCode,
        public array $colors,
    ) {
        //
    }

    /**
     * @return array{logo_url: null, title: null, footer: null, print_qr_code: true, colors: array{background: string, primary: string, text: string, button_text: string}}
     */
    public static function defaults(): array
    {
        return [
            'logo_url' => null,
            'title' => null,
            'footer' => null,
            'print_qr_code' => true,
            'colors' => [
                'background' => '#0f172a',
                'primary' => '#2563eb',
                'text' => '#f8fafc',
                'button_text' => '#ffffff',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $branding
     */
    public static function fromArray(?array $branding): self
    {
        $defaults = self::defaults();
        $colors = is_array($branding['colors'] ?? null) ? $branding['colors'] : [];

        return new self(
            logoUrl: self::nullableString($branding['logo_url'] ?? null),
            title: self::nullableString($branding['title'] ?? null),
            footer: self::nullableString($branding['footer'] ?? null),
            printQrCode: (bool) ($branding['print_qr_code'] ?? $defaults['print_qr_code']),
            colors: [
                'background' => self::color($colors['background'] ?? null, $defaults['colors']['background']),
                'primary' => self::color($colors['primary'] ?? null, $defaults['colors']['primary']),
                'text' => self::color($colors['text'] ?? null, $defaults['colors']['text']),
                'button_text' => self::color($colors['button_text'] ?? null, $defaults['colors']['button_text']),
            ],
        );
    }

    /**
     * @return array{logo_url: string|null, title: string|null, footer: string|null, print_qr_code: bool, colors: array{background: string, primary: string, text: string, button_text: string}}
     */
    public function toArray(): array
    {
        return [
            'logo_url' => $this->logoUrl,
            'title' => $this->title,
            'footer' => $this->footer,
            'print_qr_code' => $this->printQrCode,
            'colors' => $this->colors,
        ];
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected static function color(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $trimmed = trim($value);

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $trimmed) !== 1) {
            return $fallback;
        }

        return $trimmed;
    }
}
