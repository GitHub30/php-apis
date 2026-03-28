<?php

header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    exit;
}

function guessHostFromUsername($username)
{
    $parts = explode('@', $username, 2);
    if (count($parts) !== 2) {
        return '';
    }
    return $parts[1];
}

$ftp = ftp_connect($_GET['hostname'] ?? guessHostFromUsername($_GET['username']));
if (!$ftp) {
    http_response_code(400);
    exit;
}

if (!ftp_login($ftp, $_GET['username'], $_GET['password'])) {
    http_response_code(401);
    exit;
}

if (!ftp_put($ftp, $_GET['filename'], 'php://input')) {
    http_response_code(500);
    exit;
}

ftp_close($ftp);
