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
$rows = $controller->getAdminPriceOverview();
$catalog = $controller->getAdminMaterialCatalog();
$pageTitle = 'Pricing Lists';
$activePage = 'pricing-lists';

$materials = [];
foreach ($catalog as $material) {
    $materials[(string)($material['material_name'] ?? '')] = true;
}

ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-currency-dollar"></i> Pricing Lists</h4>
                <p class="text-muted mb-0">Approved junkshops and their current material buying prices are monitored here.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary"><?php echo count($catalog); ?> catalog materials</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="admin-material-filter">Filter by Material</label>
                <select class="form-select" id="admin-material-filter">
                    <option value="">All materials</option>
                    <?php foreach (array_keys($materials) as $materialName): ?>
                        <option value="<?php echo Validator::escape($materialName); ?>"><?php echo Validator::escape($materialName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="display-6 text-muted"><i class="bi bi-cash-stack"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No pricing data yet</h5>
                <p class="text-muted mb-0">Approved junkshops have not added any accepted materials or buying prices yet.</p>
            </div>
        <?php else: ?>
            <div class="accordion accordion-flush" id="adminMaterialsCatalogAccordion">
                <?php
                $categories = ['PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS'];
                foreach ($categories as $category):
                    $categoryRows = array_values(array_filter($catalog, static function (array $row) use ($category): bool {
                        return strtoupper((string)($row['category'] ?? '')) === $category;
                    }));
                    $collapseId = 'adminMaterialsCategory-' . strtolower($category);
                    ?>
                    <div class="accordion-item border rounded mb-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">
                                <?php echo Validator::escape($category); ?>
                            </button>
                        </h2>
                        <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse" data-bs-parent="#adminMaterialsCatalogAccordion">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="admin-pricing-table-<?php echo strtolower($category); ?>">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Material</th>
                                                <th>Price Statistics</th>
                                                <th class="text-end">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categoryRows as $row): ?>
                                                <?php $detailsId = 'admin-material-details-' . (int)($row['material_id'] ?? 0); ?>
                                                <tr data-material-name="<?php echo Validator::escape(strtolower((string)($row['material_name'] ?? ''))); ?>">
                                                    <td class="fw-semibold"><?php echo Validator::escape($row['material_name'] ?? ''); ?></td>
                                                    <td class="small">Avg: ₱<?php echo number_format((float)($row['avg_price'] ?? 0), 2); ?><br>Min: ₱<?php echo number_format((float)($row['min_price'] ?? 0), 2); ?><br>Max: ₱<?php echo number_format((float)($row['max_price'] ?? 0), 2); ?></td>
                                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#<?php echo $detailsId; ?>" aria-expanded="false" aria-controls="<?php echo $detailsId; ?>">View Info</button></td>
                                                </tr>
                                                <tr class="collapse" id="<?php echo $detailsId; ?>" data-material-name="<?php echo Validator::escape(strtolower((string)($row['material_name'] ?? ''))); ?>">
                                                    <td colspan="3" class="bg-light-subtle">
                                                        <div class="row g-3 small">
                                                            <div class="col-md-4"><strong>Description</strong><div class="text-muted mt-1"><?php echo nl2br(Validator::escape((string)($row['description'] ?? ''))); ?></div></div>
                                                            <div class="col-md-4"><strong>Examples</strong><div class="text-muted mt-1"><?php echo nl2br(Validator::escape((string)($row['examples'] ?? ''))); ?></div></div>
                                                            <div class="col-md-4"><strong>Preparation Notes</strong><div class="text-muted mt-1"><?php echo nl2br(Validator::escape((string)($row['preparation_notes'] ?? ''))); ?></div></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const materialFilter = document.getElementById('admin-material-filter');
        const rows = Array.from(document.querySelectorAll('[id^="admin-pricing-table-"] tbody tr'));

        function applyAdminFilters() {
            const materialValue = (materialFilter?.value || '').toLowerCase().trim();

            rows.forEach(function (row) {
                const materialMatch = !materialValue || (row.dataset.materialName || '').includes(materialValue);
                row.style.display = materialMatch ? '' : 'none';
            });
        }

        materialFilter?.addEventListener('change', applyAdminFilters);
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
