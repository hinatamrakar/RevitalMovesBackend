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

// Validate consultation ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid consultation ID is required"
    ]);
    exit();
}

// Fetch the message
$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        company_name,
        consultation_type,
        preferred_date,
        preferred_time,
        message,
        status,
        created_at,
        updated_at
    FROM consultations
    WHERE id = ? AND deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$id]);
$consultation = $stmt->fetch();

if (!$consultation) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Consultation not found"
    ]);
    exit();
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Consultation retrieved successfully",
    "message" => $consultation
]);
