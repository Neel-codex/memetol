<?php
require __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect('dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();

    if (!Security::rateLimit('login', 8, 600)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);
    $_SESSION['_old'] = ['email' => $email];

    if (!$errors) {
        if (Auth::attempt($email, $password, $remember)) {
            unset($_SESSION['_old']);
            log_event('login', "User logged in: {$email}", Auth::id());
            redirect('dashboard.php');
        }
        $errors[] = 'Invalid credentials or your account is banned.';
    }
}

$pageTitle = 'Login';
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
                        <span class="brand-logo lg"><i class="fa-solid fa-satellite-dish"></i></span>
                        <h3 class="mt-3 mb-1">Welcome back</h3>
                        <p class="text-muted small">Log in to your MemeRadar AI account</p>
                    </div>

                    <?php if ($msg = flash('status')): ?>
                        <div class="alert alert-success py-2 small"><?= e($msg) ?></div>
                    <?php endif; ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <form method="post" novalidate>
                        <?= Security::csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="remember" id="remember" value="1">
                                <label class="form-check-label small" for="remember">Remember me</label>
                            </div>
                            <a href="<?= base_url('forgot-password.php') ?>" class="small link-primary-mr">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-mr-primary w-100">Log In</button>
                    </form>
                    <p class="text-center text-muted small mt-4 mb-0">
                        New here? <a href="<?= base_url('register.php') ?>" class="link-primary-mr">Create an account</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
