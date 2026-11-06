-- TESCON membership database (MySQL 8.0+). Create a dedicated least-privilege
-- application account and load its credentials through environment variables.
-- Grant the runtime account SELECT/INSERT on audit_logs only; never grant it
-- UPDATE, DELETE, DROP, ALTER, or TRIGGER privileges on the audit table.

CREATE DATABASE IF NOT EXISTS tescon_membership 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE tescon_membership;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'auditor') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id), 
    UNIQUE KEY uq_users_username (username), 
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    index_number VARCHAR(40) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    institution VARCHAR(150) NOT NULL,
    program_of_study VARCHAR(150) NOT NULL,
    level VARCHAR(40) NOT NULL,
    region VARCHAR(100) NOT NULL,
    constituency VARCHAR(100) NOT NULL,
    verification_photo VARCHAR(69) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(500) NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id), 
    UNIQUE KEY uq_members_index (index_number), 
    UNIQUE KEY uq_members_email (email),
    KEY idx_members_status_deleted (status, is_deleted), 
    KEY idx_members_name (full_name), 
    KEY idx_members_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leaders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    position VARCHAR(120) NOT NULL,
    category ENUM('npp_national', 'regional', 'tescon') NOT NULL,
    region VARCHAR(100) NULL,
    image_url VARCHAR(2048) NULL,
    bio TEXT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
    PRIMARY KEY (id),
    KEY idx_leaders_category_status_order (category, status, display_order),
    KEY idx_leaders_region (region)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('general', 'urgent', 'event', 'meeting') NOT NULL DEFAULT 'general',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('published', 'draft', 'archived') NOT NULL DEFAULT 'published',
    author_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_status_pinned (status, is_pinned, created_at),
    KEY idx_announcements_category (category),
    CONSTRAINT fk_announcements_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    actor_type ENUM('public', 'member', 'admin', 'auditor') NOT NULL,
    action_type VARCHAR(30) NOT NULL,
    target_table VARCHAR(64) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id), 
    KEY idx_audit_created (created_at), 
    KEY idx_audit_action (action_type), 
    KEY idx_audit_actor (actor_type, actor_id), 
    KEY idx_audit_target (target_table, target_id),
    KEY idx_audit_rate_limit (action_type, ip_address, created_at),
    KEY idx_audit_action_created (action_type, created_at)
) ENGINE=InnoDB;

-- Defense in depth: even accidental application SQL cannot mutate or remove a log.
DROP TRIGGER IF EXISTS audit_logs_no_update;
DROP TRIGGER IF EXISTS audit_logs_no_delete;

DELIMITER $$

CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW
BEGIN 
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are append-only'; 
END$$

CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW
BEGIN 
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are append-only'; 
END$$

DELIMITER ;

-- Local evaluation accounts use password hashes; change their passwords before deployment.
INSERT INTO users(username, email, password_hash, role, status) VALUES
('admin', 'admin@example.invalid', '$2y$10$3ymA7FLYJQQfjxIOyFmMC.wuUM9jfrMJHV39JcZU.TV1dzDIHY8B2', 'admin', 'active'),
('auditor', 'auditor@example.invalid', '$2y$10$2BmKdlzJLHFSfnaBPjWKtepnGdkRqVReNTap/63el0Sv6kuKp.dcW', 'auditor', 'active')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Local evaluation member records use a shared password hash; rotate it before deployment.
INSERT INTO members(full_name, index_number, phone_number, email, password_hash, institution, program_of_study, level, region, constituency, status) VALUES
('Ama Serwaa Mensah', 'UG-2024-001', '0241234567', 'ama.mensah@example.invalid', '$2y$10$B7ZRVpcXM1GVv6S/t3jLheUtmQawyIaS9/B/lzlqgMeRzZnYajxZe', 'University of Ghana', 'Political Science', 'Level 200', 'Greater Accra', 'Ayawaso West Wuogon', 'pending'),
('Kwame Owusu Boateng', 'KNUST/2023/014', '0551234567', 'kwame.boateng@example.invalid', '$2y$10$B7ZRVpcXM1GVv6S/t3jLheUtmQawyIaS9/B/lzlqgMeRzZnYajxZe', 'Kwame Nkrumah University of Science and Technology', 'Computer Science', 'Level 300', 'Ashanti', 'Kumasi Central', 'approved'),
('Akosua Adjei', 'UCC-2022-044', '0201234567', 'akosua.adjei@example.invalid', '$2y$10$B7ZRVpcXM1GVv6S/t3jLheUtmQawyIaS9/B/lzlqgMeRzZnYajxZe', 'University of Cape Coast', 'Education', 'Level 400', 'Central', 'Cape Coast North', 'approved')
ON DUPLICATE KEY UPDATE index_number = VALUES(index_number);

-- Seed announcements
INSERT INTO announcements (id, title, content, category, is_pinned, status, author_id, created_at) VALUES
(1, 'Mandatory General Meeting & Orientation for 2026/2027 Academic Year', 
 'All registered and prospective TESCON ATU members are invited to our first general meeting and welcoming orientation scheduled for Friday at 3:00 PM at the Main Auditorium. Agenda includes executive briefings, upcoming voter sensitization exercises, and distribution of physical badges.', 
 'meeting', 1, 'published', 1, UTC_TIMESTAMP()),
(2, 'TESCON ATU Campus Voter Sensitization & Registration Drive', 
 'Join the outreach committee this Saturday as we conduct peer sensitization on campus and around the hostel areas. Refreshments and materials will be provided for all volunteers.', 
 'event', 0, 'published', 1, UTC_TIMESTAMP()),
(3, 'Collection of Official Membership Badges and Cards', 
 'Members whose online membership application has been marked as \'Approved\' can download their digital ID card directly from this portal or present their digital card at the secretariat office (Room 204) to collect their laminated card.', 
 'general', 0, 'published', 1, UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Inactive examples document the expected leadership data shape without
-- publishing invented office holders. Replace them with verified profiles
-- before changing their status to active.
INSERT INTO leaders(full_name, position, category, region, image_url, bio, display_order, status)
SELECT sample.full_name, sample.position, sample.category, sample.region, sample.image_url, sample.bio, sample.display_order, sample.status
FROM (
    SELECT
        'Sample national executive' AS full_name,
        'National Chairman' AS position,
        'npp_national' AS category,
        NULL AS region,
        NULL AS image_url,
        'Replace this example with a verified profile before publication.' AS bio,
        1 AS display_order,
        'inactive' AS status
    UNION ALL SELECT
        'Sample national executive',
        'General Secretary',
        'npp_national',
        NULL,
        NULL,
        'Replace this example with a verified profile before publication.',
        2,
        'inactive'
    UNION ALL SELECT
        'Sample national executive',
        'National Organizer',
        'npp_national',
        NULL,
        NULL,
        'Replace this example with a verified profile before publication.',
        3,
        'inactive'
    UNION ALL SELECT
        'Sample TESCON leader',
        'TESCON National Executive',
        'tescon',
        NULL,
        NULL,
        'Replace this example with a verified profile before publication.',
        1,
        'inactive'
    UNION ALL SELECT
        'Sample campus leader',
        'Campus Executive',
        'tescon',
        NULL,
        NULL,
        'Replace this example with a verified profile before publication.',
        2,
        'inactive'
) AS sample
WHERE NOT EXISTS (
    SELECT 1
    FROM leaders existing
    WHERE existing.category = sample.category
      AND existing.position = sample.position
      AND existing.region <=> sample.region
);

-- ─── Migrations: run once against an existing database ──────────────────────
-- If you imported this schema prior to portal enhancements, run:
--
-- ALTER TABLE members
--     ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER status;
--
-- ALTER TABLE members
--     ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email;
--
-- ALTER TABLE audit_logs
--     MODIFY COLUMN actor_type ENUM('public', 'member', 'admin', 'auditor') NOT NULL;
-- ────────────────────────────────────────────────────────────────────────────