<?php

if (Auth::check() && Auth::userRole() === 'junkshop') {
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptName = basename($scriptPath);
    $allowedScripts = ['renewal.php', 'partnership_renewal.php', 'logout.php'];

    if (!in_array($scriptName, $allowedScripts, true)) {
        $profile = Database::getInstance()->query(
            "SELECT jp.approval_status, jp.partnership_expires_at,
                    EXISTS(
                        SELECT 1 FROM partnership_renewals pr
                        WHERE pr.junkshop_account_id = jp.account_id
                          AND pr.status = 'Approved'
                          AND pr.expiry_date >= CURRENT_TIMESTAMP
                    ) AS has_active_approved_plan
             FROM junkshop_profiles jp
             WHERE jp.account_id = :account_id
             LIMIT 1",
            ['account_id' => Auth::userId()]
        )->fetch();

        $hasActiveSubscription = $profile
            && $profile['approval_status'] === 'approved'
            && !empty($profile['partnership_expires_at'])
            && strtotime((string) $profile['partnership_expires_at']) >= time()
            && (int) $profile['has_active_approved_plan'] === 1;

        if (!$hasActiveSubscription) {
            $_SESSION['subscription_gate_warning'] = 'Your account requires an active partnership plan. Please choose a subscription plan below to unlock full portal access.';
            header('Location: ' . APP_URL . '/user-junkshop/renewal.php');
            exit;
        }
    }
}