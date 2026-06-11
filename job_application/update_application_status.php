<?php

require_once '../db.php';
require_once '../server.php';
require_once '../includes/auth.php';

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
$status = isset($data['status']) ? trim($data['status']) : '';

$allowedStatuses = ['new', 'reviewed', 'shortlisted', 'rejected', 'hired'];

// Input validation
$errors = [];
if ($id <= 0) $errors[] = 'A valid application ID is required';
if (!in_array($status, $allowedStatuses)) $errors[] = 'Status must be one of: ' . implode(', ', $allowedStatuses);

if ($errors) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => implode(' ', $errors)
    ]);
    exit();
}

// Check application exists
$stmtCheck = $pdo->prepare("
    SELECT id, status 
    FROM job_applications 
    WHERE id = ? 
    LIMIT 1
");
$stmtCheck->execute([$id]);
$application = $stmtCheck->fetch();

if (!$application) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Application not found"
    ]);
    exit();
}

// No-op if already in desired state
if ($application['status'] === $status) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Application is already set to " . $status
    ]);
    exit();
}

// Update status
$stmtUpdate = $pdo->prepare("
    UPDATE job_applications 
    SET status = ? 
    WHERE id = ?
");
$stmtUpdate->execute([$status, $id]);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Application status updated successfully",
    "application" => [
        "id" => $id,
        "status" => $status
    ]
]);