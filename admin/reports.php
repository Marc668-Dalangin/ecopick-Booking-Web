<?php
require_once __DIR__ . '/../app/bootstrap.php'; require_once __DIR__ . '/../app/controllers/AdminFeatureController.php';
if (!Auth::check()) { header('Location: ' . APP_URL . '/admin-private-dnstl/login.php'); exit; } if (Auth::userRole() !== 'admin') { header('Location: ' . APP_URL . '/user-junkshop/dashboard.php'); exit; }
$pdo = Database::getInstance()->getPDO(); $pdo->exec("UPDATE pickup_requests SET admin_viewed_report = 1 WHERE current_status = 'Completed' AND (admin_viewed_report = 0 OR admin_viewed_report IS NULL)");
$startDate = (string) ($_GET['start_date'] ?? date('Y-m-01')); $endDate = (string) ($_GET['end_date'] ?? date('Y-m-d')); $sortOrder = (isset($_GET['sort_order']) && strtoupper((string) $_GET['sort_order']) === 'ASC') ? 'ASC' : 'DESC'; $report = (new AdminFeatureController())->getReport($startDate, $endDate, $sortOrder); $receiptUrl = static function (?string $receiptPath): string { $normalizedPath = ltrim(str_replace('\\', '/', trim($receiptPath ?? '')), '/'); return APP_URL . '/' . $normalizedPath; }; $pageTitle = 'Reports'; $activePage = 'reports'; ob_start();
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div><h2 class="fw-bold mb-1">Transaction Reports</h2><p class="text-muted mb-0">Aggregated completed pickup and service-fee data for the selected period.</p></div>
            <form class="d-flex flex-wrap gap-2"><input class="form-control" type="date" name="start_date" value="<?php echo Validator::escape($startDate); ?>" aria-label="Report start date"><input class="form-control" type="date" name="end_date" value="<?php echo Validator::escape($endDate); ?>" aria-label="Report end date"><select class="form-select" name="sort_order" aria-label="Transaction date order"><option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest first</option><option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest first</option></select><button class="btn btn-primary">Apply</button></form>
        </div>
        <div class="row g-3"><div class="col-md-6 col-xl-3"><div class="stat-card"><div class="display-6 fw-bold"><?php echo number_format((int) $report['completed_pickups']); ?></div><div class="text-muted">Completed pickups</div></div></div><div class="col-md-6 col-xl-3"><div class="stat-card"><div class="display-6 fw-bold">₱<?php echo number_format((float) $report['service_fees'], 2); ?></div><div class="text-muted">Service fees collected</div></div></div><div class="col-md-6 col-xl-3"><div class="stat-card"><div class="display-6 fw-bold">₱<?php echo number_format((float) $report['commissions'], 2); ?></div><div class="text-muted">Transaction commissions</div></div></div><div class="col-md-6 col-xl-3"><div class="stat-card"><div class="display-6 fw-bold">₱<?php echo number_format((float) $report['recyclable_value'], 2); ?></div><div class="text-muted">Final recyclable value</div></div></div></div>
    </div>
</div>
<?php if (false) { ?>
                    <tr class="border-0"><td colspan="7" class="p-0 border-0"><div class="collapse" id="<?php echo $collapseId; ?>"><div class="bg-light p-3 border-bottom"><h4 class="h6 fw-bold mb-3">Completed transactions with sellers</h4><?php if (empty($junkshop['transactions'])): ?><div class="text-muted small">No completed transactions in this period.</div><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light small"><tr><th>Booking</th><th>Seller</th><th>Date</th><th>Payment</th><th>Reference</th><th class="text-end">Weight (kg)</th><th class="text-end">Value (₱)</th><th class="text-end">Pickup fee</th><th class="text-end text-success">EcoPick service fee</th><th class="text-end text-warning-emphasis">Commission</th><th class="text-end">Seller net</th></tr></thead><tbody><?php foreach ($junkshop['transactions'] as $transaction): ?><tr><td><?php echo Validator::escape($transaction['booking_reference']); ?></td><td><div><?php echo Validator::escape($transaction['seller_name']); ?></div><small class="text-muted"><?php echo Validator::escape($transaction['seller_email']); ?></small></td><td><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></td><td><?php echo Validator::escape($transaction['payment_method'] ?? 'Not recorded'); ?></td><td><?php echo !empty($transaction['reference_number']) ? Validator::escape((string) $transaction['reference_number']) : '—'; ?></td>
<?php endforeach; ?><?php endif; ?><?php } ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h3 class="h4 fw-bold mb-1">Junkshop Service Fee &amp; Transaction Audit</h3><p class="text-muted mb-0">All registered junkshops and their completed transactions for the selected period.</p></div><span class="badge text-bg-light"><?php echo count($report['junkshop_reports']); ?> junkshops</span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Junkshop</th><th>Contact</th><th class="text-end">Transactions</th><th class="text-end">Weight (kg)</th><th class="text-end text-success">EcoPick service fees</th><th class="text-end text-warning-emphasis">Commissions</th><th class="text-end"><span class="visually-hidden">Details</span></th></tr></thead>
                <tbody>
                <?php if (empty($report['junkshop_reports'])): ?><tr><td colspan="7" class="text-center text-muted py-4">No registered junkshops found.</td></tr><?php endif; ?>
                <?php foreach ($report['junkshop_reports'] as $index => $junkshop): $collapseId = 'junkshop-transactions-' . (int) $junkshop['junkshop_id']; ?>
                    <tr>
                        <td><div class="fw-semibold"><?php echo Validator::escape($junkshop['business_name']); ?></div><small class="text-muted">ID #<?php echo (int) $junkshop['junkshop_id']; ?></small></td>
                        <td><div><?php echo Validator::escape($junkshop['email']); ?></div><small class="text-muted"><?php echo Validator::escape($junkshop['mobile_number'] ?: 'No mobile number'); ?></small></td>
                        <td class="text-end"><?php echo number_format((int) $junkshop['total_transactions']); ?></td>
                        <td class="text-end"><?php echo number_format((float) $junkshop['total_weight_kg'], 2); ?></td>
                        <td class="text-end fw-semibold text-success">₱<?php echo number_format((float) $junkshop['total_service_fees'], 2); ?></td>
                        <td class="text-end fw-semibold text-warning-emphasis">₱<?php echo number_format((float) $junkshop['total_commissions'], 2); ?></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><i class="bi bi-chevron-down me-1"></i>Details</button></td>
                    </tr>
                    <tr class="border-0"><td colspan="7" class="p-0 border-0"><div class="collapse" id="<?php echo $collapseId; ?>"><div class="bg-light p-3 border-bottom"><h4 class="h6 fw-bold mb-3">Completed transactions with sellers</h4><?php if (empty($junkshop['transactions'])): ?><div class="text-muted small">No completed transactions in this period.</div><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light small"><tr><th>Booking</th><th>Seller</th><th>Date</th><th>Payment</th><th>Reference</th><th>Receipt</th><th class="text-end">Weight (kg)</th><th class="text-end">Value (₱)</th><th class="text-end">Pickup fee</th><th class="text-end text-success">EcoPick service fee</th><th class="text-end text-warning-emphasis">Commission</th><th class="text-end">Seller net</th></tr></thead><tbody><?php foreach ($junkshop['transactions'] as $transaction): ?><tr><td><?php echo Validator::escape($transaction['booking_reference']); ?></td><td><div><?php echo Validator::escape($transaction['seller_name']); ?></div><small class="text-muted"><?php echo Validator::escape($transaction['seller_email']); ?></small></td><td><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></td><td><?php echo Validator::escape($transaction['payment_method'] ?? 'Not recorded'); ?></td><td><?php echo !empty($transaction['reference_number']) ? Validator::escape((string) $transaction['reference_number']) : '—'; ?></td><td><?php if (!empty($transaction['receipt_image'])): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transactionReceiptModal" data-receipt-url="<?php echo htmlspecialchars($receiptUrl((string) $transaction['receipt_image']), ENT_QUOTES, 'UTF-8'); ?>">View</button><?php else: ?>-<?php endif; ?></td><td class="text-end"><?php echo number_format((float) $transaction['actual_weight_kg'], 2); ?></td><td class="text-end">₱<?php echo number_format((float) $transaction['final_recyclable_value'], 2); ?></td><td class="text-end">₱<?php echo number_format((float) $transaction['pickup_fee'], 2); ?></td><td class="text-end fw-semibold text-success">₱<?php echo number_format((float) $transaction['ecopick_service_fee'], 2); ?></td><td class="text-end fw-semibold text-warning-emphasis">₱<?php echo number_format((float) $transaction['transaction_commission'], 2); ?></td><td class="text-end fw-semibold">₱<?php echo number_format((float) $transaction['final_seller_amount'], 2); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>

    </div>
</div>
<div class="modal fade" id="transactionReceiptModal" tabindex="-1" aria-labelledby="transactionReceiptModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="transactionReceiptModalLabel">GCash Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body text-center"><img id="transactionReceiptModalImage" class="img-fluid" alt="GCash receipt"></div></div></div></div>
<script>
    const transactionReceiptModal = document.getElementById('transactionReceiptModal');
    const transactionReceiptModalImage = document.getElementById('transactionReceiptModalImage');
    document.addEventListener('click', function (event) {
        const receiptButton = event.target.closest('[data-receipt-url]');
        if (receiptButton) {
            transactionReceiptModalImage.src = receiptButton.getAttribute('data-receipt-url');
        }
    });
    transactionReceiptModal.addEventListener('hidden.bs.modal', function () {
        transactionReceiptModalImage.removeAttribute('src');
    });
</script>
<?php $content = ob_get_clean(); require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
