<?php
/**
 * Matches API
 * Handles match listings, detailed views with CSS badges, score and goal updates
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
        handleListMatches();
        break;

    case 'get':
        handleGetMatch();
        break;

    case 'update_score':
        handleUpdateScore();
        break;

    case 'create':
        handleCreateMatch();
        break;

    case 'delete':
        handleDeleteMatch();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleListMatches() {
    $matches = getMatches();
    usort($matches, fn($a, $b) => strtotime($b['date'] . ' ' . ($b['created_at'] ?? '')) - strtotime($a['date'] . ' ' . ($a['created_at'] ?? '')));

    $users = getUsers();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
    }

    $out = [];
    foreach ($matches as $m) {
        $totalPlayers = count($m['team_a']['players'] ?? []) + count($m['team_b']['players'] ?? []);
        
        $hasScore = isset($m['team_a']['score']) && $m['team_a']['score'] !== null &&
                    isset($m['team_b']['score']) && $m['team_b']['score'] !== null;

        $pNames = $m['player_names'] ?? [];
        $capAId = $m['team_a']['captain_id'] ?? '';
        $capBId = $m['team_b']['captain_id'] ?? '';
        $captainAName = $userMap[$capAId] ?? ($pNames[$capAId] ?? ($m['team_a']['captain_name'] ?? getUserDisplayName($capAId)));
        $captainBName = $userMap[$capBId] ?? ($pNames[$capBId] ?? ($m['team_b']['captain_name'] ?? getUserDisplayName($capBId)));

        $out[] = [
            'id' => $m['id'],
            'title' => $m['title'],
            'date' => $m['date'],
            'format' => $m['format'],
            'venue' => $m['venue'] ?? '',
            'team_a_name' => $m['team_a']['name'],
            'team_b_name' => $m['team_b']['name'],
            'captain_a_name' => $captainAName,
            'captain_b_name' => $captainBName,
            'total_players' => $totalPlayers,
            'has_score' => $hasScore,
            'team_a_score' => $hasScore ? $m['team_a']['score'] : null,
            'team_b_score' => $hasScore ? $m['team_b']['score'] : null,
            'status' => $m['status'] ?? 'upcoming',
            'poll_id' => $m['poll_id'] ?? null,
        ];
    }

    jsonResponse(['success' => true, 'matches' => $out]);
}

function handleGetMatch() {
    $id = $_GET['id'] ?? '';
    $match = findMatchById($id);

    if (!$match) {
        jsonResponse(['success' => false, 'message' => 'Match not found'], 404);
        return;
    }

    $users = getUsers();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
    }

    $pNames = $match['player_names'] ?? [];

    // Helper to format roster: captain listed FIRST, followed by other players
    $formatRoster = function($team) use ($userMap, $pNames) {
        $captainId = $team['captain_id'] ?? null;
        $allPlayers = $team['players'] ?? [];
        $subs = $team['subs'] ?? [];

        $roster = [];
        // Captain first!
        if ($captainId && in_array($captainId, $allPlayers)) {
            $roster[] = [
                'id' => $captainId,
                'name' => $userMap[$captainId] ?? ($pNames[$captainId] ?? ($team['captain_name'] ?? getUserDisplayName($captainId))),
                'is_captain' => true,
                'is_sub' => in_array($captainId, $subs),
            ];
        }

        // Remaining players
        foreach ($allPlayers as $pid) {
            if ($pid === $captainId) continue;
            $roster[] = [
                'id' => $pid,
                'name' => $userMap[$pid] ?? ($pNames[$pid] ?? getUserDisplayName($pid)),
                'is_captain' => false,
                'is_sub' => in_array($pid, $subs),
            ];
        }

        return $roster;
    };

    // Load formations (fallback to default pre-defined 2-1-2 / 2-2-2 / 3-2-1 presets)
    $formations = getFormations();
    $matchFormation = null;
    foreach ($formations as $f) {
        if ($f['match_id'] === $id) {
            $matchFormation = $f;
            break;
        }
    }

    $isSavedA = !empty($matchFormation['team_a']['saved']);
    $isSavedB = !empty($matchFormation['team_b']['saved']);

    $formationA = !empty($matchFormation['team_a']['positions'])
        ? $matchFormation['team_a']['positions']
        : getDefaultFormationPositions($match['format'], $match['team_a']['players'] ?? [], $match['team_a']['captain_id'] ?? null);

    $formationB = !empty($matchFormation['team_b']['positions'])
        ? $matchFormation['team_b']['positions']
        : getDefaultFormationPositions($match['format'], $match['team_b']['players'] ?? [], $match['team_b']['captain_id'] ?? null);

    $hasScore = isset($match['team_a']['score']) && $match['team_a']['score'] !== null &&
                isset($match['team_b']['score']) && $match['team_b']['score'] !== null;

    $hasGoals = !empty($match['goals']);

    $capAId = $match['team_a']['captain_id'] ?? '';
    $capBId = $match['team_b']['captain_id'] ?? '';
    $capAName = $userMap[$capAId] ?? ($pNames[$capAId] ?? ($match['team_a']['captain_name'] ?? getUserDisplayName($capAId)));
    $capBName = $userMap[$capBId] ?? ($pNames[$capBId] ?? ($match['team_b']['captain_name'] ?? getUserDisplayName($capBId)));

    $matchDetails = [
        'id' => $match['id'],
        'poll_id' => $match['poll_id'] ?? null,
        'title' => $match['title'],
        'date' => $match['date'],
        'format' => $match['format'],
        'venue' => $match['venue'] ?? '',
        'status' => $match['status'] ?? 'upcoming',
        'has_score' => $hasScore,
        'has_goals' => $hasGoals,
        'team_a' => [
            'name' => $match['team_a']['name'],
            'captain_id' => $capAId,
            'captain_name' => $capAName,
            'score' => $hasScore ? $match['team_a']['score'] : null,
            'roster' => $formatRoster($match['team_a']),
            'formation' => $formationA,
            'formation_saved' => $isSavedA,
            'formation_type' => $isSavedA ? 'captain' : 'ai',
        ],
        'team_b' => [
            'name' => $match['team_b']['name'],
            'captain_id' => $capBId,
            'captain_name' => $capBName,
            'score' => $hasScore ? $match['team_b']['score'] : null,
            'roster' => $formatRoster($match['team_b']),
            'formation' => $formationB,
            'formation_saved' => $isSavedB,
            'formation_type' => $isSavedB ? 'captain' : 'ai',
        ],
        'goals' => $match['goals'] ?? [],
    ];

    jsonResponse(['success' => true, 'match' => $matchDetails]);
}

function handleUpdateScore() {
    global $currentUserAdmin, $currentUserId;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin only'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $scoreA = isset($input['score_a']) && $input['score_a'] !== '' ? (int)$input['score_a'] : null;
    $scoreB = isset($input['score_b']) && $input['score_b'] !== '' ? (int)$input['score_b'] : null;
    $goals = $input['goals'] ?? []; // [{ player_id, player_name, minute, team }]

    $matches = getMatches();
    foreach ($matches as &$m) {
        if ($m['id'] !== $id) continue;

        $m['team_a']['score'] = $scoreA;
        $m['team_b']['score'] = $scoreB;
        $m['goals'] = $goals;
        $m['status'] = ($scoreA !== null && $scoreB !== null) ? 'completed' : $m['status'];
        $m['updated_at'] = date('c');

        saveMatches($matches);
        logAction('match_score_update', $currentUserId, ['match_id' => $id, 'score_a' => $scoreA, 'score_b' => $scoreB]);

        jsonResponse(['success' => true, 'message' => 'Match scores and goals updated!']);
        return;
    }

    jsonResponse(['success' => false, 'message' => 'Match not found'], 404);
}

function handleCreateMatch() {
    global $currentUserAdmin, $currentUserId;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin only'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $match = createMatch($input);
    jsonResponse(['success' => true, 'match_id' => $match['id']]);
}

function handleDeleteMatch() {
    global $currentUserAdmin, $currentUserId;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_POST['id'] ?? $_GET['id'] ?? '';

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Match ID is required'], 400);
        return;
    }

    $ok = deleteMatch($id);
    if ($ok) {
        jsonResponse(['success' => true, 'message' => 'Match deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Match not found or could not be deleted'], 404);
    }
}
