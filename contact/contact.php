<?php

require_once "../db.php";
require_once "../server.php";

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
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "No input received"
    ]);
    exit();
}

$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '') ?: null;
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '') ?: null;
$service = trim($data['service'] ?? '') ?: null;
$message = trim($data['message'] ?? '');
$terms = $data['terms_accepted'] ?? false;

// Validation
$errors = [];

if (empty($firstName)) $errors[] = 'First name is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
if (empty($message)) $errors[] = 'Message is required';
if ($terms !== true) $errors[] = 'Terms must be accepted';

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
 
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (
            first_name,
            last_name,
            email,
            phone,
            service,
            message,
            terms_accepted
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone,
        $service,
        $message,
        1
    ]);
 
    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to submit message"
    ]);
    exit();
}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Message submitted successfully"
]);