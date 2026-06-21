<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $coin = [
            'contract'   => trim($_POST['contract'] ?? ''),
            'name'       => trim($_POST['name'] ?? ''),
            'symbol'     => strtoupper(trim($_POST['symbol'] ?? '')),
            'chain'      => $_POST['chain'] ?? 'ethereum',
            'price'      => (float)($_POST['price'] ?? 0),
            'price_change_24h' => (float)($_POST['price_change_24h'] ?? 0),
            'market_cap' => (float)($_POST['market_cap'] ?? 0),
            'liquidity'  => (float)($_POST['liquidity'] ?? 0),
            'volume'     => (float)($_POST['volume'] ?? 0),
            'holders'    => (int)($_POST['holders'] ?? 0),
            'whale_percent' => (float)($_POST['whale_percent'] ?? 0),
            'liquidity_locked' => isset($_POST['liquidity_locked']) ? 1 : 0,
        ];
        if ($coin['contract'] && $coin['symbol']) {
            Scanner::enrichAndStore($coin);
            flash('admin_status', 'Coin added.');
        } else {
            flash('admin_error', 'Contract and symbol are required.');
        }
    } elseif ($action === 'update' && $id) {
        $db->run(
            'UPDATE coins SET name=?, symbol=?, ai_score=?, risk_level=?, is_featured=?, is_hidden=? WHERE id=?',
            [
                trim($_POST['name'] ?? ''),
                strtoupper(trim($_POST['symbol'] ?? '')),
                max(0, min(100, (int)($_POST['ai_score'] ?? 0))),
                $_POST['risk_level'] ?? 'MEDIUM',
                isset($_POST['is_featured']) ? 1 : 0,
                isset($_POST['is_hidden']) ? 1 : 0,
                $id,
            ]
        );
        flash('admin_status', 'Coin updated.');
    } elseif ($action === 'delete' && $id) {
        $db->run('DELETE FROM coins WHERE id=?', [$id]);
        flash('admin_status', 'Coin deleted.');
    } elseif ($action === 'scan') {
        $n = Scanner::scanTrending();
        flash('admin_status', "Scan complete: {$n} coins upserted.");
    }
    redirect('admin/coins.php');
}

$q     = trim($_GET['q'] ?? '');
$where = $q !== '' ? 'WHERE name LIKE ? OR symbol LIKE ? OR contract LIKE ?' : '';
$args  = $q !== '' ? ["%$q%","%$q%","%$q%"] : [];
$coins = $db->fetchAll("SELECT * FROM coins $where ORDER BY updated_at DESC LIMIT 200", $args);

admin_header('Coins');
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="get"><input class="form-control" type="search" name="q" placeholder="Search coins" value="<?= e($q) ?>"><button class="btn btn-mr-primary"><i class="fa-solid fa-magnifying-glass"></i></button></form>
    <div class="d-flex gap-2">
        <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="scan"><button class="btn btn-outline-light"><i class="fa-solid fa-rotate me-1"></i>Run Scan</button></form>
        <button class="btn btn-mr-primary" data-bs-toggle="modal" data-bs-target="#addCoin"><i class="fa-solid fa-plus me-1"></i>Add Coin</button>
    </div>
</div>

<div class="card glass-card"><div class="card-body p-0"><div class="table-responsive">
<table class="table mr-table align-middle mb-0">
    <thead><tr><th>Coin</th><th>Chain</th><th>Price</th><th>AI</th><th>Risk</th><th>Featured</th><th>Hidden</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($coins as $c): [$rl,$rc]=risk_label((int)$c['ai_score']); ?>
        <tr>
            <td><strong><?= e($c['symbol']) ?></strong><small class="text-muted d-block"><?= e($c['name']) ?></small></td>
            <td class="small"><?= e(ucfirst($c['chain'])) ?></td>
            <td><?= money($c['price']) ?></td>
            <td><strong class="text-<?= $rc ?>-mr"><?= (int)$c['ai_score'] ?></strong></td>
            <td><span class="badge bg-<?= $rc ?>-soft text-<?= $rc ?>-mr"><?= $rl ?></span></td>
            <td><?= $c['is_featured'] ? '<i class="fa-solid fa-star text-warning"></i>' : '&mdash;' ?></td>
            <td><?= $c['is_hidden'] ? '<i class="fa-solid fa-eye-slash text-danger"></i>' : '<i class="fa-solid fa-eye text-success"></i>' ?></td>
            <td class="text-end text-nowrap">
                <button class="btn btn-icon" data-bs-toggle="modal" data-bs-target="#editCoin<?= $c['id'] ?>"><i class="fa-solid fa-pen"></i></button>
                <a class="btn btn-icon" href="<?= base_url('coin.php?id='.$c['id']) ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-icon text-danger" onclick="return confirm('Delete this coin?')"><i class="fa-solid fa-trash"></i></button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$coins): ?><tr><td colspan="8" class="text-center text-muted py-4">No coins. Run a scan or add manually.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div></div>

<!-- Add coin modal -->
<div class="modal fade" id="addCoin" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content glass-card">
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="add">
    <div class="modal-header border-secondary"><h5 class="modal-title">Add coin manually</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-2">
        <div class="col-md-6"><label class="form-label small">Name</label><input class="form-control" name="name" required></div>
        <div class="col-md-3"><label class="form-label small">Symbol</label><input class="form-control" name="symbol" required></div>
        <div class="col-md-3"><label class="form-label small">Chain</label><select class="form-select" name="chain"><?php foreach(Scanner::CHAINS as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label small">Contract</label><input class="form-control" name="contract" required></div>
        <div class="col-md-3"><label class="form-label small">Price</label><input class="form-control" type="number" step="any" name="price"></div>
        <div class="col-md-3"><label class="form-label small">24h %</label><input class="form-control" type="number" step="any" name="price_change_24h"></div>
        <div class="col-md-3"><label class="form-label small">Market Cap</label><input class="form-control" type="number" step="any" name="market_cap"></div>
        <div class="col-md-3"><label class="form-label small">Liquidity</label><input class="form-control" type="number" step="any" name="liquidity"></div>
        <div class="col-md-3"><label class="form-label small">Volume</label><input class="form-control" type="number" step="any" name="volume"></div>
        <div class="col-md-3"><label class="form-label small">Holders</label><input class="form-control" type="number" name="holders"></div>
        <div class="col-md-3"><label class="form-label small">Whale %</label><input class="form-control" type="number" step="any" name="whale_percent"></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="liquidity_locked" id="ll"><label class="form-check-label small" for="ll">Liquidity locked</label></div></div>
    </div></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Add &amp; score</button></div>
    </form>
</div></div></div>

<!-- Edit coin modals -->
<?php foreach ($coins as $c): ?>
<div class="modal fade" id="editCoin<?= $c['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $c['id'] ?>">
    <div class="modal-header border-secondary"><h5 class="modal-title">Edit <?= e($c['symbol']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label small">Name</label><input class="form-control" name="name" value="<?= e($c['name']) ?>"></div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Symbol</label><input class="form-control" name="symbol" value="<?= e($c['symbol']) ?>"></div>
            <div class="col-6"><label class="form-label small">AI Score</label><input class="form-control" type="number" min="0" max="100" name="ai_score" value="<?= (int)$c['ai_score'] ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label small">Risk level</label><select class="form-select" name="risk_level"><?php foreach(['LOW','MEDIUM','HIGH'] as $r): ?><option value="<?= $r ?>" <?= $c['risk_level']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="f<?= $c['id'] ?>" <?= $c['is_featured']?'checked':'' ?>><label class="form-check-label small" for="f<?= $c['id'] ?>">Featured</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_hidden" id="h<?= $c['id'] ?>" <?= $c['is_hidden']?'checked':'' ?>><label class="form-check-label small" for="h<?= $c['id'] ?>">Hidden</label></div>
    </div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Save</button></div>
    </form>
</div></div></div>
<?php endforeach; ?>
<?php admin_footer(); ?>
