<?php
/**
 * Auth - user + admin authentication, sessions, remember-me.
 */
class Auth
{
    /* ---------------- Users ---------------- */

    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $db   = Database::instance();
        $user = $db->fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        if ($user['status'] === 'banned') {
            return false;
        }

        self::loginUser($user);

        if ($remember) {
            self::setRememberCookie((int) $user['id']);
        }
        return true;
    }

    public static function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']  = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['plan']     = $user['plan'];

        Database::instance()->run(
            'UPDATE users SET last_login = NOW() WHERE id = ?',
            [$user['id']]
        );
    }

    public static function check(): bool
    {
        if (!empty($_SESSION['user_id'])) {
            return true;
        }
        return self::loginFromRememberCookie();
    }

    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            if (!self::loginFromRememberCookie()) {
                return null;
            }
        }
        return Database::instance()->fetch(
            'SELECT * FROM users WHERE id = ? LIMIT 1',
            [$_SESSION['user_id']]
        );
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: ' . base_url('login.php'));
            exit;
        }
    }

    public static function logout(): void
    {
        self::clearRememberCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /* ---------------- Remember me ---------------- */

    private static function setRememberCookie(int $userId): void
    {
        $selector  = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $validator);
        $expires   = time() + 60 * 60 * 24 * 30; // 30 days

        Database::instance()->run(
            'INSERT INTO remember_tokens (selector, token, user_id, expires_at)
             VALUES (?, ?, ?, FROM_UNIXTIME(?))',
            [$selector, $hash, $userId, $expires]
        );

        setcookie('remember', $selector . ':' . $validator, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function loginFromRememberCookie(): bool
    {
        if (empty($_COOKIE['remember']) || !str_contains($_COOKIE['remember'], ':')) {
            return false;
        }
        [$selector, $validator] = explode(':', $_COOKIE['remember'], 2);

        $row = Database::instance()->fetch(
            'SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1',
            [$selector]
        );
        if (!$row || !hash_equals($row['token'], hash('sha256', $validator))) {
            return false;
        }

        $user = Database::instance()->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$row['user_id']]);
        if (!$user || $user['status'] === 'banned') {
            return false;
        }

        self::loginUser($user);
        return true;
    }

    private static function clearRememberCookie(): void
    {
        if (!empty($_COOKIE['remember']) && str_contains($_COOKIE['remember'], ':')) {
            [$selector] = explode(':', $_COOKIE['remember'], 2);
            try {
                Database::instance()->run('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
            } catch (Throwable $e) {
            }
        }
        setcookie('remember', '', time() - 3600, '/');
    }

    /* ---------------- Admin ---------------- */

    public static function adminAttempt(string $username, string $password): bool
    {
        $admin = Database::instance()->fetch(
            'SELECT * FROM admins WHERE username = ? LIMIT 1',
            [$username]
        );
        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['admin_id']        = (int) $admin['id'];
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_must_reset'] = (int) $admin['must_change_password'];

        Database::instance()->run('UPDATE admins SET last_login = NOW() WHERE id = ?', [$admin['id']]);
        return true;
    }

    public static function adminCheck(): bool
    {
        return !empty($_SESSION['admin_id']);
    }

    public static function adminRequire(): void
    {
        if (!self::adminCheck()) {
            header('Location: ' . base_url('admin/index.php'));
            exit;
        }
        // Force password change after first login
        if (!empty($_SESSION['admin_must_reset'])
            && basename($_SERVER['SCRIPT_NAME']) !== 'settings.php') {
            header('Location: ' . base_url('admin/settings.php?force_pw=1'));
            exit;
        }
    }

    public static function adminLogout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_must_reset']);
    }
}
