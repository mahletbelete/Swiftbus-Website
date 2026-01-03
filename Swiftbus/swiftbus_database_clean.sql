-- SwiftBus Database Structure

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Use existing database or create if it doesn't exist
CREATE DATABASE IF NOT EXISTS `swiftbus_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `swiftbus_db`;

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `profile_image` varchar(255) DEFAULT NULL,
  `joined_date` date NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `user_sessions`
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `activity_logs`
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `cities` (FIXED: Only 10 cities as per website)
CREATE TABLE IF NOT EXISTS `cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Ethiopia',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `city_code` (`city_code`),
  KEY `idx_city_code` (`city_code`),
  KEY `idx_region` (`region`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `bus_companies` (FIXED: Only 4 companies as per website)
CREATE TABLE IF NOT EXISTS `bus_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `routes`
CREATE TABLE IF NOT EXISTS `routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_id` varchar(20) NOT NULL,
  `origin_city_id` int(11) NOT NULL,
  `destination_city_id` int(11) NOT NULL,
  `distance_km` int(11) DEFAULT NULL,
  `estimated_duration_hours` decimal(4,2) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `route_id` (`route_id`),
  UNIQUE KEY `unique_route` (`origin_city_id`, `destination_city_id`),
  KEY `idx_route_id` (`route_id`),
  KEY `idx_origin` (`origin_city_id`),
  KEY `idx_destination` (`destination_city_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `routes_ibfk_1` FOREIGN KEY (`origin_city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routes_ibfk_2` FOREIGN KEY (`destination_city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `buses`
CREATE TABLE IF NOT EXISTS `buses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_id` varchar(20) NOT NULL,
  `company_id` int(11) NOT NULL,
  `bus_number` varchar(50) NOT NULL,
  `bus_type` enum('economy','standard','standard-ac','premium-ac','luxury') NOT NULL,
  `total_seats` int(11) NOT NULL,
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities`)),
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `license_plate` varchar(20) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year_manufactured` year(4) DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bus_id` (`bus_id`),
  UNIQUE KEY `license_plate` (`license_plate`),
  KEY `idx_bus_id` (`bus_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`bus_type`),
  CONSTRAINT `buses_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `bus_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `schedules`
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` varchar(20) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `departure_time` time NOT NULL,
  `arrival_time` time NOT NULL,
  `days_of_week` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`days_of_week`)),
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `schedule_id` (`schedule_id`),
  KEY `idx_schedule_id` (`schedule_id`),
  KEY `idx_bus` (`bus_id`),
  KEY `idx_route` (`route_id`),
  KEY `idx_departure` (`departure_time`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `bookings`
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `bus_company` varchar(100) NOT NULL,
  `bus_type` varchar(100) NOT NULL,
  `from_city` varchar(100) NOT NULL,
  `to_city` varchar(100) NOT NULL,
  `travel_date` date NOT NULL,
  `departure_time` varchar(10) NOT NULL,
  `passenger_count` int(11) NOT NULL DEFAULT 1,
  `selected_seats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selected_seats`)),
  `passenger_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`passenger_details`)),
  `total_amount` decimal(10,2) NOT NULL,
  `booking_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancellation_date` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_schedule` (`schedule_id`),
  KEY `idx_travel_date` (`travel_date`),
  KEY `idx_status` (`booking_status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_company_route` (`bus_company`, `from_city`, `to_city`, `travel_date`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `bus_seats`
CREATE TABLE IF NOT EXISTS `bus_seats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_company` varchar(100) NOT NULL,
  `bus_type` varchar(100) NOT NULL,
  `route` varchar(200) NOT NULL,
  `departure_date` date NOT NULL,
  `departure_time` varchar(10) NOT NULL,
  `seat_number` int(11) NOT NULL,
  `seat_type` enum('regular','women-only','accessible') DEFAULT 'regular',
  `is_occupied` tinyint(1) DEFAULT 0,
  `booking_id` varchar(20) DEFAULT NULL,
  `reserved_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seat_booking` (`bus_company`, `bus_type`, `route`, `departure_date`, `departure_time`, `seat_number`),
  KEY `idx_seat_availability` (`bus_company`, `bus_type`, `route`, `departure_date`, `departure_time`, `is_occupied`),
  KEY `idx_booking_seats` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `payments`
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` varchar(20) NOT NULL,
  `booking_id` varchar(20) NOT NULL,
  `passenger_name` varchar(200) DEFAULT NULL,
  `passenger_email` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('telebirr','cbe','dashen','card','cash') NOT NULL,
  `payment_status` enum('pending','processing','completed','failed','refunded') DEFAULT 'pending',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `payment_date` timestamp NULL DEFAULT NULL,
  `refund_date` timestamp NULL DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_id` (`payment_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_passenger_email` (`passenger_email`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_method` (`payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `reviews`
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `review_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` varchar(20) NOT NULL,
  `company_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 1,
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_id` (`review_id`),
  KEY `idx_review_id` (`review_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_published` (`is_published`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `bus_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `notifications`
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('booking','payment','system','promotion') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_id` (`notification_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `system_settings`
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PRODUCTION DATA INSERTION
-- =====================================================

-- Insert cities only if they don't exist
INSERT IGNORE INTO `cities` (`city_code`, `name`, `region`, `latitude`, `longitude`) VALUES
('addis-ababa', 'Addis Ababa', 'Addis Ababa', 9.03200000, 38.74690000),
('kombolcha', 'Kombolcha', 'Amhara', 11.08170000, 39.74360000),
('bahirdar', 'Bahirdar', 'Amhara', 11.59420000, 37.39060000),
('dessie', 'Dessie', 'Amhara', 11.13000000, 39.63330000),
('adama', 'Adama', 'Oromia', 8.54000000, 39.26750000),
('hawasa', 'Hawasa', 'SNNPR', 7.06210000, 38.47760000),
('arbaminch', 'Arbaminch', 'SNNPR', 6.03330000, 37.55000000),
('gonder', 'Gonder', 'Amhara', 12.60900000, 37.46710000),
('mekele', 'Mekele', 'Tigray', 13.49670000, 39.47530000),
('jimma', 'Jimma', 'Oromia', 7.67310000, 36.83440000);

-- Insert bus companies only if they don't exist
INSERT IGNORE INTO `bus_companies` (`company_id`, `name`, `description`, `rating`, `contact_phone`, `contact_email`) VALUES
('selam-bus', 'Selam Bus', 'Premium bus service with luxury amenities and excellent customer service', 4.80, '+251-11-123-4567', 'info@selambus.et'),
('abay-bus', 'Abay Bus', 'Reliable northern routes specialist with comfortable travel experience', 4.50, '+251-11-234-5678', 'contact@abaybus.et'),
('ethio-bus', 'Ethio Bus', 'Budget-friendly nationwide coverage with dependable service', 4.20, '+251-11-345-6789', 'support@ethiobus.et'),
('habesha-bus', 'Habesha Bus', 'Cultural experience with comfort and traditional Ethiopian hospitality', 4.40, '+251-11-456-7890', 'hello@habeshabus.et');

-- Insert admin users only if they don't exist
INSERT IGNORE INTO `users` (`user_id`, `email`, `password_hash`, `first_name`, `last_name`, `full_name`, `role`, `is_verified`, `joined_date`) VALUES
('U202501010001', 'ezedinmoh1@gmail.com', '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla', 'Ezedin', 'Mohammed', 'Ezedin Mohammed', 'admin', 1, '2025-01-01'),
('U202501010002', 'hanamariamsebsbew1@gmail.com', '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla', 'Hana', 'Mariam', 'Hana Mariam Sebsbew', 'admin', 1, '2025-01-01'),
('U202501010003', 'mubarekali974@gmail.com', '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla', 'Mubarek', 'Ali', 'Mubarek Ali', 'admin', 1, '2025-01-01'),
('U202501010004', 'wubetlemma788@gmail.com', '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla', 'Wubet', 'Lemma', 'Wubet Lemma', 'admin', 1, '2025-01-01'),
('U202501010005', 'mahletbelete4@gmail.com', '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla', 'Mahlet', 'Belete', 'Mahlet Belete', 'admin', 1, '2025-01-01'),
('U202501010006', 'admin@swiftbus.et', '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla', 'Admin', 'User', 'Admin User', 'admin', 1, '2025-01-01');

-- Insert system settings only if they don't exist
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('site_name', 'SwiftBus', 'string', 'Website name', 1),
('site_description', 'Ethiopian Bus Booking System', 'string', 'Website description', 1),
('contact_email', 'info@swiftbus.et', 'string', 'Contact email address', 1),
('support_phone', '+251-11-555-0123', 'string', 'Support phone number', 1),
('contact_phone', '+251-11-123-4567', 'string', 'Contact phone number', 1),
('booking_advance_days', '30', 'number', 'Maximum days in advance for booking', 0),
('booking_cancellation_hours', '24', 'number', 'Hours before departure to allow cancellation', 0),
('cancellation_hours', '24', 'number', 'Hours before departure for free cancellation', 0),
('service_fee', '25', 'number', 'Service fee amount in ETB', 0),
('max_seats_per_booking', '6', 'number', 'Maximum seats per booking', 1),
('max_passengers_per_booking', '6', 'number', 'Maximum passengers per booking', 0),
('seat_reservation_timeout', '15', 'number', 'Seat reservation timeout in minutes', 0),
('payment_timeout_minutes', '15', 'number', 'Payment timeout in minutes', 0),
('payment_timeout', '30', 'number', 'Payment timeout in minutes', 0),
('admin_session_timeout', '3600', 'number', 'Admin session timeout in seconds', 0),
('admin_max_login_attempts', '5', 'number', 'Maximum login attempts for admin', 0),
('admin_lockout_duration', '1800', 'number', 'Admin lockout duration in seconds', 0),
('admin_dashboard_refresh_interval', '300', 'number', 'Dashboard auto-refresh interval in seconds', 0),
('admin_booking_edit_window', '48', 'number', 'Hours before departure when admin can edit bookings', 0),
('admin_notification_email', 'admin@swiftbus.et', 'string', 'Admin notification email', 0),
('admin_backup_frequency', 'daily', 'string', 'Database backup frequency', 0),
('admin_log_retention_days', '90', 'number', 'Days to retain admin activity logs', 0);

-- =====================================================
-- POPULATE BUSES (12 buses - 3 per company)
-- =====================================================

-- Insert buses only if they don't exist
INSERT IGNORE INTO `buses` (`bus_id`, `company_id`, `bus_number`, `bus_type`, `total_seats`, `amenities`, `status`, `license_plate`, `model`, `year_manufactured`) VALUES
-- Selam Bus (3 luxury buses)
('SB001', 1, 'Selam Express 001', 'luxury', 40, '["WiFi", "AC", "Toilet", "Entertainment", "Charging", "Reclining Seats"]', 'active', 'ET-SEL-1001', 'Mercedes-Benz Travego', 2022),
('SB002', 1, 'Selam Express 002', 'luxury', 40, '["WiFi", "AC", "Toilet", "Entertainment", "Charging", "Reclining Seats"]', 'active', 'ET-SEL-1002', 'Mercedes-Benz Travego', 2022),
('SB003', 1, 'Selam Express 003', 'luxury', 40, '["WiFi", "AC", "Toilet", "Entertainment", "Charging", "Reclining Seats"]', 'active', 'ET-SEL-1003', 'Mercedes-Benz Travego', 2023),

-- Abay Bus (3 standard buses)
('AB001', 2, 'Abay Comfort 001', 'standard-ac', 45, '["AC", "Comfortable Seats", "Snacks", "Music"]', 'active', 'ET-ABY-2001', 'Hyundai Universe', 2021),
('AB002', 2, 'Abay Comfort 002', 'standard-ac', 45, '["AC", "Comfortable Seats", "Snacks", "Music"]', 'active', 'ET-ABY-2002', 'Hyundai Universe', 2021),
('AB003', 2, 'Abay Comfort 003', 'standard-ac', 45, '["AC", "Comfortable Seats", "Snacks", "Music"]', 'maintenance', 'ET-ABY-2003', 'Hyundai Universe', 2020),

-- Ethio Bus (3 economy buses)
('EB001', 3, 'Ethio Budget 001', 'standard', 50, '["Basic AC", "Affordable", "Frequent Service"]', 'active', 'ET-ETH-3001', 'Tata Starbus', 2020),
('EB002', 3, 'Ethio Budget 002', 'standard', 50, '["Basic AC", "Affordable", "Frequent Service"]', 'active', 'ET-ETH-3002', 'Tata Starbus', 2020),
('EB003', 3, 'Ethio Budget 003', 'economy', 50, '["Basic Seating", "Affordable", "Frequent Service"]', 'active', 'ET-ETH-3003', 'Tata Starbus', 2019),

-- Habesha Bus (3 premium buses)
('HB001', 4, 'Habesha Cultural 001', 'premium-ac', 35, '["Sleeper Seats", "Blankets", "Toilet", "AC", "Cultural Music"]', 'active', 'ET-HAB-4001', 'Volvo 9700', 2021),
('HB002', 4, 'Habesha Cultural 002', 'premium-ac', 35, '["Sleeper Seats", "Blankets", "Toilet", "AC", "Cultural Music"]', 'active', 'ET-HAB-4002', 'Volvo 9700', 2022),
('HB003', 4, 'Habesha Cultural 003', 'premium-ac', 35, '["Sleeper Seats", "Blankets", "Toilet", "AC", "Cultural Music"]', 'active', 'ET-HAB-4003', 'Volvo 9700', 2022);

-- =====================================================
-- POPULATE ROUTES (50+ routes covering all city combinations)
-- =====================================================

-- Insert routes only if they don't exist
INSERT IGNORE INTO `routes` (`route_id`, `origin_city_id`, `destination_city_id`, `distance_km`, `estimated_duration_hours`, `base_price`) VALUES
-- Routes from Addis Ababa to all other cities
('RT001', 1, 2, 375, 5.5, 280),  -- Addis Ababa to Kombolcha
('RT002', 1, 3, 565, 8.5, 450),  -- Addis Ababa to Bahirdar
('RT003', 1, 4, 401, 6.0, 320),  -- Addis Ababa to Dessie
('RT004', 1, 5, 99, 1.5, 80),    -- Addis Ababa to Adama
('RT005', 1, 6, 275, 4.0, 220),  -- Addis Ababa to Hawasa
('RT006', 1, 7, 505, 7.5, 400),  -- Addis Ababa to Arbaminch
('RT007', 1, 8, 748, 11.0, 580), -- Addis Ababa to Gonder
('RT008', 1, 9, 783, 12.0, 620), -- Addis Ababa to Mekele
('RT009', 1, 10, 346, 5.0, 280), -- Addis Ababa to Jimma

-- Return routes to Addis Ababa
('RT010', 2, 1, 375, 5.5, 280),  -- Kombolcha to Addis Ababa
('RT011', 3, 1, 565, 8.5, 450),  -- Bahirdar to Addis Ababa
('RT012', 4, 1, 401, 6.0, 320),  -- Dessie to Addis Ababa
('RT013', 5, 1, 99, 1.5, 80),    -- Adama to Addis Ababa
('RT014', 6, 1, 275, 4.0, 220),  -- Hawasa to Addis Ababa
('RT015', 7, 1, 505, 7.5, 400),  -- Arbaminch to Addis Ababa
('RT016', 8, 1, 748, 11.0, 580), -- Gonder to Addis Ababa
('RT017', 9, 1, 783, 12.0, 620), -- Mekele to Addis Ababa
('RT018', 10, 1, 346, 5.0, 280), -- Jimma to Addis Ababa

-- Northern region interconnections
('RT019', 3, 8, 180, 3.0, 150),  -- Bahirdar to Gonder
('RT020', 8, 3, 180, 3.0, 150),  -- Gonder to Bahirdar
('RT021', 3, 9, 420, 6.5, 350),  -- Bahirdar to Mekele
('RT022', 9, 3, 420, 6.5, 350),  -- Mekele to Bahirdar
('RT023', 8, 9, 300, 4.5, 250),  -- Gonder to Mekele
('RT024', 9, 8, 300, 4.5, 250),  -- Mekele to Gonder
('RT025', 4, 2, 126, 2.0, 100),  -- Dessie to Kombolcha
('RT026', 2, 4, 126, 2.0, 100),  -- Kombolcha to Dessie
('RT027', 4, 9, 300, 4.5, 250),  -- Dessie to Mekele
('RT028', 9, 4, 300, 4.5, 250),  -- Mekele to Dessie
('RT029', 3, 4, 300, 4.5, 250),  -- Bahirdar to Dessie
('RT030', 4, 3, 300, 4.5, 250),  -- Dessie to Bahirdar

-- Southern region interconnections
('RT031', 6, 7, 275, 4.0, 220),  -- Hawasa to Arbaminch
('RT032', 7, 6, 275, 4.0, 220),  -- Arbaminch to Hawasa
('RT033', 5, 6, 176, 2.5, 140),  -- Adama to Hawasa
('RT034', 6, 5, 176, 2.5, 140),  -- Hawasa to Adama
('RT035', 6, 10, 290, 4.5, 230), -- Hawasa to Jimma
('RT036', 10, 6, 290, 4.5, 230), -- Jimma to Hawasa
('RT037', 5, 10, 345, 5.0, 280), -- Adama to Jimma
('RT038', 10, 5, 345, 5.0, 280), -- Jimma to Adama
('RT039', 7, 10, 565, 8.0, 450), -- Arbaminch to Jimma
('RT040', 10, 7, 565, 8.0, 450), -- Jimma to Arbaminch

-- Cross-regional routes (North-South connections)
('RT041', 3, 6, 840, 12.5, 670), -- Bahirdar to Hawasa
('RT042', 6, 3, 840, 12.5, 670), -- Hawasa to Bahirdar
('RT043', 8, 6, 1023, 15.0, 820), -- Gonder to Hawasa
('RT044', 6, 8, 1023, 15.0, 820), -- Hawasa to Gonder
('RT045', 9, 6, 1058, 16.0, 850), -- Mekele to Hawasa
('RT046', 6, 9, 1058, 16.0, 850), -- Hawasa to Mekele
('RT047', 3, 10, 911, 13.5, 730), -- Bahirdar to Jimma
('RT048', 10, 3, 911, 13.5, 730), -- Jimma to Bahirdar
('RT049', 8, 10, 1094, 16.5, 880), -- Gonder to Jimma
('RT050', 10, 8, 1094, 16.5, 880), -- Jimma to Gonder
('RT051', 9, 10, 1129, 17.0, 900), -- Mekele to Jimma
('RT052', 10, 9, 1129, 17.0, 900), -- Jimma to Mekele
('RT053', 4, 6, 676, 10.0, 540),  -- Dessie to Hawasa
('RT054', 6, 4, 676, 10.0, 540),  -- Hawasa to Dessie
('RT055', 2, 6, 650, 9.5, 520),   -- Kombolcha to Hawasa
('RT056', 6, 2, 650, 9.5, 520);   -- Hawasa to Kombolcha

-- =====================================================
-- POPULATE SCHEDULES (Multiple schedules per route)
-- =====================================================

-- Insert schedules only if they don't exist
INSERT IGNORE INTO `schedules` (`schedule_id`, `bus_id`, `route_id`, `departure_time`, `arrival_time`, `days_of_week`, `price`, `effective_from`) VALUES
-- Morning schedules (6:00 AM - 9:00 AM)
-- Selam Bus schedules
('SCH001', 1, 1, '06:00:00', '11:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 280.00, '2025-01-01'),
('SCH002', 1, 2, '06:30:00', '15:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 450.00, '2025-01-01'),
('SCH003', 2, 3, '07:00:00', '13:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 320.00, '2025-01-01'),
('SCH004', 2, 4, '07:30:00', '09:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 80.00, '2025-01-01'),
('SCH005', 3, 5, '08:00:00', '12:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 220.00, '2025-01-01'),

-- Abay Bus schedules
('SCH006', 4, 6, '06:00:00', '13:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 400.00, '2025-01-01'),
('SCH007', 4, 7, '06:30:00', '17:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 580.00, '2025-01-01'),
('SCH008', 5, 8, '07:00:00', '19:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 620.00, '2025-01-01'),
('SCH009', 5, 9, '07:30:00', '12:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 280.00, '2025-01-01'),
('SCH010', 6, 19, '08:00:00', '11:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 150.00, '2025-01-01'),

-- Ethio Bus schedules
('SCH011', 7, 21, '06:00:00', '12:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 350.00, '2025-01-01'),
('SCH012', 7, 23, '06:30:00', '11:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 250.00, '2025-01-01'),
('SCH013', 8, 25, '07:00:00', '09:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 100.00, '2025-01-01'),
('SCH014', 8, 27, '07:30:00', '12:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 250.00, '2025-01-01'),
('SCH015', 9, 31, '08:00:00', '12:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 220.00, '2025-01-01'),

-- Habesha Bus schedules (Night services)
('SCH016', 10, 33, '21:00:00', '23:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 140.00, '2025-01-01'),
('SCH017', 10, 35, '21:30:00', '02:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 230.00, '2025-01-01'),
('SCH018', 11, 37, '22:00:00', '03:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 280.00, '2025-01-01'),
('SCH019', 11, 39, '22:30:00', '06:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 450.00, '2025-01-01'),
('SCH020', 12, 41, '23:00:00', '11:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 670.00, '2025-01-01'),

-- Afternoon schedules (12:00 PM - 3:00 PM)
('SCH021', 1, 10, '12:00:00', '17:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 280.00, '2025-01-01'),
('SCH022', 2, 11, '12:30:00', '21:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 450.00, '2025-01-01'),
('SCH023', 3, 12, '13:00:00', '19:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 320.00, '2025-01-01'),
('SCH024', 4, 13, '13:30:00', '15:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 80.00, '2025-01-01'),
('SCH025', 5, 14, '14:00:00', '18:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 220.00, '2025-01-01'),
('SCH026', 6, 15, '14:30:00', '22:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 400.00, '2025-01-01'),
('SCH027', 7, 16, '15:00:00', '02:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 580.00, '2025-01-01'),
('SCH028', 8, 17, '15:30:00', '03:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 620.00, '2025-01-01'),
('SCH029', 9, 18, '16:00:00', '21:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 280.00, '2025-01-01'),
('SCH030', 10, 20, '16:30:00', '19:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 150.00, '2025-01-01'),

-- Evening schedules (6:00 PM - 9:00 PM)
('SCH031', 11, 22, '18:00:00', '00:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 350.00, '2025-01-01'),
('SCH032', 12, 24, '18:30:00', '23:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 250.00, '2025-01-01'),
('SCH033', 1, 26, '19:00:00', '21:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 100.00, '2025-01-01'),
('SCH034', 2, 28, '19:30:00', '00:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 250.00, '2025-01-01'),
('SCH035', 3, 30, '20:00:00', '00:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 250.00, '2025-01-01'),
('SCH036', 4, 32, '20:30:00', '00:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 220.00, '2025-01-01'),
('SCH037', 5, 34, '21:00:00', '23:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 140.00, '2025-01-01'),
('SCH038', 6, 36, '21:30:00', '02:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 230.00, '2025-01-01'),
('SCH039', 7, 38, '22:00:00', '03:00:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 280.00, '2025-01-01'),
('SCH040', 8, 40, '22:30:00', '06:30:00', '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]', 450.00, '2025-01-01'),

-- Weekend special schedules
('SCH041', 9, 42, '05:00:00', '17:30:00', '["Friday","Saturday","Sunday"]', 670.00, '2025-01-01'),
('SCH042', 10, 44, '05:30:00', '20:30:00', '["Friday","Saturday","Sunday"]', 820.00, '2025-01-01'),
('SCH043', 11, 46, '06:00:00', '22:00:00', '["Friday","Saturday","Sunday"]', 850.00, '2025-01-01'),
('SCH044', 12, 48, '06:30:00', '20:00:00', '["Friday","Saturday","Sunday"]', 730.00, '2025-01-01'),
('SCH045', 1, 50, '07:00:00', '23:30:00', '["Friday","Saturday","Sunday"]', 880.00, '2025-01-01'),
('SCH046', 2, 52, '07:30:00', '00:30:00', '["Friday","Saturday","Sunday"]', 900.00, '2025-01-01'),
('SCH047', 3, 54, '08:00:00', '18:00:00', '["Friday","Saturday","Sunday"]', 540.00, '2025-01-01'),
('SCH048', 4, 56, '08:30:00', '18:00:00', '["Friday","Saturday","Sunday"]', 520.00, '2025-01-01'),
('SCH049', 5, 43, '17:00:00', '08:00:00', '["Friday","Saturday","Sunday"]', 820.00, '2025-01-01'),
('SCH050', 6, 45, '17:30:00', '09:30:00', '["Friday","Saturday","Sunday"]', 850.00, '2025-01-01');

COMMIT;