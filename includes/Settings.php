<?php
/**
 * Settings - key/value site settings cached from the `settings` table.
 */
class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        try {
            $rows = Database::instance()->fetchAll('SELECT `key`, `value` FROM settings');
            foreach ($rows as $r) {
                self::$cache[$r['key']] = $r['value'];
            }
        } catch (Throwable $e) {
            // settings table may not exist before install
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $db = Database::instance();
        $db->run(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, (string) $value]
        );
        self::$cache[$key] = (string) $value;
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }
}
