SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE `vkapp` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(1024) NOT NULL,
  `handshake` varchar(256) NOT NULL,
  `secret` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL,
  `app` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_cats` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(256) NOT NULL,
  `descr` varchar(1024) NOT NULL,
  `photo` varchar(1024) NOT NULL,
  `width` int(10) UNSIGNED NOT NULL,
  `height` int(10) UNSIGNED NOT NULL,
  `sex` tinyint(4) NOT NULL DEFAULT '0',
  `price` decimal(10,0) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_deny` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_files` (
  `md5` char(32) NOT NULL,
  `time` int(11) NOT NULL,
  `attach_id` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_messages` (
  `id` varchar(64) NOT NULL,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `vkapp_catlist_money_history` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ctime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `diff` decimal(10,0) NOT NULL,
  `value` decimal(10,0) NOT NULL,
  `descr` varchar(1024) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_reposts` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `ctime` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_settings` (
  `key` varchar(32) NOT NULL,
  `type` varchar(32) NOT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_shop` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(64) NOT NULL,
  `title` varchar(1024) NOT NULL,
  `description` varchar(1024) NOT NULL DEFAULT '',
  `photo` varchar(1024) NOT NULL DEFAULT '',
  `width` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `height` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deleted` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_users` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ctime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `mtime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deny` tinyint(4) NOT NULL DEFAULT '0',
  `action` varchar(128) NOT NULL DEFAULT '',
  `state` text,
  `first_name` varchar(256) NOT NULL DEFAULT '',
  `last_name` varchar(256) NOT NULL DEFAULT '',
  `sex` smallint(6) NOT NULL,
  `money` decimal(10,0) NOT NULL DEFAULT '0',
  `bonus` decimal(10,0) NOT NULL DEFAULT '0',
  `food` decimal(10,0) NOT NULL DEFAULT '0',
  `toilet` decimal(10,0) NOT NULL DEFAULT '0',
  `cats` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `notify` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vkapp_catlist_user_cats` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ctime` int(10) UNSIGNED NOT NULL,
  `name` varchar(256) NOT NULL,
  `photo` varchar(1024) NOT NULL,
  `width` int(10) UNSIGNED NOT NULL,
  `height` int(10) UNSIGNED NOT NULL,
  `sex` tinyint(3) UNSIGNED NOT NULL,
  `cheerfulness` int(11) NOT NULL DEFAULT '100'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_comm_users` (
  `cid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_globals` (
  `group_id` int(11) NOT NULL,
  `key` varchar(64) NOT NULL,
  `value` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_grabber_blacklist` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `source_type` int(10) UNSIGNED NOT NULL,
  `remote_id` varchar(64) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE `vk_grabber_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` varchar(255) DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4,
  `attaches` longblob
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_grabber_data_index` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_id` int(10) UNSIGNED NOT NULL,
  `source_type` int(10) UNSIGNED NOT NULL,
  `remote_id` varchar(64) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `grab_time` int(10) UNSIGNED NOT NULL,
  `likes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `comments` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `images_cnt` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `gifs_cnt` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `data_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `post_type` tinyint(1) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_grabber_data_owners` (
  `id` varchar(255) NOT NULL,
  `name` varchar(1024) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `avatar` varchar(1024) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_grabber_selected_sources` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_id` int(10) UNSIGNED NOT NULL,
  `group_id` bigint(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `enabled` tinyint(3) UNSIGNED NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_grabber_sources` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_type` int(10) UNSIGNED NOT NULL,
  `source_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_groups` (
  `pos` int(11) NOT NULL,
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `period_from` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `period_to` int(10) UNSIGNED NOT NULL DEFAULT '86399',
  `interval` int(10) UNSIGNED NOT NULL DEFAULT '7200',
  `telegram_channel_id` varchar(64) NOT NULL,
  `telegram_last_vk_id` int(11) NOT NULL DEFAULT '0',
  `meme` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_join_stat` (
  `id` int(10) UNSIGNED NOT NULL,
  `cid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `type` tinyint(4) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `users_cnt` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_oauth` (
  `type` varchar(32) NOT NULL,
  `access_token` varchar(255) NOT NULL,
  `refresh_token` varchar(255) NOT NULL,
  `expires` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_posts` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `date` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `likes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `comments` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_posts_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_posts_fake_date` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `fake_date` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_posts_likes` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_posts_queue` (
  `nid` int(10) UNSIGNED NOT NULL,
  `id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `fake_date` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_posts_reposts` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `likes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `comments` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_smm_money` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `money` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_date` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_smm_money_out` (
  `id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `last_time` int(10) UNSIGNED NOT NULL,
  `sum` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_special_posts` (
  `post_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `vkapp`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `app` (`app`);

ALTER TABLE `vkapp_catlist_cats`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vkapp_catlist_deny`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `vkapp_catlist_files`
  ADD PRIMARY KEY (`md5`);

ALTER TABLE `vkapp_catlist_messages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vkapp_catlist_money_history`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vkapp_catlist_reposts`
  ADD PRIMARY KEY (`user_id`,`owner_id`,`post_id`);

ALTER TABLE `vkapp_catlist_settings`
  ADD PRIMARY KEY (`key`);

ALTER TABLE `vkapp_catlist_shop`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vkapp_catlist_users`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `vkapp_catlist_user_cats`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vk_comm_users`
  ADD PRIMARY KEY (`cid`,`uid`);

ALTER TABLE `vk_globals`
  ADD PRIMARY KEY (`group_id`,`key`);

ALTER TABLE `vk_grabber_blacklist`
  ADD PRIMARY KEY (`group_id`,`source_type`,`remote_id`);

ALTER TABLE `vk_grabber_data`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vk_grabber_data_index`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_type-remote_id` (`source_type`,`remote_id`) USING BTREE,
  ADD KEY `source_id-only_text` (`source_id`,`post_type`) USING BTREE,
  ADD KEY `time` (`time`),
  ADD KEY `reposts` (`reposts`),
  ADD KEY `likes` (`likes`),
  ADD KEY `comments` (`comments`),
  ADD KEY `grab_time` (`grab_time`);

ALTER TABLE `vk_grabber_data_owners`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vk_grabber_selected_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_id` (`source_id`,`group_id`);

ALTER TABLE `vk_grabber_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_type` (`source_type`,`source_id`);

ALTER TABLE `vk_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pos` (`pos`);

ALTER TABLE `vk_join_stat`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vk_oauth`
  ADD PRIMARY KEY (`type`);

ALTER TABLE `vk_posts`
  ADD PRIMARY KEY (`post_id`,`group_id`);

ALTER TABLE `vk_posts_comments`
  ADD PRIMARY KEY (`id`,`post_id`,`group_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `vk_posts_fake_date`
  ADD PRIMARY KEY (`group_id`);

ALTER TABLE `vk_posts_likes`
  ADD PRIMARY KEY (`post_id`,`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `vk_posts_queue`
  ADD PRIMARY KEY (`nid`),
  ADD UNIQUE KEY `group_id-id` (`group_id`,`id`) USING BTREE,
  ADD KEY `group_id-position` (`group_id`) USING BTREE;

ALTER TABLE `vk_posts_reposts`
  ADD PRIMARY KEY (`post_id`,`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `vk_smm_money`
  ADD PRIMARY KEY (`group_id`);

ALTER TABLE `vk_special_posts`
  ADD PRIMARY KEY (`post_id`,`group_id`);


ALTER TABLE `vkapp_catlist_cats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vkapp_catlist_money_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `vkapp_catlist_shop`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vkapp_catlist_user_cats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vk_grabber_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vk_grabber_data_index`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vk_grabber_selected_sources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vk_grabber_sources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vk_join_stat`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `vk_posts_queue`
  MODIFY `nid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
