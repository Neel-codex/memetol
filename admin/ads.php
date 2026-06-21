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
        $title     = trim($_POST['title'] ?? '');
        $placement = $_POST['placement'] ?? 'banner';
        $code      = $_POST['code'] ?? '';
        $link      = trim($_POST['link'] ?? '');
        $active    = isset($_POST['is_active']) ? 1 : 0;
        if ($id) {
            $db->run('UPDATE ads SET title=?, placement=?, code=?, link=?, is_active=? WHERE id=?', [$title,$placement,$code,$link,$active,$id]);
            flash('admin_status', 'Ad updated.');
        } else {
            $db->run('INSERT INTO ads (title,placement,code,link,is_active) VALUES (?,?,?,?,?)', [$title,$placement,$code,$link,$active]);
            flash('admin_status', 'Ad created.');
        }
    } elseif ($action === 'delete' && $id) {
        $db->run('DELETE FROM ads WHERE id=?', [$id]);
        flash('admin_status', 'Ad deleted.');
    }
    redirect('admin/ads.php');
}

$ads = $db->fetchAll('SELECT * FROM ads ORDER BY id DESC');
admin_header('Ads');
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-mr-primary" data-bs-toggle="modal" data-bs-target="#newAd"><i class="fa-solid fa-plus me-1"></i>New Ad</button></div>
<div class="card glass-card"><div class="card-body p-0"><div class="table-responsive">
<table class="table mr-table align-middle mb-0">
    <thead><tr><th>Title</th><th>Placement</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($ads as $a): ?>
        <tr><td><?= e($a['title']) ?></td><td><span class="badge bg-secondary-soft"><?= e($a['placement']) ?></span></td>
        <td><span class="badge bg-<?= $a['is_active']?'success':'secondary' ?>-soft"><?= $a['is_active']?'Active':'Off' ?></span></td>
        <td class="text-end"><button class="btn btn-icon" data-bs-toggle="modal" data-bs-target="#editAd<?= $a['id'] ?>"><i class="fa-solid fa-pen"></i></button>
        <form method="post" class="d-inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-icon text-danger" onclick="return confirm('Delete ad?')"><i class="fa-solid fa-trash"></i></button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$ads): ?><tr><td colspan="4" class="text-center text-muted py-4">No ads yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div></div>

<?php
function ad_fields(array $a = []): string {
    $title = e($a['title'] ?? '');
    $code  = e($a['code'] ?? '');
    $link  = e($a['link'] ?? '');
    $checked = (!empty($a['is_active']) || !isset($a['id'])) ? 'checked' : '';
    $placements = ['banner'=>'Banner','sidebar'=>'Sidebar','popup'=>'Popup','adsense'=>'Google AdSense','html'=>'Custom HTML'];
    $opts = '';
    foreach ($placements as $k=>$v) {
        $sel = (($a['placement'] ?? '') === $k) ? 'selected' : '';
        $opts .= "<option value='$k' $sel>$v</option>";
    }
    return "<div class='mb-2'><label class='form-label small'>Title</label><input class='form-control' name='title' value='$title' required></div>
        <div class='mb-2'><label class='form-label small'>Placement</label><select class='form-select' name='placement'>$opts</select></div>
        <div class='mb-2'><label class='form-label small'>Ad code / HTML / AdSense snippet</label><textarea class='form-control' name='code' rows='5'>$code</textarea></div>
        <div class='mb-2'><label class='form-label small'>Link (optional)</label><input class='form-control' name='link' value='$link'></div>
        <div class='form-check'><input class='form-check-input' type='checkbox' name='is_active' $checked id='aa'><label class='form-check-label small' for='aa'>Active</label></div>";
}
?>
<div class="modal fade" id="newAd" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="save">
    <div class="modal-header border-secondary"><h5 class="modal-title">New ad</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?= ad_fields() ?></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Create</button></div>
    </form>
</div></div></div>
<?php foreach ($ads as $a): ?>
<div class="modal fade" id="editAd<?= $a['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $a['id'] ?>">
    <div class="modal-header border-secondary"><h5 class="modal-title">Edit ad</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?= ad_fields($a) ?></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Save</button></div>
    </form>
</div></div></div>
<?php endforeach; ?>
<?php admin_footer(); ?>
