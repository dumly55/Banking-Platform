<?php
// Serves uploaded check images to employees. Uploads live outside the web root so they
// can't be fetched directly by guessing a URL.
require __DIR__ . '/../src/bootstrap.php';
require_employee();

$column = input('side') === 'back' ? 'back_image' : 'front_image';
$file = db_value("SELECT $column FROM check_deposits WHERE transaction_id = ?", [input('id')]);
$path = config('storage') . '/uploads/deposits/' . basename((string) $file);

if (!$file || !is_file($path)) {
    http_response_code(404);
    exit;
}

$ext = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . array_search($ext, DEPOSIT_IMAGE_TYPES, true));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
