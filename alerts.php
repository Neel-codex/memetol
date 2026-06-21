<?php
require __DIR__ . '/includes/bootstrap.php';
Auth::require();

$db     = Database::instance();
$userId = Auth::id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $type      = $_POST['type'] ?? 'price';
        $coinId    = ($_POST['coin_id'] ?? '') !== '' ? (int)$_POST['coin_id'] : null;
        $condition = $_POST['condition_type'] ?? 'above';
        $threshold = (float)($_POST['threshold'] ?? 0);
        $channels  = $_POST['channel'] ?? ['browser'];
        $channelStr= implode(',', array_intersect((array)$channels, ['browser','telegram','email']));
        if ($channelStr === '') $channelStr = 'browser';

        if (!in_array($type, ['price','volume','new_listing','ai_buy'], true)) {
            $errors[] = 'Invalid alert type.';
        } else {
            $db->run(
                'INSERT INTO alerts (user_id, coin_id, type, condition_type, threshold, channel, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)',
                [$userId, $coinId, $type, $condition, $threshold, $channelStr]
            );
            flash('status', 'Alert created.');
            redirect('alerts.php');
        }
    } elseif ($action === 'delete') {
        $db->run('DELETE FROM alerts WHERE id = ? AND user_id = ?', [(int)$_POST['id'], $userId]);
        redirect('alerts.php');
    } elseif ($action === 'toggle') {
        $db->run('UPDATE alerts SET is_active = 1 - is_active WHERE id = ? AND user_id = ?', [(int)$_POST['id'], $userId]);
        redirect('alerts.php');
    }
}

$alerts = $db->fetchAll(
    'SELECT a.*, c.symbol, c.name FROM alerts a LEFT JOIN coins c ON c.id = a.coin_id
     WHERE a.user_id = ? ORDER BY a.created_at DESC',
    [$userId]
);
$coins   = $db->fetchAll('SELECT id, name, symbol FROM coins WHERE is_hidden = 0 ORDER BY symbol ASC LIMIT 200');
$preCoin = (int)($_GET['coin'] ?? 0);

$pageTitle = 'Alerts';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-4">
    <h3 class="mb-4"><i class="fa-solid fa-bell text-warning me-2"></i>Alerts</h3>

    <?php if ($msg = flash('status')): ?><div class="alert alert-success py-2"><?= e($msg) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card glass-card"><div class="card-body">
                <h6 class="mb-3">Create new alert</h6>
                <form method="post">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label small">Alert type</label>
                        <select name="type" id="alertType" class="form-select">
                            <option value="price">Price</option>
                            <option value="volume">Volume</option>
                            <option value="new_listing">New listings</option>
                            <option value="ai_buy">AI buy signal</option>
                        </select>
                    </div>
                    <div class="mb-3" id="coinSelectWrap">
                        <label class="form-label small">Coin</label>
                        <select name="coin_id" class="form-select">
                            <option value="">Any coin</option>
                            <?php foreach ($coins as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $preCoin===(int)$c['id']?'selected':'' ?>><?= e($c['symbol']) ?> &mdash; <?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3" id="thresholdWrap">
                        <div class="col-5">
                            <label class="form-label small">Condition</label>
                            <select name="condition_type" class="form-select"><option value="above">Above</option><option value="below">Below</option></select>
                        </div>
                        <div class="col-7">
                            <label class="form-label small">Threshold</label>
                            <input type="number" step="any" name="threshold" class="form-control" placeholder="e.g. 0.0001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small d-block">Channels</label>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="channel[]" value="browser" id="ch_b" checked><label class="form-check-label small" for="ch_b">Browser</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="channel[]" value="telegram" id="ch_t"><label class="form-check-label small" for="ch_t">Telegram</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="channel[]" value="email" id="ch_e"><label class="form-check-label small" for="ch_e">Email</label></div>
                    </div>
                    <button class="btn btn-mr-primary w-100"><i class="fa-solid fa-plus me-1"></i>Create Alert</button>
                </form>
                <button id="enableNotif" class="btn btn-outline-light btn-sm w-100 mt-2"><i class="fa-solid fa-bell me-1"></i>Enable browser notifications</button>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card glass-card"><div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mr-table align-middle mb-0">
                        <thead><tr><th>Type</th><th>Coin</th><th>Condition</th><th>Channels</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php if (!$alerts): ?><tr><td colspan="6" class="text-center text-muted py-4">No alerts yet.</td></tr><?php endif; ?>
                        <?php foreach ($alerts as $a): ?>
                            <tr>
                                <td><span class="badge bg-secondary-soft"><?= e(str_replace('_',' ',$a['type'])) ?></span></td>
                                <td><?= e($a['symbol'] ?? 'Any') ?></td>
                                <td class="small"><?= in_array($a['type'],['price','volume'],true) ? e(ucfirst($a['condition_type']).' '.rtrim(rtrim(number_format((float)$a['threshold'],8,'.',''),'0'),'.')) : '&mdash;' ?></td>
                                <td class="small text-muted"><?= e($a['channel']) ?></td>
                                <td>
                                    <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button class="btn btn-sm <?= $a['is_active']?'btn-success':'btn-outline-secondary' ?>"><?= $a['is_active']?'Active':'Paused' ?></button>
                                    </form>
                                </td>
                                <td><form method="post" class="d-inline" onsubmit="return confirm('Delete this alert?')"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-icon"><i class="fa-solid fa-trash text-danger"></i></button></form></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>
</div>
<?php
$pageScript = '
const at = document.getElementById("alertType");
function toggleFields(){ const v = at.value; const tw=document.getElementById("thresholdWrap"); const cw=document.getElementById("coinSelectWrap");
  tw.style.display = (v==="price"||v==="volume")?"flex":"none"; }
at.addEventListener("change", toggleFields); toggleFields();
document.getElementById("enableNotif").addEventListener("click", ()=>MR.requestNotifications());
';
require __DIR__ . '/includes/partials/footer.php';
?>
