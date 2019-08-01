/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catificator_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catificator_category_triggers` (
  `category_id` int(10) unsigned NOT NULL,
  `word` varchar(128) NOT NULL,
  PRIMARY KEY (`category_id`,`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catificator_tracks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `md5` char(32) NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `filename` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `md5` (`md5`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_activity_stat_progress` (
  `group_id` int(11) NOT NULL,
  `offset` int(10) unsigned DEFAULT '0',
  `done` tinyint(4) DEFAULT '0',
  `stat_done` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_callbacks` (
  `group_id` int(10) unsigned NOT NULL,
  `type` varchar(16) NOT NULL,
  `secret` varchar(128) NOT NULL DEFAULT '',
  `install_ack` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`group_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_comm_users` (
  `cid` int(10) unsigned NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cid`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_globals` (
  `group_id` int(11) NOT NULL,
  `key` varchar(64) NOT NULL,
  `value` varchar(256) NOT NULL,
  PRIMARY KEY (`group_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_blacklist` (
  `group_id` int(10) unsigned NOT NULL,
  `source_type` int(10) unsigned NOT NULL,
  `remote_id` varchar(64) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`source_type`,`remote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_data` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner` varchar(255) DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4,
  `attaches` longblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_data_index` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int(10) unsigned NOT NULL,
  `source_type` int(10) unsigned NOT NULL,
  `remote_id` varchar(64) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `grab_time` int(10) unsigned NOT NULL,
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `reposts` int(10) unsigned NOT NULL DEFAULT '0',
  `comments` int(10) unsigned NOT NULL DEFAULT '0',
  `images_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `gifs_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `data_id` int(10) unsigned NOT NULL DEFAULT '0',
  `post_type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_type-remote_id` (`source_type`,`remote_id`) USING BTREE,
  KEY `source_id-only_text` (`source_id`,`post_type`) USING BTREE,
  KEY `time` (`time`),
  KEY `reposts` (`reposts`),
  KEY `likes` (`likes`),
  KEY `comments` (`comments`),
  KEY `grab_time` (`grab_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_data_owners` (
  `id` varchar(255) NOT NULL,
  `name` varchar(1024) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `avatar` varchar(1024) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_selected_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int(10) unsigned NOT NULL,
  `group_id` bigint(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `enabled` tinyint(3) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_id` (`source_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_type` int(10) unsigned NOT NULL,
  `source_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_type` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_sources_progress` (
  `source_id` int(10) unsigned NOT NULL,
  `offset` int(10) unsigned DEFAULT '0',
  `done` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_groups` (
  `pos` int(11) NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `widget` varchar(16) NOT NULL DEFAULT '',
  `period_from` int(10) unsigned NOT NULL DEFAULT '0',
  `period_to` int(10) unsigned NOT NULL DEFAULT '86399',
  `interval` int(10) unsigned NOT NULL DEFAULT '7200',
  `deviation` int(10) unsigned NOT NULL DEFAULT '0',
  `special_post_before` int(10) unsigned NOT NULL DEFAULT '1800',
  `special_post_after` int(10) unsigned NOT NULL DEFAULT '3600',
  `telegram_channel_id` varchar(64) NOT NULL,
  `telegram_last_vk_id` int(11) NOT NULL DEFAULT '0',
  `meme` longtext,
  `deleted` tinyint(3) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos` (`pos`),
  KEY `deleted-pos` (`deleted`,`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_groups_oauth` (
  `group_id` int(10) unsigned NOT NULL,
  `access_token` varchar(255) NOT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_join_stat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(10) unsigned NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `type` tinyint(4) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `users_cnt` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_oauth` (
  `type` varchar(32) NOT NULL,
  `access_token` varchar(255) NOT NULL,
  `refresh_token` varchar(255) NOT NULL,
  `expires` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_posts_queue` (
  `nid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id` int(10) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `fake_date` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`nid`),
  UNIQUE KEY `group_id-id` (`group_id`,`id`) USING BTREE,
  KEY `group_id-position` (`group_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_smm_money` (
  `group_id` int(10) unsigned NOT NULL,
  `money` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_date` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_smm_money_out` (
  `id` int(10) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `last_time` int(10) unsigned NOT NULL,
  `sum` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_user_comments` (
  `group_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `text_length` int(10) unsigned NOT NULL DEFAULT '0',
  `images_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `attaches_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `stickers_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`,`post_id`,`comment_id`),
  KEY `date` (`date`,`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_user_likes` (
  `group_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`group_id`,`post_id`,`user_id`),
  KEY `date` (`date`,`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_user_reposts` (
  `group_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`group_id`,`post_id`,`user_id`),
  KEY `date` (`date`,`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_users_posts` (
  `post_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `reposts` int(10) unsigned NOT NULL DEFAULT '0',
  `comments` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`post_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_users_stat` (
  `date` date NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `reposts` int(10) unsigned NOT NULL DEFAULT '0',
  `comments` int(10) unsigned NOT NULL DEFAULT '0',
  `comments_meaningful` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`date`,`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_users_stat_progress` (
  `group_id` int(11) NOT NULL,
  `offset` int(10) unsigned DEFAULT '0',
  `done` tinyint(4) NOT NULL DEFAULT '0',
  `stat_done` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_widget_top_users` (
  `group_id` int(10) unsigned NOT NULL,
  `cost_likes` int(10) unsigned NOT NULL DEFAULT '0',
  `cost_reposts` int(10) unsigned NOT NULL DEFAULT '0',
  `cost_comments` int(10) unsigned NOT NULL DEFAULT '0',
  `days` int(10) unsigned NOT NULL DEFAULT '30',
  `title` varchar(100) NOT NULL DEFAULT '',
  `tile_title` varchar(256) NOT NULL DEFAULT '',
  `tile_descr` varchar(256) NOT NULL DEFAULT '',
  `tile_link` varchar(256) NOT NULL DEFAULT '',
  `tiles` varchar(256) NOT NULL DEFAULT '',
  `mtime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_widget_top_users_blacklist` (
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp` (
  `group_id` int(10) unsigned NOT NULL,
  `token` varchar(1024) NOT NULL,
  `handshake` varchar(256) NOT NULL,
  `secret` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL,
  `app` varchar(64) NOT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `app` (`app`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_cats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) NOT NULL,
  `descr` varchar(1024) NOT NULL,
  `photo` varchar(1024) NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `sex` tinyint(4) NOT NULL DEFAULT '0',
  `price` decimal(10,0) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_deny` (
  `user_id` bigint(20) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_files` (
  `md5` char(32) NOT NULL,
  `time` int(11) NOT NULL,
  `attach_id` varchar(64) NOT NULL,
  PRIMARY KEY (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_messages` (
  `id` varchar(64) NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_money_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `ctime` int(10) unsigned NOT NULL DEFAULT '0',
  `diff` decimal(10,0) NOT NULL,
  `value` decimal(10,0) NOT NULL,
  `descr` varchar(1024) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_reposts` (
  `user_id` int(10) unsigned NOT NULL,
  `owner_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `ctime` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`owner_id`,`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_settings` (
  `key` varchar(32) NOT NULL,
  `type` varchar(32) NOT NULL,
  `value` text,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_shop` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(64) NOT NULL,
  `title` varchar(1024) NOT NULL,
  `description` varchar(1024) NOT NULL DEFAULT '',
  `photo` varchar(1024) NOT NULL DEFAULT '',
  `width` int(10) unsigned NOT NULL DEFAULT '0',
  `height` int(10) unsigned NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_user_cats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `ctime` int(10) unsigned NOT NULL,
  `name` varchar(256) NOT NULL,
  `photo` varchar(1024) NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `sex` tinyint(3) unsigned NOT NULL,
  `cheerfulness` int(11) NOT NULL DEFAULT '100',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_users` (
  `user_id` bigint(20) unsigned NOT NULL,
  `ctime` int(10) unsigned NOT NULL DEFAULT '0',
  `mtime` int(10) unsigned NOT NULL DEFAULT '0',
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
  `cats` int(10) unsigned NOT NULL DEFAULT '0',
  `notify` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
