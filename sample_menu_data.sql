-- =====================================================
-- Sample Menu Items for SnackZone
-- Import this AFTER database_schema.sql
-- =====================================================

-- Insert sample menu items with discount
INSERT INTO `menu_items` 
(`category`, `title`, `description`, `price`, `discount_percent`, `image_path`, `tag`, `featured`) 
VALUES
-- Burgers
('burgers', 'Classic Beef Burger', 'Juicy beef patty with fresh lettuce, tomatoes, onions and our special sauce', 250.00, 10, 'img/menu/item_6a9a87e5cc411.png', 'Popular', 1),
('burgers', 'Chicken Zinger Burger', 'Crispy fried chicken fillet with spicy mayo and fresh veggies', 220.00, 15, 'img/menu/item_6a9a8814101c4.png', 'Hot', 1),
('burgers', 'Cheese Deluxe Burger', 'Double patty with melted cheese, lettuce and special sauce', 280.00, 0, 'img/menu/item_6a9a8845c4fb8.png', 'New', 1),

-- Sandwiches  
('sandwiches', 'Club Sandwich', 'Triple decker with chicken, bacon, egg, lettuce and mayo', 200.00, 5, 'img/menu/item_6a9a88eb07829.png', '', 0),
('sandwiches', 'Grilled Chicken Sandwich', 'Tender grilled chicken with cheese and vegetables', 180.00, 10, 'img/menu/item_6a9a8942cfad3.png', 'Popular', 1),

-- Shawarma
('shawarma', 'Chicken Shawarma Roll', 'Marinated chicken wrapped in soft bread with garlic sauce', 150.00, 20, 'img/inger.png', 'Best Seller', 1),
('shawarma', 'Beef Shawarma Plate', 'Sliced beef with hummus, salad and pita bread', 300.00, 0, 'img/fires + sharwarma.jfif', '', 0),

-- Fries
('fries', 'Classic French Fries', 'Golden crispy fries with ketchup', 100.00, 0, 'img/fires.webp', '', 1),
('fries', 'Loaded Cheese Fries', 'Fries topped with melted cheese and jalapenos', 150.00, 15, 'img/crispy fries.jpg', 'Popular', 1),
('fries', 'Peri Peri Fries', 'Spicy fries with peri peri seasoning', 120.00, 10, 'img/friesss.jfif', 'Hot', 0),

-- Drinks
('drinks', 'Fresh Lime Soda', 'Refreshing lime soda with mint', 80.00, 0, 'img/banners/banner_drinks_6aaa6115622b3.png', '', 0),
('drinks', 'Mango Shake', 'Creamy mango shake with ice cream', 120.00, 0, 'img/banners/banner_drinks_6aaa6117c2b18.png', '', 1),
('drinks', 'Cold Coffee', 'Chilled coffee with chocolate topping', 100.00, 5, 'img/banners/banner_drinks_6aaa612a96406.png', 'Popular', 1);

-- =====================================================
-- Sample banner slides
-- =====================================================
INSERT INTO `slides` 
(`title`, `image_path`, `position`, `is_active`) 
VALUES
('Special Burger Deals', 'img/banners/banner_burger_with_drinks_6aa1259c4b8ec.png', 1, 1),
('Weekend Deals', 'img/banners/banner_deals_6aa125d422955.png', 2, 1),
('Combo Offers', 'img/banners/banner_fries_x_burger_6aaa6195155f7.png', 3, 1);

-- =====================================================
-- Sample notice
-- =====================================================
INSERT INTO `notices` 
(`title`, `body`, `is_active`) 
VALUES
('Free Delivery on Orders Above ₹500', 'Get free home delivery on all orders above ₹500. Limited time offer!', 1);

-- =====================================================
-- Data inserted successfully!
-- Now refresh your homepage to see menu items
-- =====================================================
