<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get menu items for a restaurant
    if (!isset($_GET['restaurant_id'])) {
        sendResponse(['error' => 'Restaurant ID is required'], 400);
    }
    
    $restaurantId = $_GET['restaurant_id'];
    
    try {
        // Get menu items grouped by category
        $stmt = $db->prepare("
            SELECT mi.*, c.name as category_name, c.id as category_id
            FROM menu_items mi
            LEFT JOIN categories c ON mi.category_id = c.id
            WHERE mi.restaurant_id = :restaurant_id AND mi.is_available = TRUE
            ORDER BY c.name, mi.name
        ");
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->execute();
        $menuItems = $stmt->fetchAll();
        
        // Group by category
        $groupedMenu = [];
        foreach ($menuItems as $item) {
            $categoryName = $item['category_name'] ?? 'Other';
            if (!isset($groupedMenu[$categoryName])) {
                $groupedMenu[$categoryName] = [];
            }
            $groupedMenu[$categoryName][] = $item;
        }
        
        sendResponse(['success' => true, 'data' => $menuItems, 'grouped' => $groupedMenu]);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// If method not supported
sendResponse(['error' => 'Method not allowed'], 405);
?>
