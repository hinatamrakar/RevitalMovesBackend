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
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
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

$title = trim($data['title'] ?? '');
$jobType = trim($data['job_type'] ?? '');
$description = trim($data['description'] ?? '');
$responsibilities = $data['responsibilities'] ?? [];
$status = $data['status'] ?? 'active';

 
// Input validation
$errors = [];

if (empty($title)) $errors[] = 'Title is required';
if (empty($jobType)) $errors[] = 'Job type is required';
if (empty($description)) $errors[] = 'Description is required';

if (!in_array($status, ['active', 'inactive'])) {
    $errors[] = 'Status must be active or inactive';
}

if (!is_array($responsibilities) || empty($responsibilities)) {
    $errors[] = 'At least one responsibility is required';
} else {
    foreach ($responsibilities as $index => $resp) {
        if (empty(trim($resp))) {
            $errors[] = "Responsibility at position " . ($index + 1) . " cannot be empty";
        }
    }
}

 
if ($errors) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => implode(' ', $errors)
    ]);
    exit();
}

 
// Insert
try {
    $pdo->beginTransaction();
 
    $stmtJob = $pdo->prepare("
        INSERT INTO jobs (title, job_type, description, status)
        VALUES (?, ?, ?, ?)
    ");
    $stmtJob->execute([$title, $jobType, $description, $status]);
    $jobId = $pdo->lastInsertId();
 
    $stmtResp = $pdo->prepare("
        INSERT INTO job_responsibilities (job_id, responsibility)
        VALUES (?, ?)
    ");
    foreach ($responsibilities as $resp) {
        $stmtResp->execute([$jobId, trim($resp)]);
    }
 
    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to add job. Please try again."
    ]);
    exit();
}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Job added successfully",
    "job_id"  => (int) $jobId
]);
