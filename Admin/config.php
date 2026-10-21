<?php

// Set timezone to Pakistan
date_default_timezone_set('Asia/Karachi');

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k) putenv("$k=$v");
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Only load production env on actual production server, not on localhost/XAMPP
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$isProduction = !in_array($host, ['localhost', '127.0.0.1', '::1'])
    && !str_starts_with($host, '192.168.')
    && !str_ends_with($host, '.local');

if ($isProduction) {
    loadEnv(__DIR__ . '/.env.production');
}

define('DB_HOST',         getenv('DB_HOST')         ?: 'localhost');
define('DB_USER',         getenv('DB_USER')         ?: 'root');
define('DB_PASS',         getenv('DB_PASS')         ?: '');
define('DB_NAME',         getenv('DB_NAME')         ?: 'snackzone');
define('FRONTEND_ORIGIN', getenv('FRONTEND_ORIGIN') ?: '*');

if (!function_exists('setCORS')) {
    function setCORS(): void {
        // Security: Only allow specific origins in production
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Get allowed origins from environment or use defaults
        $allowedOriginsStr = getenv('ALLOWED_ORIGINS') ?: '';
        $allowedOrigins = $allowedOriginsStr ? explode(',', $allowedOriginsStr) : [];
        
        // In development (localhost), allow any origin
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $isDevelopment = in_array($host, ['localhost', '127.0.0.1', '::1'])
            || str_starts_with($host, '192.168.')
            || str_ends_with($host, '.local');
        
        if ($isDevelopment) {
            // Development: Allow any origin for testing
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        } else {
            // Production: Strict origin checking
            if ($origin && in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
            } else {
                // Default: Allow same origin only
                header('Access-Control-Allow-Origin: https://' . $host);
            }
        }
        
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Credentials: true');
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

if (!function_exists('getDB')) {
    function getDB(): mysqli {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'DB connection failed']));
        }
        $conn->set_charset('utf8mb4');
        $db = DB_NAME;
        $conn->query("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$conn->select_db($db)) {
            die(json_encode(['success' => false, 'message' => 'Could not select database']));
        }
        return $conn;
    }
}
