-- Create databases for each service
-- This script runs automatically when MySQL container starts for the first time

CREATE DATABASE IF NOT EXISTS `wp_home_site` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `wp_customer_sites` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
