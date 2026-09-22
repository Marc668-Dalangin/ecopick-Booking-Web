<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

Auth::requireLogin();
if (Auth::userRole() !== 'junkshop') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new DashboardController();
$sortOrder = strtoupper((string) ($_GET['sort_order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$transactions = $controller->getJunkshopCompletedTransactions(Auth::userId(), $sortOrder);
$pageTitle = 'Completed Transactions';
$currentPage = 'completed-transactions';
$userDisplayName = Auth::userName();
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="mb-4"><p class="eyebrow mb-1">Junkshop records</p><h2 class="fw-bold mb-2">Completed Transactions</h2><p class="text-muted mb-0">Review completed transaction amounts and manual payment status.</p></div>
        <form method="GET" class="d-flex align-items-center gap-2 mb-3 flex-wrap">
            <?php foreach ($_GET as $key => $val): ?>
                <?php if ($key !== 'sort_order' && is_scalar($val)): ?><input type="hidden" name="<?php echo Validator::escape($key); ?>" value="<?php echo Validator::escape((string) $val); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label for="sort_order" class="form-label mb-0 fw-bold text-nowrap"><i class="bi bi-sort-numeric-down me-1"></i>Sort by Date:</label>
            <select name="sort_order" id="sort_order" class="form-select form-select-sm auto-submit" onchange="this.form.submit()">
                <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First (Descending)</option>
                <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First (Ascending)</option>
            </select>
        </form>
        <?php if (empty($transactions)): ?>
            <div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-journal-check"></i></div><h5 class="mt-3 mb-2 fw-bold">No completed transactions yet</h5><p class="text-muted mb-0">Completed pickup settlements will appear here.</p></div>
        <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
                <article class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between gap-3 p-3 flex-wrap" role="button" data-bs-toggle="collapse" data-bs-target="#collapseTxn<?php echo (int) $transaction['id']; ?>" aria-controls="collapseTxn<?php echo (int) $transaction['id']; ?>">
                        <div class="d-flex align-items-center gap-3 flex-wrap"><span class="fw-bold font-monospace">#<?php echo (int) $transaction['id']; ?></span><span class="fw-semibold"><?php echo Validator::escape($transaction['booking_reference']); ?></span><span class="text-muted small"><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></span><span class="badge text-bg-<?php echo ($transaction['payment_status'] ?? '') === 'Paid' ? 'success' : 'warning'; ?>"><?php echo Validator::escape($transaction['payment_status'] ?? 'Unpaid'); ?></span></div>
                        <div class="d-flex align-items-center gap-2"><span class="fw-bold text-success"><?php echo number_format((float) $transaction['actual_weight_kg'], 2); ?> kg</span><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTxn<?php echo (int) $transaction['id']; ?>" aria-expanded="false" aria-controls="collapseTxn<?php echo (int) $transaction['id']; ?>" onclick="event.stopPropagation();"><i class="bi bi-chevron-down me-1"></i>Details</button></div>
                    </div>
                    <div class="collapse" id="collapseTxn<?php echo (int) $transaction['id']; ?>">
                        <div class="card-body bg-light">
                            <?php if (!empty($transaction['material_details'])): ?><div class="mb-3"><div class="small text-muted fw-bold mb-1">Materials breakdown</div><?php foreach (explode('~~~', (string) $transaction['material_details']) as $materialDetail): $materialParts = explode('|||', $materialDetail, 3); if (count($materialParts) !== 3) continue; ?><div class="small"><?php echo htmlspecialchars($materialParts[0], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($materialParts[1], ENT_QUOTES, 'UTF-8'); ?> kg) · Condition: <?php echo !empty($materialParts[2]) ? htmlspecialchars($materialParts[2], ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div><?php endforeach; ?></div><?php endif; ?>
                            <div class="row g-3 small"><div class="col-md-4"><div class="text-muted fw-bold">Seller</div><div><?php echo Validator::escape($transaction['seller_fullname'] ?? 'Seller'); ?></div></div><div class="col-md-4"><div class="text-muted fw-bold">Actual weight</div><div><?php echo number_format((float) $transaction['actual_weight_kg'], 2); ?> kg</div></div><div class="col-md-4"><div class="text-muted fw-bold">Pickup address</div><div><?php echo Validator::escape($transaction['pickup_address'] ?? 'Address not recorded'); ?></div></div></div>
                            <div class="small mt-3"><div class="text-muted fw-bold">Materials summary</div><div><?php echo Validator::escape($transaction['materials_summary'] ?? 'Not recorded'); ?></div></div>
                            <div class="border rounded-3 p-3 mt-3" aria-label="Transaction amount"><div class="fw-bold">Transaction amount</div><hr class="my-2"><div class="d-flex justify-content-between gap-3"><span>Estimated recyclable value</span><strong>₱<?php echo number_format((float)$transaction['final_recyclable_value'], 2); ?></strong></div><div class="d-flex justify-content-between gap-3"><span>Pickup / Collection fee</span><strong>- ₱<?php echo number_format((float)$transaction['pickup_fee'], 2); ?></strong></div><div class="d-flex justify-content-between gap-3"><span>Ecopick service fee</span><strong>- ₱<?php echo number_format((float)$transaction['ecopick_service_fee'], 2); ?></strong></div><hr class="my-2"><div class="d-flex justify-content-between gap-3 fw-bold"><span>Net amount to receive</span><strong>₱<?php echo number_format((float)$transaction['final_seller_amount'], 2); ?></strong></div></div>
                            <div class="small text-muted mt-3">Payment method: <strong><?php echo Validator::escape($transaction['payment_method'] ?? 'Not recorded'); ?></strong><?php if (($transaction['payment_method'] ?? '') === 'GCash' && !empty($transaction['reference_number'])): ?> · Reference: <strong><?php echo Validator::escape((string) $transaction['reference_number']); ?></strong><?php endif; ?><?php if (!empty($transaction['payment_confirmed_at'])): ?> · Confirmed <?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['payment_confirmed_at']))); ?><?php endif; ?></div>
                            <?php if (!empty($transaction['payment_proof_id']) && ($transaction['payment_method'] ?? '') !== 'GCash'): ?><a class="btn btn-sm btn-outline-secondary mt-3" target="_blank" rel="noopener" href="<?php echo APP_URL; ?>/user-junkshop/api/payment.php?action=view-proof&amp;proof_id=<?php echo (int)$transaction['payment_proof_id']; ?>">Review payment proof</a><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
