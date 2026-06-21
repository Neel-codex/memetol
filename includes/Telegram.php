<?php
/**
 * Telegram - send notifications through a Telegram bot.
 * Bot token + chat id are stored in settings (admin panel).
 */
class Telegram
{
    public static function enabled(): bool
    {
        return Settings::get('telegram_enabled', '0') === '1'
            && Settings::get('telegram_bot_token')
            && Settings::get('telegram_chat_id');
    }

    public static function send(string $message): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $token = Settings::get('telegram_bot_token');
        $chat  = Settings::get('telegram_chat_id');
        $url   = "https://api.telegram.org/bot{$token}/sendMessage";

        $res = Http::postJson($url, [
            'chat_id'    => $chat,
            'text'       => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
        return $res !== null && !empty($res['ok']);
    }

    /** Test connection - returns [bool ok, string detail]. */
    public static function test(): array
    {
        $token = Settings::get('telegram_bot_token');
        if (!$token) {
            return [false, 'No bot token configured.'];
        }
        $res = Http::getJson("https://api.telegram.org/bot{$token}/getMe");
        if ($res && !empty($res['ok'])) {
            return [true, 'Connected as @' . ($res['result']['username'] ?? 'bot')];
        }
        return [false, 'Invalid bot token or network error.'];
    }
}
