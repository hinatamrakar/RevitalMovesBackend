<?php

require_once '../db.php';
require_once '../server.php';
require_once '../includes/auth.php';

startSecureSession();

// Only accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit();
}

// Auth check
if (empty($_SESSION['logged_in']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit();
}

// Validate message ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid message ID is required"
    ]);
    exit();
}

// Fetch the message
$stmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        last_name,
        email,
        phone,
        service,
        message,
        created_at
    FROM contact_messages
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Message not found"
    ]);
    exit();
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Message retrieved successfully",
    "message" => $message
]);
