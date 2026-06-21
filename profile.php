<?php
require __DIR__ . '/includes/bootstrap.php';
Auth::require();

$db     = Database::instance();
$userId = Auth::id();
$user   = Auth::user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Invalid username format.';
        } else {
            $taken = $db->scalar('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$username, $userId]);
            if ($taken) {
                $errors[] = 'Username already taken.';
            } else {
                $db->run('UPDATE users SET username = ? WHERE id = ?', [$username, $userId]);
                flash('status', 'Profile updated.');
                redirect('profile.php');
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $db->run('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_BCRYPT), $userId]);
            flash('status', 'Password changed.');
            redirect('profile.php');
        }
    } elseif ($action === 'change_plan') {
        $slug = $_POST['plan'] ?? 'free';
        $plan = $db->fetch('SELECT * FROM plans WHERE slug = ? AND is_active = 1', [$slug]);
        if ($plan) {
            // NOTE: payment gateway integration goes here. We activate immediately for demo.
            $db->run(
                'UPDATE users SET plan = ?, plan_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?',
                [$slug, (int)$plan['duration_days'], $userId]
            );
            $_SESSION['plan'] = $slug;
            log_event('plan_change', "User switched to {$slug}", $userId);
            flash('status', 'Plan updated to ' . $plan['name'] . '.');
            redirect('profile.php');
        }
    }
    $user = Auth::user();
}

$stats = [
    'watchlist' => (int)$db->scalar('SELECT COUNT(*) FROM watchlist WHERE user_id = ?', [$userId]),
    'alerts'    => (int)$db->scalar('SELECT COUNT(*) FROM alerts WHERE user_id = ?', [$userId]),
    'follows'   => (int)$db->scalar('SELECT COUNT(*) FROM wallet_follows WHERE user_id = ?', [$userId]),
];
$plan = $db->fetch('SELECT * FROM plans WHERE slug = ?', [$user['plan']]);

$pageTitle = 'My Profile';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-4">
    <h3 class="mb-4"><i class="fa-solid fa-user text-primary-mr me-2"></i>My Profile</h3>

    <?php if ($msg = flash('status')): ?><div class="alert alert-success py-2"><?= e($msg) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card glass-card text-center mb-3"><div class="card-body">
                <span class="avatar-circle xl mx-auto mb-3"><?= e(strtoupper(substr($user['username'],0,1))) ?></span>
                <h5 class="mb-0"><?= e($user['username']) ?></h5>
                <p class="text-muted small"><?= e($user['email']) ?></p>
                <span class="badge bg-primary-soft text-primary-mr"><?= e(strtoupper($user['plan'])) ?> PLAN</span>
                <?php if ($user['plan_expires_at'] && $user['plan'] !== 'free'): ?>
                    <p class="small text-muted mt-2">Renews / expires: <?= e(date('M j, Y', strtotime($user['plan_expires_at']))) ?></p>
                <?php endif; ?>
                <div class="row text-center mt-3">
                    <div class="col"><div class="metric-value"><?= $stats['watchlist'] ?></div><small class="text-muted">Watchlist</small></div>
                    <div class="col"><div class="metric-value"><?= $stats['alerts'] ?></div><small class="text-muted">Alerts</small></div>
                    <div class="col"><div class="metric-value"><?= $stats['follows'] ?></div><small class="text-muted">Follows</small></div>
                </div>
                <a href="<?= base_url('pricing.php') ?>" class="btn btn-mr-primary w-100 mt-3"><i class="fa-solid fa-crown me-1"></i>Manage plan</a>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card glass-card mb-3"><div class="card-body">
                <h6 class="mb-3">Account details</h6>
                <form method="post">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small">Username</label><input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>"></div>
                        <div class="col-md-6"><label class="form-label small">Email</label><input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled></div>
                    </div>
                    <button class="btn btn-mr-primary mt-3">Save changes</button>
                </form>
            </div></div>

            <div class="card glass-card"><div class="card-body">
                <h6 class="mb-3">Change password</h6>
                <form method="post">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label small">Current</label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">New</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                        <div class="col-md-4"><label class="form-label small">Confirm</label><input type="password" name="confirm_password" class="form-control" required></div>
                    </div>
                    <button class="btn btn-mr-primary mt-3">Update password</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
