<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db        = Database::instance();
$uploadDir = BASE_PATH . '/assets/uploads/';
$forcePw   = isset($_GET['force_pw']) || !empty($_SESSION['admin_must_reset']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'change_admin_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $admin   = $db->fetch('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
        if (!$admin || !password_verify($current, $admin['password'])) {
            flash('admin_error', 'Current password incorrect.');
        } elseif (strlen($new) < 8) {
            flash('admin_error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('admin_error', 'Passwords do not match.');
        } else {
            $db->run('UPDATE admins SET password=?, must_change_password=0 WHERE id=?', [password_hash($new, PASSWORD_BCRYPT), $_SESSION['admin_id']]);
            $_SESSION['admin_must_reset'] = 0;
            flash('admin_status', 'Admin password changed.');
            redirect('admin/settings.php');
        }
        redirect('admin/settings.php' . ($forcePw ? '?force_pw=1' : ''));
    }

    if ($action === 'save_settings') {
        $keys = [
            'site_name','seo_title','seo_description','seo_keywords',
            'theme_primary','theme_secondary','theme_accent',
            'telegram_bot_token','telegram_chat_id','telegram_webhook',
            'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from',
        ];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) Settings::set($k, trim($_POST[$k]));
        }
        Settings::set('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
        Settings::set('telegram_enabled', isset($_POST['telegram_enabled']) ? '1' : '0');

        // logo / favicon upload
        foreach (['logo','favicon'] as $imgKey) {
            if (!empty($_FILES[$imgKey]['tmp_name']) && is_uploaded_file($_FILES[$imgKey]['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES[$imgKey]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','ico'], true)) {
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                    $fname = $imgKey . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES[$imgKey]['tmp_name'], $uploadDir . $fname)) {
                        Settings::set($imgKey, base_url('assets/uploads/' . $fname));
                    }
                }
            }
        }
        flash('admin_status', 'Settings saved.');
        redirect('admin/settings.php');
    }
}

$s = Settings::all();
admin_header('Settings');

if ($forcePw): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i><strong>Security:</strong> You must change the default admin password before continuing.</div>
    <div class="card glass-card" style="max-width:520px"><div class="card-body">
        <h6 class="mb-3">Change admin password</h6>
        <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="change_admin_password">
            <div class="mb-2"><label class="form-label small">Current password</label><input type="password" class="form-control" name="current_password" required></div>
            <div class="mb-2"><label class="form-label small">New password</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
            <div class="mb-3"><label class="form-label small">Confirm password</label><input type="password" class="form-control" name="confirm_password" required></div>
            <button class="btn btn-mr-primary w-100">Update password</button>
        </form>
    </div></div>
<?php admin_footer(); return; endif; ?>

<ul class="nav nav-pills mr-pills mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-general">General</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-seo">SEO</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-theme">Theme</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-telegram">Telegram</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-smtp">SMTP</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-security">Security</button></li>
</ul>

<form method="post" enctype="multipart/form-data">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save_settings">
    <div class="tab-content card glass-card"><div class="card-body p-4">
        <div class="tab-pane fade show active" id="tab-general">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label small">Site name</label><input class="form-control" name="site_name" value="<?= e($s['site_name'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label small">Logo</label><input class="form-control" type="file" name="logo" accept="image/*"><?php if(!empty($s['logo'])): ?><small class="text-muted"><a href="<?= e($s['logo']) ?>" target="_blank">current</a></small><?php endif; ?></div>
                <div class="col-md-3"><label class="form-label small">Favicon</label><input class="form-control" type="file" name="favicon" accept="image/*"><?php if(!empty($s['favicon'])): ?><small class="text-muted"><a href="<?= e($s['favicon']) ?>" target="_blank">current</a></small><?php endif; ?></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="maintenance_mode" id="mm" <?= ($s['maintenance_mode']??'0')==='1'?'checked':'' ?>><label class="form-check-label" for="mm">Maintenance mode (site offline for non-admins)</label></div></div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-seo">
            <div class="mb-3"><label class="form-label small">SEO title</label><input class="form-control" name="seo_title" value="<?= e($s['seo_title'] ?? '') ?>"></div>
            <div class="mb-3"><label class="form-label small">SEO description</label><textarea class="form-control" name="seo_description" rows="3"><?= e($s['seo_description'] ?? '') ?></textarea></div>
            <div class="mb-3"><label class="form-label small">Keywords</label><input class="form-control" name="seo_keywords" value="<?= e($s['seo_keywords'] ?? '') ?>"></div>
        </div>
        <div class="tab-pane fade" id="tab-theme">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label small">Primary</label><input class="form-control form-control-color" type="color" name="theme_primary" value="<?= e($s['theme_primary'] ?? '#00ff99') ?>"></div>
                <div class="col-md-4"><label class="form-label small">Secondary</label><input class="form-control form-control-color" type="color" name="theme_secondary" value="<?= e($s['theme_secondary'] ?? '#00c3ff') ?>"></div>
                <div class="col-md-4"><label class="form-label small">Accent</label><input class="form-control form-control-color" type="color" name="theme_accent" value="<?= e($s['theme_accent'] ?? '#ffcc00') ?>"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-telegram">
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="telegram_enabled" id="te" <?= ($s['telegram_enabled']??'0')==='1'?'checked':'' ?>><label class="form-check-label" for="te">Enable Telegram alerts</label></div>
            <div class="mb-3"><label class="form-label small">Bot token</label><input class="form-control" name="telegram_bot_token" value="<?= e($s['telegram_bot_token'] ?? '') ?>"></div>
            <div class="mb-3"><label class="form-label small">Chat ID</label><input class="form-control" name="telegram_chat_id" value="<?= e($s['telegram_chat_id'] ?? '') ?>"></div>
            <div class="mb-3"><label class="form-label small">Webhook URL (optional)</label><input class="form-control" name="telegram_webhook" value="<?= e($s['telegram_webhook'] ?? '') ?>"></div>
        </div>
        <div class="tab-pane fade" id="tab-smtp">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label small">SMTP host</label><input class="form-control" name="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label small">Port</label><input class="form-control" name="smtp_port" value="<?= e($s['smtp_port'] ?? '587') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Username</label><input class="form-control" name="smtp_user" value="<?= e($s['smtp_user'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Password</label><input class="form-control" type="password" name="smtp_pass" value="<?= e($s['smtp_pass'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label small">From address</label><input class="form-control" name="smtp_from" value="<?= e($s['smtp_from'] ?? '') ?>"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-security">
            <p class="text-muted small">Change the admin account password.</p>
        </div>
    </div></div>
    <button class="btn btn-mr-primary mt-3"><i class="fa-solid fa-floppy-disk me-1"></i>Save settings</button>
</form>

<div class="card glass-card mt-3" style="max-width:520px"><div class="card-body">
    <h6 class="mb-3">Change admin password</h6>
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="change_admin_password">
        <div class="mb-2"><label class="form-label small">Current password</label><input type="password" class="form-control" name="current_password" required></div>
        <div class="row g-2 mb-3"><div class="col-6"><label class="form-label small">New</label><input type="password" class="form-control" name="new_password" minlength="8" required></div><div class="col-6"><label class="form-label small">Confirm</label><input type="password" class="form-control" name="confirm_password" required></div></div>
        <button class="btn btn-outline-light">Update password</button>
    </form>
</div></div>
<?php admin_footer(); ?>
