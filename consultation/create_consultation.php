<?php

require_once "../db.php";
require_once "../server.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once "../vendor/autoload.php";

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

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$company_name = trim($data['company_name'] ?? '');
$consultation_type = trim($data['consultation_type'] ?? '');
$preferred_date = trim($data['preferred_date'] ?? '');
$preferred_time = trim($data['preferred_time'] ?? '');
$message = trim($data['message'] ?? '');

// Input validation
$errors = [];

if (empty($name)) $errors[] = 'Full name is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required';
if (empty($phone)) $errors[] = 'Phone number is required';
if (!preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $phone)) $errors[] = 'Phone number format is invalid';
if (empty($company_name)) $errors[] = 'Company name is required';
if (empty($consultation_type)) $errors[] = 'Consultation type is required';
if (empty($message)) $errors[] = 'A message is required';

if (empty($preferred_date)) {
    $errors[] = 'Preferred date is required';
} else {
    $parsedDate = DateTime::createFromFormat('Y-m-d', $preferred_date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $preferred_date) {
        $errors[] = 'Preferred date must be in YYYY-MM-DD format';
    } elseif ($parsedDate < new DateTime('today')) {
        $errors[] = 'Preferred date cannot be in the past';
    }
}

if (empty($preferred_time)) {
    $errors[] = 'Preferred time is required';
} else {
    $parsedTime = DateTime::createFromFormat('H:i', $preferred_time);
    if (!$parsedTime) {
        $errors[] = 'Preferred time must be in HH:MM format';
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

// Insert consultation
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO consultations (
            name,
            email,
            phone,
            company_name,
            consultation_type,
            preferred_date,
            preferred_time,
            message
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $email,
        $phone,
        $company_name,
        $consultation_type,
        $preferred_date,
        $preferred_time,
        $message,
    ]);

    $consultationId = (int) $pdo->lastInsertId();

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to add consultation"
    ]);
    exit();
}

// Send email to admin
// Format date and time for display
$displayDate = DateTime::createFromFormat('Y-m-d', $preferred_date)->format('F j, Y');
$displayTime = DateTime::createFromFormat('H:i', $preferred_time)->format('g:i A');

$mail = new PHPMailer(true);

try {
    // SMTP Config
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
    $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) ($_ENV['SMTP_PORT'] ?? 587);

    // From (customer)
    $mail->setFrom($email, $name);
    $mail->addReplyTo($email, $name);

    // To (admin inbox)
    $mail->addAddress($_ENV['ADMIN_EMAIL'], 'Admin');

    // Content
    $mail->isHTML(true);
    $mail->Subject = "New Consultation Request";
    $mail->Body = buildEmailHtml(
        $consultationId,
        $name,
        $email,
        $phone,
        $company_name,
        $consultation_type,
        $displayDate,
        $displayTime,
        $message
    );
    $mail->AltBody = buildEmailPlain(
        $consultationId,
        $name,
        $email,
        $phone,
        $company_name,
        $consultation_type,
        $displayDate,
        $displayTime,
        $message
    );

    $mail->send();
    $emailSent = true;

} catch (Exception $e) {
    $emailSent = false;
    $emailError = $mail->ErrorInfo;
    error_log("Mailer Error: " . $emailError);

}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Consultation added successfully",
    "email_sent" => $emailSent
]);

// Create HTML email
function buildEmailHtml(
    int $id,
    string $name,
    string $email,
    string $phone,
    string $company_name,
    string $consultation_type,
    string $preferred_date,
    string $preferred_time,
    string $message
): string {
    $safeId = htmlspecialchars($id);
    $safeName = htmlspecialchars($name);
    $safeEmail = htmlspecialchars($email);
    $safePhone = htmlspecialchars($phone);
    $safeCompany = htmlspecialchars($company_name);
    $safeType = htmlspecialchars($consultation_type);
    $safeDate = htmlspecialchars($preferred_date);
    $safeTime = htmlspecialchars($preferred_time);
    $safeMessage = nl2br(htmlspecialchars($message));
 
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>New Consultation Request</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
 
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f0f0;padding:16px 0 40px;">
    <tr>
      <td align="center">
 
        <table width="560" cellpadding="0" cellspacing="0"
               style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e2e2;">
 
          <!-- Brand -->
          <tr>
            <td align="center" style="padding:24px 32px 0;">
              <p style="margin:0;font-size:21px;font-weight:700;color:#111827;letter-spacing:-0.3px;">Revital Moves</p>
            </td>
          </tr>
 
          <!-- Divider -->
          <tr>
            <td style="padding:16px 32px 0;">
              <hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">
            </td>
          </tr>
 
          <!-- Intro -->
          <tr>
            <td style="padding:18px 32px 0;">
              <p style="margin:0;font-size:14px;color:#374151;line-height:1.6;">
                A new consultation request has been submitted. The details are below.
              </p>
            </td>
          </tr>
 
          <!-- Details table -->
          <tr>
            <td style="padding:16px 32px 0;">
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="font-size:14px;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
 
                <tr style="background:#f9fafb;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;width:40%;border-bottom:1px solid #e5e7eb;">Full Name</td>
                  <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #e5e7eb;">{$safeName}</td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Email</td>
                  <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                    <a href="mailto:{$safeEmail}" style="color:#1d4ed8;text-decoration:none;">{$safeEmail}</a>
                  </td>
                </tr>
                <tr style="background:#f9fafb;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Phone</td>
                  <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #e5e7eb;">{$safePhone}</td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Company</td>
                  <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #e5e7eb;">{$safeCompany}</td>
                </tr>
                <tr style="background:#f9fafb;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Consultation Type</td>
                  <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #e5e7eb;">{$safeType}</td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Preferred Date</td>
                  <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #e5e7eb;">{$safeDate}</td>
                </tr>
                <tr style="background:#f9fafb;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;">Preferred Time</td>
                  <td style="padding:12px 16px;color:#111827;">{$safeTime}</td>
                </tr>
 
              </table>
            </td>
          </tr>
 
          <!-- Message -->
          <tr>
            <td style="padding:16px 32px 0;">
              <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Message</p>
              <div style="background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;padding:14px 16px;font-size:14px;color:#374151;line-height:1.7;">
                {$safeMessage}
              </div>
            </td>
          </tr>
 
          <!-- Reply note -->
          <tr>
            <td style="padding:20px 32px 0;">
              <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;">
                You can reply directly to this email to contact the client, or log in to the admin panel to update the consultation status.
              </p>
            </td>
          </tr>
 
          <!-- Divider -->
          <tr>
            <td style="padding:18px 32px 0;">
              <hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">
            </td>
          </tr>
 
          <!-- Footer -->
          <tr>
            <td style="padding:13px 32px 22px;">
              <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
                This is an automated notification from Revital Moves. Do not reply to this message directly. Use the reply-to address above.
              </p>
            </td>
          </tr>
 
        </table>
 
      </td>
    </tr>
  </table>
 
</body>
</html>

HTML;
}
 
// Create plain fallback
function buildEmailPlain(
    string $name,
    string $email,
    string $phone,
    string $company_name,
    string $consultation_type,
    string $preferred_date,
    string $preferred_time,
    string $message
): string {
    return <<<TEXT
NEW CONSULTATION REQUEST
Revital Moves
 
Full Name: {$name}
Email: {$email}
Phone: {$phone}
Company: {$company_name}
Consultation Type: {$consultation_type}
Preferred Date: {$preferred_date}
Preferred Time: {$preferred_time}
 
MESSAGE
{$message}
 

You can reply directly to this email to contact the client.
This is an automated notification from Revital Moves.
TEXT;
}
