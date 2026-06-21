<?php
require __DIR__ . '/includes/bootstrap.php';

$email  = trim($_GET['email'] ?? $_POST['email'] ?? '');
$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done   = false;

$db  = Database::instance();
$row = $db->fetch(
    'SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
    [$email, hash('sha256', $token)]
);
$valid = (bool) $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    Security::requireCsrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->run('UPDATE users SET password = ? WHERE email = ?', [$hash, $email]);
        $db->run('DELETE FROM password_resets WHERE email = ?', [$email]);
        log_event('password_reset', "Password reset completed for {$email}");
        $done = true;
        flash('status', 'Your password has been reset. Please log in.');
    }
}

$pageTitle = 'Set New Password';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container auth-wrap">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card glass-card auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="brand-logo lg"><i class="fa-solid fa-lock"></i></span>
                        <h3 class="mt-3 mb-1">New password</h3>
                    </div>

                    <?php if ($done): ?>
                        <div class="alert alert-success py-2 small">Password updated. <a href="<?= base_url('login.php') ?>">Log in now</a>.</div>
                    <?php elseif (!$valid): ?>
                        <div class="alert alert-danger py-2 small">This reset link is invalid or has expired.</div>
                        <p class="text-center"><a href="<?= base_url('forgot-password.php') ?>" class="link-primary-mr">Request a new link</a></p>
                    <?php else: ?>
                        <?php foreach ($errors as $err): ?>
                            <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
                        <?php endforeach; ?>
                        <form method="post" novalidate>
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="email" value="<?= e($email) ?>">
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-mr-primary w-100">Update Password</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
