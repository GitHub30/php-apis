<?php
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['hostname']) || !preg_match('/^[a-zA-Z0-9.\-]+$/', $_GET['hostname'])) {
    http_response_code(400);
    exit;
}

$hostname = $_GET['hostname'];

// --- DNS Resolution ---
$ip = gethostbyname($hostname);
$dnsResolved = $ip !== $hostname;

// --- HTTP Server Header ---
$serverHeader = null;
$httpHeaders = @get_headers("https://{$hostname}", true);
if ($httpHeaders) {
    $serverHeader = $httpHeaders['Server'] ?? $httpHeaders['server'] ?? null;
    if (is_array($serverHeader)) {
        $serverHeader = $serverHeader[0];
    }
}

// --- SSL Connection ---
$context = stream_context_create([
    'ssl' => [
        'capture_peer_cert' => true,
        'capture_peer_cert_chain' => true,
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

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

$peer_certificate = $params['options']['ssl']['peer_certificate'];
$parsed_peer_certificate = openssl_x509_parse($peer_certificate);

openssl_x509_export($peer_certificate, $certPem);
$certDer = base64_decode(
    str_replace(["\r", "\n", '-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $certPem)
);
$sha1Thumbprint = strtoupper(sha1($certDer));

$pubKey = openssl_pkey_get_details(openssl_pkey_get_public($peer_certificate));
$keyLength = $pubKey['bits'] ?? null;


$commonName = $parsed_peer_certificate['subject']['CN'] ?? null;
$san = $parsed_peer_certificate['extensions']['subjectAltName'] ?? null;
$sanList = $san ? array_map('trim', explode(',', str_replace('DNS:', '', $san))) : [];

$certMatchesHostname = false;
foreach ($sanList as $name) {
    if ($name === $hostname) {
        $certMatchesHostname = true;
        break;
    }

    if (str_starts_with($name, '*.')) {
        $wildcard = substr($name, 2);
        if (str_ends_with($hostname, $wildcard) && substr_count($hostname, '.') === substr_count($name, '.')) {
            $certMatchesHostname = true;
            break;
        }
    }
}

$parsed_peer_certificate_chain = [];
if (!empty($params['options']['ssl']['peer_certificate_chain'])) {
    foreach ($params['options']['ssl']['peer_certificate_chain'] as $peer_certificate_chain) {
        $parsed = openssl_x509_parse($peer_certificate_chain);
        $parsed_peer_certificate_chain[] = [
            'subject' => $parsed['subject']['CN'] ?? ($parsed['subject']['O'] ?? null),
            'issuer' => $parsed['issuer']['CN'] ?? ($parsed['issuer']['O'] ?? null),
            'validFrom' => date('c', $parsed['validFrom_time_t'] ?? 0),
            'validTo' => date('c', $parsed['validTo_time_t'] ?? 0),
        ];
    }
}

$validFrom_time_t = $parsed_peer_certificate['validFrom_time_t'] ?? 0;
$validTo_time_t = $parsed_peer_certificate['validTo_time_t'] ?? 0;
$now = time();

$result = [
    'dns' => [
        'resolved' => $dnsResolved,
        'gethostbyname' => $dnsResolved ? $ip : null,
        'gethostbyaddr' => $dnsResolved ? gethostbyaddr($ip) : null,
        'httpServerHeader' => $serverHeader,
    ],
    'certificate' => [
        'commonName' => $commonName,
        'subjectAlternativeNames' => $sanList,
        'issuer' => $parsed_peer_certificate['issuer']['CN'] ?? ($parsed_peer_certificate['issuer']['O'] ?? null),
        'serialNumber' => $parsed_peer_certificate['serialNumberHex'] ?? null,
        'sha1Thumbprint' => $sha1Thumbprint,
        'keyLength' => $keyLength,
        'signatureAlgorithm' => $parsed_peer_certificate['signatureTypeSN'] ?? null,
    ],
    'expiration' => [
        'validFrom' => date('c', $validFrom_time_t),
        'validTo' => date('c', $validTo_time_t),
        'daysRemaining' => max(0, (int)(($validTo_time_t - $now) / 86400)),
        'isExpired' => $now > $validTo_time_t,
    ],
    'certificateNameMatch' => $certMatchesHostname,
    'isCorrectlyInstalled' => !!@stream_socket_client("ssl://{$hostname}:443"),
    'chain' => $parsed_peer_certificate_chain,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
