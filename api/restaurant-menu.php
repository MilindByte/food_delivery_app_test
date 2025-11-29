<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// Check if restaurant is authenticated
if (!isset($_SESSION['restaurant_id']) || !isset($_SESSION['is_restaurant'])) {
    sendResponse(['error' => 'Unauthorized. Restaurant login required.'], 401);
}

$restaurantId = $_SESSION['restaurant_id'];

// GET - Get menu items
if ($method === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT mi.*, c.name as category_name
            FROM menu_items mi
            LEFT JOIN categories c ON mi.category_id = c.id
            WHERE mi.restaurant_id = :restaurant_id
            ORDER BY c.name, mi.name
        ");
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->execute();
        $menuItems = $stmt->fetchAll();
        
        sendResponse(['success' => true, 'data' => $menuItems, 'count' => count($menuItems)]);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// POST - Add new menu item
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || !isset($data['price'])) {
        sendResponse(['error' => 'Name and price are required'], 400);
    }
    
    $name = $data['name'];
    $price = $data['price'];
    $description = $data['description'] ?? null;
    $categoryId = $data['category_id'] ?? null;
    $imageUrl = $data['image_url'] ?? null;
    $isVeg = isset($data['is_veg']) ? $data['is_veg'] : true;
    $isAvailable = isset($data['is_available']) ? $data['is_available'] : true;
   
    try {
        $stmt = $db->prepare("
            INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg, is_available)
            VALUES (:restaurant_id, :category_id, :name, :description, :price, :image_url, :is_veg, :is_available)
        ");
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->bindParam(':category_id', $categoryId);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':image_url', $imageUrl);
        $stmt->bindParam(':is_veg', $isVeg);
        $stmt->bindParam(':is_available', $isAvailable);
        $stmt->execute();
        
        $itemId = $db->lastInsertId('menu_items_id_seq');
        
        sendResponse(['success' => true, 'message' => 'Menu item added', 'item_id' => $itemId], 201);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// PUT - Update menu item
elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['item_id'])) {
        sendResponse(['error' => 'Item ID is required'], 400);
    }
    
    $itemId = $data['item_id'];
    
    // Build update query dynamically based on provided fields
    $updates = [];
    $params = [':item_id' => $itemId, ':restaurant_id' => $restaurantId];
    
    if (isset($data['name'])) {
        $updates[] = "name = :name";
        $params[':name'] = $data['name'];
    }
    if (isset($data['description'])) {
        $updates[] = "description = :description";
        $params[':description'] = $data['description'];
    }
    if (isset($data['price'])) {
        $updates[] = "price = :price";
        $params[':price'] = $data['price'];
    }
    if (isset($data['category_id'])) {
        $updates[] = "category_id = :category_id";
        $params[':category_id'] = $data['category_id'];
    }
    if (isset($data['image_url'])) {
        $updates[] = "image_url = :image_url";
        $params[':image_url'] = $data['image_url'];
    }
    if (isset($data['is_veg'])) {
        $updates[] = "is_veg = :is_veg";
        $params[':is_veg'] = $data['is_veg'];
    }
    if (isset($data['is_available'])) {
        $updates[] = "is_available = :is_available";
        $params[':is_available'] = $data['is_available'];
    }
    
    if (empty($updates)) {
        sendResponse(['error' => 'No fields to update'], 400);
    }
    
    try {
        $query = "UPDATE menu_items SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :item_id AND restaurant_id = :restaurant_id";
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Menu item updated']);
        } else {
            sendResponse(['error' => 'Item not found or no changes made'], 404);
        }
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// DELETE - Remove menu item
elseif ($method === 'DELETE') {
    if (!isset($_GET['item_id'])) {
        sendResponse(['error' => 'Item ID is required'], 400);
    }
    
    $itemId = $_GET['item_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id = :item_id AND restaurant_id = :restaurant_id");
        $stmt->bindParam(':item_id', $itemId);
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Menu item deleted']);
        } else {
            sendResponse(['error' => 'Item not found'], 404);
        }
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// If method not supported
else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
?>
