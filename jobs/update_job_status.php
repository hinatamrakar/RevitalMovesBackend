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
$status = $data['status'] ?? '';

// Input validation
$errors = [];

if ($id <= 0) $errors[] = 'A valid job ID is required';

if (!in_array($status, ['active', 'inactive'])) {
    $errors[] = 'Status must be active or inactive';
}

if ($errors) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => implode(' ', $errors)
    ]);
    exit();
}

// Check job exists
$stmtCheck = $pdo->prepare("SELECT id, status FROM jobs WHERE id = ? LIMIT 1");
$stmtCheck->execute([$id]);
$job = $stmtCheck->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

// No-op if already in desired state
if ($job['status'] === $status) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Job is already " . $status
    ]);
    exit();
}

// Update status
$stmtUpdate = $pdo->prepare("UPDATE jobs SET status = ? WHERE id = ?");
$stmtUpdate->execute([$status, $id]);

$label = $status === 'active' ? 'activated' : 'deactivated';

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Job {$label} successfully",
    "job"     => [
        "id"     => $id,
        "status" => $status
    ]
]);