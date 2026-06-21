<?php
/**
 * MemeRadar AI - Installation Wizard
 * Steps: 1) Requirements  2) Database  3) Site config  4) Finish
 */
define('SKIP_INSTALL_CHECK', true);
session_start();

$BASE_PATH  = dirname(__DIR__);
$configFile = $BASE_PATH . '/config/config.php';
$sqlFile    = $BASE_PATH . '/database/memeradar.sql';
$alreadyInstalled = is_file($configFile);

$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$notice = '';

/* ----- Helper: detect base URL ----- */
function detect_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $proto = $https ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir   = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    return $proto . '://' . $host . $dir;
}

/* ----- Requirements check ----- */
$requirements = [
    'PHP >= 8.0'        => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO extension'     => extension_loaded('pdo'),
    'PDO MySQL driver'  => extension_loaded('pdo_mysql'),
    'cURL extension'    => extension_loaded('curl'),
    'JSON extension'    => extension_loaded('json'),
    'mbstring'          => extension_loaded('mbstring'),
    'config/ writable'  => is_writable($BASE_PATH . '/config') || is_writable($BASE_PATH),
    'assets/uploads writable' => is_writable($BASE_PATH . '/assets/uploads') || @mkdir($BASE_PATH . '/assets/uploads', 0755, true) || is_dir($BASE_PATH . '/assets/uploads'),
];
$allOk = !in_array(false, $requirements, true);

/* ----- Step 2: process DB form ----- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'install') {
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => $_POST['db_pass'] ?? '',
    ];
    $appUrl   = rtrim(trim($_POST['app_url'] ?? detect_url()), '/');
    $adminUser= trim($_POST['admin_user'] ?? 'admin');
    $adminPass= $_POST['admin_pass'] ?? '';

    if ($db['name'] === '' || $db['user'] === '') {
        $errors[] = 'Database name and user are required.';
    }
    if (strlen($adminPass) < 6) {
        $errors[] = 'Admin password must be at least 6 characters.';
    }

    if (!$errors) {
        try {
            $pdo = new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Import schema
            $sql = file_get_contents($sqlFile);
            if ($sql === false) throw new RuntimeException('Could not read SQL file.');
            $pdo->exec($sql);

            // Override admin credentials
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE admins SET username=?, password=?, must_change_password=0 WHERE username=?');
            $stmt->execute([$adminUser, $hash, 'admin']);
            // If username changed away from "admin", ensure the seeded one updated; if rename, also handle:
            if ($stmt->rowCount() === 0) {
                $pdo->prepare('UPDATE admins SET password=?, must_change_password=0 WHERE id=1')->execute([$hash]);
            }

            // Write config
            $appKey = bin2hex(random_bytes(24));
            $config = "<?php\nreturn " . var_export([
                'db'  => ['host' => $db['host'], 'name' => $db['name'], 'user' => $db['user'], 'pass' => $db['pass'], 'charset' => 'utf8mb4'],
                'app' => ['url' => $appUrl, 'name' => 'MemeRadar AI', 'env' => 'production', 'timezone' => 'UTC', 'app_key' => $appKey, 'debug' => false],
            ], true) . ";\n";

            if (@file_put_contents($configFile, $config) === false) {
                throw new RuntimeException('Could not write config/config.php. Check folder permissions.');
            }

            $_SESSION['install_done']  = true;
            $_SESSION['install_admin'] = $adminUser;
            header('Location: index.php?step=4');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
    $step = 2;
}

$steps = [1 => 'Requirements', 2 => 'Database', 3 => 'Configure', 4 => 'Finish'];
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install MemeRadar AI</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>:root{--mr-primary:#00ff99;--mr-secondary:#00c3ff;--mr-accent:#ffcc00;}</style>
</head>
<body class="auth-page">
<div class="container" style="max-width:760px;padding:3rem 1rem;">
    <div class="text-center mb-4">
        <span class="brand-logo lg"><i class="fa-solid fa-satellite-dish"></i></span>
        <h2 class="mt-3">MemeRadar AI Installer</h2>
        <p class="text-muted">Set up your AI meme coin scanner in a few steps.</p>
    </div>

    <div class="wizard-steps">
        <?php foreach ($steps as $n => $label): ?>
            <div class="step <?= $n===$step?'active':($n<$step?'done':'') ?>"><?= $n ?>. <?= $label ?></div>
        <?php endforeach; ?>
    </div>

    <div class="card glass-card"><div class="card-body p-4">
        <?php if ($alreadyInstalled && $step !== 4): ?>
            <div class="alert alert-warning">Application appears to be already installed. Delete <code>config/config.php</code> to reinstall, or <a href="../index.php">go to the site</a>.</div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

        <?php if ($step === 1): ?>
            <h4 class="mb-3">Server requirements</h4>
            <ul class="list-group mb-4">
                <?php foreach ($requirements as $name => $ok): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" style="background:var(--mr-card-2);border-color:var(--mr-border);color:var(--mr-text);">
                        <?= htmlspecialchars($name) ?>
                        <span class="badge bg-<?= $ok?'success':'danger' ?>"><?= $ok?'OK':'Missing' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($allOk): ?>
                <a href="index.php?step=2" class="btn btn-mr-primary w-100">Continue <i class="fa-solid fa-arrow-right ms-1"></i></a>
            <?php else: ?>
                <div class="alert alert-danger">Please resolve the missing requirements before continuing.</div>
                <a href="index.php?step=1" class="btn btn-outline-light w-100">Re-check</a>
            <?php endif; ?>

        <?php elseif ($step === 2 || $step === 3): ?>
            <h4 class="mb-3">Database &amp; site configuration</h4>
            <form method="post" action="index.php">
                <input type="hidden" name="do" value="install">
                <h6 class="text-muted mt-2">Database</h6>
                <div class="row g-2 mb-3">
                    <div class="col-md-6"><label class="form-label small">DB Host</label><input class="form-control" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"></div>
                    <div class="col-md-6"><label class="form-label small">DB Name</label><input class="form-control" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label small">DB User</label><input class="form-control" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label small">DB Password</label><input class="form-control" type="password" name="db_pass" value=""></div>
                </div>
                <h6 class="text-muted mt-3">Site</h6>
                <div class="mb-3"><label class="form-label small">Site URL</label><input class="form-control" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? detect_url()) ?>"></div>
                <h6 class="text-muted mt-3">Admin account</h6>
                <div class="row g-2 mb-4">
                    <div class="col-md-6"><label class="form-label small">Admin username</label><input class="form-control" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>"></div>
                    <div class="col-md-6"><label class="form-label small">Admin password</label><input class="form-control" type="password" name="admin_pass" placeholder="min 6 chars" required></div>
                </div>
                <button class="btn btn-mr-primary w-100"><i class="fa-solid fa-bolt me-1"></i>Install Now</button>
                <p class="small text-muted mt-2 mb-0">This will create all tables, seed demo data, and write your config file.</p>
            </form>

        <?php elseif ($step === 4): ?>
            <div class="text-center">
                <span class="brand-logo lg" style="background:linear-gradient(135deg,#00ff99,#00c3ff)"><i class="fa-solid fa-check"></i></span>
                <h4 class="mt-3">Installation complete!</h4>
                <p class="text-muted">MemeRadar AI is ready to go.</p>
            </div>
            <div class="alert alert-info-soft">
                <strong>Admin login:</strong> <code><?= htmlspecialchars($_SESSION['install_admin'] ?? 'admin') ?></code> with the password you chose.<br>
                Default demo user: <code>demo@memeradar.ai</code> / <code>demo123</code>
            </div>
            <div class="alert alert-warning small">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><strong>Important:</strong> delete the <code>/install/</code> directory now for security.
                Set up the cron job (<code>cron/scan.php</code>) to scan coins automatically.
            </div>
            <div class="d-flex gap-2">
                <a href="../index.php" class="btn btn-mr-primary flex-grow-1">Go to site</a>
                <a href="../admin/index.php" class="btn btn-outline-light flex-grow-1">Admin panel</a>
            </div>
        <?php endif; ?>
    </div></div>
</div>
</body>
</html>
