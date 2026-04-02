<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

$ip = $_GET['ip'] ?? '';

if (filter_var($ip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    $ip = gethostbyname($ip);
}

if (!$ip) {
    http_response_code(400);
    exit;
}

header('Content-Type: text/plain');
$escaped = escapeshellarg($ip);
passthru("whois -h whois.radb.net $escaped");
