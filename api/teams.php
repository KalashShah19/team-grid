<?php
/**
 * Team Selection API
 * Handles draft setup, captain ready states, turn-by-turn drafting, team naming
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$currentUserId = $_SESSION['user_id'];
$currentUserAdmin = isAdmin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handleCreateSelection();
        break;

    case 'get':
        handleGetSelection();
        break;

    case 'ready':
        handleToggleReady();
        break;

    case 'rename':
        handleRenameTeam();
        break;

    case 'pick':
        handlePickPlayer();
        break;

    case 'finalize':
        handleFinalizeSelection();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleCreateSelection() {
    global $currentUserAdmin, $currentUserId;
    if (!$currentUserAdmin) {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $format = in_array($input['format'] ?? '', ['6v6', '7v7', '8v8']) ? $input['format'] : '6v6';
    $pollId = $input['poll_id'] ?? null;
    $teamAName = trim($input['team_a_name'] ?? 'Red Warriors');
    $teamBName = trim($input['team_b_name'] ?? 'Blue Strikers');
    $captainAId   = $input['captain_a_id'] ?? '';
    $captainBId   = $input['captain_b_id'] ?? '';
    $captainAName = trim($input['captain_a_name'] ?? '');  // set when captain was typed (not DB user)
    $captainBName = trim($input['captain_b_name'] ?? '');
    $directNames     = $input['direct_names'] ?? [];
    $extraPlayerIds  = $input['extra_player_ids'] ?? [];

    // Match typed names against existing registered users; otherwise assign a temp ID without adding to users.json
    $allUsersRef = getUsers();
    $nameToUserRef = [];
    $playerNamesMap = [];
    foreach ($allUsersRef as $u) {
        $fullName = trim($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : ''));
        $nameToUserRef[strtolower(trim($u['first_name']))] = $u;
        $nameToUserRef[strtolower($fullName)] = $u;
        $playerNamesMap[$u['id']] = $fullName;
    }

    $resolveUserOrTemp = function(string $name) use ($nameToUserRef, &$playerNamesMap): string {
        $clean = trim($name);
        $lower = strtolower($clean);
        if (isset($nameToUserRef[$lower])) {
            $u = $nameToUserRef[$lower];
            $uFullName = trim($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : ''));
            $playerNamesMap[$u['id']] = $uFullName;
            return $u['id'];
        }
        // Temporary player - DO NOT add to Users!
        $tempId = 'temp_' . rawurlencode($clean);
        $playerNamesMap[$tempId] = $clean;
        return $tempId;
    };

    if (str_starts_with($captainAId, '__typed__')) {
        $cName = $captainAName ?: rawurldecode(substr($captainAId, 9));
        $captainAId = $resolveUserOrTemp($cName);
        $captainAName = $playerNamesMap[$captainAId];
    } elseif (str_starts_with($captainAId, 'temp_')) {
        $cName = $captainAName ?: rawurldecode(substr($captainAId, 5));
        $captainAId = $resolveUserOrTemp($cName);
        $captainAName = $playerNamesMap[$captainAId];
    } elseif ($captainAId && isset($playerNamesMap[$captainAId])) {
        $captainAName = $playerNamesMap[$captainAId];
    }

    if (str_starts_with($captainBId, '__typed__')) {
        $cName = $captainBName ?: rawurldecode(substr($captainBId, 9));
        $captainBId = $resolveUserOrTemp($cName);
        $captainBName = $playerNamesMap[$captainBId];
    } elseif (str_starts_with($captainBId, 'temp_')) {
        $cName = $captainBName ?: rawurldecode(substr($captainBId, 5));
        $captainBId = $resolveUserOrTemp($cName);
        $captainBName = $playerNamesMap[$captainBId];
    } elseif ($captainBId && isset($playerNamesMap[$captainBId])) {
        $captainBName = $playerNamesMap[$captainBId];
    }

    $availablePlayers = [];
    $addedIds = [];

    if ($pollId) {
        // Start from poll voters
        $poll = findPollById($pollId);
        if ($poll) {
            foreach ($poll['votes'] ?? [] as $v) {
                // Only include players who voted "In" (option 0)
                if (isset($v['option_index']) && (int)$v['option_index'] !== 0) continue;

                $voterName = getUserDisplayName($v['user_id']);
                $playerNamesMap[$v['user_id']] = $voterName;

                if ($v['user_id'] !== $captainAId && $v['user_id'] !== $captainBId && !in_array($v['user_id'], $addedIds)) {
                    $availablePlayers[] = [
                        'id'   => $v['user_id'],
                        'name' => $voterName,
                        'is_temp' => false,
                    ];
                    $addedIds[] = $v['user_id'];
                }

                // Include guests as temporary players - DO NOT add to users.json!
                $gCount = (int)($v['guest_count'] ?? 0);
                $gNames = $v['guest_names'] ?? [];
                for ($gi = 1; $gi <= $gCount; $gi++) {
                    $hostName = getUserDisplayName($v['user_id']);
                    $gName = trim($gNames[$gi - 1] ?? '') ?: ($hostName . " (+{$gi})");
                    $guestUserId = $resolveUserOrTemp($gName);
                    if ($guestUserId !== $captainAId && $guestUserId !== $captainBId && !in_array($guestUserId, $addedIds)) {
                        $availablePlayers[] = [
                            'id'   => $guestUserId,
                            'name' => $gName,
                            'is_temp' => str_starts_with($guestUserId, 'temp_'),
                        ];
                        $addedIds[] = $guestUserId;
                    }
                }
            }
        }

        // Merge extra registered players (checked by admin)
        $allUsers = getUsers();
        foreach ($extraPlayerIds as $eid) {
            if (in_array($eid, $addedIds)) continue;
            if ($eid === $captainAId || $eid === $captainBId) continue;
            $u = findUserById($eid);
            if ($u) {
                $uName = $u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '');
                $playerNamesMap[$eid] = $uName;
                $availablePlayers[] = [
                    'id'   => $u['id'],
                    'name' => $uName,
                    'is_temp' => false,
                ];
                $addedIds[] = $eid;
            }
        }

        // Merge typed new names as temporary players - DO NOT add to users.json!
        foreach ($directNames as $rawName) {
            $cleanName = trim($rawName);
            if (!$cleanName) continue;
            $userId = $resolveUserOrTemp($cleanName);
            if ($userId === $captainAId || $userId === $captainBId) continue;
            if (in_array($userId, $addedIds)) continue;
            $availablePlayers[] = [
                'id' => $userId,
                'name' => $cleanName,
                'is_temp' => str_starts_with($userId, 'temp_'),
            ];
            $addedIds[] = $userId;
        }

    } else {
        // Direct mode: checked registered players + typed names
        $allUsers = getUsers();

        // Checked registered players
        foreach ($extraPlayerIds as $eid) {
            if ($eid === $captainAId || $eid === $captainBId) continue;
            $u = findUserById($eid);
            if ($u) {
                $uName = $u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : '');
                $playerNamesMap[$eid] = $uName;
                $availablePlayers[] = [
                    'id'   => $u['id'],
                    'name' => $uName,
                    'is_temp' => false,
                ];
                $addedIds[] = $eid;
            }
        }

        // Typed new names as temporary players - DO NOT add to users.json!
        foreach ($directNames as $rawName) {
            $cleanName = trim($rawName);
            if (!$cleanName) continue;
            $userId = $resolveUserOrTemp($cleanName);
            if ($userId === $captainAId || $userId === $captainBId) continue;
            if (in_array($userId, $addedIds)) continue;
            $availablePlayers[] = [
                'id' => $userId,
                'name' => $cleanName,
                'is_temp' => str_starts_with($userId, 'temp_'),
            ];
            $addedIds[] = $userId;
        }
    }


    $selections = getTeamSelections();
    $id = generateId();

    // Turf selection based on players / format:
    // 6 side match = 99 not out, 7 side match = valas pro, 8 side match = valas old
    $customTurf = trim($input['turf'] ?? $input['venue'] ?? '');
    $turfVenue = $customTurf ?: getTurfByFormat($format);
    $pollDate = date('Y-m-d');

    if ($pollId) {
        $polls = getPolls();
        foreach ($polls as &$poll) {
            if ($poll['id'] === $pollId) {
                $poll['status'] = 'locked';
                if (!empty($poll['turf']) && empty($customTurf)) {
                    $turfVenue = $poll['turf'];
                }
                if (!empty($poll['deadline'])) {
                    $pollDate = date('Y-m-d', strtotime($poll['deadline']));
                }
                break;
            }
        }
        unset($poll);
        savePolls($polls);
    }

    $selection = [
        'id' => $id,
        'poll_id' => $pollId,
        'format' => $format,
        'venue' => $turfVenue,
        'status' => 'drafting',
        'created_at' => date('c'),
        'current_turn' => 'team_a', // team_a picks first
        'player_names' => $playerNamesMap,
        'team_a' => [
            'name' => $teamAName,
            'captain_id' => $captainAId,
            'captain_name' => $captainAName ?: getUserDisplayName($captainAId),
            'ready' => false,
            'players' => $captainAId ? [$captainAId] : [],
        ],
        'team_b' => [
            'name' => $teamBName,
            'captain_id' => $captainBId,
            'captain_name' => $captainBName ?: getUserDisplayName($captainBId),
            'ready' => false,
            'players' => $captainBId ? [$captainBId] : [],
        ],
        'available_players' => $availablePlayers,
        'draft_history' => [],
    ];

    $selections[] = $selection;
    saveTeamSelections($selections);

    // Immediately instantiate upcoming match so it shows in Upcoming Matches!
    $matches = getMatches();
    $matchId = generateId();
    $newMatch = [
        'id' => $matchId,
        'selection_id' => $id,
        'poll_id' => $pollId ?: null,
        'title' => "{$teamAName} vs {$teamBName}",
        'date' => $pollDate,
        'format' => $format,
        'venue' => $turfVenue,
        'player_names' => $playerNamesMap,
        'team_a' => [
            'name' => $teamAName,
            'captain_id' => $captainAId,
            'captain_name' => $captainAName ?: getUserDisplayName($captainAId),
            'players' => $captainAId ? [$captainAId] : [],
            'subs' => [],
            'score' => null,
        ],
        'team_b' => [
            'name' => $teamBName,
            'captain_id' => $captainBId,
            'captain_name' => $captainBName ?: getUserDisplayName($captainBId),
            'players' => $captainBId ? [$captainBId] : [],
            'subs' => [],
            'score' => null,
        ],
        'goals' => [],
        'status' => 'upcoming',
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];
    $matches[] = $newMatch;
    saveMatches($matches);

    // Notify captains (registered users only)
    if ($captainAId && !str_starts_with($captainAId, 'temp_')) {
        createNotification($captainAId, 'captain_assigned', "You are appointed Captain for {$teamAName}! Get ready to draft your squad.", "team-selection?id={$id}");
    }
    if ($captainBId && !str_starts_with($captainBId, 'temp_')) {
        createNotification($captainBId, 'captain_assigned', "You are appointed Captain for {$teamBName}! Get ready to draft your squad.", "team-selection?id={$id}");
    }

    logAction('team_selection_create', $currentUserId, ['selection_id' => $id, 'format' => $format]);

    jsonResponse(['success' => true, 'selection_id' => $id]);
}

function handleGetSelection() {
    global $currentUserId, $currentUserAdmin;
    $id = $_GET['id'] ?? '';
    $selection = findTeamSelectionById($id);

    if (!$selection) {
        jsonResponse(['success' => false, 'message' => 'Selection not found'], 404);
        return;
    }

    // Enrich player names
    $users = getUsers();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['first_name'] . (isset($u['last_name']) && $u['last_name'] ? ' ' . $u['last_name'] : '');
    }
    $pNames = $selection['player_names'] ?? [];

    $enrichList = function($ids) use ($userMap, $pNames) {
        $out = [];
        foreach ($ids as $uid) {
            $out[] = [
                'id' => $uid,
                'name' => $userMap[$uid] ?? ($pNames[$uid] ?? getUserDisplayName($uid)),
            ];
        }
        return $out;
    };

    $selection['team_a']['player_details'] = $enrichList($selection['team_a']['players'] ?? []);
    $selection['team_b']['player_details'] = $enrichList($selection['team_b']['players'] ?? []);
    $selection['team_a']['captain_name'] = $userMap[$selection['team_a']['captain_id']] ?? ($pNames[$selection['team_a']['captain_id']] ?? ($selection['team_a']['captain_name'] ?? getUserDisplayName($selection['team_a']['captain_id'])));
    $selection['team_b']['captain_name'] = $userMap[$selection['team_b']['captain_id']] ?? ($pNames[$selection['team_b']['captain_id']] ?? ($selection['team_b']['captain_name'] ?? getUserDisplayName($selection['team_b']['captain_id'])));

    $isCaptainA = ($selection['team_a']['captain_id'] === $currentUserId);
    $isCaptainB = ($selection['team_b']['captain_id'] === $currentUserId);

    $selection['can_control_a'] = $isCaptainA || $currentUserAdmin;
    $selection['can_control_b'] = $isCaptainB || $currentUserAdmin;
    $selection['is_admin'] = $currentUserAdmin;
    $selection['is_my_turn'] = ($selection['current_turn'] === 'team_a' && ($isCaptainA || $currentUserAdmin)) ||
                               ($selection['current_turn'] === 'team_b' && ($isCaptainB || $currentUserAdmin));

    jsonResponse(['success' => true, 'selection' => $selection]);
}

function handleToggleReady() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $team = $input['team'] ?? ''; // 'team_a' or 'team_b'

    $selections = getTeamSelections();
    foreach ($selections as &$sel) {
        if ($sel['id'] !== $id) continue;

        if ($team === 'team_a' && ($sel['team_a']['captain_id'] === $currentUserId || $currentUserAdmin)) {
            $sel['team_a']['ready'] = !($sel['team_a']['ready'] ?? false);
        } elseif ($team === 'team_b' && ($sel['team_b']['captain_id'] === $currentUserId || $currentUserAdmin)) {
            $sel['team_b']['ready'] = !($sel['team_b']['ready'] ?? false);
        } else {
            jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
            return;
        }

        saveTeamSelections($selections);
        jsonResponse(['success' => true, 'team_a_ready' => $sel['team_a']['ready'], 'team_b_ready' => $sel['team_b']['ready']]);
        return;
    }

    jsonResponse(['success' => false, 'message' => 'Not found'], 404);
}

function handleRenameTeam() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $team = $input['team'] ?? '';
    $newName = trim($input['name'] ?? '');

    if (!$newName) {
        jsonResponse(['success' => false, 'message' => 'Name cannot be empty']);
        return;
    }

    $selections = getTeamSelections();
    foreach ($selections as &$sel) {
        if ($sel['id'] !== $id) continue;

        if ($team === 'team_a' && ($sel['team_a']['captain_id'] === $currentUserId || $currentUserAdmin)) {
            $sel['team_a']['name'] = $newName;
        } elseif ($team === 'team_b' && ($sel['team_b']['captain_id'] === $currentUserId || $currentUserAdmin)) {
            $sel['team_b']['name'] = $newName;
        } else {
            jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
            return;
        }

        saveTeamSelections($selections);
        syncMatchWithSelection($sel);
        jsonResponse(['success' => true, 'name' => $newName]);
        return;
    }

    jsonResponse(['success' => false, 'message' => 'Not found'], 404);
}

function handlePickPlayer() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $playerId = $input['player_id'] ?? '';

    $selections = getTeamSelections();
    foreach ($selections as &$sel) {
        if ($sel['id'] !== $id) continue;

        if (!($sel['team_a']['ready'] ?? false) || !($sel['team_b']['ready'] ?? false)) {
            jsonResponse(['success' => false, 'message' => 'Both captains must be READY before picking!']);
            return;
        }

        $turn = $sel['current_turn']; // 'team_a' or 'team_b'
        $isCapA = ($sel['team_a']['captain_id'] === $currentUserId);
        $isCapB = ($sel['team_b']['captain_id'] === $currentUserId);

        if ($turn === 'team_a' && !$isCapA && !$currentUserAdmin) {
            jsonResponse(['success' => false, 'message' => "It is Team A's turn to pick!"]);
            return;
        }
        if ($turn === 'team_b' && !$isCapB && !$currentUserAdmin) {
            jsonResponse(['success' => false, 'message' => "It is Team B's turn to pick!"]);
            return;
        }

        // Verify player is in available pool
        $foundIndex = -1;
        $pickedName = '';
        foreach ($sel['available_players'] as $idx => $p) {
            if ($p['id'] === $playerId) {
                $foundIndex = $idx;
                $pickedName = $p['name'];
                break;
            }
        }

        if ($foundIndex === -1) {
            jsonResponse(['success' => false, 'message' => 'Player is no longer available']);
            return;
        }

        // Add to team
        array_splice($sel['available_players'], $foundIndex, 1);
        $sel[$turn]['players'][] = $playerId;

        // Add to history
        $teamLabel = ($turn === 'team_a') ? $sel['team_a']['name'] : $sel['team_b']['name'];
        $sel['draft_history'][] = [
            'team' => $turn,
            'team_name' => $teamLabel,
            'player_id' => $playerId,
            'player_name' => $pickedName,
            'time' => date('H:i:s'),
        ];

        // Auto-pick last player: if exactly 1 player remains, auto-assign to the next team
        if (count($sel['available_players']) === 1) {
            $nextTurn = ($turn === 'team_a') ? 'team_b' : 'team_a';
            $lastPlayer = $sel['available_players'][0];
            $sel['available_players'] = [];
            $sel[$nextTurn]['players'][] = $lastPlayer['id'];
            $nextTeamLabel = $sel[$nextTurn]['name'];
            $sel['draft_history'][] = [
                'team' => $nextTurn,
                'team_name' => $nextTeamLabel,
                'player_id' => $lastPlayer['id'],
                'player_name' => $lastPlayer['name'],
                'time' => date('H:i:s'),
                'auto' => true,
            ];
            // Turn doesn't matter once pool is empty
            $sel['current_turn'] = $nextTurn;
        } else {
            // Alternate turn
            $sel['current_turn'] = ($turn === 'team_a') ? 'team_b' : 'team_a';
        }

        saveTeamSelections($selections);
        syncMatchWithSelection($sel);
        jsonResponse([
            'success' => true,
            'message' => "{$pickedName} picked by {$teamLabel}!",
            'remaining_count' => count($sel['available_players']),
            'next_turn' => $sel['current_turn']
        ]);
        return;
    }

    jsonResponse(['success' => false, 'message' => 'Selection session not found'], 404);
}

function handleFinalizeSelection() {
    global $currentUserId, $currentUserAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';

    $selections = getTeamSelections();
    foreach ($selections as &$sel) {
        if ($sel['id'] !== $id) continue;

        $sel['status'] = 'completed';
        saveTeamSelections($selections);

        // Sync and finalize match record
        syncMatchWithSelection($sel);

        // Retrieve the match ID for redirect / notifications
        $matches = getMatches();
        $matchId = '';
        foreach ($matches as $m) {
            if (($m['selection_id'] ?? '') === $id) {
                $matchId = $m['id'];
                break;
            }
        }

        // Notify both teams (registered users only)
        foreach (array_merge($sel['team_a']['players'], $sel['team_b']['players']) as $pid) {
            if (!str_starts_with($pid, 'temp_')) {
                createNotification($pid, 'match_set', "⚽ Match ready: {$sel['team_a']['name']} vs {$sel['team_b']['name']}! Formations are now open.", "formation?match_id={$matchId}");
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'Teams finalized! Transitioning to pitch formations.',
            'match_id' => $matchId,
        ]);
        return;
    }

    jsonResponse(['success' => false, 'message' => 'Selection not found'], 404);
}

function syncMatchWithSelection($sel) {
    $matches = getMatches();
    $found = false;
    foreach ($matches as &$m) {
        if (($m['selection_id'] ?? '') === $sel['id']) {
            $m['title'] = "{$sel['team_a']['name']} vs {$sel['team_b']['name']}";
            $m['format'] = $sel['format'];
            $m['player_names'] = array_merge($m['player_names'] ?? [], $sel['player_names'] ?? []);
            $m['team_a']['name'] = $sel['team_a']['name'];
            $m['team_a']['captain_id'] = $sel['team_a']['captain_id'];
            $m['team_a']['captain_name'] = $sel['team_a']['captain_name'] ?? getUserDisplayName($sel['team_a']['captain_id']);
            $m['team_a']['players'] = $sel['team_a']['players'] ?? [];
            $m['team_b']['name'] = $sel['team_b']['name'];
            $m['team_b']['captain_id'] = $sel['team_b']['captain_id'];
            $m['team_b']['captain_name'] = $sel['team_b']['captain_name'] ?? getUserDisplayName($sel['team_b']['captain_id']);
            $m['team_b']['players'] = $sel['team_b']['players'] ?? [];
            $m['updated_at'] = date('c');
            $found = true;
            break;
        }
    }
    unset($m);
    if (!$found) {
        $matches[] = [
            'id' => generateId(),
            'selection_id' => $sel['id'],
            'poll_id' => $sel['poll_id'] ?? null,
            'title' => "{$sel['team_a']['name']} vs {$sel['team_b']['name']}",
            'date' => date('Y-m-d'),
            'format' => $sel['format'],
            'venue' => $sel['venue'] ?? getTurfByFormat($sel['format']),
            'team_a' => [
                'name' => $sel['team_a']['name'],
                'captain_id' => $sel['team_a']['captain_id'],
                'players' => $sel['team_a']['players'] ?? [],
                'subs' => [],
                'score' => null,
            ],
            'team_b' => [
                'name' => $sel['team_b']['name'],
                'captain_id' => $sel['team_b']['captain_id'],
                'players' => $sel['team_b']['players'] ?? [],
                'subs' => [],
                'score' => null,
            ],
            'goals' => [],
            'status' => 'upcoming',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }
    saveMatches($matches);
}
