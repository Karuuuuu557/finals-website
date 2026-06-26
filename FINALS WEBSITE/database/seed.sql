USE `login_credentials`;

INSERT INTO `employee_credentials` (`username`, `email`, `password`, `roles`)
VALUES
  ('admin', 'admin@fivesix.local', 'admin123', 'admin'),
  ('staff1', 'staff1@fivesix.local', 'staff123', 'staff')
ON DUPLICATE KEY UPDATE
  `password` = VALUES(`password`),
  `roles` = VALUES(`roles`);

USE `main`;

INSERT INTO `addons` (`slug`, `label`, `price`, `is_active`)
VALUES
  ('extra-shot', 'Extra Espresso Shot', 20.00, 1),
  ('oat-milk', 'Oat Milk', 15.00, 1),
  ('caramel-drizzle', 'Caramel Drizzle', 10.00, 1),
  ('whipped-cream', 'Whipped Cream', 12.00, 1)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `price` = VALUES(`price`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `products` (`product_name`, `variant`, `category`, `price`, `stock`, `threshold`, `emoji`, `is_active`)
VALUES
  ('Americano', 'Hot', 'Espresso Based', 40.00, 50, 10, '☕', 1),
  ('Iced Coffee Latte', 'Iced', 'Espresso Based', 50.00, 50, 10, '🧊', 1),
  ('Black Coffee', 'Hot', 'Espresso Based', 40.00, 40, 10, '🖤', 1),
  ('Latte', 'Hot/Iced', 'Espresso Based', 50.00, 45, 10, '🥛', 1),
  ('Vietnamese Latte', 'Iced', 'Espresso Based', 50.00, 30, 10, '🌿', 1),
  ('Caramel Macchiato', 'Hot/Iced', 'Espresso Based', 50.00, 35, 10, '🍯', 1),
  ('Spanish Latte', 'Hot/Iced', 'Espresso Based', 50.00, 35, 10, '🤎', 1),
  ('Horchata Latte', 'Iced', 'Espresso Based', 50.00, 30, 10, '🥤', 1),
  ('Dirty Matcha', 'Iced', 'Espresso Based', 50.00, 20, 8, '🍵', 1),
  ('White Choco', 'Hot/Iced', 'Non-Caffeine', 50.00, 30, 10, '🤍', 1),
  ('Dark Choco', 'Hot/Iced', 'Non-Caffeine', 50.00, 30, 10, '🍫', 1),
  ('Matcha Latte', 'Hot/Iced', 'Non-Caffeine', 50.00, 30, 10, '🍵', 1),
  ('Fruit Soda', 'Iced', 'Non-Caffeine', 40.00, 30, 10, '🥤', 1),
  ('Hot Choco', 'Hot', 'Non-Caffeine', 50.00, 30, 10, '♨️', 1),
  ('Hot White Choco', 'Hot', 'Non-Caffeine', 50.00, 30, 10, '☁️', 1)
ON DUPLICATE KEY UPDATE
  `price` = VALUES(`price`),
  `stock` = VALUES(`stock`),
  `threshold` = VALUES(`threshold`),
  `emoji` = VALUES(`emoji`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `ingredients` (`ingredient_name`, `category`, `unit_of_measure`, `current_quantity`, `is_active`)
VALUES
  ('Espresso Beans', 'Espresso', 'g', 5000, 1),
  ('Fresh Milk', 'Milk', 'ml', 12000, 1),
  ('Oat Milk', 'Milk', 'ml', 6000, 1),
  ('Caramel Syrup', 'Syrup', 'ml', 3000, 1),
  ('Chocolate Syrup', 'Chocolate', 'ml', 3500, 1),
  ('Matcha Powder', 'Non-Caffeine', 'g', 1200, 1),
  ('Fruit Soda Base', 'Base', 'ml', 5000, 1),
  ('Whipped Cream', 'Topping', 'g', 1500, 1)
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `unit_of_measure` = VALUES(`unit_of_measure`),
  `current_quantity` = VALUES(`current_quantity`),
  `is_active` = VALUES(`is_active`);
