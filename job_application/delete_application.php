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
        "message" => "A valid application ID is required"
    ]);
    exit();
}

// Fetch application
$stmtCheck = $pdo->prepare("
    SELECT id, resume_file, cover_letter
    FROM job_applications
    WHERE id = ? AND deleted_at IS NULL
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

// Delete the DB record
$stmtDel = $pdo->prepare("
    UPDATE job_applications
    SET deleted_at = NOW() 
    WHERE id = ?
");
$stmtDel->execute([$id]);

// // Delete resume file from disk
// $resumePath = __DIR__ . '/../uploads/resumes/' . $application['resume_file'];
// if (!empty($application['resume_file']) && file_exists($resumePath)) {
//     unlink($resumePath);
// }

// // Delete cover letter if it exists from disk
// $coverLetterPath = __DIR__ . '/../uploads/cover_letters/' . $application['cover_letter'];
// if (!empty($application['cover_letter']) && file_exists($coverLetterPath)) {
//     unlink($coverLetterPath);
// }

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Application deleted successfully"
]);