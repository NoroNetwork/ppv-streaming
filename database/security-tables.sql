-- Additional security tables for the streaming platform

-- Login attempts tracking
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, created_at),
    INDEX idx_ip_time (ip_address, created_at)
);

-- Security event logging
CREATE TABLE security_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    user_id INT NULL,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event (event),
    INDEX idx_ip (ip_address),
    INDEX idx_created_at (created_at)
);

-- API rate limiting
CREATE TABLE rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    attempts INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_identifier_endpoint (identifier, endpoint),
    INDEX idx_window_start (window_start)
);

-- Session management
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    data TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Webhook security
CREATE TABLE webhook_signatures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_id VARCHAR(255) NOT NULL,
    signature VARCHAR(255) NOT NULL,
    payload_hash VARCHAR(64) NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_id (webhook_id),
    INDEX idx_verified (verified)
);

-- Content moderation
CREATE TABLE moderation_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content_type ENUM('stream_title', 'stream_description', 'user_content') NOT NULL,
    content_id INT NOT NULL,
    original_content TEXT NOT NULL,
    flagged_reason VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_content_type (content_type)
);

-- IP blacklist/whitelist
CREATE TABLE ip_restrictions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    ip_range VARCHAR(50) NULL,
    type ENUM('blacklist', 'whitelist') NOT NULL,
    reason VARCHAR(255),
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_type (ip_address, type),
    INDEX idx_expires_at (expires_at)
);

-- User verification
CREATE TABLE user_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    verification_type ENUM('email', 'phone', 'identity') NOT NULL,
    verification_token VARCHAR(255) NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, verification_type),
    INDEX idx_token (verification_token),
    INDEX idx_expires_at (expires_at)
);

-- Add security columns to existing tables
ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN failed_login_attempts INT DEFAULT 0;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL;

-- Add indexes for security
ALTER TABLE users ADD INDEX idx_email_verified (email_verified);
ALTER TABLE users ADD INDEX idx_locked_until (locked_until);

-- Add security fields to streams
ALTER TABLE streams ADD COLUMN content_warning TEXT NULL;
ALTER TABLE streams ADD COLUMN age_restricted BOOLEAN DEFAULT FALSE;
ALTER TABLE streams ADD COLUMN moderation_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved';

-- Add payment security
ALTER TABLE stream_access ADD COLUMN refunded BOOLEAN DEFAULT FALSE;
ALTER TABLE stream_access ADD COLUMN refund_reason VARCHAR(255) NULL;
ALTER TABLE stream_access ADD COLUMN refunded_at TIMESTAMP NULL;