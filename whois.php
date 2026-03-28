<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain');

$domain = $_GET['domain'] ?? '';

if (!$domain || !preg_match('/^[a-zA-Z0-9._-]+$/', $domain)) {
    http_response_code(400);
    exit;
}

$escaped = escapeshellarg($domain);
passthru("whois $escaped");
