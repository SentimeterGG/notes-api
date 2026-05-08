<?php

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function get_bearer_token(): ?string
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function authenticate(mysqli $db): array
{
    $token = get_bearer_token();
    if (!$token) {
        json_response(['message' => 'Unauthorized: No token provided'], 401);
    }

    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        json_response(['message' => 'Unauthorized: Invalid or expired token'], 401);
    }

    return $user;
}

function generate_token(): string
{
    return bin2hex(random_bytes(32));
}
