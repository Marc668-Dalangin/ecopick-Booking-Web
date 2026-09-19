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
$materials = $controller->listAdminMaterialCatalog();
$categories = ['PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS'];
$pageTitle = 'Materials Monitoring';
$activePage = 'materials';

ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-recycle"></i> Materials Monitoring</h4>
                <p class="text-muted mb-0">Active recyclable materials and their preparation guidance.</p>
            </div>
            <span class="badge bg-success-subtle text-success"><?php echo count($materials); ?> active materials</span>
        </div>

        <div class="accordion accordion-flush" id="adminMaterialCatalogAccordion">
            <?php foreach ($categories as $category): ?>
                <?php $categoryMaterials = array_values(array_filter($materials, static fn (array $material): bool => strtoupper((string)($material['category'] ?? '')) === $category)); ?>
                <?php $categoryId = 'admin-material-category-' . strtolower($category); ?>
                <div class="accordion-item border rounded mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $categoryId; ?>"><?php echo Validator::escape($category); ?></button>
                    </h2>
                    <div id="<?php echo $categoryId; ?>" class="accordion-collapse collapse" data-bs-parent="#adminMaterialCatalogAccordion">
                        <div class="accordion-body">
                            <?php if (empty($categoryMaterials)): ?>
                                <div class="text-muted small">No active materials in this category.</div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($categoryMaterials as $material): ?>
                                        <?php $infoId = 'admin-catalog-info-' . (int)($material['material_id'] ?? 0); ?>
                                        <div class="col-12 col-lg-6">
                                            <div class="border rounded p-3 h-100">
                                                <div class="d-flex justify-content-between align-items-start gap-3">
                                                    <strong><?php echo Validator::escape($material['material_name'] ?? ''); ?></strong>
                                                    <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#<?php echo $infoId; ?>">View Info</button>
                                                </div>
                                                <div id="<?php echo $infoId; ?>" class="collapse small text-muted mt-2">
                                                    <div><strong>Description:</strong> <?php echo nl2br(Validator::escape((string)($material['description'] ?? ''))); ?></div>
                                                    <div class="mt-1"><strong>Examples:</strong> <?php echo nl2br(Validator::escape((string)($material['examples'] ?? ''))); ?></div>
                                                    <div class="mt-1"><strong>Preparation Notes:</strong> <?php echo nl2br(Validator::escape((string)($material['preparation_notes'] ?? ''))); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
