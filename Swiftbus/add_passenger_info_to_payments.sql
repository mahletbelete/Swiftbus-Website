-- =====================================================
-- Add Passenger Information to Payments Table
-- =====================================================
-- This script adds passenger_name and passenger_email columns
-- to the payments table for better record keeping
-- =====================================================

USE `swiftbus_db`;

-- Add passenger_name column
ALTER TABLE `payments` 
ADD COLUMN `passenger_name` varchar(200) DEFAULT NULL AFTER `booking_id`;

-- Add passenger_email column  
ALTER TABLE `payments`
ADD COLUMN `passenger_email` varchar(255) DEFAULT NULL AFTER `passenger_name`;

-- Add index for passenger email for faster searches
ALTER TABLE `payments`
ADD KEY `idx_passenger_email` (`passenger_email`);

-- Update existing records with passenger info from bookings table
UPDATE `payments` p
JOIN `bookings` b ON p.booking_id = b.booking_id
SET 
    p.passenger_name = JSON_UNQUOTE(JSON_EXTRACT(b.passenger_details, '$.fullName')),
    p.passenger_email = JSON_UNQUOTE(JSON_EXTRACT(b.passenger_details, '$.email'))
WHERE p.passenger_name IS NULL;

-- Show the updated table structure
DESCRIBE `payments`;