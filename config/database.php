<?php

define('DB_HOST', 'mysql-197427f9-todoflutterapi.k.aivencloud.com');
define('DB_USER', 'avnadmin');
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', 'defaultdb');
define('DB_PORT', 15230);

function getDB(): mysqli
{
    $conn = mysqli_init();
    mysqli_ssl_set($conn, null, null, null, null, null);
    mysqli_real_connect(
        $conn,
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        DB_PORT,
        null,
        MYSQLI_CLIENT_SSL
    );
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
