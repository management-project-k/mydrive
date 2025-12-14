<?php
/**
 * MyDrive - MySQL Cloud Storage
 * Single File Backend (index.php)
 * Database: InfinityFree MySQL
 */

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', 'sql211.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_40677908_astradb1');
define('DB_USER', 'if0_40677908');
define('DB_PASS', '23022Cm032');
define('JWT_SECRET', 'mydrive_secret_key_2025_change_in_production');

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

function getDB() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed']));
    }
}

// ============================================================================
// HEADERS & SETUP
// ============================================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('Asia/Kolkata');

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function getUserFromToken() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

    if (empty($token)) {
        return null;
    }

    $parts = explode(':', base64_decode($token));
    return isset($parts[0]) ? $parts[0] : null;
}

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit();
}

// ============================================================================
// ROUTING
// ============================================================================

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {

    // ========================================================================
    // AUTHENTICATION ENDPOINTS
    // ========================================================================

    case 'login':
        if ($method !== 'POST') sendError('Method not allowed', 405);

        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['userId'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($userId) || empty($password)) {
            sendError('User ID and password required');
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                sendError('Invalid credentials', 401);
            }

            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, 'LOGIN', 'User logged in', $_SERVER['REMOTE_ADDR']]);

            // Generate token
            $token = base64_encode($userId . ':' . time() . ':' . JWT_SECRET);

            sendResponse([
                'token' => $token,
                'userId' => $user['user_id'],
                'email' => $user['email'],
                'isAdmin' => (bool)$user['is_admin'],
                'mustResetPassword' => (bool)$user['must_reset_password'],
                'storageUsed' => (int)$user['storage_used'],
                'storageLimit' => (int)$user['storage_limit']
            ]);

        } catch(PDOException $e) {
            sendError('Server error', 500);
        }
        break;

    // ========================================================================
    case 'reset-password':
        if ($method !== 'POST') sendError('Method not allowed', 405);

        $userId = getUserFromToken();
        if (!$userId) sendError('Unauthorized', 401);

        $data = json_decode(file_get_contents('php://input'), true);
        $newPassword = $data['newPassword'] ?? '';

        if (strlen($newPassword) < 8) {
            sendError('Password must be at least 8 characters');
        }

        try {
            $db = getDB();
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $db->prepare("UPDATE users SET password_hash = ?, must_reset_password = 0, temp_password = NULL WHERE user_id = ?");
            $stmt->execute([$passwordHash, $userId]);

            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$userId, 'PASSWORD_RESET', 'User reset password']);

            sendResponse(['message' => 'Password reset successful']);

        } catch(PDOException $e) {
            sendError('Server error', 500);
        }
        break;

    // ========================================================================
    case 'create-user':
        if ($method !== 'POST') sendError('Method not allowed', 405);

        $adminId = getUserFromToken();
        if (!$adminId) sendError('Unauthorized', 401);

        // Check if admin
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT is_admin FROM users WHERE user_id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();

            if (!$admin || !$admin['is_admin']) {
                sendError('Admin access required', 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['userId'] ?? '';
            $email = $data['email'] ?? '';

            if (empty($userId) || empty($email)) {
                sendError('User ID and email required');
            }

            // Generate temporary password DD:MM:YY-HH:MM
            $tempPassword = date('d:m:y-H:i');
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            $stmt = $db->prepare("INSERT INTO users (user_id, email, password_hash, temp_password, must_reset_password) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$userId, $email, $passwordHash, $tempPassword]);

            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$adminId, 'USER_CREATED', "Created user $userId"]);

            sendResponse([
                'message' => 'User created successfully',
                'userId' => $userId,
                'tempPassword' => $tempPassword
            ], 201);

        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                sendError('User ID or email already exists', 409);
            }
            sendError('Server error', 500);
        }
        break;

    // ========================================================================
    // FILE MANAGEMENT ENDPOINTS
    // ========================================================================

    case 'list-files':
        if ($method !== 'GET') sendError('Method not allowed', 405);

        $userId = getUserFromToken();
        if (!$userId) sendError('Unauthorized', 401);

        $folderId = $_GET['folderId'] ?? null;

        try {
            $db = getDB();

            // Get files
            $stmt = $db->prepare("SELECT file_id, file_name, file_size, file_type, cloudinary_url, uploaded_at FROM files WHERE user_id = ? AND (folder_id = ? OR (folder_id IS NULL AND ? IS NULL)) ORDER BY uploaded_at DESC");
            $stmt->execute([$userId, $folderId, $folderId]);
            $files = $stmt->fetchAll();

            // Get folders
            $stmt = $db->prepare("SELECT folder_id, folder_name, created_at FROM folders WHERE user_id = ? AND (parent_folder_id = ? OR (parent_folder_id IS NULL AND ? IS NULL)) ORDER BY created_at DESC");
            $stmt->execute([$userId, $folderId, $folderId]);
            $folders = $stmt->fetchAll();

            sendResponse(['files' => $files, 'folders' => $folders]);

        } catch(PDOException $e) {
            sendError('Server error', 500);
        }
        break;

    // ========================================================================
    case 'upload-file':
        if ($method !== 'POST') sendError('Method not allowed', 405);

        $userId = getUserFromToken();
        if (!$userId) sendError('Unauthorized', 401);

        $data = json_decode(file_get_contents('php://input'), true);
        $fileData = $data['file'] ?? '';
        $fileName = $data['fileName'] ?? '';
        $fileSize = $data['fileSize'] ?? 0;
        $folderId = $data['folderId'] ?? null;

        if ($fileSize > 5 * 1024 * 1024) {
            sendError('File size exceeds 5MB limit');
        }

        try {
            $db = getDB();

            // Check storage quota
            $stmt = $db->prepare("SELECT storage_used, storage_limit FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user['storage_used'] + $fileSize > $user['storage_limit']) {
                sendError('Storage quota exceeded', 403);
            }

            // Generate file ID
            $fileId = bin2hex(random_bytes(16));
            $cloudinaryId = $fileId;

            // Store file (base64 data stored directly in database)
            $stmt = $db->prepare("INSERT INTO files (file_id, user_id, folder_id, cloudinary_id, file_name, file_size, file_type, cloudinary_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fileId, $userId, $folderId, $cloudinaryId, $fileName, $fileSize, 'unknown', $fileData]);

            // Update storage
            $stmt = $db->prepare("UPDATE users SET storage_used = storage_used + ? WHERE user_id = ?");
            $stmt->execute([$fileSize, $userId]);

            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$userId, 'FILE_UPLOAD', "Uploaded $fileName"]);

            sendResponse([
                'fileId' => $fileId,
                'fileName' => $fileName,
                'fileUrl' => $fileData,
                'message' => 'File uploaded successfully'
            ]);

        } catch(PDOException $e) {
            sendError('Upload failed', 500);
        }
        break;

    // ========================================================================
    case 'delete-file':
        if ($method !== 'DELETE') sendError('Method not allowed', 405);

        $userId = getUserFromToken();
        if (!$userId) sendError('Unauthorized', 401);

        $fileId = $_GET['fileId'] ?? '';

        try {
            $db = getDB();

            $stmt = $db->prepare("SELECT * FROM files WHERE file_id = ? AND user_id = ?");
            $stmt->execute([$fileId, $userId]);
            $file = $stmt->fetch();

            if (!$file) sendError('File not found', 404);

            $stmt = $db->prepare("DELETE FROM files WHERE file_id = ?");
            $stmt->execute([$fileId]);

            $stmt = $db->prepare("UPDATE users SET storage_used = storage_used - ? WHERE user_id = ?");
            $stmt->execute([$file['file_size'], $userId]);

            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$userId, 'FILE_DELETE', "Deleted {$file['file_name']}"]);

            sendResponse(['message' => 'File deleted successfully']);

        } catch(PDOException $e) {
            sendError('Delete failed', 500);
        }
        break;

    // ========================================================================
    // ADMIN ENDPOINTS
    // ========================================================================

    case 'list-users':
        if ($method !== 'GET') sendError('Method not allowed', 405);

        $adminId = getUserFromToken();
        if (!$adminId) sendError('Unauthorized', 401);

        try {
            $db = getDB();

            // Check if admin
            $stmt = $db->prepare("SELECT is_admin FROM users WHERE user_id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();

            if (!$admin || !$admin['is_admin']) {
                sendError('Admin access required', 403);
            }

            $stmt = $db->prepare("SELECT user_id, email, storage_used, storage_limit, is_admin, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll();

            sendResponse(['users' => $users]);

        } catch(PDOException $e) {
            sendError('Server error', 500);
        }
        break;

    // ========================================================================
    // DATABASE SETUP (INSTALL)
    // ========================================================================

    case 'install':
        try {
            $db = getDB();

            // Check if already installed
            $stmt = $db->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                sendResponse(['message' => 'Database already installed', 'status' => 'exists']);
            }

            // Create tables
            $db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    user_id VARCHAR(50) PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    temp_password VARCHAR(50) DEFAULT NULL,
                    must_reset_password BOOLEAN DEFAULT FALSE,
                    is_admin BOOLEAN DEFAULT FALSE,
                    storage_used BIGINT DEFAULT 0,
                    storage_limit BIGINT DEFAULT 52428800,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS folders (
                    folder_id VARCHAR(36) PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    folder_name VARCHAR(255) NOT NULL,
                    parent_folder_id VARCHAR(36) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS files (
                    file_id VARCHAR(36) PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    folder_id VARCHAR(36) DEFAULT NULL,
                    cloudinary_id VARCHAR(255) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_size BIGINT NOT NULL,
                    file_type VARCHAR(100),
                    cloudinary_url LONGTEXT NOT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create admin user (password: admin123)
            $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (user_id, email, password_hash, is_admin, storage_limit) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@mydrive.com', $adminHash, 1, 104857600]);

            sendResponse([
                'message' => 'Database installed successfully',
                'status' => 'installed',
                'tables' => ['users', 'files', 'folders', 'activity_logs'],
                'admin' => 'Username: admin, Password: admin123'
            ]);

        } catch(PDOException $e) {
            sendError('Installation failed: ' . $e->getMessage(), 500);
        }
        break;

    // ========================================================================
    default:
        sendError('Invalid action', 404);
}
?>
