<?php
require __DIR__ . '/../includes/bootstrap.php';

if (Auth::adminCheck()) {
    redirect('admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    if (!Security::rateLimit('admin_login', 6, 600)) {
        $error = 'Too many attempts. Please wait.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (Auth::adminAttempt($username, $password)) {
            log_event('admin_login', "Admin logged in: {$username}");
            redirect('admin/dashboard.php');
        }
        $error = 'Invalid admin credentials.';
    }
}

$siteName = Settings::get('site_name', 'MemeRadar AI');
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | <?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
    <style>:root{--mr-primary:#00ff99;--mr-secondary:#00c3ff;--mr-accent:#ffcc00;}</style>
</head>
<body class="auth-page">
<div class="container auth-wrap">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card glass-card auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="brand-logo lg"><i class="fa-solid fa-user-shield"></i></span>
                        <h3 class="mt-3 mb-1">Admin Panel</h3>
                        <p class="text-muted small"><?= e($siteName) ?></p>
                    </div>
                    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
                    <form method="post">
                        <?= Security::csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <div class="input-group"><span class="input-group-text"><i class="fa-solid fa-user"></i></span><input type="text" name="username" class="form-control" required autofocus></div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group"><span class="input-group-text"><i class="fa-solid fa-lock"></i></span><input type="password" name="password" class="form-control" required></div>
                        </div>
                        <button class="btn btn-mr-primary w-100">Log In</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
