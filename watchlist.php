<?php
require __DIR__ . '/includes/bootstrap.php';
Auth::require();

$db     = Database::instance();
$userId = Auth::id();

$coins = $db->fetchAll(
    'SELECT c.* FROM watchlist w JOIN coins c ON c.id = w.coin_id
     WHERE w.user_id = ? ORDER BY w.created_at DESC',
    [$userId]
);

$pageTitle = 'My Watchlist';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="fa-solid fa-star text-warning me-2"></i>My Watchlist</h3>
        <a href="<?= base_url('trending.php') ?>" class="btn btn-mr-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add coins</a>
    </div>

    <?php if (!$coins): ?>
        <div class="card glass-card"><div class="card-body text-center py-5">
            <i class="fa-regular fa-star fa-3x text-muted mb-3"></i>
            <h5>Your watchlist is empty</h5>
            <p class="text-muted">Track coins to monitor their price, AI score and risk in one place.</p>
            <a href="<?= base_url('trending.php') ?>" class="btn btn-mr-primary"><i class="fa-solid fa-fire me-1"></i>Browse trending</a>
        </div></div>
    <?php else: ?>
        <div class="card glass-card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mr-table align-middle mb-0">
                    <thead><tr><th>Coin</th><th>Price</th><th>24h</th><th>AI Score</th><th>Risk</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($coins as $c): [$rl,$rc]=risk_label((int)$c['ai_score']); ?>
                        <tr>
                            <td onclick="location.href='<?= base_url('coin.php?id='.$c['id']) ?>'" style="cursor:pointer">
                                <div class="coin-ident"><span class="coin-avatar sm"><?= e(strtoupper(substr($c['symbol'],0,2))) ?></span><span><strong><?= e($c['name']) ?></strong><small class="text-muted d-block"><?= e($c['symbol']) ?> &middot; <?= e(ucfirst($c['chain'])) ?></small></span></div>
                            </td>
                            <td><?= money($c['price']) ?></td>
                            <td class="<?= $c['price_change_24h']>=0?'text-success':'text-danger' ?>"><?= ($c['price_change_24h']>=0?'+':'').number_format((float)$c['price_change_24h'],1) ?>%</td>
                            <td><strong class="text-<?= $rc ?>-mr"><?= (int)$c['ai_score'] ?></strong></td>
                            <td><span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr"><?= $rl ?></span></td>
                            <td class="text-end"><button class="btn btn-icon watch-btn active" data-coin="<?= (int)$c['id'] ?>" title="Remove"><i class="fa-solid fa-trash text-danger"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    <?php endif; ?>
</div>
<?php
$pageScript = 'MR.initWatchButtons(true);';
require __DIR__ . '/includes/partials/footer.php';
?>
