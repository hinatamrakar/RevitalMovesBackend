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

// Validate application ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid application ID is required"
    ]);
    exit();
}

// Fetch the application with job info
$stmt = $pdo->prepare("
    SELECT
        ja.id,
        ja.job_id,
        j.title AS job_title,
        j.job_type,
        ja.name,
        ja.email,
        ja.phone,
        ja.resume_file,
        ja.cover_letter,
        ja.status,
        ja.created_at,
        ja.updated_at
    FROM job_applications ja
    LEFT JOIN jobs j ON j.id = ja.job_id
    WHERE ja.id = ? AND ja.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$id]);
$application = $stmt->fetch();

if (!$application) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Application not found"
    ]);
    exit();
}

http_response_code(200);
echo json_encode([
    "success"     => true,
    "message"     => "Application retrieved successfully",
    "application" => $application
]);