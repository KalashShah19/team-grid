<?php
/**
 * Formation API
 * Handles football pitch formation positioning, saving, per-match jersey numbers,
 * conflict detection, and strict team-visibility controls
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$currentUserId = $_SESSION['user_id'];
$currentUserAdmin = isAdmin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        handleGetFormation();
        break;

    case 'save':
        handleSaveFormation();
        break;

    case 'save_jersey':
        handleSaveJerseyNumber();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleGetFormation() {
    global $currentUserId, $currentUserAdmin;
    $matchId = $_GET['match_id'] ?? '';
    $match = findMatchById($matchId);

    if (!$match) {
        jsonResponse(['success' => false, 'message' => 'Match not found'], 404);
        return;
    }

    $formations = getFormations();
    $matchFormation = null;
    foreach ($formations as $f) {
        if ($f['match_id'] === $matchId) {
            $matchFormation = $f;
            break;
        }
    }

    // Default empty formation if not saved yet
    if (!$matchFormation) {
        $matchFormation = [
            'id' => generateId(),
            'match_id' => $matchId,
            'team_a' => ['saved' => false, 'positions' => [], 'subs' => [], 'jersey_numbers' => []],
            'team_b' => ['saved' => false, 'positions' => [], 'subs' => [], 'jersey_numbers' => []],
            'created_at' => date('c'),
        ];
    }

    $inTeamA = in_array($currentUserId, $match['team_a']['players'] ?? []) || ($match['team_a']['captain_id'] === $currentUserId);
    $inTeamB = in_array($currentUserId, $match['team_b']['players'] ?? []) || ($match['team_b']['captain_id'] === $currentUserId);

    // If player not in either team and not admin
    if (!$inTeamA && !$inTeamB && !$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'You are not a participant in this match'], 403);
        return;
    }

    // Player can view ONLY their own team formation!
    $visibleTeamA = ($inTeamA || $currentUserAdmin);
    $visibleTeamB = ($inTeamB || $currentUserAdmin);

    $isCaptainA = ($match['team_a']['captain_id'] === $currentUserId);
    $isCaptainB = ($match['team_b']['captain_id'] === $currentUserId);

    $users = getUsers();
    $userMap = [];
    $defaultJerseyMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
        $defaultJerseyMap[$u['id']] = $u['jersey_number'] ?? null;
    }
    $pNames = $match['player_names'] ?? [];

    // Build enriched player list with per-match jersey numbers + conflict detection
    $buildTeamPlayers = function($playerIds, $matchJerseys) use ($userMap, $defaultJerseyMap, $pNames) {
        $players = [];
        $numberCounts = [];

        // First pass: compute effective jersey number (match-specific > user default)
        foreach ($playerIds as $pid) {
            $matchNum = isset($matchJerseys[$pid]) ? (int)$matchJerseys[$pid] : null;
            $defaultNum = $defaultJerseyMap[$pid] ?? null;
            $effectiveNum = $matchNum ?? $defaultNum;
            $playerName = $userMap[$pid] ?? ($pNames[$pid] ?? getUserDisplayName($pid));

            $players[] = [
                'id' => $pid,
                'name' => $playerName,
                'jersey_number' => $effectiveNum,
            ];

            if ($effectiveNum !== null) {
                $numberCounts[$effectiveNum] = ($numberCounts[$effectiveNum] ?? 0) + 1;
            }
        }

        // Detect conflicting numbers (same number used by 2+ players on same team)
        $conflictedNumbers = [];
        foreach ($numberCounts as $num => $count) {
            if ($count > 1) {
                $conflictedNumbers[] = $num;
            }
        }

        return ['players' => $players, 'conflicts' => $conflictedNumbers];
    };

    $matchJerseysA = $matchFormation['team_a']['jersey_numbers'] ?? [];
    $matchJerseysB = $matchFormation['team_b']['jersey_numbers'] ?? [];

    $teamAData = $buildTeamPlayers($match['team_a']['players'] ?? [], $matchJerseysA);
    $teamBData = $buildTeamPlayers($match['team_b']['players'] ?? [], $matchJerseysB);

    // Default pre-defined formation fallback (2-1-2 for 6v6, 2-2-2 for 7v7, 3-2-1 for 8v8)
    $defaultPosA = getDefaultFormationPositions($match['format'], $match['team_a']['players'] ?? [], $match['team_a']['captain_id'] ?? null);
    $defaultPosB = getDefaultFormationPositions($match['format'], $match['team_b']['players'] ?? [], $match['team_b']['captain_id'] ?? null);

    $positionsA = !empty($matchFormation['team_a']['positions']) ? $matchFormation['team_a']['positions'] : $defaultPosA;
    $positionsB = !empty($matchFormation['team_b']['positions']) ? $matchFormation['team_b']['positions'] : $defaultPosB;

    $placedA = array_column($positionsA, 'player_id');
    $subsA = !empty($matchFormation['team_a']['subs']) ? $matchFormation['team_a']['subs'] : array_values(array_diff($match['team_a']['players'] ?? [], $placedA));

    $placedB = array_column($positionsB, 'player_id');
    $subsB = !empty($matchFormation['team_b']['subs']) ? $matchFormation['team_b']['subs'] : array_values(array_diff($match['team_b']['players'] ?? [], $placedB));

    $capAId = $match['team_a']['captain_id'] ?? '';
    $capBId = $match['team_b']['captain_id'] ?? '';
    $capAName = $userMap[$capAId] ?? ($pNames[$capAId] ?? ($match['team_a']['captain_name'] ?? getUserDisplayName($capAId)));
    $capBName = $userMap[$capBId] ?? ($pNames[$capBId] ?? ($match['team_b']['captain_name'] ?? getUserDisplayName($capBId)));

    $response = [
        'match_id' => $matchId,
        'match_title' => $match['title'],
        'format' => $match['format'],
        'can_edit_a' => $isCaptainA || $currentUserAdmin,
        'can_edit_b' => $isCaptainB || $currentUserAdmin,
        'team_a' => [
            'visible' => $visibleTeamA,
            'name' => $match['team_a']['name'],
            'captain_id' => $capAId,
            'captain_name' => $capAName,
            'saved' => $matchFormation['team_a']['saved'] ?? false,
            'formation_type' => (!empty($matchFormation['team_a']['saved'])) ? 'captain' : 'ai',
            'positions' => $visibleTeamA ? $positionsA : [],
            'subs' => $visibleTeamA ? $subsA : [],
            'all_players' => $visibleTeamA ? $teamAData['players'] : [],
            'jersey_conflicts' => $visibleTeamA ? $teamAData['conflicts'] : [],
        ],
        'team_b' => [
            'visible' => $visibleTeamB,
            'name' => $match['team_b']['name'],
            'captain_id' => $capBId,
            'captain_name' => $capBName,
            'saved' => $matchFormation['team_b']['saved'] ?? false,
            'formation_type' => (!empty($matchFormation['team_b']['saved'])) ? 'captain' : 'ai',
            'positions' => $visibleTeamB ? $positionsB : [],
            'subs' => $visibleTeamB ? $subsB : [],
            'all_players' => $visibleTeamB ? $teamBData['players'] : [],
            'jersey_conflicts' => $visibleTeamB ? $teamBData['conflicts'] : [],
        ],
    ];

    jsonResponse(['success' => true, 'formation' => $response]);
}

function handleSaveFormation() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $matchId = $input['match_id'] ?? '';
    $team = $input['team'] ?? ''; // 'team_a' or 'team_b'
    $positions = $input['positions'] ?? []; // [{ player_id, x, y, role }]
    $subs = $input['subs'] ?? []; // [player_id, ...]

    $match = findMatchById($matchId);
    if (!$match) {
        jsonResponse(['success' => false, 'message' => 'Match not found'], 404);
        return;
    }

    $isCapA = ($match['team_a']['captain_id'] === $currentUserId);
    $isCapB = ($match['team_b']['captain_id'] === $currentUserId);

    if ($team === 'team_a' && !$isCapA && !$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Only Team A Captain or Admin can save Team A formation'], 403);
        return;
    }
    if ($team === 'team_b' && !$isCapB && !$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Only Team B Captain or Admin can save Team B formation'], 403);
        return;
    }

    // Enforce formation player limit (e.g. 6v6 allows max 6 on pitch)
    $formatNum = 6;
    if (preg_match('/^(\d+)/', $match['format'] ?? '', $fmatches)) {
        $formatNum = (int)$fmatches[1];
    }
    if (count($positions) > $formatNum) {
        jsonResponse([
            'success' => false,
            'message' => "Cannot place more than {$formatNum} players on the pitch for {$match['format']}! Additional players must be substitutes."
        ]);
        return;
    }

    // Auto-compute subs if any team players were not placed on pitch
    $placedPlayerIds = array_column($positions, 'player_id');
    $allTeamPlayerIds = $match[$team]['players'] ?? [];
    $leftovers = array_diff($allTeamPlayerIds, $placedPlayerIds);

    // Merge explicitly selected subs and leftovers
    $finalSubs = array_values(array_unique(array_merge($subs, $leftovers)));

    $formations = getFormations();
    $found = false;
    foreach ($formations as &$f) {
        if ($f['match_id'] === $matchId) {
            // Preserve existing jersey_numbers when saving formation
            $existingJerseys = $f[$team]['jersey_numbers'] ?? [];
            $f[$team] = [
                'saved' => true,
                'positions' => $positions,
                'subs' => $finalSubs,
                'jersey_numbers' => $existingJerseys,
                'saved_at' => date('c'),
            ];
            $found = true;
            break;
        }
    }

    if (!$found) {
        $newFormation = [
            'id' => generateId(),
            'match_id' => $matchId,
            'team_a' => ($team === 'team_a') ? ['saved' => true, 'positions' => $positions, 'subs' => $finalSubs, 'jersey_numbers' => [], 'saved_at' => date('c')] : ['saved' => false, 'positions' => [], 'subs' => [], 'jersey_numbers' => []],
            'team_b' => ($team === 'team_b') ? ['saved' => true, 'positions' => $positions, 'subs' => $finalSubs, 'jersey_numbers' => [], 'saved_at' => date('c')] : ['saved' => false, 'positions' => [], 'subs' => [], 'jersey_numbers' => []],
            'created_at' => date('c'),
        ];
        $formations[] = $newFormation;
    }

    saveFormations($formations);

    // Also update match subs in matches.json
    $matches = getMatches();
    foreach ($matches as &$m) {
        if ($m['id'] === $matchId) {
            $m[$team]['subs'] = $finalSubs;
            $m['updated_at'] = date('c');
            break;
        }
    }
    saveMatches($matches);

    // Notify team players that formation is saved and viewable
    $teamName = $match[$team]['name'];
    foreach ($allTeamPlayerIds as $pid) {
        if ($pid !== $currentUserId && !str_starts_with($pid, 'temp_')) {
            createNotification($pid, 'formation_ready', "Tactical formation for {$teamName} is locked! Check your position on the pitch.", "formation?match_id={$matchId}");
        }
    }

    logAction('formation_save', $currentUserId, ['match_id' => $matchId, 'team' => $team]);

    jsonResponse(['success' => true, 'message' => "Formation saved successfully! Now visible to your squad."]);
}

function handleSaveJerseyNumber() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $matchId = $input['match_id'] ?? '';
    $team = $input['team'] ?? ''; // 'team_a' or 'team_b'
    $targetUserId = $input['user_id'] ?? $currentUserId;
    $jerseyNumber = (int)($input['jersey_number'] ?? 0);

    if ($jerseyNumber < 1 || $jerseyNumber > 99) {
        jsonResponse(['success' => false, 'message' => 'Jersey number must be between 1 and 99']);
        return;
    }

    $match = findMatchById($matchId);
    if (!$match) {
        jsonResponse(['success' => false, 'message' => 'Match not found'], 404);
        return;
    }

    // Determine which team the target player is on (if team not provided)
    if (!$team) {
        if (in_array($targetUserId, $match['team_a']['players'] ?? [])) {
            $team = 'team_a';
        } elseif (in_array($targetUserId, $match['team_b']['players'] ?? [])) {
            $team = 'team_b';
        } else {
            jsonResponse(['success' => false, 'message' => 'Player not found in either team'], 404);
            return;
        }
    }

    $isCaptainOfTeam = ($match[$team]['captain_id'] === $currentUserId);
    $isOwnJersey = ($targetUserId === $currentUserId);

    // Permission: captain of the team, admin, or editing own jersey
    if (!$isCaptainOfTeam && !$currentUserAdmin && !$isOwnJersey) {
        jsonResponse(['success' => false, 'message' => 'Only the team captain or admin can change jersey numbers'], 403);
        return;
    }

    // Save per-match jersey number in formation data
    $formations = getFormations();
    $found = false;
    foreach ($formations as &$f) {
        if ($f['match_id'] === $matchId) {
            if (!isset($f[$team]['jersey_numbers'])) {
                $f[$team]['jersey_numbers'] = [];
            }
            $f[$team]['jersey_numbers'][$targetUserId] = $jerseyNumber;
            $found = true;
            break;
        }
    }

    if (!$found) {
        // Create a new formation entry for this match
        $newFormation = [
            'id' => generateId(),
            'match_id' => $matchId,
            'team_a' => ['saved' => false, 'positions' => [], 'subs' => [], 'jersey_numbers' => []],
            'team_b' => ['saved' => false, 'positions' => [], 'subs' => [], 'jersey_numbers' => []],
            'created_at' => date('c'),
        ];
        $newFormation[$team]['jersey_numbers'][$targetUserId] = $jerseyNumber;
        $formations[] = $newFormation;
    }

    saveFormations($formations);

    // Also save to user profile as default for future matches (only if editing own)
    if ($isOwnJersey) {
        $users = getUsers();
        foreach ($users as &$u) {
            if ($u['id'] === $targetUserId) {
                $u['jersey_number'] = $jerseyNumber;
                break;
            }
        }
        unset($u);
        saveUsers($users);
    }

    logAction('jersey_number_save', $currentUserId, ['target_user' => $targetUserId, 'jersey_number' => $jerseyNumber, 'match_id' => $matchId]);

    jsonResponse(['success' => true, 'jersey_number' => $jerseyNumber, 'message' => "Jersey #{$jerseyNumber} saved!"]);
}
