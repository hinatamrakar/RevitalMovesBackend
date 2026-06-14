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

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid job ID is required"
    ]);
    exit();
}

// Check job exists
$stmtCheck = $pdo->prepare("
    SELECT id 
    FROM jobs 
    WHERE id = ? AND deleted_at IS NULL
    LIMIT 1");
$stmtCheck->execute([$id]);

if (!$stmtCheck->fetch()) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

// Soft delete job and its responsibilities
try {
    $pdo->beginTransaction();

    // Delete responsibilities first
    $stmtResp = $pdo->prepare("
        UPDATE job_responsibilities
        SET deleted_at = NOW()
        WHERE job_id = ?");
    $stmtResp->execute([$id]);

    $stmtJob = $pdo->prepare("
        UPDATE jobs
        SET deleted_at = NOW()
        WHERE id = ?");
    $stmtJob->execute([$id]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to delete job. Please try again."
    ]);
    exit();
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Job deleted successfully"
]);