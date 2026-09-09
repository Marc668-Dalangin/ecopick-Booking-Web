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
        <div class="mb-4"><p class="eyebrow mb-1">Junkshop records</p><h2 class="fw-bold mb-2">Completed Transactions</h2><p class="text-muted mb-0">Review final settlements, EcoPick commission, seller payout, and manual payment status.</p></div>
        <?php if (empty($transactions)): ?>
            <div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-journal-check"></i></div><h5 class="mt-3 mb-2 fw-bold">No completed transactions yet</h5><p class="text-muted mb-0">Completed pickup settlements will appear here.</p></div>
        <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
                <article class="border rounded-3 p-3 p-lg-4 mb-3">
                    <div class="d-flex justify-content-between flex-wrap gap-2"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1"><?php echo Validator::escape($transaction['booking_reference']); ?></h5><div class="small text-muted"><?php echo Validator::escape($transaction['materials_summary'] ?? ''); ?></div></div><span class="badge text-bg-<?php echo ($transaction['payment_status'] ?? '') === 'Paid' ? 'success' : 'warning'; ?>"><?php echo Validator::escape($transaction['payment_status'] ?? 'Unpaid'); ?></span></div>
                    <div class="row g-3 mt-2 small"><div class="col-md-3"><div class="text-muted">Completed</div><strong><?php echo Validator::escape(date('M d, Y H:i', strtotime($transaction['completed_at']))); ?></strong></div><div class="col-md-3"><div class="text-muted">Final value</div><strong>₱<?php echo number_format((float)$transaction['final_recyclable_value'], 2); ?></strong></div><div class="col-md-3"><div class="text-muted">Seller payout</div><strong>₱<?php echo number_format((float)$transaction['final_seller_amount'], 2); ?></strong></div><div class="col-md-3"><div class="text-muted">EcoPick commission</div><strong>₱<?php echo number_format((float)$transaction['transaction_commission'], 2); ?></strong></div></div>
                    <div class="small text-muted mt-3">Payment method: <strong><?php echo Validator::escape($transaction['payment_method'] ?? 'Not recorded'); ?></strong><?php if (!empty($transaction['payment_confirmed_at'])): ?> · Confirmed <?php echo Validator::escape(date('M d, Y H:i', strtotime($transaction['payment_confirmed_at']))); ?><?php endif; ?></div>
                    <?php if (!empty($transaction['payment_proof_id'])): ?><a class="btn btn-sm btn-outline-secondary mt-3" target="_blank" rel="noopener" href="<?php echo APP_URL; ?>/user-junkshop/api/payment.php?action=view-proof&amp;proof_id=<?php echo (int)$transaction['payment_proof_id']; ?>">Review payment proof</a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
