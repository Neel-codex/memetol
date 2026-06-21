<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db = Database::instance();

$cards = [
    'users'        => (int)$db->scalar('SELECT COUNT(*) FROM users'),
    'premium'      => (int)$db->scalar("SELECT COUNT(*) FROM users WHERE plan <> 'free'"),
    'new_coins'    => (int)$db->scalar('SELECT COUNT(*) FROM coins WHERE created_at >= CURDATE()'),
    'total_coins'  => (int)$db->scalar('SELECT COUNT(*) FROM coins'),
];

// Revenue estimate from active paid plans
$revenue = (float)$db->scalar(
    "SELECT COALESCE(SUM(p.price),0) FROM users u JOIN plans p ON p.slug = u.plan WHERE u.plan <> 'free'"
);

$apiOk      = (int)$db->scalar('SELECT COUNT(*) FROM api_settings WHERE enabled = 1');
$telegramOn = Telegram::enabled();

$recentUsers = $db->fetchAll('SELECT id, username, email, plan, status, created_at FROM users ORDER BY created_at DESC LIMIT 6');
$recentLogs  = $db->fetchAll('SELECT * FROM logs ORDER BY created_at DESC LIMIT 8');

// signups last 7 days
$signups = [];
for ($i = 6; $i >= 0; $i--) {
    $day  = date('Y-m-d', strtotime("-$i day"));
    $cnt  = (int)$db->scalar('SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?', [$day]);
    $signups[date('M j', strtotime($day))] = $cnt;
}

admin_header('Dashboard');
?>
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Users', number_format($cards['users']), 'fa-users', 'primary'],
        ['Premium Users', number_format($cards['premium']), 'fa-crown', 'warning'],
        ['Revenue (MRR)', money($revenue), 'fa-sack-dollar', 'success'],
        ['New Coins Today', number_format($cards['new_coins']), 'fa-coins', 'info'],
    ];
    foreach ($statCards as $s): ?>
        <div class="col-6 col-lg-3"><div class="card glass-card stat-card"><div class="card-body">
            <span class="stat-icon bg-<?= $s[3] ?>-soft"><i class="fa-solid <?= $s[2] ?> text-<?= $s[3]==='primary'?'primary-mr':$s[3] ?>"></i></span>
            <div class="stat-value"><?= $s[1] ?></div><div class="stat-caption"><?= $s[0] ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card glass-card"><div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon bg-<?= $apiOk?'success':'danger' ?>-soft"><i class="fa-solid fa-plug text-<?= $apiOk?'success':'danger' ?>"></i></span>
        <div><div class="stat-value"><?= $apiOk ?>/3</div><div class="stat-caption">APIs Enabled</div></div>
        <a href="<?= base_url('admin/api.php') ?>" class="btn btn-sm btn-outline-light ms-auto">Manage</a>
    </div></div></div>
    <div class="col-md-6"><div class="card glass-card"><div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon bg-<?= $telegramOn?'success':'secondary' ?>-soft"><i class="fa-brands fa-telegram text-<?= $telegramOn?'success':'muted' ?>"></i></span>
        <div><div class="stat-value"><?= $telegramOn?'Connected':'Off' ?></div><div class="stat-caption">Telegram Bot</div></div>
        <a href="<?= base_url('admin/settings.php') ?>" class="btn btn-sm btn-outline-light ms-auto">Configure</a>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card glass-card mb-3"><div class="card-body">
            <h6 class="mb-3">New signups (7 days)</h6>
            <canvas id="signupChart" height="110"></canvas>
        </div></div>
        <div class="card glass-card"><div class="card-body">
            <h6 class="mb-3">Recent users</h6>
            <div class="table-responsive"><table class="table mr-table align-middle mb-0">
                <thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($recentUsers as $u): ?>
                    <tr><td><strong><?= e($u['username']) ?></strong><small class="text-muted d-block"><?= e($u['email']) ?></small></td>
                    <td><span class="badge bg-primary-soft text-primary-mr"><?= e(strtoupper($u['plan'])) ?></span></td>
                    <td><span class="badge bg-<?= $u['status']==='active'?'success':'secondary' ?>-soft"><?= e($u['status']) ?></span></td>
                    <td class="small text-muted"><?= e(date('M j', strtotime($u['created_at']))) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card glass-card"><div class="card-body">
            <h6 class="mb-3">Recent activity</h6>
            <?php foreach ($recentLogs as $l): ?>
                <div class="log-row"><span class="badge bg-secondary-soft me-2"><?= e($l['type']) ?></span><span class="small"><?= e(mb_strimwidth($l['message'],0,60,'...')) ?></span><small class="text-muted d-block"><?= time_ago($l['created_at']) ?></small></div>
            <?php endforeach; ?>
            <?php if (!$recentLogs): ?><p class="text-muted small mb-0">No activity yet.</p><?php endif; ?>
        </div></div>
    </div>
</div>
<?php
admin_footer('
MR_admin_chart("signupChart", ' . json_encode(array_keys($signups)) . ', ' . json_encode(array_values($signups)) . ');
function MR_admin_chart(id,labels,data){
  const ctx=document.getElementById(id); if(!ctx)return;
  new Chart(ctx,{type:"line",data:{labels,datasets:[{data,borderColor:"#00ff99",backgroundColor:"rgba(0,255,153,.15)",fill:true,tension:.4,pointRadius:3}]},options:{plugins:{legend:{display:false}},scales:{x:{grid:{color:"rgba(255,255,255,.05)"}},y:{grid:{color:"rgba(255,255,255,.05)"},ticks:{precision:0}}}}});
}
');
?>
