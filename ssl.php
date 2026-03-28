<?php
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['hostname']) || !preg_match('/^[a-zA-Z0-9.\-]+$/', $_GET['hostname'])) {
    http_response_code(400);
    exit;
}


$context = stream_context_create([
    'ssl' => [
        'capture_peer_cert' => true,
        'capture_peer_cert_chain' => true,
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$hostname = $_GET['hostname'];
$fp = stream_socket_client(
    "ssl://{$hostname}:443",
    $error_code,
    $error_message,
    60,
    STREAM_CLIENT_CONNECT,
    $context
);

header('Content-Type: application/json');
if (!$fp) {
    echo json_encode(['error_code' => $error_code, 'error_message' => $error_message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$params = stream_context_get_params($fp);
fclose($fp);

$peer_certificate = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

$parsed_peer_certificate_chain = [];
if (!empty($params['options']['ssl']['peer_certificate_chain'])) {
    foreach ($params['options']['ssl']['peer_certificate_chain'] as $peer_certificate_chain) {
        $parsed = openssl_x509_parse($peer_certificate_chain);
        $parsed_peer_certificate_chain[] = [
            'subject' => $parsed['subject'] ?? null,
            'issuer' => $parsed['issuer'] ?? null,
            'validFrom' => date('c', $parsed['validFrom_time_t'] ?? 0),
            'validTo' => date('c', $parsed['validTo_time_t'] ?? 0),
        ];
    }
}

$validFrom = $peer_certificate['validFrom_time_t'] ?? 0;
$validTo = $peer_certificate['validTo_time_t'] ?? 0;
$now = time();

$result = [
    'host' => $host,
    'subject' => $peer_certificate['subject'] ?? null,
    'issuer' => $peer_certificate['issuer'] ?? null,
    'serialNumber' => $peer_certificate['serialNumberHex'] ?? null,
    'validFrom' => date('c', $validFrom),
    'validTo' => date('c', $validTo),
    'daysRemaining' => max(0, (int)(($validTo - $now) / 86400)),
    'isValid' => $now >= $validFrom && $now <= $validTo,
    'signatureAlgorithm' => $peer_certificate['signatureTypeSN'] ?? null,
    'subjectAltName' => $peer_certificate['extensions']['subjectAltName'] ?? null,
    'chain' => $parsed_peer_certificate_chain,
];


echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

