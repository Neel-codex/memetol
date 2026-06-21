<?php
require __DIR__ . '/includes/bootstrap.php';

$db = Database::instance();

// Stats for hero
$stats = [
    'coins'    => (int) $db->scalar('SELECT COUNT(*) FROM coins WHERE is_hidden = 0'),
    'newToday' => (int) $db->scalar("SELECT COUNT(*) FROM coins WHERE created_at >= CURDATE()"),
    'users'    => (int) $db->scalar('SELECT COUNT(*) FROM users'),
    'volume'   => (float) $db->scalar('SELECT COALESCE(SUM(volume),0) FROM coins WHERE is_hidden = 0'),
];

$featured = $db->fetchAll(
    'SELECT * FROM coins WHERE is_hidden = 0 ORDER BY is_featured DESC, ai_score DESC LIMIT 6'
);

$plans = $db->fetchAll('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC');

$heroBanner = active_banner('hero');

$pageTitle = null; // use SEO default
$bodyClass = 'landing-page';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<?php if (!empty($heroBanner['image'])): ?>
<div class="container mt-3">
    <a href="<?= e($heroBanner['link'] ?: '#') ?>" class="d-block"><img src="<?= e($heroBanner['image']) ?>" class="img-fluid rounded-4 w-100" alt="<?= e($heroBanner['title']) ?>"></a>
</div>
<?php endif; ?>
<!-- HERO -->
<section class="hero-section">
    <div class="hero-glow"></div>
    <div class="container position-relative">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <span class="badge-pill mb-3"><i class="fa-solid fa-bolt me-1"></i> Live across 5 chains</span>
                <h1 class="hero-title">Detect the next <span class="text-gradient">meme coin</span> before it pumps</h1>
                <p class="hero-sub">AI-powered scanner that finds brand-new tokens, scores rug-pull risk in real time, tracks smart-money wallets and sends you instant signals.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="<?= base_url('register.php') ?>" class="btn btn-mr-primary btn-lg"><i class="fa-solid fa-rocket me-2"></i>Start Free</a>
                    <a href="<?= base_url('trending.php') ?>" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-fire me-2"></i>View Trending</a>
                </div>
                <div class="hero-stats mt-5">
                    <div><span class="stat-num"><?= number_format($stats['coins']) ?></span><span class="stat-label">Coins tracked</span></div>
                    <div><span class="stat-num"><?= number_format($stats['newToday']) ?></span><span class="stat-label">New today</span></div>
                    <div><span class="stat-num"><?= money($stats['volume']) ?></span><span class="stat-label">24h volume</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card glass-card hero-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fa-solid fa-radar text-primary-mr me-2"></i>Live Radar</h6>
                            <span class="badge bg-success-soft"><span class="pulse-dot"></span> Scanning</span>
                        </div>
                        <?php foreach (array_slice($featured, 0, 4) as $c):
                            [$rl,$rc] = risk_label((int)$c['ai_score']); ?>
                            <a href="<?= base_url('coin.php?id='.$c['id']) ?>" class="radar-row">
                                <span class="coin-ident">
                                    <span class="coin-avatar"><?= e(strtoupper(substr($c['symbol'],0,2))) ?></span>
                                    <span>
                                        <strong><?= e($c['symbol']) ?></strong>
                                        <small class="text-muted d-block"><?= e(ucfirst($c['chain'])) ?></small>
                                    </span>
                                </span>
                                <span class="text-end">
                                    <strong><?= money($c['price']) ?></strong>
                                    <small class="d-block <?= $c['price_change_24h']>=0?'text-success':'text-danger' ?>">
                                        <?= ($c['price_change_24h']>=0?'+':'') . number_format((float)$c['price_change_24h'],1) ?>%
                                    </small>
                                </span>
                                <span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr">AI <?= (int)$c['ai_score'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="container py-5">
    <div class="text-center mb-5">
        <h2 class="section-title">Everything you need to trade meme coins safely</h2>
        <p class="text-muted">Institutional-grade tooling, built for degens.</p>
    </div>
    <div class="row g-4">
        <?php
        $features = [
            ['fa-magnifying-glass-chart','New Coin Scanner','Auto-scans Ethereum, BNB, Solana, Base &amp; Polygon for freshly launched pairs.'],
            ['fa-shield-halved','AI Risk Score','0-100 safety score from liquidity, holders, volume, age &amp; smart-money signals.'],
            ['fa-triangle-exclamation','Rug-Pull Detector','Flags mint functions, honeypots, unlocked liquidity, whale concentration &amp; hidden taxes.'],
            ['fa-wallet','Smart Money Tracker','Follow profitable wallets and see what they buy in real time.'],
            ['fa-bell','Instant Alerts','Price, volume, new-listing &amp; AI-buy alerts via browser, Telegram &amp; email.'],
            ['fa-chart-line','Live Analytics','Volume, market-cap, social-trend &amp; AI-sentiment charts updated continuously.'],
        ];
        foreach ($features as $f): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card glass-card feature-card h-100">
                    <div class="card-body">
                        <span class="feature-icon"><i class="fa-solid <?= $f[0] ?>"></i></span>
                        <h5 class="mt-3"><?= $f[1] ?></h5>
                        <p class="text-muted small mb-0"><?= $f[2] ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- FEATURED COINS -->
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="section-title mb-0">AI Top Picks</h2>
        <a href="<?= base_url('trending.php') ?>" class="link-primary-mr">View all <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="row g-4">
        <?php foreach ($featured as $c):
            [$rl,$rc] = risk_label((int)$c['ai_score']); ?>
            <div class="col-md-6 col-lg-4">
                <a href="<?= base_url('coin.php?id='.$c['id']) ?>" class="text-decoration-none">
                    <div class="card glass-card coin-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="coin-ident">
                                    <span class="coin-avatar"><?= e(strtoupper(substr($c['symbol'],0,2))) ?></span>
                                    <div>
                                        <h6 class="mb-0"><?= e($c['name']) ?></h6>
                                        <small class="text-muted"><?= e($c['symbol']) ?> &middot; <?= e(ucfirst($c['chain'])) ?></small>
                                    </div>
                                </div>
                                <span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr"><?= $rl ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div><small class="text-muted d-block">Price</small><strong><?= money($c['price']) ?></strong></div>
                                <div><small class="text-muted d-block">24h</small><strong class="<?= $c['price_change_24h']>=0?'text-success':'text-danger' ?>"><?= ($c['price_change_24h']>=0?'+':'').number_format((float)$c['price_change_24h'],1) ?>%</strong></div>
                                <div><small class="text-muted d-block">AI Score</small><strong class="text-<?= $rc ?>-mr"><?= (int)$c['ai_score'] ?></strong></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- PRICING TEASER -->
<section class="container py-5">
    <div class="text-center mb-5">
        <h2 class="section-title">Simple, transparent pricing</h2>
        <p class="text-muted">Start free. Upgrade when you are ready to go pro.</p>
    </div>
    <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $i => $p):
            $feats = array_filter(array_map('trim', explode('|', (string)$p['features']))); ?>
            <div class="col-md-6 col-lg-4">
                <div class="card glass-card pricing-card h-100 <?= $p['slug']==='pro' ? 'pricing-featured' : '' ?>">
                    <div class="card-body text-center p-4">
                        <?php if ($p['slug']==='pro'): ?><span class="badge bg-primary-mr mb-2">Most Popular</span><?php endif; ?>
                        <h4><?= e($p['name']) ?></h4>
                        <div class="price-tag"><?= $p['price']>0 ? '$'.number_format((float)$p['price'],0) : 'Free' ?><?php if($p['price']>0): ?><span>/mo</span><?php endif; ?></div>
                        <ul class="plan-feats">
                            <?php foreach ($feats as $ft): ?><li><i class="fa-solid fa-check"></i> <?= e($ft) ?></li><?php endforeach; ?>
                        </ul>
                        <a href="<?= base_url('register.php') ?>" class="btn <?= $p['slug']==='pro'?'btn-mr-primary':'btn-outline-light' ?> w-100 mt-2">Choose <?= e($p['name']) ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- CTA -->
<section class="container py-5">
    <div class="card glass-card cta-card text-center p-5">
        <h2 class="mb-3">Ready to catch the next 100x?</h2>
        <p class="text-muted mb-4">Join traders using AI to scan, score and snipe meme coins across 5 chains.</p>
        <div><a href="<?= base_url('register.php') ?>" class="btn btn-mr-primary btn-lg"><i class="fa-solid fa-rocket me-2"></i>Create Free Account</a></div>
    </div>
</section>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
