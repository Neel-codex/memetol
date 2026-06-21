<?php
require __DIR__ . '/includes/bootstrap.php';
Auth::require();

$db   = Database::instance();
$user = Auth::user();

// Top cards
$cards = [
    'new_today'   => (int) $db->scalar("SELECT COUNT(*) FROM coins WHERE created_at >= CURDATE() AND is_hidden = 0"),
    'trending'    => (int) $db->scalar("SELECT COUNT(*) FROM coins WHERE volume > 50000 AND is_hidden = 0"),
    'ai_buys'     => (int) $db->scalar("SELECT COUNT(*) FROM coins WHERE ai_score >= 70 AND is_hidden = 0"),
    'total_volume'=> (float) $db->scalar("SELECT COALESCE(SUM(volume),0) FROM coins WHERE is_hidden = 0"),
];

$newCoins   = $db->fetchAll("SELECT * FROM coins WHERE is_hidden = 0 ORDER BY created_at DESC, id DESC LIMIT 8");
$aiPicks    = $db->fetchAll("SELECT * FROM coins WHERE is_hidden = 0 AND ai_score >= 65 ORDER BY ai_score DESC LIMIT 6");
$smartMoney = $db->fetchAll("SELECT * FROM smart_money ORDER BY tx_date DESC LIMIT 8");

// Chart data: top coins by volume
$topVol = $db->fetchAll("SELECT symbol, volume, market_cap, social_score, ai_score FROM coins WHERE is_hidden = 0 ORDER BY volume DESC LIMIT 7");
$chartLabels = array_map(fn($r) => $r['symbol'], $topVol);
$chartVolume = array_map(fn($r) => round((float)$r['volume']), $topVol);
$chartMcap   = array_map(fn($r) => round((float)$r['market_cap']), $topVol);
$chartSocial = array_map(fn($r) => (int)$r['social_score'], $topVol);
$chartAi     = array_map(fn($r) => (int)$r['ai_score'], $topVol);

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">Welcome back, <?= e($user['username']) ?> <i class="fa-solid fa-hand-sparkles text-warning"></i></h3>
            <small class="text-muted">Last scan: <?= e(Settings::get('last_scan_at') ?: 'pending') ?></small>
        </div>
        <button id="rescanBtn" class="btn btn-mr-primary btn-sm"><i class="fa-solid fa-rotate me-1"></i>Refresh Scan</button>
    </div>

    <!-- TOP CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card glass-card stat-card">
                <div class="card-body">
                    <span class="stat-icon bg-primary-soft"><i class="fa-solid fa-seedling text-primary-mr"></i></span>
                    <div class="stat-value"><?= number_format($cards['new_today']) ?></div>
                    <div class="stat-caption">New Tokens Today</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card glass-card stat-card">
                <div class="card-body">
                    <span class="stat-icon bg-warning-soft"><i class="fa-solid fa-fire text-warning"></i></span>
                    <div class="stat-value"><?= number_format($cards['trending']) ?></div>
                    <div class="stat-caption">Trending Coins</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card glass-card stat-card">
                <div class="card-body">
                    <span class="stat-icon bg-success-soft"><i class="fa-solid fa-robot text-success"></i></span>
                    <div class="stat-value"><?= number_format($cards['ai_buys']) ?></div>
                    <div class="stat-caption">AI Buy Signals</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card glass-card stat-card">
                <div class="card-body">
                    <span class="stat-icon bg-info-soft"><i class="fa-solid fa-chart-column text-info"></i></span>
                    <div class="stat-value"><?= money($cards['total_volume']) ?></div>
                    <div class="stat-caption">Total Market Volume</div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card glass-card h-100"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-chart-column text-primary-mr me-2"></i>24h Volume (Top Coins)</h6>
                <canvas id="volumeChart" height="140"></canvas>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card glass-card h-100"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-coins text-info me-2"></i>Market Cap</h6>
                <canvas id="mcapChart" height="140"></canvas>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card glass-card h-100"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-hashtag text-warning me-2"></i>Social Trend</h6>
                <canvas id="socialChart" height="140"></canvas>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card glass-card h-100"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-brain text-success me-2"></i>AI Sentiment</h6>
                <canvas id="aiChart" height="140"></canvas>
            </div></div>
        </div>
    </div>

    <!-- RECENT SECTIONS -->
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card glass-card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fa-solid fa-seedling text-primary-mr me-2"></i>New Meme Coins</h6>
                    <a href="<?= base_url('trending.php?cat=new') ?>" class="small link-primary-mr">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table mr-table align-middle mb-0">
                        <thead><tr><th>Coin</th><th>Price</th><th>24h</th><th>Liq</th><th>AI</th></tr></thead>
                        <tbody>
                        <?php foreach ($newCoins as $c): [$rl,$rc]=risk_label((int)$c['ai_score']); ?>
                            <tr onclick="location.href='<?= base_url('coin.php?id='.$c['id']) ?>'" style="cursor:pointer">
                                <td>
                                    <div class="coin-ident">
                                        <span class="coin-avatar sm"><?= e(strtoupper(substr($c['symbol'],0,2))) ?></span>
                                        <span><strong><?= e($c['symbol']) ?></strong><small class="text-muted d-block"><?= e(ucfirst($c['chain'])) ?></small></span>
                                    </div>
                                </td>
                                <td><?= money($c['price']) ?></td>
                                <td class="<?= $c['price_change_24h']>=0?'text-success':'text-danger' ?>"><?= ($c['price_change_24h']>=0?'+':'').number_format((float)$c['price_change_24h'],1) ?>%</td>
                                <td><?= money($c['liquidity']) ?></td>
                                <td><span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr"><?= (int)$c['ai_score'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card glass-card mb-3"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-robot text-success me-2"></i>AI Recommendations</h6>
                <?php foreach ($aiPicks as $c): [$rl,$rc]=risk_label((int)$c['ai_score']); ?>
                    <a href="<?= base_url('coin.php?id='.$c['id']) ?>" class="radar-row">
                        <span class="coin-ident"><span class="coin-avatar sm"><?= e(strtoupper(substr($c['symbol'],0,2))) ?></span><span><strong><?= e($c['symbol']) ?></strong><small class="text-muted d-block"><?= e(ucfirst($c['chain'])) ?></small></span></span>
                        <span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr">AI <?= (int)$c['ai_score'] ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$aiPicks): ?><p class="text-muted small mb-0">No strong AI picks right now.</p><?php endif; ?>
            </div></div>
            <div class="card glass-card"><div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-wallet text-warning me-2"></i>Smart Money Activity</h6>
                <?php foreach (array_slice($smartMoney,0,6) as $s): ?>
                    <div class="radar-row">
                        <span class="coin-ident">
                            <span class="coin-avatar sm <?= $s['action']==='buy'?'buy':'sell' ?>"><i class="fa-solid fa-<?= $s['action']==='buy'?'arrow-up':'arrow-down' ?>"></i></span>
                            <span><strong><?= e($s['token_symbol']) ?></strong><small class="text-muted d-block"><?= e($s['label'] ?: substr($s['wallet'],0,8).'...') ?></small></span>
                        </span>
                        <span class="text-end"><strong><?= money($s['amount_usd']) ?></strong><small class="d-block text-muted"><?= time_ago($s['tx_date']) ?></small></span>
                    </div>
                <?php endforeach; ?>
                <a href="<?= base_url('trending.php') ?>" class="small link-primary-mr">View smart money</a>
            </div></div>
        </div>
    </div>
</div>

<?php
$pageScript = '
const LBL = ' . json_encode($chartLabels) . ';
MR.barChart("volumeChart", LBL, ' . json_encode($chartVolume) . ', "Volume ($)", "#00ff99");
MR.barChart("mcapChart", LBL, ' . json_encode($chartMcap) . ', "Market Cap ($)", "#00c3ff");
MR.lineChart("socialChart", LBL, ' . json_encode($chartSocial) . ', "Social Score", "#ffcc00");
MR.radarChart("aiChart", LBL, ' . json_encode($chartAi) . ', "AI Score");
document.getElementById("rescanBtn")?.addEventListener("click", function(){
    this.disabled = true; this.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i>Scanning...";
    MR.api("scan").then(()=>location.reload()).catch(()=>{ this.disabled=false; this.innerHTML="Refresh Scan"; });
});
';
require __DIR__ . '/includes/partials/footer.php';
?>
