<?php
/**
 * Header Include
 * Common HTML head section for all pages
 */
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/config.php';
}
require_once INCLUDES_PATH . 'functions.php';

// Check expired polls on every page load
checkExpiredPolls();

$currentUser = getSessionUser();
$isLoggedIn = $currentUser !== null;
$isAdminUser = $isLoggedIn && isAdmin();
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
$unreadCount = $isLoggedIn ? getUnreadCount($currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Team-Grid — Football Team Management App">
    <meta name="theme-color" content="#0a0e17">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' — ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/lib/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/css/style.css">
    <?php if (isset($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
