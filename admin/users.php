<?php
/**
 * Admin Users Data Page
 * Shows all player names, DOBs in table format, role management, and safe deletion
 * (Users referenced in matches or polls CANNOT be deleted).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Squad Registry';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <div class="d-flex align-center gap-xs">
                <a href="<?php echo BASE_URL; ?>/admin" class="btn btn-secondary btn-xs">← Admin</a>
                <h1 class="page-title" style="margin-bottom:0;">👥 Squad Registry & DOBs</h1>
            </div>
            <p class="page-subtitle">Registered players database, birth dates, and credentials management</p>
        </div>
        <button class="btn btn-primary" id="btnOpenAddUser">
            ➕ Register Player
        </button>
    </div>

    <!-- Search Input -->
    <div class="form-group mb-md" style="max-width:360px;">
        <input type="text" class="form-input" id="userSearchInput" placeholder="🔍 Search by name or DOB...">
    </div>

    <!-- Users Table Card -->
    <div class="card overflow-hidden">
        <div class="table-container">
            <table class="table" id="usersTable">
                <thead>
                    <tr>
                        <th>Player Name</th>
                        <th>Date of Birth (DOB)</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Activity Reference</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-xl text-muted">
                            <div class="loading-spinner mb-sm"></div>
                            <div>Loading players...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">Register Player</h3>
            <button class="modal-close" id="closeAddUserModal">&times;</button>
        </div>
        <form id="addUserForm">
            <div class="modal-body">
                <div class="form-group mb-sm">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" class="form-input" id="newFirstName" placeholder="e.g. Kalash" required>
                </div>
                <div class="form-group mb-sm">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-input" id="newLastName" placeholder="e.g. Shah">
                </div>
                <div class="form-group mb-sm">
                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">🎂</span>
                        <input type="text" class="form-input dob-picker-input" id="newDob" placeholder="DD-MM-YYYY" required autocomplete="bday">
                        <button type="button" class="dob-calendar-toggle" aria-label="Pick date of birth" tabindex="-1">📅</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Role</label>
                    <select class="form-select" id="newRole">
                        <option value="player">Player</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelAddUserModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Profile</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let allUsers = [];
    const tableBody = document.getElementById('usersTableBody');
    const searchInput = document.getElementById('userSearchInput');

    async function loadUsers() {
        try {
            const res = await TM.api('admin.php?action=users_list');
            if (res.success) {
                allUsers = res.users;
                renderUsers();
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-md text-red">${res.message}</td></tr>`;
            }
        } catch (e) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-md text-red">Failed to load users</td></tr>';
        }
    }

    function renderUsers() {
        const query = searchInput.value.toLowerCase().trim();
        const filtered = allUsers.filter(u => {
            if (!query) return true;
            const dobFormatted = u.dob_display || (window.TM?.formatDob ? TM.formatDob(u.dob) : u.dob) || '';
            return u.full_name.toLowerCase().includes(query) || (u.dob && u.dob.includes(query)) || dobFormatted.includes(query);
        });

        if (!filtered.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-xl text-muted">No matching players found</td></tr>';
            return;
        }

        tableBody.innerHTML = filtered.map(u => {
            const isRef = u.is_referenced;
            const refBadge = isRef 
                ? '<span class="badge badge-primary text-xs" title="Linked to match or poll data">Active in Matches/Polls</span>'
                : '<span class="badge badge-secondary text-xs">Unreferenced</span>';

            const roleBadge = u.role === 'admin'
                ? '<span class="badge badge-warning text-xs">👑 Admin</span>'
                : '<span class="badge badge-secondary text-xs">Player</span>';

            const lastLoginText = u.last_login ? TM.timeAgo(u.last_login) : 'Never';
            const displayDob = u.dob_display || (window.TM?.formatDob ? TM.formatDob(u.dob) : u.dob) || 'N/A';

            return `
                <tr>
                    <td>
                        <div class="d-flex align-center gap-xs">
                            <div class="user-avatar" style="width:28px;height:28px;font-size:11px;">
                                ${u.first_name.charAt(0).toUpperCase()}
                            </div>
                            <span class="fw-semibold text-sm">${TM.escapeHtml(u.full_name)}</span>
                        </div>
                    </td>
                    <td>
                        <span class="fw-mono text-sm">${displayDob}</span>
                    </td>
                    <td>
                        <div class="d-flex align-center gap-xs">
                            ${roleBadge}
                            <button class="btn btn-secondary btn-xs btn-toggle-role" data-user-id="${u.id}" title="Toggle role">⇄</button>
                        </div>
                    </td>
                    <td>
                        <span class="text-xs text-muted">${lastLoginText}</span>
                    </td>
                    <td>
                        ${refBadge}
                    </td>
                    <td class="text-right">
                        ${u.can_delete ? `
                            <button class="btn btn-secondary btn-xs text-red btn-delete-user" data-user-id="${u.id}" data-name="${TM.escapeHtml(u.full_name)}">
                                🗑️ Delete
                            </button>
                        ` : `
                            <button class="btn btn-secondary btn-xs text-muted" disabled title="Cannot delete: referenced in match or poll data">
                                🔒 Protected
                            </button>
                        `}
                    </td>
                </tr>
            `;
        }).join('');

        // Delete buttons
        tableBody.querySelectorAll('.btn-delete-user').forEach(btn => {
            btn.addEventListener('click', () => {
                confirmDeleteUser(btn.dataset.userId, btn.dataset.name);
            });
        });

        // Toggle role buttons
        tableBody.querySelectorAll('.btn-toggle-role').forEach(btn => {
            btn.addEventListener('click', () => {
                toggleRole(btn.dataset.userId);
            });
        });
    }

    searchInput.addEventListener('input', TM.debounce(renderUsers, 200));

    async function confirmDeleteUser(id, name) {
        const confirm = await TM.confirm(`Delete user ${name}?`, 'This action cannot be undone. Safe deletion ensures no match history is broken.', 'warning');
        if (!confirm.isConfirmed) return;

        try {
            const res = await TM.api('admin.php?action=user_delete', {
                method: 'POST',
                body: { id }
            });
            if (res.success) {
                TM.toast(res.message, 'success');
                loadUsers();
            } else {
                Swal.fire({ icon: 'error', title: 'Protected User', text: res.message });
            }
        } catch (e) {
            TM.toast('Failed to delete user', 'error');
        }
    }

    async function toggleRole(id) {
        try {
            const res = await TM.api('admin.php?action=user_toggle_role', {
                method: 'POST',
                body: { id }
            });
            if (res.success) {
                TM.toast(`Role updated to ${res.new_role}`, 'success');
                loadUsers();
            } else {
                TM.toast(res.message, 'warning');
            }
        } catch (e) {
            TM.toast('Failed to update role', 'error');
        }
    }

    // Add user modal
    const modal = document.getElementById('addUserModal');
    const btnOpen = document.getElementById('btnOpenAddUser');
    const form = document.getElementById('addUserForm');

    btnOpen?.addEventListener('click', () => modal.classList.add('open'));
    document.getElementById('closeAddUserModal')?.addEventListener('click', () => modal.classList.remove('open'));
    document.getElementById('cancelAddUserModal')?.addEventListener('click', () => modal.classList.remove('open'));

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const firstName = document.getElementById('newFirstName').value.trim();
        const lastName = document.getElementById('newLastName').value.trim();
        const dob = document.getElementById('newDob').value;
        const role = document.getElementById('newRole').value;

        TM.loading('Registering player profile...');
        try {
            const res = await TM.api('admin.php?action=user_add', {
                method: 'POST',
                body: { first_name: firstName, last_name: lastName, dob, role }
            });
            TM.stopLoading();

            if (res.success) {
                modal.classList.remove('open');
                form.reset();
                TM.toast(res.message, 'success');
                loadUsers();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (err) {
            TM.stopLoading();
            TM.toast('Failed to register player', 'error');
        }
    });

    loadUsers();
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
