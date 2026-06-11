<?php

require_once '../db.php';
require_once '../server.php';
require_once '../includes/auth.php';

startSecureSession();

// may need to update the responsibility update portion

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
$title = trim($data['title'] ?? '');
$jobType = trim($data['job_type'] ?? '');
$description = trim($data['description'] ?? '');
$responsibilities = $data['responsibilities'] ?? [];
$status = $data['status'] ?? 'active';

// Input validation
$errors = [];

if ($id <= 0) $errors[] = 'A valid job ID is required';
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

// Check job exists
$stmtCheck = $pdo->prepare("SELECT id FROM jobs WHERE id = ? LIMIT 1");
$stmtCheck->execute([$id]);

if (!$stmtCheck->fetch()) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

// Update job and replace responsibilities
try {
    $pdo->beginTransaction();

    $stmtJob = $pdo->prepare("
        UPDATE jobs
        SET title = ?, job_type = ?, description = ?, status = ?
        WHERE id = ?
    ");
    $stmtJob->execute([$title, $jobType, $description, $status, $id]);

    // Update responsibilities
    $stmtExisting = $pdo->prepare("
        SELECT responsibility
        FROM job_responsibilities
        WHERE job_id = ?
        ORDER BY id
    ");
    $stmtExisting->execute([$id]);
    $existingResponsibilities = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);

    // Normalize values
    $existingResponsibilities = array_map('trim', $existingResponsibilities);
    $newResponsibilities = array_map('trim', $responsibilities);

    if ($existingResponsibilities !== $newResponsibilities) {
        // Delete old 
        $stmtDel = $pdo->prepare("
            DELETE FROM job_responsibilities 
            WHERE job_id = ?
        ");
        $stmtDel->execute([$id]);

        // Insert new
        $stmtResp = $pdo->prepare("
            INSERT INTO job_responsibilities (job_id, responsibility)
            VALUES (?, ?)
        ");
        foreach ($responsibilities as $resp) {
            $stmtResp->execute([$id, trim($resp)]);
        }
    }


    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to update job. Please try again."
    ]);
    exit();
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Job updated successfully"
]);