<?php
$filename = $_GET['file'] ?? '';

if (!$filename || !file_exists("uploads/$filename")) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

$filepath = "uploads/$filename";

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filepath) ?: 'application/octet-stream';
finfo_close($finfo);

$cleanFilename = preg_replace('/_(\d{7})(?=\.\w+$)/', '', basename($filename));

header('Content-Description: File Transfer');
header("Content-Type: $mimeType");
header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

readfile($filepath);
exit;