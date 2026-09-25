<?php

return [

    /*
    |--------------------------------------------------------------------------
    | In-app updater
    |--------------------------------------------------------------------------
    |
    | When enabled, platform administrators can install releases from the
    | Updates page. The server must use the zero-downtime layout described in
    | docs/deployment.md: {base}/releases/*, {base}/shared and a {base}/current
    | symlink that the web server points at. Keep this off on development
    | machines and anywhere the layout is not in place.
    |
    */

    'enabled' => (bool) env('DEPLOY_UPDATER_ENABLED', false),

    'base_path' => env('DEPLOY_BASE_PATH'),

    'keep_releases' => (int) env('DEPLOY_KEEP_RELEASES', 5),

    // Seconds a single install (download, build, migrate, switch) may take.
    'timeout' => (int) env('DEPLOY_TIMEOUT', 1800),

    'binaries' => [
        'php' => env('DEPLOY_PHP_BINARY', PHP_BINARY),
        'composer' => env('DEPLOY_COMPOSER_BINARY', 'composer'),
        'npm' => env('DEPLOY_NPM_BINARY', 'npm'),
        'bash' => env('DEPLOY_BASH_BINARY', 'bash'),
    ],

    // Run after the switch, e.g. "sudo systemctl reload php8.4-fpm".
    'reload_command' => env('DEPLOY_RELOAD_COMMAND'),

    /*
    |--------------------------------------------------------------------------
    | Update packages
    |--------------------------------------------------------------------------
    */

    'max_package_mb' => (int) env('DEPLOY_MAX_PACKAGE_MB', 300),

    // Uncompressed size limit, guarding against zip bombs.
    'max_extracted_mb' => (int) env('DEPLOY_MAX_EXTRACTED_MB', 1500),

    /*
    |--------------------------------------------------------------------------
    | GitHub
    |--------------------------------------------------------------------------
    |
    | Only repositories listed here can be installed from. The token is needed
    | for private repositories (fine-grained, "Contents: read-only").
    |
    */

    'github' => [
        'repositories' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DEPLOY_GITHUB_REPOSITORIES', 'oeleydo-ship-it/digsignage')),
        ))),
        'token' => env('DEPLOY_GITHUB_TOKEN'),
        // Release asset installed in preference to GitHub's source zip.
        'asset_pattern' => env('DEPLOY_GITHUB_ASSET_PATTERN', '/^digsignage-.*\.zip$/'),
    ],

];
