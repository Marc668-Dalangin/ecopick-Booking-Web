<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/MaterialPriceController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

if (Auth::userRole() !== 'seller') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new MaterialPriceController();
$junkshops = $controller->listApprovedJunkshopsWithPrices();
$materials = $controller->listActiveMaterials();

$pageTitle = 'Partner Junkshops and Buying Prices';
$currentPage = 'partner-prices';
$userDisplayName = Auth::userName();

$materialMap = [];
foreach ($materials as $material) {
    $materialMap[(int)($material['id'] ?? 0)] = $material;
}

$rowsByJunkshop = [];
foreach ($junkshops as $row) {
    $junkshopId = (int)($row['junkshop_account_id'] ?? 0);
    $rowsByJunkshop[$junkshopId][] = $row;
}

ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-shop-window"></i> Partner Junkshops and Buying Prices</h4>
                <p class="text-muted mb-0">Only approved EcoPick partner junkshops are listed here. Final recyclable value depends on actual accepted materials, weight, and condition.</p>
            </div>
            <span class="badge bg-success-subtle text-success"><?php echo count($rowsByJunkshop); ?> partners</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="filter-junkshop-name">Filter by Junkshop Name</label>
                <input type="text" class="form-control" id="filter-junkshop-name" placeholder="Search by business name">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="filter-material">Filter by Material</label>
                <select class="form-select" id="filter-material">
                    <option value="">All materials</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo Validator::escape($material['material_name'] ?? ''); ?>"><?php echo Validator::escape($material['material_name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (empty($rowsByJunkshop)): ?>
            <div class="empty-state">
                <div class="display-6 text-muted"><i class="bi bi-shop"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No approved partner junkshops yet</h5>
                <p class="text-muted mb-0">Approved EcoPick partner junkshops and their live buying prices will appear here once they add accepted materials.</p>
            </div>
        <?php else: ?>
            <div id="seller-price-list" class="row g-4">
                <?php foreach ($rowsByJunkshop as $junkshopId => $rows): ?>
                    <?php $junkshop = $rows[0]; ?>
                    <div class="col-lg-6 seller-junkshop-card" data-junkshop-name="<?php echo Validator::escape(strtolower((string)($junkshop['business_name'] ?? ''))); ?>">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <h5 class="fw-bold mb-1"><?php echo Validator::escape($junkshop['business_name'] ?? ''); ?></h5>
                                        <div class="small text-muted"><?php echo Validator::escape($junkshop['location'] ?? ''); ?></div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success">Approved</span>
                                </div>

                                <div class="mb-3 small text-muted">
                                    <div><strong>Contact:</strong> <?php echo Validator::escape($junkshop['contact_person'] ?? ''); ?></div>
                                    <div><strong>Operating hours:</strong> <?php echo Validator::escape($junkshop['operating_schedule'] ?? ''); ?></div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th class="text-end">Buying Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rows as $row): ?>
                                                <tr data-material-name="<?php echo Validator::escape(strtolower((string)($row['material_name'] ?? ''))); ?>">
                                                    <td><?php echo Validator::escape($row['material_name'] ?? ''); ?></td>
                                                    <td><?php echo Validator::escape($row['category'] ?? ''); ?></td>
                                                    <td class="text-end fw-semibold">₱<?php echo number_format((float)($row['buying_price'] ?? 0), 2); ?> / <?php echo Validator::escape($row['unit_of_measure'] ?? 'kg'); ?></td>
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
        const nameFilter = document.getElementById('filter-junkshop-name');
        const materialFilter = document.getElementById('filter-material');
        const cards = Array.from(document.querySelectorAll('.seller-junkshop-card'));

        function applySellerFilters() {
            const nameValue = (nameFilter?.value || '').toLowerCase().trim();
            const materialValue = (materialFilter?.value || '').toLowerCase().trim();

            cards.forEach(function (card) {
                const businessName = (card.dataset.junkshopName || '').toLowerCase();
                const rows = card.querySelectorAll('[data-material-name]');
                let visible = true;

                if (nameValue && !businessName.includes(nameValue)) {
                    visible = false;
                }

                if (materialValue) {
                    const matchMaterial = Array.from(rows).some(function (row) {
                        return (row.dataset.materialName || '').includes(materialValue);
                    });
                    if (!matchMaterial) {
                        visible = false;
                    }
                }

                card.style.display = visible ? '' : 'none';
            });
        }

        nameFilter?.addEventListener('input', applySellerFilters);
        materialFilter?.addEventListener('change', applySellerFilters);
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
