<?php
/**
 * Notifications API
 * List, mark read, mark all read
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $notifications = getUserNotifications($userId);
        $unread = getUnreadCount($userId);
        jsonResponse(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
        break;

    case 'count':
        jsonResponse(['success' => true, 'unread_count' => getUnreadCount($userId)]);
        break;

    case 'mark_read':
        $id = $_GET['id'] ?? '';
        if ($id) {
            markNotificationRead($id, $userId);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Missing notification ID']);
        }
        break;

    case 'mark_all_read':
        markAllNotificationsRead($userId);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
