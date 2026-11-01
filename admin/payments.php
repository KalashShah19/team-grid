<?php
/**
 * Admin Payments Page
 * Turf fees tracker, per-player contribution calculation, interactive paid/pending checklist.
 * STRICTLY restricted to Admin only!
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Payments & Turf Fees';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';

$allUsers = getUsers();
$allMatches = getMatches();
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <div class="d-flex align-center gap-xs">
                <a href="<?php echo BASE_URL; ?>/admin" class="btn btn-secondary btn-xs">← Admin</a>
                <h1 class="page-title" style="margin-bottom:0;">💰 Payments & Turf Fees</h1>
            </div>
            <p class="page-subtitle">Track turf booking costs and manage player payment checklists</p>
        </div>
        <button class="btn btn-primary" id="btnNewPayment">
            ➕ Add Turf Payment Record
        </button>
    </div>

    <!-- Financial KPI Summary Cards -->
    <div class="d-grid gap-md mb-xl" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));" id="paymentSummaryCards">
        <div class="card p-md">
            <span class="text-xs text-muted">TOTAL TURF FEES</span>
            <div class="stat-value text-primary mt-xs" id="sumTurfFees">₹0</div>
        </div>
        <div class="card p-md">
            <span class="text-xs text-muted">TOTAL COLLECTED</span>
            <div class="stat-value text-green mt-xs" id="sumCollected">₹0</div>
        </div>
        <div class="card p-md">
            <span class="text-xs text-muted">TOTAL PENDING</span>
            <div class="stat-value text-red mt-xs" id="sumPending">₹0</div>
        </div>
        <div class="card p-md">
            <span class="text-xs text-muted">NET BALANCE</span>
            <div class="stat-value text-gold mt-xs" id="sumBalance">₹0</div>
        </div>
    </div>

    <!-- Payments Container -->
    <div class="d-grid gap-lg" id="paymentsListContainer">
        <div class="card p-xl text-center text-muted">
            <div class="loading-spinner mb-sm"></div>
            <div>Loading payment logs...</div>
        </div>
    </div>
</div>

<!-- Add / Edit Payment Modal -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal" style="max-width: 620px;">
        <div class="modal-header">
            <h3 class="modal-title" id="paymentModalTitle">Record Turf Booking & Contributions</h3>
            <button class="modal-close" id="closePaymentModal">&times;</button>
        </div>
        <form id="paymentForm">
            <input type="hidden" id="editPaymentId" value="">
            <div class="modal-body">
                <div class="form-row mb-md">
                    <div class="form-group">
                        <label class="form-label">Link to Match <span class="text-xs text-muted">(Optional)</span></label>
                        <select class="form-select" id="payMatchSelect">
                            <option value="">Custom / No Match Link</option>
                            <?php foreach ($allMatches as $m): ?>
                                <option value="<?php echo $m['id']; ?>" data-title="<?php echo htmlspecialchars($m['title']); ?>" data-players="<?php echo htmlspecialchars(json_encode(array_merge($m['team_a']['players'] ?? [], $m['team_b']['players'] ?? []))); ?>">
                                    <?php echo htmlspecialchars($m['title'] . ' (' . $m['date'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date <span class="required">*</span></label>
                        <input type="date" class="form-input" id="payDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="form-row mb-md">
                    <div class="form-group">
                        <label class="form-label">Booking Title <span class="required">*</span></label>
                        <input type="text" class="form-input" id="payTitle" placeholder="e.g., Friday Night 7v7 Turf" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Turf Fee (₹) <span class="required">*</span></label>
                        <input type="number" class="form-input" id="payTurfFee" min="0" step="50" placeholder="e.g. 2400" required>
                    </div>
                </div>

                <!-- Squad Selection & Per-Player calculation -->
                <div class="form-group mb-md">
                    <div class="d-flex align-center justify-between mb-xs">
                        <label class="form-label mb-0">Contributing Players (<span id="selectedPlayersCount">0</span> selected)</label>
                        <span class="text-xs text-green fw-bold" id="calcPerPlayerFee">Est. Per Player: ₹0</span>
                    </div>
                    <div class="card p-sm" style="background:var(--bg-input); max-height:180px; overflow-y:auto;">
                        <div class="d-grid gap-xs" id="playersCheckboxGrid" style="grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));">
                            <?php foreach ($allUsers as $u): ?>
                                <label class="d-flex align-center gap-xs text-xs p-xs" style="cursor:pointer; border-radius:4px; background:var(--bg-secondary);">
                                    <input type="checkbox" class="pay-player-check" value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '')); ?>">
                                    <span class="flex-1"><?php echo htmlspecialchars($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '')); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes <span class="text-xs text-muted">(Optional)</span></label>
                    <input type="text" class="form-input" id="payNotes" placeholder="e.g., Paid via GooglePay to turf manager">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelPaymentModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment Record</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let paymentsData = [];

    async function loadPayments() {
        try {
            const res = await TM.api('admin.php?action=payments_list');
            if (res.success) {
                paymentsData = res.payments;
                renderSummary(res.summary);
                renderPayments();
            } else {
                TM.toast(res.message, 'error');
            }
        } catch (e) {
            TM.toast('Failed to load payments', 'error');
        }
    }

    function renderSummary(s) {
        document.getElementById('sumTurfFees').textContent = `₹${s.total_turf_fees.toLocaleString()}`;
        document.getElementById('sumCollected').textContent = `₹${s.total_collected.toLocaleString()}`;
        document.getElementById('sumPending').textContent = `₹${s.total_pending.toLocaleString()}`;
        document.getElementById('sumBalance').textContent = `₹${s.net_balance.toLocaleString()}`;
        document.getElementById('sumBalance').className = `stat-value mt-xs ${s.net_balance >= 0 ? 'text-green' : 'text-red'}`;
    }

    function renderPayments() {
        const container = document.getElementById('paymentsListContainer');
        if (!paymentsData.length) {
            container.innerHTML = `
                <div class="card p-xl text-center text-muted">
                    <div style="font-size:40px; margin-bottom:8px;">💰</div>
                    <h3>No Payment Logs Yet</h3>
                    <p class="text-secondary text-sm mt-xs">Click "+ Add Turf Payment Record" to track turf fees and dues.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = paymentsData.map(p => {
            const totalPlayers = p.players.length;
            const paidCount = p.players.filter(pl => pl.status === 'paid').length;
            const pendingCount = totalPlayers - paidCount;

            const rowsHtml = p.players.map(pl => {
                const isPaid = (pl.status === 'paid');
                return `
                    <tr>
                        <td>
                            <span class="fw-semibold text-sm">👤 ${TM.escapeHtml(pl.user_name)}</span>
                        </td>
                        <td>
                            <span class="fw-bold">₹${pl.amount}</span>
                        </td>
                        <td>
                            <span class="badge ${isPaid ? 'badge-success' : 'badge-danger'}">
                                ${isPaid ? '✓ Paid' : '⏳ Pending'}
                            </span>
                        </td>
                        <td>
                            <span class="text-xs text-muted">${pl.paid_at ? TM.formatDate(pl.paid_at, {month:'short', day:'numeric'}) : '-'}</span>
                        </td>
                        <td class="text-right">
                            <button class="btn btn-xs ${isPaid ? 'btn-secondary text-red' : 'btn-primary'} btn-toggle-pay" data-pay-id="${p.id}" data-user-id="${pl.user_id}">
                                ${isPaid ? 'Mark Pending' : '✓ Mark Paid'}
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            return `
                <div class="card overflow-hidden">
                    <div class="card-header justify-between flex-wrap gap-sm">
                        <div>
                            <h3 style="font-size:var(--font-size-md);">${TM.escapeHtml(p.title)}</h3>
                            <div class="text-xs text-muted">
                                📅 ${TM.formatDate(p.date)} &bull; Turf Fee: <strong class="text-primary">₹${p.turf_fee}</strong> &bull; Per Player: <strong class="text-green">₹${p.per_player_fee}</strong>
                            </div>
                        </div>
                        <div class="d-flex align-center gap-xs">
                            <span class="badge badge-success">${paidCount} Paid</span>
                            <span class="badge badge-danger">${pendingCount} Pending</span>
                        </div>
                    </div>
                    ${p.notes ? `<div class="p-sm text-xs text-secondary" style="background:var(--bg-input); border-bottom:1px solid var(--border-color);">📝 Notes: ${TM.escapeHtml(p.notes)}</div>` : ''}
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Share</th>
                                    <th>Status</th>
                                    <th>Paid At</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml.length ? rowsHtml : '<tr><td colspan="5" class="text-center text-muted py-sm">No players added to this record</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }).join('');

        // Attach toggle handlers
        container.querySelectorAll('.btn-toggle-pay').forEach(btn => {
            btn.addEventListener('click', async () => {
                const payId = btn.dataset.payId;
                const uid = btn.dataset.userId;
                try {
                    const res = await TM.api('admin.php?action=payment_toggle_status', {
                        method: 'POST',
                        body: { payment_id: payId, user_id: uid }
                    });
                    if (res.success) {
                        TM.toast('Payment status updated', 'success');
                        loadPayments();
                    }
                } catch (e) {
                    TM.toast('Failed to update status', 'error');
                }
            });
        });
    }

    // Modal controls
    const modal = document.getElementById('paymentModal');
    const btnOpen = document.getElementById('btnNewPayment');
    const form = document.getElementById('paymentForm');

    btnOpen?.addEventListener('click', () => {
        form.reset();
        document.getElementById('editPaymentId').value = '';
        modal.classList.add('open');
        updateCalc();
    });

    document.getElementById('closePaymentModal')?.addEventListener('click', () => modal.classList.remove('open'));
    document.getElementById('cancelPaymentModal')?.addEventListener('click', () => modal.classList.remove('open'));

    // Match auto-selection
    document.getElementById('payMatchSelect')?.addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        if (!opt || !opt.value) return;

        document.getElementById('payTitle').value = opt.dataset.title || '';
        try {
            const playerIds = JSON.parse(opt.dataset.players || '[]');
            document.querySelectorAll('.pay-player-check').forEach(ck => {
                ck.checked = playerIds.includes(ck.value);
            });
            updateCalc();
        } catch (err) {}
    });

    // Checkbox updates
    document.querySelectorAll('.pay-player-check').forEach(ck => {
        ck.addEventListener('change', updateCalc);
    });
    document.getElementById('payTurfFee')?.addEventListener('input', updateCalc);

    function updateCalc() {
        const checked = Array.from(document.querySelectorAll('.pay-player-check:checked'));
        const count = checked.length;
        document.getElementById('selectedPlayersCount').textContent = count;

        const fee = parseFloat(document.getElementById('payTurfFee')?.value || 0);
        const perPlayer = count > 0 ? (fee / count).toFixed(1) : 0;
        document.getElementById('calcPerPlayerFee').textContent = `Est. Per Player: ₹${perPlayer}`;
    }

    // Submit payment form
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editPaymentId').value || null;
        const matchId = document.getElementById('payMatchSelect').value || null;
        const date = document.getElementById('payDate').value;
        const title = document.getElementById('payTitle').value.trim();
        const turfFee = parseFloat(document.getElementById('payTurfFee').value);
        const notes = document.getElementById('payNotes').value.trim();

        const checkedBoxes = Array.from(document.querySelectorAll('.pay-player-check:checked'));
        if (!checkedBoxes.length) {
            TM.toast('Please select at least 1 player', 'warning');
            return;
        }

        const perPlayer = countToShare(turfFee, checkedBoxes.length);
        const players = checkedBoxes.map(ck => ({
            user_id: ck.value,
            name: ck.dataset.name,
            amount: perPlayer,
            status: 'pending',
            paid_at: null,
        }));

        TM.loading('Saving payment checklist...');
        try {
            const res = await TM.api('admin.php?action=payment_save', {
                method: 'POST',
                body: { id, match_id: matchId, date, title, turf_fee: turfFee, notes, players }
            });
            TM.stopLoading();

            if (res.success) {
                modal.classList.remove('open');
                TM.toast('Turf payment record created!', 'success');
                loadPayments();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (err) {
            TM.stopLoading();
            TM.toast('Failed to save payment record', 'error');
        }
    });

    function countToShare(total, count) {
        return count > 0 ? Math.round(total / count) : 0;
    }

    loadPayments();
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
