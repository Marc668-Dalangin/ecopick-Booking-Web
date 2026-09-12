(function () {
    const countUrl = window.EcoPickAdmin?.pendingCountUrl;

    window.fetchPendingJunkshopCount = async function fetchPendingJunkshopCount() {
        if (!countUrl) return;

        try {
            const response = await fetch(countUrl, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const payload = await response.json();
            if (!response.ok || typeof payload.count === 'undefined') {
                throw new Error(payload.message || 'Invalid pending count response.');
            }
            const badges = document.querySelectorAll('#pending-junkshop-badge, [data-pending-junkshop-badge]');
            const count = Number(payload.count || 0);
            badges.forEach(function (badge) {
                badge.textContent = count > 0 ? String(count) : '';
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            });
        } catch (error) {
            console.error('Unable to refresh pending junkshop count:', error);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.fetchPendingJunkshopCount();
        window.setInterval(window.fetchPendingJunkshopCount, 5000);
    });
})();