<?php

return [
    'enabled' => env('PERISCOPE_ENABLED', true),

    'name' => env('PERISCOPE_NAME', 'Periscope'),

    'theme' => env('PERISCOPE_THEME', 'default'),

    'path' => env('PERISCOPE_PATH', 'periscope'),

    'domain' => env('PERISCOPE_DOMAIN'),

    'middleware' => ['web'],

    'connection' => env('PERISCOPE_DB_CONNECTION', env('TELESCOPE_DB_CONNECTION')),

    'per_page' => 100,

    'max_per_page' => 200,

    'default_hours' => 24,

    'overview_request_scan_limit' => env('PERISCOPE_OVERVIEW_REQUEST_SCAN_LIMIT', 1000),

    'error_scan_timeout_ms' => env('PERISCOPE_ERROR_SCAN_TIMEOUT_MS', 1500),

    'error_scan_max_entries' => env('PERISCOPE_ERROR_SCAN_MAX_ENTRIES', 10000),

    'exclude_from_telescope' => env('PERISCOPE_EXCLUDE_FROM_TELESCOPE', true),

    'disable_debugbar' => env('PERISCOPE_DISABLE_DEBUGBAR', true),

    'exclude_debugbar_entries' => env('PERISCOPE_EXCLUDE_DEBUGBAR_ENTRIES', true),
];
