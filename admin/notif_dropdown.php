<?php
// notif_dropdown.php — shared notification bell dropdown partial
// Include this just before </body> on every admin page.
// The page MUST already have Phosphor Icons loaded.
?>
<style>
    .notif-wrapper {
        position: relative;
    }
    .notif-panel {
        display: none;
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: 360px;
        background: #FFFFFF;
        border-radius: 18px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18), 0 4px 16px rgba(15, 23, 42, 0.08);
        z-index: 2000;
        overflow: hidden;
        border: 1px solid #EEF0F5;
        animation: notifFadeIn 0.18s ease;
    }
    .notif-panel.open {
        display: block;
    }
    @keyframes notifFadeIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .notif-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px 12px;
        border-bottom: 1px solid #F0F2F5;
    }
    .notif-panel-title {
        font-size: 15px;
        font-weight: 800;
        color: #0F172A;
    }
    .notif-unread-label {
        font-size: 12px;
        font-weight: 700;
        color: #6A5AE0;
        background: rgba(106, 90, 224, 0.1);
        padding: 3px 10px;
        border-radius: 999px;
    }
    .notif-list {
        max-height: 340px;
        overflow-y: auto;
    }
    .notif-item {
        display: flex;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid #F8FAFC;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none;
        color: inherit;
    }
    .notif-item:hover {
        background: #F8FAFC;
    }
    .notif-item.unread {
        background: rgba(106, 90, 224, 0.04);
        border-left: 3px solid #6A5AE0;
    }
    .notif-item.unread:hover {
        background: rgba(106, 90, 224, 0.08);
    }
    .notif-dot {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6A5AE0, #8B5CF6);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
        font-size: 16px;
    }
    .notif-item.unread .notif-dot {
        background: linear-gradient(135deg, #F59E0B, #EF4444);
    }
    .notif-body {
        flex: 1;
        min-width: 0;
    }
    .notif-item-title {
        font-size: 13px;
        font-weight: 700;
        color: #0F172A;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .notif-item-msg {
        font-size: 12px;
        color: #64748B;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .notif-item-time {
        font-size: 11px;
        color: #94A3B8;
        margin-top: 4px;
        font-weight: 600;
    }
    .notif-empty {
        padding: 32px 20px;
        text-align: center;
        color: #94A3B8;
        font-size: 13px;
    }
    .notif-empty i {
        font-size: 32px;
        display: block;
        margin-bottom: 8px;
        opacity: 0.5;
    }
    .notif-panel-footer {
        padding: 12px 20px;
        border-top: 1px solid #F0F2F5;
        text-align: center;
    }
    .notif-view-all {
        display: inline-block;
        font-size: 13px;
        font-weight: 700;
        color: #6A5AE0;
        text-decoration: none;
        padding: 8px 20px;
        border-radius: 999px;
        transition: background 0.15s;
    }
    .notif-view-all:hover {
        background: rgba(106, 90, 224, 0.08);
    }
    /* Badge styles (canonical, shared) */
    .icon-badge-wrap {
        position: relative;
    }
    .icon-badge {
        position: absolute;
        top: -4px;
        right: -2px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #F43F5E;
        color: white;
        font-size: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        box-shadow: 0 2px 8px rgba(244, 63, 94, 0.4);
    }
</style>

<!-- Page Loader Overlay (shared) -->
<div class="page-loader" id="pageLoader" aria-hidden="true">
    <div class="page-loader-card" role="status" aria-live="polite">
        <span class="page-loader-spinner" aria-hidden="true"></span>
    </div>
</div>

<!-- Notification Dropdown Panel (shared partial) -->
<div class="notif-wrapper" id="notifWrapperGlobal" style="position:relative;">
    <div class="notif-panel" id="notifDropdownPanel">
        <div class="notif-panel-header">
            <span class="notif-panel-title">Notifications</span>
            <span class="notif-unread-label" id="notifUnreadLabel">0 unread</span>
        </div>
        <div class="notif-list" id="notifDropdownList">
            <div class="notif-empty"><i class="ph ph-bell-slash"></i>No notifications yet.</div>
        </div>
        <div class="notif-panel-footer">
            <a href="notifications.php" class="notif-view-all">View All Notifications</a>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── helpers ── */
    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)   return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function notifIcon(type) {
        const map = {
            loan_request:     'ph-file-text',
            loan_approved:    'ph-check-circle',
            loan_rejected:    'ph-x-circle',
            reloan_request:   'ph-arrows-clockwise',
            reloan_approved:  'ph-check-circle',
            reloan_rejected:  'ph-x-circle',
            payment_recorded: 'ph-receipt',
            overdue_payment:  'ph-warning',
            system_message:   'ph-info',
        };
        return 'ph ' + (map[type] || 'ph-bell');
    }

    /* ── DOM refs ── */
    let bellBtn      = null;
    let panel        = null;
    let list         = null;
    let unreadLabel  = null;
    let badge        = null;

    function initRefs() {
        bellBtn     = document.getElementById('notifBellBtn');
        panel       = document.getElementById('notifDropdownPanel');
        list        = document.getElementById('notifDropdownList');
        unreadLabel = document.getElementById('notifUnreadLabel');
        badge       = document.getElementById('topNotificationBadge');

        // Move the dropdown panel to be positioned relative to the bell button
        if (bellBtn && panel) {
            const wrapper = document.getElementById('notifWrapperGlobal');
            // Insert wrapper right after the bell button in the DOM
            bellBtn.parentNode.insertBefore(wrapper, bellBtn.nextSibling);
            wrapper.insertBefore(bellBtn, wrapper.firstChild);
        }
    }

    /* ── badge update ── */
    function updateBadge(count) {
        if (!bellBtn) return;
        let b = document.getElementById('topNotificationBadge');
        if (count > 0) {
            if (!b) {
                b = document.createElement('span');
                b.id = 'topNotificationBadge';
                b.className = 'icon-badge';
                bellBtn.appendChild(b);
            }
            b.textContent = count > 99 ? '99+' : count;
        } else {
            if (b) b.remove();
        }
        if (unreadLabel) {
            unreadLabel.textContent = count + ' unread';
        }
    }

    /* ── render ── */
    function renderList(notifications) {
        if (!list) return;
        if (!notifications || notifications.length === 0) {
            list.innerHTML = '<div class="notif-empty"><i class="ph ph-bell-slash"></i>No notifications yet.</div>';
            return;
        }
        list.innerHTML = notifications.map(function (n) {
            const isUnread = n.status === 'unread';
            return '<div class="notif-item ' + (isUnread ? 'unread' : '') + '" data-id="' + n.id + '" data-unread="' + (isUnread ? '1' : '0') + '">' +
                '<div class="notif-dot"><i class="' + notifIcon(n.type) + '"></i></div>' +
                '<div class="notif-body">' +
                    '<div class="notif-item-title">' + escHtml(n.title) + '</div>' +
                    '<div class="notif-item-msg">' + escHtml(n.message) + '</div>' +
                    '<div class="notif-item-time">' + timeAgo(n.created_at) + '</div>' +
                '</div>' +
            '</div>';
        }).join('');

        // attach click handlers
        list.querySelectorAll('.notif-item').forEach(function (el) {
            el.addEventListener('click', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                const wasUnread = this.getAttribute('data-unread') === '1';
                if (wasUnread) {
                    markRead(id);
                }
                // Close panel after click
                closePanel();
            });
        });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── fetch ── */
    async function fetchNotifications() {
        try {
            const res  = await fetch('get_recent_notifications.php', { cache: 'no-store' });
            const data = await res.json();
            if (data.status !== 'success') return;
            renderList(data.notifications);
            updateBadge(data.unread_count);
        } catch (e) { /* silent */ }
    }

    /* ── mark read ── */
    async function markRead(id) {
        try {
            const fd = new FormData();
            fd.append('id', id);
            const res  = await fetch('mark_notification_read.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.status === 'success') {
                updateBadge(data.unread_count);
                // Update item visually
                const el = list.querySelector('[data-id="' + id + '"]');
                if (el) {
                    el.classList.remove('unread');
                    el.setAttribute('data-unread', '0');
                }
            }
        } catch (e) { /* silent */ }
    }

    /* ── panel toggle ── */
    function openPanel() {
        if (!panel) return;
        panel.classList.add('open');
        fetchNotifications();
    }

    function closePanel() {
        if (panel) panel.classList.remove('open');
    }

    /* ── init ── */
    document.addEventListener('DOMContentLoaded', function () {
        initRefs();

        // Page enter animation
        document.body.classList.add('page-enter');
        window.setTimeout(function () {
            document.body.classList.remove('page-enter');
        }, 320);

        // Sidebar navigation transition + loader
        const loader = document.getElementById('pageLoader');
        function showLoader() {
            if (!loader) return;
            loader.classList.add('active');
            loader.setAttribute('aria-hidden', 'false');
        }
        function hideLoader() {
            if (!loader) return;
            loader.classList.remove('active');
            loader.setAttribute('aria-hidden', 'true');
        }
        hideLoader();

        // Attach to sidebar links (same-origin navigation only)
        document.querySelectorAll('.sidebar .nav-links a[href]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                const href = this.getAttribute('href') || '';
                if (!href || href === '#' || href.startsWith('javascript:')) return;
                // Allow ctrl/cmd click or middle-click to open new tab
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
                // Ignore if same page
                const current = (location.pathname || '').split('/').pop();
                if (href === current) return;

                e.preventDefault();
                showLoader();
                document.body.classList.add('page-exit');
                window.setTimeout(function () {
                    window.location.href = href;
                }, 220);
            });
        });

        if (bellBtn) {
            bellBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (panel && panel.classList.contains('open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            });
        }

        // Close on outside click
        document.addEventListener('click', function (e) {
            const wrapper = document.getElementById('notifWrapperGlobal');
            if (wrapper && !wrapper.contains(e.target)) {
                closePanel();
            }
        });

        // Initial load + poll every 30s
        fetchNotifications();
        setInterval(fetchNotifications, 30000);
    });
}());
</script>
