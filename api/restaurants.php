<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get restaurants list or single restaurant
    if (isset($_GET['id'])) {
        // Get single restaurant with menu items
        $id = $_GET['id'];
        
        try {
            // Get restaurant details
            $stmt = $db->prepare("
                SELECT * FROM restaurants 
                WHERE id = :id AND is_active = TRUE
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $restaurant = $stmt->fetch();
            
            if (!$restaurant) {
                sendResponse(['error' => 'Restaurant not found'], 404);
            }
            
            // Get menu items for this restaurant
            $stmt = $db->prepare("
                SELECT mi.*, c.name as category_name 
                FROM menu_items mi
                LEFT JOIN categories c ON mi.category_id = c.id
                WHERE mi.restaurant_id = :restaurant_id AND mi.is_available = TRUE
                ORDER BY c.name, mi.name
            ");
            $stmt->bindParam(':restaurant_id', $id);
            $stmt->execute();
            $menuItems = $stmt->fetchAll();
            
            $restaurant['menu_items'] = $menuItems;
            
            sendResponse(['success' => true, 'data' => $restaurant]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    } else {
        // Get all restaurants with filters
        $filters = [];
        $params = [];
        
        // Build WHERE clause based on filters
        $where = "WHERE is_active = TRUE";
        
        if (isset($_GET['rating'])) {
            $where .= " AND rating >= :rating";
            $params[':rating'] = $_GET['rating'];
        }
        
        if (isset($_GET['pure_veg']) && $_GET['pure_veg'] == '1') {
            $where .= " AND is_pure_veg = TRUE";
        }
        
        if (isset($_GET['cuisine'])) {
            $where .= " AND cuisine_types LIKE :cuisine";
            $params[':cuisine'] = '%' . $_GET['cuisine'] . '%';
        }
        
        if (isset($_GET['search'])) {
            $where .= " AND (name LIKE :search OR cuisine_types LIKE :search2 OR description LIKE :search3)";
            $search = '%' . $_GET['search'] . '%';
            $params[':search'] = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM restaurants $where ORDER BY rating DESC");
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $restaurants = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $restaurants, 'count' => count($restaurants)]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

// If method not supported
sendResponse(['error' => 'Method not allowed'], 405);
?>
