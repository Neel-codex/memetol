<?php
/**
 * AlertEngine - evaluates user alerts against current coin data and
 * dispatches notifications (Telegram + email; browser handled client-side).
 */
class AlertEngine
{
    /** Evaluate all active alerts. Returns number of alerts triggered. */
    public static function run(): int
    {
        $db        = Database::instance();
        $alerts    = $db->fetchAll('SELECT * FROM alerts WHERE is_active = 1');
        $triggered = 0;

        foreach ($alerts as $a) {
            if (self::shouldTrigger($a)) {
                self::dispatch($a);
                $db->run('UPDATE alerts SET last_triggered_at = NOW() WHERE id = ?', [$a['id']]);
                $triggered++;
            }
        }
        return $triggered;
    }

    private static function shouldTrigger(array $a): bool
    {
        $db = Database::instance();

        // Avoid re-spamming: at most once per hour
        if ($a['last_triggered_at'] && (time() - strtotime($a['last_triggered_at'])) < 3600) {
            return false;
        }

        switch ($a['type']) {
            case 'price':
                if (!$a['coin_id']) return false;
                $price = (float)$db->scalar('SELECT price FROM coins WHERE id = ?', [$a['coin_id']]);
                return $a['condition_type'] === 'above'
                    ? $price >= (float)$a['threshold']
                    : $price <= (float)$a['threshold'];

            case 'volume':
                if (!$a['coin_id']) return false;
                $vol = (float)$db->scalar('SELECT volume FROM coins WHERE id = ?', [$a['coin_id']]);
                return $a['condition_type'] === 'above'
                    ? $vol >= (float)$a['threshold']
                    : $vol <= (float)$a['threshold'];

            case 'new_listing':
                // trigger when a coin was created in the last 15 min
                $n = (int)$db->scalar("SELECT COUNT(*) FROM coins WHERE created_at >= NOW() - INTERVAL 15 MINUTE");
                return $n > 0;

            case 'ai_buy':
                $n = (int)$db->scalar("SELECT COUNT(*) FROM coins WHERE ai_score >= 75 AND updated_at >= NOW() - INTERVAL 30 MINUTE");
                return $n > 0;
        }
        return false;
    }

    private static function dispatch(array $a): void
    {
        $db   = Database::instance();
        $coin = $a['coin_id'] ? $db->fetch('SELECT * FROM coins WHERE id = ?', [$a['coin_id']]) : null;
        $user = $db->fetch('SELECT * FROM users WHERE id = ?', [$a['user_id']]);
        if (!$user) return;

        $label = strtoupper(str_replace('_', ' ', $a['type']));
        $name  = $coin ? ($coin['name'] . ' (' . $coin['symbol'] . ')') : 'Market';
        $msg   = "<b>MemeRadar AI Alert</b>\n{$label} triggered for {$name}";
        if ($coin) {
            $msg .= "\nPrice: " . money($coin['price']) . " | AI Score: " . (int)$coin['ai_score'] . " | Risk: " . $coin['risk_level'];
        }

        $channels = explode(',', (string)$a['channel']);

        if (in_array('telegram', $channels, true)) {
            Telegram::send($msg);
        }
        if (in_array('email', $channels, true) && $user['email']) {
            $plain = strip_tags(str_replace(["\n", '<b>', '</b>'], ["\n", '', ''], $msg));
            @mail($user['email'], 'MemeRadar AI Alert', $plain, 'From: ' . Settings::get('smtp_from', 'no-reply@memeradar.ai'));
        }

        log_event('alert', "Alert #{$a['id']} ({$label}) dispatched to user {$a['user_id']}", $a['user_id']);
    }
}
