<?php

namespace App\Services\Deploy;

use App\Support\AppVersion;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads releases from GitHub and downloads their packages. Only repositories
 * on the configured allowlist are ever contacted.
 */
class GitHubReleases
{
    private const API = 'https://api.github.com';

    /**
     * Turn a pasted GitHub link into a repository and optional ref. Supports
     * repository, release, tag, tree/branch and "latest release" links.
     *
     * @return array{repository: string, ref: string|null}
     */
    public function parseUrl(string $url): array
    {
        $url = trim($url);
        $parts = parse_url(preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.$url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($host, ['github.com', 'www.github.com'], true)) {
            throw new ReleaseException(__('Enter a github.com link, such as https://github.com/owner/repo/releases/tag/v1.2.0.'));
        }

        $segments = array_values(array_filter(explode('/', (string) ($parts['path'] ?? '')), fn (string $part) => $part !== ''));

        if (count($segments) < 2) {
            throw new ReleaseException(__('The link must include the owner and repository name.'));
        }

        $repository = $this->allowedRepository($segments[0].'/'.preg_replace('/\.git$/', '', $segments[1]));
        $rest = array_slice($segments, 2);
        $ref = match (true) {
            ($rest[0] ?? null) === 'releases' && ($rest[1] ?? null) === 'tag' => implode('/', array_slice($rest, 2)),
            ($rest[0] ?? null) === 'tree' => implode('/', array_slice($rest, 1)),
            ($rest[0] ?? null) === 'commit' => $rest[1] ?? '',
            default => null,
        };

        if ($ref !== null) {
            $ref = rawurldecode($ref);
            $this->assertRef($ref);
        }

        return ['repository' => $repository, 'ref' => $ref];
    }

    public function allowedRepository(string $repository): string
    {
        foreach ((array) config('deploy.github.repositories') as $allowed) {
            if (strcasecmp((string) $allowed, $repository) === 0) {
                return (string) $allowed;
            }
        }

        throw new ReleaseException(__('Installing from :repository is not allowed. Add it to DEPLOY_GITHUB_REPOSITORIES first.', ['repository' => $repository]));
    }

    /**
     * Published releases, newest first.
     *
     * @return list<array{tag: string, version: string|null, name: string|null, body: string|null, published_at: string|null, prerelease: bool, url: string|null, asset_url: string|null, asset_size: int|null}>
     */
    public function releases(string $repository, int $limit = 20): array
    {
        $response = $this->get('/repos/'.$this->allowedRepository($repository).'/releases', ['per_page' => $limit]);

        return array_values(collect((array) $response->json())
            ->filter(fn ($release) => is_array($release) && ($release['draft'] ?? false) !== true)
            ->map(fn (array $release) => $this->summary($release))
            ->all());
    }

    /**
     * The release for a tag, or the latest release when no tag is given.
     * Null when the tag has no GitHub release (a bare tag or a branch).
     *
     * @return array{tag: string, version: string|null, name: string|null, body: string|null, published_at: string|null, prerelease: bool, url: string|null, asset_url: string|null, asset_size: int|null}|null
     */
    public function release(string $repository, ?string $tag): ?array
    {
        $repository = $this->allowedRepository($repository);
        $path = $tag === null
            ? "/repos/{$repository}/releases/latest"
            : "/repos/{$repository}/releases/tags/".$this->encodeRef($tag);
        $response = $this->request()->get(self::API.$path);

        if ($response->status() === 404) {
            return null;
        }

        $this->assertOk($response);

        return $this->summary((array) $response->json());
    }

    /**
     * Download a release asset (preferred) or the source zip for a ref.
     */
    public function download(string $repository, string $ref, ?string $assetUrl, string $destination): void
    {
        $repository = $this->allowedRepository($repository);
        $this->assertRef($ref);

        $url = $assetUrl ?? self::API."/repos/{$repository}/zipball/".$this->encodeRef($ref);

        if ($assetUrl !== null && ! str_starts_with($assetUrl, self::API."/repos/{$repository}/releases/assets/")) {
            throw new ReleaseException(__('Unexpected download location for the release package.'));
        }

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        try {
            $response = $this->request()
                ->accept($assetUrl !== null ? 'application/octet-stream' : 'application/vnd.github+json')
                ->timeout(600)
                ->withOptions(['sink' => $destination])
                ->get($url);
        } catch (Throwable $exception) {
            @unlink($destination);

            throw new ReleaseException(__('Downloading from GitHub failed: :message', ['message' => $exception->getMessage()]));
        }

        if (! $response->successful()) {
            @unlink($destination);
            $this->assertOk($response);
        }

        $limit = max(1, (int) config('deploy.max_package_mb')) * 1024 * 1024;

        if ((int) filesize($destination) > $limit) {
            @unlink($destination);

            throw new ReleaseException(__('The package is larger than :size MB.', ['size' => (int) config('deploy.max_package_mb')]));
        }
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array{tag: string, version: string|null, name: string|null, body: string|null, published_at: string|null, prerelease: bool, url: string|null, asset_url: string|null, asset_size: int|null}
     */
    private function summary(array $release): array
    {
        $tag = (string) ($release['tag_name'] ?? '');
        $asset = collect((array) ($release['assets'] ?? []))
            ->first(fn ($asset) => is_array($asset)
                && preg_match((string) config('deploy.github.asset_pattern'), (string) ($asset['name'] ?? '')) === 1);

        return [
            'tag' => $tag,
            'version' => AppVersion::normalize($tag),
            'name' => isset($release['name']) ? (string) $release['name'] : null,
            'body' => isset($release['body']) ? (string) $release['body'] : null,
            'published_at' => isset($release['published_at']) ? (string) $release['published_at'] : null,
            'prerelease' => (bool) ($release['prerelease'] ?? false),
            'url' => isset($release['html_url']) ? (string) $release['html_url'] : null,
            'asset_url' => is_array($asset) ? (string) ($asset['url'] ?? '') ?: null : null,
            'asset_size' => is_array($asset) ? (int) ($asset['size'] ?? 0) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = []): Response
    {
        try {
            $response = $this->request()->get(self::API.$path, $query);
        } catch (Throwable $exception) {
            throw new ReleaseException(__('GitHub could not be reached: :message', ['message' => $exception->getMessage()]));
        }

        $this->assertOk($response);

        return $response;
    }

    private function request(): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'DigSignage-Updater',
        ])->timeout(20);

        $token = config('deploy.github.token');

        return is_string($token) && $token !== '' ? $request->withToken($token) : $request;
    }

    private function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new ReleaseException(match ($response->status()) {
            401, 403 => __('GitHub refused the request. Check DEPLOY_GITHUB_TOKEN and its access to the repository.'),
            404 => __('GitHub could not find that repository or release. Private repositories need DEPLOY_GITHUB_TOKEN.'),
            default => __('GitHub returned an error (:status).', ['status' => $response->status()]),
        });
    }

    private function assertRef(string $ref): void
    {
        if ($ref === '' || strlen($ref) > 200 || preg_match('#^[A-Za-z0-9._/-]+$#', $ref) !== 1 || str_contains($ref, '..')) {
            throw new ReleaseException(__('That tag or branch name is not valid.'));
        }
    }

    private function encodeRef(string $ref): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $ref)));
    }
}
