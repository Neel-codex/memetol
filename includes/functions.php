<?php
/**
 * Global helper functions.
 */

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        global $APP_CONFIG;
        $segments = explode('.', $key);
        $value    = $APP_CONFIG;
        foreach ($segments as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return Security::e($value);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        $url = preg_match('#^https?://#', $path) ? $path : base_url($path);
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        return Security::e($_SESSION['_old'][$key] ?? $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $value = null)
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
}

if (!function_exists('json_response')) {
    function json_response($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('money')) {
    function money($n): string
    {
        $n = (float) $n;
        if ($n >= 1_000_000_000) return '$' . number_format($n / 1_000_000_000, 2) . 'B';
        if ($n >= 1_000_000)     return '$' . number_format($n / 1_000_000, 2) . 'M';
        if ($n >= 1_000)         return '$' . number_format($n / 1_000, 2) . 'K';
        if ($n > 0 && $n < 0.01) return '$' . rtrim(rtrim(sprintf('%.8f', $n), '0'), '.');
        return '$' . number_format($n, 2);
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime): string
    {
        $ts   = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
        $diff = time() - $ts;
        if ($diff < 60)     return $diff . 's ago';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }
}

if (!function_exists('risk_label')) {
    function risk_label(int $score): array
    {
        // Higher score = safer. Returns [label, css class, color]
        if ($score >= 70) return ['LOW', 'success', '#00ff99'];
        if ($score >= 40) return ['MEDIUM', 'warning', '#ffcc00'];
        return ['HIGH', 'danger', '#ff4d6d'];
    }
}

if (!function_exists('log_event')) {
    function log_event(string $type, string $message, ?int $userId = null): void
    {
        try {
            Database::instance()->run(
                'INSERT INTO logs (type, message, user_id, ip, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$type, $message, $userId, $_SERVER['REMOTE_ADDR'] ?? null]
            );
        } catch (Throwable $e) {
        }
    }
}


if (!function_exists('render_ads')) {
    /** Render all active ads for a placement (banner|sidebar|popup|adsense|html). */
    function render_ads(string $placement): string
    {
        try {
            $ads = Database::instance()->fetchAll(
                'SELECT * FROM ads WHERE placement = ? AND is_active = 1 ORDER BY id DESC',
                [$placement]
            );
        } catch (Throwable $e) {
            return '';
        }
        $out = '';
        foreach ($ads as $ad) {
            // Ad code is raw HTML supplied by the admin (trusted operator content).
            $inner = $ad['code'] ?: '';
            if (!empty($ad['link'])) {
                $inner = '<a href="' . e($ad['link']) . '" target="_blank" rel="nofollow noopener">' . $inner . '</a>';
            }
            $out .= '<div class="mr-ad mr-ad-' . e($placement) . '">' . $inner . '</div>';
        }
        return $out;
    }
}

if (!function_exists('active_banner')) {
    /** Fetch the current active banner for a position, respecting schedule. */
    function active_banner(string $position): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM banners WHERE position = ? AND is_active = 1
                 AND (starts_at IS NULL OR starts_at <= NOW())
                 AND (ends_at IS NULL OR ends_at >= NOW())
                 ORDER BY id DESC LIMIT 1',
                [$position]
            );
        } catch (Throwable $e) {
            return null;
        }
    }
}
