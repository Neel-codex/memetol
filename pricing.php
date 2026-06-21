<?php
require __DIR__ . '/includes/bootstrap.php';

$db    = Database::instance();
$plans = $db->fetchAll('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC');
$user  = Auth::check() ? Auth::user() : null;

$pageTitle = 'Pricing';
$pageDesc  = 'Choose a MemeRadar AI plan: Free, Pro or Premium. Real-time scanning, AI risk scores, rug-pull detection and smart-money alerts.';
require __DIR__ . '/includes/partials/head.php';
require __DIR__ . '/includes/partials/navbar.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="section-title">Plans &amp; Pricing</h1>
        <p class="text-muted">Upgrade for real-time data, AI scores and instant alerts. Cancel anytime.</p>
    </div>

    <?php if ($msg = flash('status')): ?>
        <div class="alert alert-success text-center"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $p):
            $feats   = array_filter(array_map('trim', explode('|', (string)$p['features'])));
            $current = $user && $user['plan'] === $p['slug']; ?>
            <div class="col-md-6 col-lg-4">
                <div class="card glass-card pricing-card h-100 <?= $p['slug']==='pro' ? 'pricing-featured' : '' ?>">
                    <div class="card-body text-center p-4">
                        <?php if ($p['slug']==='pro'): ?><span class="badge bg-primary-mr mb-2">Most Popular</span><?php endif; ?>
                        <h4><?= e($p['name']) ?></h4>
                        <div class="price-tag"><?= $p['price']>0 ? '$'.number_format((float)$p['price'],0) : 'Free' ?><?php if($p['price']>0): ?><span>/mo</span><?php endif; ?></div>
                        <p class="text-muted small"><?= (int)$p['duration_days'] ?> days access</p>
                        <ul class="plan-feats text-start">
                            <?php foreach ($feats as $ft): ?><li><i class="fa-solid fa-check"></i> <?= e($ft) ?></li><?php endforeach; ?>
                        </ul>
                        <?php if ($current): ?>
                            <button class="btn btn-outline-success w-100 mt-2" disabled><i class="fa-solid fa-check me-1"></i>Current Plan</button>
                        <?php elseif ($user): ?>
                            <form method="post" action="<?= base_url('profile.php') ?>">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="change_plan">
                                <input type="hidden" name="plan" value="<?= e($p['slug']) ?>">
                                <button class="btn <?= $p['slug']==='pro'?'btn-mr-primary':'btn-outline-light' ?> w-100 mt-2">
                                    <?= $p['price']>0 ? 'Upgrade to '.e($p['name']) : 'Switch to Free' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= base_url('register.php') ?>" class="btn <?= $p['slug']==='pro'?'btn-mr-primary':'btn-outline-light' ?> w-100 mt-2">Get Started</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-5 pt-4">
        <div class="col-lg-8 mx-auto">
            <h3 class="text-center mb-4">Frequently asked questions</h3>
            <div class="accordion accordion-flush mr-accordion" id="faq">
                <?php
                $faqs = [
                    ['Is the data real-time?','Pro and Premium plans receive real-time scanning. The Free plan shows slightly delayed data.'],
                    ['How accurate is the AI risk score?','The score combines liquidity, holder distribution, volume growth, token age, social activity and smart-money signals into a 0-100 rating. It is a guide, not financial advice.'],
                    ['Can I cancel anytime?','Yes. Plans are billed monthly and you can downgrade to Free whenever you like.'],
                    ['Which chains are supported?','Ethereum, BNB Chain, Solana, Base and Polygon.'],
                ];
                foreach ($faqs as $i => $q): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= e($q[0]) ?></button>
                        </h2>
                        <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faq">
                            <div class="accordion-body text-muted"><?= e($q[1]) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
