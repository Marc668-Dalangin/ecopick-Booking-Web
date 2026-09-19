<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/MaterialPriceController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin-private-dnstl/login.php');
    exit;
}
if (Auth::userRole() !== 'admin') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new MaterialPriceController();
$categories = ['PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS'];
$statusMessage = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $statusMessage = ['success' => false, 'message' => 'Invalid security token.'];
    } else {
        $payload = [
            'name' => $_POST['name'] ?? '',
            'category' => $_POST['category'] ?? '',
            'description' => $_POST['description'] ?? '',
            'examples' => $_POST['examples'] ?? '',
            'preparation_notes' => $_POST['preparation_notes'] ?? '',
            'unit_of_measure' => $_POST['unit_of_measure'] ?? 'kg',
        ];
        $materialId = (int) ($_POST['material_id'] ?? 0);
        $statusMessage = $materialId > 0
            ? $controller->updateAdminMaterial($materialId, $payload)
            : $controller->createAdminMaterial($payload);
    }
}

$catalog = $controller->getAdminMaterialCatalog();
$pageTitle = 'Materials Management';
$activePage = 'materials';
ob_start();
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-recycle"></i> Materials Management</h4>
                <?php if ($statusMessage): ?><div class="alert <?php echo !empty($statusMessage['success']) ? 'alert-success' : 'alert-danger'; ?>" role="alert"><?php echo Validator::escape($statusMessage['message']); ?></div><?php endif; ?>
                <form method="post">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="material_id" id="material_id" value="0">
                    <div class="mb-3"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?php echo $category; ?>"><?php echo $category; ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label" for="name">Material Name</label><input class="form-control" id="name" name="name" maxlength="100" required></div>
                    <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label" for="examples">Examples</label><textarea class="form-control" id="examples" name="examples" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label" for="preparation_notes">Preparation Notes</label><textarea class="form-control" id="preparation_notes" name="preparation_notes" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label" for="unit_of_measure">Unit</label><input class="form-control" id="unit_of_measure" name="unit_of_measure" value="kg" maxlength="20" required></div>
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save"></i> Save Material</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h5 class="fw-bold mb-3">Five-Category Catalog</h5>
            <div class="accordion accordion-flush" id="adminMaterialManagementAccordion">
                <?php foreach ($categories as $category): ?>
                    <?php $items = array_values(array_filter($catalog, static fn (array $item): bool => ($item['category'] ?? '') === $category)); $collapseId = 'manage-' . strtolower($category); ?>
                    <div class="accordion-item border rounded mb-2 overflow-hidden"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>"><?php echo $category; ?></button></h2><div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse" data-bs-parent="#adminMaterialManagementAccordion"><div class="accordion-body p-0">
                        <?php if (!$items): ?><div class="p-3 text-muted">No materials in this category.</div><?php endif; ?>
                        <?php foreach ($items as $item): ?><div class="border-bottom p-3 d-flex justify-content-between align-items-start gap-3"><div><strong><?php echo Validator::escape($item['material_name']); ?></strong><div class="small text-muted mt-1"><?php echo Validator::escape($item['description'] ?? ''); ?></div></div><button type="button" class="btn btn-sm btn-outline-primary edit-material" data-material="<?php echo Validator::escape(json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>">Edit</button></div><?php endforeach; ?>
                    </div></div></div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-material').forEach(function (button) {
        button.addEventListener('click', function () {
            const material = JSON.parse(button.dataset.material || '{}');
            document.getElementById('material_id').value = material.material_id || 0;
            document.getElementById('category').value = material.category || '';
            document.getElementById('name').value = material.material_name || '';
            document.getElementById('description').value = material.description || '';
            document.getElementById('examples').value = material.examples || '';
            document.getElementById('preparation_notes').value = material.preparation_notes || '';
            document.getElementById('unit_of_measure').value = material.unit_of_measure || 'kg';
            document.getElementById('category').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
