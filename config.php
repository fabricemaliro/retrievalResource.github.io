<?php

/*
 * LU Records Finder — configuration.
 * By default the project runs on SQLite (zero setup). To use MySQL / MariaDB (XAMPP, WAMP...),
 * set 'driver' => 'mysql' and fill in the 'mysql' block. Tables and demo data are created
 * automatically on the first request (or with: php bin/install.php).
 */
return [
    'app_name' => 'LU Records Finder',
    'institution' => 'Lakeview University',
    'timezone' => 'Africa/Kampala',

    'db' => [
        'mysql' => getenv('DB_DRIVER') ?: 'sqlite',            // 'sqlite' or 'mysql'
        'sqlite_path' => __DIR__ . '/storage/records.sqlite',
        'mysql' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_NAME') ?: 'records_ir',
            'username' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASS') ?: '',
        ],
    ],

    'per_page' => 10,
    'upload_dir' => __DIR__ . '/storage/uploads',
    'upload_max_mb' => 10,
    'records_office_email' => 'records@lakeview-university.example',

    // Shows the demo accounts on the login page. Set to false outside the classroom.
    'demo_mode' => true,
];
