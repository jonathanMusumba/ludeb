-- Public User System Database Schema
-- This schema supports the public registration, login, and resource access system

-- 1. Public Users Table
CREATE TABLE `public_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `first_name` varchar(100) NOT NULL,
    `last_name` varchar(100) NOT NULL,
    `email` varchar(255) NOT NULL UNIQUE,
    `phone` varchar(20) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('student', 'parent', 'teacher', 'individual') NOT NULL,
    `school_name` varchar(255) DEFAULT NULL,
    `class_level` varchar(10) DEFAULT NULL,
    `subject_specialization` varchar(255) DEFAULT NULL,
    `access_level` enum('free', 'premium', 'premium_pending') DEFAULT 'free',
    `payment_status` enum('none', 'pending_verification', 'verified', 'expired', 'cancelled') DEFAULT 'none',
    `payment_method` enum('mobile_money', 'bank_transfer', 'cash') DEFAULT NULL,
    `payment_reference` varchar(255) DEFAULT NULL,
    `payment_amount` decimal(10,2) DEFAULT NULL,
    `payment_date` datetime DEFAULT NULL,
    `premium_expires` datetime DEFAULT NULL,
    `status` enum('active', 'inactive', 'suspended') DEFAULT 'active',
    `email_verified` tinyint(1) DEFAULT 0,
    `email_verification_token` varchar(255) DEFAULT NULL,
    `remember_token` varchar(255) DEFAULT NULL,
    `remember_token_expires` datetime DEFAULT NULL,
    `failed_login_attempts` int(3) DEFAULT 0,
    `last_login` datetime DEFAULT NULL,
    `registration_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_access_level` (`access_level`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Resources Table (for public access)
CREATE TABLE `resources` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `description` text,
    `category` varchar(100) NOT NULL,
    `subject` varchar(100) DEFAULT NULL,
    `class_level` varchar(10) DEFAULT NULL,
    `resource_type` enum('PDF', 'Video', 'Document', 'Audio', 'Interactive') NOT NULL,
    `file_path` varchar(500) NOT NULL,
    `file_size` bigint(20) DEFAULT NULL,
    `thumbnail_path` varchar(500) DEFAULT NULL,
    `is_premium` tinyint(1) DEFAULT 0,
    `download_count` int(11) DEFAULT 0,
    `rating` decimal(3,2) DEFAULT 0.00,
    `rating_count` int(11) DEFAULT 0,
    `tags` text DEFAULT NULL,
    `status` enum('active', 'inactive', 'under_review') DEFAULT 'active',
    `uploaded_by` int(11) DEFAULT NULL,
    `upload_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_subject` (`subject`),
    KEY `idx_class_level` (`class_level`),
    KEY `idx_is_premium` (`is_premium`),
    KEY `idx_status` (`status`),
    FULLTEXT KEY `idx_search` (`title`, `description`, `tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. User Downloads Table
CREATE TABLE `user_downloads` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `resource_id` int(11) NOT NULL,
    `resource_name` varchar(255) NOT NULL,
    `resource_type` varchar(50) NOT NULL,
    `download_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_resource_id` (`resource_id`),
    KEY `idx_download_date` (`download_date`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. User Progress Tracking Table
CREATE TABLE `user_progress` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `subject` varchar(100) NOT NULL,
    `topic` varchar(255) NOT NULL,
    `completion_percentage` decimal(5,2) DEFAULT 0.00,
    `time_spent` int(11) DEFAULT 0, -- in minutes
    `last_accessed` datetime DEFAULT CURRENT_TIMESTAMP,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_progress` (`user_id`, `subject`, `topic`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_subject` (`subject`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Resource Reviews/Ratings Table
CREATE TABLE `resource_reviews` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `resource_id` int(11) NOT NULL,
    `rating` int(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
    `review_text` text DEFAULT NULL,
    `is_helpful` tinyint(1) DEFAULT 1,
    `status` enum('active', 'hidden', 'flagged') DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_resource` (`user_id`, `resource_id`),
    KEY `idx_resource_id` (`resource_id`),
    KEY `idx_rating` (`rating`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. User Sessions Table (for enhanced security)
CREATE TABLE `user_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `session_id` varchar(255) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` text NOT NULL,
    `login_time` datetime DEFAULT CURRENT_TIMESTAMP,
    `last_activity` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_session` (`session_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_active` (`is_active`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Admin Notifications Table (for payment verifications)
CREATE TABLE `admin_notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type` enum('payment_verification', 'new_registration', 'report', 'system') NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `related_user_id` int(11) DEFAULT NULL,
    `related_transaction_id` int(11) DEFAULT NULL,
    `status` enum('unread', 'read', 'archived') DEFAULT 'unread',
    `priority` enum('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `read_at` datetime DEFAULT NULL,
    `read_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_priority` (`priority`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`related_user_id`) REFERENCES `public_users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`related_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. System Announcements Table
CREATE TABLE `system_announcements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `content` text NOT NULL,
    `type` enum('info', 'warning', 'success', 'danger') DEFAULT 'info',
    `target_audience` enum('all', 'students', 'teachers', 'parents', 'premium_only') DEFAULT 'all',
    `is_active` tinyint(1) DEFAULT 1,
    `start_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `end_date` datetime DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_target_audience` (`target_audience`),
    KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. User Activity Log Table
CREATE TABLE `user_activity_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `activity_type` enum('login', 'logout', 'download', 'view', 'search', 'profile_update') NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `resource_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_activity_type` (`activity_type`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. User Study Goals Table
CREATE TABLE `user_study_goals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `goal_type` enum('weekly_hours', 'monthly_downloads', 'subject_completion', 'test_scores') NOT NULL,
    `goal_title` varchar(255) NOT NULL,
    `target_value` decimal(10,2) NOT NULL,
    `current_value` decimal(10,2) DEFAULT 0.00,
    `unit` varchar(50) DEFAULT NULL,
    `target_date` date DEFAULT NULL,
    `status` enum('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Resource Categories Table
CREATE TABLE `resource_categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `parent_id` int(11) DEFAULT NULL,
    `icon` varchar(100) DEFAULT NULL,
    `color` varchar(7) DEFAULT NULL,
    `sort_order` int(3) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_is_active` (`is_active`),
    FOREIGN KEY (`parent_id`) REFERENCES `resource_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Password Reset Tokens Table
CREATE TABLE `password_reset_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `token` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `used` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_token` (`token`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. System Settings Table
CREATE TABLE `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text DEFAULT NULL,
    `setting_type` enum('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    `description` varchar(255) DEFAULT NULL,
    `is_editable` tinyint(1) DEFAULT 1,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('premium_price', '50000', 'integer', 'Premium subscription price in UGX'),
('premium_duration_days', '365', 'integer', 'Premium subscription duration in days'),
('max_free_downloads_per_day', '5', 'integer', 'Maximum free downloads per user per day'),
('max_free_downloads_per_month', '50', 'integer', 'Maximum free downloads per user per month'),
('email_verification_required', '1', 'boolean', 'Whether email verification is required for new accounts'),
('maintenance_mode', '0', 'boolean', 'Enable/disable maintenance mode'),
('registration_enabled', '1', 'boolean', 'Enable/disable new user registration'),
('contact_email', 'jmprossy@gmail.com', 'string', 'Primary contact email'),
('contact_phone', '+256777115678', 'string', 'Primary contact phone'),
('site_name', 'Luuka Examination Board', 'string', 'Site name for emails and notifications');

-- Insert default resource categories
INSERT INTO `resource_categories` (`name`, `description`, `icon`, `color`, `sort_order`) VALUES
('Primary Education', 'Resources for Primary 1-7 students', 'fas fa-child', '#ff6b6b', 1),
('Secondary Education', 'Resources for Secondary 1-6 students', 'fas fa-user-graduate', '#4ecdc4', 2),
('Past Papers', 'Previous examination papers and solutions', 'fas fa-file-pdf', '#3498db', 3),
('Study Guides', 'Comprehensive revision materials', 'fas fa-book-open', '#9b59b6', 4),
('Video Lessons', 'Interactive video tutorials', 'fas fa-play-circle', '#e74c3c', 5),
('Practice Tests', 'Assessment and practice materials', 'fas fa-clipboard-check', '#f39c12', 6),
('Teacher Resources', 'Materials for educators', 'fas fa-chalkboard-teacher', '#27ae60', 7),
('Parent Guides', 'Resources for parents and guardians', 'fas fa-users', '#34495e', 8);

-- Sample resources data
INSERT INTO `resources` (`title`, `description`, `category`, `subject`, `class_level`, `resource_type`, `file_path`, `is_premium`, `tags`) VALUES
('Primary 1 Mathematics Workbook', 'Complete mathematics workbook for Primary 1 students', 'Primary Education', 'Mathematics', 'P1', 'PDF', 'uploads/primary/p1_math_workbook.pdf', 0, 'mathematics,primary,workbook,numbers'),
('Primary 7 Science Past Papers 2023', 'Complete set of Primary 7 science examination papers from 2023', 'Past Papers', 'Science', 'P7', 'PDF', 'uploads/pastpapers/p7_science_2023.pdf', 1, 'science,primary,past papers,2023'),
('S1 English Grammar Guide', 'Comprehensive English grammar guide for Senior 1 students', 'Study Guides', 'English', 'S1', 'PDF', 'uploads/secondary/s1_english_grammar.pdf', 0, 'english,grammar,secondary,guide'),
('Advanced Mathematics Video Series', 'Complete video tutorial series for A-Level mathematics', 'Video Lessons', 'Mathematics', 'S5', 'Video', 'uploads/videos/advanced_math_series/', 1, 'mathematics,video,advanced,tutorials'),
('UCE 2023 Mathematics Paper 1', 'Uganda Certificate of Education Mathematics Paper 1 from 2023', 'Past Papers', 'Mathematics', 'S4', 'PDF', 'uploads/pastpapers/uce_2023_math_p1.pdf', 1, 'mathematics,uce,2023,past papers');

-- Create indexes for better performance
CREATE INDEX idx_resources_search ON resources(category, subject, class_level, is_premium);
CREATE INDEX idx_users_access ON public_users(access_level, payment_status, status);
CREATE INDEX idx_downloads_stats ON user_downloads(user_id, download_date);

-- Create triggers for automatic updates

-- Trigger to update resource download count
DELIMITER //
CREATE TRIGGER update_download_count 
AFTER INSERT ON user_downloads
FOR EACH ROW
BEGIN
    UPDATE resources 
    SET download_count = download_count + 1 
    WHERE id = NEW.resource_id;
END//
DELIMITER ;

-- Trigger to update resource rating
DELIMITER //
CREATE TRIGGER update_resource_rating 
AFTER INSERT ON resource_reviews
FOR EACH ROW
BEGIN
    UPDATE resources 
    SET rating = (
        SELECT AVG(rating) 
        FROM resource_reviews 
        WHERE resource_id = NEW.resource_id AND status = 'active'
    ),
    rating_count = (
        SELECT COUNT(*) 
        FROM resource_reviews 
        WHERE resource_id = NEW.resource_id AND status = 'active'
    )
    WHERE id = NEW.resource_id;
END//
DELIMITER ;

-- Trigger to create admin notification for new payment
DELIMITER //
CREATE TRIGGER create_payment_notification 
AFTER INSERT ON payment_transactions
FOR EACH ROW
BEGIN
    INSERT INTO admin_notifications (
        type, title, message, related_user_id, related_transaction_id, priority
    ) VALUES (
        'payment_verification',
        'New Payment Verification Required',
        CONCAT('Payment verification needed for user ID: ', NEW.user_id, ' - Amount: UGX ', NEW.amount),
        NEW.user_id,
        NEW.id,
        'high'
    );
END//
DELIMITER ;

-- Trigger to update user access level when payment is verified
DELIMITER //
CREATE TRIGGER update_user_access_on_payment 
AFTER UPDATE ON payment_transactions
FOR EACH ROW
BEGIN
    IF NEW.status = 'verified' AND OLD.status != 'verified' THEN
        UPDATE public_users 
        SET 
            access_level = 'premium',
            payment_status = 'verified',
            payment_date = NEW.verification_date,
            premium_expires = DATE_ADD(NEW.verification_date, INTERVAL 365 DAY)
        WHERE id = NEW.user_id;
    END IF;
END//
DELIMITER ;

-- Views for easier data access

-- View for user statistics
CREATE VIEW user_stats_view AS
SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.role,
    u.access_level,
    u.registration_date,
    COALESCE(d.download_count, 0) as total_downloads,
    COALESCE(f.favorite_count, 0) as total_favorites,
    COALESCE(p.avg_progress, 0) as average_progress,
    u.last_login
FROM public_users u
LEFT JOIN (
    SELECT user_id, COUNT(*) as download_count 
    FROM user_downloads 
    GROUP BY user_id
) d ON u.id = d.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) as favorite_count 
    FROM user_favorites 
    GROUP BY user_id
) f ON u.id = f.user_id
LEFT JOIN (
    SELECT user_id, AVG(completion_percentage) as avg_progress 
    FROM user_progress 
    GROUP BY user_id
) p ON u.id = p.user_id;

-- View for resource statistics
CREATE VIEW resource_stats_view AS
SELECT 
    r.id,
    r.title,
    r.category,
    r.subject,
    r.class_level,
    r.resource_type,
    r.is_premium,
    r.download_count,
    r.rating,
    r.rating_count,
    COALESCE(d.recent_downloads, 0) as downloads_last_30_days,
    COALESCE(f.favorite_count, 0) as favorite_count
FROM resources r
LEFT JOIN (
    SELECT resource_id, COUNT(*) as recent_downloads 
    FROM user_downloads 
    WHERE download_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY resource_id
) d ON r.id = d.resource_id
LEFT JOIN (
    SELECT resource_id, COUNT(*) as favorite_count 
    FROM user_favorites 
    GROUP BY resource_id
) f ON r.id = f.resource_id;

-- Create stored procedures for common operations

-- Procedure to verify payment and upgrade user
DELIMITER //
CREATE PROCEDURE VerifyPayment(
    IN p_transaction_id INT,
    IN p_verified_by INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_user_id INT;
    DECLARE v_amount DECIMAL(10,2);
    
    START TRANSACTION;
    
    -- Get transaction details
    SELECT user_id, amount INTO v_user_id, v_amount
    FROM payment_transactions 
    WHERE id = p_transaction_id AND status = 'pending';
    
    IF v_user_id IS NOT NULL THEN
        -- Update transaction status
        UPDATE payment_transactions 
        SET 
            status = 'verified',
            verified_by = p_verified_by,
            verification_date = NOW(),
            verification_notes = p_notes
        WHERE id = p_transaction_id;
        
        -- Update user access (this will be handled by trigger)
        -- Log the activity
        INSERT INTO user_activity_log (user_id, activity_type, description)
        VALUES (v_user_id, 'profile_update', 'Premium access activated');
        
        COMMIT;
        SELECT 'Payment verified successfully' as message;
    ELSE
        ROLLBACK;
        SELECT 'Transaction not found or already processed' as message;
    END IF;
END//
DELIMITER ;

-- Procedure to clean up expired sessions
DELIMITER //
CREATE PROCEDURE CleanupExpiredSessions()
BEGIN
    -- Remove sessions older than 7 days
    DELETE FROM user_sessions 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Remove expired remember tokens
    UPDATE public_users 
    SET remember_token = NULL, remember_token_expires = NULL
    WHERE remember_token_expires < NOW();
    
    -- Remove expired password reset tokens
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW() OR used = 1;
    
    SELECT ROW_COUNT() as cleaned_records;
END//
DELIMITER ;

-- Procedure to get user dashboard data
DELIMITER //
CREATE PROCEDURE GetUserDashboardData(IN p_user_id INT)
BEGIN
    -- User basic info with stats
    SELECT 
        u.*,
        COALESCE(stats.total_downloads, 0) as downloads_count,
        COALESCE(stats.total_favorites, 0) as favorites_count,
        COALESCE(stats.average_progress, 0) as avg_progress
    FROM public_users u
    LEFT JOIN user_stats_view stats ON u.id = stats.id
    WHERE u.id = p_user_id;
    
    -- Recent downloads
    SELECT 
        ud.resource_name,
        ud.resource_type,
        ud.download_date,
        r.id as resource_id,
        r.file_path
    FROM user_downloads ud
    LEFT JOIN resources r ON ud.resource_id = r.id
    WHERE ud.user_id = p_user_id
    ORDER BY ud.download_date DESC
    LIMIT 10;
    
    -- Available resource counts by category
    SELECT 
        category,
        COUNT(*) as count,
        SUM(CASE WHEN is_premium = 0 THEN 1 ELSE 0 END) as free_count,
        SUM(CASE WHEN is_premium = 1 THEN 1 ELSE 0 END) as premium_count
    FROM resources 
    WHERE status = 'active'
    GROUP BY category
    ORDER BY category;
    
    -- User progress by subject
    SELECT 
        subject,
        AVG(completion_percentage) as avg_completion,
        SUM(time_spent) as total_time_spent,
        COUNT(*) as topics_count
    FROM user_progress 
    WHERE user_id = p_user_id
    GROUP BY subject
    ORDER BY avg_completion DESC;
END//
DELIMITER ;

-- Event scheduler for automatic cleanup (optional)
-- This requires the event scheduler to be enabled: SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS cleanup_expired_data
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
BEGIN
    -- Clean up expired sessions and tokens
    CALL CleanupExpiredSessions();
    
    -- Archive old activity logs (keep last 90 days)
    DELETE FROM user_activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Update expired premium accounts
    UPDATE public_users 
    SET access_level = 'free', payment_status = 'expired'
    WHERE access_level = 'premium' 
    AND premium_expires < NOW()
    AND payment_status = 'verified';
END;

-- Create admin user for payment verification (optional)
-- You should change the default password after first login
INSERT INTO `public_users` (
    `first_name`, `last_name`, `email`, `phone`, `password`, `role`, 
    `access_level`, `payment_status`, `status`
) VALUES (
    'Admin', 'User', 'admin@luukaboard.com', '+256777115678', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: 'password'
    'individual', 'premium', 'verified', 'active'
);

-- Performance optimization indexes
CREATE INDEX idx_user_downloads_monthly ON user_downloads(user_id, download_date);
CREATE INDEX idx_user_activity_recent ON user_activity_log(user_id, created_at);
CREATE INDEX idx_resources_popular ON resources(download_count DESC, rating DESC);

-- Full-text search index for resources
ALTER TABLE resources ADD FULLTEXT(title, description, tags);

-- Add constraints for data integrity
ALTER TABLE public_users 
ADD CONSTRAINT chk_email_format 
CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,} CASCADE,
    FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User Favorites Table
CREATE TABLE `user_favorites` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `resource_id` int(11) NOT NULL,
    `added_date` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_favorite` (`user_id`, `resource_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_resource_id` (`resource_id`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Payment Transactions Table
CREATE TABLE `payment_transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `transaction_reference` varchar(255) NOT NULL,
    `payment_method` enum('mobile_money', 'bank_transfer', 'cash') NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `currency` varchar(3) DEFAULT 'UGX',
    `status` enum('pending', 'verified', 'failed', 'cancelled') DEFAULT 'pending',
    `verified_by` int(11) DEFAULT NULL,
    `verification_date` datetime DEFAULT NULL,
    `verification_notes` text DEFAULT NULL,
    `transaction_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_reference` (`transaction_reference`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_transaction_date` (`transaction_date`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE);

ALTER TABLE payment_transactions
ADD CONSTRAINT chk_amount_positive 
CHECK (amount > 0);

ALTER TABLE user_progress
ADD CONSTRAINT chk_percentage_range 
CHECK (completion_percentage >= 0 AND completion_percentage <= 100);

-- Comments for documentation
ALTER TABLE public_users COMMENT = 'Stores public user registration and profile information';
ALTER TABLE resources COMMENT = 'Stores all educational resources and materials';
ALTER TABLE user_downloads COMMENT = 'Tracks user download history and analytics';
ALTER TABLE user_favorites COMMENT = 'Stores user favorite resources';
ALTER TABLE payment_transactions COMMENT = 'Handles premium subscription payments and verification';
ALTER TABLE user_progress COMMENT = 'Tracks individual user learning progress';
ALTER TABLE resource_reviews COMMENT = 'User reviews and ratings for resources';
ALTER TABLE admin_notifications COMMENT = 'System notifications for administrators';

-- Final note: Remember to:
-- 1. Update the database connection details in ../config/database.php
-- 2. Set up proper file upload directories with appropriate permissions
-- 3. Configure email settings for notifications
-- 4. Set up backup procedures for user data
-- 5. Implement proper logging and monitoring
-- 6. Configure SSL certificates for secure data transmission CASCADE,
    FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User Favorites Table
CREATE TABLE `user_favorites` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `resource_id` int(11) NOT NULL,
    `added_date` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_favorite` (`user_id`, `resource_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_resource_id` (`resource_id`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Payment Transactions Table
CREATE TABLE `payment_transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `transaction_reference` varchar(255) NOT NULL,
    `payment_method` enum('mobile_money', 'bank_transfer', 'cash') NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `currency` varchar(3) DEFAULT 'UGX',
    `status` enum('pending', 'verified', 'failed', 'cancelled') DEFAULT 'pending',
    `verified_by` int(11) DEFAULT NULL,
    `verification_date` datetime DEFAULT NULL,
    `verification_notes` text DEFAULT NULL,
    `transaction_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_reference` (`transaction_reference`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_transaction_date` (`transaction_date`),
    FOREIGN KEY (`user_id`) REFERENCES `public_users` (`id`) ON DELETE CASCADE