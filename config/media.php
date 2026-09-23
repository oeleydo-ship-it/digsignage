<?php

return [
    'disk' => env('MEDIA_DISK', 'media'),
    'max_file_kilobytes' => (int) env('MEDIA_MAX_FILE_KB', 512000),
    'max_image_kilobytes' => (int) env('MEDIA_MAX_IMAGE_KB', 20480),
    'max_html_package_kilobytes' => (int) env('MEDIA_MAX_HTML_PACKAGE_KB', 102400),
    'thumbnail_width' => 480,
    'thumbnail_height' => 270,
    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
    // Minutes before signed object-storage redirect URLs expire.
    'temporary_url_minutes' => (int) env('MEDIA_TEMPORARY_URL_MINUTES', 10),
    'allowed_extensions' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'video' => ['mp4', 'webm', 'mov', 'm4v', 'ogv', 'ogg', 'mpeg', 'mpg', 'avi', 'mkv', '3gp'],
        'audio' => ['mp3', 'aac', 'wav', 'ogg', 'm4a'],
        'pdf' => ['pdf'],
        'html_package' => ['zip'],
    ],
    'allowed_mimes' => [
        'image' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ],
        'video' => [
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/x-m4v',
            'video/ogg',
            'video/mpeg',
            'video/x-msvideo',
            'video/x-matroska',
            'video/3gpp',
            'application/octet-stream',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/mp4',
            'audio/wav',
            'audio/ogg',
            'audio/x-wav',
            'audio/aac',
        ],
        'pdf' => [
            'application/pdf',
        ],
        'html_package' => [
            'application/zip',
            'application/x-zip-compressed',
        ],
    ],
    'blocked_archive_extensions' => [
        'php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'msi',
    ],
];
