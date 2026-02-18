-- Create databases for each service
-- This script runs automatically when MySQL container starts for the first time

CREATE DATABASE IF NOT EXISTS `wp_home_site` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `wp_admin_site` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `laravel_api` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Note: User creation should be done via Docker environment variables
-- or manually for security reasons. Add users here only for development.

-- Example for development (DO NOT use these passwords in production):
-- CREATE USER IF NOT EXISTS 'wp_home_user'@'%' IDENTIFIED BY 'dev_password';
-- GRANT ALL PRIVILEGES ON `wp_home_site`.* TO 'wp_home_user'@'%';

-- CREATE USER IF NOT EXISTS 'wp_admin_user'@'%' IDENTIFIED BY 'dev_password';
-- GRANT ALL PRIVILEGES ON `wp_admin_site`.* TO 'wp_admin_user'@'%';

-- CREATE USER IF NOT EXISTS 'laravel_user'@'%' IDENTIFIED BY 'dev_password';
-- GRANT ALL PRIVILEGES ON `laravel_api`.* TO 'laravel_user'@'%';

-- FLUSH PRIVILEGES;
