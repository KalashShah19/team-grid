<?php
/**
 * Admin Pattern Analysis Page
 * Pattern recognition: matchday frequency, spending trends, format popularity, voting participation
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Pattern Analysis';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <div class="d-flex align-center gap-xs">
                <a href="<?php echo BASE_URL; ?>/admin" class="btn btn-secondary btn-xs">← Admin</a>
                <h1 class="page-title" style="margin-bottom:0;">🔍 Squad Pattern Analysis</h1>
            </div>
            <p class="page-subtitle">Behavioral analytics, match scheduling patterns, and expenditure tracking</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="loadAnalysis()">
            🔄 Refresh Analytics
        </button>
    </div>

    <!-- Spending Banner -->
    <div class="card p-lg mb-xl" style="background: linear-gradient(135deg, var(--bg-card), var(--bg-elevated)); border-left: 4px solid var(--accent-green);">
        <div class="d-flex align-center justify-between flex-wrap gap-md">
            <div>
                <span class="text-xs text-muted uppercase">LIFETIME TURF EXPENDITURE</span>
                <div class="stat-value text-green mt-xs" style="font-size:2.5rem;" id="totalSpentValue">₹0</div>
                <div class="text-xs text-secondary mt-xs">Total capital committed for match bookings and turf fees</div>
            </div>
            <div class="d-flex gap-md" id="monthlySpendSummary"></div>
        </div>
    </div>

    <div class="d-grid gap-xl" style="grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));">
        <!-- Day of the Week Match Patterns -->
        <div class="card p-md">
            <h3 style="font-size:var(--font-size-md);" class="mb-xs">📅 Match Scheduling by Day of Week</h3>
            <p class="text-xs text-secondary mb-md">Pattern recognition for preferred playing days</p>
            <div class="d-grid gap-sm" id="dayPatternsBars">
                <div class="text-center py-md text-muted">Calculating pattern...</div>
            </div>
        </div>

        <!-- Format Popularity -->
        <div class="card p-md">
            <h3 style="font-size:var(--font-size-md);" class="mb-xs">⚽ Format Popularity Distribution</h3>
            <p class="text-xs text-secondary mb-md">Preference between 6v6, 7v7, and 8v8 fixtures</p>
            <div class="d-grid gap-sm" id="formatPopularityBars">
                <div class="text-center py-md text-muted">Calculating pattern...</div>
            </div>
        </div>
    </div>

    <!-- Voter Engagement Pattern -->
    <div class="card p-md mt-xl">
        <h3 style="font-size:var(--font-size-md);" class="mb-xs">📊 Voter Participation & View Retention Trends</h3>
        <p class="text-xs text-secondary mb-md">Comparative ratio between views, votes cast, and squad comments</p>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Poll Title</th>
                        <th>Date</th>
                        <th>Views</th>
                        <th>Votes Cast</th>
                        <th>Conversion Rate</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody id="pollTrendsTableBody">
                    <tr><td colspan="6" class="text-center py-md text-muted">Loading trends...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadAnalysis();
});

async function loadAnalysis() {
    try {
        const res = await TM.api('admin.php?action=analysis');
        if (res.success) {
            // Lifetime spend
            document.getElementById('totalSpentValue').textContent = `₹${res.total_spent.toLocaleString()}`;

            // Monthly breakdown
            const monthlyContainer = document.getElementById('monthlySpendSummary');
            monthlyContainer.innerHTML = Object.entries(res.monthly_spend || {}).slice(-3).map(([month, amount]) => `
                <div class="card p-sm text-center" style="background:var(--bg-input); min-width:110px;">
                    <div class="text-xs text-muted">${month}</div>
                    <div class="fw-bold text-sm text-primary">₹${amount.toLocaleString()}</div>
                </div>
            `).join('');

            // Day patterns
            const dayContainer = document.getElementById('dayPatternsBars');
            const dayEntries = Object.entries(res.day_patterns || {});
            const maxDay = Math.max(...dayEntries.map(([, c]) => c), 1);
            dayContainer.innerHTML = dayEntries.map(([day, count]) => {
                const pct = Math.round((count / maxDay) * 100);
                return `
                    <div>
                        <div class="d-flex justify-between text-xs mb-xs">
                            <span class="fw-semibold">${day}</span>
                            <span class="text-muted">${count} matches</span>
                        </div>
                        <div style="height:8px; background:var(--bg-input); border-radius:4px; overflow:hidden;">
                            <div style="width:${pct}%; height:100%; background:var(--accent-green); transition:width 0.4s ease;"></div>
                        </div>
                    </div>
                `;
            }).join('');

            // Format popularity
            const formatContainer = document.getElementById('formatPopularityBars');
            const formatEntries = Object.entries(res.format_popularity || {});
            const maxFormat = Math.max(...formatEntries.map(([, c]) => c), 1);
            formatContainer.innerHTML = formatEntries.map(([fmt, count]) => {
                const pct = Math.round((count / maxFormat) * 100);
                return `
                    <div>
                        <div class="d-flex justify-between text-xs mb-xs">
                            <span class="fw-bold">${fmt} Format</span>
                            <span class="text-muted">${count} fixtures</span>
                        </div>
                        <div style="height:8px; background:var(--bg-input); border-radius:4px; overflow:hidden;">
                            <div style="width:${pct}%; height:100%; background:var(--accent-blue); transition:width 0.4s ease;"></div>
                        </div>
                    </div>
                `;
            }).join('');

            // Poll trends
            const trendsBody = document.getElementById('pollTrendsTableBody');
            if (!res.poll_trends.length) {
                trendsBody.innerHTML = '<tr><td colspan="6" class="text-center py-md text-muted">No poll history yet</td></tr>';
            } else {
                trendsBody.innerHTML = res.poll_trends.map(pt => {
                    const conv = pt.views > 0 ? Math.round((pt.votes / pt.views) * 100) : 0;
                    return `
                        <tr>
                            <td><span class="fw-semibold text-sm">${TM.escapeHtml(pt.title)}</span></td>
                            <td><span class="text-xs text-muted">${pt.date}</span></td>
                            <td>${pt.views}</td>
                            <td><strong>${pt.votes}</strong></td>
                            <td>
                                <span class="badge ${conv >= 70 ? 'badge-success' : 'badge-warning'}">${conv}%</span>
                            </td>
                            <td>${pt.comments}</td>
                        </tr>
                    `;
                }).join('');
            }
        }
    } catch (e) {
        TM.toast('Failed to load analysis', 'error');
    }
}
</script>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
