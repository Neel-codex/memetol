<?php
$loggedIn = Auth::check();
$u        = $loggedIn ? Auth::user() : null;
$current  = basename($_SERVER['SCRIPT_NAME']);
$siteName = Settings::get('site_name', 'MemeRadar AI');
$logo     = Settings::get('logo', '');

function nav_active(string $file, string $current): string {
    return $file === $current ? ' active' : '';
}
?>
<nav class="navbar navbar-expand-lg mr-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('index.php') ?>">
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" height="32">
            <?php else: ?>
                <span class="brand-logo"><i class="fa-solid fa-satellite-dish"></i></span>
            <?php endif; ?>
            <span class="brand-text"><?= e($siteName) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?= nav_active('dashboard.php',$current) ?>" href="<?= base_url('dashboard.php') ?>"><i class="fa-solid fa-gauge-high me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('trending.php',$current) ?>" href="<?= base_url('trending.php') ?>"><i class="fa-solid fa-fire me-1"></i>Trending</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('watchlist.php',$current) ?>" href="<?= base_url('watchlist.php') ?>"><i class="fa-solid fa-star me-1"></i>Watchlist</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('alerts.php',$current) ?>" href="<?= base_url('alerts.php') ?>"><i class="fa-solid fa-bell me-1"></i>Alerts</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('pricing.php',$current) ?>" href="<?= base_url('pricing.php') ?>"><i class="fa-solid fa-crown me-1"></i>Pricing</a></li>
            </ul>
            <form class="d-flex me-lg-3 my-2 my-lg-0" role="search" action="<?= base_url('trending.php') ?>" method="get">
                <div class="input-group input-group-sm mr-search">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="form-control" type="search" name="q" placeholder="Search coin, symbol, contract..." value="<?= e($_GET['q'] ?? '') ?>">
                </div>
            </form>
            <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center">
                <?php if ($loggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                            <span class="avatar-circle"><?= e(strtoupper(substr($u['username'] ?? 'U',0,1))) ?></span>
                            <span class="d-none d-lg-inline"><?= e($u['username'] ?? 'User') ?></span>
                            <span class="badge bg-plan ms-1"><?= e(strtoupper($u['plan'] ?? 'FREE')) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end mr-dropdown">
                            <li><a class="dropdown-item" href="<?= base_url('profile.php') ?>"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="<?= base_url('watchlist.php') ?>"><i class="fa-solid fa-star me-2"></i>Watchlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('login.php') ?>">Login</a></li>
                    <li class="nav-item"><a class="btn btn-mr-primary btn-sm ms-lg-2" href="<?= base_url('register.php') ?>">Get Started</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
