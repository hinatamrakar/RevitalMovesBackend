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

// Validate job ID
$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

if ($jobId <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid job_id is required"
    ]);
    exit();
}

// Verify job exists
$stmtJob = $pdo->prepare("
    SELECT id, title
    FROM jobs
    WHERE id = ? AND deleted_at IS NULL
    LIMIT 1
");
$stmtJob->execute([$jobId]);
$job = $stmtJob->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

// Status filter
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

$allowedStatuses = ['new', 'reviewed', 'shortlisted', 'rejected', 'hired'];

$where = ['ja.job_id = :job_id', 'ja.deleted_at IS NULL'];
$params = [':job_id' => (int)$jobId];

if (!empty($status) && in_array($status, $allowedStatuses)) {
    $where[] = 'ja.status = :status';
    $params[':status'] = $status;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Total count
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM job_applications ja
    $whereClause
");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

// Fetch status summary for this job
$stmtSummary = $pdo->prepare("
    SELECT status, COUNT(*) AS count
    FROM job_applications
    WHERE job_id = ? AND deleted_at IS NULL
    GROUP BY status
");
$stmtSummary->execute([$jobId]);
$summaryRows = $stmtSummary->fetchAll();
 
$summary = array_fill_keys($allowedStatuses, 0);
foreach ($summaryRows as $row) {
    $summary[$row['status']] = (int) $row['count'];
}

// Fetch paginated applications
$params[':limit'] = $limit;
$params[':offset'] = $offset;

$stmt = $pdo->prepare("
    SELECT
        ja.id,
        ja.job_id,
        ja.name,
        ja.email,
        ja.phone,
        ja.resume_file,
        ja.cover_letter,
        ja.status,
        ja.created_at,
        ja.updated_at
    FROM job_applications ja
    $whereClause
    ORDER BY ja.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Applications retrieved successfully",
    "job" => $job,
    "summary" => $summary,
    "data" => $applications,
    "pagination" => [
        "total" => $total,
        "page" => $page,
        "limit" => $limit,
        "total_pages" => (int)ceil($total / $limit),
    ]
]);