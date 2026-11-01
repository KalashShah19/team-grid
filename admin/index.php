<?php
/**
 * Admin Panel Dashboard
 * Quick metrics, active polls management, quick launchers for drafts and payments
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';
require_once INCLUDES_PATH . 'header.php';
require_once INCLUDES_PATH . 'navbar.php';

$users = getUsers();
$matches = getMatches();
$polls = getPolls();
$payments = getPayments();
$activePlayers = getActivePlayers(30);

$totalTurfFees = 0;
foreach ($payments as $p) {
    $totalTurfFees += (float)($p['turf_fee'] ?? 0);
}
?>

<div class="container container-lg">
    <div class="d-flex align-center justify-between mb-lg flex-wrap gap-md">
        <div>
            <h1 class="page-title">⚙️ Admin Dashboard</h1>
            <p class="page-subtitle">Squad control center & operational tools</p>
        </div>
        <div class="d-flex gap-sm flex-wrap">
            <a href="<?php echo BASE_URL; ?>/polls" class="btn btn-primary btn-sm">
                ➕ New Poll
            </a>
            <a href="<?php echo BASE_URL; ?>/team-selection" class="btn btn-secondary btn-sm">
                ⚡ Direct Team Selection
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/payments" class="btn btn-secondary btn-sm">
                💰 Payments Checklist
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="d-grid gap-md mb-xl" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="card p-md">
            <div class="d-flex align-center justify-between text-muted text-xs mb-xs">
                <span>TOTAL MATCHES</span>
                <span>⚽</span>
            </div>
            <div class="stat-value text-green" style="font-size:2rem; font-weight:700;"><?php echo count($matches); ?></div>
            <div class="text-xs text-secondary mt-xs">Fixtures recorded</div>
        </div>

        <div class="card p-md">
            <div class="d-flex align-center justify-between text-muted text-xs mb-xs">
                <span>TOTAL PLAYERS</span>
                <span>👥</span>
            </div>
            <div class="stat-value text-blue" style="font-size:2rem; font-weight:700;"><?php echo count($users); ?></div>
            <div class="text-xs text-secondary mt-xs"><?php echo count($activePlayers); ?> active this month</div>
        </div>

        <div class="card p-md">
            <div class="d-flex align-center justify-between text-muted text-xs mb-xs">
                <span>TOTAL POLLS</span>
                <span>📊</span>
            </div>
            <div class="stat-value text-gold" style="font-size:2rem; font-weight:700;"><?php echo count($polls); ?></div>
            <div class="text-xs text-secondary mt-xs">All sessions</div>
        </div>

        <div class="card p-md">
            <div class="d-flex align-center justify-between text-muted text-xs mb-xs">
                <span>TURF FEES SPENT</span>
                <span>💰</span>
            </div>
            <div class="stat-value text-primary" style="font-size:2rem; font-weight:700;">₹<?php echo number_format($totalTurfFees, 0); ?></div>
            <div class="text-xs text-secondary mt-xs">Historical bookings</div>
        </div>
    </div>

    <!-- Admin Modules Navigation Cards -->
    <div class="d-grid gap-md mb-xl" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <a href="<?php echo BASE_URL; ?>/admin/users" class="card card-hover p-md" style="text-decoration:none;">
            <div class="d-flex align-center gap-sm mb-xs">
                <span style="font-size: 24px;">👥</span>
                <h3 style="font-size: var(--font-size-md);">Player Roster & DOBs</h3>
            </div>
            <p class="text-xs text-secondary">Manage player profiles, DOB registry, and safe deletions.</p>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/payments" class="card card-hover p-md" style="text-decoration:none;">
            <div class="d-flex align-center gap-sm mb-xs">
                <span style="font-size: 24px;">💰</span>
                <h3 style="font-size: var(--font-size-md);">Turf Fees & Payments</h3>
            </div>
            <p class="text-xs text-secondary">Player contribution tracking with paid/pending toggles.</p>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/reports" class="card card-hover p-md" style="text-decoration:none;">
            <div class="d-flex align-center gap-sm mb-xs">
                <span style="font-size: 24px;">📈</span>
                <h3 style="font-size: var(--font-size-md);">Activity Reports</h3>
            </div>
            <p class="text-xs text-secondary">Attendance rankings, fastest voters, and chat stats.</p>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/analysis" class="card card-hover p-md" style="text-decoration:none;">
            <div class="d-flex align-center gap-sm mb-xs">
                <span style="font-size: 24px;">🔍</span>
                <h3 style="font-size: var(--font-size-md);">Pattern Analysis</h3>
            </div>
            <p class="text-xs text-secondary">Matchday trends, spending patterns, and voting behaviors.</p>
        </a>

        <a href="<?php echo BASE_URL; ?>/admin/logs" class="card card-hover p-md" style="text-decoration:none;">
            <div class="d-flex align-center gap-sm mb-xs">
                <span style="font-size: 24px;">📋</span>
                <h3 style="font-size: var(--font-size-md);">System Logs</h3>
            </div>
            <p class="text-xs text-secondary">Full audit logs for logins, polls, team selections, and errors.</p>
        </a>
    </div>

    <!-- Polls Management Table -->
    <div class="card">
        <div class="card-header">
            <h3 style="font-size: var(--font-size-md);">All Polls Management</h3>
            <span class="badge badge-primary"><?php echo count($polls); ?> total</span>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Tag</th>
                        <th>Status</th>
                        <th>Votes</th>
                        <th>Views</th>
                        <th class="text-right">Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($polls)): ?>
                        <tr><td colspan="6" class="text-center py-md text-muted">No polls created yet</td></tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($polls) as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-sm">
                                        <a href="<?php echo BASE_URL; ?>/poll-detail?id=<?php echo $p['id']; ?>">
                                            <?php echo htmlspecialchars($p['title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-xs text-muted"><?php echo date('M j, Y', strtotime($p['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($p['tag'] ?? '') === 'friendly' ? 'badge-warning' : 'badge-primary'; ?>">
                                        <?php echo htmlspecialchars($p['tag'] ?? 'internal'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($p['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo htmlspecialchars($p['status'] ?? 'active'); ?>
                                    </span>
                                </td>
                                <td><?php echo count($p['votes'] ?? []); ?></td>
                                <td><?php echo count($p['views'] ?? []); ?></td>
                                <td class="text-right">
                                    <div class="d-flex gap-xs justify-end">
                                        <a href="<?php echo BASE_URL; ?>/poll-detail?id=<?php echo $p['id']; ?>" class="btn btn-secondary btn-xs">
                                            View Poll
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/team-selection?poll_id=<?php echo $p['id']; ?>" class="btn btn-primary btn-xs">
                                            Select Teams
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
