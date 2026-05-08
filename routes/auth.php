<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

function handle_register(): void
{
    $db = getDB();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        json_response(['message' => 'name, email, and password are required'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['message' => 'Invalid email format'], 422);
    }
    // Check duplicate
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        json_response(['message' => 'Email already registered'], 409);
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $name, $email, $hash);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;

        $token = generate_token();

        $update = $db->prepare("UPDATE users SET token = ? WHERE id = ?");
        $update->bind_param('si', $token, $userId);
        $update->execute();
        $update->close();

        json_response([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'data' => [
                'id'    => $userId,
                'name'  => $name,
                'email' => $email,
            ]
        ], 200);
    } else {
        json_response(['message' => 'Registration failed'], 500);
    }
    $stmt->close();
}

function handle_login(): void
{
    $db = getDB();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        json_response(['message' => 'email and password are required'], 422);
    }

    $stmt = $db->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        json_response(['message' => 'Invalid credentials'], 401);
    }

    $token = generate_token();
    $stmt  = $db->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param('si', $token, $user['id']);
    $stmt->execute();
    $stmt->close();

    json_response([
        'message'      => 'Login successful',
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'data'         => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ],
    ], 200);
}

function handle_get_user(): void
{
    $db   = getDB();
    $user = authenticate($db);

    // Re-fetch token to return it
    $token = get_bearer_token();

    json_response([
        'id'           => $user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'status'       => 200,
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'message'      => "Success"
    ]);
}

function handle_logout(): void
{
    $db   = getDB();
    $user = authenticate($db);

    $stmt = $db->prepare("UPDATE users SET token = NULL WHERE id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->close();

    json_response(['message' => 'Logged out successfully']);
}
