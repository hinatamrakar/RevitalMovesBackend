<?php

require_once '../db.php';
require_once '../server.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}

// Read multipart form data
$jobId = trim($_POST['job_id'] ?? '');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
 
// Input validation
$errors = [];

if (empty($jobId) || !ctype_digit((string)$jobId)) {
    $errors[] = 'A valid job ID is required';
}
if (empty($name)) {
    $errors[] = 'Name is required';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email is required';
}
if (empty($phone)) {
    $errors[] = 'Phone number is required';
}
if (!preg_match('/^\+?[\d\s\-().]{7,20}$/', $phone)) {
    $errors[] = 'Phone number format is invalid';
}

// File validation
$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$allowedExtensions = ['pdf', 'doc', 'docx'];
$maxFileSize = 5 * 1024 * 1024; // 5 MB

// Resume validation
$resumeFile = null;
$resumeExt = null;

if (empty($_FILES['resume_file']) || $_FILES['resume_file']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Resume file is required';
} elseif ($_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Resume upload failed. Please try again';
} else {
    $resumeFile = $_FILES['resume_file'];
    $resumeExt = strtolower(pathinfo($resumeFile['name'], PATHINFO_EXTENSION));
    $resumeMime = mime_content_type($resumeFile['tmp_name']);
 
    if ($resumeFile['size'] > $maxFileSize) {
        $errors[] = 'Resume file must be 5 MB or smaller';
    }
    if (!in_array($resumeExt, $allowedExtensions, true)) {
        $errors[] = 'Resume must be a PDF, DOC, or DOCX file';
    }
    if (!in_array($resumeMime, $allowedMimeTypes, true)) {
        $errors[] = 'Resume file type is not allowed';
    }
}

// Cover letter validation
$coverLetter = null;
$coverLetterExt = null;

if (!empty($_FILES['cover_letter'])) {
    if ($_FILES['cover_letter']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Cover letter upload failed. Please try again';
    } else {
        $coverLetter = $_FILES['cover_letter'];
        $coverLetterExt = strtolower(pathinfo($coverLetter['name'], PATHINFO_EXTENSION));
        $coverLetterMime = mime_content_type($coverLetter['tmp_name']);
     
        if ($coverLetter['size'] > $maxFileSize) {
            $errors[] = 'Cover letter file must be 5 MB or smaller';
        }
        if (!in_array($coverLetterExt, $allowedExtensions, true)) {
            $errors[] = 'Cover letter must be a PDF, DOC, or DOCX file';
        }
        if (!in_array($coverLetterMime, $allowedMimeTypes, true)) {
            $errors[] = 'Cover letter file type is not allowed';
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

// Verify job exists
$jobStmt = $pdo->prepare("
    SELECT id 
    FROM jobs 
    WHERE id = ? AND deleted_at IS NULL AND status = 'active'
    LIMIT 1");
$jobStmt->execute([$jobId]);
if (!$jobStmt->fetch()) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

// Prevent duplicate applications (same email + job)
$dupStmt = $pdo->prepare("
    SELECT id FROM job_applications
    WHERE job_id = ? AND email = ? AND deleted_at IS NULL
    LIMIT 1
");
$dupStmt->execute([$jobId, $email]);
if ($dupStmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "You have already applied for this job"
    ]);
    exit();
}

// Save resume file
$resumeUploadDir = "../uploads/resumes/";
if (!is_dir($resumeUploadDir)) {
    mkdir($resumeUploadDir, 0755, true);
}

$resumeFileName = sprintf(
    'resume_%s_%s_%s.%s',
    time(),
    $jobId,
    bin2hex(random_bytes(6)),
    $resumeExt
);

$resumePath = $resumeUploadDir . $resumeFileName;

if (!move_uploaded_file($resumeFile['tmp_name'], $resumePath)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to upload resume. Please try again"
    ]);
    exit();
}

// Save cover letter
$coverLetterFileName = null;
$coverLetterPath = null;

if ($coverLetter !== null) {
    $coverLetterUploadDir = "../uploads/cover_letters/";
    if (!is_dir($coverLetterUploadDir)) {
        mkdir($coverLetterUploadDir, 0755, true);
    }
    
    $coverLetterFileName = sprintf(
        'cover_%s_%s_%s.%s',
        time(),
        $jobId,
        bin2hex(random_bytes(6)),
        $coverLetterExt
    );
    
    $coverLetterPath = $coverLetterUploadDir . $coverLetterFileName;
    
    if (!move_uploaded_file($coverLetter['tmp_name'], $coverLetterPath)) {
        if (file_exists($resumePath)) {
            unlink($resumePath);
        }
    
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to upload cover letter. Please try again"
        ]);
        exit();
    }
}

// Insert application
try {
    $stmt = $pdo->prepare("
        INSERT INTO job_applications
            (job_id, name, email, phone, resume_file, cover_letter, status)
        VALUES
            (:job_id, :name, :email, :phone, :resume_file, :cover_letter, 'new')
    ");
    $stmt->execute([
        ':job_id' => (int)$jobId,
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':resume_file' => $resumeFileName,
        ':cover_letter' => $coverLetterFileName,
    ]);
 
    $applicationId = $pdo->lastInsertId();
} catch (PDOException $e) {
    // Clean up uploaded file on DB failure
    if (file_exists($resumePath)) {
        unlink($resumePath);
    }

    if (file_exists($coverLetterPath)) {
        unlink($coverLetterPath);
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to submit application. Please try again"
    ]);
    exit();
}
 
http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Application submitted successfully",
    "application" => [
        "id" => (int)$applicationId,
        "job_id" => (int)$jobId,
        "name"  => $name,
        "email" => $email,
    ]
]);
