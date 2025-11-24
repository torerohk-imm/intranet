-- Migration: Add link categories functionality
-- Created: 2025-11-24

-- Create link_categories table
CREATE TABLE IF NOT EXISTS link_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(120) NULL,
    color VARCHAR(20) NULL,
    display_order INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Add category_id to quick_links table
ALTER TABLE quick_links 
ADD COLUMN category_id INT NULL AFTER icon,
ADD FOREIGN KEY (category_id) REFERENCES link_categories(id) ON DELETE SET NULL;

-- Insert some default categories (optional)
INSERT INTO link_categories (name, icon, color, display_order) VALUES
('Herramientas', 'bi bi-tools', '#3498db', 1),
('Documentos', 'bi bi-file-text', '#2ecc71', 2),
('Comunicación', 'bi bi-chat-dots', '#9b59b6', 3),
('Recursos', 'bi bi-box', '#e74c3c', 4);
