<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get user's cart
if ($method === 'GET') {
    $userId = requireAuth();
    
    try {
        $stmt = $db->prepare("
            SELECT c.*, mi.name, mi.price, mi.image_url, mi.is_veg, 
                   r.name as restaurant_name, r.id as restaurant_id,
                   (mi.price * c.quantity) as item_total
            FROM cart c
            JOIN menu_items mi ON c.menu_item_id = mi.id
            JOIN restaurants r ON mi.restaurant_id = r.id
            WHERE c.user_id = :user_id
            ORDER BY c.created_at DESC
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $cartItems = $stmt->fetchAll();
        
        // Calculate totals
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item['item_total'];
        }
        
        $tax = $subtotal * 0.05; // 5% tax
        $deliveryFee = $subtotal > 0 ? 40 : 0;
        $total = $subtotal + $tax + $deliveryFee;
        
        sendResponse([
            'success' => true,
            'data' => $cartItems,
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'tax' => round($tax, 2),
                'delivery_fee' => $deliveryFee,
                'total' => round($total, 2),
                'item_count' => count($cartItems)
            ]
        ]);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// POST - Add item to cart
elseif ($method === 'POST') {
    $userId = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['menu_item_id']) || !isset($data['quantity'])) {
        sendResponse(['error' => 'Menu item ID and quantity are required'], 400);
    }
    
    $menuItemId = $data['menu_item_id'];
    $quantity = $data['quantity'];
    
    try {
        // Check if item already exists in cart
        $stmt = $db->prepare("
            SELECT * FROM cart 
            WHERE user_id = :user_id AND menu_item_id = :menu_item_id
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':menu_item_id', $menuItemId);
        $stmt->execute();
        $existingItem = $stmt->fetch();
        
        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            $stmt = $db->prepare("
                UPDATE cart 
                SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->bindParam(':quantity', $newQuantity);
            $stmt->bindParam(':id', $existingItem['id']);
            $stmt->execute();
            
            sendResponse(['success' => true, 'message' => 'Cart updated', 'action' => 'updated']);
        } else {
            // Insert new item
            $stmt = $db->prepare("
                INSERT INTO cart (user_id, menu_item_id, quantity)
                VALUES (:user_id, :menu_item_id, :quantity)
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':menu_item_id', $menuItemId);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->execute();
            
            sendResponse(['success' => true, 'message' => 'Item added to cart', 'action' => 'added']);
        }
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// PUT - Update cart item quantity
elseif ($method === 'PUT') {
    $userId = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['cart_id']) || !isset($data['quantity'])) {
        sendResponse(['error' => 'Cart ID and quantity are required'], 400);
    }
    
    $cartId = $data['cart_id'];
    $quantity = $data['quantity'];
    
    try {
        $stmt = $db->prepare("
            UPDATE cart 
            SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':id', $cartId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Cart item updated']);
        } else {
            sendResponse(['error' => 'Cart item not found'], 404);
        }
    } catch(PDOException $e) {
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// DELETE - Remove item from cart
elseif ($method === 'DELETE') {
    $userId = requireAuth();
    
    if (isset($_GET['cart_id'])) {
        $cartId = $_GET['cart_id'];
        
        try {
            $stmt = $db->prepare("
                DELETE FROM cart 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->bindParam(':id', $cartId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                sendResponse(['success' => true, 'message' => 'Item removed from cart']);
            } else {
                sendResponse(['error' => 'Cart item not found'], 404);
            }
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    } elseif (isset($_GET['clear'])) {
        // Clear entire cart
        try {
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            sendResponse(['success' => true, 'message' => 'Cart cleared', 'deleted_count' => $stmt->rowCount()]);
        } catch(PDOException $e) {
            sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    } else {
        sendResponse(['error' => 'Cart ID is required'], 400);
    }
}

// If method not supported
else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
?>
