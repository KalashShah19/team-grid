<?php
/**
 * Formation Page
 * Interactive tactical football pitch with white jersey tokens,
 * per-match editable jersey numbers, conflict detection,
 * drag-and-drop positioning, substitutes bench, captain saving.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$matchId = $_GET['match_id'] ?? '';
if (!$matchId) {
    header('Location: ' . BASE_URL . '/matches');
    exit;
}

$pageTitle = 'Tactical Formation';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-md flex-wrap gap-sm">
        <div>
            <div class="d-flex align-center gap-xs">
                <a href="<?php echo BASE_URL; ?>/matches" class="btn btn-secondary btn-xs">← Matches</a>
                <h1 class="page-title" style="font-size: var(--font-size-xl); margin-bottom: 0;" id="formationTitle">Tactical Board</h1>
            </div>
            <p class="text-secondary text-sm" id="formationSubtitle">Pitch tactical lineups &amp; substitutes</p>
        </div>
        <div id="formationActionArea" class="d-flex gap-sm"></div>
    </div>

    <!-- Team Switcher Tabs -->
    <div class="tabs mb-md" id="teamTabs" style="display:none;">
        <button class="tab active" id="tabTeamA" data-team="team_a">Team A</button>
        <button class="tab" id="tabTeamB" data-team="team_b">Team B</button>
    </div>

    <!-- Formation Grid -->
    <div class="d-grid gap-md" style="grid-template-columns: 1fr 300px;" id="pitchLayoutGrid">
        <!-- Football Pitch Card -->
        <div class="card p-md" style="background:var(--bg-secondary); overflow:hidden;">
            <div class="d-flex align-center justify-between mb-sm">
                <div class="d-flex align-center gap-xs">
                    <span class="fw-bold" id="activeTeamNameDisplay">Team Lineup</span>
                    <span class="badge badge-primary" id="pitchCountBadge" style="font-size:10px;">0 on pitch</span>
                    <span class="badge badge-success ml-xs" id="savedBadge" style="display:none;">✓ Locked</span>
                    <span class="badge ml-xs" id="aiBadge" style="display:none; background:linear-gradient(135deg, #7c4dff, #00b0ff); color:#fff; font-weight:700;">🤖 AI Generated Formation</span>
                </div>
                <span class="text-xs text-muted" id="pitchHelpText">Optional: Presets applied by default · Drag to adjust</span>
            </div>
            <div style="position:relative; max-width:650px; margin:0 auto;">
                <div class="football-field" id="tacticalPitch" style="position:relative; width:100%; aspect-ratio: 1 / 1.35; padding:0; background: #1b5e20; border:2px solid rgba(255,255,255,0.6); border-radius:12px; overflow:hidden; user-select:none;">
                    <!-- Pitch markings -->
                    <div style="position:absolute; top:50%; left:50%; width:24%; aspect-ratio:1; border:2px solid rgba(255,255,255,0.5); border-radius:50%; transform:translate(-50%,-50%); pointer-events:none;"></div>
                    <div style="position:absolute; top:50%; left:0; right:0; height:2px; background:rgba(255,255,255,0.5); pointer-events:none;"></div>
                    <div style="position:absolute; top:50%; left:50%; width:6px; height:6px; background:#fff; border-radius:50%; transform:translate(-50%,-50%); pointer-events:none;"></div>
                    <div style="position:absolute; top:0; left:50%; transform:translateX(-50%); width:52%; height:16%; border:2px solid rgba(255,255,255,0.5); border-top:none; pointer-events:none;"></div>
                    <div style="position:absolute; top:0; left:50%; transform:translateX(-50%); width:26%; height:7%; border:2px solid rgba(255,255,255,0.5); border-top:none; pointer-events:none;"></div>
                    <div style="position:absolute; top:11%; left:50%; width:4px; height:4px; background:#fff; border-radius:50%; transform:translate(-50%,-50%); pointer-events:none;"></div>
                    <div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:52%; height:16%; border:2px solid rgba(255,255,255,0.5); border-bottom:none; pointer-events:none;"></div>
                    <div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:26%; height:7%; border:2px solid rgba(255,255,255,0.5); border-bottom:none; pointer-events:none;"></div>
                    <div style="position:absolute; bottom:11%; left:50%; width:4px; height:4px; background:#fff; border-radius:50%; transform:translate(-50%,-50%); pointer-events:none;"></div>
                    <div style="position:absolute; top:0; left:0; width:16px; height:16px; border-bottom:2px solid rgba(255,255,255,0.5); border-right:2px solid rgba(255,255,255,0.5); border-bottom-right-radius:16px; pointer-events:none;"></div>
                    <div style="position:absolute; top:0; right:0; width:16px; height:16px; border-bottom:2px solid rgba(255,255,255,0.5); border-left:2px solid rgba(255,255,255,0.5); border-bottom-left-radius:16px; pointer-events:none;"></div>
                    <div style="position:absolute; bottom:0; left:0; width:16px; height:16px; border-top:2px solid rgba(255,255,255,0.5); border-right:2px solid rgba(255,255,255,0.5); border-top-right-radius:16px; pointer-events:none;"></div>
                    <div style="position:absolute; bottom:0; right:0; width:16px; height:16px; border-top:2px solid rgba(255,255,255,0.5); border-left:2px solid rgba(255,255,255,0.5); border-top-left-radius:16px; pointer-events:none;"></div>
                    <div id="pitchTokensContainer"></div>
                </div>
            </div>
        </div>

        <!-- Squad & Subs Sidebar -->
        <div class="d-grid gap-md" style="align-content: start;">
            <div class="card p-md" style="background:var(--bg-secondary);">
                <h4 class="text-xs fw-bold text-muted mb-sm">SQUAD PLAYERS (<span id="squadCount">0</span>)</h4>
                <p class="text-xs text-muted mb-sm" id="squadInstruction">Select player, then click pitch to place:</p>
                <div class="d-grid gap-xs" id="squadList" style="max-height:220px; overflow-y:auto;"></div>
            </div>
            <div class="card p-md" style="background:var(--bg-secondary); border-left:3px solid var(--accent-orange);">
                <div class="d-flex align-center justify-between mb-sm">
                    <h4 class="text-xs fw-bold text-orange">🪑 SUBSTITUTES BENCH</h4>
                    <span class="badge badge-warning" id="subsCountBadge">0</span>
                </div>
                <p class="text-xs text-muted mb-sm">Players not on the tactical pitch will serve as subs:</p>
                <div class="d-grid gap-xs" id="subsList"></div>
            </div>
        </div>
    </div>
</div>

<!-- Jersey Number Edit Modal -->
<div class="modal-overlay" id="jerseyModal">
    <div class="modal" style="max-width:320px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Jersey Number</h3>
            <button class="modal-close" id="closeJerseyModal">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-secondary mb-md" id="jerseyModalPlayerName"></p>
            <div class="form-group">
                <label class="form-label">Jersey Number (1-99)</label>
                <input type="number" class="form-input" id="jerseyNumberInput" min="1" max="99" placeholder="e.g. 10">
                <span class="text-xs text-muted mt-xs" style="display:block;">This sets the number for this match. Your own number also saves as default for future matches.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" id="btnCancelJersey">Cancel</button>
            <button class="btn btn-primary btn-sm" id="btnSaveJersey">💾 Save</button>
        </div>
    </div>
</div>

<style>
@media (max-width: 860px) {
    #pitchLayoutGrid { grid-template-columns: 1fr !important; }
}

/* ===== Jersey Token ===== */
.pitch-token {
    position: absolute;
    width: 54px;
    height: 68px;
    transform: translate(-50%, -50%);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
    z-index: 10;
    user-select: none;
    transition: transform 0.12s ease;
}
.pitch-token:active {
    cursor: grabbing;
    transform: translate(-50%, -50%) scale(1.12);
}
.pitch-token .jersey-wrap {
    position: relative;
    width: 50px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pitch-token svg.jersey-svg {
    width: 50px;
    height: 54px;
    filter: drop-shadow(0 3px 8px rgba(0,0,0,0.65));
    display: block;
}
.pitch-token .jersey-num {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -42%);
    font-size: 16px;
    font-weight: 900;
    color: #111;
    line-height: 1;
    pointer-events: none;
    font-family: 'Arial Black', Arial, sans-serif;
    text-align: center;
    z-index: 2;
}
.pitch-token-name {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    font-weight: 700;
    white-space: nowrap;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.9), 0 0 5px rgba(0,0,0,0.7);
    pointer-events: none;
    max-width: 72px;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}
.pitch-token .edit-jersey-btn {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    background: var(--accent-gold);
    border-radius: 50%;
    border: none;
    cursor: pointer;
    font-size: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
    z-index: 12;
    box-shadow: 0 1px 4px rgba(0,0,0,0.5);
    padding: 0;
    line-height: 1;
}
.pitch-token .edit-jersey-btn:hover {
    background: var(--accent-orange);
    transform: scale(1.15);
}

.squad-player-btn {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: var(--radius-md); cursor: pointer; font-size: var(--font-size-sm);
    transition: all var(--transition-fast);
}
.squad-player-btn:hover { border-color: var(--accent-green); background: var(--bg-elevated); }
.squad-player-btn.selected { border-color: var(--accent-green); background: var(--accent-green-glow); font-weight: 600; }
.squad-player-btn.placed { opacity: 0.5; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const matchId = '<?php echo $matchId; ?>';
    const currentUserId = '<?php echo $_SESSION['user_id']; ?>';
    let formationData = null;
    let activeTeam = 'team_a';
    let selectedPlayerToPlace = null;
    let currentPositions = [];
    let editingJerseyForPlayer = null;

    const pitch = document.getElementById('tacticalPitch');
    const pitchTokensContainer = document.getElementById('pitchTokensContainer');

    // ---- Jersey SVG ----
    function jerseySvg() {
        return `<svg class="jersey-svg" viewBox="0 0 100 110" xmlns="http://www.w3.org/2000/svg">
            <path d="M28 12 L8 28 L4 58 L24 62 L24 102 L76 102 L76 62 L96 58 L92 28 L72 12
                     Q62 24 50 20 Q38 24 28 12 Z"
                  fill="#ffffff" stroke="#bbb" stroke-width="2.5" stroke-linejoin="round"/>
            <path d="M36 12 Q50 4 64 12" stroke="#999" stroke-width="2" fill="none" stroke-linecap="round"/>
        </svg>`;
    }

    async function loadFormation() {
        try {
            const res = await TM.api(`formation.php?action=get&match_id=${matchId}`);
            if (res.success) {
                formationData = res.formation;
                initTeamTabs();
                switchTeamView(activeTeam);
            } else {
                Swal.fire({ icon: 'error', title: 'Restricted', text: res.message }).then(() => {
                    window.location.href = `${TM.baseUrl}/matches`;
                });
            }
        } catch (e) {
            TM.toast('Failed to load tactical formation', 'error');
        }
    }

    function initTeamTabs() {
        const teamA = formationData.team_a;
        const teamB = formationData.team_b;
        if (teamA.visible && !teamB.visible) activeTeam = 'team_a';
        else if (!teamA.visible && teamB.visible) activeTeam = 'team_b';
        else if (teamA.visible && teamB.visible) {
            document.getElementById('teamTabs').style.display = 'flex';
            document.getElementById('tabTeamA').textContent = teamA.name;
            document.getElementById('tabTeamB').textContent = teamB.name;
        }
        document.getElementById('tabTeamA').addEventListener('click', () => switchTeamView('team_a'));
        document.getElementById('tabTeamB').addEventListener('click', () => switchTeamView('team_b'));
    }

    function switchTeamView(teamKey) {
        activeTeam = teamKey;
        const team = formationData[teamKey];
        document.querySelectorAll('#teamTabs .tab').forEach(t => t.classList.toggle('active', t.dataset.team === teamKey));
        document.getElementById('formationTitle').textContent = `${formationData.match_title} (${formationData.format})`;
        document.getElementById('activeTeamNameDisplay').textContent = team.name;
        currentPositions = JSON.parse(JSON.stringify(team.positions || []));
        const canEdit = (teamKey === 'team_a' && formationData.can_edit_a) || (teamKey === 'team_b' && formationData.can_edit_b);
        const actionArea = document.getElementById('formationActionArea');
        actionArea.innerHTML = '';
        if (canEdit) {
            const autoBtn = document.createElement('button');
            autoBtn.className = 'btn btn-secondary btn-sm mr-xs';
            autoBtn.innerHTML = '⚡ Auto-Plot Formation';
            autoBtn.onclick = autoPlotFormation;
            actionArea.appendChild(autoBtn);

            const saveBtn = document.createElement('button');
            saveBtn.className = 'btn btn-primary btn-sm';
            saveBtn.innerHTML = '💾 Save Formation';
            saveBtn.onclick = saveFormation;
            actionArea.appendChild(saveBtn);
        }
        const isSaved = !!team.saved;
        const savedBadge = document.getElementById('savedBadge');
        const aiBadge = document.getElementById('aiBadge');
        const pitchHelp = document.getElementById('pitchHelpText');

        if (isSaved) {
            if (savedBadge) {
                savedBadge.textContent = '✓ Captain Set';
                savedBadge.style.display = 'inline-flex';
            }
            if (aiBadge) aiBadge.style.display = 'none';
            if (pitchHelp) pitchHelp.textContent = canEdit ? 'Saved by captain · Drag to adjust and re-save' : 'Custom formation saved by team captain';
        } else {
            if (savedBadge) savedBadge.style.display = 'none';
            if (aiBadge) {
                aiBadge.textContent = '🤖 AI Generated Formation';
                aiBadge.style.display = 'inline-flex';
            }
            if (pitchHelp) pitchHelp.textContent = canEdit ? 'Captain has not set formation · Displaying AI Generated Formation · Drag and click Save to customize' : 'Captain has not set formation · Displaying AI Generated Formation';
        }
        renderPitch();
        renderSquadList();
        renderSubsList();
    }

    function autoPlotFormation() {
        const team = formationData[activeTeam];
        const format = (formationData.format || '6v6').toLowerCase();
        let slots = [];
        if (format.includes('7')) {
            // 7-a-side: 2-2-2
            slots = [
                { x: 50, y: 88, role: 'GK' },
                { x: 28, y: 68, role: 'DEF' },
                { x: 72, y: 68, role: 'DEF' },
                { x: 32, y: 48, role: 'MID' },
                { x: 68, y: 48, role: 'MID' },
                { x: 30, y: 24, role: 'FWD' },
                { x: 70, y: 24, role: 'FWD' },
            ];
        } else if (format.includes('8')) {
            // 8-a-side: 3-2-1
            slots = [
                { x: 50, y: 88, role: 'GK' },
                { x: 22, y: 68, role: 'DEF' },
                { x: 50, y: 70, role: 'DEF' },
                { x: 78, y: 68, role: 'DEF' },
                { x: 34, y: 48, role: 'MID' },
                { x: 66, y: 48, role: 'MID' },
                { x: 35, y: 24, role: 'FWD' },
                { x: 65, y: 24, role: 'FWD' },
            ];
        } else {
            // 6-a-side: 2-1-2
            slots = [
                { x: 50, y: 88, role: 'GK' },
                { x: 28, y: 68, role: 'DEF' },
                { x: 72, y: 68, role: 'DEF' },
                { x: 50, y: 48, role: 'MID' },
                { x: 30, y: 24, role: 'FWD' },
                { x: 70, y: 24, role: 'FWD' },
            ];
        }

        const playerIds = team.all_players.map(p => p.id);
        const arranged = [];
        if (team.captain_id && playerIds.includes(team.captain_id)) {
            arranged.push(team.captain_id);
        }
        playerIds.forEach(pid => {
            if (!arranged.includes(pid)) arranged.push(pid);
        });

        currentPositions = [];
        slots.forEach((s, i) => {
            if (i < arranged.length) {
                currentPositions.push({ player_id: arranged[i], x: s.x, y: s.y, role: s.role });
            }
        });

        renderPitch();
        renderSquadList();
        renderSubsList();
        TM.toast('Auto-plotted preset formation! Click Save to keep this layout.', 'info');
    }

    function getEffectiveJersey(playerId) {
        const team = formationData[activeTeam];
        const p = team.all_players.find(pl => pl.id === playerId);
        const num = p?.jersey_number ?? null;
        if (num === null) return '';
        // Check if conflicted
        const conflicts = team.jersey_conflicts || [];
        if (conflicts.includes(num)) return '';
        return num;
    }

    function renderPitch() {
        const team = formationData[activeTeam];
        pitchTokensContainer.innerHTML = '';
        const canEdit = (activeTeam === 'team_a' && formationData.can_edit_a) || (activeTeam === 'team_b' && formationData.can_edit_b);
        const isInThisTeam = team.all_players.some(p => p.id === currentUserId);

        const formatMatch = (formationData.format || '6v6').match(/^(\d+)/);
        const maxPlayers = formatMatch ? parseInt(formatMatch[1], 10) : 6;
        const countBadge = document.getElementById('pitchCountBadge');
        if (countBadge) {
            countBadge.textContent = `${currentPositions.length} / ${maxPlayers} on pitch`;
            countBadge.className = (currentPositions.length === maxPlayers) ? 'badge badge-success' : 'badge badge-primary';
        }

        currentPositions.forEach((pos, idx) => {
            const playerObj = team.all_players.find(p => p.id === pos.player_id);
            const name = playerObj ? playerObj.name : 'Player';
            const jerseyNum = getEffectiveJersey(pos.player_id);
            const isCap = (pos.player_id === team.captain_id);
            const isOwn = (pos.player_id === currentUserId);
            const canEditThisJersey = canEdit || (isOwn && isInThisTeam);

            const token = document.createElement('div');
            token.className = 'pitch-token';
            token.style.left = `${pos.x}%`;
            token.style.top = `${pos.y}%`;
            token.dataset.playerId = pos.player_id;

            token.innerHTML = `
                <div class="jersey-wrap">
                    ${jerseySvg()}
                    <span class="jersey-num">${jerseyNum}</span>
                </div>
                <span class="pitch-token-name">${TM.escapeHtml(name)}${isCap ? ' ©' : ''}</span>
                ${canEditThisJersey ? `<button class="edit-jersey-btn" title="Edit jersey #">✏</button>` : ''}
            `;

            if (canEditThisJersey) {
                token.querySelector('.edit-jersey-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    const rawNum = playerObj?.jersey_number ?? '';
                    openJerseyModal(pos.player_id, name, rawNum);
                });
            }
            if (canEdit) {
                enableDragging(token, idx);
                token.addEventListener('dblclick', () => {
                    currentPositions.splice(idx, 1);
                    renderPitch(); renderSquadList(); renderSubsList();
                });
            }
            pitchTokensContainer.appendChild(token);
        });
    }

    function renderSquadList() {
        const team = formationData[activeTeam];
        const squadList = document.getElementById('squadList');
        document.getElementById('squadCount').textContent = team.all_players.length;
        const canEdit = (activeTeam === 'team_a' && formationData.can_edit_a) || (activeTeam === 'team_b' && formationData.can_edit_b);

        if (!team.all_players.length) {
            squadList.innerHTML = '<div class="text-xs text-muted text-center py-sm">No players in squad</div>';
            return;
        }

        const conflicts = team.jersey_conflicts || [];

        squadList.innerHTML = team.all_players.map(p => {
            const isPlaced = currentPositions.some(pos => pos.player_id === p.id);
            const isSelected = selectedPlayerToPlace === p.id;
            const isCap = (p.id === team.captain_id);
            const rawNum = p.jersey_number;
            const displayNum = (rawNum !== null && rawNum !== undefined && !conflicts.includes(rawNum)) ? `#${rawNum}` : '#?';

            return `
                <div class="squad-player-btn ${isPlaced ? 'placed' : ''} ${isSelected ? 'selected' : ''}" data-player-id="${p.id}">
                    <span class="d-flex align-center gap-xs">
                        <span style="background:rgba(255,255,255,0.15); border-radius:4px; padding:1px 5px; font-size:11px; font-weight:800; color:var(--text-primary);">${displayNum}</span>
                        <span>${TM.escapeHtml(p.name)}</span>
                        ${isCap ? '<span style="color:var(--accent-gold); font-weight:900; margin-left:2px;">©</span>' : ''}
                    </span>
                    <span class="text-xs ${isPlaced ? 'text-green' : 'text-muted'}">
                        ${isPlaced ? 'On Pitch' : (canEdit ? 'Place +' : 'Sub')}
                    </span>
                </div>
            `;
        }).join('');

        if (canEdit) {
            squadList.querySelectorAll('.squad-player-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedPlayerToPlace = (selectedPlayerToPlace === btn.dataset.playerId) ? null : btn.dataset.playerId;
                    renderSquadList();
                });
            });
        }
    }

    function renderSubsList() {
        const team = formationData[activeTeam];
        const subsList = document.getElementById('subsList');
        const placedIds = currentPositions.map(pos => pos.player_id);
        const unplaced = team.all_players.filter(p => !placedIds.includes(p.id));
        document.getElementById('subsCountBadge').textContent = unplaced.length;
        if (!unplaced.length) {
            subsList.innerHTML = '<div class="text-xs text-muted text-center py-xs">All players are on the tactical pitch</div>';
            return;
        }
        subsList.innerHTML = unplaced.map(p => `
            <div class="d-flex align-center justify-between p-xs" style="background:var(--bg-card); border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                <span class="text-xs fw-semibold">🪑 ${TM.escapeHtml(p.name)}</span>
                <span class="badge badge-warning text-xs">Sub</span>
            </div>
        `).join('');
    }

    // Click pitch to place
    pitch.addEventListener('click', (e) => {
        const canEdit = (activeTeam === 'team_a' && formationData.can_edit_a) || (activeTeam === 'team_b' && formationData.can_edit_b);
        if (!canEdit || !selectedPlayerToPlace) return;
        if (e.target.closest('.pitch-token')) return;

        const formatMatch = (formationData.format || '6v6').match(/^(\d+)/);
        const maxPlayers = formatMatch ? parseInt(formatMatch[1], 10) : 6;

        const rect = pitch.getBoundingClientRect();
        const x = Math.round(((e.clientX - rect.left) / rect.width) * 100);
        const y = Math.round(((e.clientY - rect.top) / rect.height) * 100);
        const ei = currentPositions.findIndex(p => p.player_id === selectedPlayerToPlace);
        if (ei !== -1) { 
            currentPositions[ei].x = x; 
            currentPositions[ei].y = y; 
        } else { 
            if (currentPositions.length >= maxPlayers) {
                TM.toast(`Formation full! Maximum ${maxPlayers} players allowed on pitch for ${formationData.format}. Other players are substitutes.`, 'warning');
                return;
            }
            currentPositions.push({ player_id: selectedPlayerToPlace, x, y, role: 'player' }); 
        }
        selectedPlayerToPlace = null;
        renderPitch(); renderSquadList(); renderSubsList();
    });

    function enableDragging(el, posIndex) {
        let isDragging = false;
        const onDown = (e) => { isDragging = true; e.stopPropagation(); };
        const onMove = (e) => {
            if (!isDragging) return;
            const cx = e.clientX || (e.touches && e.touches[0].clientX);
            const cy = e.clientY || (e.touches && e.touches[0].clientY);
            const rect = pitch.getBoundingClientRect();
            let x = Math.max(5, Math.min(95, Math.round(((cx - rect.left) / rect.width) * 100)));
            let y = Math.max(4, Math.min(96, Math.round(((cy - rect.top) / rect.height) * 100)));
            el.style.left = `${x}%`; el.style.top = `${y}%`;
            currentPositions[posIndex].x = x; currentPositions[posIndex].y = y;
        };
        const onUp = () => { isDragging = false; };
        el.addEventListener('mousedown', onDown);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        el.addEventListener('touchstart', onDown, { passive: true });
        window.addEventListener('touchmove', onMove, { passive: true });
        window.addEventListener('touchend', onUp);
    }

    async function saveFormation() {
        const team = formationData[activeTeam];
        const placedIds = currentPositions.map(pos => pos.player_id);
        const subIds = team.all_players.filter(p => !placedIds.includes(p.id)).map(p => p.id);
        TM.loading(`Saving formation for ${team.name}...`);
        try {
            const res = await TM.api('formation.php?action=save', {
                method: 'POST',
                body: { match_id: matchId, team: activeTeam, positions: currentPositions, subs: subIds }
            });
            TM.stopLoading();
            if (res.success) { TM.toast(res.message, 'success'); loadFormation(); }
            else { Swal.fire({ icon: 'error', title: 'Error', text: res.message }); }
        } catch (e) { TM.stopLoading(); TM.toast('Failed to save formation', 'error'); }
    }

    // ---- Jersey Modal ----
    function openJerseyModal(playerId, playerName, currentNum) {
        editingJerseyForPlayer = { id: playerId, name: playerName };
        document.getElementById('jerseyModalPlayerName').textContent = `Player: ${playerName}`;
        document.getElementById('jerseyNumberInput').value = (currentNum !== null && currentNum !== '' && currentNum !== undefined) ? currentNum : '';
        document.getElementById('jerseyModal').classList.add('open');
        document.getElementById('jerseyNumberInput').focus();
    }

    document.getElementById('closeJerseyModal').addEventListener('click', () => { document.getElementById('jerseyModal').classList.remove('open'); });
    document.getElementById('btnCancelJersey').addEventListener('click', () => { document.getElementById('jerseyModal').classList.remove('open'); });

    document.getElementById('btnSaveJersey').addEventListener('click', saveJerseyNumber);
    document.getElementById('jerseyNumberInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') saveJerseyNumber(); });

    async function saveJerseyNumber() {
        if (!editingJerseyForPlayer) return;
        const num = parseInt(document.getElementById('jerseyNumberInput').value, 10);
        if (!num || num < 1 || num > 99) { TM.toast('Enter a number between 1 and 99', 'warning'); return; }

        const savedId = editingJerseyForPlayer.id;
        try {
            const res = await TM.api('formation.php?action=save_jersey', {
                method: 'POST',
                body: { match_id: matchId, team: activeTeam, user_id: savedId, jersey_number: num }
            });
            document.getElementById('jerseyModal').classList.remove('open');
            editingJerseyForPlayer = null;
            if (res.success) {
                TM.toast(res.message, 'success');
                await loadFormation();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (e) { TM.toast('Failed to save jersey number', 'error'); }
    }

    loadFormation();
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
