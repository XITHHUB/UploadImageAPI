# PHP Login and Registration System with Image Uploader & API

A complete PHP-based user authentication system with login, registration, image upload functionality, and RESTful API using MySQL database.

## Features

- User Registration with validation
- User Login with username/email support
- Secure password hashing
- Session management
- **Image Upload & Management**
- **Image Gallery with modal viewer**
- **Secure image storage and access**
- **RESTful API with API key authentication**
- **API key management system**
- **API testing interface**
- Responsive design
- Dashboard with image uploader
- Logout functionality

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- PDO extension enabled

## Setup Instructions

### 1. Database Setup

1. Make sure MySQL is running
2. Update database credentials in `config.php` if needed:

   ```php
   $host = 'localhost';
   $dbname = 'user_auth_db';
   $username = 'root';
   $password = '';
   ```

3. Run the setup script to create the database and table:
   - Navigate to `http://localhost/your-project-folder/setup.php`
   - This will automatically create the database and users table

### 2. File Structure

```
/
├── config.php          # Database configuration
├── setup.php           # Database setup script
├── index.php           # Main entry point
├── login.php           # Login page
├── register.php        # Registration page
├── dashboard.php       # Image uploader dashboard with API management
├── logout.php          # Logout script
├── image.php           # Secure image viewer
├── api_upload.php      # API endpoint for image upload
├── api_images.php      # API endpoint for listing images
├── api_tester.php      # API testing interface
├── style.css           # Styling
├── uploads/            # Image storage directory
│   └── .htaccess       # Security for uploads
└── README.md           # This file
```

### 3. Usage

1. **First Time Setup**: Visit `setup.php` to create the database
2. **Registration**: Go to `register.php` to create a new account
3. **Login**: Use `login.php` to access your account
4. **Dashboard**: After login, you'll be redirected to `dashboard.php` with image upload functionality and API management

### 4. API Features

- **API Key Generation**: Create multiple API keys for different applications
- **Secure Authentication**: Bearer token authentication for API access
- **RESTful Endpoints**: Upload images and list images via API
- **Usage Tracking**: Monitor API key usage and last access times
- **API Testing Interface**: Built-in tool to test your API endpoints

#### API Endpoints:

- `POST /api_upload.php` - Upload images via API
- `GET /api_images.php` - List user's images with pagination

### 5. Image Upload Features

- **Secure Upload**: Only JPEG, PNG, GIF, and WebP images allowed
- **File Size Limit**: Maximum 5MB per image
- **Image Gallery**: View all uploaded images in a responsive grid
- **Modal Viewer**: Click images to view in full-size modal
- **Image Management**: Delete unwanted images
- **Secure Access**: Images are protected and only accessible by the owner

### 5. Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- Input validation and sanitization
- Session management for authentication
- XSS protection with `htmlspecialchars()`
- **Secure image storage with access control**
- **File type validation and size limits**

### 6. Customization

- Modify `style.css` to change the appearance
- Update `config.php` for different database settings
- Extend `dashboard.php` to add more user features
- Adjust image size limits and allowed formats in `dashboard.php`

## Database Schema

The system creates three tables:

**Users Table:**

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Images Table:**

```sql
CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**API Keys Table:**

```sql
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    usage_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Troubleshooting

- **Database connection errors**: Check MySQL is running and credentials in `config.php`
- **Permission errors**: Ensure web server has read/write access to the project folder
- **Session issues**: Make sure session support is enabled in PHP
