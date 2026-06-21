<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db = Database::instance();

// AJAX test connection
if (($_GET['test'] ?? '') !== '') {
    header('Content-Type: application/json');
    $name = $_GET['test'];
    $row  = $db->fetch('SELECT * FROM api_settings WHERE name = ?', [$name]);
    if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Unknown API']); exit; }
    $base = rtrim((string)$row['base_url'], '/');
    $url  = match ($name) {
        'dexscreener'   => $base . '/search?q=pepe',
        'geckoterminal' => $base . '/networks',
        'coingecko'     => $base . '/ping',
        default         => $base,
    };
    $res = Http::getJson($url);
    echo json_encode(['ok' => $res !== null, 'msg' => $res !== null ? 'Connection OK' : 'No response / blocked']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    foreach ($db->fetchAll('SELECT id, name FROM api_settings') as $api) {
        $n = $api['name'];
        $db->run('UPDATE api_settings SET base_url=?, api_key=?, enabled=?, updated_at=NOW() WHERE id=?', [
            trim($_POST["base_$n"] ?? ''),
            trim($_POST["key_$n"] ?? ''),
            isset($_POST["enabled_$n"]) ? 1 : 0,
            $api['id'],
        ]);
    }
    flash('admin_status', 'API settings saved.');
    redirect('admin/api.php');
}

$apis = $db->fetchAll('SELECT * FROM api_settings ORDER BY name');
admin_header('API Settings');
?>
<form method="post">
    <?= Security::csrfField() ?>
    <div class="row g-3">
        <?php foreach ($apis as $a): ?>
            <div class="col-md-6"><div class="card glass-card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 text-capitalize"><?= e($a['name']) ?></h5>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled_<?= e($a['name']) ?>" <?= $a['enabled']?'checked':'' ?>></div>
                </div>
                <div class="mb-2"><label class="form-label small">Base URL</label><input class="form-control" name="base_<?= e($a['name']) ?>" value="<?= e($a['base_url']) ?>"></div>
                <div class="mb-3"><label class="form-label small">API Key (optional)</label><input class="form-control" name="key_<?= e($a['name']) ?>" value="<?= e($a['api_key']) ?>" placeholder="Not required for free tier"></div>
                <button type="button" class="btn btn-outline-light btn-sm test-api" data-api="<?= e($a['name']) ?>"><i class="fa-solid fa-plug me-1"></i>Test connection</button>
                <span class="test-result small ms-2" data-for="<?= e($a['name']) ?>"></span>
            </div></div></div>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-mr-primary mt-3"><i class="fa-solid fa-floppy-disk me-1"></i>Save settings</button>
</form>
<?php
admin_footer('
document.querySelectorAll(".test-api").forEach(b=>b.addEventListener("click",function(){
  const api=this.dataset.api; const out=document.querySelector(`.test-result[data-for="${api}"]`);
  out.textContent="Testing..."; out.className="test-result small ms-2 text-muted";
  fetch("api.php?test="+encodeURIComponent(api)).then(r=>r.json()).then(j=>{
    out.textContent=j.msg; out.className="test-result small ms-2 "+(j.ok?"text-success":"text-danger");
  }).catch(()=>{out.textContent="Error"; out.className="test-result small ms-2 text-danger";});
}));
');
?>
