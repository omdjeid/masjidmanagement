<?php
declare(strict_types=1);

return [
    'host' => env_value('DB_HOST', '127.0.0.1'),
    'port' => (int) env_value('DB_PORT', '3306'),
    'database' => env_value('DB_NAME', 'masjid_ausath'),
    'username' => env_value('DB_USER', 'root'),
    'password' => env_value('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
