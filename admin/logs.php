<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    if (($_POST['action'] ?? '') === 'clear') {
        $db->run('DELETE FROM logs WHERE created_at < NOW() - INTERVAL 30 DAY');
        flash('admin_status', 'Old logs (30+ days) cleared.');
    }
    redirect('admin/logs.php');
}

$type  = trim($_GET['type'] ?? '');
$where = $type !== '' ? 'WHERE type = ?' : '';
$args  = $type !== '' ? [$type] : [];
$logs  = $db->fetchAll("SELECT * FROM logs $where ORDER BY created_at DESC LIMIT 300", $args);
$types = $db->fetchAll('SELECT DISTINCT type FROM logs ORDER BY type');

admin_header('Logs');
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="get">
        <select name="type" class="form-select" onchange="this.form.submit()">
            <option value="">All types</option>
            <?php foreach ($types as $t): ?><option value="<?= e($t['type']) ?>" <?= $type===$t['type']?'selected':'' ?>><?= e($t['type']) ?></option><?php endforeach; ?>
        </select>
    </form>
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="clear"><button class="btn btn-outline-danger" onclick="return confirm('Delete logs older than 30 days?')"><i class="fa-solid fa-broom me-1"></i>Clear old logs</button></form>
</div>

<div class="card glass-card"><div class="card-body p-0"><div class="table-responsive">
<table class="table mr-table align-middle mb-0">
    <thead><tr><th>Type</th><th>Message</th><th>User</th><th>IP</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
        <tr><td><span class="badge bg-secondary-soft"><?= e($l['type']) ?></span></td>
        <td class="small"><?= e($l['message']) ?></td>
        <td class="small text-muted"><?= $l['user_id'] ? '#'.$l['user_id'] : '&mdash;' ?></td>
        <td class="small text-muted"><?= e($l['ip'] ?? '') ?></td>
        <td class="small text-muted" title="<?= e($l['created_at']) ?>"><?= time_ago($l['created_at']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted py-4">No logs.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div></div>
<?php admin_footer(); ?>
