<?php
/**
 * Built-in PHP Development Server Router
 * Usage: php -S localhost:8000 router.php
 * Handles extensionless routing like /login, /polls, /admin/payments, etc.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = preg_replace('#/+#', '/', str_replace('\\', '/', $uri));
$filePath = __DIR__ . $uri;

// 1. Serve static files directly if they exist on disk (css, js, images, etc.)
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
    return false;
}

// 2. Route /login to index.php
if ($uri === '/login' || $uri === '/login/') {
    require __DIR__ . '/index.php';
    exit;
}

// 3. Route /admin to admin/index.php
if ($uri === '/admin' || $uri === '/admin/') {
    require __DIR__ . '/admin/index.php';
    exit;
}

// 4. Check for direct .php file match (e.g. /polls -> /polls.php or /admin/payments -> /admin/payments.php)
if (file_exists(__DIR__ . $uri . '.php')) {
    require __DIR__ . $uri . '.php';
    exit;
}

// 5. Check if uri is an existing PHP file directly
if (file_exists($filePath) && !is_dir($filePath) && substr($filePath, -4) === '.php') {
    require $filePath;
    exit;
}

// 6. Default root index
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// 7. Return 404 if not found
http_response_code(404);
echo "404 Not Found";
exit;
