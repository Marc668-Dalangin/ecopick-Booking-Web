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
$pageTitle = 'Pricing Lists';
$activePage = 'pricing-lists';

$materials = [];
$rowsByJunkshop = [];
foreach ($rows as $row) {
    $materials[(string)($row['material_name'] ?? '')] = true;
    $rowsByJunkshop[(int)($row['junkshop_account_id'] ?? 0)][] = $row;
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
            <span class="badge bg-primary-subtle text-primary"><?php echo count($rowsByJunkshop); ?> junkshops</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="admin-junkshop-filter">Filter by Junkshop</label>
                <input type="text" class="form-control" id="admin-junkshop-filter" placeholder="Search by business name">
            </div>
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
            <div class="accordion accordion-flush" id="adminJunkshopPricingAccordion">
                <?php $categories = ['PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS']; ?>
                <?php foreach ($rowsByJunkshop as $junkshopId => $junkshopRows): ?>
                    <?php $junkshop = $junkshopRows[0]; $junkshopCollapseId = 'junkshop-pricing-' . $junkshopId; ?>
                    <div class="accordion-item border rounded mb-3 overflow-hidden admin-junkshop-item" data-junkshop-name="<?php echo Validator::escape(strtolower((string)($junkshop['business_name'] ?? ''))); ?>">
                        <h2 class="accordion-header d-flex align-items-center">
                            <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $junkshopCollapseId; ?>">
                                <span><strong><?php echo Validator::escape($junkshop['business_name'] ?? ''); ?></strong><small class="d-block text-muted"><?php echo Validator::escape($junkshop['location'] ?? ''); ?></small></span>
                            </button>
                        </h2>
                        <div id="<?php echo $junkshopCollapseId; ?>" class="accordion-collapse collapse" data-bs-parent="#adminJunkshopPricingAccordion">
                            <div class="accordion-body">
                                <div class="small text-muted mb-3">Contact: <?php echo Validator::escape($junkshop['contact_person'] ?? ''); ?> · <?php echo Validator::escape($junkshop['operating_schedule'] ?? ''); ?></div>
                                <div class="accordion accordion-flush">
                                    <?php foreach ($categories as $category): ?>
                                        <?php $categoryRows = array_values(array_filter($junkshopRows, static fn (array $row): bool => strtoupper((string)($row['category'] ?? '')) === $category)); ?>
                                        <?php $categoryCollapseId = $junkshopCollapseId . '-' . strtolower($category); ?>
                                        <div class="accordion-item border rounded mb-2">
                                            <h3 class="accordion-header">
                                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $categoryCollapseId; ?>"><?php echo Validator::escape($category); ?></button>
                                            </h3>
                                            <div id="<?php echo $categoryCollapseId; ?>" class="accordion-collapse collapse">
                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-0 admin-pricing-table">
                                                        <thead class="table-light"><tr><th>Material Name</th><th>Buying Price</th><th>Information</th><th>Last Updated</th></tr></thead>
                                                        <tbody>
                                                            <?php if (empty($categoryRows)): ?><tr><td colspan="4" class="text-muted fst-italic">No accepted materials in this category.</td></tr><?php endif; ?>
                                                            <?php foreach ($categoryRows as $row): ?>
                                                                <?php $infoId = 'admin-material-info-' . (int)($row['price_id'] ?? 0); ?>
                                                                <tr data-junkshop-name="<?php echo Validator::escape(strtolower((string)($row['business_name'] ?? ''))); ?>" data-material-name="<?php echo Validator::escape(strtolower((string)($row['material_name'] ?? ''))); ?>">
                                                                    <td class="fw-semibold"><?php echo Validator::escape($row['material_name'] ?? ''); ?></td>
                                                                    <td><?php echo (int)($row['available'] ?? 0) === 1 ? '₱' . number_format((float)($row['buying_price'] ?? 0), 2) . ' / kg' : '<span class="badge bg-secondary-subtle text-secondary">Not Accepted</span>'; ?></td>
                                                                    <td><button class="btn btn-link btn-sm p-0" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $infoId; ?>">View Info</button><div id="<?php echo $infoId; ?>" class="collapse small text-muted mt-2"><div><strong>Description:</strong> <?php echo nl2br(Validator::escape((string)($row['description'] ?? ''))); ?></div><div><strong>Examples:</strong> <?php echo nl2br(Validator::escape((string)($row['examples'] ?? ''))); ?></div><div><strong>Preparation:</strong> <?php echo nl2br(Validator::escape((string)($row['preparation_notes'] ?? ''))); ?></div></div></td>
                                                                    <td><?php echo Validator::escape(date('M d, Y', strtotime($row['updated_at'] ?? date('Y-m-d')))); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
        const junkshopFilter = document.getElementById('admin-junkshop-filter');
        const materialFilter = document.getElementById('admin-material-filter');
        const rows = Array.from(document.querySelectorAll('.admin-pricing-table tbody tr'));
        const junkshopItems = Array.from(document.querySelectorAll('.admin-junkshop-item'));

        function applyAdminFilters() {
            const junkshopValue = (junkshopFilter?.value || '').toLowerCase().trim();
            const materialValue = (materialFilter?.value || '').toLowerCase().trim();

            rows.forEach(function (row) {
                const nameMatch = !junkshopValue || (row.dataset.junkshopName || '').includes(junkshopValue);
                const materialMatch = !materialValue || (row.dataset.materialName || '').includes(materialValue);
                row.style.display = nameMatch && materialMatch ? '' : 'none';
            });
            junkshopItems.forEach(function (item) {
                const nameMatch = !junkshopValue || (item.dataset.junkshopName || '').includes(junkshopValue);
                const matchingRows = Array.from(item.querySelectorAll('.admin-pricing-table tbody tr[data-material-name]'));
                const materialMatch = !materialValue || matchingRows.some(function (row) {
                    return (row.dataset.materialName || '').includes(materialValue);
                });
                item.style.display = nameMatch && materialMatch ? '' : 'none';
            });
        }

        junkshopFilter?.addEventListener('input', applyAdminFilters);
        materialFilter?.addEventListener('change', applyAdminFilters);
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
