<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Metode tidak diizinkan']);
  exit;
}

if (!isset($_FILES['data']) || !isset($_POST['filename'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
  exit;
}

$filename = basename($_POST['filename']);
$uploadDir = __DIR__ . '/uploads/';
$uploadPath = $uploadDir . $filename;

if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

if (move_uploaded_file($_FILES['data']['tmp_name'], $uploadPath)) {
  echo json_encode([
    'success' => true,
    'file' => $filename
  ]);
} else {
  echo json_encode([
    'success' => false,
    'error' => 'Gagal menyimpan file'
  ]);
}