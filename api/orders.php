<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get user's orders or single order
if ($method === 'GET') {
    $userId = requireAuth();
    
    if (isset($_GET['id'])) {
        // Get single order with items
        $orderId = $_GET['id'];
        
        try {
            // Get order details
            $stmt = $db->prepare("
                SELECT o.*, r.name as restaurant_name, r.image_url as restaurant_image,
                       u.name as user_name, u.email, u.phone
                FROM orders o
                JOIN restaurants r ON o.restaurant_id = r.id
                JOIN users u ON o.user_id = u.id
                WHERE o.id = :id AND o.user_id = :user_id
            ");
            $stmt->bindParam(':id', $orderId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $order = $stmt->fetch();
            
            if (!$order) {
                sendResponse(['error' => 'Order not found'], 404);
            }
            
            // Get order items
            $stmt = $db->prepare("
                SELECT oi.*, mi.name, mi.image_url, mi.is_veg
                FROM order_items oi
                JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE oi.order_id = :order_id
            ");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            $orderItems = $stmt->fetchAll();
            
            $order['items'] = $orderItems;
            
            sendResponse(['success' => true, 'data' => $order]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    } else {
        // Get all orders for user
        try {
            $stmt = $db->prepare("
                SELECT o.*, r.name as restaurant_name, r.image_url as restaurant_image,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN restaurants r ON o.restaurant_id = r.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.user_id = :user_id
                GROUP BY o.id, r.name, r.image_url
                ORDER BY o.created_at DESC
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            sendResponse(['success' => true, 'data' => $orders, 'count' => count($orders)]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

// POST - Place new order
elseif ($method === 'POST') {
    $userId = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['delivery_address']) || !isset($data['payment_method'])) {
        sendResponse(['error' => 'Delivery address and payment method are required'], 400);
    }
    
    $deliveryAddress = $data['delivery_address'];
    $paymentMethod = $data['payment_method'];
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get cart items
        $stmt = $db->prepare("
            SELECT c.*, mi.price, mi.restaurant_id
            FROM cart c
            JOIN menu_items mi ON c.menu_item_id = mi.id
            WHERE c.user_id = :user_id
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $cartItems = $stmt->fetchAll();
        
        if (empty($cartItems)) {
            $db->rollBack();
            sendResponse(['error' => 'Cart is empty'], 400);
        }
        
        // Check all items are from same restaurant
        $restaurantId = $cartItems[0]['restaurant_id'];
        foreach ($cartItems as $item) {
            if ($item['restaurant_id'] != $restaurantId) {
                $db->rollBack();
                sendResponse(['error' => 'All items must be from the same restaurant'], 400);
            }
        }
        
        // Calculate total
        $totalAmount = 0;
        foreach ($cartItems as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }
        
        // Add tax and delivery fee
        $tax = $totalAmount * 0.05;
        $deliveryFee = 40;
        $totalAmount = $totalAmount + $tax + $deliveryFee;
        
        // Create order
        $stmt = $db->prepare("
            INSERT INTO orders (user_id, restaurant_id, total_amount, delivery_address, payment_method, status)
            VALUES (:user_id, :restaurant_id, :total_amount, :delivery_address, :payment_method, 'pending')
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':restaurant_id', $restaurantId);
        $stmt->bindParam(':total_amount', $totalAmount);
        $stmt->bindParam(':delivery_address', $deliveryAddress);
        $stmt->bindParam(':payment_method', $paymentMethod);
        $stmt->execute();
        
        $orderId = $db->lastInsertId('orders_id_seq');
        
        // Add order items
        $stmt = $db->prepare("
            INSERT INTO order_items (order_id, menu_item_id, quantity, price)
            VALUES (:order_id, :menu_item_id, :quantity, :price)
        ");
        
        foreach ($cartItems as $item) {
            $stmt->bindParam(':order_id', $orderId);
            $stmt->bindParam(':menu_item_id', $item['menu_item_id']);
            $stmt->bindParam(':quantity', $item['quantity']);
            $stmt->bindParam(':price', $item['price']);
            $stmt->execute();
        }
        
        // Clear cart
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        sendResponse([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $orderId,
            'total_amount' => round($totalAmount, 2)
        ], 201);
    } catch(PDOException $e) {
        $db->rollBack();
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// If method not supported
else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
?>
