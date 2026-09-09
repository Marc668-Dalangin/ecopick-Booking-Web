(function () {
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    }

    function clearNotificationState() {
        const keys = [];
        for (let i = 0; i < localStorage.length; i += 1) {
            const key = localStorage.key(i);
            if (key && key.indexOf('ecopick_notif_state_') === 0) {
                keys.push(key);
            }
        }

        keys.forEach((key) => localStorage.removeItem(key));
    }

    function storageKeyForDate(dateValue) {
        return 'ecopick_notif_state_' + String(dateValue);
    }

    function applyGroupState(group, shouldOpen) {
        const toggle = group && group.querySelector('.notification-toggle');
        const collapse = group && group.querySelector('.collapse');
        const icon = group && group.querySelector('.toggle-icon');

        if (!toggle || !collapse || !icon) return;

        collapse.classList.toggle('show', shouldOpen);
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        icon.classList.toggle('bi-chevron-up', shouldOpen);
        icon.classList.toggle('bi-chevron-down', !shouldOpen);

        const dateValue = group.dataset.dateGroup;
        if (dateValue) {
            localStorage.setItem(storageKeyForDate(dateValue), shouldOpen ? 'open' : 'closed');
        }
    }

    function restoreGroupState(group) {
        const dateValue = group && group.dataset.dateGroup;
        if (!dateValue) return;

        const savedState = localStorage.getItem(storageKeyForDate(dateValue));
        const hasUnread = Array.from(group.querySelectorAll('.notification-item')).some(item => item.dataset.isUnread === '1');

        if (savedState === 'open') {
            applyGroupState(group, true);
            return;
        }

        if (savedState === 'closed') {
            applyGroupState(group, false);
            return;
        }

        applyGroupState(group, hasUnread);
    }

    function restoreAllGroups() {
        document.querySelectorAll('.notification-date-group').forEach(restoreGroupState);
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

    function handleNotificationRender() {
        requestAnimationFrame(function () {
            document.querySelectorAll('.notification-date-group').forEach((group) => {
                const dateValue = group.dataset.dateGroup;
                if (!dateValue) return;

                const savedState = localStorage.getItem(storageKeyForDate(dateValue));
                const hasUnread = Array.from(group.querySelectorAll('.notification-item')).some(item => item.dataset.isUnread === '1');

                if (savedState === 'open') {
                    applyGroupState(group, true);
                    return;
                }

                if (savedState === 'closed') {
                    applyGroupState(group, false);
                    return;
                }

                applyGroupState(group, hasUnread);
            });
        });
    }

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.notification-toggle');
        if (!toggle) return;

        const group = toggle.closest('.notification-date-group');
        if (!group) return;

        event.preventDefault();
        const isOpen = toggle.getAttribute('aria-expanded') === 'true' || group.querySelector('.collapse')?.classList.contains('show');
        applyGroupState(group, !isOpen);
    });

    document.addEventListener('shown.bs.collapse', function (event) {
        const group = event.target.closest('.notification-date-group');
        if (!group) return;
        const dateValue = group.dataset.dateGroup;
        if (dateValue) localStorage.setItem(storageKeyForDate(dateValue), 'open');
    });

    document.addEventListener('hidden.bs.collapse', function (event) {
        const group = event.target.closest('.notification-date-group');
        if (!group) return;
        const dateValue = group.dataset.dateGroup;
        if (dateValue) localStorage.setItem(storageKeyForDate(dateValue), 'closed');
    });

    window.addEventListener('DOMContentLoaded', function () {
        if (sessionStorage.getItem('ecopick_reset_notif_state') === '1') {
            clearNotificationState();
            sessionStorage.removeItem('ecopick_reset_notif_state');
        }

        restoreAllGroups();

        if (!window.EcoPickLiveUpdates || !document.querySelector('[data-notification-count]')) return;

        window.EcoPickLiveUpdates.startPolling({
            key: 'global-notifications',
            url: window.ecopickNotificationUrl || '/api/notifications/fetch-latest.php',
            interval: 3000,
            onSuccess: function (payload) {
                const data = payload.data || {};
                updateBadges(Number(data.unread_count || 0));
                injectUnreadNotifications(data.notifications || []);
                handleNotificationRender();
            }
        });
    });
})();