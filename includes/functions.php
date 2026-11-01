<?php
/**
 * Team-Grid Core Functions
 * JSON file operations, UUID generation, data helpers
 */

require_once __DIR__ . '/config.php';

// ============================================================
// FILE OPERATIONS (with file locking for safety)
// ============================================================

/**
 * Read JSON data from file with locking
 */
function readJsonFile(string $filePath): array {
    if (!file_exists($filePath)) return [];
    
    $fp = fopen($filePath, 'r');
    if (!$fp) return [];
    
    flock($fp, LOCK_SH);
    $content = '';
    while (!feof($fp)) {
        $content .= fread($fp, 8192);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Write JSON data to file with locking
 */
function writeJsonFile(string $filePath, array $data): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $fp = fopen($filePath, 'c');
    if (!$fp) return false;
    
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    
    return true;
}

// ============================================================
// UUID GENERATION
// ============================================================

function generateId(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ============================================================
// USER FUNCTIONS
// ============================================================

function getUsers(): array {
    return readJsonFile(USERS_FILE);
}

function saveUsers(array $users): bool {
    return writeJsonFile(USERS_FILE, $users);
}

/**
 * Normalize any date string into standard YYYY-MM-DD for database storage
 * Supports DD-MM-YYYY, DD/MM/YYYY, and YYYY-MM-DD
 */
function normalizeDob(string $dob): string {
    $dob = trim($dob);
    if (empty($dob)) return '';
    // ddmmyyyy (e.g. 19042002)
    if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $dob, $m)) {
        if ((int)$m[1] <= 31 && (int)$m[2] <= 12) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
    }
    // yyyymmdd (e.g. 20020419)
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dob, $m)) {
        if ((int)$m[2] <= 12 && (int)$m[3] <= 31) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
    }
    // dd-mm-yyyy or d-m-yyyy or dd/mm/yyyy
    if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $dob, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    // yyyy-mm-dd or yyyy/mm/dd
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $dob, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    return $dob;
}

/**
 * Format any date string into human-friendly DD-MM-YYYY
 */
function formatDobDisplay(string $dob): string {
    $clean = normalizeDob($dob);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $clean, $m)) {
        return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
    }
    return $dob;
}

function findUserByNameAndDob(string $firstName, string $dob): ?array {
    $users = getUsers();
    $firstName = strtolower(trim($firstName));
    $normDob = normalizeDob($dob);
    foreach ($users as $user) {
        $userNormDob = normalizeDob($user['dob'] ?? '');
        if (strtolower($user['first_name']) === $firstName && ($userNormDob === $normDob || ($user['dob'] ?? '') === $dob)) {
            return $user;
        }
    }
    return null;
}

function findUserById(string $id): ?array {
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['id'] === $id) return $user;
    }
    return null;
}

function getUserDisplayName(string $userId): string {
    $user = findUserById($userId);
    if ($user) {
        return $user['first_name'] . (isset($user['last_name']) && $user['last_name'] ? ' ' . $user['last_name'] : '');
    }
    if (str_starts_with($userId, 'temp_')) {
        return rawurldecode(substr($userId, 5));
    }
    if (str_starts_with($userId, '__typed__')) {
        return rawurldecode(substr($userId, 9));
    }
    return 'Player';
}

function isAdmin(?string $userId = null): bool {
    if ($userId === null) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    $user = findUserById($userId);
    return $user && isset($user['role']) && $user['role'] === 'admin';
}

function isUserReferenced(string $userId): bool {
    // Check polls
    $polls = readJsonFile(POLLS_FILE);
    foreach ($polls as $poll) {
        if (isset($poll['votes'])) {
            foreach ($poll['votes'] as $vote) {
                if ($vote['user_id'] === $userId) return true;
            }
        }
        if (isset($poll['comments'])) {
            foreach ($poll['comments'] as $comment) {
                if ($comment['user_id'] === $userId) return true;
            }
        }
    }
    
    // Check matches
    $matches = readJsonFile(MATCHES_FILE);
    foreach ($matches as $match) {
        foreach (['team_a', 'team_b'] as $team) {
            if (isset($match[$team])) {
                if (isset($match[$team]['captain_id']) && $match[$team]['captain_id'] === $userId) return true;
                if (isset($match[$team]['players']) && in_array($userId, $match[$team]['players'])) return true;
                if (isset($match[$team]['subs']) && in_array($userId, $match[$team]['subs'])) return true;
            }
        }
    }
    
    return false;
}

function registerUser(string $firstName, string $lastName, string $dob): array {
    $users = getUsers();
    
    $isFirstAdmin = (strtolower(trim($firstName)) === strtolower(DEFAULT_ADMIN_FIRST_NAME));
    
    $user = [
        'id' => generateId(),
        'first_name' => trim($firstName),
        'last_name' => trim($lastName),
        'dob' => normalizeDob($dob),
        'role' => $isFirstAdmin ? 'admin' : 'player',
        'is_active' => true,
        'created_at' => date('c'),
        'last_login' => date('c'),
    ];
    
    $users[] = $user;
    saveUsers($users);
    
    logAction('register', $user['id'], ['name' => $firstName . ' ' . $lastName]);
    
    return $user;
}

function updateUserLogin(string $userId): void {
    $users = getUsers();
    foreach ($users as &$user) {
        if ($user['id'] === $userId) {
            $user['last_login'] = date('c');
            break;
        }
    }
    saveUsers($users);
}

// ============================================================
// POLL FUNCTIONS
// ============================================================

function getPolls(): array {
    return readJsonFile(POLLS_FILE);
}

function savePolls(array $polls): bool {
    return writeJsonFile(POLLS_FILE, $polls);
}

function findPollById(string $id): ?array {
    $polls = getPolls();
    foreach ($polls as $poll) {
        if ($poll['id'] === $id) return $poll;
    }
    return null;
}

function createPoll(string $title, array $options, string $tag, ?string $deadline, ?int $maxVotes, string $createdBy, string $turf = ''): array {
    $polls = getPolls();
    
    $poll = [
        'id' => generateId(),
        'title' => trim($title),
        'turf' => trim($turf),
        'options' => $options,
        'tag' => $tag,
        'deadline' => $deadline,
        'max_votes' => $maxVotes,
        'created_by' => $createdBy,
        'created_at' => date('c'),
        'status' => 'active',
        'votes' => [],
        'views' => [],
        'comments' => [],
    ];
    
    $polls[] = $poll;
    savePolls($polls);
    
    // Notify all users
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['id'] !== $createdBy) {
            createNotification(
                $user['id'],
                'poll_created',
                '📊 New poll: "' . $title . '" — cast your vote!',
                'poll-detail?id=' . $poll['id']
            );
        }
    }
    
    logAction('poll_create', $createdBy, ['poll_id' => $poll['id'], 'title' => $title]);
    
    return $poll;
}

function votePoll(string $pollId, string $userId, int $optionIndex, int $guestCount = 0, array $guestNames = []): array {
    $polls = getPolls();
    $result = ['success' => false, 'message' => ''];
    $guestCount = max(0, min(50, $guestCount));

    // Clean guest names
    $cleanGuestNames = [];
    foreach ($guestNames as $gn) {
        $trimmed = trim((string)$gn);
        if ($trimmed !== '') {
            $cleanGuestNames[] = $trimmed;
        }
    }

    foreach ($polls as &$poll) {
        if ($poll['id'] !== $pollId) continue;

        if ($poll['status'] !== 'active') {
            $result['message'] = ($poll['status'] === 'locked') ? 'Poll is locked for team selection' : 'Poll is no longer active';
            return $result;
        }

        if ($poll['deadline'] && strtotime($poll['deadline']) < time()) {
            $poll['status'] = 'closed';
            savePolls($polls);
            $result['message'] = 'Poll deadline has passed';
            return $result;
        }

        // Calculate total headcount (each vote = 1 + guest_count)
        if ($poll['max_votes']) {
            $currentTotalHeadcount = 0;
            foreach ($poll['votes'] as $v) {
                if ($v['user_id'] !== $userId) {
                    $currentTotalHeadcount += 1 + (int)($v['guest_count'] ?? 0);
                }
            }
            if ($currentTotalHeadcount + 1 + $guestCount > $poll['max_votes']) {
                $spotsLeft = max(0, $poll['max_votes'] - $currentTotalHeadcount);
                $result['message'] = "Not enough spots left (only {$spotsLeft} spot" . ($spotsLeft === 1 ? '' : 's') . " remaining)";
                return $result;
            }
        }

        // Check if user already voted
        $isEdit = false;
        foreach ($poll['votes'] as &$vote) {
            if ($vote['user_id'] === $userId) {
                $vote['option_index'] = $optionIndex;
                $vote['guest_count'] = $guestCount;
                $vote['guest_names'] = $cleanGuestNames;
                $vote['edited_at'] = date('c');
                $isEdit = true;
                break;
            }
        }

        if (!$isEdit) {
            $poll['votes'][] = [
                'user_id' => $userId,
                'option_index' => $optionIndex,
                'guest_count' => $guestCount,
                'guest_names' => $cleanGuestNames,
                'voted_at' => date('c'),
                'edited_at' => null,
            ];
        }

        savePolls($polls);

        // Notify other voters
        $voterName = getUserDisplayName($userId);
        $guestNotice = $guestCount > 0 ? " (+{$guestCount} guest" . ($guestCount === 1 ? '' : 's') . ")" : "";
        foreach ($poll['votes'] as $v) {
            if ($v['user_id'] !== $userId) {
                $type = $isEdit ? 'vote_edited' : 'vote_cast';
                $msg = $isEdit 
                    ? "✏️ $voterName updated their vote$guestNotice on \"{$poll['title']}\""
                    : "🗳️ $voterName voted$guestNotice on \"{$poll['title']}\"";
                createNotification($v['user_id'], $type, $msg, 'poll-detail?id=' . $pollId);
            }
        }

        $logType = $isEdit ? 'vote_edit' : 'vote';
        logAction($logType, $userId, ['poll_id' => $pollId, 'option' => $optionIndex, 'guest_count' => $guestCount]);

        $result['success'] = true;
        $result['message'] = $isEdit ? 'Vote updated' : 'Vote recorded';
        return $result;
    }

    $result['message'] = 'Poll not found';
    return $result;
}

function addComment(string $pollId, string $userId, string $text): array {
    $polls = getPolls();
    $result = ['success' => false, 'message' => ''];
    
    foreach ($polls as &$poll) {
        if ($poll['id'] !== $pollId) continue;
        
        // Anti-spam check
        if (!empty($poll['comments'])) {
            $userComments = array_filter($poll['comments'], fn($c) => $c['user_id'] === $userId);
            if (!empty($userComments)) {
                $lastComment = end($userComments);
                if (time() - strtotime($lastComment['created_at']) < COMMENT_COOLDOWN) {
                    $result['message'] = 'Please wait a few seconds before commenting again';
                    return $result;
                }
            }
        }
        
        $poll['comments'][] = [
            'id' => generateId(),
            'user_id' => $userId,
            'text' => htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8'),
            'created_at' => date('c'),
        ];
        
        savePolls($polls);
        logAction('comment', $userId, ['poll_id' => $pollId]);

        // Send notifications to poll participants (voters and other commenters)
        $commenterName = getUserDisplayName($userId);
        $notifiedUsers = [$userId];

        // Notify voters
        foreach ($poll['votes'] ?? [] as $v) {
            $vid = $v['user_id'] ?? '';
            if ($vid && !in_array($vid, $notifiedUsers) && !str_starts_with($vid, 'temp_')) {
                $notifiedUsers[] = $vid;
                createNotification($vid, 'poll_comment', "💬 {$commenterName} commented on \"{$poll['title']}\"", 'poll-detail?id=' . $pollId);
            }
        }

        // Notify other commenters
        foreach ($poll['comments'] ?? [] as $c) {
            $cid = $c['user_id'] ?? '';
            if ($cid && !in_array($cid, $notifiedUsers) && !str_starts_with($cid, 'temp_')) {
                $notifiedUsers[] = $cid;
                createNotification($cid, 'poll_comment', "💬 {$commenterName} commented on \"{$poll['title']}\"", 'poll-detail?id=' . $pollId);
            }
        }

        // Notify creator if not yet notified
        if (!empty($poll['created_by']) && !in_array($poll['created_by'], $notifiedUsers) && !str_starts_with($poll['created_by'], 'temp_')) {
            $notifiedUsers[] = $poll['created_by'];
            createNotification($poll['created_by'], 'poll_comment', "💬 {$commenterName} commented on \"{$poll['title']}\"", 'poll-detail?id=' . $pollId);
        }
        
        $result['success'] = true;
        $result['message'] = 'Comment added';
        return $result;
    }
    
    $result['message'] = 'Poll not found';
    return $result;
}

function markPollViewed(string $pollId, string $userId): void {
    $polls = getPolls();
    foreach ($polls as &$poll) {
        if ($poll['id'] !== $pollId) continue;
        if (!in_array($userId, $poll['views'])) {
            $poll['views'][] = $userId;
            savePolls($polls);
        }
        break;
    }
}

function closePoll(string $pollId): bool {
    $polls = getPolls();
    foreach ($polls as &$poll) {
        if ($poll['id'] === $pollId) {
            $poll['status'] = 'closed';
            savePolls($polls);
            logAction('poll_close', $_SESSION['user_id'] ?? '', ['poll_id' => $pollId]);
            return true;
        }
    }
    return false;
}

function lockPoll(string $pollId): bool {
    $polls = getPolls();
    foreach ($polls as &$poll) {
        if ($poll['id'] === $pollId) {
            $poll['status'] = 'locked';
            savePolls($polls);
            logAction('poll_lock', $_SESSION['user_id'] ?? '', ['poll_id' => $pollId]);
            return true;
        }
    }
    return false;
}

function deletePoll(string $pollId): bool {
    $polls = getPolls();
    $found = false;
    $deletedTitle = '';
    $newPolls = [];
    foreach ($polls as $p) {
        if ($p['id'] === $pollId) {
            $found = true;
            $deletedTitle = $p['title'] ?? 'Poll';
        } else {
            $newPolls[] = $p;
        }
    }
    if (!$found) return false;

    savePolls($newPolls);

    // Also remove notifications related to this poll
    $notifs = getNotifications();
    $newNotifs = [];
    foreach ($notifs as $n) {
        if (!empty($n['link']) && str_contains($n['link'], $pollId)) {
            continue;
        }
        $newNotifs[] = $n;
    }
    saveNotifications($newNotifs);

    logAction('poll_delete', $_SESSION['user_id'] ?? '', ['poll_id' => $pollId, 'title' => $deletedTitle]);
    return true;
}

// ============================================================
// NOTIFICATION FUNCTIONS
// ============================================================

function getNotifications(): array {
    return readJsonFile(NOTIFICATIONS_FILE);
}

function saveNotifications(array $notifications): bool {
    return writeJsonFile(NOTIFICATIONS_FILE, $notifications);
}

function createNotification(string $userId, string $type, string $message, string $link = ''): void {
    $notifications = getNotifications();
    $notifications[] = [
        'id' => generateId(),
        'user_id' => $userId,
        'type' => $type,
        'message' => $message,
        'link' => $link,
        'is_read' => false,
        'created_at' => date('c'),
    ];
    saveNotifications($notifications);
}

function getUserNotifications(string $userId): array {
    $notifications = getNotifications();
    $userNotifs = array_filter($notifications, fn($n) => $n['user_id'] === $userId);
    usort($userNotifs, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    return array_values($userNotifs);
}

function getUnreadCount(string $userId): int {
    $notifications = getNotifications();
    return count(array_filter($notifications, fn($n) => $n['user_id'] === $userId && !$n['is_read']));
}

function markNotificationRead(string $notifId, string $userId): bool {
    $notifications = getNotifications();
    foreach ($notifications as &$n) {
        if ($n['id'] === $notifId && $n['user_id'] === $userId) {
            $n['is_read'] = true;
            saveNotifications($notifications);
            return true;
        }
    }
    return false;
}

function markAllNotificationsRead(string $userId): bool {
    $notifications = getNotifications();
    $changed = false;
    foreach ($notifications as &$n) {
        if ($n['user_id'] === $userId && !$n['is_read']) {
            $n['is_read'] = true;
            $changed = true;
        }
    }
    if ($changed) saveNotifications($notifications);
    return $changed;
}

// ============================================================
// MATCH FUNCTIONS
// ============================================================

function getMatches(): array {
    return readJsonFile(MATCHES_FILE);
}

function saveMatches(array $matches): bool {
    return writeJsonFile(MATCHES_FILE, $matches);
}

function findMatchById(string $id): ?array {
    $matches = getMatches();
    foreach ($matches as $match) {
        if ($match['id'] === $id) return $match;
    }
    return null;
}

function createMatch(array $data): array {
    $matches = getMatches();
    $format = $data['format'] ?? '6v6';
    $venue = !empty($data['venue']) ? $data['venue'] : getTurfByFormat($format);
    
    $match = [
        'id' => generateId(),
        'poll_id' => $data['poll_id'] ?? null,
        'title' => $data['title'] ?? '',
        'date' => $data['date'] ?? date('Y-m-d'),
        'format' => $format,
        'venue' => $venue,
        'team_a' => $data['team_a'] ?? ['name' => 'Team A', 'captain_id' => null, 'players' => [], 'subs' => [], 'score' => null],
        'team_b' => $data['team_b'] ?? ['name' => 'Team B', 'captain_id' => null, 'players' => [], 'subs' => [], 'score' => null],
        'goals' => $data['goals'] ?? [],
        'status' => $data['status'] ?? 'upcoming',
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];
    
    $matches[] = $match;
    saveMatches($matches);
    
    logAction('match_create', $_SESSION['user_id'] ?? '', ['match_id' => $match['id']]);
    
    return $match;
}

function deleteMatch(string $matchId): bool {
    $matches = getMatches();
    $found = false;
    $deletedTitle = '';
    $selectionId = null;
    $newMatches = [];
    foreach ($matches as $m) {
        if ($m['id'] === $matchId) {
            $found = true;
            $deletedTitle = $m['title'] ?? 'Match';
            $selectionId = $m['selection_id'] ?? null;
        } else {
            $newMatches[] = $m;
        }
    }
    if (!$found) return false;

    saveMatches($newMatches);

    // Also remove formations linked to this match
    $formations = getFormations();
    $newFormations = array_filter($formations, fn($f) => ($f['match_id'] ?? '') !== $matchId);
    saveFormations(array_values($newFormations));

    // Also clean up linked team selection if exists
    if ($selectionId) {
        $selections = getTeamSelections();
        $newSelections = array_filter($selections, fn($s) => ($s['id'] ?? '') !== $selectionId);
        saveTeamSelections(array_values($newSelections));
    }

    // Also remove notifications linked to this match
    $notifs = getNotifications();
    $newNotifs = [];
    foreach ($notifs as $n) {
        if (!empty($n['link']) && str_contains($n['link'], $matchId)) {
            continue;
        }
        $newNotifs[] = $n;
    }
    saveNotifications($newNotifs);

    logAction('match_delete', $_SESSION['user_id'] ?? '', ['match_id' => $matchId, 'title' => $deletedTitle]);
    return true;
}

// ============================================================
// PAYMENT FUNCTIONS
// ============================================================

function getPayments(): array {
    return readJsonFile(PAYMENTS_FILE);
}

function savePayments(array $payments): bool {
    return writeJsonFile(PAYMENTS_FILE, $payments);
}

// ============================================================
// TEAM SELECTION FUNCTIONS
// ============================================================

function getTeamSelections(): array {
    return readJsonFile(TEAM_SELECTIONS_FILE);
}

function saveTeamSelections(array $selections): bool {
    return writeJsonFile(TEAM_SELECTIONS_FILE, $selections);
}

function findTeamSelectionById(string $id): ?array {
    $selections = getTeamSelections();
    foreach ($selections as $sel) {
        if ($sel['id'] === $id) return $sel;
    }
    return null;
}

// ============================================================
// FORMATION FUNCTIONS
// ============================================================

function getFormations(): array {
    return readJsonFile(FORMATIONS_FILE);
}

function saveFormations(array $formations): bool {
    return writeJsonFile(FORMATIONS_FILE, $formations);
}

// ============================================================
// LOGGING FUNCTIONS
// ============================================================

function logAction(string $type, string $userId, array $details = []): void {
    $logs = readJsonFile(ACTION_LOG_FILE);
    $logs[] = [
        'id' => generateId(),
        'type' => $type,
        'user_id' => $userId,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('c'),
    ];
    writeJsonFile(ACTION_LOG_FILE, $logs);
}

function logLogin(string $userId, bool $success, string $firstName = ''): void {
    $logs = readJsonFile(LOGIN_LOG_FILE);
    $logs[] = [
        'id' => generateId(),
        'user_id' => $userId,
        'first_name' => $firstName,
        'success' => $success,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('c'),
    ];
    writeJsonFile(LOGIN_LOG_FILE, $logs);
}

function logError(string $message, array $context = []): void {
    $logs = readJsonFile(ERROR_LOG_FILE);
    $logs[] = [
        'id' => generateId(),
        'message' => $message,
        'context' => $context,
        'created_at' => date('c'),
    ];
    writeJsonFile(ERROR_LOG_FILE, $logs);
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return date('M j', strtotime($datetime));
}

function formatDate(string $datetime, string $format = 'M j, Y'): string {
    return date($format, strtotime($datetime));
}

function sanitizeInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/polls');
        exit;
    }
}

function getSessionUser(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return findUserById($_SESSION['user_id']);
}

/**
 * Check and auto-close expired polls
 */
function checkExpiredPolls(): void {
    $polls = getPolls();
    $changed = false;
    foreach ($polls as &$poll) {
        if ($poll['status'] === 'active' && $poll['deadline'] && strtotime($poll['deadline']) < time()) {
            $poll['status'] = 'closed';
            $changed = true;
        }
    }
    if ($changed) savePolls($polls);
}

/**
 * Get active players (logged in within last 30 days)
 */
function getActivePlayers(int $days = 30): array {
    $users = getUsers();
    $cutoff = date('c', strtotime("-$days days"));
    return array_filter($users, fn($u) => isset($u['last_login']) && $u['last_login'] >= $cutoff);
}

/**
 * Suggest format based on voter count
 */
function suggestFormat(int $voterCount): string {
    if ($voterCount >= 16) return '8v8';
    if ($voterCount >= 14) return '7v7';
    return '6v6';
}

/**
 * Get turf venue based on match format
 * 6 side match = 99 not out
 * 7 side match = valas pro
 * 8 side match = valas old
 */
function getTurfByFormat(string $format): string {
    $clean = strtolower(trim($format));
    if (str_contains($clean, '8') || str_contains($clean, '8v8') || str_contains($clean, '8-side') || str_contains($clean, '8 side')) {
        return 'valas old';
    }
    if (str_contains($clean, '7') || str_contains($clean, '7v7') || str_contains($clean, '7-side') || str_contains($clean, '7 side')) {
        return 'valas pro';
    }
    return '99 not out';
}

/**
 * Get turf venue based on player count
 * 6 side match (12 players) = 99 not out
 * 7 side match (14 players) = valas pro
 * 8 side match (16+ players) = valas old
 */
function getTurfByPlayerCount(int $count): string {
    if ($count >= 16) return 'valas old';
    if ($count >= 14) return 'valas pro';
    return '99 not out';
}

/**
 * Generate default pre-defined tactical formation positions
 * 2-1-2 for 6v6, 2-2-2 for 7v7, 3-2-1 for 8v8
 */
function getDefaultFormationPositions(string $format, array $playerIds, ?string $captainId = null): array {
    $cleanFormat = strtolower(trim($format));
    $slots = [];

    if (str_contains($cleanFormat, '7')) {
        // 7-a-side: 2-2-2 (1 GK + 2 DEF + 2 MID + 2 FWD = 7)
        $slots = [
            ['x' => 50, 'y' => 88, 'role' => 'GK'],
            ['x' => 28, 'y' => 68, 'role' => 'DEF'],
            ['x' => 72, 'y' => 68, 'role' => 'DEF'],
            ['x' => 32, 'y' => 48, 'role' => 'MID'],
            ['x' => 68, 'y' => 48, 'role' => 'MID'],
            ['x' => 30, 'y' => 24, 'role' => 'FWD'],
            ['x' => 70, 'y' => 24, 'role' => 'FWD'],
        ];
    } elseif (str_contains($cleanFormat, '8')) {
        // 8-a-side: 3-2-1 (1 GK + 3 DEF + 2 MID + 2 FWD = 8)
        $slots = [
            ['x' => 50, 'y' => 88, 'role' => 'GK'],
            ['x' => 22, 'y' => 68, 'role' => 'DEF'],
            ['x' => 50, 'y' => 70, 'role' => 'DEF'],
            ['x' => 78, 'y' => 68, 'role' => 'DEF'],
            ['x' => 34, 'y' => 48, 'role' => 'MID'],
            ['x' => 66, 'y' => 48, 'role' => 'MID'],
            ['x' => 35, 'y' => 24, 'role' => 'FWD'],
            ['x' => 65, 'y' => 24, 'role' => 'FWD'],
        ];
    } else {
        // 6-a-side: 2-1-2 (1 GK + 2 DEF + 1 MID + 2 FWD = 6)
        $slots = [
            ['x' => 50, 'y' => 88, 'role' => 'GK'],
            ['x' => 28, 'y' => 68, 'role' => 'DEF'],
            ['x' => 72, 'y' => 68, 'role' => 'DEF'],
            ['x' => 50, 'y' => 48, 'role' => 'MID'],
            ['x' => 30, 'y' => 24, 'role' => 'FWD'],
            ['x' => 70, 'y' => 24, 'role' => 'FWD'],
        ];
    }

    if (empty($playerIds)) return [];

    $uniquePlayerIds = array_values(array_unique($playerIds));
    $arranged = [];

    // Prioritize captain on the pitch
    if ($captainId && in_array($captainId, $uniquePlayerIds)) {
        $arranged[] = $captainId;
        $uniquePlayerIds = array_values(array_diff($uniquePlayerIds, [$captainId]));
    }

    // Append remaining players
    foreach ($uniquePlayerIds as $pid) {
        $arranged[] = $pid;
    }

    $positions = [];
    $maxSlots = count($slots);
    foreach ($arranged as $idx => $pid) {
        if ($idx >= $maxSlots) break; // Remaining are subs
        $slot = $slots[$idx];
        $positions[] = [
            'player_id' => $pid,
            'x' => $slot['x'],
            'y' => $slot['y'],
            'role' => $slot['role'],
        ];
    }

    return $positions;
}
