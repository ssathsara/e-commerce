-- Runs once when the MySQL volume is first created.
-- Separate database for the automated test suite (phpunit.xml uses ecommerce_testing).
CREATE DATABASE IF NOT EXISTS ecommerce_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ecommerce_testing.* TO 'ecommerce'@'%';
FLUSH PRIVILEGES;
