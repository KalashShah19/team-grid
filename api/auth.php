<?php
/**
 * Auth API
 * Handles login, register, logout
 */
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    $firstName = trim($input['first_name'] ?? '');
    $dob = trim($input['dob'] ?? '');

    if (!$firstName || !$dob) {
        jsonResponse(['success' => false, 'message' => 'First name and DOB are required']);
        return;
    }

    $user = findUserByNameAndDob($firstName, $dob);
    if (!$user) {
        logLogin('', false, $firstName);
        jsonResponse(['success' => false, 'message' => 'No account found. Please register first.']);
        return;
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'];

    // Update last login
    updateUserLogin($user['id']);
    logLogin($user['id'], true, $firstName);

    $redirect = ($user['role'] === 'admin') ? 'admin' : 'polls';

    jsonResponse([
        'success' => true,
        'message' => 'Welcome back, ' . $user['first_name'] . '!',
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'role' => $user['role'],
        ],
        'redirect' => $redirect,
    ]);
}

function handleRegister() {
    $input = json_decode(file_get_contents('php://input'), true);
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $dob = trim($input['dob'] ?? '');

    if (!$firstName || !$dob) {
        jsonResponse(['success' => false, 'message' => 'First name and date of birth are required']);
        return;
    }

    // Check if already exists
    $existing = findUserByNameAndDob($firstName, $dob);
    if ($existing) {
        jsonResponse(['success' => false, 'message' => 'Account already exists. Please login instead.']);
        return;
    }

    $user = registerUser($firstName, $lastName, $dob);

    // Auto-login after register
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'];

    $redirect = ($user['role'] === 'admin') ? 'admin' : 'polls';

    jsonResponse([
        'success' => true,
        'message' => 'Account created! Welcome, ' . $user['first_name'] . '!',
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'role' => $user['role'],
        ],
        'redirect' => $redirect,
    ]);
}

function handleLogout() {
    if (isset($_SESSION['user_id'])) {
        logAction('logout', $_SESSION['user_id']);
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/login');
    exit;
}
