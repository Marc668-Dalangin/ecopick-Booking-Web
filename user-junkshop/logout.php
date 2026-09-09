<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Check if user is logged in
if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

// Logout user
Auth::logout();
?>
<script>
    sessionStorage.setItem('ecopick_reset_notif_state', '1');
    for (let i = localStorage.length - 1; i >= 0; i -= 1) {
        const key = localStorage.key(i);
        if (key && key.indexOf('ecopick_notif_state_') === 0) {
            localStorage.removeItem(key);
        }
    }
    window.location.href = '<?php echo APP_URL; ?>/user-junkshop/login.php?logout=1';
</script>
<?php
exit;
