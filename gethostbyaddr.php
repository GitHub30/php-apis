<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain');

if (!isset($_GET['ip'])) {
    http_response_code(400);
    exit;
}

echo gethostbyaddr($_GET['ip']);
