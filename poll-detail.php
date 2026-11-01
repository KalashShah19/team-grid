<?php
/**
 * Poll Detail Page
 * Voting, edit vote, admin proxy voting, live results, comments with anti-spam,
 * unvoted viewer analytics, and Team Selection transition button
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pollId = $_GET['id'] ?? '';
if (!$pollId) {
    header('Location: ' . BASE_URL . '/polls');
    exit;
}

$pageTitle = 'Poll Details';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';

$allUsers = getUsers();
?>

<div class="container container-sm">
    <div class="mb-md">
        <a href="<?php echo BASE_URL; ?>/polls" class="btn btn-secondary btn-sm">
            ← Back to Polls
        </a>
    </div>

    <!-- Poll Card -->
    <div class="card mb-lg" id="pollCard">
        <div class="card-header">
            <div id="pollHeaderLeft" class="d-flex align-center gap-sm">
                <!-- Tag & Status will be injected -->
            </div>
            <div id="pollHeaderRight">
                <!-- Admin Actions / Team Selection -->
            </div>
        </div>

        <div class="card-body">
            <h1 class="page-title mb-sm" id="pollTitle">Loading poll...</h1>
            <div class="text-sm text-secondary mb-md" id="pollMeta"></div>

            <!-- Options & Voting -->
            <div class="d-grid gap-md mb-md" id="optionsContainer">
                <div class="text-center text-muted py-lg">Loading voting options...</div>
            </div>

            <!-- Notice when poll is locked (Chat is still open!) -->
            <div id="lockedChatNotice" class="card p-sm mb-md text-xs d-flex align-center gap-xs" style="display:none; background:rgba(0, 230, 118, 0.08); border-left:3px solid var(--accent-green); color:var(--text-secondary);">
                <span>💬 <strong>Match Chat Active:</strong> Voting is locked for team selection, but live chat remains open! Coordinate arrival times, turf details, and tactics below.</span>
            </div>

            <!-- Guest / Temporary Players Counter Section -->
            <div class="card p-md mb-lg" id="guestSection" style="background:var(--bg-elevated); border:1px solid var(--border-color); border-radius:var(--radius-lg); display:none;">
                <div class="d-flex align-center justify-between flex-wrap gap-sm">
                    <div>
                        <div class="fw-bold" style="font-size:var(--font-size-sm); display:flex; align-items:center; gap:6px;">
                            <span>👥 Bringing Extra / Temporary Players?</span>
                            <span class="badge badge-success text-xs" id="activeGuestBadge" style="display:none;">+0 Guests</span>
                        </div>
                        <p class="text-xs text-muted mt-xs mb-0">Temporary players coming with you count towards the match headcount.</p>
                    </div>
                    <div class="d-flex align-center gap-sm">
                        <div class="d-flex align-center gap-xs" style="background:var(--bg-input); padding:3px 6px; border-radius:var(--radius-md); border:1px solid var(--border-light);">
                            <button type="button" class="btn btn-secondary btn-xs" id="btnGuestMinus" style="width:28px; height:28px; padding:0; font-weight:bold; font-size:16px; line-height:1;">–</button>
                            <span id="guestCountDisplay" style="min-width:30px; text-align:center; font-weight:700; font-size:var(--font-size-sm); color:var(--accent-green);">0</span>
                            <button type="button" class="btn btn-secondary btn-xs" id="btnGuestPlus" style="width:28px; height:28px; padding:0; font-weight:bold; font-size:16px; line-height:1;">+</button>
                        </div>
                        <button type="button" class="btn btn-primary btn-xs" id="btnUpdateGuests" style="display:none;">Save Guests</button>
                    </div>
                </div>
                <div id="guestNamesContainer" style="display:none; margin-top:10px; padding-top:8px; border-top:1px dashed var(--border-light);">
                    <label class="form-label text-xs mb-xs">Guest Names <span class="text-muted">(Optional, comma-separated)</span></label>
                    <input type="text" class="form-input text-xs" id="guestNamesInput" placeholder="e.g. Alex, Ryan (leave blank to auto-name with (+1), (+2))">
                </div>
            </div>

            <!-- Admin Proxy Voting Bar -->
            <?php if ($isAdminUser): ?>
                <div class="card p-md mb-md" style="background: var(--bg-elevated); border-left: 3px solid var(--accent-gold);">
                    <div class="d-flex align-center justify-between mb-sm flex-wrap gap-sm">
                        <span class="text-xs fw-bold text-gold">👑 ADMIN PROXY VOTE</span>
                        <button class="btn btn-secondary btn-xs" id="btnViewUnvoted">
                            👀 Who Viewed But Didn't Vote?
                        </button>
                    </div>
                    <p class="text-xs text-muted mb-sm">Vote on behalf of another squad member:</p>
                    <div class="d-flex gap-sm flex-wrap align-center">
                        <select class="form-select flex-1" id="proxyUserSelect" style="min-width: 140px;">
                            <option value="">Select Player...</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select" id="proxyOptionSelect" style="min-width: 120px;">
                            <!-- Options injected dynamically -->
                        </select>
                        <div class="d-flex align-center gap-xs" title="Number of guests coming with this player">
                            <span class="text-xs text-muted">Guests:</span>
                            <input type="number" id="proxyGuestCount" class="form-input text-xs" min="0" max="20" value="0" style="width:65px; padding:6px 8px;">
                        </div>
                        <button class="btn btn-primary btn-sm" id="btnCastProxyVote">Cast Proxy</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer justify-between flex-wrap" id="pollFooter">
            <div class="text-xs text-muted" id="pollViewsCount"></div>
            <div class="d-flex gap-sm flex-wrap" id="pollFooterBtns"></div>
        </div>
    </div>

    <!-- Comments Section -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-center gap-sm">
                <h3 style="font-size: var(--font-size-md); margin-bottom: 0;">💬 Match Chat & Comments</h3>
                <span id="chatStatusBadge" class="badge text-xs" style="background:rgba(0, 230, 118, 0.12); color:var(--accent-green); border:1px solid rgba(0, 230, 118, 0.3);">
                    ● Chat Open
                </span>
            </div>
            <span class="badge badge-primary" id="commentCountBadge">0</span>
        </div>

        <div class="card-body">
            <!-- Comment Input -->
            <form id="commentForm" class="mb-lg">
                <div class="form-group mb-sm">
                    <textarea class="form-textarea" id="commentText" placeholder="Discuss tactics, arrival time, turf details with the squad..." rows="2" required></textarea>
                </div>
                <div class="d-flex justify-end align-center">
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSendComment">Send <span class="text-xs" style="opacity:0.7;">(Ctrl+↵)</span></button>
                </div>
            </form>

            <!-- Comments Stream -->
            <div class="d-grid gap-sm" id="commentsStream">
                <div class="text-center text-muted py-md text-sm">No comments yet. Start the conversation!</div>
            </div>
        </div>
    </div>
</div>

<!-- Unvoted Viewers Modal -->
<?php if ($isAdminUser): ?>
<div class="modal-overlay" id="unvotedModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Viewed But Not Voted</h3>
            <button class="modal-close" id="closeUnvotedModal">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-secondary mb-md">These players opened this poll page but have not yet registered their vote:</p>
            <div id="unvotedList" class="d-grid gap-xs">
                <div class="loading-spinner"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" id="btnCloseUnvoted">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pollId = '<?php echo $pollId; ?>';
    let pollData = null;
    let cooldownTimer = null;
    let cooldownRemaining = 0;
    let lastPollDataJson = '';

    async function loadPoll(silent = false) {
        try {
            const res = await TM.api(`polls.php?action=get&id=${pollId}`);
            if (res.success) {
                const newJson = JSON.stringify(res.poll);
                if (newJson !== lastPollDataJson) {
                    lastPollDataJson = newJson;
                    pollData = res.poll;
                    renderPoll();
                    renderComments();
                }
            } else if (!silent) {
                Swal.fire({
                    icon: 'error',
                    title: 'Access Restricted',
                    text: res.message,
                }).then(() => {
                    window.location.href = `${TM.baseUrl}/polls`;
                });
            }
        } catch (e) {
            if (!silent) {
                TM.toast('Failed to load poll details', 'error');
            }
        }
    }

    function renderPoll() {
        if (!pollData) return;

        // Title & Meta
        document.getElementById('pollTitle').textContent = pollData.title;

        const tagText = pollData.tag === 'friendly' 
            ? '<span class="badge badge-warning">🤝 Friendly</span>' 
            : '<span class="badge badge-primary">🏟️ Internal</span>';

        let statusBadge = '<span class="badge badge-danger">● Closed</span>';
        if (pollData.status === 'active') {
            statusBadge = '<span class="badge badge-success">● Active</span>';
        } else if (pollData.status === 'locked') {
            statusBadge = '<span class="badge badge-warning">🔒 Locked (Team Selection)</span>';
        }

        const liveBadge = (pollData.status === 'active' || pollData.status === 'locked')
            ? `<span class="badge text-xs" style="background:rgba(0, 230, 118, 0.12); color:var(--accent-green); border:1px solid rgba(0, 230, 118, 0.3); display:inline-flex; align-items:center; gap:5px;"><span class="pulse-dot" style="width:5px; height:5px; background:var(--accent-green); border-radius:50%; box-shadow:0 0 5px var(--accent-green); display:inline-block;"></span> ${pollData.status === 'locked' ? '💬 Match Chat Active' : 'Live'}</span>`
            : '';

        document.getElementById('pollHeaderLeft').innerHTML = `${tagText} ${statusBadge} ${liveBadge}`;

        const lockedNotice = document.getElementById('lockedChatNotice');
        if (lockedNotice) {
            lockedNotice.style.display = (pollData.status === 'locked') ? 'flex' : 'none';
        }

        let metaHtml = `<span>Total Votes: <strong>${pollData.total_votes}</strong> ${pollData.max_votes ? `(Max: ${pollData.max_votes})` : ''}</span>`;
        if (pollData.turf) {
            metaHtml += ` &bull; <span>📍 <strong>${TM.escapeHtml(pollData.turf)}</strong></span>`;
        }
        if (pollData.deadline) {
            metaHtml += ` &bull; <span>Deadline: ${TM.formatDate(pollData.deadline, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})}</span>`;
        }
        document.getElementById('pollMeta').innerHTML = metaHtml;

        // Render Options & Progress Bars
        const optionsContainer = document.getElementById('optionsContainer');
        const total = pollData.total_votes || 0;
        const isClosed = (pollData.status !== 'active');

        // Manage Guest Section
        const guestSection = document.getElementById('guestSection');
        if (guestSection) {
            if (!isClosed) {
                guestSection.style.display = 'block';
                const currentGuests = pollData.my_guest_count || 0;
                const guestCountDisplay = document.getElementById('guestCountDisplay');
                const guestNamesContainer = document.getElementById('guestNamesContainer');
                const guestNamesInput = document.getElementById('guestNamesInput');
                const activeGuestBadge = document.getElementById('activeGuestBadge');
                const btnUpdateGuests = document.getElementById('btnUpdateGuests');

                if (guestCountDisplay) guestCountDisplay.textContent = currentGuests;
                if (currentGuests > 0) {
                    if (activeGuestBadge) {
                        activeGuestBadge.textContent = `+${currentGuests} Guest${currentGuests === 1 ? '' : 's'}`;
                        activeGuestBadge.style.display = 'inline-block';
                    }
                    if (guestNamesContainer) guestNamesContainer.style.display = 'block';
                    if (guestNamesInput && document.activeElement !== guestNamesInput) {
                        guestNamesInput.value = (pollData.my_guest_names || []).join(', ');
                    }
                } else {
                    if (activeGuestBadge) activeGuestBadge.style.display = 'none';
                    if (guestNamesContainer) guestNamesContainer.style.display = 'none';
                    if (guestNamesInput && document.activeElement !== guestNamesInput) {
                        guestNamesInput.value = '';
                    }
                }
                if (btnUpdateGuests) btnUpdateGuests.style.display = 'none';
            } else {
                guestSection.style.display = 'none';
            }
        }

        optionsContainer.innerHTML = pollData.option_stats.map((opt, idx) => {
            const pct = total > 0 ? Math.round((opt.count / total) * 100) : 0;
            const isMyVote = (pollData.my_vote === idx);

            const voterChips = (opt.voters && opt.voters.length > 0)
                ? `<div class="d-flex flex-wrap gap-xs mt-sm">${opt.voters.map(v => {
                    const name = typeof v === 'string' ? v : v.name;
                    const gCount = v.guest_count || 0;
                    const gNames = v.guest_names || [];

                    let guestTags = '';
                    if (gCount > 0) {
                        const guestLabels = [];
                        for (let gi = 1; gi <= gCount; gi++) {
                            const gn = (gNames[gi - 1] && gNames[gi - 1].trim()) ? gNames[gi - 1].trim() : `${name} (+${gi})`;
                            guestLabels.push(`<span class="badge text-xs" style="background:rgba(0, 230, 118, 0.12); border:1px dashed rgba(0, 230, 118, 0.4); color:var(--accent-green);">↳ ${TM.escapeHtml(gn)}</span>`);
                        }
                        guestTags = guestLabels.join('');
                    }

                    return `
                        <span class="badge badge-secondary text-xs" style="display:inline-flex; align-items:center; gap:5px;">
                            ${TM.escapeHtml(name)}
                            ${gCount > 0 ? `<span class="badge badge-success text-xs" style="padding:1px 5px; font-weight:700;">+${gCount}</span>` : ''}
                        </span>
                        ${guestTags}
                    `;
                }).join('')}</div>`
                : '';

            return `
                <div class="card p-md ${isMyVote ? 'border-focus' : ''}" style="background:var(--bg-secondary); position:relative; overflow:hidden; border:${isMyVote ? '2px solid var(--accent-green)' : '1px solid var(--border-color)'};">
                    <div style="position:absolute; top:0; left:0; bottom:0; width:${pct}%; background:rgba(0, 230, 118, 0.08); pointer-events:none; transition:width 0.4s ease;"></div>
                    
                    <div class="d-flex align-center justify-between flex-wrap gap-sm" style="position:relative; z-index:1;">
                        <div>
                            <span class="fw-bold" style="font-size:var(--font-size-md);">${TM.escapeHtml(opt.title)}</span>
                            ${isMyVote ? '<span class="badge badge-success ml-sm">Your Choice ✓</span>' : ''}
                        </div>
                        <div class="d-flex align-center gap-sm">
                            <span class="text-sm fw-bold text-muted">${opt.count} (${pct}%)</span>
                            ${!isClosed ? `
                                <button class="btn btn-sm ${isMyVote ? 'btn-secondary' : 'btn-primary'} btn-vote" data-index="${idx}">
                                    ${isMyVote ? 'Keep' : (pollData.my_vote !== null ? 'Switch' : 'Vote')}
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    ${voterChips}
                </div>
            `;
        }).join('');

        // Voting click handlers
        optionsContainer.querySelectorAll('.btn-vote').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.index, 10);
                const { count, names } = getPendingGuestData();
                castVote(idx, count, names);
            });
        });

        // Proxy options select
        const proxyOptionSelect = document.getElementById('proxyOptionSelect');
        if (proxyOptionSelect) {
            proxyOptionSelect.innerHTML = pollData.options.map((opt, i) => `<option value="${i}">${TM.escapeHtml(opt)}</option>`).join('');
        }

        // Views count
        document.getElementById('pollViewsCount').textContent = `👁️ ${pollData.views_count} views`;

        // Footer buttons — contextual based on poll/selection/match state
        const footerBtns = document.getElementById('pollFooterBtns');
        footerBtns.innerHTML = '';

        // Admin: Close Poll button (only if still active)
        if (pollData.is_admin && pollData.status === 'active') {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn btn-secondary btn-sm text-red';
            closeBtn.textContent = '🔒 Close Poll';
            closeBtn.onclick = confirmClosePoll;
            footerBtns.appendChild(closeBtn);
        }

        // Admin: Delete Poll button
        if (pollData.is_admin) {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-secondary btn-sm text-red';
            deleteBtn.innerHTML = '🗑️ Delete Poll';
            deleteBtn.onclick = confirmDeletePoll;
            footerBtns.appendChild(deleteBtn);
        }

        const sel = pollData.linked_selection;
        const match = pollData.linked_match;

        if (match) {
            // Match exists (team selection was finalized)
            if (match.is_participant) {
                const formBtn = document.createElement('a');
                formBtn.href = `${TM.baseUrl}/formation?match_id=${match.id}`;
                formBtn.className = 'btn btn-primary btn-sm';
                formBtn.innerHTML = '📋 View Formation';
                footerBtns.appendChild(formBtn);

                const matchBtn = document.createElement('a');
                matchBtn.href = `${TM.baseUrl}/match-detail?id=${match.id}`;
                matchBtn.className = 'btn btn-secondary btn-sm';
                matchBtn.innerHTML = '⚽ Match Details';
                footerBtns.appendChild(matchBtn);
            }
        } else if (sel) {
            // Team selection exists but not finalized
            if (pollData.is_admin || pollData.is_captain) {
                const draftBtn = document.createElement('a');
                draftBtn.href = `${TM.baseUrl}/team-selection?id=${sel.id}`;
                draftBtn.className = 'btn btn-primary btn-sm';
                draftBtn.innerHTML = '⚡ View Draft Room';
                footerBtns.appendChild(draftBtn);
            } else {
                // Regular players can see draft is in progress
                const infoBtn = document.createElement('span');
                infoBtn.className = 'badge badge-warning';
                infoBtn.innerHTML = '⏳ Team Selection in progress';
                footerBtns.appendChild(infoBtn);
            }
        } else {
            // No selection yet — admin can start one
            if (pollData.is_admin) {
                const selectBtn = document.createElement('a');
                selectBtn.href = `${TM.baseUrl}/team-selection?poll_id=${pollData.id}`;
                selectBtn.className = 'btn btn-primary btn-sm';
                selectBtn.innerHTML = '⚡ Start Team Selection';
                footerBtns.appendChild(selectBtn);
            }
        }
    }

    function getPendingGuestData() {
        const guestCountDisplay = document.getElementById('guestCountDisplay');
        const guestNamesInput = document.getElementById('guestNamesInput');
        const count = guestCountDisplay ? (parseInt(guestCountDisplay.textContent, 10) || 0) : 0;
        const names = guestNamesInput ? guestNamesInput.value.split(',').map(s => s.trim()).filter(Boolean) : [];
        return { count, names };
    }

    // Initialize Stepper Event Listeners
    const btnGuestMinus = document.getElementById('btnGuestMinus');
    const btnGuestPlus = document.getElementById('btnGuestPlus');
    const guestCountDisplay = document.getElementById('guestCountDisplay');
    const guestNamesContainer = document.getElementById('guestNamesContainer');
    const guestNamesInput = document.getElementById('guestNamesInput');
    const btnUpdateGuests = document.getElementById('btnUpdateGuests');

    if (btnGuestMinus && btnGuestPlus) {
        btnGuestMinus.addEventListener('click', () => {
            let current = parseInt(guestCountDisplay.textContent, 10) || 0;
            if (current > 0) {
                current--;
                guestCountDisplay.textContent = current;
                if (guestNamesContainer) guestNamesContainer.style.display = current > 0 ? 'block' : 'none';
                if (btnUpdateGuests && pollData && pollData.my_vote !== null) {
                    btnUpdateGuests.style.display = 'inline-block';
                }
            }
        });

        btnGuestPlus.addEventListener('click', () => {
            let current = parseInt(guestCountDisplay.textContent, 10) || 0;
            current++;
            guestCountDisplay.textContent = current;
            if (guestNamesContainer) guestNamesContainer.style.display = 'block';
            if (btnUpdateGuests && pollData && pollData.my_vote !== null) {
                btnUpdateGuests.style.display = 'inline-block';
            }
        });

        if (guestNamesInput) {
            guestNamesInput.addEventListener('input', () => {
                if (btnUpdateGuests && pollData && pollData.my_vote !== null) {
                    btnUpdateGuests.style.display = 'inline-block';
                }
            });
        }

        if (btnUpdateGuests) {
            btnUpdateGuests.addEventListener('click', () => {
                if (pollData && pollData.my_vote !== null) {
                    const { count, names } = getPendingGuestData();
                    castVote(pollData.my_vote, count, names);
                }
            });
        }
    }

    async function castVote(optionIndex, guestCount = 0, guestNames = []) {
        TM.loading('Recording vote...');
        try {
            const res = await TM.api('polls.php?action=vote', {
                method: 'POST',
                body: {
                    poll_id: pollId,
                    option_index: optionIndex,
                    guest_count: guestCount,
                    guest_names: guestNames
                }
            });
            TM.stopLoading();

            if (res.success) {
                TM.toast(res.message, 'success');
                loadPoll();
            } else {
                Swal.fire({ icon: 'warning', title: 'Cannot Vote', text: res.message });
            }
        } catch (e) {
            TM.stopLoading();
            TM.toast('Failed to record vote', 'error');
        }
    }

    // Admin proxy vote
    const btnProxy = document.getElementById('btnCastProxyVote');
    if (btnProxy) {
        btnProxy.addEventListener('click', async () => {
            const targetUser = document.getElementById('proxyUserSelect').value;
            const optionIdx = parseInt(document.getElementById('proxyOptionSelect').value, 10);
            const proxyGuestInput = document.getElementById('proxyGuestCount');
            const proxyGuests = proxyGuestInput ? (parseInt(proxyGuestInput.value, 10) || 0) : 0;

            if (!targetUser) {
                TM.toast('Please select a player to vote for', 'warning');
                return;
            }

            TM.loading('Casting vote on player behalf...');
            try {
                const res = await TM.api('polls.php?action=admin_vote', {
                    method: 'POST',
                    body: {
                        poll_id: pollId,
                        user_id: targetUser,
                        option_index: optionIdx,
                        guest_count: proxyGuests
                    }
                });
                TM.stopLoading();
                if (res.success) {
                    TM.toast('Proxy vote recorded!', 'success');
                    if (proxyGuestInput) proxyGuestInput.value = '0';
                    loadPoll();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            } catch (err) {
                TM.stopLoading();
                TM.toast('Failed to record proxy vote', 'error');
            }
        });
    }

    async function confirmClosePoll() {
        const confirm = await TM.confirm('Close this poll?', 'No more votes will be accepted. You will be able to proceed directly to Team Selection.', 'warning');
        if (!confirm.isConfirmed) return;

        try {
            const res = await TM.api('polls.php?action=close', {
                method: 'POST',
                body: { poll_id: pollId }
            });
            if (res.success) {
                TM.toast('Poll closed!', 'success');
                loadPoll();
            }
        } catch (e) {
            TM.toast('Failed to close poll', 'error');
        }
    }

    async function confirmDeletePoll() {
        const confirm = await TM.confirm('Delete Poll?', 'Are you sure you want to permanently delete this poll, all votes, and comments? This action cannot be undone.', 'warning');
        if (!confirm.isConfirmed) return;

        TM.loading('Deleting poll...');
        try {
            const res = await TM.api('polls.php?action=delete', {
                method: 'POST',
                body: { id: pollId }
            });
            TM.stopLoading();
            if (res.success) {
                TM.toast('Poll deleted successfully', 'success');
                setTimeout(() => {
                    window.location.href = `${TM.baseUrl}/polls`;
                }, 600);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (e) {
            TM.stopLoading();
            TM.toast('Failed to delete poll', 'error');
        }
    }

    // Comments rendering
    function renderComments() {
        if (!pollData) return;
        const comments = pollData.comments || [];
        document.getElementById('commentCountBadge').textContent = comments.length;

        const chatBadge = document.getElementById('chatStatusBadge');
        const commentText = document.getElementById('commentText');
        const isChatAllowed = (pollData.status === 'active' || pollData.status === 'locked' || pollData.is_admin);

        if (chatBadge) {
            if (pollData.status === 'locked') {
                chatBadge.style.display = 'inline-flex';
                chatBadge.style.alignItems = 'center';
                chatBadge.className = 'badge text-xs';
                chatBadge.style.background = 'rgba(0, 230, 118, 0.12)';
                chatBadge.style.color = 'var(--accent-green)';
                chatBadge.style.border = '1px solid rgba(0, 230, 118, 0.3)';
                chatBadge.innerHTML = '<span class="pulse-dot" style="width:5px; height:5px; background:var(--accent-green); border-radius:50%; box-shadow:0 0 5px var(--accent-green); display:inline-block; margin-right:5px;"></span> 💬 Match Chat Active';
            } else if (pollData.status === 'active') {
                chatBadge.style.display = 'inline-flex';
                chatBadge.style.alignItems = 'center';
                chatBadge.className = 'badge text-xs';
                chatBadge.style.background = 'rgba(0, 230, 118, 0.12)';
                chatBadge.style.color = 'var(--accent-green)';
                chatBadge.style.border = '1px solid rgba(0, 230, 118, 0.3)';
                chatBadge.innerHTML = '<span class="pulse-dot" style="width:5px; height:5px; background:var(--accent-green); border-radius:50%; box-shadow:0 0 5px var(--accent-green); display:inline-block; margin-right:5px;"></span> ● Chat Open';
            } else {
                chatBadge.style.display = 'inline-block';
                chatBadge.className = 'badge badge-secondary text-xs';
                chatBadge.style.background = '';
                chatBadge.style.color = '';
                chatBadge.style.border = '';
                chatBadge.textContent = 'Chat Closed';
            }
        }

        if (commentText && btnSend) {
            if (!isChatAllowed) {
                commentText.disabled = true;
                commentText.placeholder = 'Comments are closed for this poll.';
                btnSend.disabled = true;
            } else {
                commentText.disabled = false;
                if (!cooldownRemaining) {
                    btnSend.disabled = false;
                }
            }
        }

        const stream = document.getElementById('commentsStream');
        if (!comments.length) {
            stream.innerHTML = '<div class="text-center text-muted py-md text-sm">No comments yet. Start the conversation!</div>';
            return;
        }

        // Latest first — reverse so newest comment is at the top
        const sorted = [...comments].reverse();
        stream.innerHTML = sorted.map(c => `
            <div class="d-flex gap-sm p-sm" style="background:var(--bg-input); border-radius:var(--radius-md);">
                <div class="user-avatar" style="width:28px; height:28px; font-size:11px; flex-shrink:0;">
                    ${c.user_name.charAt(0).toUpperCase()}
                </div>
                <div class="flex-1">
                    <div class="d-flex align-center justify-between">
                        <span class="fw-semibold text-xs text-primary">${TM.escapeHtml(c.user_name)}</span>
                        <span class="text-xs text-muted">${TM.timeAgo(c.created_at)}</span>
                    </div>
                    <div class="text-sm mt-xs" style="white-space:pre-wrap; word-break:break-word;">${TM.escapeHtml(c.text)}</div>
                </div>
            </div>
        `).join('');
    }

    // Background comment submission and debounce (no anti-spam messages shown to players)
    const commentForm = document.getElementById('commentForm');
    const commentText = document.getElementById('commentText');
    const btnSend = document.getElementById('btnSendComment');
    let isSubmittingComment = false;

    commentForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitComment();
    });

    // Ctrl+Enter shortcut to submit comment
    commentText?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            submitComment();
        }
    });

    async function submitComment(retries = 1) {
        const text = commentText.value.trim();
        if (!text || isSubmittingComment) return;

        isSubmittingComment = true;
        btnSend.disabled = true;

        try {
            const res = await TM.api('polls.php?action=comment', {
                method: 'POST',
                body: { poll_id: pollId, text: text }
            });

            if (res.success) {
                commentText.value = '';
                loadPoll();
                startBgCooldown(2);
            } else if (res.message && res.message.toLowerCase().includes('wait') && retries > 0) {
                // Background cooldown hit: quietly wait and retry in background without displaying any anti-spam msg
                setTimeout(async () => {
                    isSubmittingComment = false;
                    await submitComment(retries - 1);
                }, 1500);
                return;
            } else {
                if (!res.message || !res.message.toLowerCase().includes('wait')) {
                    TM.toast(res.message || 'Could not post comment', 'error');
                }
                isSubmittingComment = false;
                btnSend.disabled = false;
            }
        } catch (err) {
            isSubmittingComment = false;
            btnSend.disabled = false;
        }
    }

    function startBgCooldown(seconds) {
        cooldownRemaining = seconds;
        btnSend.disabled = true;
        clearInterval(cooldownTimer);
        cooldownTimer = setInterval(() => {
            cooldownRemaining--;
            if (cooldownRemaining <= 0) {
                clearInterval(cooldownTimer);
                isSubmittingComment = false;
                btnSend.disabled = false;
            }
        }, 1000);
    }

    // Unvoted viewers modal
    const unvotedModal = document.getElementById('unvotedModal');
    const btnViewUnvoted = document.getElementById('btnViewUnvoted');
    const unvotedList = document.getElementById('unvotedList');

    if (btnViewUnvoted) {
        btnViewUnvoted.addEventListener('click', async () => {
            unvotedModal.classList.add('open');
            unvotedList.innerHTML = '<div class="text-center py-md"><div class="loading-spinner"></div></div>';

            try {
                const res = await TM.api(`polls.php?action=unvoted_viewers&poll_id=${pollId}`);
                if (res.success) {
                    if (!res.unvoted_viewers.length) {
                        unvotedList.innerHTML = '<p class="text-sm text-green text-center py-md">✓ All players who opened this poll have cast their vote!</p>';
                    } else {
                        unvotedList.innerHTML = res.unvoted_viewers.map(u => `
                            <div class="d-flex align-center justify-between p-sm" style="background:var(--bg-card); border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                <span class="fw-semibold text-sm">👤 ${TM.escapeHtml(u.name)}</span>
                                <span class="text-xs text-muted">DOB: ${(window.TM?.formatDob ? TM.formatDob(u.dob) : u.dob) || 'N/A'}</span>
                            </div>
                        `).join('');
                    }
                }
            } catch (err) {
                unvotedList.innerHTML = '<p class="text-sm text-red">Failed to load viewer status</p>';
            }
        });

        document.getElementById('closeUnvotedModal')?.addEventListener('click', () => unvotedModal.classList.remove('open'));
        document.getElementById('btnCloseUnvoted')?.addEventListener('click', () => unvotedModal.classList.remove('open'));
    }

    loadPoll();

    // Auto-update live without page refresh every 4 seconds
    setInterval(() => loadPoll(true), 4000);

    // Live refresh on incoming notification or when tab regains focus
    window.addEventListener('tm:notification', () => loadPoll(true));
    window.addEventListener('focus', () => loadPoll(true));
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
