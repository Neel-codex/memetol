<?php
require __DIR__ . '/includes/bootstrap.php';

$db = Database::instance();
$id = (int) ($_GET['id'] ?? 0);

$coin = $db->fetch('SELECT * FROM coins WHERE id = ? AND is_hidden = 0', [$id]);
if (!$coin) {
    http_response_code(404);
    $pageTitle = 'Coin not found';
    require __DIR__ . '/includes/partials/head.php';
    require __DIR__ . '/includes/partials/navbar.php';
    echo '<div class="container py-5 text-center"><h3>Coin not found</h3><a href="'.base_url('trending.php').'" class="btn btn-mr-primary mt-3">Browse coins</a></div>';
    require __DIR__ . '/includes/partials/footer.php';
    exit;
}

// Increment view count
$db->run('UPDATE coins SET views = views + 1 WHERE id = ?', [$id]);

$analysis = RiskEngine::analyze($coin);
$insight  = RiskEngine::insight($analysis, $coin);
[$rl, $rc, $rcolor] = risk_label((int)$coin['ai_score']);

$warnings = json_decode((string)$coin['warnings'], true) ?: $analysis['warnings'];

$inWatch = false;
if (Auth::check()) {
    $inWatch = (bool) $db->scalar('SELECT COUNT(*) FROM watchlist WHERE user_id = ? AND coin_id = ?', [Auth::id(), $id]);
}

$smartMoney = $db->fetchAll('SELECT * FROM smart_money WHERE token_symbol = ? ORDER BY tx_date DESC LIMIT 6', [$coin['symbol']]);

$pageTitle = $coin['name'] . ' (' . $coin['symbol'] . ')';
$pageDesc  = $coin['name'] . ' price, AI risk score ' . (int)$coin['ai_score'] . '/100, rug-pull analysis, liquidity and smart-money activity on ' . ucfirst($coin['chain']) . '.';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-4">
    <nav class="small mb-3"><a href="<?= base_url('trending.php') ?>" class="link-primary-mr">Trending</a> <span class="text-muted">/ <?= e($coin['symbol']) ?></span></nav>

    <div class="row g-3">
        <!-- Header -->
        <div class="col-12">
            <div class="card glass-card"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="coin-ident">
                        <span class="coin-avatar lg"><?= e(strtoupper(substr($coin['symbol'],0,2))) ?></span>
                        <div>
                            <h3 class="mb-0"><?= e($coin['name']) ?> <small class="text-muted"><?= e($coin['symbol']) ?></small></h3>
                            <span class="badge bg-secondary-soft"><?= e(ucfirst($coin['chain'])) ?></span>
                            <span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr"><i class="fa-solid fa-shield-halved me-1"></i><?= $rl ?> RISK</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="h3 mb-0"><?= money($coin['price']) ?></div>
                        <span class="<?= $coin['price_change_24h']>=0?'text-success':'text-danger' ?>"><i class="fa-solid fa-arrow-<?= $coin['price_change_24h']>=0?'up':'down' ?>"></i> <?= number_format(abs((float)$coin['price_change_24h']),2) ?>% (24h)</span>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (Auth::check()): ?>
                            <button class="btn btn-outline-light watch-btn <?= $inWatch?'active':'' ?>" data-coin="<?= $id ?>"><i class="fa-<?= $inWatch?'solid':'regular' ?> fa-star me-1"></i><span class="wt"><?= $inWatch?'Watching':'Watch' ?></span></button>
                            <a class="btn btn-mr-primary" href="<?= base_url('alerts.php?coin='.$id) ?>"><i class="fa-regular fa-bell me-1"></i>Alert</a>
                        <?php else: ?>
                            <a class="btn btn-mr-primary" href="<?= base_url('login.php') ?>"><i class="fa-solid fa-star me-1"></i>Login to track</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div></div>
        </div>

        <!-- Metrics -->
        <div class="col-lg-8">
            <div class="card glass-card mb-3"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-chart-simple text-primary-mr me-2"></i>Market Metrics</h6>
                <div class="row g-3 metric-grid">
                    <?php
                    $metrics = [
                        ['Market Cap', money($coin['market_cap'])],
                        ['Liquidity', money($coin['liquidity'])],
                        ['24h Volume', money($coin['volume'])],
                        ['Holders', number_format((int)$coin['holders'])],
                        ['Pair Age', $coin['pair_age_hours'] >= 24 ? round($coin['pair_age_hours']/24,1).' d' : round($coin['pair_age_hours'],1).' h'],
                        ['Buy/Sell Ratio', number_format((float)$coin['buy_sell_ratio'],2)],
                        ['Buys 24h', number_format((int)$coin['buys_24h'])],
                        ['Sells 24h', number_format((int)$coin['sells_24h'])],
                    ];
                    foreach ($metrics as $m): ?>
                        <div class="col-6 col-md-3"><div class="metric-box"><span class="metric-label"><?= e($m[0]) ?></span><span class="metric-value"><?= e($m[1]) ?></span></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 small text-muted">Contract: <code class="wallet-code"><?= e($coin['contract']) ?></code>
                    <?php if($coin['pair_url']): ?> &middot; <a href="<?= e($coin['pair_url']) ?>" target="_blank" rel="noopener" class="link-primary-mr">View pair <i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
                </div>
            </div></div>

            <!-- AI Risk breakdown -->
            <div class="card glass-card mb-3"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-robot text-success me-2"></i>AI Risk Analysis</h6>
                <div class="alert alert-info-soft small"><i class="fa-solid fa-lightbulb me-1"></i><?= e($insight) ?></div>
                <?php foreach ($analysis['factors'] as $name => $val):
                    $pct = min(100, (int)round($val / 18 * 100)); ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span><?= e($name) ?></span><span class="text-muted"><?= (int)$val ?> pts</span></div>
                        <div class="progress mr-progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div></div>

            <!-- Rug pull detector -->
            <div class="card glass-card"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Rug-Pull Detector</h6>
                <?php if ($warnings): ?>
                    <div class="alert alert-danger-soft"><strong><i class="fa-solid fa-skull-crossbones me-1"></i>High Risk &mdash; <?= count($warnings) ?> warning(s)</strong></div>
                    <ul class="rug-list">
                        <?php foreach ($warnings as $w): ?><li><i class="fa-solid fa-circle-exclamation text-danger me-2"></i><?= e($w) ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-success-soft mb-0"><i class="fa-solid fa-circle-check me-1"></i>No critical rug-pull indicators detected. Always do your own research.</div>
                <?php endif; ?>
                <div class="row g-2 mt-2 small">
                    <?php
                    $checks = [
                        ['Liquidity Locked', !empty($coin['liquidity_locked'])],
                        ['Mint Disabled', empty($coin['mint_enabled'])],
                        ['Not Honeypot', empty($coin['is_honeypot'])],
                        ['No Blacklist', empty($coin['has_blacklist'])],
                        ['Low Whale %', (float)$coin['whale_percent'] < 40],
                        ['Renounced Owner', empty($coin['owner_privileges'])],
                    ];
                    foreach ($checks as $ck): ?>
                        <div class="col-6 col-md-4"><span class="check-pill <?= $ck[1]?'ok':'bad' ?>"><i class="fa-solid fa-<?= $ck[1]?'check':'xmark' ?> me-1"></i><?= e($ck[0]) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div></div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card glass-card mb-3 text-center"><div class="card-body">
                <h6 class="text-muted">AI Safety Score</h6>
                <div class="score-ring" style="--score:<?= (int)$coin['ai_score'] ?>;--ring:<?= $rcolor ?>">
                    <span><?= (int)$coin['ai_score'] ?></span>
                </div>
                <div class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr mt-2"><?= $rl ?> RISK</div>
            </div></div>

            <div class="card glass-card"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-wallet text-warning me-2"></i>Smart Money</h6>
                <?php if (!$smartMoney): ?>
                    <p class="text-muted small mb-0">No tracked wallet activity for this token yet.</p>
                <?php endif; ?>
                <?php foreach ($smartMoney as $s): ?>
                    <div class="radar-row">
                        <span class="coin-ident"><span class="coin-avatar sm <?= $s['action']==='buy'?'buy':'sell' ?>"><i class="fa-solid fa-<?= $s['action']==='buy'?'arrow-up':'arrow-down' ?>"></i></span><span><strong><?= e($s['label'] ?: substr($s['wallet'],0,6).'...') ?></strong><small class="text-muted d-block"><?= time_ago($s['tx_date']) ?></small></span></span>
                        <span class="text-end"><strong><?= money($s['amount_usd']) ?></strong></span>
                    </div>
                <?php endforeach; ?>
            </div></div>
        </div>
    </div>
</div>
<?php
$pageScript = 'MR.initWatchButtons();';
require __DIR__ . '/includes/partials/footer.php';
?>
