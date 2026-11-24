-- Separate schema for the automated test suite, so tests never touch local data.
CREATE DATABASE IF NOT EXISTS financial_import_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON financial_import_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
