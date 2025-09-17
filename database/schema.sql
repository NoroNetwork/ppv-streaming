-- PPV Streaming Platform Database Schema

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email)
);

-- Streams table
CREATE TABLE streams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    stream_key VARCHAR(255) UNIQUE NOT NULL,
    rtmp_url TEXT,
    hls_url TEXT,
    status ENUM('inactive', 'active', 'ended') DEFAULT 'inactive',
    scheduled_start TIMESTAMP NULL,
    actual_start TIMESTAMP NULL,
    actual_end TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_stream_key (stream_key)
);

-- Stream purchases/access table
CREATE TABLE stream_access (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    stream_id INT NOT NULL,
    payment_intent_id VARCHAR(255),
    amount_paid DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    access_granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (stream_id) REFERENCES streams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_stream (user_id, stream_id),
    INDEX idx_user_id (user_id),
    INDEX idx_stream_id (stream_id),
    INDEX idx_payment_intent (payment_intent_id)
);

-- Stream statistics table
CREATE TABLE stream_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stream_id INT NOT NULL,
    viewer_count INT DEFAULT 0,
    peak_viewers INT DEFAULT 0,
    total_revenue DECIMAL(10, 2) DEFAULT 0.00,
    total_purchases INT DEFAULT 0,
    bandwidth_usage BIGINT DEFAULT 0, -- in bytes
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stream_id) REFERENCES streams(id) ON DELETE CASCADE,
    INDEX idx_stream_id (stream_id),
    INDEX idx_recorded_at (recorded_at)
);

-- Session tokens table (for token blacklisting if needed)
CREATE TABLE token_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token_hash VARCHAR(64) NOT NULL,
    blacklisted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (email, password, role) VALUES
('admin@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewTuVMWuuKaVBL4u', 'admin');

-- Insert sample stream
INSERT INTO streams (title, description, price, stream_key, status) VALUES
('Sample Live Stream', 'A sample streaming event for testing', 9.99, 'sample-stream-2024', 'inactive');