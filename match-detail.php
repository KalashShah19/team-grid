<?php
/**
 * Match Detail Page
 * Match details, players count, captains listed first with CSS-only captain badges,
 * pitch formations for both teams, optional scores (hidden if not set), and optional goals.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$matchId = $_GET['id'] ?? '';
if (!$matchId) {
    header('Location: ' . BASE_URL . '/matches');
    exit;
}

$pageTitle = 'Match Details';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';

$allUsers = getUsers();
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-md flex-wrap gap-sm" style="align-items: flex-start;">
        <div style="min-width:0; flex:1;">
            <div class="d-flex align-center gap-xs flex-wrap">
                <a href="<?php echo BASE_URL; ?>/matches" class="btn btn-secondary btn-xs">← Matches</a>
                <h1 class="page-title" style="font-size: var(--font-size-xl); margin-bottom: 0;" id="matchTitle">Match Details</h1>
            </div>
            <p class="text-secondary text-sm" id="matchMeta">Loading fixture...</p>
        </div>

        <div class="d-flex gap-sm flex-wrap" id="adminMatchActions">
            <a href="<?php echo BASE_URL; ?>/formation?match_id=<?php echo htmlspecialchars($matchId); ?>" class="btn btn-secondary btn-sm">
                📋 Formation
            </a>
            <?php if ($isAdminUser): ?>
                <button class="btn btn-primary btn-sm" id="btnEditScore">
                    ✏️ Update Score
                </button>
                <button class="btn btn-secondary btn-sm text-red" id="btnDeleteMatch">
                    🗑️ Delete Match
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Optional Score Banner (Hidden if no score) -->
    <div id="scoreBanner" style="display:none;" class="card p-md mb-lg text-center">
        <div class="d-flex align-center justify-center gap-md flex-wrap">
            <div class="text-right flex-1" style="min-width:80px;">
                <h3 class="text-red" id="scoreTeamAName" style="font-size:clamp(0.9rem,3vw,1.25rem);">Team A</h3>
            </div>
            <div class="match-score-pill" style="font-size: clamp(20px, 5vw, 28px); padding: 6px 20px;" id="scoreNumbers">0 - 0</div>
            <div class="text-left flex-1" style="min-width:80px;">
                <h3 class="text-blue" id="scoreTeamBName" style="font-size:clamp(0.9rem,3vw,1.25rem);">Team B</h3>
            </div>
        </div>
        <!-- Optional Goals Section -->
        <div id="goalsSection" style="display:none;" class="mt-md pt-sm" style="border-top:1px solid var(--border-color);">
            <div class="d-flex justify-center gap-md flex-wrap text-xs text-secondary" id="goalsList"></div>
        </div>
    </div>

    <!-- Rosters & Formations Grid -->
    <div class="d-grid gap-md match-detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <!-- Team A Column -->
        <div class="card p-md team-card team-a">
            <div class="d-flex align-center justify-between mb-md">
                <h3 class="text-red" id="teamAHeaderTitle">Team A</h3>
                <span class="badge badge-secondary" id="teamAPlayerCount">0 players</span>
            </div>

            <h4 class="text-xs fw-bold text-muted mb-sm">SQUAD ROSTER (CAPTAIN LISTED FIRST)</h4>
            <div class="d-grid gap-xs mb-lg" id="teamARosterList"></div>

            <div class="d-flex align-center justify-between mb-sm">
                <h4 class="text-xs fw-bold text-muted mb-0">TACTICAL FORMATION</h4>
                <span id="teamAFormationBadge"></span>
            </div>
            <div style="position:relative; width:100%; aspect-ratio: 1 / 1.1; background:#1b5e20; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.4); overflow:hidden;">
                <div style="position:absolute; top:50%; left:50%; width:24%; aspect-ratio:1; border:1px solid rgba(255,255,255,0.4); border-radius:50%; transform:translate(-50%,-50%);"></div>
                <div style="position:absolute; top:50%; left:0; right:0; height:1px; background:rgba(255,255,255,0.4);"></div>
                <div id="teamAPitchMini" style="position:absolute; inset:0; overflow:hidden;"></div>
            </div>
        </div>

        <!-- Team B Column -->
        <div class="card p-md team-card team-b">
            <div class="d-flex align-center justify-between mb-md">
                <h3 class="text-blue" id="teamBHeaderTitle">Team B</h3>
                <span class="badge badge-secondary" id="teamBPlayerCount">0 players</span>
            </div>

            <h4 class="text-xs fw-bold text-muted mb-sm">SQUAD ROSTER (CAPTAIN LISTED FIRST)</h4>
            <div class="d-grid gap-xs mb-lg" id="teamBRosterList"></div>

            <div class="d-flex align-center justify-between mb-sm">
                <h4 class="text-xs fw-bold text-muted mb-0">TACTICAL FORMATION</h4>
                <span id="teamBFormationBadge"></span>
            </div>
            <div style="position:relative; width:100%; aspect-ratio: 1 / 1.1; background:#1b5e20; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.4); overflow:hidden;">
                <div style="position:absolute; top:50%; left:50%; width:24%; aspect-ratio:1; border:1px solid rgba(255,255,255,0.4); border-radius:50%; transform:translate(-50%,-50%);"></div>
                <div style="position:absolute; top:50%; left:0; right:0; height:1px; background:rgba(255,255,255,0.4);"></div>
                <div id="teamBPitchMini" style="position:absolute; inset:0; overflow:hidden;"></div>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdminUser): ?>
<!-- Edit Score & Goals Modal -->
<div class="modal-overlay" id="scoreModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Record Match Score & Goals</h3>
            <button class="modal-close" id="closeScoreModal">&times;</button>
        </div>
        <form id="scoreForm">
            <div class="modal-body">
                <p class="text-xs text-muted mb-md">Scores are optional. If left blank, the score banner is completely hidden.</p>
                <div class="form-row mb-md">
                    <div class="form-group">
                        <label class="form-label" id="modalScoreALabel">Team A Score</label>
                        <input type="number" class="form-input" id="inputScoreA" min="0" max="99" placeholder="Leave blank if unset">
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="modalScoreBLabel">Team B Score</label>
                        <input type="number" class="form-input" id="inputScoreB" min="0" max="99" placeholder="Leave blank if unset">
                    </div>
                </div>

                <div class="form-group mb-sm">
                    <div class="d-flex align-center justify-between mb-xs">
                        <label class="form-label mb-0">Goal Scorers (Optional)</label>
                        <button type="button" class="btn btn-secondary btn-xs" id="btnAddGoal">➕ Add Goal</button>
                    </div>
                    <div class="d-grid gap-xs" id="goalsInputsContainer"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelScoreModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Result</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const matchId = '<?php echo $matchId; ?>';
    let matchData = null;

    async function loadMatch() {
        try {
            const res = await TM.api(`matches.php?action=get&id=${matchId}`);
            if (res.success) {
                matchData = res.match;
                renderMatch();
            } else {
                TM.toast(res.message, 'error');
            }
        } catch (e) {
            TM.toast('Failed to load match details', 'error');
        }
    }

    function renderMatch() {
        if (!matchData) return;
        const m = matchData;

        document.getElementById('matchTitle').textContent = m.title;
        let dateDisplay = 'Upcoming';
        if (m.date) {
            const d = new Date(m.date);
            dateDisplay = !isNaN(d.getTime()) ? TM.formatDate(m.date, {year:'numeric', month:'short', day:'numeric'}) : TM.escapeHtml(m.date);
        }
        const fallbackTurf = (m.format && m.format.includes('8')) ? 'valas old' : ((m.format && m.format.includes('7')) ? 'valas pro' : '99 not out');
        document.getElementById('matchMeta').innerHTML = `
            <span>📅 ${dateDisplay}</span> &bull; 
            <span>🏟️ ${TM.escapeHtml(m.venue || fallbackTurf)}</span> &bull; 
            <span class="badge badge-secondary">${TM.escapeHtml(m.format || '6v6')}</span>
        `;

        // Scores (OPTIONAL: only show if present, otherwise completely hide section!)
        const scoreBanner = document.getElementById('scoreBanner');
        if (m.has_score) {
            scoreBanner.style.display = 'block';
            document.getElementById('scoreTeamAName').textContent = m.team_a.name;
            document.getElementById('scoreTeamBName').textContent = m.team_b.name;
            document.getElementById('scoreNumbers').textContent = `${m.team_a.score} - ${m.team_b.score}`;

            // Goals (OPTIONAL: only show if present)
            const goalsSec = document.getElementById('goalsSection');
            if (m.has_goals) {
                goalsSec.style.display = 'block';
                document.getElementById('goalsList').innerHTML = m.goals.map(g => `
                    <span>⚽ ${TM.escapeHtml(g.player_name || 'Player')} ${g.minute ? `(${g.minute}')` : ''} [${TM.escapeHtml(g.team_name || '')}]</span>
                `).join(' &bull; ');
            } else {
                goalsSec.style.display = 'none';
            }
        } else {
            scoreBanner.style.display = 'none';
        }

        // Team A details (Captain listed FIRST with captain badge!)
        document.getElementById('teamAHeaderTitle').textContent = m.team_a.name;
        document.getElementById('teamAPlayerCount').textContent = `${m.team_a.roster.length} players`;
        document.getElementById('teamARosterList').innerHTML = m.team_a.roster.map(p => `
            <div class="player-pick-item" style="cursor:default;">
                <span class="d-flex align-center gap-xs">
                    <span>${p.is_captain ? '👑' : '⚽'} ${TM.escapeHtml(p.name)}</span>
                    ${p.is_captain ? '<span class="captain-badge" title="Team Captain"></span>' : ''}
                </span>
                ${p.is_sub ? '<span class="badge badge-warning text-xs">Sub</span>' : '<span class="text-xs text-muted">Starter</span>'}
            </div>
        `).join('');

        const badgeA = document.getElementById('teamAFormationBadge');
        if (badgeA) {
            badgeA.innerHTML = m.team_a.formation_saved
                ? `<span class="badge badge-success text-xs">✓ Captain Set</span>`
                : `<span class="badge text-xs" style="background:linear-gradient(135deg, #7c4dff, #00b0ff); color:#fff; font-weight:700;">🤖 AI Generated</span>`;
        }

        renderMiniPitch('teamAPitchMini', m.team_a.formation, 'team-a-token');

        // Team B details (Captain listed FIRST with captain badge!)
        document.getElementById('teamBHeaderTitle').textContent = m.team_b.name;
        document.getElementById('teamBPlayerCount').textContent = `${m.team_b.roster.length} players`;
        document.getElementById('teamBRosterList').innerHTML = m.team_b.roster.map(p => `
            <div class="player-pick-item" style="cursor:default;">
                <span class="d-flex align-center gap-xs">
                    <span>${p.is_captain ? '👑' : '⚽'} ${TM.escapeHtml(p.name)}</span>
                    ${p.is_captain ? '<span class="captain-badge" title="Team Captain"></span>' : ''}
                </span>
                ${p.is_sub ? '<span class="badge badge-warning text-xs">Sub</span>' : '<span class="text-xs text-muted">Starter</span>'}
            </div>
        `).join('');

        const badgeB = document.getElementById('teamBFormationBadge');
        if (badgeB) {
            badgeB.innerHTML = m.team_b.formation_saved
                ? `<span class="badge badge-success text-xs">✓ Captain Set</span>`
                : `<span class="badge text-xs" style="background:linear-gradient(135deg, #7c4dff, #00b0ff); color:#fff; font-weight:700;">🤖 AI Generated</span>`;
        }

        renderMiniPitch('teamBPitchMini', m.team_b.formation, 'team-b-token');

        // Link to Match Chat / Poll if fixture was born from a poll
        if (m.poll_id) {
            let chatBtn = document.getElementById('btnMatchChat');
            if (!chatBtn) {
                chatBtn = document.createElement('a');
                chatBtn.id = 'btnMatchChat';
                chatBtn.className = 'btn btn-secondary btn-sm';
                chatBtn.innerHTML = '💬 Match Chat';
                const actionsArea = document.getElementById('adminMatchActions');
                if (actionsArea) {
                    actionsArea.prepend(chatBtn);
                }
            }
            chatBtn.href = `${TM.baseUrl}/poll-detail?id=${m.poll_id}`;
        }
    }

    function renderMiniPitch(containerId, positions, tokenClass) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        if (!positions || !positions.length) {
            container.innerHTML = '<div class="d-flex align-center justify-center text-xs text-muted" style="height:100%;">Formation not set</div>';
            return;
        }
        positions.forEach(pos => {
            const dot = document.createElement('div');
            dot.style.cssText = `position:absolute; left:${pos.x}%; top:${pos.y}%; width:clamp(16px,3.5%,22px); height:clamp(16px,3.5%,22px); border-radius:50%; transform:translate(-50%,-50%); z-index:2;`;
            dot.style.background = tokenClass === 'team-a-token' ? '#e53935' : '#1e88e5';
            dot.style.border = tokenClass === 'team-a-token' ? '2px solid #ef5350' : '2px solid #42a5f5';
            dot.style.boxShadow = '0 2px 6px rgba(0,0,0,0.4)';
            container.appendChild(dot);
        });
    }

    // Admin Score Edit
    const scoreModal = document.getElementById('scoreModal');
    const btnEditScore = document.getElementById('btnEditScore');
    const scoreForm = document.getElementById('scoreForm');
    const goalsContainer = document.getElementById('goalsInputsContainer');

    if (btnEditScore) {
        btnEditScore.addEventListener('click', () => {
            scoreModal.classList.add('open');
            document.getElementById('modalScoreALabel').textContent = `${matchData.team_a.name} Score`;
            document.getElementById('modalScoreBLabel').textContent = `${matchData.team_b.name} Score`;
            document.getElementById('inputScoreA').value = matchData.team_a.score !== null ? matchData.team_a.score : '';
            document.getElementById('inputScoreB').value = matchData.team_b.score !== null ? matchData.team_b.score : '';

            goalsContainer.innerHTML = '';
            (matchData.goals || []).forEach(g => addGoalInputRow(g));
        });

        document.getElementById('closeScoreModal')?.addEventListener('click', () => scoreModal.classList.remove('open'));
        document.getElementById('cancelScoreModal')?.addEventListener('click', () => scoreModal.classList.remove('open'));

        document.getElementById('btnAddGoal')?.addEventListener('click', () => addGoalInputRow());

        scoreForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const valA = document.getElementById('inputScoreA').value.trim();
            const valB = document.getElementById('inputScoreB').value.trim();

            const goals = [];
            goalsContainer.querySelectorAll('.goal-row').forEach(row => {
                const name = row.querySelector('.goal-player-name').value.trim();
                const min = row.querySelector('.goal-minute').value.trim();
                const team = row.querySelector('.goal-team').value;
                if (name) {
                    goals.push({ player_name: name, minute: min || null, team_name: team });
                }
            });

            TM.loading('Saving match result...');
            try {
                const res = await TM.api('matches.php?action=update_score', {
                    method: 'POST',
                    body: {
                        id: matchId,
                        score_a: valA === '' ? null : parseInt(valA, 10),
                        score_b: valB === '' ? null : parseInt(valB, 10),
                        goals: goals,
                    }
                });
                TM.stopLoading();

                if (res.success) {
                    scoreModal.classList.remove('open');
                    TM.toast(res.message, 'success');
                    loadMatch();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            } catch (err) {
                TM.stopLoading();
                TM.toast('Failed to update scores', 'error');
            }
        });
        document.getElementById('btnDeleteMatch')?.addEventListener('click', async () => {
            const confirm = await TM.confirm('Delete Match?', 'Are you sure you want to permanently delete this match, formation, and score records? This action cannot be undone.', 'warning');
            if (!confirm.isConfirmed) return;

            TM.loading('Deleting match...');
            try {
                const res = await TM.api('matches.php?action=delete', {
                    method: 'POST',
                    body: { id: matchId }
                });
                TM.stopLoading();
                if (res.success) {
                    TM.toast('Match deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = `${TM.baseUrl}/matches`;
                    }, 600);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            } catch (err) {
                TM.stopLoading();
                TM.toast('Failed to delete match', 'error');
            }
        });
    }

    function addGoalInputRow(existing = null) {
        const row = document.createElement('div');
        row.className = 'goal-row d-flex gap-xs align-center mb-xs';
        row.innerHTML = `
            <input type="text" class="form-input form-input-sm goal-player-name flex-1" placeholder="Scorer Name" value="${existing ? TM.escapeHtml(existing.player_name || '') : ''}" required>
            <input type="number" class="form-input form-input-sm goal-minute" style="width:70px;" placeholder="Min" value="${existing ? (existing.minute || '') : ''}">
            <select class="form-select form-select-sm goal-team" style="width:110px;">
                <option value="${matchData.team_a.name}">${matchData.team_a.name}</option>
                <option value="${matchData.team_b.name}">${matchData.team_b.name}</option>
            </select>
            <button type="button" class="btn btn-secondary btn-xs" onclick="this.parentElement.remove()">✕</button>
        `;
        if (existing && existing.team_name === matchData.team_b.name) {
            row.querySelector('.goal-team').value = matchData.team_b.name;
        }
        goalsContainer.appendChild(row);
    }

    loadMatch();
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
