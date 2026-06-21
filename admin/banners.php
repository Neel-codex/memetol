<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminRequire();
require __DIR__ . '/_layout.php';

$db        = Database::instance();
$uploadDir = BASE_PATH . '/assets/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $title    = trim($_POST['title'] ?? '');
        $position = $_POST['position'] ?? 'hero';
        $link     = trim($_POST['link'] ?? '');
        $active   = isset($_POST['is_active']) ? 1 : 0;
        $starts   = $_POST['starts_at'] ?: null;
        $ends     = $_POST['ends_at'] ?: null;

        // Handle upload
        $image = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true) && $_FILES['image']['size'] < 4_000_000) {
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                $fname = 'banner_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname)) {
                    $image = base_url('assets/uploads/' . $fname);
                }
            } else {
                flash('admin_error', 'Invalid image (allowed: jpg/png/gif/webp/svg, < 4MB).');
            }
        }

        if ($id) {
            $db->run('UPDATE banners SET title=?, position=?, image=?, link=?, is_active=?, starts_at=?, ends_at=? WHERE id=?',
                [$title,$position,$image,$link,$active,$starts,$ends,$id]);
            flash('admin_status', 'Banner updated.');
        } else {
            $db->run('INSERT INTO banners (title,position,image,link,is_active,starts_at,ends_at) VALUES (?,?,?,?,?,?,?)',
                [$title,$position,$image,$link,$active,$starts,$ends]);
            flash('admin_status', 'Banner created.');
        }
    } elseif ($action === 'delete' && $id) {
        $db->run('DELETE FROM banners WHERE id=?', [$id]);
        flash('admin_status', 'Banner deleted.');
    }
    redirect('admin/banners.php');
}

$banners = $db->fetchAll('SELECT * FROM banners ORDER BY id DESC');
admin_header('Banners');
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-mr-primary" data-bs-toggle="modal" data-bs-target="#newBanner"><i class="fa-solid fa-plus me-1"></i>New Banner</button></div>
<div class="row g-3">
    <?php foreach ($banners as $b): ?>
        <div class="col-md-4"><div class="card glass-card h-100">
            <?php if ($b['image']): ?><img src="<?= e($b['image']) ?>" class="card-img-top banner-thumb" alt=""><?php else: ?><div class="banner-thumb d-flex align-items-center justify-content-center text-muted"><i class="fa-regular fa-image fa-2x"></i></div><?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between"><h6><?= e($b['title']) ?></h6><span class="badge bg-<?= $b['is_active']?'success':'secondary' ?>-soft"><?= $b['is_active']?'Active':'Off' ?></span></div>
                <span class="badge bg-secondary-soft"><?= e($b['position']) ?></span>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-outline-light btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#editBanner<?= $b['id'] ?>"><i class="fa-solid fa-pen me-1"></i>Edit</button>
                    <form method="post"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn btn-icon text-danger" onclick="return confirm('Delete banner?')"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        </div></div>
    <?php endforeach; ?>
    <?php if (!$banners): ?><div class="col-12"><p class="text-muted text-center py-4">No banners yet.</p></div><?php endif; ?>
</div>

<?php
function banner_fields(array $b = []): string {
    $title = e($b['title'] ?? '');
    $link  = e($b['link'] ?? '');
    $img   = e($b['image'] ?? '');
    $starts= e($b['starts_at'] ?? '');
    $ends  = e($b['ends_at'] ?? '');
    $checked = (!empty($b['is_active']) || !isset($b['id'])) ? 'checked' : '';
    $positions = ['hero'=>'Homepage Hero','trending'=>'Trending','mobile'=>'Mobile'];
    $opts=''; foreach($positions as $k=>$v){ $sel=(($b['position']??'')===$k)?'selected':''; $opts.="<option value='$k' $sel>$v</option>"; }
    $startVal = $starts ? substr(str_replace(' ','T',$starts),0,16) : '';
    $endVal   = $ends ? substr(str_replace(' ','T',$ends),0,16) : '';
    return "<input type='hidden' name='existing_image' value='$img'>
        <div class='mb-2'><label class='form-label small'>Title</label><input class='form-control' name='title' value='$title' required></div>
        <div class='mb-2'><label class='form-label small'>Position</label><select class='form-select' name='position'>$opts</select></div>
        <div class='mb-2'><label class='form-label small'>Image upload</label><input class='form-control' type='file' name='image' accept='image/*'>".($img?"<small class='text-muted'>Current: <a href='$img' target='_blank'>view</a></small>":'')."</div>
        <div class='mb-2'><label class='form-label small'>Link</label><input class='form-control' name='link' value='$link'></div>
        <div class='row g-2 mb-2'><div class='col-6'><label class='form-label small'>Starts</label><input class='form-control' type='datetime-local' name='starts_at' value='$startVal'></div>
        <div class='col-6'><label class='form-label small'>Ends</label><input class='form-control' type='datetime-local' name='ends_at' value='$endVal'></div></div>
        <div class='form-check'><input class='form-check-input' type='checkbox' name='is_active' $checked id='ba'><label class='form-check-label small' for='ba'>Active</label></div>";
}
?>
<div class="modal fade" id="newBanner" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post" enctype="multipart/form-data"><?= Security::csrfField() ?><input type="hidden" name="action" value="save">
    <div class="modal-header border-secondary"><h5 class="modal-title">New banner</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?= banner_fields() ?></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Create</button></div>
    </form>
</div></div></div>
<?php foreach ($banners as $b): ?>
<div class="modal fade" id="editBanner<?= $b['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content glass-card">
    <form method="post" enctype="multipart/form-data"><?= Security::csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $b['id'] ?>">
    <div class="modal-header border-secondary"><h5 class="modal-title">Edit banner</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?= banner_fields($b) ?></div>
    <div class="modal-footer border-secondary"><button class="btn btn-mr-primary">Save</button></div>
    </form>
</div></div></div>
<?php endforeach; ?>
<?php admin_footer(); ?>
