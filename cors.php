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

function get_request_headers()
{
    $request_headers = [];
    foreach (getallheaders() as $name => $value) {
        if (strtolower($name) === 'host') {
            continue;
        }
        $request_headers[] = "$name: $value";
    }
    return $request_headers;
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


error_log($_GET['url']);
$ch = curl_init($_GET['url']);
$stream = fopen('php://output', 'w');

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_HTTPHEADER, get_request_headers());
curl_setopt($ch, CURLOPT_FILE, $stream);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'header_function');

$input = file_get_contents('php://input');
if ($input) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

if (!curl_exec($ch)) {
    error_log('cURL Error: ' . curl_error($ch));
}
