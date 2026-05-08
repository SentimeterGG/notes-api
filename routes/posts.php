<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

function handle_get_posts(): void
{
    $db   = getDB();
    $user = authenticate($db);

    $stmt_notes = $db->prepare("SELECT id, title, content, slug, status FROM posts WHERE user_id = ? ORDER BY id DESC");
    $stmt_notes->bind_param('i', $user['id']);
    $stmt_notes->execute();
    $result = $stmt_notes->get_result();
    $posts  = [];
    while ($row = $result->fetch_assoc()) {
        $row['id']     = (int)$row['id'];
        $row['status'] = (int)$row['status'];
        $posts[]       = $row;
    }
    if (empty($posts)) {
        json_response(['message' => 'No posts found'], 204);
    }
    json_response(['data' => $posts], 200);
}

function handle_get_post(int $id): void
{
    $db   = getDB();
    $user = authenticate($db);

    $stmt = $db->prepare("SELECT id, title, content, slug, status FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $post   = $result->fetch_assoc();
    $stmt->close();

    if (!$post) {
        json_response(['message' => 'Post not found'], 404);
    }

    $post['id']     = (int)$post['id'];
    $post['status'] = (int)$post['status'];
    json_response(['message' => "Success",'data' => $post]);
}

function handle_create_post(): void
{
    $db   = getDB();
    $user = authenticate($db);

    parse_str(file_get_contents('php://input'), $body);
    $title   = trim($body['title'] ?? $_POST['title'] ?? '');
    $content = trim($body['content'] ?? $_POST['content'] ?? '');
    $status  = isset($body['status']) ? (int)$body['status'] : (isset($_POST['status']) ? (int)$_POST['status'] : 1);

    if (!$title || !$content) {
        json_response(['message' => 'title and content are required'], 422);
    }

    $slug = slug($title);

    // Ensure unique slug
    $base_slug = $slug;
    $i         = 1;
    while (true) {
        $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ?");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            break;
        }
        $slug = $base_slug . '-' . $i++;
    }

    $stmt = $db->prepare("INSERT INTO posts (user_id, title, content, slug, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('isssi', $user['id'], $title, $content, $slug, $status);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();
        handle_get_post($new_id); // Return the created post
    } else {
        json_response(['message' => 'Failed to create post'], 500);
    }
}

function handle_update_post(int $id): void
{
    $db   = getDB();
    $user = authenticate($db);

    // Support both PUT body and form data
    parse_str(file_get_contents('php://input'), $body);
    $title   = trim($body['title'] ?? $_POST['title'] ?? '');
    $content = trim($body['content'] ?? $_POST['content'] ?? '');
    $status  = isset($body['status']) ? (int)$body['status'] : (isset($_POST['status']) ? (int)$_POST['status'] : null);

    // Check post exists
    $stmt = $db->prepare("SELECT id, title, content, slug, status FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $post   = $result->fetch_assoc();
    $stmt->close();

    if (!$post) {
        json_response(['message' => 'Post not found'], 404);
    }

    // Use existing values as fallback
    $new_title   = $title ?: $post['title'];
    $new_content = $content ?: $post['content'];
    $new_status  = $status ?? (int)$post['status'];
    $new_slug    = ($title && $title !== $post['title']) ? slug($title) : $post['slug'];

    $stmt = $db->prepare("UPDATE posts SET title = ?, content = ?, slug = ?, status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('sssiii', $new_title, $new_content, $new_slug, $new_status, $id, $user['id']);

    if ($stmt->execute()) {
        $stmt->close();
        handle_get_post($id);
    } else {
        json_response(['message' => 'Failed to update post'], 500);
    }
}

function handle_delete_post(int $id): void
{
    $db   = getDB();
    $user = authenticate($db);

    $stmt = $db->prepare("SELECT id FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        json_response(['message' => 'Post not found'], 404);
    }
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $stmt->close();
        json_response(['message' => 'Post deleted successfully']);
    } else {
        $stmt->close();
        json_response(['message' => 'Failed to delete post'], 500);
    }
}
