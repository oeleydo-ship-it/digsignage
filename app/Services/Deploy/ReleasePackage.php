<?php

namespace App\Services\Deploy;

/**
 * What a validated release zip contains.
 */
final readonly class ReleasePackage
{
    /**
     * @param  list<string>  $features
     */
    public function __construct(
        public string $version,
        public string $root,
        public ?string $title,
        public ?string $notes,
        public array $features,
        public string $checksum,
        public int $size,
        public bool $hasVendor,
        public bool $hasBuild,
    ) {}
}
