(function () {
    const countUrl = window.EcoPickAdmin?.pendingCountUrl;
    let isPollingActive = false;

    window.fetchPendingJunkshopCount = async function fetchPendingJunkshopCount() {
        if (!countUrl || document.hidden || isPollingActive) return;

        isPollingActive = true;

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
        } finally {
            isPollingActive = false;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.fetchPendingJunkshopCount();
        window.setInterval(window.fetchPendingJunkshopCount, 12000);
    });
})();