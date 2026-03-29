<?php

header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['filename'])) {
        $contentType = mime_content_type($_GET['filename']);
        if ($contentType) {
            header("Content-Type: $contentType");
        }
        if (!ftp_get($ftp, 'php://output', $_GET['filename'])) {
            http_response_code(404);
        }
    } else {
        $files = ftp_nlist($ftp, $_GET['path'] ?? '.');
        if (!$files) {
            header('Content-Type: application/json');
            echo json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
} else if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    if (!ftp_put($ftp, $_GET['filename'], 'php://input')) {
        http_response_code(500);
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!ftp_delete($ftp, $_GET['filename'])) {
        http_response_code(500);
    }
} else {
    http_response_code(405);
}

ftp_close($ftp);
