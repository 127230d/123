/*
  # File Sharing Platform Database Schema

  ## Tables Overview
  1. users - User accounts and profiles
  2. files - Shared files with all metadata
  3. file_purchases - Purchase transactions history
  4. file_ratings - Star ratings for files
  5. file_reviews - User reviews and comments
  6. user_balances - User wallet balances
  7. transactions - All financial transactions
  8. activity_logs - System activity logging

  ## Features
  - Complete file sharing system
  - Preview system (text/image)
  - Rating system with stars (1-5)
  - Review system with comments
  - Transaction tracking
  - User balance management
*/

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS users_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE users_app;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    profile_image VARCHAR(255),
    bio TEXT,
    balance DECIMAL(10,2) DEFAULT 0.00,
    total_uploads INT DEFAULT 0,
    total_sales INT DEFAULT 0,
    total_purchases INT DEFAULT 0,
    account_status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_account_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files table (renamed from shared_files for clarity)
CREATE TABLE IF NOT EXISTS files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) UNIQUE NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,

    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    tags VARCHAR(500),

    preview_type ENUM('text', 'image') NOT NULL DEFAULT 'text',
    preview_text TEXT,
    preview_image VARCHAR(255),

    price DECIMAL(10,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    final_price DECIMAL(10,2) NOT NULL,

    owner_id VARCHAR(50) NOT NULL,

    total_downloads INT DEFAULT 0,
    total_views INT DEFAULT 0,
    total_sales INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,

    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    total_reviews INT DEFAULT 0,

    review_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by VARCHAR(50),
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT,

    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_id) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_owner (owner_id),
    INDEX idx_review_status (review_status),
    INDEX idx_price (price),
    INDEX idx_rating (average_rating),
    INDEX idx_created (created_at),
    INDEX idx_active (is_active),
    INDEX idx_category (category),
    FULLTEXT INDEX idx_search (title, description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File purchases table
CREATE TABLE IF NOT EXISTS file_purchases (
    purchase_id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    buyer_id VARCHAR(50) NOT NULL,
    seller_id VARCHAR(50) NOT NULL,

    purchase_price DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,
    seller_amount DECIMAL(10,2) NOT NULL,

    transaction_reference VARCHAR(100) UNIQUE NOT NULL,
    payment_status ENUM('completed', 'pending', 'failed', 'refunded') DEFAULT 'completed',

    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (file_id) REFERENCES files(file_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_file (file_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_seller (seller_id),
    INDEX idx_purchased (purchased_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File ratings table (star ratings)
CREATE TABLE IF NOT EXISTS file_ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    rating_value INT NOT NULL CHECK (rating_value BETWEEN 1 AND 5),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (file_id) REFERENCES files(file_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_user_rating (file_id, user_id),
    INDEX idx_file (file_id),
    INDEX idx_user (user_id),
    INDEX idx_rating (rating_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File reviews table (comments)
CREATE TABLE IF NOT EXISTS file_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,

    review_text TEXT NOT NULL,
    is_verified_purchase BOOLEAN DEFAULT FALSE,

    helpful_count INT DEFAULT 0,
    reported_count INT DEFAULT 0,

    review_status ENUM('visible', 'hidden', 'reported') DEFAULT 'visible',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (file_id) REFERENCES files(file_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_file (file_id),
    INDEX idx_user (user_id),
    INDEX idx_status (review_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_reference VARCHAR(100) UNIQUE NOT NULL,

    user_id VARCHAR(50) NOT NULL,
    transaction_type ENUM('purchase', 'sale', 'deposit', 'withdrawal', 'refund', 'commission') NOT NULL,

    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,

    related_file_id INT,
    related_purchase_id INT,

    description VARCHAR(255),
    metadata JSON,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (related_file_id) REFERENCES files(file_id) ON DELETE SET NULL,
    FOREIGN KEY (related_purchase_id) REFERENCES file_purchases(purchase_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (transaction_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    action_type VARCHAR(50) NOT NULL,
    action_details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, full_name, is_admin, balance)
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', TRUE, 10000.00)
ON DUPLICATE KEY UPDATE username=username;
