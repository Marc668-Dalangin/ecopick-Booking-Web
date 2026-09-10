(function () {
    let lastUnreadCount = null;

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

    function decrementDateBadge(group) {
        const badge = group && group.querySelector('[data-date-unread-badge]');
        if (!badge) return;

        const unreadCount = Math.max(0, Number.parseInt(badge.dataset.unreadCount || badge.textContent, 10) - 1);
        badge.dataset.unreadCount = String(unreadCount);
        badge.textContent = unreadCount + ' unread';
        badge.classList.toggle('d-none', unreadCount === 0);
    }

    function persistNotificationRead(notificationId) {
        const csrfToken = window.ecopickCsrfToken || '';
        fetch(window.ecopickMarkReadUrl || '/api/notifications/mark-read.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ notification_id: Number(notificationId) })
        }).catch(() => {});
    }

    function markNotificationRead(item) {
        if (!item || item.dataset.isUnread !== '1') return;

        const notificationId = item.dataset.notificationId;
        if (!notificationId) return;

        item.dataset.isUnread = '0';
        item.classList.remove('notification-unread', 'bg-light');
        item.querySelectorAll('.notification-unread-indicator').forEach(indicator => indicator.remove());
        decrementDateBadge(item.closest('.notification-date-group'));

        const currentCount = lastUnreadCount === null
            ? Number(document.querySelector('[data-notification-count]')?.textContent || 0)
            : lastUnreadCount;
        lastUnreadCount = Math.max(0, currentCount - 1);
        updateBadges(lastUnreadCount);
        persistNotificationRead(notificationId);
    }

    function dateKeyFromValue(value) {
        const date = new Date(String(value || '').replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '';

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function dateLabel(dateKey) {
        const today = dateKeyFromValue(new Date());
        const yesterdayDate = new Date();
        yesterdayDate.setDate(yesterdayDate.getDate() - 1);
        const yesterday = dateKeyFromValue(yesterdayDate);

        if (dateKey === today) return 'Today';
        if (dateKey === yesterday) return 'Yesterday';

        const date = new Date(dateKey + 'T00:00:00');
        return Number.isNaN(date.getTime())
            ? dateKey
            : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function openNotificationDropdown() {
        const toggle = document.querySelector('[data-notification-toggle]');
        if (!toggle || !window.bootstrap?.Dropdown) return;

        window.bootstrap.Dropdown.getOrCreateInstance(toggle).show();
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

    function renderUnreadNotifications(notifications) {
        const menu = document.querySelector('[data-notification-menu]');
        if (!menu || !Array.isArray(notifications)) return;

        const grouped = notifications.reduce((groups, notification) => {
            const dateKey = dateKeyFromValue(notification.created_at) || dateKeyFromValue(new Date());
            if (!groups[dateKey]) groups[dateKey] = [];
            groups[dateKey].push(notification);
            return groups;
        }, {});

        const groupMarkup = Object.keys(grouped).sort().reverse().map((dateKey, index) => {
            const groupNotifications = grouped[dateKey];
            const groupId = 'live-notification-group-' + dateKey.replace(/[^0-9]/g, '');
            const unreadCount = groupNotifications.length;
            const isCurrentDate = dateKey === dateKeyFromValue(new Date());
            const shouldOpen = isCurrentDate && (lastUnreadCount === null || unreadCount > 0);
            const items = groupNotifications.map((notification) => '<li class="notification-dropdown-item notification-unread bg-light" data-notification-id="' + escapeHtml(notification.id) + '" data-is-unread="1"><a class="dropdown-item" href="' + escapeHtml(notification.link_url || '#') + '"><strong class="d-block">' + escapeHtml(notification.title) + '</strong><span class="small text-muted d-block">' + escapeHtml(notification.message) + '</span><span class="small text-muted">' + formatNotificationTimestamp(notification.created_at) + '</span></a></li>').join('');

            return '<li class="notification-date-group" data-date-group="' + escapeHtml(dateKey) + '"><button type="button" class="notification-toggle dropdown-item d-flex align-items-center justify-content-between gap-2 fw-semibold" data-bs-toggle="collapse" data-bs-target="#' + groupId + '" aria-expanded="' + (shouldOpen ? 'true' : 'false') + '"><span>' + escapeHtml(dateLabel(dateKey)) + '</span><span class="d-flex align-items-center gap-2"><span class="badge bg-danger rounded-pill" data-date-unread-badge data-unread-count="' + unreadCount + '">' + unreadCount + ' unread</span><i class="bi bi-chevron-' + (shouldOpen ? 'up' : 'down') + ' toggle-icon"></i></span></button><div id="' + groupId + '" class="collapse ' + (shouldOpen ? 'show' : '') + '"><ul class="list-unstyled mb-0">' + items + '</ul></div></li>';
        }).join('');

        const empty = menu.querySelector('[data-notification-empty]');
        menu.querySelectorAll('.notification-date-group').forEach(group => group.remove());
        if (empty) empty.classList.toggle('d-none', notifications.length > 0);
        menu.insertAdjacentHTML('beforeend', groupMarkup);
    }

    function expandCurrentDateGroup() {
        const group = document.querySelector('.notification-date-group[data-date-group="' + dateKeyFromValue(new Date()) + '"]');
        if (group) applyGroupState(group, true);
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
        const notificationItem = event.target.closest('.notification-item, .notification-dropdown-item');
        if (notificationItem) {
            event.stopPropagation();
            markNotificationRead(notificationItem);
            return;
        }

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
                const currentUnreadCount = Number(data.unread_count || 0);
                const hasNewNotification = lastUnreadCount !== null && currentUnreadCount > lastUnreadCount;

                updateBadges(currentUnreadCount);
                renderUnreadNotifications(data.notifications || []);
                handleNotificationRender();

                if (hasNewNotification) {
                    openNotificationDropdown();
                    expandCurrentDateGroup();
                }

                lastUnreadCount = currentUnreadCount;
            }
        });
    });
})();