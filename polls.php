<?php
/**
 * Polls Page
 * Displays active polls for players, filter by tag, and poll creation for admin
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pageTitle = 'Polls';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<div class="container">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <div class="d-flex align-center gap-sm">
                <h1 class="page-title" style="margin-bottom:0;">Match Polls</h1>
                <span class="badge badge-success text-xs" style="display:inline-flex; align-items:center; gap:5px; font-weight:600;"><span class="pulse-dot" style="width:6px; height:6px; background:var(--accent-green); border-radius:50%; box-shadow:0 0 6px var(--accent-green); display:inline-block;"></span> Live</span>
            </div>
            <p class="page-subtitle">Cast your vote for upcoming sessions & matchdays</p>
        </div>
        <?php if ($isAdminUser): ?>
            <button class="btn btn-primary" id="btnCreatePoll">
                <span>➕</span> Create New Poll
            </button>
        <?php endif; ?>
    </div>

    <!-- Tag Filters -->
    <div class="pill-filters">
        <button class="pill active" data-filter="all">All Polls</button>
        <button class="pill" data-filter="internal">🏟️ Internal</button>
        <button class="pill" data-filter="friendly">🤝 Friendly</button>
    </div>

    <!-- Polls Grid -->
    <div class="d-grid gap-md" id="pollsGrid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
        <div class="card text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
            <div class="loading-spinner mb-md"></div>
            <p class="text-muted">Loading active polls...</p>
        </div>
    </div>
</div>

<?php if ($isAdminUser): ?>
<!-- Create Poll Modal -->
<div class="modal-overlay" id="createPollModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Create Match Poll</h3>
            <button class="modal-close" id="closeCreatePollModal">&times;</button>
        </div>
        <form id="createPollForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Poll Title <span class="required">*</span></label>
                    <input type="text" class="form-input" id="pollTitle" placeholder="e.g., Saturday 7v7 Turf Match" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Turf / Venue <span class="text-muted text-xs">(Optional)</span></label>
                    <input type="text" class="form-input" id="pollTurf" placeholder="e.g. 99 not out, valas pro, valas old">
                    <div class="d-flex gap-xs mt-xs flex-wrap" id="pollTurfPresets">
                        <button type="button" class="btn btn-secondary btn-xs poll-turf-btn" data-votes="12" data-turf="99 not out">🏟️ 6-side: 99 not out</button>
                        <button type="button" class="btn btn-secondary btn-xs poll-turf-btn" data-votes="14" data-turf="valas pro">🏟️ 7-side: valas pro</button>
                        <button type="button" class="btn btn-secondary btn-xs poll-turf-btn" data-votes="16" data-turf="valas old">🏟️ 8-side: valas old</button>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Poll Tag <span class="required">*</span></label>
                        <select class="form-select" id="pollTag">
                            <option value="internal">🏟️ Internal</option>
                            <option value="friendly">🤝 Friendly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Votes <span class="text-muted text-xs">(Optional)</span></label>
                        <input type="number" class="form-input" id="pollMaxVotes" min="2" max="100" placeholder="e.g. 14">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Deadline <span class="text-muted text-xs">(Optional)</span></label>
                    <input type="datetime-local" class="form-input" id="pollDeadline">
                </div>

                <div class="form-group">
                    <label class="form-label">Voting Options <span class="required">*</span></label>
                    <div id="optionsList" class="d-grid gap-sm">
                        <div class="d-flex gap-sm">
                            <input type="text" class="form-input option-input" placeholder="Option 1" value="In" required>
                        </div>
                        <div class="d-flex gap-sm">
                            <input type="text" class="form-input option-input" placeholder="Option 2" value="Out" required>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-sm" id="btnAddOption">
                        ➕ Add Another Option
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelCreatePoll">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitPoll">🚀 Publish Poll</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const isAdmin = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
    let allPolls = [];
    let currentFilter = 'all';

    const pollsGrid = document.getElementById('pollsGrid');

    let lastPollsJson = '';

    async function loadPolls(silent = false) {
        try {
            const data = await TM.api('polls.php?action=list');
            if (data.success) {
                const newJson = JSON.stringify(data.polls);
                if (newJson !== lastPollsJson) {
                    lastPollsJson = newJson;
                    allPolls = data.polls;
                    renderPolls();
                }
            } else if (!silent) {
                pollsGrid.innerHTML = `<div class="card text-center" style="grid-column: 1 / -1; padding: var(--spacing-xl);"><p class="text-red">${data.message}</p></div>`;
            }
        } catch (e) {
            if (!silent) {
                pollsGrid.innerHTML = `<div class="card text-center" style="grid-column: 1 / -1; padding: var(--spacing-xl);"><p class="text-red">Failed to load polls</p></div>`;
            }
        }
    }

    function renderPolls() {
        const filtered = allPolls.filter(p => {
            if (currentFilter === 'all') return true;
            return p.tag === currentFilter;
        });

        if (!filtered.length) {
            pollsGrid.innerHTML = `
                <div class="card text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                    <div style="font-size: 48px; margin-bottom: var(--spacing-sm); opacity: 0.5;">⚽</div>
                    <h3>No Active Polls</h3>
                    <p class="text-secondary mt-sm">There are no open polls right now. Check back soon!</p>
                </div>`;
            return;
        }

        pollsGrid.innerHTML = filtered.map(poll => {
            const tagBadge = poll.tag === 'friendly' 
                ? '<span class="badge badge-warning">🤝 Friendly</span>' 
                : '<span class="badge badge-primary">🏟️ Internal</span>';

            const deadlineText = poll.deadline 
                ? `<div class="text-xs text-muted mt-sm">⏳ Deadline: ${TM.formatDate(poll.deadline, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})}</div>` 
                : '';

            const myVoteBadge = (poll.my_vote !== null)
                ? `<span class="badge badge-success">✓ Voted</span>`
                : `<span class="badge badge-danger">Not Voted</span>`;

            const statusBadge = (poll.status === 'locked')
                ? `<span class="badge badge-warning">🔒 Locked</span> <span class="badge text-xs" style="background:rgba(0, 230, 118, 0.12); color:var(--accent-green); border:1px solid rgba(0, 230, 118, 0.3);">💬 Chat Open</span>`
                : (poll.status === 'closed' ? `<span class="badge badge-danger">Closed</span>` : '');

            const actionBtnText = (poll.status === 'locked')
                ? '🔒 Locked · 💬 View & Chat'
                : (poll.my_vote !== null ? '✏️ View / Edit Vote' : 'Vote');

            return `
                <div class="card card-hover" style="display:flex; flex-direction:column; justify-content:space-between; cursor:pointer;" onclick="window.location.href='${TM.baseUrl}/poll-detail?id=${poll.id}'">
                    <div class="card-header">
                        <div class="d-flex align-center gap-xs">
                            ${tagBadge}
                            ${statusBadge}
                        </div>
                        ${myVoteBadge}
                    </div>
                    <div class="card-body">
                        <h3 class="mb-xs" style="font-size: var(--font-size-lg);">${TM.escapeHtml(poll.title)}</h3>
                        ${poll.turf ? `<div class="text-xs text-muted mb-sm" style="display:flex; align-items:center; gap:4px;">📍 <span>${TM.escapeHtml(poll.turf)}</span></div>` : ''}
                        <div class="d-flex gap-md text-sm text-secondary mb-sm flex-wrap">
                            <span>🗳️ ${poll.breakdown_text || `${poll.total_votes} votes`}${poll.max_votes ? ` (Cap: ${poll.max_votes})` : ''}</span>
                            <span>💬 ${poll.comments_count} comments</span>
                        </div>
                        ${deadlineText}
                    </div>
                    <div class="card-footer d-flex gap-xs">
                        <a href="${TM.baseUrl}/poll-detail?id=${poll.id}" class="btn ${poll.status === 'locked' ? 'btn-secondary' : 'btn-primary'} btn-block" style="flex:1;">
                            ${actionBtnText}
                        </a>
                        ${isAdmin ? `
                            <button type="button" class="btn btn-secondary btn-sm text-red" title="Delete Poll" onclick="event.stopPropagation(); deletePollFromList('${poll.id}')">
                                🗑️
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    window.deletePollFromList = async function(id) {
        const confirm = await TM.confirm('Delete Poll?', 'Are you sure you want to permanently delete this poll, all votes, and comments? This action cannot be undone.', 'warning');
        if (!confirm.isConfirmed) return;

        TM.loading('Deleting poll...');
        try {
            const res = await TM.api('polls.php?action=delete', {
                method: 'POST',
                body: { id }
            });
            TM.stopLoading();
            if (res.success) {
                TM.toast('Poll deleted successfully', 'success');
                lastPollsJson = '';
                loadPolls();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (e) {
            TM.stopLoading();
            TM.toast('Failed to delete poll', 'error');
        }
    };

    // Filter pill clicks
    document.querySelectorAll('.pill-filters .pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.pill-filters .pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentFilter = pill.dataset.filter;
            renderPolls();
        });
    });

    // Admin Modal logic
    const modal = document.getElementById('createPollModal');
    const btnOpen = document.getElementById('btnCreatePoll');
    const btnClose = document.getElementById('closeCreatePollModal');
    const btnCancel = document.getElementById('cancelCreatePoll');
    const btnAddOption = document.getElementById('btnAddOption');
    const optionsList = document.getElementById('optionsList');
    const form = document.getElementById('createPollForm');

    if (btnOpen) {
        btnOpen.addEventListener('click', () => modal.classList.add('open'));
        btnClose?.addEventListener('click', () => modal.classList.remove('open'));
        btnCancel?.addEventListener('click', () => modal.classList.remove('open'));

        btnAddOption?.addEventListener('click', () => {
            const count = optionsList.querySelectorAll('.option-input').length + 1;
            const row = document.createElement('div');
            row.className = 'd-flex gap-sm';
            row.innerHTML = `
                <input type="text" class="form-input option-input" placeholder="Option ${count}" required>
                <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" title="Remove option">✕</button>
            `;
            optionsList.appendChild(row);
        });

        // Auto-suggest turf based on max votes (6-side/12: 99 not out, 7-side/14: valas pro, 8-side/16: valas old)
        const pollTurfInput = document.getElementById('pollTurf');
        const pollMaxVotesInput = document.getElementById('pollMaxVotes');

        function suggestTurfFromVotes(num) {
            if (!pollTurfInput) return;
            if (num >= 16) pollTurfInput.value = 'valas old';
            else if (num >= 14) pollTurfInput.value = 'valas pro';
            else if (num >= 12) pollTurfInput.value = '99 not out';
        }

        pollMaxVotesInput?.addEventListener('input', () => {
            const val = parseInt(pollMaxVotesInput.value, 10);
            if (!isNaN(val) && val > 0 && !pollTurfInput.dataset.manual) {
                suggestTurfFromVotes(val);
            }
        });

        pollTurfInput?.addEventListener('input', () => {
            pollTurfInput.dataset.manual = 'true';
        });

        document.querySelectorAll('.poll-turf-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (pollTurfInput) {
                    pollTurfInput.value = btn.dataset.turf;
                    pollTurfInput.dataset.manual = 'true';
                }
                if (pollMaxVotesInput && !pollMaxVotesInput.value) {
                    pollMaxVotesInput.value = btn.dataset.votes;
                }
            });
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('pollTitle').value.trim();
            const turf = document.getElementById('pollTurf').value.trim();
            const tag = document.getElementById('pollTag').value;
            const maxVotes = document.getElementById('pollMaxVotes').value;
            const deadline = document.getElementById('pollDeadline').value;
            const optionInputs = Array.from(document.querySelectorAll('.option-input'));
            const options = optionInputs.map(i => i.value.trim()).filter(Boolean);

            if (options.length < 2) {
                TM.toast('Please provide at least 2 voting options', 'error');
                return;
            }

            TM.loading('Creating poll...');
            try {
                const res = await TM.api('polls.php?action=create', {
                    method: 'POST',
                    body: { title, turf, tag, max_votes: maxVotes || null, deadline: deadline || null, options }
                });
                TM.stopLoading();

                if (res.success) {
                    modal.classList.remove('open');
                    form.reset();
                    optionsList.innerHTML = `
                        <div class="d-flex gap-sm">
                            <input type="text" class="form-input option-input" placeholder="Option 1" value="In" required>
                        </div>
                        <div class="d-flex gap-sm">
                            <input type="text" class="form-input option-input" placeholder="Option 2" value="Out" required>
                        </div>
                    `;
                    TM.toast('Poll created and players notified!', 'success');
                    loadPolls();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            } catch (err) {
                TM.stopLoading();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to create poll' });
            }
        });
    }

    loadPolls();

    // Auto-update live without page refresh every 4 seconds
    setInterval(() => loadPolls(true), 4000);

    // Live refresh on incoming notification or when tab regains focus
    window.addEventListener('tm:notification', () => loadPolls(true));
    window.addEventListener('focus', () => loadPolls(true));
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
