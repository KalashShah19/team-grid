<?php
/**
 * Team Selection & Draft Room
 * Supports 6v6, 7v7, 8v8 auto-selection from poll votes OR direct player entry,
 * captain assignment, team renaming, captain ready states, turn-by-turn picking,
 * and transition to team formations.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$selectionId = $_GET['id'] ?? '';
$pollId = $_GET['poll_id'] ?? '';

$pageTitle = 'Team Selection';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';

$allUsers = getUsers();
$suggestedFormat = '6v6';
$suggestedTurf = '99 not out';
$pollVoters = [];

if ($pollId) {
    $poll = findPollById($pollId);
    if ($poll) {
        $votes = $poll['votes'] ?? [];
        foreach ($votes as $v) {
            if (isset($v['option_index']) && (int)$v['option_index'] !== 0) continue;
            $u = findUserById($v['user_id']);
            if ($u) {
                $uName = $u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '');
                $pollVoters[] = [
                    'id' => $u['id'],
                    'name' => $uName,
                ];
                $gCount = (int)($v['guest_count'] ?? 0);
                $gNames = $v['guest_names'] ?? [];
                for ($gi = 1; $gi <= $gCount; $gi++) {
                    $gName = trim($gNames[$gi - 1] ?? '') ?: ($u['first_name'] . " (+{$gi})");
                    $pollVoters[] = [
                        'id' => '__typed__' . urlencode($gName),
                        'name' => $gName,
                        'is_guest' => true,
                    ];
                }
            }
        }
        $suggestedFormat = suggestFormat(count($pollVoters));
        $suggestedTurf = !empty($poll['turf']) ? $poll['turf'] : getTurfByFormat($suggestedFormat);
    }
} else {
    $suggestedTurf = getTurfByFormat($suggestedFormat);
}
?>

<div class="container container-lg">
    <?php if (!$selectionId): ?>
        <!-- Setup Stage (Admin sets up the draft) -->
        <?php if (!$isAdminUser): ?>
            <div class="card p-xl text-center">
                <h3>Draft Room</h3>
                <p class="text-secondary mt-sm">Only the administrator can configure a new team draft session.</p>
                <a href="<?php echo BASE_URL; ?>/polls" class="btn btn-primary mt-md">Back to Polls</a>
            </div>
        <?php else: ?>
            <div class="card p-lg mb-xl" style="max-width: 680px; margin: 0 auto;">
                <div class="card-header border-none p-0 mb-md">
                    <h2 class="page-title">Setup Team Selection</h2>
                    <p class="text-secondary">Configure match format, assign captains, and initiate drafting</p>
                </div>

                <form id="setupSelectionForm">
                    <!-- Format selection (3 options only: 6v6, 7v7, 8v8) -->
                    <div class="form-group mb-md">
                        <label class="form-label">Match Format <span class="required">*</span></label>
                        <div class="tabs format-tabs" style="background:var(--bg-secondary);">
                            <button type="button" class="tab format-tab <?php echo $suggestedFormat === '6v6' ? 'active' : ''; ?>" data-format="6v6">6 v 6</button>
                            <button type="button" class="tab format-tab <?php echo $suggestedFormat === '7v7' ? 'active' : ''; ?>" data-format="7v7">7 v 7</button>
                            <button type="button" class="tab format-tab <?php echo $suggestedFormat === '8v8' ? 'active' : ''; ?>" data-format="8v8">8 v 8</button>
                        </div>
                        <input type="hidden" id="selectedFormat" value="<?php echo $suggestedFormat; ?>">
                        <?php if ($pollId): ?>
                            <div class="text-xs text-green mt-xs">✓ Auto-suggested based on <?php echo count($pollVoters); ?> poll votes</div>
                        <?php endif; ?>
                    </div>

                    <!-- Turf selection based on players / format -->
                    <div class="form-group mb-md">
                        <label class="form-label">Turf / Venue <span class="required">*</span></label>
                        <input type="text" class="form-input" id="selectedTurf" value="<?php echo htmlspecialchars($suggestedTurf); ?>" placeholder="e.g. 99 not out, valas pro, valas old" required>
                        <div class="d-flex gap-xs mt-xs flex-wrap" id="turfQuickSelect">
                            <button type="button" class="btn btn-secondary btn-xs turf-badge" data-format="6v6" data-turf="99 not out">🏟️ 6-side: 99 not out</button>
                            <button type="button" class="btn btn-secondary btn-xs turf-badge" data-format="7v7" data-turf="valas pro">🏟️ 7-side: valas pro</button>
                            <button type="button" class="btn btn-secondary btn-xs turf-badge" data-format="8v8" data-turf="valas old">🏟️ 8-side: valas old</button>
                        </div>
                        <div class="text-xs text-muted mt-xs" id="turfHintText">Auto-selected based on match format: 6-side = 99 not out, 7-side = valas pro, 8-side = valas old</div>
                    </div>

                    <?php if ($pollId): ?>
                        <input type="hidden" id="pollIdInput" value="<?php echo htmlspecialchars($pollId); ?>">
                        <div class="form-group mb-md">
                            <label class="form-label">Poll Voters (<?php echo count($pollVoters); ?> voted In)</label>
                            <div class="card p-sm" style="background:var(--bg-input); max-height:100px; overflow-y:auto;">
                                <?php if ($pollVoters): ?>
                                    <div class="d-flex flex-wrap gap-xs">
                                        <?php foreach ($pollVoters as $pv): ?>
                                            <span class="badge badge-primary text-xs"><?php echo htmlspecialchars($pv['name']); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-xs text-muted">No votes yet on this poll.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Extra players on top of poll voters -->
                        <div class="form-group mb-md" style="border:1px solid var(--border-light); border-radius:var(--radius-md); padding:12px;">
                            <div class="d-flex align-center justify-between mb-sm">
                                <label class="form-label mb-0">➕ Add Extra Players <span class="text-xs text-muted">(optional)</span></label>
                            </div>

                            <?php
                            $pollVoterIds = array_column($pollVoters, 'id');
                            $nonVoters = array_filter($allUsers, fn($u) => !in_array($u['id'], $pollVoterIds));
                            ?>
                            <?php if ($nonVoters): ?>
                            <p class="text-xs text-muted mb-sm">Pick from registered players not in the poll:</p>
                            <div class="d-flex flex-wrap gap-xs mb-sm" id="extraPlayerCheckboxes">
                                <?php foreach ($nonVoters as $u): ?>
                                    <?php $name = $u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : ''); ?>
                                    <label style="display:flex; align-items:center; gap:5px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:4px 10px; cursor:pointer; font-size:var(--font-size-xs); transition:all 0.15s;">
                                        <input type="checkbox" class="extra-player-check" value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($name); ?>" style="accent-color:var(--accent-green); width:14px; height:14px;">
                                        <?php echo htmlspecialchars($name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <p class="text-xs text-muted mb-xs">Or type new names (comma/newline separated):</p>
                            <textarea class="form-textarea" id="directPlayerNames" rows="2" placeholder="e.g. John Doe, Alex Smith" style="font-size:var(--font-size-sm);"></textarea>
                            <span class="text-xs text-muted">Temporary players (not added to registered users).</span>
                        </div>
                    <?php else: ?>
                        <!-- Direct Player Entry (no poll) -->
                        <div class="form-group mb-md">
                            <label class="form-label">Player Pool <span class="required">*</span></label>

                            <?php if ($allUsers): ?>
                            <p class="text-xs text-muted mb-sm">Pick from registered players:</p>
                            <div class="d-flex flex-wrap gap-xs mb-sm" id="extraPlayerCheckboxes">
                                <?php foreach ($allUsers as $u): ?>
                                    <?php $name = $u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : ''); ?>
                                    <label style="display:flex; align-items:center; gap:5px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:4px 10px; cursor:pointer; font-size:var(--font-size-xs); transition:all 0.15s;">
                                        <input type="checkbox" class="extra-player-check" value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($name); ?>" style="accent-color:var(--accent-green); width:14px; height:14px;">
                                        <?php echo htmlspecialchars($name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <hr style="border-color:var(--border-color); margin:8px 0;">
                            <?php endif; ?>

                            <p class="text-xs text-muted mb-xs">Or type new names (comma/newline separated):</p>
                            <textarea class="form-textarea" id="directPlayerNames" rows="3" placeholder="e.g. Messi, Ronaldo, Neymar, Mbappe..."></textarea>
                            <div class="d-flex align-center justify-between mt-xs">
                                <span class="text-xs text-muted">Temporary players (not added to registered users).</span>
                                <span class="text-xs fw-semibold" id="playerNameCount" style="color:var(--accent-green);">0 players</span>
                            </div>
                        </div>
                    <?php endif; ?>


                    <div class="form-row mb-md">
                        <div class="form-group">
                            <label class="form-label">Team A Captain <span class="required">*</span></label>
                            <select class="form-select" id="captainASelect" required>
                                <option value="">— Select Captain A —</option>
                                <?php if ($pollId && !empty($pollVoters)): ?>
                                    <?php foreach ($pollVoters as $pv): ?>
                                        <option value="<?php echo htmlspecialchars($pv['id']); ?>">
                                            <?php echo htmlspecialchars($pv['name'] . (!empty($pv['is_guest']) ? ' 👤 (Temp)' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Team B Captain <span class="required">*</span></label>
                            <select class="form-select" id="captainBSelect" required>
                                <option value="">— Select Captain B —</option>
                                <?php if ($pollId && !empty($pollVoters)): ?>
                                    <?php foreach ($pollVoters as $pv): ?>
                                        <option value="<?php echo htmlspecialchars($pv['id']); ?>">
                                            <?php echo htmlspecialchars($pv['name'] . (!empty($pv['is_guest']) ? ' 👤 (Temp)' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row mb-lg">
                        <div class="form-group">
                            <label class="form-label">Team A Default Name</label>
                            <input type="text" class="form-input" id="teamANameInput" value="Red Warriors">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Team B Default Name</label>
                            <input type="text" class="form-input" id="teamBNameInput" value="Blue Strikers">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="btnStartDraft">
                        🚀 Launch Selection Room
                    </button>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Active Draft Room -->
        <div class="d-flex align-center justify-between mb-md flex-wrap gap-sm">
            <div>
                <h1 class="page-title" style="margin-bottom:2px;">Draft Room</h1>
                <p class="text-secondary text-sm" id="draftSubtitle">Loading session...</p>
            </div>
            <div id="draftControlArea" class="d-flex gap-sm"></div>
        </div>

        <!-- 3-Column Draft Board -->
        <div class="draft-container">
            <!-- Team A Column -->
            <div class="team-card team-a" id="teamACard">
                <div class="d-flex align-center justify-between mb-sm">
                    <h3 id="teamATitle" class="text-red">Team A</h3>
                    <button class="btn btn-secondary btn-xs rename-btn" data-team="team_a" id="renameTeamABtn" title="Rename Team">✏️</button>
                </div>
                <div class="text-xs text-muted mb-md" id="teamACaptain">Captain: ...</div>
                
                <button class="btn btn-sm btn-block mb-md ready-btn" id="teamAReadyBtn">
                    ⏳ Not Ready
                </button>

                <h4 class="text-xs fw-bold text-muted mb-sm">SQUAD ROSTER (<span id="teamACount">0</span>)</h4>
                <div class="d-grid gap-xs" id="teamAList"></div>
            </div>

            <!-- Available Players Pool (Center) -->
            <div class="card p-md" style="background:var(--bg-secondary);">
                <div class="d-flex align-center justify-between mb-sm">
                    <h3 style="font-size:var(--font-size-md);">Available Pool</h3>
                    <span class="badge badge-primary" id="poolCountBadge">0</span>
                </div>
                <p class="text-xs text-secondary mb-md" id="turnInstruction">
                    Both captains must click "Ready" to begin drafting.
                </p>

                <div class="d-grid gap-xs" id="availablePlayersList" style="max-height: 480px; overflow-y: auto;">
                    <div class="text-center py-md text-muted text-sm">Loading pool...</div>
                </div>
            </div>

            <!-- Team B Column -->
            <div class="team-card team-b" id="teamBCard">
                <div class="d-flex align-center justify-between mb-sm">
                    <h3 id="teamBTitle" class="text-blue">Team B</h3>
                    <button class="btn btn-secondary btn-xs rename-btn" data-team="team_b" id="renameTeamBBtn" title="Rename Team">✏️</button>
                </div>
                <div class="text-xs text-muted mb-md" id="teamBCaptain">Captain: ...</div>
                
                <button class="btn btn-sm btn-block mb-md ready-btn" id="teamBReadyBtn">
                    ⏳ Not Ready
                </button>

                <h4 class="text-xs fw-bold text-muted mb-sm">SQUAD ROSTER (<span id="teamBCount">0</span>)</h4>
                <div class="d-grid gap-xs" id="teamBList"></div>
            </div>
        </div>

        <!-- Draft History -->
        <div class="card p-md mt-lg">
            <h4 class="text-xs fw-bold text-muted mb-sm">DRAFT TIMELINE</h4>
            <div class="d-flex gap-sm flex-wrap text-xs" id="draftHistory">
                <span class="text-muted">Draft has not started yet.</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectionId = '<?php echo $selectionId; ?>';

    // -------------------------------------------------------------
    // Setup Form Logic (If creating a new draft)
    // -------------------------------------------------------------
    const setupForm = document.getElementById('setupSelectionForm');
    if (setupForm) {

        // --- Format & Turf mapping based on players ---
        // 6 side match = 99 not out, 7 side match = valas pro, 8 side match = valas old
        const turfMapping = {
            '6v6': '99 not out',
            '7v7': 'valas pro',
            '8v8': 'valas old'
        };

        // --- Format tab switching ---
        document.querySelectorAll('.format-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.format-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const fmt = tab.dataset.format;
                document.getElementById('selectedFormat').value = fmt;
                const turfInput = document.getElementById('selectedTurf');
                if (turfInput && turfMapping[fmt]) {
                    turfInput.value = turfMapping[fmt];
                }
            });
        });

        // --- Quick Turf preset buttons ---
        document.querySelectorAll('.turf-badge').forEach(btn => {
            btn.addEventListener('click', () => {
                const turf = btn.dataset.turf;
                const fmt = btn.dataset.format;
                const turfInput = document.getElementById('selectedTurf');
                if (turfInput) turfInput.value = turf;
                if (fmt) {
                    const tab = document.querySelector(`.format-tab[data-format="${fmt}"]`);
                    if (tab) {
                        document.querySelectorAll('.format-tab').forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        document.getElementById('selectedFormat').value = fmt;
                    }
                }
            });
        });

        // --- Live player count + dynamic captain dropdowns ---
        const textarea   = document.getElementById('directPlayerNames');
        const countLabel = document.getElementById('playerNameCount');
        const captainA   = document.getElementById('captainASelect');
        const captainB   = document.getElementById('captainBSelect');
        const isPollMode = !!document.getElementById('pollIdInput');
        const initialPollVoters = <?php echo json_encode($pollVoters); ?>;

        function parsedNames() {
            const raw = textarea?.value || '';
            return raw.split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
        }

        function checkedNames() {
            return [...document.querySelectorAll('.extra-player-check:checked')]
                .map(cb => ({ id: cb.value, name: cb.dataset.name, is_temp: false }));
        }

        function allPlayerNames() {
            // Combine poll voters + checked registered players + typed names
            const typed   = parsedNames().map(n => ({ id: '__typed__' + encodeURIComponent(n), name: n, is_temp: true }));
            const checked = checkedNames();
            const pollList = isPollMode ? initialPollVoters : [];

            // Dedupe by name (lowercase)
            const seen = new Set();
            const merged = [];

            // 1. Poll voters (registered + guests)
            pollList.forEach(p => {
                const lower = p.name.toLowerCase();
                if (!seen.has(lower)) {
                    seen.add(lower);
                    merged.push({ id: p.id, name: p.name, is_temp: !!p.is_guest });
                }
            });

            // 2. Extra checked registered players
            checked.forEach(c => {
                const lower = c.name.toLowerCase();
                if (!seen.has(lower)) {
                    seen.add(lower);
                    merged.push(c);
                }
            });

            // 3. Typed names (temp players)
            typed.forEach(t => {
                const lower = t.name.toLowerCase();
                if (!seen.has(lower)) {
                    seen.add(lower);
                    merged.push(t);
                }
            });

            return merged;
        }

        function rebuildCaptainDropdowns() {
            const players = allPlayerNames();
            const prevA   = captainA.value;
            const prevB   = captainB.value;

            const placeholder = '<option value="">— Select Captain —</option>';
            const options = players.map(p => {
                const isTemp = p.is_temp || p.is_guest || (typeof p.id === 'string' && (p.id.startsWith('__typed__') || p.id.startsWith('temp_')));
                const tag = isTemp ? ' 👤 (Temp)' : '';
                return `<option value="${p.id}">${p.name}${tag}</option>`;
            }).join('');

            captainA.innerHTML = placeholder + options;
            captainB.innerHTML = placeholder + options;

            // Restore previous selection if still valid
            if (prevA && [...captainA.options].some(o => o.value === prevA)) captainA.value = prevA;
            if (prevB && [...captainB.options].some(o => o.value === prevB)) captainB.value = prevB;
        }

        function updateCount() {
            const players = allPlayerNames();
            const n = players.length;
            if (countLabel) {
                countLabel.textContent = n === 0 ? '0 players' : `${n} player${n !== 1 ? 's' : ''}`;
                countLabel.style.color = n >= 4 ? 'var(--accent-green)' : 'var(--accent-orange)';
            }

            // Auto-select match format and turf based on players (6-side = 99 not out, 7-side = valas pro, 8-side = valas old)
            if (!isPollMode && n > 0) {
                let autoFmt = '6v6';
                if (n >= 16) autoFmt = '8v8';
                else if (n >= 14) autoFmt = '7v7';
                
                const curTab = document.querySelector(`.format-tab[data-format="${autoFmt}"]`);
                const activeTab = document.querySelector('.format-tab.active');
                if (curTab && activeTab?.dataset.format !== autoFmt) {
                    curTab.click();
                }
            }

            rebuildCaptainDropdowns();
        }

        // Listen on textarea and checkboxes
        if (textarea) textarea.addEventListener('input', updateCount);
        document.querySelectorAll('.extra-player-check').forEach(cb => {
            cb.addEventListener('change', updateCount);
        });

        // Checkbox label highlight on check
        document.querySelectorAll('#extraPlayerCheckboxes label').forEach(lbl => {
            const cb = lbl.querySelector('input');
            if (!cb) return;
            cb.addEventListener('change', () => {
                lbl.style.borderColor = cb.checked ? 'var(--accent-green)' : 'var(--border-color)';
                lbl.style.background  = cb.checked ? 'rgba(0,230,118,0.08)' : 'var(--bg-card)';
                lbl.style.color       = cb.checked ? 'var(--accent-green)' : '';
            });
        });

        // Initial state
        updateCount();

        // --- Form submit ---
        setupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const format   = document.getElementById('selectedFormat').value;
            const pollId   = document.getElementById('pollIdInput')?.value || null;
            const capA     = captainA.value;
            const capB     = captainB.value;
            const teamAName = document.getElementById('teamANameInput').value.trim();
            const teamBName = document.getElementById('teamBNameInput').value.trim();

            if (!capA || !capB) {
                TM.toast('Please assign both captains', 'warning');
                return;
            }
            if (capA === capB) {
                TM.toast('Captain A and Captain B must be different players', 'error');
                return;
            }

            // Collect extra checked players (registered users)
            const extraPlayerIds = [...document.querySelectorAll('.extra-player-check:checked')]
                .map(cb => cb.value);

            // Collect typed new names (deduplicated)
            const directNames = parsedNames();

            // Resolve captain names for typed-only mode
            // If captainA/B value starts with __typed__, pass the name in direct_names + flag
            const captainATyped = capA.startsWith('__typed__') ? decodeURIComponent(capA.replace('__typed__', '')) : null;
            const captainBTyped = capB.startsWith('__typed__') ? decodeURIComponent(capB.replace('__typed__', '')) : null;

            // If no poll: need at least some players
            if (!pollId && extraPlayerIds.length === 0 && directNames.length === 0) {
                TM.toast('Please select or enter at least some players', 'warning');
                return;
            }

            TM.loading('Initializing draft room...');
            try {
                const turf = document.getElementById('selectedTurf')?.value.trim() || turfMapping[format] || '99 not out';
                const res = await TM.api('teams.php?action=create', {
                    method: 'POST',
                    body: {
                        format,
                        turf,
                        poll_id: pollId,
                        captain_a_id: capA,
                        captain_b_id: capB,
                        captain_a_name: captainATyped,
                        captain_b_name: captainBTyped,
                        team_a_name: teamAName,
                        team_b_name: teamBName,
                        direct_names: directNames,
                        extra_player_ids: extraPlayerIds,
                    }
                });
                TM.stopLoading();

                if (res.success) {
                    window.location.href = `${TM.baseUrl}/team-selection?id=${res.selection_id}`;
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            } catch (err) {
                TM.stopLoading();
                TM.toast('Failed to launch draft room', 'error');
            }
        });
        return;
    }

    // -------------------------------------------------------------
    // Active Draft Room Logic
    // -------------------------------------------------------------
    let selectionState = null;
    let pollTimer = null;

    async function fetchSelection() {
        try {
            const res = await TM.api(`teams.php?action=get&id=${selectionId}`);
            if (res.success) {
                selectionState = res.selection;
                renderDraft();
            } else {
                TM.toast('Selection session not found', 'error');
            }
        } catch (e) { /* silent */ }
    }

    function renderDraft() {
        if (!selectionState) return;
        const sel = selectionState;

        document.getElementById('draftSubtitle').textContent = `Format: ${sel.format} | Turn: ${sel.current_turn === 'team_a' ? sel.team_a.name : sel.team_b.name}`;

        // Team A UI
        document.getElementById('teamATitle').textContent = sel.team_a.name;
        document.getElementById('teamACaptain').innerHTML = `Captain: <strong>${TM.escapeHtml(sel.team_a.captain_name)}</strong> <span class="captain-badge"></span>`;
        document.getElementById('teamACount').textContent = sel.team_a.players.length;

        const btnReadyA = document.getElementById('teamAReadyBtn');
        btnReadyA.textContent = sel.team_a.ready ? '✓ Ready' : '⏳ Click When Ready';
        btnReadyA.className = `btn btn-sm btn-block mb-md ${sel.team_a.ready ? 'btn-primary' : 'btn-secondary'}`;
        btnReadyA.disabled = !sel.can_control_a;

        document.getElementById('teamAList').innerHTML = sel.team_a.player_details.map(p => `
            <div class="player-pick-item">
                <span class="text-sm fw-semibold">${TM.escapeHtml(p.name)}</span>
                ${p.id === sel.team_a.captain_id ? '<span class="captain-badge"></span>' : ''}
            </div>
        `).join('');

        // Team B UI
        document.getElementById('teamBTitle').textContent = sel.team_b.name;
        document.getElementById('teamBCaptain').innerHTML = `Captain: <strong>${TM.escapeHtml(sel.team_b.captain_name)}</strong> <span class="captain-badge"></span>`;
        document.getElementById('teamBCount').textContent = sel.team_b.players.length;

        const btnReadyB = document.getElementById('teamBReadyBtn');
        btnReadyB.textContent = sel.team_b.ready ? '✓ Ready' : '⏳ Click When Ready';
        btnReadyB.className = `btn btn-sm btn-block mb-md ${sel.team_b.ready ? 'btn-primary' : 'btn-secondary'}`;
        btnReadyB.disabled = !sel.can_control_b;

        document.getElementById('teamBList').innerHTML = sel.team_b.player_details.map(p => `
            <div class="player-pick-item">
                <span class="text-sm fw-semibold">${TM.escapeHtml(p.name)}</span>
                ${p.id === sel.team_b.captain_id ? '<span class="captain-badge"></span>' : ''}
            </div>
        `).join('');

        // Turn highlighting
        const teamACard = document.getElementById('teamACard');
        const teamBCard = document.getElementById('teamBCard');
        teamACard.classList.toggle('turn-active', sel.current_turn === 'team_a' && sel.team_a.ready && sel.team_b.ready);
        teamBCard.classList.toggle('turn-active', sel.current_turn === 'team_b' && sel.team_a.ready && sel.team_b.ready);

        // Turn instruction
        const bothReady = sel.team_a.ready && sel.team_b.ready;
        const turnInstruction = document.getElementById('turnInstruction');
        if (!bothReady) {
            turnInstruction.innerHTML = '<span class="text-gold">⚠️ Waiting for both captains to click Ready...</span>';
        } else {
            const activeTeamName = sel.current_turn === 'team_a' ? sel.team_a.name : sel.team_b.name;
            if (sel.is_my_turn) {
                turnInstruction.innerHTML = `<span class="text-green fw-bold">👉 YOUR TURN! Pick a player for ${activeTeamName}.</span>`;
            } else {
                turnInstruction.innerHTML = `<span>Waiting for <strong>${activeTeamName}</strong> to pick...</span>`;
            }
        }

        // Available pool
        const poolList = document.getElementById('availablePlayersList');
        document.getElementById('poolCountBadge').textContent = sel.available_players.length;

        if (!sel.available_players.length) {
            poolList.innerHTML = '<div class="text-center py-md text-green fw-semibold">🎉 All players drafted!</div>';
        } else {
            poolList.innerHTML = sel.available_players.map(p => `
                <div class="player-pick-item ${(!bothReady || !sel.is_my_turn) ? 'disabled' : ''}" data-player-id="${p.id}" data-player-name="${TM.escapeHtml(p.name)}">
                    <span class="fw-semibold text-sm">⚽ ${TM.escapeHtml(p.name)}</span>
                    <button class="btn btn-primary btn-xs" ${(!bothReady || !sel.is_my_turn) ? 'disabled' : ''}>Pick +</button>
                </div>
            `).join('');

            poolList.querySelectorAll('.player-pick-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (!bothReady || !sel.is_my_turn) return;
                    pickPlayer(item.dataset.playerId, item.dataset.playerName);
                });
            });
        }

        // Draft history
        const historyContainer = document.getElementById('draftHistory');
        if (sel.draft_history && sel.draft_history.length > 0) {
            historyContainer.innerHTML = sel.draft_history.map(h => `
                <span class="badge ${h.team === 'team_a' ? 'badge-danger' : 'badge-primary'}">
                    ${TM.escapeHtml(h.player_name)} → ${TM.escapeHtml(h.team_name)}
                </span>
            `).join('');
        }

        // Finalize / Formation transition button
        const controlArea = document.getElementById('draftControlArea');
        controlArea.innerHTML = '';
        if (sel.poll_id) {
            const chatLink = document.createElement('a');
            chatLink.href = `${TM.baseUrl}/poll-detail?id=${sel.poll_id}`;
            chatLink.className = 'btn btn-secondary btn-sm';
            chatLink.innerHTML = '💬 Match Chat';
            controlArea.appendChild(chatLink);
        }
        if (sel.is_admin || sel.available_players.length === 0) {
            const btnDone = document.createElement('button');
            btnDone.className = 'btn btn-primary btn-sm';
            btnDone.innerHTML = '🎯 Finalize & Set Formations →';
            btnDone.onclick = finalizeDraft;
            controlArea.appendChild(btnDone);
        }
    }

    // Captain ready toggle
    document.getElementById('teamAReadyBtn')?.addEventListener('click', () => toggleReady('team_a'));
    document.getElementById('teamBReadyBtn')?.addEventListener('click', () => toggleReady('team_b'));

    async function toggleReady(team) {
        try {
            const res = await TM.api('teams.php?action=ready', {
                method: 'POST',
                body: { id: selectionId, team }
            });
            if (res.success) {
                fetchSelection();
            } else {
                TM.toast(res.message, 'warning');
            }
        } catch (e) {
            TM.toast('Failed to update ready state', 'error');
        }
    }

    // Team rename
    document.getElementById('renameTeamABtn')?.addEventListener('click', () => promptRename('team_a'));
    document.getElementById('renameTeamBBtn')?.addEventListener('click', () => promptRename('team_b'));

    async function promptRename(team) {
        const curName = (team === 'team_a') ? selectionState.team_a.name : selectionState.team_b.name;
        const { value: newName } = await Swal.fire({
            title: 'Rename Team',
            input: 'text',
            inputValue: curName,
            showCancelButton: true,
            inputValidator: (v) => !v.trim() ? 'Team name cannot be empty' : null
        });

        if (newName) {
            try {
                const res = await TM.api('teams.php?action=rename', {
                    method: 'POST',
                    body: { id: selectionId, team, name: newName.trim() }
                });
                if (res.success) {
                    TM.toast(`Team renamed to ${newName.trim()}`, 'success');
                    fetchSelection();
                }
            } catch (e) {
                TM.toast('Failed to rename team', 'error');
            }
        }
    }

    // Pick player
    async function pickPlayer(playerId, playerName) {
        try {
            const res = await TM.api('teams.php?action=pick', {
                method: 'POST',
                body: { id: selectionId, player_id: playerId }
            });
            if (res.success) {
                TM.toast(res.message, 'success');
                fetchSelection();
            } else {
                Swal.fire({ icon: 'warning', title: 'Cannot Pick', text: res.message });
            }
        } catch (e) {
            TM.toast('Failed to pick player', 'error');
        }
    }

    // Finalize
    async function finalizeDraft() {
        const confirm = await TM.confirm('Finalize Team Selection?', 'Both teams will be locked and you will move straight to tactical pitch formations.', 'question');
        if (!confirm.isConfirmed) return;

        TM.loading('Finalizing draft and preparing match pitch...');
        try {
            const res = await TM.api('teams.php?action=finalize', {
                method: 'POST',
                body: { id: selectionId }
            });
            TM.stopLoading();

            if (res.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Teams Finalized!',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false,
                });
                window.location.href = `${TM.baseUrl}/formation?match_id=${res.match_id}`;
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (e) {
            TM.stopLoading();
            TM.toast('Failed to finalize draft', 'error');
        }
    }

    // Start auto polling for live turn updates
    fetchSelection();
    pollTimer = setInterval(fetchSelection, 3000);
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
