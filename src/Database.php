<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(array $cfg): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (($cfg['driver'] ?? 'sqlite') === 'mysql') {
            $m = $cfg['mysql'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $m['host'], $m['port'], $m['database']);
            return new PDO($dsn, $m['username'], $m['password'], $options);
        }

        $path = $cfg['sqlite_path'];
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }

    public static function isSqlite(PDO $db): bool
    {
        return $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    /** Builds a LIKE pattern from user input, escaping % and _ (use with ESCAPE '!'). */
    public static function like(string $value): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
    }

    /** "?, ?, ?" for an IN (...) clause. */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
