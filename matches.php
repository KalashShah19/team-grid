<?php
/**
 * Matches Page
 * Card-based responsive layout for all screen sizes
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pageTitle = 'Match Center';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<style>
/* ===== Match Cards ===== */
.match-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 0;
    overflow: hidden;
    cursor: pointer;
    transition: border-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
    position: relative;
}
.match-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}
.match-card:active {
    transform: translateY(0);
}

/* Top accent bar */
.match-card-accent {
    height: 3px;
    background: linear-gradient(90deg, var(--accent-red), var(--accent-blue));
}
.match-card-accent.completed {
    background: linear-gradient(90deg, var(--accent-green), var(--accent-cyan));
}

/* Card header: date + format + status */
.match-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px 0;
    gap: 8px;
}
.match-date-block {
    display: flex;
    align-items: center;
    gap: 8px;
}
.match-date-text {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.match-venue-text {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}
.match-badges {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

/* Fixture row: Team A — Score — Team B */
.match-fixture {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    gap: 8px;
}
.match-team {
    flex: 1;
    min-width: 0;
}
.match-team-a { text-align: left; }
.match-team-b { text-align: right; }

.match-team-name {
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.match-team-a .match-team-name { color: #ff6b6b; }
.match-team-b .match-team-name { color: #74b9ff; }

.match-team-captain {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Score centre */
.match-score-block {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    min-width: 60px;
}
.match-score-display {
    font-size: 1.4rem;
    font-weight: 900;
    letter-spacing: 0.05em;
    color: var(--accent-gold);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.match-score-vs {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

/* Card footer: player count + action buttons */
.match-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px 12px;
    border-top: 1px solid var(--border-color);
    gap: 8px;
    flex-wrap: wrap;
}
.match-meta-pills {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.match-action-btns {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

/* Status glow accents */
.match-card.status-completed .match-card-accent {
    background: linear-gradient(90deg, var(--accent-green), var(--accent-cyan));
}
.match-card.status-upcoming .match-card-accent {
    background: linear-gradient(90deg, var(--accent-gold), var(--accent-orange));
}

/* Grid */
#matchesGrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: var(--spacing-md);
}

@media (max-width: 600px) {
    #matchesGrid { grid-template-columns: 1fr; }
    .match-team-name { font-size: 0.9rem; }
    .match-score-display { font-size: 1.2rem; }
    .match-fixture { padding: 10px 12px; }
    .match-card-header { padding: 8px 12px 0; }
    .match-card-footer { padding: 8px 12px 10px; }
}
</style>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <h1 class="page-title">Match Center</h1>
            <p class="page-subtitle">Squad fixtures, results, rosters &amp; tactics</p>
        </div>
        <?php if ($isAdminUser): ?>
            <a href="<?php echo BASE_URL; ?>/team-selection" class="btn btn-secondary btn-sm">
                ⚡ New Draft
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Pills -->
    <div class="pill-filters mb-md">
        <button class="pill active" data-filter="all">All</button>
        <button class="pill" data-filter="upcoming">⏳ Upcoming</button>
        <button class="pill" data-filter="completed">✅ Completed</button>
    </div>

    <!-- Matches Grid -->
    <div id="matchesGrid">
        <div class="card text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
            <div class="loading-spinner mb-md"></div>
            <p class="text-muted">Loading matches...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const isAdmin = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
    let allMatches = [];
    let currentFilter = 'all';
    const grid = document.getElementById('matchesGrid');
    let lastMatchesJson = '';

    async function loadMatches() {
        try {
            const res = await TM.api('matches.php?action=list');
            if (res.success) {
                allMatches = res.matches;
                renderMatches();
            } else {
                grid.innerHTML = `<div class="card text-center p-xl text-red">${res.message}</div>`;
            }
        } catch (e) {
            grid.innerHTML = `<div class="card text-center p-xl text-red">Failed to load matches</div>`;
        }
    }

    function renderMatches() {
        const filtered = allMatches.filter(m => {
            if (currentFilter === 'all') return true;
            return (m.status || 'upcoming') === currentFilter;
        });

        if (!filtered.length) {
            grid.innerHTML = `
                <div class="card text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                    <div style="font-size: 48px; margin-bottom: 12px; opacity:0.4;">⚽</div>
                    <h3 class="fw-semibold mb-xs">No matches found</h3>
                    <p class="text-secondary text-sm">Completed polls and team drafts will appear here.</p>
                </div>`;
            return;
        }

        grid.innerHTML = filtered.map(m => buildMatchCard(m)).join('');

        // Click whole card → match detail
        grid.querySelectorAll('.match-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.match-action-btns')) return;
                window.location.href = `${TM.baseUrl}/match-detail?id=${card.dataset.matchId}`;
            });
        });
    }

    function buildMatchCard(m) {
        const status = m.status || 'upcoming';
        const isCompleted = status === 'completed';

        let formattedDate = 'Upcoming';
        if (m.date) {
            const d = new Date(m.date);
            formattedDate = !isNaN(d.getTime()) 
                ? TM.formatDate(m.date, { weekday: 'short', month: 'short', day: 'numeric' })
                : TM.escapeHtml(m.date);
        }

        // Score
        let scoreHtml;
        if (m.has_score) {
            scoreHtml = `<div class="match-score-display">${m.team_a_score}<span style="opacity:0.5; margin:0 3px;">-</span>${m.team_b_score}</div>`;
        } else {
            scoreHtml = `<div class="match-score-vs">VS</div>`;
        }

        // Status badge
        const statusBadge = isCompleted
            ? `<span class="badge badge-success" style="font-size:10px;">✅ FT</span>`
            : `<span class="badge badge-warning" style="font-size:10px;">⏳ Upcoming</span>`;

        return `
            <div class="match-card status-${status}" data-match-id="${m.id}">
                <div class="match-card-accent ${isCompleted ? 'completed' : ''}"></div>

                <div class="match-card-header">
                    <div class="match-date-block">
                        <div>
                            <div class="match-date-text">📅 ${formattedDate}</div>
                            ${m.venue ? `<div class="match-venue-text">📍 ${TM.escapeHtml(m.venue)}</div>` : ''}
                        </div>
                    </div>
                    <div class="match-badges">
                        <span class="badge badge-secondary" style="font-size:10px;">${m.format}</span>
                        ${statusBadge}
                    </div>
                </div>

                <div class="match-fixture">
                    <div class="match-team match-team-a">
                        <div class="match-team-name">${TM.escapeHtml(m.team_a_name)}</div>
                        <div class="match-team-captain">© ${TM.escapeHtml(m.captain_a_name)}</div>
                    </div>

                    <div class="match-score-block">
                        ${scoreHtml}
                    </div>

                    <div class="match-team match-team-b">
                        <div class="match-team-name">${TM.escapeHtml(m.team_b_name)}</div>
                        <div class="match-team-captain">© ${TM.escapeHtml(m.captain_b_name)}</div>
                    </div>
                </div>

                <div class="match-card-footer">
                    <div class="match-meta-pills">
                        <span class="badge badge-primary" style="font-size:10px;">👥 ${m.total_players} players</span>
                    </div>
                    <div class="match-action-btns">
                        ${m.poll_id ? `
                            <a href="${TM.baseUrl}/poll-detail?id=${m.poll_id}"
                               class="btn btn-secondary btn-xs"
                               onclick="event.stopPropagation()"
                               title="Match Discussion & Chat">
                                💬 Chat
                            </a>
                        ` : ''}
                        <a href="${TM.baseUrl}/formation?match_id=${m.id}"
                           class="btn btn-primary btn-xs"
                           onclick="event.stopPropagation()">
                            📋 Formation
                        </a>
                        <a href="${TM.baseUrl}/match-detail?id=${m.id}"
                           class="btn btn-secondary btn-xs"
                           onclick="event.stopPropagation()">
                            View →
                        </a>
                        ${isAdmin ? `
                            <button type="button"
                                    class="btn btn-secondary btn-xs text-red"
                                    title="Delete Match"
                                    onclick="event.stopPropagation(); deleteMatchFromList('${m.id}')">
                                🗑️
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    window.deleteMatchFromList = async function(id) {
        const confirm = await TM.confirm('Delete Match?', 'Are you sure you want to permanently delete this match and its formations? This action cannot be undone.', 'warning');
        if (!confirm.isConfirmed) return;

        TM.loading('Deleting match...');
        try {
            const res = await TM.api('matches.php?action=delete', {
                method: 'POST',
                body: { id }
            });
            TM.stopLoading();
            if (res.success) {
                TM.toast('Match deleted successfully', 'success');
                loadMatches();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        } catch (e) {
            TM.stopLoading();
            TM.toast('Failed to delete match', 'error');
        }
    };

    // Filter pills
    document.querySelectorAll('.pill-filters .pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.pill-filters .pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentFilter = pill.dataset.filter;
            renderMatches();
        });
    });

    loadMatches();
});
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
