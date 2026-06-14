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

// Fetch all active jobs with their responsibilities
$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.title,
        j.job_type,
        j.description,
        j.status,
        j.created_at,
        j.updated_at
    FROM jobs j
    WHERE j.status = 'active' AND deleted_at IS NULL
    ORDER BY j.created_at DESC
");
$stmt->execute();
$jobs = $stmt->fetchAll();

if (!$jobs) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "No active jobs found",
        "jobs" => []
    ]);
    exit();
}

// Fetch responsibilities for all active jobs
$jobIds = array_column($jobs, 'id');
$placeholders = implode(',', array_fill(0, count($jobIds), '?'));

$stmtResp = $pdo->prepare("
    SELECT job_id, id AS responsibility_id, responsibility
    FROM job_responsibilities
    WHERE job_id IN ($placeholders) AND deleted_at IS NULL
    ORDER BY id ASC
");
$stmtResp->execute($jobIds);
$responsibilities = $stmtResp->fetchAll();

// Group responsibilities by job_id
$respByJob = [];
foreach ($responsibilities as $resp) {
    $respByJob[$resp['job_id']][] = [
        "id"             => $resp['responsibility_id'],
        "responsibility" => $resp['responsibility']
    ];
}

// Attach responsibilities to each job
foreach ($jobs as &$job) {
    $job['responsibilities'] = $respByJob[$job['id']] ?? [];
}
unset($job);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Active jobs retrieved successfully",
    "jobs"    => $jobs
]);