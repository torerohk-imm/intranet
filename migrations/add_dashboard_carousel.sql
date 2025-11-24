-- Migration: Add dashboard carousel table
-- Description: Creates table for storing carousel media items (images/videos) for dashboard display
-- Permissions: Only Publisher and Admin roles can manage carousel items

CREATE TABLE IF NOT EXISTS dashboard_carousel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NULL COMMENT 'Optional caption for the media item',
    file_path VARCHAR(255) NOT NULL COMMENT 'Path to uploaded media file',
    media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    display_order INT NOT NULL DEFAULT 0 COMMENT 'Order in which items appear in carousel',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether item is visible in carousel',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
