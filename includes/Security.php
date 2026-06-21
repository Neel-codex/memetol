<?php
/**
 * Security - CSRF tokens, output escaping, rate limiting.
 */
class Security
{
    /** Generate / return the current CSRF token. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Hidden input field with the CSRF token. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::e(self::csrfToken()) . '">';
    }

    /** Validate a submitted CSRF token (timing-safe). */
    public static function verifyCsrf(?string $token): bool
    {
        return !empty($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Abort the request if the POSTed CSRF token is invalid. */
    public static function requireCsrf(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        if (!self::verifyCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid or expired CSRF token. Please refresh and try again.');
        }
    }

    /** HTML-escape output (XSS protection). */
    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Basic rate limiter using the session. Returns true when allowed. */
    public static function rateLimit(string $key, int $max, int $seconds): bool
    {
        $now    = time();
        $bucket = $_SESSION['rl'][$key] ?? ['count' => 0, 'reset' => $now + $seconds];

        if ($now > $bucket['reset']) {
            $bucket = ['count' => 0, 'reset' => $now + $seconds];
        }

        $bucket['count']++;
        $_SESSION['rl'][$key] = $bucket;

        return $bucket['count'] <= $max;
    }
}
