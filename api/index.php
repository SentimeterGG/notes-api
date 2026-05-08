<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../routes/auth.php';
require_once __DIR__ . '/../routes/posts.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip base path if hosted in a subdirectory, e.g. /api
// Normalize: remove trailing slash
$uri = str_replace('/FlutterAPI', '', $uri);

// Route matching
// /api/register
if ($uri === '/api/register' && $method === 'POST') {
    handle_register();
} elseif ($uri === '/api/login' && $method === 'POST') {
    handle_login();
} elseif ($uri === '/api/user' && $method === 'GET') {
    handle_get_user();
} elseif ($uri === '/api/logout' && $method === 'POST') {
    handle_logout();
} elseif ($uri === '/api/posts' && $method === 'GET') {
    handle_get_posts();
} elseif ($uri === '/api/posts' && $method === 'POST') {
    handle_create_post();
} elseif (preg_match('#^/api/posts/(\d+)$#', $uri, $matches)) {
    $id = (int)$matches[1];
    match ($method) {
        'GET'    => handle_get_post($id),
        'PUT'    => handle_update_post($id),
        'DELETE' => handle_delete_post($id),
        default  => json_response(['message' => 'Method not allowed'], 405),
    };
} else {
    http_response_code(404);
    echo json_encode(['message' => 'Endpoint not found']);
}
