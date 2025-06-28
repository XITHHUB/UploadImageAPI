<?php
// Simple image viewer to serve images securely
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied');
}

// Get image filename from URL
$filename = $_GET['img'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    exit('Image not specified');
}

// Sanitize filename
$filename = basename($filename);
$filepath = 'uploads/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    http_response_code(404);
    exit('Image not found');
}

// Verify that the image belongs to the current user
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id FROM images WHERE filename = ? AND user_id = ?");
    $stmt->execute([$filename, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        exit('Access denied');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Set appropriate headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');

// Output the image
readfile($filepath);
?>
