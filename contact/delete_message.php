<?php

require_once "../db.php";
require_once "../server.php";
require_once "../includes/auth.php";

startSecureSession();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Parse JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "No input received"
    ]);
    exit();
}

$id = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid message ID is required"
    ]);
    exit();
}

// Fetch message
$stmtCheck = $pdo->prepare("
    SELECT id
    FROM contact_messages
    WHERE id = ? AND deleted_at IS NULL
    LIMIT 1
");
$stmtCheck->execute([$id]);
$message = $stmtCheck->fetch();

if (!$message) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Message not found"
    ]);
    exit();
}

// Delete the DB record
$stmtDel = $pdo->prepare("
    UPDATE contact_messages
    SET deleted_at = NOW() 
    WHERE id = ?
");
$stmtDel->execute([$id]);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Message deleted successfully"
]);