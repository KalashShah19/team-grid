/**
 * Team-Grid — Core JS
 * Handles notifications, user menu, common utilities
 */

(function() {
    'use strict';

    // ============================================================
    // NOTIFICATION SYSTEM
    // ============================================================

    const notifBell = document.getElementById('notifBell');
    const notifPanel = document.getElementById('notifPanel');
    const notifBadge = document.getElementById('notifBadge');
    const notifList = document.getElementById('notifList');
    const markAllBtn = document.getElementById('markAllRead');

    let notifOpen = false;
    let notifPolling = null;

    function toggleNotifPanel() {
        notifOpen = !notifOpen;
        notifPanel?.classList.toggle('open', notifOpen);
        if (notifOpen) fetchNotifications();
    }

    if (notifBell) {
        notifBell.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleNotifPanel();
            // Close user menu if open
            document.getElementById('userMenu')?.classList.remove('open');
        });
    }

    let knownNotifIds = null;

    async function fetchNotifications() {
        if (!window.TM?.userId) return;
        try {
            const res = await fetch(`${TM.baseUrl}/api/notifications.php?action=list`);
            const data = await res.json();
            if (data.success) {
                const notifications = data.notifications || [];
                const currentIds = new Set(notifications.map(n => n.id));

                if (knownNotifIds !== null) {
                    notifications.forEach(n => {
                        if (!n.is_read && !knownNotifIds.has(n.id)) {
                            // Live popup alert!
                            TM.toast(n.message, 'info');
                            window.dispatchEvent(new CustomEvent('tm:notification', { detail: n }));
                        }
                    });
                }
                knownNotifIds = currentIds;

                if (notifOpen) renderNotifications(notifications);
                updateBadge(data.unread_count);
            }
        } catch (e) { /* silent */ }
    }

    function renderNotifications(notifications) {
        if (!notifList) return;
        if (!notifications.length) {
            notifList.innerHTML = `
                <div class="notif-empty">
                    <div class="notif-empty-icon">🔕</div>
                    <p>No notifications yet</p>
                </div>`;
            return;
        }

        notifList.innerHTML = notifications.map(n => `
            <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" data-link="${n.link || ''}">
                <div class="notif-item-content">
                    <div class="notif-item-text">${escapeHtml(n.message)}</div>
                    <div class="notif-item-time">${timeAgo(n.created_at)}</div>
                </div>
                <div class="notif-item-action">
                    ${!n.is_read ? `<button class="notif-tick" data-notif-id="${n.id}" title="Mark as read">✓</button>` : ''}
                </div>
            </div>
        `).join('');

        // Click handlers
        notifList.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                if (e.target.closest('.notif-tick')) return;
                const link = item.dataset.link;
                const notifId = item.dataset.id;
                // Mark as read on click (only if unread)
                if (notifId && item.classList.contains('unread')) {
                    await markRead(notifId);
                    item.classList.remove('unread');
                    const tick = item.querySelector('.notif-tick');
                    if (tick) tick.remove();
                    updateBadge(Math.max(0, parseInt(notifBadge?.textContent || '0', 10) - 1));
                }
                if (link) window.location.href = TM.baseUrl + '/' + link;
            });
        });

        notifList.querySelectorAll('.notif-tick').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.notifId;
                await markRead(id);
                fetchNotifications();
            });
        });
    }

    function updateBadge(count) {
        if (!notifBadge) return;
        if (count > 0) {
            notifBadge.textContent = count > 99 ? '99+' : count;
            notifBadge.classList.remove('hidden');
        } else {
            notifBadge.classList.add('hidden');
        }
    }

    async function markRead(notifId) {
        try {
            await fetch(`${TM.baseUrl}/api/notifications.php?action=mark_read&id=${notifId}`);
        } catch (e) { /* silent */ }
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', async () => {
            try {
                await fetch(`${TM.baseUrl}/api/notifications.php?action=mark_all_read`);
                fetchNotifications();
            } catch (e) { /* silent */ }
        });
    }

    // Start polling for notifications
    if (window.TM?.userId && TM.pollInterval) {
        fetchNotifications();
        notifPolling = setInterval(fetchNotifications, TM.pollInterval);
    }

    // ============================================================
    // USER MENU
    // ============================================================

    const userAvatar = document.getElementById('userAvatar');
    const userMenu = document.getElementById('userMenu');

    if (userAvatar && userMenu) {
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('open');
            // Close notif panel if open
            if (notifOpen) {
                notifOpen = false;
                notifPanel?.classList.remove('open');
            }
        });
    }

    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
        if (userMenu && !e.target.closest('#userMenu') && !e.target.closest('#userAvatar')) {
            userMenu.classList.remove('open');
        }
        if (notifPanel && notifOpen && !e.target.closest('#notifPanel') && !e.target.closest('#notifBell')) {
            notifOpen = false;
            notifPanel.classList.remove('open');
        }
    });

    // ============================================================
    // UTILITY FUNCTIONS (global)
    // ============================================================

    window.TM = window.TM || {};

    TM.api = async function(endpoint, options = {}) {
        const url = `${TM.baseUrl}/api/${endpoint}`;
        const config = {
            headers: { 'Content-Type': 'application/json' },
            ...options,
        };
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        const res = await fetch(url, config);
        return res.json();
    };

    TM.toast = function(message, type = 'success') {
        Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    };

    TM.confirm = function(title, text = '', icon = 'warning') {
        return Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
        });
    };

    TM.loading = function(title = 'Loading...') {
        Swal.fire({
            title: title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });
    };

    TM.stopLoading = function() {
        Swal.close();
    };

    // Escape HTML
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    TM.escapeHtml = escapeHtml;

    // Time ago helper
    function timeAgo(datetime) {
        const diff = Math.floor((Date.now() - new Date(datetime).getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return new Date(datetime).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
    }
    TM.timeAgo = timeAgo;

    // Format date
    TM.formatDate = function(datetime, options) {
        if (!datetime) return '';
        const d = new Date(datetime);
        if (isNaN(d.getTime())) return datetime;
        return d.toLocaleDateString('en-IN', options || { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
    };

    // Debounce
    TM.debounce = function(fn, ms = 300) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    };

})();
