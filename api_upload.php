<?php
// API endpoint for image upload using API keys
header('Content-Type: application/json');

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once 'config.php';

// Function to send JSON response
function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Get Authorization header
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($auth_header)) {
    sendResponse(['error' => 'Authorization header missing'], 401);
}

// Extract API key from Bearer token
if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    sendResponse(['error' => 'Invalid authorization format. Use: Bearer YOUR_API_KEY'], 401);
}

$api_key = trim($matches[1]);

if (empty($api_key)) {
    sendResponse(['error' => 'API key is required'], 401);
}

try {
    // Validate API key and get user info
    $stmt = $pdo->prepare("SELECT ak.*, u.username FROM api_keys ak JOIN users u ON ak.user_id = u.id WHERE ak.api_key = ? AND ak.is_active = 1");
    $stmt->execute([$api_key]);
    $key_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$key_info) {
        sendResponse(['error' => 'Invalid or inactive API key'], 401);
    }
    
    $user_id = $key_info['user_id'];
    $api_key_id = $key_info['id'];
    
} catch (PDOException $e) {
    sendResponse(['error' => 'Database error during authentication'], 500);
}

// Check if image file is provided
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File too large (exceeds upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds MAX_FILE_SIZE)',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $error_code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $error_message = $error_messages[$error_code] ?? 'Unknown upload error';
    
    sendResponse(['error' => $error_message], 400);
}

$file = $_FILES['image'];

// Get optional title and description
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

// Validate title and description lengths
if (strlen($title) > 255) {
    sendResponse(['error' => 'Title must be 255 characters or less'], 400);
}

if (strlen($description) > 1000) {
    sendResponse(['error' => 'Description must be 1000 characters or less'], 400);
}

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($detected_type, $allowed_types)) {
    sendResponse(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'], 400);
}

// Validate file size (5MB limit)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    sendResponse(['error' => 'File too large. Maximum size is 5MB.'], 400);
}

// Check uploads directory
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
    sendResponse(['error' => 'Upload directory not accessible'], 500);
}

try {
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('api_img_', true) . '.' . $file_extension;
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        sendResponse(['error' => 'Failed to save uploaded file'], 500);
    }
    
    // Save to database
    $stmt = $pdo->prepare("INSERT INTO images (user_id, filename, original_name, file_size, mime_type, title, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $filename, $file['name'], $file['size'], $detected_type, $title, $description]);
    $image_id = $pdo->lastInsertId();
    
    // Update API key usage
    $stmt = $pdo->prepare("UPDATE api_keys SET usage_count = usage_count + 1, last_used = NOW() WHERE id = ?");
    $stmt->execute([$api_key_id]);
    
    // Return success response
    sendResponse([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'data' => [
            'image_id' => $image_id,
            'filename' => $filename,
            'original_name' => $file['name'],
            'title' => $title,
            'description' => $description,
            'file_size' => $file['size'],
            'mime_type' => $detected_type,
            'uploaded_by' => $key_info['username'],
            'api_key_name' => $key_info['key_name']
        ]
    ], 201);
    
} catch (PDOException $e) {
    // Clean up uploaded file if database insert fails
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    sendResponse(['error' => 'Database error while saving image'], 500);
} catch (Exception $e) {
    sendResponse(['error' => 'Unexpected error occurred'], 500);
}
?>
