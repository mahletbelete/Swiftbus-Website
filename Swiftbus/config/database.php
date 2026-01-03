<?php
/**
 * SwiftBus Database Configuration
 * 
 * This file contains database connection settings and initialization
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'swiftbus_db';
    private $username = 'root';
    private $password = '';
    private $conn;

    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    /**
     * Initialize database and create tables
     */
    public function initializeDatabase() {
        try {
            // Create database if it doesn't exist
            $conn = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "CREATE DATABASE IF NOT EXISTS " . $this->db_name . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $conn->exec($sql);
            
            // Connect to the database
            $this->conn = $this->getConnection();
            
            // Create tables
            $this->createTables();
            
            return true;
        } catch(PDOException $e) {
            echo "Database initialization error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Create all necessary tables
     */
    private function createTables() {
        $tables = [
            // Users table
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(20) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                phone VARCHAR(20),
                role ENUM('user', 'admin') DEFAULT 'user',
                is_verified BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                profile_image VARCHAR(255),
                joined_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_user_id (user_id),
                INDEX idx_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Bus companies table
            "CREATE TABLE IF NOT EXISTS bus_companies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id VARCHAR(20) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                logo VARCHAR(255),
                description TEXT,
                rating DECIMAL(3,2) DEFAULT 0.00,
                total_reviews INT DEFAULT 0,
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),
                website VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_company_id (company_id),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Cities table
            "CREATE TABLE IF NOT EXISTS cities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                city_code VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                region VARCHAR(100),
                country VARCHAR(100) DEFAULT 'Ethiopia',
                latitude DECIMAL(10, 8),
                longitude DECIMAL(11, 8),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_city_code (city_code),
                INDEX idx_region (region)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Routes table
            "CREATE TABLE IF NOT EXISTS routes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                route_id VARCHAR(20) UNIQUE NOT NULL,
                origin_city_id INT NOT NULL,
                destination_city_id INT NOT NULL,
                distance_km INT,
                estimated_duration_hours DECIMAL(4,2),
                base_price DECIMAL(10,2) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (origin_city_id) REFERENCES cities(id) ON DELETE CASCADE,
                FOREIGN KEY (destination_city_id) REFERENCES cities(id) ON DELETE CASCADE,
                INDEX idx_route_id (route_id),
                INDEX idx_origin (origin_city_id),
                INDEX idx_destination (destination_city_id),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Buses table
            "CREATE TABLE IF NOT EXISTS buses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bus_id VARCHAR(20) UNIQUE NOT NULL,
                company_id INT NOT NULL,
                bus_number VARCHAR(50) NOT NULL,
                bus_type ENUM('economy', 'standard', 'standard-ac', 'premium-ac', 'luxury') NOT NULL,
                total_seats INT NOT NULL,
                amenities JSON,
                status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
                license_plate VARCHAR(20),
                model VARCHAR(100),
                year_manufactured YEAR,
                last_maintenance_date DATE,
                next_maintenance_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES bus_companies(id) ON DELETE CASCADE,
                INDEX idx_bus_id (bus_id),
                INDEX idx_company (company_id),
                INDEX idx_status (status),
                INDEX idx_type (bus_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Schedules table
            "CREATE TABLE IF NOT EXISTS schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                schedule_id VARCHAR(20) UNIQUE NOT NULL,
                bus_id INT NOT NULL,
                route_id INT NOT NULL,
                departure_time TIME NOT NULL,
                arrival_time TIME NOT NULL,
                days_of_week JSON NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                effective_from DATE NOT NULL,
                effective_until DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
                FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
                INDEX idx_schedule_id (schedule_id),
                INDEX idx_bus (bus_id),
                INDEX idx_route (route_id),
                INDEX idx_departure (departure_time),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Bookings table
            "CREATE TABLE IF NOT EXISTS bookings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                schedule_id INT NOT NULL,
                travel_date DATE NOT NULL,
                passenger_count INT NOT NULL DEFAULT 1,
                selected_seats JSON,
                passenger_details JSON NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                booking_status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
                payment_status ENUM('pending', 'paid', 'refunded', 'failed') DEFAULT 'pending',
                payment_method VARCHAR(50),
                payment_reference VARCHAR(100),
                special_requirements TEXT,
                qr_code VARCHAR(255),
                booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                cancellation_date TIMESTAMP NULL,
                cancellation_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
                INDEX idx_booking_id (booking_id),
                INDEX idx_user (user_id),
                INDEX idx_schedule (schedule_id),
                INDEX idx_travel_date (travel_date),
                INDEX idx_status (booking_status),
                INDEX idx_payment_status (payment_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Payments table
            "CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_id VARCHAR(20) UNIQUE NOT NULL,
                booking_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method ENUM('telebirr', 'cbe', 'dashen', 'card', 'cash') NOT NULL,
                payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                transaction_reference VARCHAR(100),
                gateway_response JSON,
                payment_date TIMESTAMP NULL,
                refund_date TIMESTAMP NULL,
                refund_amount DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                INDEX idx_payment_id (payment_id),
                INDEX idx_booking (booking_id),
                INDEX idx_status (payment_status),
                INDEX idx_method (payment_method)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Reviews table
            "CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                review_id VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                booking_id INT NOT NULL,
                company_id INT NOT NULL,
                rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                review_text TEXT,
                is_verified BOOLEAN DEFAULT FALSE,
                is_published BOOLEAN DEFAULT TRUE,
                admin_response TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES bus_companies(id) ON DELETE CASCADE,
                INDEX idx_review_id (review_id),
                INDEX idx_user (user_id),
                INDEX idx_company (company_id),
                INDEX idx_rating (rating),
                INDEX idx_published (is_published)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Admin logs table
            "CREATE TABLE IF NOT EXISTS admin_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_table VARCHAR(50),
                target_id INT,
                old_values JSON,
                new_values JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_admin (admin_user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($tables as $sql) {
            $this->conn->exec($sql);
        }

        // Insert initial data
        $this->insertInitialData();
    }

    /**
     * Insert initial data for testing
     */
    private function insertInitialData() {
        // Insert cities
        $cities = [
            ['addis-ababa', 'Addis Ababa', 'Addis Ababa', 9.0320, 38.7469],
            ['bahir-dar', 'Bahir Dar', 'Amhara', 11.5942, 37.3906],
            ['gondar', 'Gondar', 'Amhara', 12.6090, 37.4671],
            ['mekele', 'Mekele', 'Tigray', 13.4967, 39.4753],
            ['hawassa', 'Hawassa', 'SNNPR', 7.0621, 38.4776],
            ['dire-dawa', 'Dire Dawa', 'Dire Dawa', 9.5931, 41.8661],
            ['jimma', 'Jimma', 'Oromia', 7.6731, 36.8344],
            ['adama', 'Adama (Nazreth)', 'Oromia', 8.5400, 39.2675],
            ['dessie', 'Dessie', 'Amhara', 11.1300, 39.6333],
            ['kombolcha', 'Kombolcha', 'Amhara', 11.0817, 39.7436],
            ['arbaminch', 'Arba Minch', 'SNNPR', 6.0333, 37.5500]
        ];

        $cityStmt = $this->conn->prepare("INSERT IGNORE INTO cities (city_code, name, region, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
        foreach ($cities as $city) {
            $cityStmt->execute($city);
        }

        // Insert bus companies
        $companies = [
            ['selam-bus', 'Selam Bus', 'Premium bus service with luxury amenities', 4.8],
            ['abay-bus', 'Abay Bus', 'Reliable northern routes specialist', 4.5],
            ['sky-bus', 'Sky Bus', 'Modern fleet with excellent service', 4.6],
            ['ethio-bus', 'Ethio Bus', 'Budget-friendly nationwide coverage', 4.2],
            ['habesha-bus', 'Habesha Bus', 'Cultural experience with comfort', 4.4],
            ['zemen-bus', 'Zemen Bus', 'Fast and efficient travel', 4.3]
        ];

        $companyStmt = $this->conn->prepare("INSERT IGNORE INTO bus_companies (company_id, name, description, rating) VALUES (?, ?, ?, ?)");
        foreach ($companies as $company) {
            $companyStmt->execute($company);
        }

        // Create admin user if not exists
        $adminEmail = 'admin@swiftbus.et';
        $checkAdmin = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkAdmin->execute([$adminEmail]);
        
        if (!$checkAdmin->fetch()) {
            $adminStmt = $this->conn->prepare("
                INSERT INTO users (user_id, email, password_hash, first_name, last_name, full_name, role, is_verified, joined_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $adminStmt->execute([
                'U' . time(),
                $adminEmail,
                password_hash('admin123', PASSWORD_DEFAULT),
                'System',
                'Administrator',
                'System Administrator',
                'admin',
                1,
                date('Y-m-d')
            ]);
        }
    }
}

// Initialize database on first load
if (!isset($_SESSION['db_initialized'])) {
    $db = new Database();
    if ($db->initializeDatabase()) {
        $_SESSION['db_initialized'] = true;
    }
}
?>