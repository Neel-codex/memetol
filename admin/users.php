<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'update' && $id) {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $plan     = $_POST['plan'] ?? 'free';
        $status   = $_POST['status'] ?? 'active';
        if (preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $db->run('UPDATE users SET username=?, email=?, plan=?, status=? WHERE id=?', [$username, $email, $plan, $status, $id]);
            flash('admin_status', 'User updated.');
        } else {
            flash('admin_error', 'Invalid username or email.');
        }
    } elseif ($action === 'reset_password' && $id) {
        $new = bin2hex(random_bytes(4));
        $db->run('UPDATE users SET password=? WHERE id=?', [password_hash($new, PASSWORD_BCRYPT), $id]);
        flash('admin_status', "Password reset. Temporary password: {$new}");
    } elseif ($action === 'ban' && $id) {
        $db->run('UPDATE users SET status=? WHERE id=?', ['banned', $id]);
        flash('admin_status', 'User banned.');
    } elseif ($action === 'unban' && $id) {
        $db->run('UPDATE users SET status=? WHERE id=?', ['active', $id]);
        flash('admin_status', 'User reactivated.');
    } elseif ($action === 'delete' && $id) {
        $db->run('DELETE FROM users WHERE id=?', [$id]);
        flash('admin_status', 'User deleted.');
    }
    redirect('admin/users.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}

$q     = trim($_GET['q'] ?? '');
$where = $q !== '' ? 'WHERE username LIKE ? OR email LIKE ?' : '';
$args  = $q !== '' ? ["%$q%", "%$q%"] : [];
$users = $db->fetchAll("SELECT * FROM users $where ORDER BY created_at DESC LIMIT 200", $args);
$plans = $db->fetchAll('SELECT slug, name FROM plans ORDER BY sort_order');

admin_header('Users');
?>
<form class="d-flex gap-2 mb-3" method="get">
    <input class="form-control" type="search" name="q" placeholder="Search username or email" value="<?= e($q) ?>">
    <button class="btn btn-mr-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
</form>

<div class="card glass-card"><div class="card-body p-0"><div class="table-responsive">
<table class="table mr-table align-middle mb-0">
    <thead><tr><th>ID</th><th>User</th><th>Plan</th><th>Status</th><th>Joined</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><strong><?= e($u['username']) ?></strong><small class="text-muted d-block"><?= e($u['email']) ?></small></td>
            <td><span class="badge bg-primary-soft text-primary-mr"><?= e(strtoupper($u['plan'])) ?></span></td>
            <td><span class="badge bg-<?= $u['status']==='active'?'success':($u['status']==='banned'?'danger':'secondary') ?>-soft"><?= e($u['status']) ?></span></td>
            <td class="small text-muted"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
            <td class="small text-muted"><?= $u['last_login'] ? time_ago($u['last_login']) : '&mdash;' ?></td>
            <td class="text-end text-nowrap">
                <button class="btn btn-icon" data-bs-toggle="modal" data-bs-target="#editUser<?= $u['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></button>
                <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-icon" title="Reset password" onclick="return confirm('Reset this user password?')"><i class="fa-solid fa-key"></i></button></form>
                <?php if ($u['status']==='banned'): ?>
                    <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="unban"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-icon text-success" title="Unban"><i class="fa-solid fa-unlock"></i></button></form>
                <?php else: ?>
                    <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="ban"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-icon text-warning" title="Ban" onclick="return confirm('Ban this user?')"><i class="fa-solid fa-ban"></i></button></form>
                <?php endif; ?>
                <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-icon text-danger" title="Delete" onclick="return confirm('Permanently delete this user?')"><i class="fa-solid fa-trash"></i></button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div></div>

<?php foreach ($users as $u): ?>
<div class="modal fade" id="editUser<?= $u['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content glass-card">
        <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $u['id'] ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit <?= e($u['username']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label small">Username</label><input class="form-control" name="username" value="<?= e($u['username']) ?>"></div>
                <div class="mb-3"><label class="form-label small">Email</label><input class="form-control" name="email" value="<?= e($u['email']) ?>"></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">Plan</label><select class="form-select" name="plan"><?php foreach($plans as $p): ?><option value="<?= e($p['slug']) ?>" <?= $u['plan']===$p['slug']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6"><label class="form-label small">Status</label><select class="form-select" name="status"><?php foreach(['active','pending','banned'] as $st): ?><option value="<?= $st ?>" <?= $u['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Save</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; ?>
<?php admin_footer(); ?>
