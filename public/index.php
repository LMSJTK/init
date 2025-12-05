<?php
/**
 * Startup Game - Main Entry Point
 */

// Allow the script to run for 5 minutes (300 seconds) to handle AI delays
set_time_limit(300);

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'StartupGame\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    // If no config, check if we need to show setup
    $configExample = __DIR__ . '/../config/config.example.php';
    if (file_exists($configExample)) {
        copy($configExample, $configFile);
    }
}

$config = require $configFile;

// Set timezone
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

// Initialize database
\StartupGame\Database::init($config['database']);

// Handle API requests
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$path = $basePath !== '/' ? str_replace($basePath, '', $requestUri) : $requestUri;

// Handle static files
if ($path === '/' || $path === '') {
    require __DIR__ . '/game.html';
    exit;
}

// API routes
if (str_starts_with($path, '/api/')) {
    header('Content-Type: application/json');

    $method = $_SERVER['REQUEST_METHOD'];
    $data = [];

    if ($method === 'GET') {
        $data = $_GET;
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];
    }

    $controller = new \StartupGame\Controllers\ApiController($config);
    $response = $controller->handle($method, $path, $data);

	$status = $response['status'] ?? 200;
    
    // Only treat 'status' as an HTTP code if it is an integer
    if (is_int($status)) {
        unset($response['status']);
    } else {
        $status = 200;
    }

    http_response_code($status);
    echo json_encode($response);
    exit;
}

// Database migration endpoint
if ($path === '/migrate') {
    header('Content-Type: application/json');

    try {
        $results = \StartupGame\Database::migrate(__DIR__ . '/../migrations');
        echo json_encode(['success' => true, 'results' => $results]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 404 for unknown routes
http_response_code(404);
echo json_encode(['error' => 'Not found']);
