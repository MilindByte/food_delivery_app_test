<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// POST - Handle login
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'login') {
        // Restaurant login
        if (!isset($data['username']) || !isset($data['password'])) {
            sendResponse(['error' => 'Username and password are required'], 400);
        }
        
        $username = $data['username'];
        $password = $data['password'];
        
        try {
            $stmt = $db->prepare("SELECT * FROM restaurants WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $restaurant = $stmt->fetch();
            
            if (!$restaurant || !password_verify($password, $restaurant['password'])) {
                sendResponse(['error' => 'Invalid username or password'], 401);
            }
            
            // Set session for restaurant
            $_SESSION['restaurant_id'] = $restaurant['id'];
            $_SESSION['restaurant_name'] = $restaurant['name'];
            $_SESSION['is_restaurant'] = true;
            
            // Remove sensitive data from response
            unset($restaurant['password']);
            unset($restaurant['username']);
            
            sendResponse([
                'success' => true,
                'message' => 'Login successful',
                'restaurant' => $restaurant
            ]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'register') {
        // Restaurant registration
        if (!isset($data['name']) || !isset($data['username']) || !isset($data['password'])) {
            sendResponse(['error' => 'Restaurant name, username, and password are required'], 400);
        }
        
        $name = $data['name'];
        $username = $data['username'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $description = $data['description'] ?? null;
        $address = $data['address'] ?? null;
        $cuisineTypes = $data['cuisine_types'] ?? null;
        $deliveryTime = $data['delivery_time'] ?? '30-45 min';
        $priceForOne = $data['price_for_one'] ?? 200;
        $isPureVeg = isset($data['is_pure_veg']) ? $data['is_pure_veg'] : false;
        
        try {
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM restaurants WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                sendResponse(['error' => 'Username already exists'], 409);
            }
            
            // Insert new restaurant
            $stmt = $db->prepare("
                INSERT INTO restaurants (name, username, password, description, address, cuisine_types, delivery_time, price_for_one, is_pure_veg, is_active)
                VALUES (:name, :username, :password, :description, :address, :cuisine_types, :delivery_time, :price_for_one, :is_pure_veg, TRUE)
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':cuisine_types', $cuisineTypes);
            $stmt->bindParam(':delivery_time', $deliveryTime);
            $stmt->bindParam(':price_for_one', $priceForOne);
            $stmt->bindParam(':is_pure_veg', $isPureVeg, PDO::PARAM_BOOL);
            $stmt->execute();
            
            $restaurantId = $db->lastInsertId('restaurants_id_seq');
            
            // Auto-login after registration
            $_SESSION['restaurant_id'] = $restaurantId;
            $_SESSION['restaurant_name'] = $name;
            $_SESSION['is_restaurant'] = true;
            
            sendResponse([
                'success' => true,
                'message' => 'Restaurant registered successfully',
                'restaurant_id' => $restaurantId
            ], 201);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'logout') {
        // Logout restaurant
        unset($_SESSION['restaurant_id']);
        unset($_SESSION['restaurant_name']);
        unset($_SESSION['is_restaurant']);
        sendResponse(['success' => true, 'message' => 'Logged out successfully']);
    }
    
    else {
        sendResponse(['error' => 'Invalid action'], 400);
    }
}

// GET - Check authentication status
elseif ($method === 'GET' && $action === 'check') {
    if (isset($_SESSION['restaurant_id']) && isset($_SESSION['is_restaurant'])) {
        try {
            $stmt = $db->prepare("SELECT id, name, description, image_url, rating, address FROM restaurants WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['restaurant_id']);
            $stmt->execute();
            $restaurant = $stmt->fetch();
            
            if ($restaurant) {
                sendResponse(['authenticated' => true, 'restaurant' => $restaurant]);
            } else {
                unset($_SESSION['restaurant_id']);
                unset($_SESSION['restaurant_name']);
                unset($_SESSION['is_restaurant']);
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
