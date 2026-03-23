-- NOTE: Replace __TABLE_PREFIX__ with your desired prefix (e.g., echo_a_).
-- Example: __TABLE_PREFIX__users -> echo_a_users

-- 1. USERS TABLE
CREATE TABLE `__TABLE_PREFIX__users` (
  `user_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `alias` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `session_id` varchar(60) DEFAULT NULL,
  `is_admin` tinyint NOT NULL,
  `is_crew` tinyint NOT NULL,
  `last_login` datetime(3) DEFAULT NULL,
  `is_password_reset` tinyint NOT NULL DEFAULT '1',
  `is_active` tinyint NOT NULL DEFAULT '1',
  `preferences` varchar(255) NOT NULL DEFAULT '',
  `failed_attempts` int NOT NULL DEFAULT '0',
  `lockout_until` datetime(3) DEFAULT NULL,
  `last_failed_attempt` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY idx_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. CONVERSATIONS TABLE
CREATE TABLE `__TABLE_PREFIX__conversations` (
  `conversation_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(130) NOT NULL,
  `parent_conversation_id` int UNSIGNED NULL DEFAULT NULL,
  `date_created` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `last_message_mcc` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `last_message_hab` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `encryption_key` TEXT NULL COMMENT 'Encrypted conversation key for message/file encryption',
  PRIMARY KEY (`conversation_id`),
  INDEX idx_parent_conversation (`parent_conversation_id`),
  INDEX idx_conversations_encryption_key (`encryption_key`(255)),
  CONSTRAINT __TABLE_PREFIX__fk_parent_conversation
    FOREIGN KEY (`parent_conversation_id`)
    REFERENCES `__TABLE_PREFIX__conversations`(`conversation_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. PARTICIPANTS TABLE
CREATE TABLE `__TABLE_PREFIX__participants` (
  `conversation_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Participant',
  PRIMARY KEY (`conversation_id`, `user_id`),
  FOREIGN KEY(`conversation_id`) REFERENCES `__TABLE_PREFIX__conversations`(`conversation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY(`user_id`) REFERENCES `__TABLE_PREFIX__users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. MESSAGES TABLE
CREATE TABLE `__TABLE_PREFIX__messages` (
  `message_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Author',
  `conversation_id` int UNSIGNED NOT NULL,
  `text` text DEFAULT NULL,
  `message_type` enum('text','important','video','audio','file') NOT NULL, -- Renamed from 'type'
  `from_crew` tinyint NOT NULL,
  `message_id_alt` int UNSIGNED DEFAULT NULL,
  `recv_time_hab` datetime(3) NOT NULL,
  `recv_time_mcc` datetime(3) NOT NULL,
  PRIMARY KEY (`message_id`),
  KEY idx_messages_user (user_id, message_id),
  KEY idx_messages_conversation_message (conversation_id, message_id),
  FOREIGN KEY (`conversation_id`) REFERENCES `__TABLE_PREFIX__conversations`(`conversation_id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `__TABLE_PREFIX__users`(`user_id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. MESSAGE STATUS TABLE
CREATE TABLE `__TABLE_PREFIX__msg_status` (
  `message_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Recipient',
  PRIMARY KEY(`message_id`, `user_id`),
  INDEX idx_msg_status_user (`user_id`),
  FOREIGN KEY(`user_id`) REFERENCES `__TABLE_PREFIX__users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY(`message_id`) REFERENCES `__TABLE_PREFIX__messages`(`message_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. SAVED MESSAGES TABLE
CREATE TABLE `__TABLE_PREFIX__msg_saved` (
  `message_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  PRIMARY KEY(`message_id`, `user_id`),
  INDEX idx_msg_status_user (`user_id`),
  FOREIGN KEY(`user_id`) REFERENCES `__TABLE_PREFIX__users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY(`message_id`) REFERENCES `__TABLE_PREFIX__messages`(`message_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 7. MESSAGE FILES TABLE
CREATE TABLE `__TABLE_PREFIX__msg_files` (
  `file_id` int UNSIGNED NOT NULL AUTO_INCREMENT,  
  `message_id` int UNSIGNED NOT NULL,
  `server_name` text NOT NULL,
  `original_name` text NOT NULL,
  `mime_type` text NOT NULL,
  PRIMARY KEY(`file_id`),
  INDEX idx_msg_files_message (`message_id`),
  FOREIGN KEY(`message_id`) REFERENCES `__TABLE_PREFIX__messages`(`message_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 8. MISSION CONFIG TABLE (Updated Column Names)
CREATE TABLE `__TABLE_PREFIX__mission_config` (
  `name` varchar(32) NOT NULL UNIQUE,
  `config_value` text NOT NULL,
  `config_type` enum('string','int','float','bool', 'json') NOT NULL,
  PRIMARY KEY(`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 9. MISSION ARCHIVES TABLE
CREATE TABLE `__TABLE_PREFIX__mission_archives` (
  `archive_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `server_name` text NOT NULL,
  `notes` text NOT NULL,
  `mime_type` text NOT NULL,
  `timestamp` datetime(3) NOT NULL,
  `content_tz` varchar(64) NOT NULL, 
  PRIMARY KEY(`archive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
