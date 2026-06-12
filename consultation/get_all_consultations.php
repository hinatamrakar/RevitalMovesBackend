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

// Fetch results
$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        company_name,
        consultation_type,
        preferred_date,
        preferred_time,
        message,
        status,
        created_at,
        updated_at
    FROM consultations
    WHERE deleted_at IS NULL
    ORDER BY created_at DESC
");
$stmt->execute();
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Consultations retrieved successfully",
    "data" => $consultations
]);