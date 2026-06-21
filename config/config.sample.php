<?php
/**
 * MemeRadar AI - Sample Configuration
 *
 * Copy this file to config.php and fill in your credentials,
 * or use the installation wizard at /install/ which generates it for you.
 */

return [
    // --- Database ---
    'db' => [
        'host'    => 'localhost',
        'name'    => 'memeradar',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // --- Application ---
    'app' => [
        // Public base URL (no trailing slash). e.g. https://yourdomain.com
        'url'         => 'http://localhost',
        'name'        => 'MemeRadar AI',
        'env'         => 'production', // production | development
        'timezone'    => 'UTC',
        // Secret used for CSRF / remember-me token signing. CHANGE THIS!
        'app_key'     => 'change-this-to-a-long-random-string',
        'debug'       => false,
    ],
];
