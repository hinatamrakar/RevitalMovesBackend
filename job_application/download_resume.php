<?php

require_once "../db.php";
require_once "../server.php";
require_once "../includes/auth.php";

startSecureSession();

// Only accept POST
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

// Fetch resume filename from db
$stmt = $pdo->prepare("
    SELECT resume_file, name
    FROM job_applications
    WHERE id = ?
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

// Security: strip any path traversal from stored filename
$safeFileName = basename($application['resume_file']);
$filePath = __DIR__ . '/../uploads/resumes/' . $safeFileName;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Resume file not found on server"
    ]);
    exit();
}

// Determine MIME type
$ext = strtolower(pathinfo($safeFileName, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

// Build a clean download name: "Resume - Applicant Name.ext"
$cleanName = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $application['name']);
$downloadName = 'Resume - ' . $cleanName . '.' . $ext;

// Stream the file as a download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
 
// Disable output buffering and flush
while (ob_get_level()) {
    ob_end_clean();
}
 
readfile($filePath);
exit();
