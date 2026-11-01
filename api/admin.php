<?php
/**
 * Admin API
 * Dedicated administrative endpoints for users, payments, reports, pattern analysis, and audit logs
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if (!isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
}

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'users_list':
        handleUsersList();
        break;

    case 'user_delete':
        handleUserDelete();
        break;

    case 'user_add':
        handleUserAdd();
        break;

    case 'user_toggle_role':
        handleUserToggleRole();
        break;

    case 'payments_list':
        handlePaymentsList();
        break;

    case 'payment_save':
        handlePaymentSave();
        break;

    case 'payment_toggle_status':
        handlePaymentToggleStatus();
        break;

    case 'reports':
        handleReports();
        break;

    case 'analysis':
        handleAnalysis();
        break;

    case 'logs':
        handleLogs();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

// ============================================================
// USERS
// ============================================================

function handleUsersList() {
    $users = getUsers();
    $out = [];
    foreach ($users as $u) {
        $referenced = isUserReferenced($u['id']);
        $out[] = [
            'id' => $u['id'],
            'first_name' => $u['first_name'],
            'last_name' => $u['last_name'] ?? '',
            'full_name' => $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : ''),
            'dob' => $u['dob'],
            'dob_display' => formatDobDisplay($u['dob'] ?? ''),
            'role' => $u['role'] ?? 'player',
            'created_at' => $u['created_at'] ?? '',
            'last_login' => $u['last_login'] ?? '',
            'is_referenced' => $referenced,
            'can_delete' => !$referenced && ($u['id'] !== $_SESSION['user_id']),
        ];
    }
    jsonResponse(['success' => true, 'users' => $out]);
}

function handleUserDelete() {
    global $currentUserId;
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['id'] ?? '';

    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    if ($userId === $currentUserId) {
        jsonResponse(['success' => false, 'message' => 'You cannot delete your own admin account']);
        return;
    }

    // STRICT CHECK: Cannot delete if referenced in polls or matches!
    if (isUserReferenced($userId)) {
        jsonResponse([
            'success' => false,
            'message' => 'Cannot delete user: This player is referenced in active or historical matches/polls.'
        ]);
        return;
    }

    $users = getUsers();
    $newUsers = [];
    $deletedName = '';
    foreach ($users as $u) {
        if ($u['id'] === $userId) {
            $deletedName = $u['first_name'] . ' ' . ($u['last_name'] ?? '');
            continue;
        }
        $newUsers[] = $u;
    }

    saveUsers($newUsers);
    logAction('user_delete', $currentUserId, ['deleted_user_id' => $userId, 'name' => $deletedName]);

    jsonResponse(['success' => true, 'message' => "User {$deletedName} deleted successfully."]);
}

function handleUserAdd() {
    global $currentUserId;
    $input = json_decode(file_get_contents('php://input'), true);
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $dob = normalizeDob(trim($input['dob'] ?? ''));
    $role = ($input['role'] ?? 'player') === 'admin' ? 'admin' : 'player';

    if (!$firstName || !$dob) {
        jsonResponse(['success' => false, 'message' => 'First name and DOB are required']);
        return;
    }

    $existing = findUserByNameAndDob($firstName, $dob);
    if ($existing) {
        jsonResponse(['success' => false, 'message' => 'A user with this name and DOB already exists']);
        return;
    }

    $user = registerUser($firstName, $lastName, $dob);
    if ($role === 'admin') {
        $users = getUsers();
        foreach ($users as &$u) {
            if ($u['id'] === $user['id']) {
                $u['role'] = 'admin';
                break;
            }
        }
        saveUsers($users);
    }

    jsonResponse(['success' => true, 'message' => "User {$firstName} added successfully!", 'user' => $user]);
}

function handleUserToggleRole() {
    global $currentUserId;
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['id'] ?? '';

    if ($userId === $currentUserId) {
        jsonResponse(['success' => false, 'message' => 'Cannot demote yourself']);
        return;
    }

    $users = getUsers();
    foreach ($users as &$u) {
        if ($u['id'] === $userId) {
            $u['role'] = ($u['role'] === 'admin') ? 'player' : 'admin';
            saveUsers($users);
            logAction('user_role_change', $currentUserId, ['target' => $userId, 'new_role' => $u['role']]);
            jsonResponse(['success' => true, 'new_role' => $u['role']]);
            return;
        }
    }

    jsonResponse(['success' => false, 'message' => 'User not found'], 404);
}

// ============================================================
// PAYMENTS (Admin only)
// ============================================================

function handlePaymentsList() {
    $payments = getPayments();
    $matches = getMatches();
    $users = getUsers();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
    }

    // Enrich payment records
    $enriched = [];
    $totalTurfFees = 0;
    $totalCollected = 0;
    $totalPending = 0;

    foreach ($payments as $p) {
        $totalTurfFees += (float)($p['turf_fee'] ?? 0);
        $playerList = [];
        foreach ($p['players'] ?? [] as $pl) {
            $amount = (float)($pl['amount'] ?? 0);
            $isPaid = ($pl['status'] ?? 'pending') === 'paid';
            if ($isPaid) $totalCollected += $amount;
            else $totalPending += $amount;

            $playerList[] = [
                'user_id' => $pl['user_id'],
                'user_name' => $userMap[$pl['user_id']] ?? ($pl['name'] ?? 'Player'),
                'amount' => $amount,
                'status' => $pl['status'] ?? 'pending',
                'paid_at' => $pl['paid_at'] ?? null,
            ];
        }

        $enriched[] = [
            'id' => $p['id'],
            'match_id' => $p['match_id'] ?? null,
            'title' => $p['title'] ?? 'Turf Booking',
            'date' => $p['date'] ?? date('Y-m-d'),
            'turf_fee' => (float)($p['turf_fee'] ?? 0),
            'per_player_fee' => (float)($p['per_player_fee'] ?? 0),
            'players' => $playerList,
            'notes' => $p['notes'] ?? '',
        ];
    }

    jsonResponse([
        'success' => true,
        'payments' => $enriched,
        'summary' => [
            'total_turf_fees' => $totalTurfFees,
            'total_collected' => $totalCollected,
            'total_pending' => $totalPending,
            'net_balance' => $totalCollected - $totalTurfFees,
        ],
        'matches' => array_map(fn($m) => ['id' => $m['id'], 'title' => $m['title'], 'date' => $m['date']], $matches),
    ]);
}

function handlePaymentSave() {
    global $currentUserId;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? generateId();
    $matchId = $input['match_id'] ?? null;
    $title = trim($input['title'] ?? 'Turf Match');
    $date = $input['date'] ?? date('Y-m-d');
    $turfFee = (float)($input['turf_fee'] ?? 0);
    $players = $input['players'] ?? []; // [{ user_id, amount, status }]
    $notes = trim($input['notes'] ?? '');

    $perPlayerFee = count($players) > 0 ? round($turfFee / count($players), 2) : 0;

    $payments = getPayments();
    $found = false;
    foreach ($payments as &$p) {
        if ($p['id'] === $id) {
            $p['match_id'] = $matchId;
            $p['title'] = $title;
            $p['date'] = $date;
            $p['turf_fee'] = $turfFee;
            $p['per_player_fee'] = $perPlayerFee;
            $p['players'] = $players;
            $p['notes'] = $notes;
            $p['updated_at'] = date('c');
            $found = true;
            break;
        }
    }

    if (!$found) {
        $payments[] = [
            'id' => $id,
            'match_id' => $matchId,
            'title' => $title,
            'date' => $date,
            'turf_fee' => $turfFee,
            'per_player_fee' => $perPlayerFee,
            'players' => $players,
            'notes' => $notes,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    savePayments($payments);
    logAction('payment_save', $currentUserId, ['payment_id' => $id, 'turf_fee' => $turfFee]);

    jsonResponse(['success' => true, 'message' => 'Payment record saved']);
}

function handlePaymentToggleStatus() {
    global $currentUserId;
    $input = json_decode(file_get_contents('php://input'), true);
    $paymentId = $input['payment_id'] ?? '';
    $userId = $input['user_id'] ?? '';

    $payments = getPayments();
    foreach ($payments as &$p) {
        if ($p['id'] !== $paymentId) continue;

        foreach ($p['players'] as &$pl) {
            if ($pl['user_id'] === $userId) {
                $isPaid = ($pl['status'] === 'paid');
                $pl['status'] = $isPaid ? 'pending' : 'paid';
                $pl['paid_at'] = $isPaid ? null : date('c');

                savePayments($payments);
                logAction('payment_toggle', $currentUserId, [
                    'payment_id' => $paymentId,
                    'user_id' => $userId,
                    'new_status' => $pl['status']
                ]);

                jsonResponse(['success' => true, 'new_status' => $pl['status']]);
                return;
            }
        }
    }

    jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
}

// ============================================================
// REPORTS
// ============================================================

function handleReports() {
    $matches = getMatches();
    $polls = getPolls();
    $users = getUsers();
    $activePlayers = getActivePlayers(30);

    // Compute activity per player
    $playerStats = [];
    foreach ($users as $u) {
        $uid = $u['id'];
        $name = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
        $playerStats[$uid] = [
            'id' => $uid,
            'name' => $name,
            'matches_played' => 0,
            'votes_cast' => 0,
            'comments_posted' => 0,
            'last_login' => $u['last_login'] ?? 'Never',
            'vote_response_times' => [],
        ];
    }

    // Match counts
    foreach ($matches as $m) {
        $playersInMatch = array_merge($m['team_a']['players'] ?? [], $m['team_b']['players'] ?? []);
        foreach ($playersInMatch as $pid) {
            if (isset($playerStats[$pid])) {
                $playerStats[$pid]['matches_played']++;
            }
        }
    }

    // Poll counts & fastest voters
    foreach ($polls as $p) {
        $pollCreatedAt = strtotime($p['created_at']);
        foreach ($p['votes'] ?? [] as $v) {
            $vid = $v['user_id'];
            if (isset($playerStats[$vid])) {
                $playerStats[$vid]['votes_cast']++;
                if (isset($v['voted_at'])) {
                    $diffMinutes = max(0, round((strtotime($v['voted_at']) - $pollCreatedAt) / 60, 1));
                    $playerStats[$vid]['vote_response_times'][] = $diffMinutes;
                }
            }
        }

        foreach ($p['comments'] ?? [] as $c) {
            $cid = $c['user_id'];
            if (isset($playerStats[$cid])) {
                $playerStats[$cid]['comments_posted']++;
            }
        }
    }

    // Calculate average response time
    foreach ($playerStats as &$ps) {
        $times = $ps['vote_response_times'];
        $ps['avg_vote_time_min'] = count($times) > 0 ? round(array_sum($times) / count($times), 1) : null;
        $ps['activity_score'] = ($ps['matches_played'] * 3) + ($ps['votes_cast'] * 2) + ($ps['comments_posted'] * 1);
        unset($ps['vote_response_times']);
    }

    // Sort descending by most active
    $mostActive = array_values($playerStats);
    usort($mostActive, fn($a, $b) => $b['activity_score'] - $a['activity_score']);

    // Fastest voters (at least 1 vote)
    $fastestVoters = array_filter(array_values($playerStats), fn($p) => $p['avg_vote_time_min'] !== null);
    usort($fastestVoters, fn($a, $b) => $a['avg_vote_time_min'] <=> $b['avg_vote_time_min']);

    $totalMatches = count($matches);

    jsonResponse([
        'success' => true,
        'summary' => [
            'total_matches' => $totalMatches,
            'total_players' => count($users),
            'active_players_count' => count($activePlayers),
            'total_polls' => count($polls),
        ],
        'most_active_players' => $mostActive,
        'fastest_voters' => array_slice($fastestVoters, 0, 10),
    ]);
}

// ============================================================
// ANALYSIS (Pattern recognition & turf spending)
// ============================================================

function handleAnalysis() {
    $matches = getMatches();
    $payments = getPayments();
    $polls = getPolls();

    // 1. Day of week pattern
    $dayCounts = ['Sunday' => 0, 'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0];
    foreach ($matches as $m) {
        $day = date('l', strtotime($m['date']));
        if (isset($dayCounts[$day])) $dayCounts[$day]++;
    }

    // 2. Spending over time
    $monthlySpend = [];
    $totalTurfSpent = 0;
    foreach ($payments as $p) {
        $fee = (float)($p['turf_fee'] ?? 0);
        $totalTurfSpent += $fee;
        $month = date('M Y', strtotime($p['date']));
        $monthlySpend[$month] = ($monthlySpend[$month] ?? 0) + $fee;
    }

    // 3. Format popularity
    $formatCounts = ['6v6' => 0, '7v7' => 0, '8v8' => 0];
    foreach ($matches as $m) {
        $fmt = $m['format'] ?? '6v6';
        if (isset($formatCounts[$fmt])) $formatCounts[$fmt]++;
    }

    // 4. Voting participation trend
    $pollTrends = [];
    foreach ($polls as $p) {
        $pollTrends[] = [
            'title' => $p['title'],
            'votes' => count($p['votes'] ?? []),
            'views' => count($p['views'] ?? []),
            'comments' => count($p['comments'] ?? []),
            'date' => date('M j', strtotime($p['created_at'])),
        ];
    }

    jsonResponse([
        'success' => true,
        'day_patterns' => $dayCounts,
        'total_spent' => $totalTurfSpent,
        'monthly_spend' => $monthlySpend,
        'format_popularity' => $formatCounts,
        'poll_trends' => array_slice($pollTrends, -10),
    ]);
}

// ============================================================
// AUDIT LOGS
// ============================================================

function handleLogs() {
    $type = $_GET['type'] ?? 'actions'; // 'logins', 'actions', 'errors'

    if ($type === 'logins') {
        $logs = readJsonFile(LOGIN_LOG_FILE);
    } elseif ($type === 'errors') {
        $logs = readJsonFile(ERROR_LOG_FILE);
    } else {
        $logs = readJsonFile(ACTION_LOG_FILE);
    }

    usort($logs, fn($a, $b) => strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? ''));

    $users = getUsers();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
    }

    foreach ($logs as &$l) {
        if (isset($l['user_id'])) {
            $l['user_name'] = $userMap[$l['user_id']] ?? ($l['first_name'] ?? 'System');
        }
    }

    jsonResponse(['success' => true, 'logs' => array_slice($logs, 0, 150)]);
}
