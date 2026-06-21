<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
$resetLink = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    if (!Security::rateLimit('forgot', 5, 600)) {
        $errors[] = 'Too many requests. Please wait a few minutes.';
    }
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!$errors) {
        $db   = Database::instance();
        $user = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $db->run(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
                [$email, hash('sha256', $token)]
            );
            $resetLink = base_url('reset-password.php?email=' . urlencode($email) . '&token=' . $token);

            // Attempt to email via PHP mail() if SMTP not configured; always show link in dev.
            $subject = 'Reset your ' . Settings::get('site_name', 'MemeRadar AI') . ' password';
            $body    = "Use this link to reset your password (valid 1 hour):\n\n" . $resetLink;
            @mail($email, $subject, $body, 'From: ' . Settings::get('smtp_from', 'no-reply@memeradar.ai'));

            log_event('password_reset_request', "Reset requested for {$email}");
        }
        // Always show same message (avoid user enumeration)
        $sent = true;
    }
}

$pageTitle = 'Forgot Password';
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
                        <span class="brand-logo lg"><i class="fa-solid fa-key"></i></span>
                        <h3 class="mt-3 mb-1">Reset password</h3>
                        <p class="text-muted small">Enter your email to receive a reset link</p>
                    </div>

                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <?php if ($sent): ?>
                        <div class="alert alert-success py-2 small">
                            If an account exists for that email, a reset link has been sent.
                        </div>
                        <?php if ($resetLink && config('app.debug')): ?>
                            <div class="alert alert-info py-2 small">Dev link: <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="post" novalidate>
                            <?= Security::csrfField() ?>
                            <div class="mb-4">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required autofocus>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-mr-primary w-100">Send Reset Link</button>
                        </form>
                    <?php endif; ?>
                    <p class="text-center text-muted small mt-4 mb-0">
                        <a href="<?= base_url('login.php') ?>" class="link-primary-mr">Back to login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
