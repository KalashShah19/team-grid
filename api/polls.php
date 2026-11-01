<?php
/**
 * Polls API
 * Handles listing, creating, voting, admin voting, commenting, closing, and viewer tracking
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$currentUserId = $_SESSION['user_id'];
$currentUserAdmin = isAdmin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleListPolls();
        break;

    case 'get':
        handleGetPoll();
        break;

    case 'create':
        handleCreatePoll();
        break;

    case 'vote':
        handleVote();
        break;

    case 'admin_vote':
        handleAdminVote();
        break;

    case 'comment':
        handleAddComment();
        break;

    case 'close':
        handleClosePoll();
        break;

    case 'lock':
        handleLockPoll();
        break;

    case 'delete':
        handlePollDelete();
        break;

    case 'unvoted_viewers':
        handleUnvotedViewers();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleListPolls() {
    global $currentUserAdmin;
    checkExpiredPolls();
    $polls = getPolls();

    // If player, active and locked polls are visible.
    if (!$currentUserAdmin) {
        $polls = array_filter($polls, fn($p) => isset($p['status']) && in_array($p['status'], ['active', 'locked']));
    }

    // Sort newest first
    usort($polls, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

    // Add extra computed fields
    $output = [];
    foreach ($polls as $poll) {
        $totalVotes = 0;
        foreach ($poll['votes'] ?? [] as $v) {
            $totalVotes += 1 + (int)($v['guest_count'] ?? 0);
        }
        $myVote = null;
        if (isset($poll['votes'])) {
            foreach ($poll['votes'] as $v) {
                if ($v['user_id'] === $_SESSION['user_id']) {
                    $myVote = $v['option_index'];
                    break;
                }
            }
        }

        $breakdownParts = [];
        foreach ($poll['options'] ?? [] as $idx => $opt) {
            $optCount = 0;
            foreach ($poll['votes'] ?? [] as $v) {
                if (($v['option_index'] ?? -1) === $idx) {
                    $optCount += 1 + (int)($v['guest_count'] ?? 0);
                }
            }
            $breakdownParts[] = "{$optCount} " . strtolower(trim($opt));
        }
        $breakdownText = !empty($breakdownParts) ? implode('  ', $breakdownParts) : "{$totalVotes} votes";

        $output[] = [
            'id' => $poll['id'],
            'title' => $poll['title'],
            'options' => $poll['options'] ?? [],
            'tag' => $poll['tag'] ?? 'internal',
            'status' => $poll['status'] ?? 'active',
            'deadline' => $poll['deadline'] ?? null,
            'max_votes' => $poll['max_votes'] ?? null,
            'turf' => $poll['turf'] ?? '',
            'total_votes' => $totalVotes,
            'breakdown_text' => $breakdownText,
            'my_vote' => $myVote,
            'comments_count' => count($poll['comments'] ?? []),
            'created_at' => $poll['created_at'],
        ];
    }

    jsonResponse(['success' => true, 'polls' => array_values($output)]);
}

function handleGetPoll() {
    global $currentUserId, $currentUserAdmin;
    checkExpiredPolls();
    $id = $_GET['id'] ?? '';
    $poll = findPollById($id);

    if (!$poll) {
        jsonResponse(['success' => false, 'message' => 'Poll not found'], 404);
        return;
    }

    // If player and poll is closed/inactive, allow active and locked polls
    if (!$currentUserAdmin && !in_array($poll['status'] ?? '', ['active', 'locked'])) {
        jsonResponse(['success' => false, 'message' => 'This poll has ended and is no longer available'], 403);
        return;
    }

    // Mark as viewed by this user
    markPollViewed($id, $currentUserId);
    $poll = findPollById($id); // reload after marking viewed

    // Enrich voter names
    $users = getUsers();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
    }

    $enrichedVotes = [];
    $myVote = null;
    $myGuestCount = 0;
    $myGuestNames = [];
    $totalHeadcount = 0;
    foreach ($poll['votes'] ?? [] as $v) {
        $uName = $userMap[$v['user_id']] ?? 'Unknown';
        $gCount = (int)($v['guest_count'] ?? 0);
        $gNames = $v['guest_names'] ?? [];
        $totalHeadcount += 1 + $gCount;

        $enrichedVotes[] = [
            'user_id' => $v['user_id'],
            'user_name' => $uName,
            'option_index' => $v['option_index'],
            'guest_count' => $gCount,
            'guest_names' => $gNames,
            'voted_at' => $v['voted_at'],
            'edited_at' => $v['edited_at'] ?? null,
        ];
        if ($v['user_id'] === $currentUserId) {
            $myVote = $v['option_index'];
            $myGuestCount = $gCount;
            $myGuestNames = $gNames;
        }
    }

    // Enrich comments
    $enrichedComments = [];
    foreach ($poll['comments'] ?? [] as $c) {
        $enrichedComments[] = [
            'id' => $c['id'],
            'user_id' => $c['user_id'],
            'user_name' => $userMap[$c['user_id']] ?? 'Unknown',
            'text' => $c['text'],
            'created_at' => $c['created_at'],
        ];
    }

    // Compute option counts (headcount includes guests)
    $optionCounts = [];
    foreach ($poll['options'] as $idx => $opt) {
        $optVotes = array_filter($enrichedVotes, fn($v) => $v['option_index'] === $idx);
        $count = 0;
        $voters = [];
        foreach ($optVotes as $ov) {
            $count += 1 + $ov['guest_count'];
            $voters[] = [
                'user_id' => $ov['user_id'],
                'name' => $ov['user_name'],
                'guest_count' => $ov['guest_count'],
                'guest_names' => $ov['guest_names'],
            ];
        }
        $optionCounts[] = [
            'title' => $opt,
            'count' => $count,
            'voters' => $voters,
        ];
    }

    // Find linked team selection and match for this poll
    $linkedSelection = null;
    $linkedMatch = null;
    $isCaptain = false;

    $selections = getTeamSelections();
    foreach ($selections as $sel) {
        if (($sel['poll_id'] ?? '') === $poll['id']) {
            $linkedSelection = [
                'id' => $sel['id'],
                'status' => $sel['status'] ?? 'drafting',
                'format' => $sel['format'] ?? '',
                'team_a_name' => $sel['team_a']['name'] ?? 'Team A',
                'team_b_name' => $sel['team_b']['name'] ?? 'Team B',
            ];
            // Check if current user is a captain
            if (($sel['team_a']['captain_id'] ?? '') === $currentUserId ||
                ($sel['team_b']['captain_id'] ?? '') === $currentUserId) {
                $isCaptain = true;
            }
            break;
        }
    }

    if ($linkedSelection) {
        $matches = getMatches();
        foreach ($matches as $m) {
            if (($m['selection_id'] ?? '') === $linkedSelection['id']) {
                $inMatchA = in_array($currentUserId, $m['team_a']['players'] ?? []);
                $inMatchB = in_array($currentUserId, $m['team_b']['players'] ?? []);
                $isInMatch = $inMatchA || $inMatchB || $currentUserAdmin;
                $linkedMatch = [
                    'id' => $m['id'],
                    'title' => $m['title'],
                    'status' => $m['status'] ?? 'upcoming',
                    'is_participant' => $isInMatch,
                ];
                // Also check captain status from match
                if (($m['team_a']['captain_id'] ?? '') === $currentUserId ||
                    ($m['team_b']['captain_id'] ?? '') === $currentUserId) {
                    $isCaptain = true;
                }
                break;
            }
        }
    }

    $pollData = [
        'id' => $poll['id'],
        'title' => $poll['title'],
        'options' => $poll['options'],
        'option_stats' => $optionCounts,
        'tag' => $poll['tag'] ?? 'internal',
        'status' => $poll['status'] ?? 'active',
        'deadline' => $poll['deadline'] ?? null,
        'max_votes' => $poll['max_votes'] ?? null,
        'turf' => $poll['turf'] ?? '',
        'total_votes' => $totalHeadcount,
        'my_vote' => $myVote,
        'my_guest_count' => $myGuestCount,
        'my_guest_names' => $myGuestNames,
        'votes' => $enrichedVotes,
        'comments' => $enrichedComments,
        'views_count' => count($poll['views'] ?? []),
        'created_at' => $poll['created_at'],
        'is_admin' => $currentUserAdmin,
        'is_captain' => $isCaptain,
        'linked_selection' => $linkedSelection,
        'linked_match' => $linkedMatch,
    ];

    jsonResponse(['success' => true, 'poll' => $pollData]);
}

function handleCreatePoll() {
    global $currentUserAdmin, $currentUserId;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $title = trim($input['title'] ?? '');
    $turf = trim($input['turf'] ?? '');
    $options = $input['options'] ?? [];
    $tag = in_array($input['tag'] ?? '', ['internal', 'friendly']) ? $input['tag'] : 'internal';
    $deadline = !empty($input['deadline']) ? date('c', strtotime($input['deadline'])) : null;
    $maxVotes = !empty($input['max_votes']) ? (int)$input['max_votes'] : null;

    if (empty($turf) && $maxVotes) {
        $turf = getTurfByPlayerCount($maxVotes);
    }

    if (!$title) {
        jsonResponse(['success' => false, 'message' => 'Poll title is required']);
        return;
    }

    // Filter valid non-empty options
    $cleanOptions = [];
    foreach ($options as $opt) {
        $o = trim($opt);
        if ($o !== '') $cleanOptions[] = $o;
    }

    if (count($cleanOptions) < 2) {
        jsonResponse(['success' => false, 'message' => 'At least two options are required']);
        return;
    }

    $poll = createPoll($title, $cleanOptions, $tag, $deadline, $maxVotes, $currentUserId, $turf);

    jsonResponse([
        'success' => true,
        'message' => 'Poll created successfully!',
        'poll_id' => $poll['id']
    ]);
}

function handleVote() {
    global $currentUserId;
    $input = json_decode(file_get_contents('php://input'), true);
    $pollId = $input['poll_id'] ?? '';
    $optionIndex = isset($input['option_index']) ? (int)$input['option_index'] : -1;
    $guestCount = isset($input['guest_count']) ? (int)$input['guest_count'] : 0;
    $guestNames = isset($input['guest_names']) && is_array($input['guest_names']) ? $input['guest_names'] : [];

    if (!$pollId || $optionIndex < 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid vote data']);
        return;
    }

    $res = votePoll($pollId, $currentUserId, $optionIndex, $guestCount, $guestNames);
    jsonResponse($res);
}

function handleAdminVote() {
    global $currentUserAdmin;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin only'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $pollId = $input['poll_id'] ?? '';
    $targetUserId = $input['user_id'] ?? '';
    $optionIndex = isset($input['option_index']) ? (int)$input['option_index'] : -1;
    $guestCount = isset($input['guest_count']) ? (int)$input['guest_count'] : 0;
    $guestNames = isset($input['guest_names']) && is_array($input['guest_names']) ? $input['guest_names'] : [];

    if (!$pollId || !$targetUserId || $optionIndex < 0) {
        jsonResponse(['success' => false, 'message' => 'Missing target user or option']);
        return;
    }

    $res = votePoll($pollId, $targetUserId, $optionIndex, $guestCount, $guestNames);
    if ($res['success']) {
        logAction('admin_proxy_vote', $_SESSION['user_id'], [
            'poll_id' => $pollId,
            'for_user' => $targetUserId,
            'option' => $optionIndex,
            'guest_count' => $guestCount,
        ]);
    }
    jsonResponse($res);
}

function handleAddComment() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $pollId = $input['poll_id'] ?? '';
    $text = trim($input['text'] ?? '');

    if (!$pollId || !$text) {
        jsonResponse(['success' => false, 'message' => 'Comment cannot be empty']);
        return;
    }

    $poll = findPollById($pollId);
    if (!$poll) {
        jsonResponse(['success' => false, 'message' => 'Poll not found'], 404);
        return;
    }

    // Players can comment on active and locked polls
    if (!$currentUserAdmin && !in_array($poll['status'] ?? '', ['active', 'locked'])) {
        jsonResponse(['success' => false, 'message' => 'This poll has ended and comments are closed'], 403);
        return;
    }

    $res = addComment($pollId, $currentUserId, $text);
    jsonResponse($res);
}

function handleClosePoll() {
    global $currentUserAdmin;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $pollId = $input['poll_id'] ?? '';

    if (!$pollId) {
        jsonResponse(['success' => false, 'message' => 'Poll ID required']);
        return;
    }

    $ok = closePoll($pollId);
    jsonResponse(['success' => $ok, 'message' => $ok ? 'Poll has been closed' : 'Failed to close poll']);
}

function handleLockPoll() {
    global $currentUserAdmin;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $pollId = $input['poll_id'] ?? '';

    if (!$pollId) {
        jsonResponse(['success' => false, 'message' => 'Poll ID required']);
        return;
    }

    $ok = lockPoll($pollId);
    jsonResponse(['success' => $ok, 'message' => $ok ? 'Poll locked for team selection' : 'Failed to lock poll']);
}

function handleUnvotedViewers() {
    global $currentUserAdmin;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin only'], 403);
        return;
    }

    $pollId = $_GET['poll_id'] ?? '';
    $poll = findPollById($pollId);
    if (!$poll) {
        jsonResponse(['success' => false, 'message' => 'Poll not found'], 404);
        return;
    }

    $views = $poll['views'] ?? [];
    $votedUserIds = array_map(fn($v) => $v['user_id'], $poll['votes'] ?? []);

    $unvotedIds = array_diff($views, $votedUserIds);
    $users = getUsers();
    $unvotedUsers = [];
    foreach ($users as $u) {
        if (in_array($u['id'], $unvotedIds)) {
            $unvotedUsers[] = [
                'id' => $u['id'],
                'name' => $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : ''),
                'dob' => $u['dob'] ?? '',
            ];
        }
    }

    jsonResponse(['success' => true, 'unvoted_viewers' => array_values($unvotedUsers)]);
}

function handlePollDelete() {
    global $currentUserAdmin, $currentUserId;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_POST['id'] ?? $_GET['id'] ?? '';

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Poll ID is required'], 400);
        return;
    }

    $ok = deletePoll($id);
    if ($ok) {
        jsonResponse(['success' => true, 'message' => 'Poll deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Poll not found or could not be deleted'], 404);
    }
}
