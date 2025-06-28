<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

$message = '';
$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validate title and description lengths
    if (strlen($title) > 255) {
        $error = 'Title must be 255 characters or less.';
    } elseif (strlen($description) > 1000) {
        $error = 'Description must be 1000 characters or less.';
    } else {
        // Debug information
        error_log("Upload attempt - File error: " . $file['error']);
        error_log("Upload attempt - File size: " . $file['size']);
        error_log("Upload attempt - File type: " . $file['type']);
        error_log("Upload attempt - Title: " . $title);
        error_log("Upload attempt - Description: " . $description);
        
        // Check if file was uploaded without errors
        if ($file['error'] === UPLOAD_ERR_OK) {
        // Get actual mime type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file type (check both reported and detected mime types)
        if (!in_array($file['type'], $allowed_types) && !in_array($detected_type, $allowed_types)) {
            $error = 'Only JPEG, PNG, GIF, and WebP images are allowed. Detected type: ' . $detected_type;
        }
        // Validate file size
        elseif ($file['size'] > $max_size) {
            $error = 'File size must be less than 5MB. Your file is ' . number_format($file['size'] / 1024 / 1024, 2) . 'MB.';
        }
        // Check if uploads directory exists and is writable
        elseif (!is_dir('uploads')) {
            $error = 'Uploads directory does not exist.';
        }
        elseif (!is_writable('uploads')) {
            $error = 'Uploads directory is not writable. Please check permissions.';
        }
        else {
            // Generate unique filename
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('img_', true) . '.' . $file_extension;
            
            // Use absolute path
            $upload_dir = __DIR__ . '/uploads/';
            $upload_path = $upload_dir . $filename;
            
            // Debug information
            error_log("Upload dir: " . $upload_dir);
            error_log("Upload path: " . $upload_path);
            error_log("Temp file: " . $file['tmp_name']);
            error_log("Temp file exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No'));
            error_log("Uploads dir exists: " . (is_dir($upload_dir) ? 'Yes' : 'No'));
            error_log("Uploads dir writable: " . (is_writable($upload_dir) ? 'Yes' : 'No'));
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                try {
                    // Save to database (store relative path)
                    $stmt = $pdo->prepare("INSERT INTO images (user_id, filename, original_name, file_size, mime_type, title, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $filename, $file['name'], $file['size'], $detected_type, $title, $description]);
                    
                    $message = 'Image uploaded successfully!';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    // Delete the uploaded file if database insert fails
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            } else {
                $php_error = error_get_last();
                $error = 'Failed to move uploaded file. Check directory permissions. Last PHP error: ' . ($php_error ? $php_error['message'] : 'None');
                error_log("Move upload failed. PHP error: " . print_r($php_error, true));
            }
        }
    } else {
        // More detailed error messages
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error = 'The uploaded file exceeds the MAX_FILE_SIZE directive.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error = 'The uploaded file was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = 'No file was uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error = 'Missing a temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error = 'A PHP extension stopped the file upload.';
                break;
            default:
                $error = 'Unknown upload error: ' . $file['error'];
                break;
        }
    }
}
}

// Handle image deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $image_id = $_GET['delete'];
    
    try {
        // Get image info before deleting
        $stmt = $pdo->prepare("SELECT filename FROM images WHERE id = ? AND user_id = ?");
        $stmt->execute([$image_id, $user_id]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
            $stmt->execute([$image_id, $user_id]);
            
            // Delete file from disk
            $file_path = 'uploads/' . $image['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $message = 'Image deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error deleting image: ' . $e->getMessage();
    }
}

// Handle API key generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_api_key'])) {
    $key_name = trim($_POST['key_name']);
    
    if (empty($key_name)) {
        $error = 'Please provide a name for the API key.';
    } else {
        try {
            // Generate unique API key
            $api_key = bin2hex(random_bytes(32)); // 64 character hex string
            
            $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key, key_name) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $api_key, $key_name]);
            
            $message = 'API key generated successfully!';
        } catch (PDOException $e) {
            $error = 'Error generating API key: ' . $e->getMessage();
        }
    }
}

// Handle API key deletion
if (isset($_GET['delete_api_key']) && is_numeric($_GET['delete_api_key'])) {
    $api_key_id = $_GET['delete_api_key'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
        $stmt->execute([$api_key_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = 'API key deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error deleting API key: ' . $e->getMessage();
    }
}

// Get user's API keys
try {
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $api_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching API keys: ' . $e->getMessage();
    $api_keys = [];
}

// Get user's images
try {
    $stmt = $pdo->prepare("SELECT * FROM images WHERE user_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$user_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching images: ' . $e->getMessage();
    $images = [];
}

// Get statistics for pie chart
try {
    // Get image count by title (for pie chart)
    $stmt = $pdo->prepare("SELECT 
        CASE 
            WHEN title IS NULL OR title = '' THEN 'Untitled' 
            ELSE title 
        END as title_group,
        COUNT(*) as count 
        FROM images 
        WHERE user_id = ? 
        GROUP BY title_group 
        ORDER BY count DESC 
        LIMIT 8");
    $stmt->execute([$user_id]);
    $title_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total storage used
    $stmt = $pdo->prepare("SELECT SUM(file_size) as total_size FROM images WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $storage_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_storage = $storage_stats['total_size'] ?? 0;
    
    // Get upload activity by month for the last 6 months
    $stmt = $pdo->prepare("SELECT 
        DATE_FORMAT(upload_date, '%Y-%m') as month,
        COUNT(*) as count 
        FROM images 
        WHERE user_id = ? AND upload_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(upload_date, '%Y-%m')
        ORDER BY month DESC");
    $stmt->execute([$user_id]);
    $monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get most popular titles (non-empty titles)
    $stmt = $pdo->prepare("SELECT 
        title, 
        COUNT(*) as count 
        FROM images 
        WHERE user_id = ? AND title IS NOT NULL AND title != '' 
        GROUP BY title 
        ORDER BY count DESC, title ASC 
        LIMIT 10");
    $stmt->execute([$user_id]);
    $popular_titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching statistics: ' . $e->getMessage();
    $title_stats = [];
    $monthly_stats = [];
    $popular_titles = [];
    $total_storage = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Uploader - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="dashboard">
        <div class="header">
            <h1 class="welcome">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <div class="stats-section">
            <h2>Your Statistics</h2>

            <div class="stats-grid">
                <!-- Overview Cards -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($images); ?></div>
                        <div class="stat-label">Total Images</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($total_storage / 1024 / 1024, 1); ?> MB</div>
                        <div class="stat-label">Storage Used</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($api_keys); ?></div>
                        <div class="stat-label">API Keys</div>
                    </div>
                </div> <!-- Titles Distribution Pie Chart -->
                <div class="chart-container">
                    <h3>Images by Title</h3>
                    <?php if (empty($title_stats)): ?>
                    <div class="no-chart-data">
                        <p class="no-data">No images uploaded yet.</p>
                        <small>Upload some images to see the title distribution!</small>
                    </div>
                    <?php else: ?>
                    <canvas id="titleChart" width="300" height="300"></canvas>
                    <?php endif; ?>
                </div>

                <!-- Most Popular Titles -->
                <div class="popular-titles">
                    <h3>Most Popular Titles</h3>
                    <?php if (empty($popular_titles)): ?>
                    <p class="no-data">No titled images yet. Add titles to your uploads!</p>
                    <?php else: ?>
                    <div class="titles-list">
                        <?php foreach ($popular_titles as $index => $title_data): ?>
                        <div class="title-item">
                            <div class="title-rank"><?php echo $index + 1; ?></div>
                            <div class="title-content">
                                <div class="title-name"><?php echo htmlspecialchars($title_data['title']); ?></div>
                                <div class="title-count"><?php echo $title_data['count']; ?>
                                    image<?php echo $title_data['count'] > 1 ? 's' : ''; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="upload-section">
            <h2>Upload New Image</h2>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="form-group">
                    <label for="image">Choose Image:</label>
                    <input type="file" id="image" name="image" accept="image/*" required>
                    <small>Supported formats: JPEG, PNG, GIF, WebP (Max: 5MB)</small>
                </div>
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" placeholder="Enter image title (optional)"
                        maxlength="255">
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="3"
                        placeholder="Enter image description (optional)" maxlength="1000"></textarea>
                </div>
                <button type="submit" class="btn">Upload Image</button>
            </form>
        </div>

        <!-- API Key Management -->
        <div class="api-section">
            <h2>API Key Management</h2>
            <p>Generate API keys to upload images programmatically via API endpoints.</p>

            <!-- Generate New API Key -->
            <div class="api-generate">
                <h3>Generate New API Key</h3>
                <form method="POST" class="api-form">
                    <div class="form-group">
                        <label for="key_name">API Key Name:</label>
                        <input type="text" id="key_name" name="key_name" placeholder="e.g., Mobile App, Website, etc."
                            required>
                    </div>
                    <button type="submit" name="generate_api_key" class="btn">Generate API Key</button>
                </form>
            </div>

            <!-- Existing API Keys -->
            <div class="api-keys-list">
                <h3>Your API Keys (<?php echo count($api_keys); ?>)</h3>

                <?php if (empty($api_keys)): ?>
                <p class="no-keys">No API keys generated yet.</p>
                <?php else: ?>
                <div class="api-keys-grid">
                    <?php foreach ($api_keys as $key): ?>
                    <div class="api-key-card">
                        <div class="api-key-header">
                            <h4><?php echo htmlspecialchars($key['key_name']); ?></h4>
                            <span class="api-status <?php echo $key['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <div class="api-key-info">
                            <div class="api-key-value">
                                <label>API Key:</label>
                                <div class="key-display">
                                    <input type="text" value="<?php echo htmlspecialchars($key['api_key']); ?>" readonly
                                        class="api-key-input" id="key-<?php echo $key['id']; ?>">
                                    <button onclick="copyApiKey('key-<?php echo $key['id']; ?>')"
                                        class="copy-btn">Copy</button>
                                </div>
                            </div>

                            <div class="api-key-stats">
                                <p><strong>Created:</strong>
                                    <?php echo date('M j, Y', strtotime($key['created_at'])); ?></p>
                                <p><strong>Usage Count:</strong> <?php echo number_format($key['usage_count']); ?></p>
                                <?php if ($key['last_used']): ?>
                                <p><strong>Last Used:</strong>
                                    <?php echo date('M j, Y H:i', strtotime($key['last_used'])); ?></p>
                                <?php else: ?>
                                <p><strong>Last Used:</strong> Never</p>
                                <?php endif; ?>
                            </div>

                            <a href="?delete_api_key=<?php echo $key['id']; ?>" class="delete-btn"
                                onclick="return confirm('Are you sure you want to delete this API key? This action cannot be undone.')">Delete</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div> <!-- API Documentation -->
            <div class="api-docs">
                <h3>API Documentation</h3>

                <div class="api-test-link">
                    <a href="api_tester.php" class="btn" style="display: inline-block; margin-bottom: 20px;">🧪 Test API
                        Endpoints</a>
                </div>

                <div class="api-endpoint">
                    <h4>Upload Image via API</h4>
                    <p><strong>Endpoint:</strong> <code>POST /api_upload.php</code></p>
                    <p><strong>Headers:</strong></p>
                    <ul>
                        <li><code>Authorization: Bearer YOUR_API_KEY</code></li>
                        <li><code>Content-Type: multipart/form-data</code></li>
                    </ul>
                    <p><strong>Parameters:</strong></p>
                    <ul>
                        <li><code>image</code> - The image file to upload (required)</li>
                        <li><code>title</code> - Title for the image (optional, max 255 characters)</li>
                        <li><code>description</code> - Description for the image (optional, max 1000 characters)</li>
                    </ul>

                    <h5>Example cURL Request:</h5>
                    <pre class="code-example">curl -X POST "<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/api_upload.php" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -F "image=@/path/to/your/image.jpg" \
     -F "title=My Image Title" \
     -F "description=This is a description of my image"</pre>
                </div>

                <div class="api-endpoint">
                    <h4>List Images via API</h4>
                    <p><strong>Endpoint:</strong> <code>GET /api_images.php</code></p>
                    <p><strong>Headers:</strong></p>
                    <ul>
                        <li><code>Authorization: Bearer YOUR_API_KEY</code></li>
                    </ul>
                    <p><strong>Query Parameters:</strong></p>
                    <ul>
                        <li><code>page</code> - Page number (default: 1)</li>
                        <li><code>limit</code> - Items per page (default: 20, max: 100)</li>
                    </ul>

                    <h5>Example cURL Request:</h5>
                    <pre class="code-example">curl -X GET "<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/api_images.php?page=1&limit=10" \
     -H "Authorization: Bearer YOUR_API_KEY"</pre>
                </div>
            </div>
        </div>

        <!-- Images Gallery -->
        <div class="gallery-section">
            <h2>Your Images (<?php echo count($images); ?>)</h2>

            <?php if (empty($images)): ?>
            <p class="no-images">No images uploaded yet. Upload your first image above!</p>
            <?php else: ?>
            <div class="image-grid">
                <?php foreach ($images as $image): ?>
                <div class="image-card">
                    <img src="image.php?img=<?php echo urlencode($image['filename']); ?>"
                        alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                        onclick="openModal('image.php?img=<?php echo urlencode($image['filename']); ?>', '<?php echo htmlspecialchars($image['original_name']); ?>', '<?php echo htmlspecialchars($image['title'] ?? ''); ?>', '<?php echo htmlspecialchars($image['description'] ?? ''); ?>')">

                    <div class="image-info">
                        <h4><?php echo htmlspecialchars($image['title'] ?: $image['original_name']); ?></h4>
                        <?php if (!empty($image['description'])): ?>
                        <p class="image-description"><?php echo htmlspecialchars($image['description']); ?></p>
                        <?php endif; ?>
                        <p>Size: <?php echo number_format($image['file_size'] / 1024, 1); ?> KB</p>
                        <p>Uploaded: <?php echo date('M j, Y', strtotime($image['upload_date'])); ?></p>
                        <a href="?delete=<?php echo $image['id']; ?>" class="delete-btn"
                            onclick="return confirm('Are you sure you want to delete this image?')">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for full-size image view -->
    <div id="imageModal" class="modal" onclick="closeModal()">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="modalImage" src="" alt="">
            <div id="modalCaption">
                <h3 id="modalTitle"></h3>
                <p id="modalDescription"></p>
            </div>
        </div>
    </div>

    <script>
    // Initialize pie chart for titles
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('titleChart');
        if (ctx) {
            const titleData = <?php echo json_encode($title_stats); ?>;

            // Prepare data for chart
            const labels = [];
            const data = [];
            const backgroundColors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
            ];

            titleData.forEach((item, index) => {
                // Truncate long titles for display
                let displayTitle = item.title_group;
                if (displayTitle.length > 20) {
                    displayTitle = displayTitle.substring(0, 17) + '...';
                }
                labels.push(displayTitle);
                data.push(item.count);
            });

            // Only create chart if there's data
            if (data.length > 0) {
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: backgroundColors.slice(0, data.length),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b,
                                            0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(
                                            1);
                                        const fullTitle = titleData[context.dataIndex].title_group;
                                        return fullTitle + ': ' + context.parsed + ' (' +
                                            percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                // Show message if no data
                ctx.style.display = 'none';
                const container = ctx.parentElement;
                container.innerHTML = '<h3>Images by Title</h3><p class="no-data">No images uploaded yet.</p>';
            }
        }
    });

    function openModal(src, originalName, title, description) {
        document.getElementById('imageModal').style.display = 'block';
        document.getElementById('modalImage').src = src;

        const titleElement = document.getElementById('modalTitle');
        const descriptionElement = document.getElementById('modalDescription');

        titleElement.textContent = title || originalName;
        descriptionElement.textContent = description || '';
        descriptionElement.style.display = description ? 'block' : 'none';
    }

    function closeModal() {
        document.getElementById('imageModal').style.display = 'none';
    }

    function copyApiKey(inputId) {
        const input = document.getElementById(inputId);
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices

        try {
            document.execCommand('copy');
            // Visual feedback
            const button = input.nextElementSibling;
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.style.background = '#28a745';

            setTimeout(() => {
                button.textContent = originalText;
                button.style.background = '';
            }, 2000);
        } catch (err) {
            alert('Failed to copy API key. Please copy manually.');
        }
    }

    // Close modal when pressing Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    </script>
</body>

</html>