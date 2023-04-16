CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `alias` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `session_id` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL,
  `is_crew` tinyint(1) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_password_reset` tinyint(1) NOT NULL DEFAULT '1',
  `preferences` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `file_id` binary(16) NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `conversations` (
  `conversation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `parent_conversation_id` int(10) UNSIGNED NULL DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT NOW(),
  `last_message` datetime NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `participants` (
  `conversation_id` int(10) UNSIGNED NOT NULL ,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Participant',
  PRIMARY KEY (`conversation_id`, `user_id`),
  FOREIGN KEY(`conversation_id`) REFERENCES conversations(`conversation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY(`user_id`) REFERENCES users(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE  
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `messages` (
  `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Author',
  `conversation_id` int(10) UNSIGNED NOT NULL ,
  `file_id` binary(16) NULL DEFAULT NULL,
  `text` text CHARACTER SET utf8 DEFAULT NULL,
  `type` enum('text','important','video','audio','file') COLLATE utf8_unicode_ci NOT NULL,
  `sent_time` datetime NOT NULL,
  `from_crew` tinyint(1) NOT NULL,
  `message_id_alt` int(10) UNSIGNED DEFAULT NULL,
  `recv_time_hab` datetime NOT NULL,
  `recv_time_mcc` datetime NOT NULL,
  PRIMARY KEY(`message_id`, `user_id`, `conversation_id`),
  FOREIGN KEY(`conversation_id`) REFERENCES conversations(`conversation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY(`user_id`) REFERENCES users(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `msg_status` (
  `message_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Recipient',
  PRIMARY KEY(`message_id`, `user_id`),
  FOREIGN KEY(`user_id`) REFERENCES users(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY(`message_id`) REFERENCES messages(`message_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `mission_config` (
  `name` varchar(32) COLLATE utf8_unicode_ci NOT NULL UNIQUE,
  `value` text CHARACTER SET utf8 NOT NULL,
  `type` enum('string','int','float','bool', 'datetime') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY(`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `files` (
   `file_id` binary(16) NOT NULL,
   `association` enum('avatar','archive','message') COLLATE utf8_unicode_ci NOT NULL,
   `server_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
   `original_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
   `mime_type` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
   `timestamp` datetime NOT NULL,
   `notes` text CHARACTER SET utf8 DEFAULT NULL,
   `settings` text CHARACTER SET utf8 DEFAULT NULL,...........
   PRIMARY KEY(`file_id`),
   FOREIGN KEY(`file_id`) REFERENCES messages(`file_id`) ON DELETE CASCADE
   FOREIGN KEY(`file_id`) REFERENCES users(`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `mission_config` (`name`, `type`, `value`) VALUES
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
('delay_config',       'string', '[{"ts":"2021-01-01 00:00:00","eq":0}]'),
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
('debug',                    'bool', '0');

INSERT INTO `users` (`user_id`, `username`, `alias`, `password`, `session_id`, `is_admin`, `is_crew`, `last_login`, `is_password_reset`, `preferences`) VALUES
(1, 'admin', 'Admin', '2bb80d537b1da3e38bd30361aa855686bde0eacd7162fef6a25fe97bf527a25b', NULL, 1, 0, '2021-07-23 14:52:17', 1, '');

INSERT INTO `conversations` (`conversation_id`, `name`, `parent_conversation_id`, `date_created`, `last_message`) VALUES
(1, 'Mission Chat', NULL, '2021-07-23 14:57:49', NOW());

INSERT INTO `participants` (`conversation_id`, `user_id`) VALUES
(1, 1);

/* The following values are used for testing only and should not be deployed by default. */

INSERT INTO `users` (`user_id`, `username`, `alias`, `password`, `session_id`, `is_admin`, `is_crew`, `last_login`, `is_password_reset`, `preferences`) VALUES
(2, 'user1', 'Flight Director', '2bb80d537b1da3e38bd30361aa855686bde0eacd7162fef6a25fe97bf527a25b', NULL, 0, 0, NULL, 1, ''),
(3, 'user2', 'Blueberry', '2bb80d537b1da3e38bd30361aa855686bde0eacd7162fef6a25fe97bf527a25b', NULL, 0, 1, NULL, 1, ''),
(4, 'user3', 'Tangerine', '2bb80d537b1da3e38bd30361aa855686bde0eacd7162fef6a25fe97bf527a25b', NULL, 0, 1, NULL, 1, '');


INSERT INTO `conversations` (`conversation_id`, `name`, `parent_conversation_id`, `date_created`, `last_message`) VALUES
(2, 'Admin-Flight Director', NULL, '2021-08-03 23:14:48', NOW()),
(3, 'Admin-Blueberry', NULL, '2021-08-03 23:14:59', NOW()),
(4, 'Flight Director-Blueberry', NULL, '2021-08-03 23:14:59', NOW()),
(5, 'Admin-Tangerine', NULL, '2021-08-03 23:15:07', NOW()),
(6, 'Flight Director-Tangerine', NULL, '2021-08-03 23:15:07', NOW()),
(7, 'Blueberry-Tangerine', NULL, '2021-08-03 23:15:07', NOW());


INSERT INTO `participants` (`conversation_id`, `user_id`) VALUES
(1, 2),
(1, 3),
(1, 4),
(2, 1),
(2, 2),
(3, 1),
(3, 3),
(4, 2),
(4, 3),
(5, 1),
(5, 4),
(6, 2),
(6, 4),
(7, 3),
(7, 4);