<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

function guessHostFromUser($user)
{
    $parts = explode('@', $user, 2);
    if (count($parts) !== 2) {
        return '';
    }
    $ip = gethostbyname($parts[1]);
    $host = gethostbyaddr($ip);
    return $host ?: $parts[1];
}

$user = $_GET['user'] ?? '';
$password = $_GET['password'] ?? '';
$host = $_GET['host'] ?? guessHostFromUser($user);
$port = (int) ($_GET['port'] ?? 587);
$to = $_GET['to'] ?? '';
$subject = $_GET['subject'] ?? '';
$body = $_GET['body'] ?? '';
$isHtml = ($_GET['html'] ?? '0') === '1';

if ($user === '' || $password === '' || $to === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'user, password, to are required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $password;
    $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $port;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($user);
    foreach (explode(',', $to) as $recipient) {
        $recipient = trim($recipient);
        if ($recipient !== '') {
            $mail->addAddress($recipient);
        }
    }

    $mail->isHTML($isHtml);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    http_response_code(201);
} catch (Exception $e) {
    http_response_code(502);
    echo $mail->ErrorInfo;
}
