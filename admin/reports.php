<?php
/**
 * Admin Reports Page
 * Comprehensive metrics: total matches, total players, active players,
 * descending order of most active players, attendance rates, fastest voters, and chat participation.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Activity Reports';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <div class="d-flex align-center gap-xs">
                <a href="<?php echo BASE_URL; ?>/admin" class="btn btn-secondary btn-xs">← Admin</a>
                <h1 class="page-title" style="margin-bottom:0;">📈 Squad Activity Reports</h1>
            </div>
            <p class="page-subtitle">Player activity index, attendance rankings, and fastest voter leaderboards</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="loadReports()">
            🔄 Refresh Data
        </button>
    </div>

    <!-- High-level Stats Grid -->
    <div class="d-grid gap-md mb-xl" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));" id="reportsStatsGrid">
        <div class="card p-md">
            <span class="text-xs text-muted">TOTAL MATCHES</span>
            <div class="stat-value text-green mt-xs" id="repTotalMatches">-</div>
        </div>
        <div class="card p-md">
            <span class="text-xs text-muted">REGISTERED SQUAD</span>
            <div class="stat-value text-blue mt-xs" id="repTotalPlayers">-</div>
        </div>
        <div class="card p-md">
            <span class="text-xs text-muted">ACTIVE PLAYERS (30D)</span>
            <div class="stat-value text-gold mt-xs" id="repActivePlayers">-</div>
        </div>
        <div class="card p-md">
            <span class="text-xs text-muted">TOTAL POLLS</span>
            <div class="stat-value text-primary mt-xs" id="repTotalPolls">-</div>
        </div>
    </div>

    <div class="d-grid gap-xl" style="grid-template-columns: 1fr;">
        <!-- Most Active Players (Descending Order) -->
        <div class="card overflow-hidden">
            <div class="card-header justify-between">
                <div>
                    <h3 style="font-size:var(--font-size-md);">🏆 Most Active Players (Descending Order)</h3>
                    <p class="text-xs text-muted">Weighted score based on matches played, poll votes cast, and squad chats</p>
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player Name</th>
                            <th>Activity Score</th>
                            <th>Matches Played</th>
                            <th>Votes Cast</th>
                            <th>Comments</th>
                            <th>Last Active</th>
                        </tr>
                    </thead>
                    <tbody id="mostActiveTableBody">
                        <tr><td colspan="7" class="text-center py-xl text-muted">Loading leaderboard...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fastest Voters Leaderboard -->
        <div class="card overflow-hidden">
            <div class="card-header justify-between">
                <div>
                    <h3 style="font-size:var(--font-size-md);">⚡ Fastest Voters Leaderboard</h3>
                    <p class="text-xs text-muted">Average response time between poll publication and vote cast</p>
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player Name</th>
                            <th>Average Response Time</th>
                            <th>Total Votes</th>
                        </tr>
                    </thead>
                    <tbody id="fastestVotersTableBody">
                        <tr><td colspan="4" class="text-center py-xl text-muted">Loading voter times...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadReports();
});

async function loadReports() {
    const mostActiveBody = document.getElementById('mostActiveTableBody');
    const fastestBody = document.getElementById('fastestVotersTableBody');

    try {
        const res = await TM.api('admin.php?action=reports');
        if (res.success) {
            // Stats
            document.getElementById('repTotalMatches').textContent = res.summary.total_matches;
            document.getElementById('repTotalPlayers').textContent = res.summary.total_players;
            document.getElementById('repActivePlayers').textContent = res.summary.active_players_count;
            document.getElementById('repTotalPolls').textContent = res.summary.total_polls;

            // Most active
            if (!res.most_active_players.length) {
                mostActiveBody.innerHTML = '<tr><td colspan="7" class="text-center py-md text-muted">No activity data recorded yet</td></tr>';
            } else {
                mostActiveBody.innerHTML = res.most_active_players.map((p, idx) => {
                    let rankBadge = `<span class="badge badge-secondary">${idx + 1}</span>`;
                    if (idx === 0) rankBadge = '<span class="badge badge-warning">🥇 #1</span>';
                    else if (idx === 1) rankBadge = '<span class="badge badge-secondary">🥈 #2</span>';
                    else if (idx === 2) rankBadge = '<span class="badge badge-secondary">🥉 #3</span>';

                    return `
                        <tr>
                            <td>${rankBadge}</td>
                            <td>
                                <span class="fw-semibold text-sm">👤 ${TM.escapeHtml(p.name)}</span>
                            </td>
                            <td>
                                <span class="badge badge-primary">${p.activity_score} pts</span>
                            </td>
                            <td><strong>${p.matches_played}</strong> matches</td>
                            <td>${p.votes_cast} votes</td>
                            <td>${p.comments_posted} comments</td>
                            <td><span class="text-xs text-muted">${p.last_login !== 'Never' ? TM.timeAgo(p.last_login) : 'Never'}</span></td>
                        </tr>
                    `;
                }).join('');
            }

            // Fastest voters
            if (!res.fastest_voters.length) {
                fastestBody.innerHTML = '<tr><td colspan="4" class="text-center py-md text-muted">No voting timing records found</td></tr>';
            } else {
                fastestBody.innerHTML = res.fastest_voters.map((p, idx) => {
                    const timeDisplay = p.avg_vote_time_min < 60 
                        ? `${p.avg_vote_time_min} mins` 
                        : `${(p.avg_vote_time_min / 60).toFixed(1)} hours`;

                    return `
                        <tr>
                            <td><span class="badge badge-secondary">${idx + 1}</span></td>
                            <td><span class="fw-semibold text-sm">👤 ${TM.escapeHtml(p.name)}</span></td>
                            <td><span class="text-green fw-bold">⚡ ${timeDisplay}</span></td>
                            <td>${p.votes_cast} votes</td>
                        </tr>
                    `;
                }).join('');
            }
        }
    } catch (e) {
        TM.toast('Failed to load reports', 'error');
    }
}
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
