-- Table: audit_logs
-- Stores audit trail for setup actions
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    user_id INT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stored Procedure: log_action
DELIMITER //
CREATE PROCEDURE log_action (
    IN p_action VARCHAR(255),
    IN p_user_id INT,
    IN p_details TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details)
    VALUES (p_action, p_user_id, p_details);
END //
DELIMITER ;