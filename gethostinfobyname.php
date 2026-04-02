<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['hostname'])) {
    http_response_code(400);
    exit;
}

$ip = gethostbyname($_GET['hostname']);

header('Content-Type: application/json');
echo json_encode([
    'hostname' => $_GET['hostname'],
    'gethostbyname' => $ip,
    'gethostbyaddr' => gethostbyaddr($ip),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
