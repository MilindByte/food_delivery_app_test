<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// POST - Handle login and registration
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'login') {
        // Rider login
        if (!isset($data['email']) || !isset($data['password'])) {
            sendResponse(['error' => 'Email and password are required'], 400);
        }
        
        $email = $data['email'];
        $password = $data['password'];
        
        try {
            $stmt = $db->prepare("SELECT * FROM riders WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $rider = $stmt->fetch();
            
            if (!$rider || !password_verify($password, $rider['password'])) {
                sendResponse(['error' => 'Invalid email or password'], 401);
            }
            
            // Set session for rider
            $_SESSION['rider_id'] = $rider['id'];
            $_SESSION['rider_name'] = $rider['name'];
            $_SESSION['is_rider'] = true;
            
            // Remove sensitive data from response
            unset($rider['password']);
            
            sendResponse([
                'success' => true,
                'message' => 'Login successful',
                'rider' => $rider
            ]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'register') {
        // Rider registration
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || !isset($data['phone'])) {
            sendResponse(['error' => 'Name, email, phone, and password are required'], 400);
        }
        
        $name = $data['name'];
        $email = $data['email'];
        $phone = $data['phone'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $vehicleType = $data['vehicle_type'] ?? 'bike';
        
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM riders WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                sendResponse(['error' => 'Email already registered'], 409);
            }
            
            // Insert new rider
            $stmt = $db->prepare("
                INSERT INTO riders (name, email, phone, password, vehicle_type, is_verified)
                VALUES (:name, :email, :phone, :password, :vehicle_type, 0)
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':vehicle_type', $vehicleType);
            $stmt->execute();
            
            $riderId = $db->lastInsertId('riders_id_seq');
            
            // Auto-login after registration
            $_SESSION['rider_id'] = $riderId;
            $_SESSION['rider_name'] = $name;
            $_SESSION['is_rider'] = true;
            
            sendResponse([
                'success' => true,
                'message' => 'Registration successful',
                'rider_id' => $riderId
            ], 201);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'logout') {
        // Logout rider
        unset($_SESSION['rider_id']);
        unset($_SESSION['rider_name']);
        unset($_SESSION['is_rider']);
        sendResponse(['success' => true, 'message' => 'Logged out successfully']);
    }
    
    else {
        sendResponse(['error' => 'Invalid action'], 400);
    }
}

// PUT - Toggle availability
elseif ($method === 'PUT' && $action === 'toggle-availability') {
    if (!isset($_SESSION['rider_id']) || !isset($_SESSION['is_rider'])) {
        sendResponse(['error' => 'Unauthorized'], 401);
    }
    
    $riderId = $_SESSION['rider_id'];
    
    try {
        $stmt = $db->prepare("UPDATE riders SET is_available = NOT is_available WHERE id = :id");
        $stmt->bindParam(':id', $riderId);
        $stmt->execute();
        
        // Get new status
        $stmt = $db->prepare("SELECT is_available FROM riders WHERE id = :id");
        $stmt->bindParam(':id', $riderId);
        $stmt->execute();
        $rider = $stmt->fetch();
        
        sendResponse([
            'success' => true,
            'is_available' => (bool)$rider['is_available']
        ]);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// GET - Check authentication status
elseif ($method === 'GET' && $action === 'check') {
    if (isset($_SESSION['rider_id']) && isset($_SESSION['is_rider'])) {
        try {
            $stmt = $db->prepare("SELECT id, name, email, phone, vehicle_type, is_available, is_verified, rating, total_deliveries FROM riders WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['rider_id']);
            $stmt->execute();
            $rider = $stmt->fetch();
            
            if ($rider) {
                sendResponse(['authenticated' => true, 'rider' => $rider]);
            } else {
                unset($_SESSION['rider_id']);
                unset($_SESSION['rider_name']);
                unset($_SESSION['is_rider']);
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
