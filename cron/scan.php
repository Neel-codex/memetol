<?php
/**
 * Cron job: scan for new meme coins + evaluate alerts.
 *
 * cPanel cron example (every 10 minutes):
 *   *\/10 * * * * /usr/bin/php /home/USER/public_html/cron/scan.php >> /home/USER/cron.log 2>&1
 *
 * Or via wget/curl with a secret token (set cron_token in settings):
 *   *\/10 * * * * curl -s "https://yourdomain.com/cron/scan.php?token=YOURTOKEN"
 */

define('SKIP_INSTALL_CHECK', true);
require __DIR__ . '/../includes/bootstrap.php';

$isCli = (PHP_SAPI === 'cli');

// If invoked over HTTP, require a token to prevent abuse.
if (!$isCli) {
    $token = Settings::get('cron_token', '');
    if ($token === '' || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        exit('Forbidden: invalid cron token. Set "cron_token" in settings or run via CLI.');
    }
    header('Content-Type: text/plain');
}

$start = microtime(true);

try {
    $scanned   = Scanner::scanTrending();
    $triggered = AlertEngine::run();

    // Telegram heads-up for fresh, high-score coins
    if (Telegram::enabled()) {
        $fresh = Database::instance()->fetchAll(
            "SELECT name, symbol, chain, ai_score FROM coins
             WHERE created_at >= NOW() - INTERVAL 15 MINUTE AND ai_score >= 70 AND is_hidden = 0
             ORDER BY ai_score DESC LIMIT 5"
        );
        foreach ($fresh as $c) {
            Telegram::send(sprintf(
                "<b>New AI Pick</b>\n%s (%s) on %s\nAI Score: %d/100",
                $c['name'], $c['symbol'], ucfirst($c['chain']), (int)$c['ai_score']
            ));
        }
    }

    $elapsed = round(microtime(true) - $start, 2);
    $msg = "Cron OK | scanned={$scanned} alerts_triggered={$triggered} time={$elapsed}s";
    log_event('cron', $msg);
    echo $msg . "\n";
} catch (Throwable $e) {
    log_event('cron_error', $e->getMessage());
    http_response_code(500);
    echo 'Cron error: ' . $e->getMessage() . "\n";
}
