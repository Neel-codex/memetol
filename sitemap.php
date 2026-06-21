<?php
/**
 * Dynamic XML sitemap. Accessible at /sitemap.xml via .htaccess rewrite,
 * or directly at /sitemap.php.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['loc' => base_url('index.php'),    'priority' => '1.0', 'freq' => 'daily'],
    ['loc' => base_url('trending.php'), 'priority' => '0.9', 'freq' => 'hourly'],
    ['loc' => base_url('pricing.php'),  'priority' => '0.7', 'freq' => 'weekly'],
    ['loc' => base_url('register.php'), 'priority' => '0.6', 'freq' => 'monthly'],
    ['loc' => base_url('login.php'),    'priority' => '0.5', 'freq' => 'monthly'],
];

try {
    $coins = Database::instance()->fetchAll(
        'SELECT id, updated_at FROM coins WHERE is_hidden = 0 ORDER BY updated_at DESC LIMIT 1000'
    );
    foreach ($coins as $c) {
        $urls[] = [
            'loc'      => base_url('coin.php?id=' . $c['id']),
            'priority' => '0.6',
            'freq'     => 'daily',
            'lastmod'  => date('Y-m-d', strtotime($c['updated_at'])),
        ];
    }
} catch (Throwable $e) {
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc']) . "</loc>\n";
    if (!empty($u['lastmod'])) echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $u['freq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
