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
foreach ($rows as $row) {
    $materials[(string)($row['material_name'] ?? '')] = true;
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
            <span class="badge bg-primary-subtle text-primary"><?php echo count($rows); ?> current entries</span>
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
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="admin-pricing-table">
                    <thead class="table-light">
                        <tr>
                            <th>Junkshop</th>
                            <th>Location</th>
                            <th>Material</th>
                            <th>Category</th>
                            <th>Buying Price</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr data-junkshop-name="<?php echo Validator::escape(strtolower((string)($row['business_name'] ?? ''))); ?>" data-material-name="<?php echo Validator::escape(strtolower((string)($row['material_name'] ?? ''))); ?>">
                                <td class="fw-semibold"><?php echo Validator::escape($row['business_name'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($row['location'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($row['material_name'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($row['category'] ?? ''); ?></td>
                                <td>₱<?php echo number_format((float)($row['buying_price'] ?? 0), 2); ?> / <?php echo Validator::escape($row['unit_of_measure'] ?? 'kg'); ?></td>
                                <td><?php echo Validator::escape(date('M d, Y', strtotime($row['updated_at'] ?? date('Y-m-d')))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const junkshopFilter = document.getElementById('admin-junkshop-filter');
        const materialFilter = document.getElementById('admin-material-filter');
        const rows = Array.from(document.querySelectorAll('#admin-pricing-table tbody tr'));

        function applyAdminFilters() {
            const junkshopValue = (junkshopFilter?.value || '').toLowerCase().trim();
            const materialValue = (materialFilter?.value || '').toLowerCase().trim();

            rows.forEach(function (row) {
                const nameMatch = !junkshopValue || (row.dataset.junkshopName || '').includes(junkshopValue);
                const materialMatch = !materialValue || (row.dataset.materialName || '').includes(materialValue);
                row.style.display = nameMatch && materialMatch ? '' : 'none';
            });
        }

        junkshopFilter?.addEventListener('input', applyAdminFilters);
        materialFilter?.addEventListener('change', applyAdminFilters);
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
