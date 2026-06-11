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

// Validate job ID from query string
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid job ID is required"
    ]);
    exit();
}

// Fetch the job
$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        job_type,
        description,
        status,
        created_at,
        updated_at
    FROM jobs
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

// Fetch responsibilities for this job
$stmtResp = $pdo->prepare("
    SELECT id AS responsibility_id, responsibility
    FROM job_responsibilities
    WHERE job_id = ?
    ORDER BY id ASC
");
$stmtResp->execute([$id]);
$responsibilities = $stmtResp->fetchAll();

$job['responsibilities'] = array_map(function ($r) {
    return [
        "id" => $r['responsibility_id'],
        "responsibility" => $r['responsibility']
    ];
}, $responsibilities);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Job retrieved successfully",
    "job" => $job
]);