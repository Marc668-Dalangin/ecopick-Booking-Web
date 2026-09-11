(function () {
    const badges = document.querySelectorAll('#matched-requests-badge, [data-matched-requests-badge]');
    const endpoint = window.ecopickPendingRequestsCountUrl;

    if (!badges.length || !endpoint) {
        return;
    }

    function updateBadges(count) {
        const safeCount = Math.max(0, Number.parseInt(count, 10) || 0);
        badges.forEach(function (badge) {
            badge.textContent = String(safeCount);
            badge.style.display = safeCount > 0 ? 'inline-block' : 'none';
        });
    }

    function fetchPendingRequestCount() {
        fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return { response: response, payload: payload };
                });
            })
            .then(function (result) {
                if (result.payload.session_expired && result.payload.redirect) {
                    window.location.href = result.payload.redirect;
                    return;
                }

                if (result.response.ok && result.payload.success) {
                    updateBadges(result.payload.count);
                }
            })
            .catch(function () {
                // Keep the last known count when a transient poll fails.
            });
    }

    fetchPendingRequestCount();
    window.setInterval(fetchPendingRequestCount, 5000);
})();