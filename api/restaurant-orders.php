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

// GET - Get orders for restaurant
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Get single order details
        $orderId = $_GET['id'];
        
        try {
            $stmt = $db->prepare("
                SELECT o.*, u.name as customer_name, u.email, u. phone
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = :id AND o.restaurant_id = :restaurant_id
            ");
            $stmt->bindParam(':id', $orderId);
            $stmt->bindParam(':restaurant_id', $restaurantId);
            $stmt->execute();
            $order = $stmt->fetch();
            
            if (!$order) {
                sendResponse(['error' => 'Order not found'], 404);
            }
            
            // Get order itemsinstallation
            $stmt = $db->prepare("
                SELECT oi.*, mi.name, mi.image_url, mi.is_veg
                FROM order_items oi
                JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE oi.order_id = :order_id
            ");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            $order['items'] = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $order]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    } else {
        // Get all orders for restaurant
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        try {
            $query = "
                SELECT o.*, u.name as customer_name, u.phone,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN users u ON o.user_id = u.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.restaurant_id = :restaurant_id
            ";
            
            if ($status) {
                $query .= " AND o.status = :status";
            }
            
            $query .= " GROUP BY o.id, u.name, u.phone ORDER BY o.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurantId);
            if ($status) {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $orders, 'count' => count($orders)]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

// PUT - Update order status
elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id']) || !isset($data['status'])) {
        sendResponse(['error' => 'Order ID and status are required'], 400);
    }
    
    $orderId = $data['order_id'];
    $status = $data['status'];
    
    // Validate status - restaurants can only set these statuses
    $validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        sendResponse(['error' => 'Invalid status. Restaurants can only set: pending, confirmed, preparing, ready, or cancelled'], 400);
    }
    
    try {
        // Get current status first
        $stmt = $db->prepare("SELECT status FROM orders WHERE id = :id AND restaurant_id = :restaurant_id");
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->execute();
        $currentOrder = $stmt->fetch();

        if (!$currentOrder) {
            sendResponse(['error' => 'Order not found'], 404);
        }

        $currentStatus = $currentOrder['status'];

        // Define allowed transitions
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['cancelled'], // Only rider can move from ready to on_the_way
            'on_the_way' => [], // Only rider can update
            'delivered' => [], // Final status
            'cancelled' => [] // Final status
        ];

        // Allow same status update (idempotency) or valid transition
        if ($currentStatus !== $status && !in_array($status, $allowedTransitions[$currentStatus] ?? [])) {
             sendResponse(['error' => "Invalid status transition. Cannot change from '$currentStatus' to '$status'"], 400);
        }

        $stmt = $db->prepare("
            UPDATE orders 
            SET status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND restaurant_id = :restaurant_id
        ");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Order status updated']);
        } else {
            sendResponse(['error' => 'Order not found or no changes made'], 404);
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
