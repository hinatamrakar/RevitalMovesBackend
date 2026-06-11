<?php

require_once '../db.php';
require_once '../server.php';
require_once '../includes/auth.php';

startSecureSession();

// Logged in check
if (empty($_SESSION['logged_in']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false, 
        "message" => "Not authenticated."
    ]);
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit();
}
 
// Parse JSON body
$data = json_decode(file_get_contents('php://input'), true);
 
if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "No input received."
    ]);
    exit();
}
 
$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';
 
// Validation
$errors = [];
 
if ($currentPassword === '') $errors[] = "Current password is required.";
if ($newPassword === '') $errors[] = "New password is required.";
if ($confirmPassword === '') $errors[] = "Please confirm your new password.";
 
if ($errors) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => implode(' ', $errors)
    ]);
    exit();
}
 
if ($newPassword !== $confirmPassword) {
    http_response_code(422);
    echo json_encode([
        "success" => false, 
        "message" => "New passwords do not match."
    ]);
    exit();
}
 
if ($currentPassword === $newPassword) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "New password must be different from current password."
    ]);
    exit();
}
 
// Password strength: min 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $newPassword)) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.",
    ]);
    exit();
}
 
// Fetch password hash from DB
$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
 
if (!$admin) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Admin account not found."
    ]);
    exit();
}
 
// Verify current password
if (!password_verify($currentPassword, $admin['password_hash'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Current password is incorrect."
    ]);
    exit;
}
 
// Hash & save new password
$newHash = password_hash($newPassword, PASSWORD_BCRYPT);
 
$update = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
$update->execute([$newHash, $_SESSION['admin_id']]);
 
// Invalidate session — force re-login for security
// logoutAdmin();
 
http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Password changed successfully.",
]);
