<?php
/**
 * Manual payment verification and proof handling.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class PaymentController
{
    private const MAX_PROOF_SIZE_BYTES = 3145728;

    private Database $db;
    private string $proofDirectory;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->proofDirectory = __DIR__ . '/../../storage/payment-proofs';
    }

    public function uploadGcashProof(int $sellerAccountId, int $transactionId, array $file): array
    {
        $transaction = $this->getTransactionForSeller($sellerAccountId, $transactionId);
        if ($transaction === null) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }
        if (($transaction['payment_method'] ?? '') !== 'GCash') {
            return ['success' => false, 'message' => 'Proof uploads are only required for GCash payments.'];
        }
        if (($transaction['payment_status'] ?? '') !== 'Unpaid') {
            return ['success' => false, 'message' => 'This payment has already been reviewed.'];
        }

        $validation = $this->validateProofFile($file);
        if (!$validation['success']) {
            return $validation;
        }

        if (!is_dir($this->proofDirectory) && !mkdir($this->proofDirectory, 0750, true)) {
            return ['success' => false, 'message' => 'Payment proof storage is unavailable.'];
        }

        $extension = $validation['mime_type'] === 'image/png' ? 'png' : 'jpg';
        $filename = bin2hex(random_bytes(20)) . '.' . $extension;
        $targetPath = $this->proofDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Payment proof could not be saved.'];
        }

        try {
            $this->db->query(
                'INSERT INTO payment_proofs (transaction_id, seller_account_id, storage_path, original_filename, mime_type, file_size_bytes) VALUES (:transaction_id, :seller_account_id, :storage_path, :original_filename, :mime_type, :file_size_bytes)',
                [
                    'transaction_id' => $transactionId,
                    'seller_account_id' => $sellerAccountId,
                    'storage_path' => $filename,
                    'original_filename' => basename((string) ($file['name'] ?? 'payment-proof')),
                    'mime_type' => $validation['mime_type'],
                    'file_size_bytes' => (int) $file['size'],
                ]
            );

            return ['success' => true, 'message' => 'GCash payment proof uploaded for junkshop review.'];
        } catch (Throwable $e) {
            @unlink($targetPath);
            error_log('Payment proof insert error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment proof could not be recorded.'];
        }
    }

    public function confirmPayment(int $junkshopAccountId, int $transactionId): array
    {
        $transaction = $this->getTransactionForJunkshop($junkshopAccountId, $transactionId);
        if ($transaction === null) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }
        if (($transaction['payment_status'] ?? '') !== 'Unpaid') {
            return ['success' => false, 'message' => 'This payment has already been confirmed.'];
        }

        $proofId = null;
        if (($transaction['payment_method'] ?? '') === 'GCash') {
            $proof = $this->db->query(
                'SELECT id FROM payment_proofs WHERE transaction_id = :transaction_id AND proof_status = :proof_status ORDER BY uploaded_at DESC, id DESC LIMIT 1',
                ['transaction_id' => $transactionId, 'proof_status' => 'Submitted']
            )->fetch();
            if (!$proof) {
                return ['success' => false, 'message' => 'A submitted GCash proof is required before confirmation.'];
            }
            $proofId = (int) $proof['id'];
        }

        try {
            $this->db->beginTransaction();
            $previousStatus = (string) $transaction['payment_status'];
            $transactionUpdate = $this->db->query(
                'UPDATE transactions SET payment_status = :payment_status, payment_confirmed_at = CURRENT_TIMESTAMP, payment_confirmed_by_account_id = :actor_id WHERE id = :transaction_id AND junkshop_id = :junkshop_id AND payment_status = :expected_status',
                [
                    'payment_status' => 'Paid',
                    'actor_id' => $junkshopAccountId,
                    'transaction_id' => $transactionId,
                    'junkshop_id' => $junkshopAccountId,
                    'expected_status' => 'Unpaid',
                ]
            );
            if ($transactionUpdate->rowCount() !== 1) {
                throw new RuntimeException('The payment status changed before confirmation completed.');
            }

            $paymentUpdate = $this->db->query(
                'UPDATE transaction_payments SET payment_status = :payment_status, paid_at = CURRENT_TIMESTAMP, recorded_by_account_id = :actor_id WHERE transaction_id = :transaction_id AND payment_purpose = :purpose AND payment_status = :expected_status',
                [
                    'payment_status' => 'Paid',
                    'actor_id' => $junkshopAccountId,
                    'transaction_id' => $transactionId,
                    'purpose' => 'Seller Payout',
                    'expected_status' => 'Unpaid',
                ]
            );
            if ($paymentUpdate->rowCount() !== 1) {
                throw new RuntimeException('The seller payout payment record could not be updated.');
            }

            if ($proofId !== null) {
                $this->db->query(
                    'UPDATE payment_proofs SET proof_status = :proof_status, reviewed_at = CURRENT_TIMESTAMP, reviewed_by_account_id = :reviewer_id WHERE id = :proof_id',
                    ['proof_status' => 'Approved', 'reviewer_id' => $junkshopAccountId, 'proof_id' => $proofId]
                );
            }

            $bookingUpdate = $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :pickup_request_id AND current_status = :expected_status',
                ['status' => 'Completed', 'pickup_request_id' => (int) $transaction['pickup_request_id'], 'expected_status' => 'For Pickup']
            );
            if ($bookingUpdate->rowCount() !== 1) {
                throw new RuntimeException('The pickup request could not be completed after payment confirmation.');
            }
            StatusLogger::logChange((int) $transaction['pickup_request_id'], 'For Pickup', 'Completed', 'Junkshop', $junkshopAccountId);

            $this->db->query(
                'INSERT INTO payment_status_history (transaction_id, previous_status, new_status, payment_method, amount, proof_id, acting_account_id) VALUES (:transaction_id, :previous_status, :new_status, :payment_method, :amount, :proof_id, :acting_account_id)',
                [
                    'transaction_id' => $transactionId,
                    'previous_status' => $previousStatus,
                    'new_status' => 'Paid',
                    'payment_method' => $transaction['payment_method'],
                    'amount' => $transaction['final_seller_amount'],
                    'proof_id' => $proofId,
                    'acting_account_id' => $junkshopAccountId,
                ]
            );
            $this->db->commit();

            return ['success' => true, 'message' => 'Payment marked as Paid and recorded successfully.'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Payment confirmation error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment confirmation could not be completed.'];
        }
    }

    public function streamProof(int $accountId, string $role, int $proofId): void
    {
        $sql = 'SELECT pp.storage_path, pp.mime_type, pp.original_filename FROM payment_proofs pp JOIN transactions t ON t.id = pp.transaction_id WHERE pp.id = :proof_id';
        $params = ['proof_id' => $proofId];
        if ($role === 'seller') {
            $sql .= ' AND pp.seller_account_id = :account_id';
            $params['account_id'] = $accountId;
        } elseif ($role === 'junkshop') {
            $sql .= ' AND t.junkshop_id = :account_id';
            $params['account_id'] = $accountId;
        } else {
            throw new RuntimeException('Payment proof access denied.');
        }

        $proof = $this->db->query($sql, $params)->fetch();
        if (!$proof) {
            http_response_code(404);
            return;
        }

        $path = $this->proofDirectory . DIRECTORY_SEPARATOR . basename((string) $proof['storage_path']);
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $proof['mime_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode((string) $proof['original_filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function getTransactionForSeller(int $sellerAccountId, int $transactionId): ?array
    {
        $row = $this->db->query(
            'SELECT id, payment_method, payment_status FROM transactions WHERE id = :transaction_id AND seller_id = :seller_id LIMIT 1',
            ['transaction_id' => $transactionId, 'seller_id' => $sellerAccountId]
        )->fetch();
        return $row ?: null;
    }

    private function getTransactionForJunkshop(int $junkshopAccountId, int $transactionId): ?array
    {
        $row = $this->db->query(
            'SELECT id, pickup_request_id, payment_method, payment_status, final_seller_amount FROM transactions WHERE id = :transaction_id AND junkshop_id = :junkshop_id LIMIT 1',
            ['transaction_id' => $transactionId, 'junkshop_id' => $junkshopAccountId]
        )->fetch();
        return $row ?: null;
    }

    private function validateProofFile(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'A GCash payment screenshot is required.'];
        }
        if (!isset($file['size']) || (int) $file['size'] <= 0 || (int) $file['size'] > self::MAX_PROOF_SIZE_BYTES) {
            return ['success' => false, 'message' => 'Payment proof must be 3 MB or smaller.'];
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['success' => false, 'message' => 'The payment proof upload is invalid.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ['image/jpeg', 'image/png'], true) || @getimagesize($file['tmp_name']) === false) {
            return ['success' => false, 'message' => 'Payment proof must be a valid JPEG or PNG image.'];
        }

        return ['success' => true, 'mime_type' => $mimeType];
    }
}
