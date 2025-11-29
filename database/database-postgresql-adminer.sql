-- Food Delivery App PostgreSQL Database Schema
-- Optimized for Adminer Import
-- NOTE: Make sure to select the correct database in Adminer BEFORE importing

-- Create ENUM types first
DROP TYPE IF EXISTS vehicle_type_enum CASCADE;
DROP TYPE IF EXISTS order_status_enum CASCADE;
DROP TYPE IF EXISTS payment_method_enum CASCADE;

CREATE TYPE vehicle_type_enum AS ENUM ('bike', 'scooter', 'car');
CREATE TYPE order_status_enum AS ENUM ('pending', 'confirmed', 'preparing', 'ready', 'on_the_way', 'delivered', 'cancelled');
CREATE TYPE payment_method_enum AS ENUM ('cod', 'card', 'upi', 'wallet');

-- Function for updating timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
DROP TABLE IF EXISTS cart CASCADE;
DROP TABLE IF EXISTS menu_items CASCADE;
DROP TABLE IF EXISTS riders CASCADE;
DROP TABLE IF EXISTS restaurants CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Categories table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Restaurants table
CREATE TABLE restaurants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    rating DECIMAL(2,1) DEFAULT 0.0,
    delivery_time VARCHAR(20),
    cuisine_types VARCHAR(255),
    price_for_one INT,
    discount_text VARCHAR(100),
    address TEXT,
    is_pure_veg BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_restaurants_updated_at BEFORE UPDATE ON restaurants
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Menu items table
CREATE TABLE menu_items (
    id SERIAL PRIMARY KEY,
    restaurant_id INT NOT NULL,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    is_veg BOOLEAN DEFAULT TRUE,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TRIGGER update_menu_items_updated_at BEFORE UPDATE ON menu_items
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Cart table
CREATE TABLE cart (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    UNIQUE (user_id, menu_item_id)
);

CREATE TRIGGER update_cart_updated_at BEFORE UPDATE ON cart
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Riders table
CREATE TABLE riders (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    vehicle_type vehicle_type_enum DEFAULT 'bike',
    is_available BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    rating DECIMAL(2,1) DEFAULT 0.0,
    total_deliveries INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_riders_updated_at BEFORE UPDATE ON riders
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Orders table
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    rider_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_address TEXT NOT NULL,
    status order_status_enum DEFAULT 'pending',
    payment_method payment_method_enum DEFAULT 'cod',
    delivery_fee DECIMAL(10,2) DEFAULT 40.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
);

CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Order items table
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- Insert sample data

-- Sample users (password is 'password123' hashed with bcrypt)
INSERT INTO users (name, email, password, phone, address) VALUES
('User 1', 'user1@example.com', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6', '+91 9876543210', '123 Main Street, Mumbai'),
('User 2', 'user2@example.com', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6', '+91 9876543211', '456 Park Avenue, Mumbai');

-- Sample categories
INSERT INTO categories (name, image_url) VALUES
('Biryani', 'https://b.zmtcdn.com/data/dish_images/d19a31d42d5913ff129cafd7cec772f51634721372.png'),
('Burger', 'https://b.zmtcdn.com/data/dish_images/ccb7dc2ba2b054419f805da7f05704471634886169.png'),
('Chicken', 'https://b.zmtcdn.com/data/dish_images/197987b7ebcd1ee08f8c25ea4e77e20f1634731334.png'),
('Cake', 'https://b.zmtcdn.com/data/dish_images/d5ab931c8c239271de45e1c159af94311634805744.png'),
('Rolls', 'https://b.zmtcdn.com/data/dish_images/c2f22c42f7ba90d81440a88449f4e5891634806087.png'),
('Paneer', 'https://b.zmtcdn.com/data/dish_images/e44c42ff4b60b025225c8691ef9735b11635781903.png'),
('Pizza', 'https://b.zmtcdn.com/data/o2_assets/d0bd7c9405ac87f6aa65e31fe55800941632716575.png'),
('Pasta', 'https://b.zmtcdn.com/data/dish_images/e44c42ff4b60b025225c8691ef9735b11635781903.png');

-- Sample restaurants (password is 'admin123' hashed with bcrypt)
INSERT INTO restaurants (name, description, image_url, rating, delivery_time, cuisine_types, price_for_one, discount_text, address, is_pure_veg, username, password) VALUES
('Burger King', 'Home of the Whopper', 'https://b.zmtcdn.com/data/pictures/chains/1/18412861/b0c163574235286542c3d5268c784466_o2_featured_v2.jpg', 4.2, '42 min', 'Burger, Fast Food', 150, '50% OFF', 'Connaught Place, Mumbai', FALSE, 'burgerking', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6'),
('Dominos Pizza', 'Pizza Delivery Experts', 'https://b.zmtcdn.com/data/pictures/chains/2/308022/d551f8d42b588ba17d1a49c71954314c_o2_featured_v2.jpg', 4.1, '35 min', 'Pizza, Fast Food', 200, 'Flat ₹100 OFF', 'Karol Bagh, Mumbai', FALSE, 'dominos', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6'),
('KFC', 'Finger Lickin Good', 'https://b.zmtcdn.com/data/pictures/chains/8/310088/2434635e98828a16d86b57e7a6729b86_o2_featured_v2.jpg', 4.0, '55 min', 'Burger, Fast Food, Biryani', 180, '60% OFF', 'Rajouri Garden, Mumbai', FALSE, 'kfc', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6'),
('Biryani Blues', 'Authentic Biryani Experience', 'https://b.zmtcdn.com/data/pictures/3/18614743/f854e2c6d84b7e6a0c5f8e4d3b9c7a1e_o2_featured_v2.jpg', 4.5, '45 min', 'Biryani, Mughlai, North Indian', 250, '30% OFF', 'Safdarjung, Mumbai', FALSE, 'biryaniblues', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6'),
('Haldirams', 'Taste of Tradition', 'https://b.zmtcdn.com/data/pictures/chains/5/18605/f3a3a6f9d3b7e6a0c5f8e4d3b9c7a1e5_o2_featured_v2.jpg', 4.3, '30 min', 'North Indian, South Indian, Chinese', 200, '20% OFF', 'Chandni Chowk, Mumbai', TRUE, 'haldirams', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6'),
('Subway', 'Eat Fresh', 'https://b.zmtcdn.com/data/pictures/chains/9/18429/f3a3a6f9d3b7e6a0c5f8e4d3b9c7a1e6_o2_featured_v2.jpg', 4.0, '25 min', 'Sandwich, Fast Food, Healthy', 150, 'Buy 1 Get 1', 'Nehru Place, Mumbai', FALSE, 'subway', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6');

-- Sample menu items for Burger King
INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg) VALUES
(1, 2, 'Whopper', 'Signature flame-grilled burger', 199.00, 'https://media-cdn.tripadvisor.com/media/photo-s/13/37/9d/49/whopper.jpg', FALSE),
(1, 2, 'Chicken Royale', 'Crispy chicken burger', 179.00, 'https://burgerking.co.in/static/media/ChickenRoyale.jpg', FALSE),
(1, 2, 'Veg Whopper', 'Vegetarian whopper delight', 169.00, 'https://burgerking.co.in/static/media/VegWhopper.jpg', TRUE),
(1, 2, 'Double Whopper', 'Double the beef patties', 289.00, 'https://burgerking.co.in/static/media/DoubleWhopper.jpg', FALSE),
(1, NULL, 'French Fries', 'Crispy golden fries', 99.00, 'https://media-cdn.tripadvisor.com/media/photo-s/0e/cc/0a/dc/french-fries.jpg', TRUE);

-- Sample menu items for Dominos Pizza
INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg) VALUES
(2, 7, 'Margherita Pizza', 'Classic cheese pizza', 299.00, 'https://api.pizzahut.io/v1/content/en-in/in-1/images/pizza/margherita.f8bc138ad504482da2f1813e686a362e.1.jpg', TRUE),
(2, 7, 'Pepperoni Pizza', 'Loaded with pepperoni', 399.00, 'https://api.pizzahut.io/v1/content/en-in/in-1/images/pizza/pepperoni.f8bc138ad504482da2f1813e686a362e.1.jpg', FALSE),
(2, 7, 'Veggie Paradise', 'Loaded with vegetables', 349.00, 'https://api.pizzahut.io/v1/content/en-in/in-1/images/pizza/veggie-paradise.f8bc138ad504482da2f1813e686a362e.1.jpg', TRUE),
(2, 7, 'Chicken Dominator', 'Triple chicken toppings', 499.00, 'https://api.pizzahut.io/v1/content/en-in/in-1/images/pizza/chicken-dominator.f8bc138ad504482da2f1813e686a362e.1.jpg', FALSE),
(2, 8, 'Pasta Italiano', 'Creamy white sauce pasta', 199.00, 'https://media-cdn.tripadvisor.com/media/photo-s/13/ef/1a/7f/pasta.jpg', TRUE);

-- Sample menu items for KFC
INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg) VALUES
(3, 3, 'Hot & Crispy Chicken', '2 pieces of signature chicken', 219.00, 'https://orderserv-kfc-assets.yum.com/15895bb59f7b4bb588ee933f8cd5344a/images/items/xl/A-32408-0.jpg', FALSE),
(3, 3, 'Zinger Burger', 'Spicy chicken burger', 199.00, 'https://orderserv-kfc-assets.yum.com/15895bb59f7b4bb588ee933f8cd5344a/images/items/xl/A-32466-0.jpg', FALSE),
(3, 3, 'Popcorn Chicken', 'Bite-sized chicken pieces', 149.00, 'https://orderserv-kfc-assets.yum.com/15895bb59f7b4bb588ee933f8cd5344a/images/items/xl/A-32482-0.jpg', FALSE),
(3, 2, 'Veg Zinger', 'Crispy veg patty burger', 179.00, 'https://orderserv-kfc-assets.yum.com/15895bb59f7b4bb588ee933f8cd5344a/images/items/xl/A-32355-0.jpg', TRUE),
(3, 1, 'Chicken Biryani Bucket', 'Serves 2-3 people', 399.00, 'https://orderserv-kfc-assets.yum.com/15895bb59f7b4bb588ee933f8cd5344a/images/items/xl/A-32548-0.jpg', FALSE);

-- Sample menu items for Biryani Blues
INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg) VALUES
(4, 1, 'Hyderabadi Chicken Biryani', 'Authentic Hyderabadi style', 299.00, 'https://www.licious.in/blog/wp-content/uploads/2020/12/Hyderabadi-Chicken-Biryani.jpg', FALSE),
(4, 1, 'Lucknowi Mutton Biryani', 'Awadhi style biryani', 399.00, 'https://www.licious.in/blog/wp-content/uploads/2020/12/Mutton-Biryani.jpg', FALSE),
(4, 1, 'Veg Dum Biryani', 'Vegetarian biryani', 249.00, 'https://www.licious.in/blog/wp-content/uploads/2020/12/Veg-Biryani.jpg', TRUE),
(4, 3, 'Chicken Tikka', 'Grilled chicken pieces', 279.00, 'https://www.licious.in/blog/wp-content/uploads/2020/12/Chicken-Tikka.jpg', FALSE),
(4, NULL, 'Raita', 'Cooling yogurt side', 49.00, 'https://www.licious.in/blog/wp-content/uploads/2020/12/Raita.jpg', TRUE);

-- Sample menu items for Haldirams
INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg) VALUES
(5, 6, 'Paneer Tikka', 'Grilled cottage cheese', 249.00, 'https://www.vegrecipesofindia.com/wp-content/uploads/2020/06/paneer-tikka-1.jpg', TRUE),
(5, NULL, 'Chole Bhature', 'North Indian classic', 179.00, 'https://www.vegrecipesofindia.com/wp-content/uploads/2014/12/chole-bhature-recipe-1.jpg', TRUE),
(5, NULL, 'Masala Dosa', 'South Indian favorite', 129.00, 'https://www.vegrecipesofindia.com/wp-content/uploads/2014/09/masala-dosa-recipe-1.jpg', TRUE),
(5, NULL, 'Veg Manchurian', 'Indo-Chinese dish', 199.00, 'https://www.vegrecipesofindia.com/wp-content/uploads/2020/06/veg-manchurian-1.jpg', TRUE),
(5, 4, 'Gulab Jamun', 'Sweet dessert', 79.00, 'https://www.vegrecipesofindia.com/wp-content/uploads/2015/04/gulab-jamun-recipe-1.jpg', TRUE);

-- Sample menu items for Subway
INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_url, is_veg) VALUES
(6, NULL, 'Chicken Teriyaki Sub', '6 inch chicken sub', 199.00, 'https://www.subway.com/~/media/Global/MenuItems/Sandwiches/ChickenTeriyaki.jpg', FALSE),
(6, NULL, 'Veggie Delite Sub', '6 inch veggie sub', 149.00, 'https://www.subway.com/~/media/Global/MenuItems/Sandwiches/VeggieDelite.jpg', TRUE),
(6, NULL, 'Paneer Tikka Sub', '6 inch paneer sub', 179.00, 'https://www.subway.com/~/media/Global/MenuItems/Sandwiches/PaneerTikka.jpg', TRUE),
(6, NULL, 'Tuna Sub', '6 inch tuna sub', 219.00, 'https://www.subway.com/~/media/Global/MenuItems/Sandwiches/Tuna.jpg', FALSE),
(6, NULL, 'Aloo Patty Sub', '6 inch aloo patty sub', 129.00, 'https://www.subway.com/~/media/Global/MenuItems/Sandwiches/AlooPatty.jpg', TRUE);

-- Sample riders (password is 'rider123' hashed with bcrypt)
INSERT INTO riders (name, email, phone, password, vehicle_type, is_available, is_verified, rating, total_deliveries) VALUES
('rider1', 'rider1@example.com', '+91 9876543210', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6', 'bike', TRUE, TRUE, 4.8, 245),
('rider2', 'rider2@example.com', '+91 9876543211', '$2y$10$remqecqf8vGvVu5zUFUePOBBVfgA3m9IdTVlYvJRO7Q7mLvBbNjP6', 'scooter', TRUE, TRUE, 4.6, 189);
