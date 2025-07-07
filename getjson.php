<?php
header("Content-Type: application/json");

$filename = $_GET['file'] ?? '';

if (!$filename || !preg_match('/^[\w\-]+\.\w+$/', $filename)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid filename"]);
    exit;
}

$filepath = __DIR__ . "/uploads/" . basename($filename);

if (!file_exists($filepath)) {
    http_response_code(404);
    echo json_encode(["error" => "File not found"]);
    exit;
}

$content = file_get_contents($filepath);
echo $content;