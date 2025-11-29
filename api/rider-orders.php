<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Check if rider is authenticated
if (!isset($_SESSION['rider_id']) || !isset($_SESSION['is_rider'])) {
    sendResponse(['error' => 'Unauthorized. Rider login required.'], 401);
}

$riderId = $_SESSION['rider_id'];

// GET - Get orders
if ($method === 'GET') {
    if ($action === 'available') {
        // Get available orders (confirmed but not assigned to any rider)
        try {
            $stmt = $db->prepare("
                SELECT o.*, 
                       r.name as restaurant_name, 
                       r.address as restaurant_address,
                       u.name as customer_name,
                       u.phone as customer_phone
                FROM orders o
                JOIN restaurants r ON o.restaurant_id = r.id
                JOIN users u ON o.user_id = u.id
                WHERE o.status = 'ready' AND o.rider_id IS NULL
                ORDER BY o.created_at ASC
            ");
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $orders]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'assigned') {
        // Get rider's assigned orders
        try {
            $stmt = $db->prepare("
                SELECT o.*,
                       r.name as restaurant_name,
                       r.address as restaurant_address,
                       u.name as customer_name,
                       u.phone as customer_phone,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN restaurants r ON o.restaurant_id = r.id
                JOIN users u ON o.user_id = u.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.rider_id = :rider_id 
                AND o.status NOT IN ('delivered', 'cancelled')
                GROUP BY o.id, r.name, r.address, u.name, u.phone, o.created_at
                ORDER BY o.created_at DESC
            ");
            $stmt->bindParam(':rider_id', $riderId);
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $orders]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'history') {
        // Get delivery history
        try {
            $stmt = $db->prepare("
                SELECT o.*,
                       r.name as restaurant_name,
                       u.name as customer_name,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN restaurants r ON o.restaurant_id = r.id
                JOIN users u ON o.user_id = u.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.rider_id = :rider_id AND o.status = 'delivered'
                GROUP BY o.id, r.name, u.name, o.updated_at
                ORDER BY o.updated_at DESC
                LIMIT 50
            ");
            $stmt->bindParam(':rider_id', $riderId);
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $orders]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    elseif ($action === 'earnings') {
        // Get rider earnings
        try {
            // Today's earnings
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(delivery_fee), 0) as total
                FROM orders 
                WHERE rider_id = :rider_id 
                AND status = 'delivered'
                AND DATE(updated_at) = CURRENT_DATE
            ");
            $stmt->bindParam(':rider_id', $riderId);
            $stmt->execute();
            $today = $stmt->fetch()['total'];
            
            // Week's earnings
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(delivery_fee), 0) as total
                FROM orders 
                WHERE rider_id = :rider_id 
                AND status = 'delivered'
                AND updated_at >= DATE_TRUNC('week', CURRENT_DATE)
            ");
            $stmt->bindParam(':rider_id', $riderId);
            $stmt->execute();
            $week = $stmt->fetch()['total'];
            
            // Month's earnings
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(delivery_fee), 0) as total
                FROM orders 
                WHERE rider_id = :rider_id 
                AND status = 'delivered'
                AND updated_at >= DATE_TRUNC('month', CURRENT_DATE)
            ");
            $stmt->bindParam(':rider_id', $riderId);
            $stmt->execute();
            $month = $stmt->fetch()['total'];
            
            // Total earnings
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(delivery_fee), 0) as total
                FROM orders 
                WHERE rider_id = :rider_id 
                AND status = 'delivered'
            ");
            $stmt->bindParam(':rider_id', $riderId);
            $stmt->execute();
            $total = $stmt->fetch()['total'];
            
            sendResponse([
                'success' => true, 
                'summary' => [
                    'today_earnings' => $today,
                    'week_earnings' => $week,
                    'month_earnings' => $month,
                    'total_earnings' => $total
                ]
            ]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    else {
        sendResponse(['error' => 'Invalid action'], 400);
    }
}

// POST - Accept order
elseif ($method === 'POST' && $action === 'accept') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id'])) {
        sendResponse(['error' => 'Order ID is required'], 400);
    }
    
    $orderId = $data['order_id'];
    
    try {
        // Check if order is still available
        $stmt = $db->prepare("SELECT rider_id, status FROM orders WHERE id = :id");
        $stmt->bindParam(':id', $orderId);
        $stmt->execute();
        $order = $stmt->fetch();
        
        if (!$order) {
            sendResponse(['error' => 'Order not found'], 404);
        }
        
        if ($order['rider_id']) {
            sendResponse(['error' => 'Order already assigned to another rider'], 409);
        }
        
        if ($order['status'] !== 'confirmed' && $order['status'] !== 'ready') {
            sendResponse(['error' => 'Order is not available for pickup'], 400);
        }
        
        // Assign order to rider and update status
        // If order is already ready, keep it ready. If confirmed, move to preparing.
        $newStatus = ($order['status'] === 'ready') ? 'ready' : 'preparing';
        
        $stmt = $db->prepare("
            UPDATE orders 
            SET rider_id = :rider_id, status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND rider_id IS NULL
        ");
        $stmt->bindParam(':rider_id', $riderId);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $orderId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Order accepted successfully']);
        } else {
            sendResponse(['error' => 'Failed to accept order'], 500);
        }
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// PUT - Update order status
elseif ($method === 'PUT' && $action === 'update-status') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id']) || !isset($data['status'])) {
        sendResponse(['error' => 'Order ID and status are required'], 400);
    }
    
    $orderId = $data['order_id'];
    $status = $data['status'];
    
    // Validate status transitions
    $validStatuses = ['preparing', 'ready', 'on_the_way', 'delivered'];
    if (!in_array($status, $validStatuses)) {
        sendResponse(['error' => 'Invalid status'], 400);
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND rider_id = :rider_id
        ");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':rider_id', $riderId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // If delivered, increment rider's total deliveries
            if ($status === 'delivered') {
                $stmt = $db->prepare("UPDATE riders SET total_deliveries = total_deliveries + 1 WHERE id = :id");
                $stmt->bindParam(':id', $riderId);
                $stmt->execute();
            }
            
            sendResponse(['success' => true, 'message' => 'Order status updated']);
        } else {
            sendResponse(['error' => 'Order not found or not assigned to you'], 404);
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
