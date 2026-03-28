<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

$domain = $_GET['domain'] ?? '';

if (!$domain || !preg_match('/^[a-zA-Z0-9._-]+$/', $domain)) {
    http_response_code(400);
    exit;
}

header('Content-Type: text/plain');
$escaped = escapeshellarg($domain);
passthru("whois $escaped");
