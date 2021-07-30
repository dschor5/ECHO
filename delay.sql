CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `alias` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `session_id` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL,
  `is_crew` tinyint(1) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `password_reset` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

CREATE TABLE `conversations` (
  `conversation_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`);

ALTER TABLE `conversations`
  MODIFY `conversation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

CREATE TABLE `participants` (
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `participants`
  ADD PRIMARY KEY (`conversation_id`,`user_id`);

CREATE TABLE `messages` (
  `message_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL COMMENT 'Message Author',
  `conversation_id` int(11) UNSIGNED NOT NULL,
  `text` text CHARACTER SET utf8 NOT NULL,
  `type` enum('text','video','audio','file') COLLATE utf8_unicode_ci NOT NULL,
  `sent_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`);

ALTER TABLE `messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

CREATE TABLE `msg_status` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Recipient user id',
  `recv_time` int(11) DEFAULT NULL COMMENT 'In recipient''s time',
  `is_delivered` tinyint(1) NOT NULL DEFAULT '0',
  `is_read` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `msg_status`
  ADD PRIMARY KEY (`message_id`,`user_id`);

INSERT INTO `users` (`user_id`, `username`, `password`, `session_id`, `is_admin`, `is_crew`, `last_login`, `password_reset`) VALUES
(1, 'admin', '5ebe2294ecd0e0f08eab7690d2a6ee69', '17eebdfd162812db191eefdf', 1, 0, '2021-07-23 14:52:17', 1);

INSERT INTO `conversations` (`conversation_id`, `name`, `date_created`) VALUES
(1, 'Mission Chat', '2021-07-23 14:57:49');

INSERT INTO `participants` (`conversation_id`, `user_id`, `last_read`) VALUES
(1, 1, '0000-00-00 00:00:00');