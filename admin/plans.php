<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name     = trim($_POST['name'] ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $duration = max(1, (int)($_POST['duration_days'] ?? 30));
        $features = trim($_POST['features'] ?? '');
        $active   = isset($_POST['is_active']) ? 1 : 0;
        if ($id) {
            $db->run('UPDATE plans SET name=?, price=?, duration_days=?, features=?, is_active=? WHERE id=?',
                [$name, $price, $duration, $features, $active, $id]);
            flash('admin_status', 'Plan updated.');
        } else {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($_POST['slug'] ?? $name));
            $db->run('INSERT INTO plans (slug,name,price,duration_days,features,is_active,sort_order) VALUES (?,?,?,?,?,?,?)',
                [$slug, $name, $price, $duration, $features, $active, (int)$db->scalar('SELECT COALESCE(MAX(sort_order),0)+1 FROM plans')]);
            flash('admin_status', 'Plan created.');
        }
    } elseif ($action === 'delete' && $id) {
        $db->run('DELETE FROM plans WHERE id=?', [$id]);
        flash('admin_status', 'Plan deleted.');
    }
    redirect('admin/plans.php');
}

$plans = $db->fetchAll('SELECT * FROM plans ORDER BY sort_order ASC');
admin_header('Plans');
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-mr-primary" data-bs-toggle="modal" data-bs-target="#newPlan"><i class="fa-solid fa-plus me-1"></i>New Plan</button></div>
<div class="row g-3">
    <?php foreach ($plans as $p):
        $feats = array_filter(array_map('trim', explode('|', (string)$p['features']))); ?>
        <div class="col-md-4"><div class="card glass-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h5><?= e($p['name']) ?></h5><span class="badge bg-<?= $p['is_active']?'success':'secondary' ?>-soft"><?= $p['is_active']?'Active':'Disabled' ?></span></div>
            <div class="price-tag mb-2"><?= $p['price']>0?'$'.number_format((float)$p['price'],0):'Free' ?><?php if($p['price']>0): ?><span>/<?= (int)$p['duration_days'] ?>d</span><?php endif; ?></div>
            <ul class="plan-feats small"><?php foreach($feats as $f): ?><li><i class="fa-solid fa-check"></i> <?= e($f) ?></li><?php endforeach; ?></ul>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-outline-light btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#editPlan<?= $p['id'] ?>"><i class="fa-solid fa-pen me-1"></i>Edit</button>
                <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-icon text-danger" onclick="return confirm('Delete plan?')"><i class="fa-solid fa-trash"></i></button></form>
            </div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<?php
function plan_form_fields(array $p = []): string {
    $name = e($p['name'] ?? '');
    $price = e((string)($p['price'] ?? '0'));
    $dur = e((string)($p['duration_days'] ?? '30'));
    $feat = e($p['features'] ?? '');
    $checked = (!isset($p['is_active']) || $p['is_active']) ? 'checked' : '';
    $slugField = isset($p['id']) ? '' : '<div class="mb-2"><label class="form-label small">Slug</label><input class="form-control" name="slug" required></div>';
    return "$slugField
        <div class='mb-2'><label class='form-label small'>Name</label><input class='form-control' name='name' value='$name' required></div>
        <div class='row g-2 mb-2'><div class='col-6'><label class='form-label small'>Price (USD)</label><input class='form-control' type='number' step='any' name='price' value='$price'></div>
        <div class='col-6'><label class='form-label small'>Duration (days)</label><input class='form-control' type='number' name='duration_days' value='$dur'></div></div>
        <div class='mb-2'><label class='form-label small'>Features (pipe | separated)</label><textarea class='form-control' name='features' rows='4'>$feat</textarea></div>
        <div class='form-check'><input class='form-check-input' type='checkbox' name='is_active' $checked id='pa'><label class='form-check-label small' for='pa'>Active</label></div>";
}
?>
<div class="modal fade" id="newPlan" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="save">
    <div class="modal-header border-secondary"><h5 class="modal-title">New plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?= plan_form_fields() ?></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Create</button></div>
    </form>
</div></div></div>

<?php foreach ($plans as $p): ?>
<div class="modal fade" id="editPlan<?= $p['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $p['id'] ?>">
    <div class="modal-header border-secondary"><h5 class="modal-title">Edit <?= e($p['name']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?= plan_form_fields($p) ?></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Save</button></div>
    </form>
</div></div></div>
<?php endforeach; ?>
<?php admin_footer(); ?>
