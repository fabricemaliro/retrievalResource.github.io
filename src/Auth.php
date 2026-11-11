<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public static function attempt(string $username, string $password): array|string
    {
        $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
        if ($lockedUntil > time()) {
            return __('Too many failed attempts. Try again in {seconds} seconds.', ['seconds' => $lockedUntil - time()]);
        }

        $db = App::db();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= self::MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['login_failures'] = 0;
            }
            Logger::audit(null, 'login_failed', null, ['username' => mb_substr($username, 0, 60)]);
            return __('Incorrect username or password.');
        }

        session_regenerate_id(true);
        unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
        $_SESSION['uid'] = (int) $user['id'];
        $db->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $user['id']]);
        App::forgetUser();
        $current = App::user();
        Logger::audit($current, 'login');
        return $current;
    }

    public static function logout(): void
    {
        Logger::audit(App::user(), 'logout');
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        App::forgetUser();
    }

    public static function current(PDO $db): ?array
    {
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) {
            return null;
        }
        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.full_name, u.role, u.department_id, u.student_no, d.name AS department_name
               FROM users u LEFT JOIN departments d ON d.id = u.department_id
              WHERE u.id = ?'
        );
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: null;
    }
}
