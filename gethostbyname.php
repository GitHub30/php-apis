<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain');

if (!isset($_GET['hostname'])) {
    http_response_code(400);
    exit;
}

echo gethostbyname($_GET['hostname']);
