<?php

/**
 * /cors.php?url=https://example.com
 */
set_time_limit(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_implicit_flush(true);

header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function header_function($ch, $header)
{
    $len = strlen($header);
    $trimmed = trim($header);
    if (empty($trimmed)) {
        return $len;
    }

    // 4. HTTPステータスライン (HTTP/1.1 や HTTP/2 など) を除外
    // ※ cURLのステータスコードは curl_getinfo で後から取得するのが安全です
    if (stripos($trimmed, 'HTTP/') === 0) {
        return $len;
    }

    $headerParts = explode(':', $trimmed, 2);
    if (count($headerParts) === 2) {
        $headerName = strtolower(trim($headerParts[0]));

        // 転送してはいけないヘッダーのブラックリスト
        $excludeHeaders = [
            'transfer-encoding',
            'connection',
            'keep-alive',
            'content-encoding',
            'content-length',
            'access-control-allow-origin'
        ];

        if (!in_array($headerName, $excludeHeaders)) {
            header($trimmed, false);
        }
    }

    return $len;
}

$ch = curl_init($_GET['url']);
$stream = fopen('php://output', 'w');
curl_setopt($ch, CURLOPT_FILE, $stream);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'header_function');

if (!curl_exec($ch)) {
    error_log('cURL Error: ' . curl_error($ch));
}
