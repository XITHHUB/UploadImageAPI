<?php
// API endpoint to list user's images
header('Content-Type: application/json');

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
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
    
    // Get pagination parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20))); // Max 100 items per page
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM images WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_count = $stmt->fetchColumn();
    
    // Get images
    $stmt = $pdo->prepare("SELECT id, filename, original_name, title, description, file_size, mime_type, upload_date FROM images WHERE user_id = ? ORDER BY upload_date DESC LIMIT ? OFFSET ?");
    $stmt->execute([$user_id, $limit, $offset]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add full URLs to images
    $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    
    foreach ($images as &$image) {
        $image['url'] = $base_url . '/image.php?img=' . urlencode($image['filename']);
        $image['file_size_formatted'] = number_format($image['file_size'] / 1024, 1) . ' KB';
    }
    
    // Update API key usage
    $stmt = $pdo->prepare("UPDATE api_keys SET usage_count = usage_count + 1, last_used = NOW() WHERE id = ?");
    $stmt->execute([$api_key_id]);
    
    // Calculate pagination info
    $total_pages = ceil($total_count / $limit);
    
    sendResponse([
        'success' => true,
        'data' => [
            'images' => $images,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_count' => $total_count,
                'per_page' => $limit,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ],
        'meta' => [
            'user' => $key_info['username'],
            'api_key_name' => $key_info['key_name']
        ]
    ]);
    
} catch (PDOException $e) {
    sendResponse(['error' => 'Database error'], 500);
} catch (Exception $e) {
    sendResponse(['error' => 'Unexpected error occurred'], 500);
}
?>
