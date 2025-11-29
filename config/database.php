<?php
// Database configuration for PostgreSQL
define('DB_HOST', 'pg-3de34480-fooddeliverytest1.g.aivencloud.com');
define('DB_PORT', '17948'); // PostgreSQL default port
define('DB_USER', 'avnadmin');
define('DB_PASS', 'AVNS_fi_u6qVvtO19a7dgU_-');
define('DB_NAME', 'food_delivery');

// Create database connection
class Database {
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            // PostgreSQL PDO connection
            $this->conn = new PDO(
                'pgsql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbname,
                $this->user,
                $this->pass
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }

        return $this->conn;
    }
}

// Enable CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session for authentication
session_start();

// Helper function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Helper function to get current user ID from session
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Helper function to check if user is authenticated
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['error' => 'Unauthorized. Please login.'], 401);
    }
    return $_SESSION['user_id'];
}
?>

