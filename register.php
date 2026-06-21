<?php
require __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect('dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();

    if (!Security::rateLimit('register', 5, 600)) {
        $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $_SESSION['_old'] = ['username' => $username, 'email' => $email];

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $db     = Database::instance();
        $exists = $db->scalar('SELECT COUNT(*) FROM users WHERE email = ? OR username = ?', [$email, $username]);
        if ($exists) {
            $errors[] = 'An account with that email or username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $id   = $db->insert(
                'INSERT INTO users (username, email, password, plan, status) VALUES (?, ?, ?, ?, ?)',
                [$username, $email, $hash, 'free', 'active']
            );
            log_event('register', "New user registered: {$username}", $id);
            unset($_SESSION['_old']);
            $user = $db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
            Auth::loginUser($user);
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Create Account';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container auth-wrap">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card glass-card auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="brand-logo lg"><i class="fa-solid fa-satellite-dish"></i></span>
                        <h3 class="mt-3 mb-1">Create your account</h3>
                        <p class="text-muted small">Start scanning meme coins with AI insights</p>
                    </div>

                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <form method="post" novalidate>
                        <?= Security::csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="username" class="form-control" value="<?= old('username') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-mr-primary w-100">Create Account</button>
                    </form>
                    <p class="text-center text-muted small mt-4 mb-0">
                        Already have an account? <a href="<?= base_url('login.php') ?>" class="link-primary-mr">Log in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
