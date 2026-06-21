<?php
/**
 * Admin layout helpers. Include AFTER bootstrap + Auth::adminRequire().
 * Call admin_header($title) then page content then admin_footer().
 */

function admin_header(string $title): void
{
    $siteName = Settings::get('site_name', 'MemeRadar AI');
    $current  = basename($_SERVER['SCRIPT_NAME']);
    $items = [
        'dashboard.php' => ['fa-gauge-high', 'Dashboard'],
        'users.php'     => ['fa-users', 'Users'],
        'coins.php'     => ['fa-coins', 'Coins'],
        'plans.php'     => ['fa-layer-group', 'Plans'],
        'api.php'       => ['fa-plug', 'APIs'],
        'ads.php'       => ['fa-rectangle-ad', 'Ads'],
        'banners.php'   => ['fa-image', 'Banners'],
        'settings.php'  => ['fa-gear', 'Settings'],
        'logs.php'      => ['fa-list', 'Logs'],
    ];
    ?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Admin &middot; <?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
    <style>:root{--mr-primary:<?= e(Settings::get('theme_primary','#00ff99')) ?>;--mr-secondary:<?= e(Settings::get('theme_secondary','#00c3ff')) ?>;--mr-accent:<?= e(Settings::get('theme_accent','#ffcc00')) ?>;}</style>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= base_url('admin/dashboard.php') ?>"><span class="brand-logo"><i class="fa-solid fa-satellite-dish"></i></span><span>Admin</span></a>
        <nav class="admin-nav">
            <?php foreach ($items as $file => $it): ?>
                <a class="admin-nav-link <?= $current===$file?'active':'' ?>" href="<?= base_url('admin/'.$file) ?>"><i class="fa-solid <?= $it[0] ?>"></i><span><?= e($it[1]) ?></span></a>
            <?php endforeach; ?>
            <a class="admin-nav-link" href="<?= base_url('index.php') ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View Site</span></a>
            <a class="admin-nav-link text-danger" href="<?= base_url('admin/logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
        </nav>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-sm btn-outline-light d-lg-none" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h4 class="mb-0"><?= e($title) ?></h4>
            <span class="ms-auto small text-muted">Signed in as <strong><?= e($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
        </header>
        <div class="admin-content">
    <?php
    if ($msg = flash('admin_status')) {
        echo '<div class="alert alert-success py-2">' . e($msg) . '</div>';
    }
    if ($err = flash('admin_error')) {
        echo '<div class="alert alert-danger py-2">' . e($err) . '</div>';
    }
}

function admin_footer(string $pageScript = ''): void
{
    ?>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.querySelector('.admin-sidebar').classList.toggle('open'));
</script>
<?php if ($pageScript): ?><script><?= $pageScript ?></script><?php endif; ?>
</body>
</html>
    <?php
}
