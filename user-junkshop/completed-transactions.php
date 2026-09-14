<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

Auth::requireLogin();
if (Auth::userRole() !== 'junkshop') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new DashboardController();
$transactions = $controller->getJunkshopCompletedTransactions(Auth::userId());
$pageTitle = 'Completed Transactions';
$currentPage = 'completed-transactions';
$userDisplayName = Auth::userName();
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="mb-4"><p class="eyebrow mb-1">Junkshop records</p><h2 class="fw-bold mb-2">Completed Transactions</h2><p class="text-muted mb-0">Review completed transaction amounts and manual payment status.</p></div>
        <?php if (empty($transactions)): ?>
            <div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-journal-check"></i></div><h5 class="mt-3 mb-2 fw-bold">No completed transactions yet</h5><p class="text-muted mb-0">Completed pickup settlements will appear here.</p></div>
        <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
                <article class="border rounded-3 p-3 p-lg-4 mb-3">
                    <?php if (!empty($transaction['material_details'])): ?><div class="small mb-2"><?php foreach (explode('~~~', (string) $transaction['material_details']) as $materialDetail): $materialParts = explode('|||', $materialDetail, 3); if (count($materialParts) !== 3) continue; ?><div><?php echo htmlspecialchars($materialParts[0], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($materialParts[1], ENT_QUOTES, 'UTF-8'); ?> kg)<br>Condition: <?php echo !empty($materialParts[2]) ? htmlspecialchars($materialParts[2], ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div><?php endforeach; ?></div><?php endif; ?>
                    <div class="d-flex justify-content-between flex-wrap gap-2"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1"><?php echo Validator::escape($transaction['booking_reference']); ?></h5><div class="small text-muted"><?php echo Validator::escape($transaction['materials_summary'] ?? ''); ?></div><div class="small mt-2"><span class="text-muted">Seller name:</span> <strong><?php echo Validator::escape($transaction['seller_fullname'] ?? 'Seller'); ?></strong></div><div class="small mt-1"><span class="text-muted">Pickup address:</span> <strong><?php echo Validator::escape($transaction['pickup_address'] ?? 'Address not recorded'); ?></strong></div></div><span class="badge text-bg-<?php echo ($transaction['payment_status'] ?? '') === 'Paid' ? 'success' : 'warning'; ?>"><?php echo Validator::escape($transaction['payment_status'] ?? 'Unpaid'); ?></span></div>
                    <div class="row g-3 mt-2 small"><div class="col-md-3"><div class="text-muted">Completed</div><strong><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></strong></div><div class="col-md-3"><div class="text-muted">Actual weight</div><strong><?php echo number_format((float)$transaction['actual_weight_kg'], 2); ?> kg</strong></div></div>
                    <div class="border rounded-3 p-3 mt-3" aria-label="Estimated amount"><div class="fw-bold">Estimated amount</div><hr class="my-2"><div class="d-flex justify-content-between gap-3"><span>Estimated recyclable value</span><strong>₱<?php echo number_format((float)$transaction['final_recyclable_value'], 2); ?></strong></div><div class="d-flex justify-content-between gap-3"><span>Pickup / Collection fee</span><strong>- ₱<?php echo number_format((float)$transaction['pickup_fee'], 2); ?></strong></div><div class="d-flex justify-content-between gap-3"><span>Ecopick service fee</span><strong>- ₱<?php echo number_format((float)$transaction['ecopick_service_fee'], 2); ?></strong></div><hr class="my-2"><div class="d-flex justify-content-between gap-3 fw-bold"><span>Estimated net amount to receive</span><strong>₱<?php echo number_format((float)$transaction['final_seller_amount'], 2); ?></strong></div></div>
                    <div class="small text-muted mt-3">Payment method: <strong><?php echo Validator::escape($transaction['payment_method'] ?? 'Not recorded'); ?></strong><?php if (!empty($transaction['payment_confirmed_at'])): ?> · Confirmed <?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['payment_confirmed_at']))); ?><?php endif; ?></div>
                    <?php if (!empty($transaction['payment_proof_id'])): ?><a class="btn btn-sm btn-outline-secondary mt-3" target="_blank" rel="noopener" href="<?php echo APP_URL; ?>/user-junkshop/api/payment.php?action=view-proof&amp;proof_id=<?php echo (int)$transaction['payment_proof_id']; ?>">Review payment proof</a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
