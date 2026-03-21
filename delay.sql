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

--
-- DATA INSERTS 
--

INSERT INTO `__TABLE_PREFIX__mission_config` (`name`, `config_type`, `config_value`) VALUES
('name',               'string', 'Analog Mission Name'),
('date_start',         'string', '2021-08-10 00:00:00'),
('date_end',           'string', '2021-11-10 00:00:00'),
('mcc_name',           'string', 'Mission Control'),
('mcc_planet',         'string', 'Earth'),
('mcc_user_role',      'string', 'Mission Control'),
('mcc_timezone',       'string', 'America/New_York'),
('hab_name',           'string', 'Analog Habitat'),
('hab_planet',         'string', 'Mars'),
('hab_user_role',      'string', 'Astronaut'),
('hab_timezone',       'string', 'America/Chicago'),
('hab_day_name',       'string', 'Mission Day'),
('delay_type',         'string', 'manual'),
('delay_config',       'json',   '[{"ts":"2021-01-01 00:00:00","eq":0}]'),
('login_timeout',      'int',    '3600'),
('feat_audio_notification',  'bool', '1'),
('feat_badge_notification',  'bool', '1'),
('feat_unread_msg_counts',   'bool', '1'),
('feat_convo_list_order',    'bool', '1'),
('feat_est_delivery_status', 'bool', '1'),
('feat_progress_bar',        'bool', '1'),
('feat_markdown_support',    'bool', '1'),
('feat_important_msgs',      'bool', '1'),
('feat_convo_threads',       'bool', '1'), 
('feat_convo_threads_all',   'bool', '1'),
('feat_out_of_seq',          'bool', '1'),
('feat_saved_messages',      'bool', '1'),
('debug',                    'bool', '0'),
('initialized',              'bool', '0');

INSERT INTO `__TABLE_PREFIX__users` (`user_id`, `username`, `alias`, `password`, `session_id`, `is_admin`, `is_crew`, `last_login`, `is_password_reset`, `preferences`) VALUES
(1, 'admin', 'Admin', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', NULL, 1, 0, '2021-07-23 14:52:17', 0, '');

INSERT INTO `__TABLE_PREFIX__conversations` (`conversation_id`, `name`, `parent_conversation_id`, `date_created`, `last_message_mcc`, `last_message_hab`, `encryption_key`) VALUES
(1, 'Mission Chat', NULL, '2021-07-23 14:57:49', NOW(), NOW(), NULL);

INSERT INTO `__TABLE_PREFIX__participants` (`conversation_id`, `user_id`) VALUES
(1, 1);
