<?php
$siteName = Settings::get('site_name', 'MemeRadar AI');
$year     = date('Y');
?>
<footer class="mr-footer mt-5">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <h5 class="brand-text mb-3"><i class="fa-solid fa-satellite-dish me-2"></i><?= e($siteName) ?></h5>
                <p class="text-muted small">Real-time AI meme coin scanner. Detect new tokens, analyze rug-pull risk and track smart money across Ethereum, BNB, Solana, Base &amp; Polygon.</p>
                <p class="small text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Not financial advice. Crypto is high risk. DYOR.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-head">Platform</h6>
                <ul class="footer-links">
                    <li><a href="<?= base_url('dashboard.php') ?>">Dashboard</a></li>
                    <li><a href="<?= base_url('trending.php') ?>">Trending</a></li>
                    <li><a href="<?= base_url('watchlist.php') ?>">Watchlist</a></li>
                    <li><a href="<?= base_url('alerts.php') ?>">Alerts</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-head">Account</h6>
                <ul class="footer-links">
                    <li><a href="<?= base_url('pricing.php') ?>">Pricing</a></li>
                    <li><a href="<?= base_url('login.php') ?>">Login</a></li>
                    <li><a href="<?= base_url('register.php') ?>">Register</a></li>
                    <li><a href="<?= base_url('profile.php') ?>">Profile</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="footer-head">Stay Updated</h6>
                <p class="text-muted small">Connect your Telegram in alerts to receive new coin and AI buy signals instantly.</p>
                <div class="d-flex gap-2">
                    <a class="social-icon" href="#"><i class="fa-brands fa-telegram"></i></a>
                    <a class="social-icon" href="#"><i class="fa-brands fa-x-twitter"></i></a>
                    <a class="social-icon" href="#"><i class="fa-brands fa-discord"></i></a>
                </div>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <span>&copy; <?= $year ?> <?= e($siteName) ?>. All rights reserved.</span>
            <span>Built for cPanel &middot; PHP &amp; MySQL</span>
        </div>
    </div>
</footer>

<?= render_ads('popup') ?>
<?= render_ads('html') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>window.MR_BASE = <?= json_encode(rtrim((string)config('app.url',''),'/')) ?>;</script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($pageScript)): ?><script><?= $pageScript ?></script><?php endif; ?>
</body>
</html>
