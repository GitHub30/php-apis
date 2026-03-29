<?php
require_once __DIR__ . '/vendor/autoload.php';

header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['api_key'])) {
    http_response_code(400);
    exit;
}

$model = $_GET['model'] ?? 'gemini-3.1-flash-lite-preview';
$prompt = $_GET['prompt'] ?? 'Hello';

try {
    $client = Gemini::client($_GET['api_key']);
    $result = $client->generativeModel(model: $model)->generateContent($prompt);
    echo $result->text();
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
