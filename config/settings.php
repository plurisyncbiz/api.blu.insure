<?php
declare(strict_types=1);

// 1. Determine Environment (Assumes Dotenv is loaded in index.php)
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';

// 2. Set Global PHP Error Handling
// Since this file is required by the container, this logic runs when the container is built.
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// 3. Return the Settings Array directly
return [
    // Slim Error Handling
    'error' => [
        'display_error_details' => !$isProduction,
        'log_errors' => true,
        'log_error_details' => true,
    ],

    // Basic Auth
    'api_auth' => [
        "path" => "/token",
        "ignore" => "/token",
        "realm" => "Protected",
        "secure" => $isProduction,
        "relaxed" => ["localhost", "127.0.0.1"],
        "users" => [
            "root" => '$2y$10$1lwCIlqktFZwEBIppL4ak.I1AHxjoKy9stLnbedwVMrt92aGz82.O',
            "somebody" => '$2y$10$6/vGXuMUoRlJUeDN.bUWduge4GhQbgPkm6pfyGxwgEWT0vEkHKBUW'
        ]
    ],

    // JWT
    'jwt' => [
        'private_key' => $_ENV['JWT_PRIVATE_KEY'] ?? '',
        'public_key'  => $_ENV['JWT_PUBLIC_KEY'] ?? '',
        'algorithm'   => 'RS256',
        'issuer_claim' => 'https://blds.co.za',
        'audience_claim' => 'unknown',
        'expire' => 3600,
    ],

    // Database
    'db' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'],
        'dbname' => $_ENV['DB_NAME'],
        'user' => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASS'],
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'driverOptions' => [
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
        ],
    ],

    // Logger
    'logger' => [
        'determineRouteBeforeAppMiddleware' => true,
        'path' => __DIR__ . '/../logs',
        'level' => $isProduction ? Psr\Log\LogLevel::INFO : Psr\Log\LogLevel::DEBUG,
    ],

    // SFTP
    'sftp' => [
        'host' => $_ENV['SFTP_HOST'],
        'port' => (int)($_ENV['SFTP_PORT'] ?? 22),
        'username' => $_ENV['SFTP_USER'],
        'password' => $_ENV['SFTP_PASS'],
        'root' => $_ENV['SFTP_ROOT'] ?? '/outgoing',
    ],

    // SMS
    'smsgateway' => [
        'username' => $_ENV['SMS_USER'],
        'password' => $_ENV['SMS_PASS']
    ],

    // SMTP
    'smtp' => [
        'dsn' => $_ENV['SMTP_DSN'],
        'from' => 'mailer@example.com',
    ],

    // Storage
    'storage' => [
        'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
        'config' => [
            'root' => realpath(__DIR__ . '/../storage'),
            'permissions' => [
                'file' => ['public' => 0755, 'private' => 0755],
                'dir' => ['public' => 0755, 'private' => 0755],
            ],
            'visibility' => \League\Flysystem\Visibility::PUBLIC,
            'lock' => LOCK_EX,
            'link' => \League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS,
        ]
    ]
];