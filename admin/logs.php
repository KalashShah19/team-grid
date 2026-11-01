<?php
/**
 * Admin Logs Page
 * Filterable audit logs for Logins, System Actions (polls, matches, drafts, payments), and Errors.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Audit Logs';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <div class="d-flex align-center gap-xs">
                <a href="<?php echo BASE_URL; ?>/admin" class="btn btn-secondary btn-xs">← Admin</a>
                <h1 class="page-title" style="margin-bottom:0;">📋 System Audit Logs</h1>
            </div>
            <p class="page-subtitle">Security events, authentication records, and operational audit trail</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="loadLogs()">
            🔄 Refresh Logs
        </button>
    </div>

    <!-- Log Type Tabs -->
    <div class="tabs mb-md" id="logTabs">
        <button class="tab active" data-type="actions">⚡ System Actions</button>
        <button class="tab" data-type="logins">🔐 Login History</button>
        <button class="tab" data-type="errors">⚠️ System Errors</button>
    </div>

    <!-- Logs Table Card -->
    <div class="card overflow-hidden">
        <div class="table-container">
            <table class="table" id="logsTable">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Event / Type</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr><td colspan="5" class="text-center py-xl text-muted">Loading audit entries...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentLogType = 'actions';
    const tableBody = document.getElementById('logsTableBody');

    document.querySelectorAll('#logTabs .tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#logTabs .tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentLogType = tab.dataset.type;
            loadLogs();
        });
    });

    async function loadLogs() {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-md text-muted"><div class="loading-spinner"></div></td></tr>';
        try {
            const res = await TM.api(`admin.php?action=logs&type=${currentLogType}`);
            if (res.success) {
                renderLogs(res.logs);
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-md text-red">${res.message}</td></tr>`;
            }
        } catch (e) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-md text-red">Failed to load logs</td></tr>';
        }
    }

    function renderLogs(logs) {
        if (!logs.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-xl text-muted">No log events recorded for this category</td></tr>';
            return;
        }

        tableBody.innerHTML = logs.map(l => {
            const timeStr = l.created_at ? TM.formatDate(l.created_at, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit'}) : 'N/A';
            const userStr = l.user_name ? TM.escapeHtml(l.user_name) : (l.user_id ? l.user_id.substring(0, 8) : 'System');

            let typeBadge = '';
            if (currentLogType === 'logins') {
                typeBadge = l.success 
                    ? '<span class="badge badge-success">✓ Login Success</span>'
                    : '<span class="badge badge-danger">✕ Login Failed</span>';
            } else if (currentLogType === 'errors') {
                typeBadge = '<span class="badge badge-danger">⚠️ Error</span>';
            } else {
                typeBadge = `<span class="badge badge-primary">${TM.escapeHtml(l.type || 'action')}</span>`;
            }

            let detailsStr = '';
            if (l.details) {
                detailsStr = `<code class="text-xs" style="color:var(--text-secondary);">${TM.escapeHtml(JSON.stringify(l.details))}</code>`;
            } else if (l.message) {
                detailsStr = `<span class="text-xs text-red">${TM.escapeHtml(l.message)}</span>`;
            }

            return `
                <tr>
                    <td><span class="text-xs text-muted fw-mono">${timeStr}</span></td>
                    <td>${typeBadge}</td>
                    <td><span class="fw-semibold text-sm">👤 ${userStr}</span></td>
                    <td><span class="text-xs text-muted fw-mono">${TM.escapeHtml(l.ip || '127.0.0.1')}</span></td>
                    <td>${detailsStr}</td>
                </tr>
            `;
        }).join('');
    }

    window.loadLogs = loadLogs;
    loadLogs();
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
