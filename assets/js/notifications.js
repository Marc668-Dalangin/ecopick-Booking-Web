(function () {
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    }

    function updateNotificationDateGroups() {
        document.querySelectorAll('.notification-date-group').forEach((group) => {
            const toggle = group.querySelector('.notification-toggle');
            const collapse = group.querySelector('.collapse');
            const icon = group.querySelector('.toggle-icon');
            if (!toggle || !collapse || !icon) return;

            collapse.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
            icon.classList.remove('bi-chevron-up');
            icon.classList.add('bi-chevron-down');
        });
    }

    function updateBadges(count) {
        document.querySelectorAll('[data-notification-count]').forEach((badge) => {
            badge.textContent = String(count);
            badge.classList.toggle('d-none', count < 1);
        });
    }

    function formatNotificationTimestamp(value) {
        const rawValue = String(value || '');
        const normalizedValue = rawValue.includes('T') ? rawValue : rawValue.replace(' ', 'T') + 'Z';
        const timestamp = new Date(normalizedValue);
        if (Number.isNaN(timestamp.getTime())) return '';

        const date = timestamp.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const time = timestamp.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        return date + ' &bull; <strong>' + escapeHtml(time) + '</strong>';
    }

    function injectUnreadNotifications(notifications) {
        const menu = document.querySelector('[data-notification-menu]');
        if (!menu || !Array.isArray(notifications)) return;

        const knownIds = new Set(Array.from(menu.querySelectorAll('[data-notification-id]')).map(item => item.dataset.notificationId));
        const fresh = notifications.filter(notification => !knownIds.has(String(notification.id)));
        fresh.reverse().forEach((notification) => {
            const item = document.createElement('li');
            item.dataset.notificationId = String(notification.id);
            item.innerHTML = '<a class="dropdown-item notification-dropdown-item" href="' + escapeHtml(notification.link_url || '#') + '"><strong class="d-block">' + escapeHtml(notification.title) + '</strong><span class="small text-muted d-block">' + escapeHtml(notification.message) + '</span><span class="small text-muted">' + formatNotificationTimestamp(notification.created_at) + '</span></a>';
            menu.insertBefore(item, menu.querySelector('[data-notification-empty]') || null);
        });

        const empty = menu.querySelector('[data-notification-empty]');
        if (empty) empty.classList.toggle('d-none', notifications.length > 0);
    }

    window.addEventListener('DOMContentLoaded', function () {
        updateNotificationDateGroups();

        if (!window.EcoPickLiveUpdates || !document.querySelector('[data-notification-count]')) return;
        window.EcoPickLiveUpdates.startPolling({
            key: 'global-notifications',
            url: window.ecopickNotificationUrl || '/api/notifications/fetch-latest.php',
            interval: 3000,
            onSuccess: function (payload) {
                const data = payload.data || {};
                updateBadges(Number(data.unread_count || 0));
                injectUnreadNotifications(data.notifications || []);
            }
        });
    });
})();