<?php

require_once '../db.php';
require_once '../server.php';
require_once '../includes/auth.php';

// resume and cover letter files fetch??

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

// Filters via query string
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

$allowedStatuses = ['new', 'reviewed', 'shortlisted', 'rejected', 'hired'];

$where = [];
$params = [];

if (!empty($status) && in_array($status, $allowedStatuses)) {
    $where[]  = 'ja.status = :status';
    $params[':status'] = $status;
}

if (!empty($search)) {
    $where[]  = '(ja.name LIKE :search OR ja.email LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) FROM job_applications ja
    $whereClause
");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

// Fetch paginated results
$params[':limit'] = $limit;
$params[':offset'] = $offset;

$stmt = $pdo->prepare("
    SELECT
        ja.id,
        ja.job_id,
        j.title        AS job_title,
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
    "data" => $applications,
    "pagination" => [
        "total" => $total,
        "page" => $page,
        "limit" => $limit,
        "total_pages" => (int)ceil($total / $limit),
    ]
]);