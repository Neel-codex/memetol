<?php
require __DIR__ . '/includes/bootstrap.php';

$db        = Database::instance();
$loggedIn  = Auth::check();
$userId    = Auth::id();

$cat   = $_GET['cat'] ?? 'trending';
$q     = trim($_GET['q'] ?? '');
$chain = $_GET['chain'] ?? '';

$categories = [
    'viewed'   => 'Most Viewed',
    'gainers'  => 'Top Gainers',
    'trending' => 'Trending',
    'new'      => 'New Listings',
    'ai'       => 'AI Picks',
];

// Build query
$where  = ['is_hidden = 0'];
$params = [];

if ($q !== '') {
    // Live-search via DexScreener and persist hits so user sees fresh data
    foreach (Scanner::searchDex($q) as $hit) {
        if (($hit['liquidity'] ?? 0) >= 500) {
            Scanner::enrichAndStore($hit);
        }
    }
    $where[]  = '(name LIKE ? OR symbol LIKE ? OR contract LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($chain !== '' && array_key_exists($chain, Scanner::CHAINS)) {
    $where[]  = 'chain = ?';
    $params[] = $chain;
}

switch ($cat) {
    case 'viewed':  $order = 'views DESC, volume DESC'; break;
    case 'gainers': $order = 'price_change_24h DESC'; break;
    case 'new':     $order = 'created_at DESC, id DESC'; break;
    case 'ai':      $order = 'ai_score DESC'; $where[] = 'ai_score >= 60'; break;
    default:        $order = 'volume DESC'; break;
}

$coins = $db->fetchAll(
    'SELECT * FROM coins WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 60",
    $params
);

// Watchlist ids for current user
$watchIds = [];
if ($loggedIn) {
    foreach ($db->fetchAll('SELECT coin_id FROM watchlist WHERE user_id = ?', [$userId]) as $w) {
        $watchIds[(int)$w['coin_id']] = true;
    }
}

$smartMoney = $db->fetchAll('SELECT * FROM smart_money ORDER BY tx_date DESC LIMIT 12');

$pageTitle = 'Trending Meme Coins';
$pageDesc  = 'Browse trending meme coins, top gainers, new listings and AI picks across Ethereum, BNB, Solana, Base and Polygon.';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0"><i class="fa-solid fa-fire text-warning me-2"></i>Trending</h3>
        <form class="d-flex gap-2" method="get">
            <input type="hidden" name="cat" value="<?= e($cat) ?>">
            <input class="form-control form-control-sm" type="search" name="q" placeholder="Search name, symbol, contract" value="<?= e($q) ?>" style="min-width:220px">
            <select name="chain" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                <option value="">All chains</option>
                <?php foreach (Scanner::CHAINS as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $chain===$k?'selected':'' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-mr-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    <!-- Category tabs -->
    <ul class="nav nav-pills mr-pills mb-4 flex-nowrap overflow-auto">
        <?php foreach ($categories as $k => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $cat===$k?'active':'' ?>" href="<?= base_url('trending.php?cat='.$k . ($chain?'&chain='.$chain:'') . ($q?'&q='.urlencode($q):'')) ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($q !== ''): ?>
        <p class="text-muted small">Showing results for "<strong><?= e($q) ?></strong>" (<?= count($coins) ?> found)</p>
    <?php endif; ?>

    <div class="card glass-card mb-4"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mr-table align-middle mb-0">
                <thead><tr>
                    <th>Coin</th><th>Price</th><th>24h</th><th>Liquidity</th><th>Volume</th><th>AI Score</th><th>Risk</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php if (!$coins): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No coins found. Try a different search or run a scan.</td></tr>
                <?php endif; ?>
                <?php foreach ($coins as $c): [$rl,$rc]=risk_label((int)$c['ai_score']); $inW = isset($watchIds[(int)$c['id']]); ?>
                    <tr>
                        <td onclick="location.href='<?= base_url('coin.php?id='.$c['id']) ?>'" style="cursor:pointer">
                            <div class="coin-ident">
                                <span class="coin-avatar sm"><?= e(strtoupper(substr($c['symbol'],0,2))) ?></span>
                                <span><strong><?= e($c['name']) ?></strong><small class="text-muted d-block"><?= e($c['symbol']) ?> &middot; <?= e(ucfirst($c['chain'])) ?></small></span>
                            </div>
                        </td>
                        <td><?= money($c['price']) ?></td>
                        <td class="<?= $c['price_change_24h']>=0?'text-success':'text-danger' ?>"><?= ($c['price_change_24h']>=0?'+':'').number_format((float)$c['price_change_24h'],1) ?>%</td>
                        <td><?= money($c['liquidity']) ?></td>
                        <td><?= money($c['volume']) ?></td>
                        <td><strong class="text-<?= $rc ?>-mr"><?= (int)$c['ai_score'] ?></strong></td>
                        <td><span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr"><?= $rl ?></span></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-icon watch-btn <?= $inW?'active':'' ?>" data-coin="<?= (int)$c['id'] ?>" title="Watchlist"><i class="fa-<?= $inW?'solid':'regular' ?> fa-star"></i></button>
                            <a class="btn btn-icon" href="<?= base_url('alerts.php?coin='.$c['id']) ?>" title="Set alert"><i class="fa-regular fa-bell"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>

    <!-- SMART MONEY TRACKER -->
    <h4 class="mb-3"><i class="fa-solid fa-wallet text-warning me-2"></i>Smart Money Tracker</h4>
    <div class="card glass-card"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mr-table align-middle mb-0">
                <thead><tr><th>Wallet</th><th>Action</th><th>Token</th><th>Amount</th><th>Est. Profit</th><th>Chain</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($smartMoney as $s): ?>
                    <tr>
                        <td><code class="wallet-code"><?= e(substr($s['wallet'],0,6).'...'.substr($s['wallet'],-4)) ?></code><?php if($s['label']): ?> <span class="badge bg-info-soft text-info"><?= e($s['label']) ?></span><?php endif; ?></td>
                        <td><span class="badge bg-<?= $s['action']==='buy'?'success':'danger' ?>-soft text-<?= $s['action']==='buy'?'success':'danger' ?>"><?= strtoupper($s['action']) ?></span></td>
                        <td><strong><?= e($s['token_symbol']) ?></strong></td>
                        <td><?= money($s['amount_usd']) ?></td>
                        <td class="<?= $s['est_profit']>=0?'text-success':'text-danger' ?>"><?= ($s['est_profit']>=0?'+':'').money(abs($s['est_profit'])) ?></td>
                        <td><?= e(ucfirst($s['chain'])) ?></td>
                        <td class="text-muted small"><?= time_ago($s['tx_date']) ?></td>
                        <td><?php if($loggedIn): ?><button class="btn btn-icon follow-btn" data-wallet="<?= e($s['wallet']) ?>" title="Follow wallet"><i class="fa-solid fa-user-plus"></i></button><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>
<?php
$pageScript = 'MR.initWatchButtons(); MR.initFollowButtons();';
require __DIR__ . '/includes/partials/footer.php';
?>
