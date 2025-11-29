<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// POST - Handle login and register
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'register') {
        // Register new user
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
            sendResponse(['error' => 'Name, email and password are required'], 400);
        }
        
        $name = $data['name'];
        $email = $data['email'];
        $password = $data['password'];
        $phone = isset($data['phone']) ? $data['phone'] : null;
        $address = isset($data['address']) ? $data['address'] : null;
        
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                sendResponse(['error' => 'Email already registered'], 400);
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, phone, address)
                VALUES (:name, :email, :password, :phone, :address)
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->execute();
            
            $userId = $db->lastInsertId('users_id_seq');
            
            // Set session
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            sendResponse([
                'success' => true,
                'message' => 'Registration successful',
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address
                ]
            ], 201);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'login') {
        // Login user
        if (!isset($data['email']) || !isset($data['password'])) {
            sendResponse(['error' => 'Email and password are required'], 400);
        }
        
        $email = $data['email'];
        $password = $data['password'];
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                sendResponse(['error' => 'Invalid email or password'], 401);
            }
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            // Remove password from response
            unset($user['password']);
            
            sendResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'logout') {
        // Logout user
        session_destroy();
        sendResponse(['success' => true, 'message' => 'Logged out successfully']);
    }
    
    else {
        sendResponse(['error' => 'Invalid action'], 400);
    }
}

// GET - Check authentication status
elseif ($method === 'GET' && $action === 'check') {
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $db->prepare("SELECT id, name, email, phone, address FROM users WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user) {
                sendResponse(['authenticated' => true, 'user' => $user]);
            } else {
                session_destroy();
                sendResponse(['authenticated' => false]);
            }
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    } else {
        sendResponse(['authenticated' => false]);
    }
}

// If method not supported
else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
?>
