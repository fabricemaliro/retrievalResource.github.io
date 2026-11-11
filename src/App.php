<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Tiny service container: configuration, database connection, session and current user. */
final class App
{
    private static array $config = [];
    private static ?PDO $db = null;
    private static ?array $user = null;
    private static bool $userLoaded = false;

    public static function init(array $config): void
    {
        self::$config = $config;
        date_default_timezone_set($config['timezone'] ?? 'UTC');

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            session_name('lu_records');
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
        I18n::init();
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function db(): PDO
    {
        if (self::$db === null) {
            self::$db = Database::connect(self::config('db'));
            if (!Installer::isInstalled(self::$db)) {
                Installer::install(self::$db);
            }
        }
        return self::$db;
    }

    /** Overrides the connection (used by the tests and the installer). */
    public static function setDb(PDO $db): void
    {
        self::$db = $db;
    }

    public static function user(): ?array
    {
        if (!self::$userLoaded) {
            self::$userLoaded = true;
            self::$user = Auth::current(self::db());
        }
        return self::$user;
    }

    public static function forgetUser(): void
    {
        self::$user = null;
        self::$userLoaded = false;
    }
}
