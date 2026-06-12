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
        "message" => "A valid consultation ID is required"
    ]);
    exit();
}

// Check consultation exists
$stmtCheck = $pdo->prepare("
    SELECT id, deleted_at
    FROM consultations
    WHERE id = ?
    LIMIT 1
");
$stmtCheck->execute([$id]);
$consultation = $stmtCheck->fetch();

if (!$consultation) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Consultation not found"
    ]);
    exit();
}

if ($consultation['deleted_at'] !== NULL) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Consultation is already deleted"
    ]);
    exit();
}

// Soft delete
$stmtDel = $pdo->prepare("
    UPDATE consultations
    SET deleted_at = NOW()
    WHERE id = ?"
);
$stmtDel->execute([$id]);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Consultation deleted successfully"
]);