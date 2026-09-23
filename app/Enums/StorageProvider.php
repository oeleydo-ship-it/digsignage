<?php

namespace App\Enums;

enum StorageProvider: string
{
    case Local = 'local';
    case AmazonS3 = 's3';
    case Wasabi = 'wasabi';
    case DigitalOceanSpaces = 'digitalocean';
    case CloudflareR2 = 'cloudflare_r2';
    case BackblazeB2 = 'backblaze_b2';
    case MinIO = 'minio';
    case S3Compatible = 's3_compatible';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local server disk',
            self::AmazonS3 => 'Amazon S3',
            self::Wasabi => 'Wasabi',
            self::DigitalOceanSpaces => 'DigitalOcean Spaces',
            self::CloudflareR2 => 'Cloudflare R2',
            self::BackblazeB2 => 'Backblaze B2',
            self::MinIO => 'MinIO',
            self::S3Compatible => 'Other S3-compatible',
        };
    }

    /**
     * Underlying Laravel filesystem driver.
     */
    public function driver(): string
    {
        return $this === self::Local ? 'local' : 's3';
    }

    public function isS3(): bool
    {
        return $this->driver() === 's3';
    }

    /**
     * Providers whose endpoint cannot be derived from the region.
     */
    public function requiresEndpoint(): bool
    {
        return match ($this) {
            self::CloudflareR2, self::MinIO, self::S3Compatible => true,
            default => false,
        };
    }

    /**
     * Default to path-style addressing for self-hosted gateways.
     */
    public function defaultPathStyle(): bool
    {
        return $this === self::MinIO;
    }

    /**
     * Endpoint template with a `{region}` placeholder, when the provider has one.
     */
    public function endpointTemplate(): ?string
    {
        return match ($this) {
            self::Wasabi => 'https://s3.{region}.wasabisys.com',
            self::DigitalOceanSpaces => 'https://{region}.digitaloceanspaces.com',
            self::BackblazeB2 => 'https://s3.{region}.backblazeb2.com',
            default => null,
        };
    }

    /**
     * Region shown as a placeholder in the admin form.
     */
    public function regionHint(): string
    {
        return match ($this) {
            self::AmazonS3 => 'us-east-1',
            self::Wasabi => 'us-east-1',
            self::DigitalOceanSpaces => 'nyc3',
            self::BackblazeB2 => 'us-west-004',
            self::CloudflareR2 => 'auto',
            default => 'us-east-1',
        };
    }

    /**
     * Resolve the endpoint for a region, preferring an explicit value.
     */
    public function endpointFor(?string $endpoint, ?string $region): ?string
    {
        if (filled($endpoint)) {
            return rtrim((string) $endpoint, '/');
        }

        $template = $this->endpointTemplate();

        if ($template === null || blank($region)) {
            return null;
        }

        return str_replace('{region}', (string) $region, $template);
    }

    /**
     * @return list<array{value: string, label: string, driver: string, requires_endpoint: bool, default_path_style: bool, endpoint_template: string|null, region_hint: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $provider) => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'driver' => $provider->driver(),
            'requires_endpoint' => $provider->requiresEndpoint(),
            'default_path_style' => $provider->defaultPathStyle(),
            'endpoint_template' => $provider->endpointTemplate(),
            'region_hint' => $provider->regionHint(),
        ], self::cases());
    }
}
