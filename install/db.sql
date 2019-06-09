-- MySQL dump 10.13  Distrib 5.7.25-28, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: xujxuj
-- ------------------------------------------------------
-- Server version	5.7.25-28

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!50717 SELECT COUNT(*) INTO @rocksdb_has_p_s_session_variables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'performance_schema' AND TABLE_NAME = 'session_variables' */;
/*!50717 SET @rocksdb_get_is_supported = IF (@rocksdb_has_p_s_session_variables, 'SELECT COUNT(*) INTO @rocksdb_is_supported FROM performance_schema.session_variables WHERE VARIABLE_NAME=\'rocksdb_bulk_load\'', 'SELECT 0') */;
/*!50717 PREPARE s FROM @rocksdb_get_is_supported */;
/*!50717 EXECUTE s */;
/*!50717 DEALLOCATE PREPARE s */;
/*!50717 SET @rocksdb_enable_bulk_load = IF (@rocksdb_is_supported, 'SET SESSION rocksdb_bulk_load = 1', 'SET @rocksdb_dummy_bulk_load = 0') */;
/*!50717 PREPARE s FROM @rocksdb_enable_bulk_load */;
/*!50717 EXECUTE s */;
/*!50717 DEALLOCATE PREPARE s */;

--
-- Table structure for table `vk_comm_users`
--

DROP TABLE IF EXISTS `vk_comm_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_comm_users` (
  `cid` int(10) unsigned NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cid`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_globals`
--

DROP TABLE IF EXISTS `vk_globals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_globals` (
  `group_id` int(11) NOT NULL,
  `key` varchar(64) NOT NULL,
  `value` varchar(256) NOT NULL,
  PRIMARY KEY (`group_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_grabber_blacklist`
--

DROP TABLE IF EXISTS `vk_grabber_blacklist`;
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

--
-- Table structure for table `vk_grabber_data`
--

DROP TABLE IF EXISTS `vk_grabber_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_data` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner` varchar(255) DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4,
  `attaches` longblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6892447 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_grabber_data_index`
--

DROP TABLE IF EXISTS `vk_grabber_data_index`;
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
) ENGINE=InnoDB AUTO_INCREMENT=24650793 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_grabber_data_owners`
--

DROP TABLE IF EXISTS `vk_grabber_data_owners`;
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

--
-- Table structure for table `vk_grabber_selected_sources`
--

DROP TABLE IF EXISTS `vk_grabber_selected_sources`;
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
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_grabber_sources`
--

DROP TABLE IF EXISTS `vk_grabber_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_grabber_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_type` int(10) unsigned NOT NULL,
  `source_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_type` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47567 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_groups`
--

DROP TABLE IF EXISTS `vk_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_groups` (
  `pos` int(11) NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `period_from` int(10) unsigned NOT NULL DEFAULT '0',
  `period_to` int(10) unsigned NOT NULL DEFAULT '86399',
  `interval` int(10) unsigned NOT NULL DEFAULT '7200',
  `deviation` int(10) unsigned NOT NULL DEFAULT '0',
  `special_post_before` int(10) unsigned NOT NULL DEFAULT '1800',
  `special_post_after` int(10) unsigned NOT NULL DEFAULT '3600',
  `telegram_channel_id` varchar(64) NOT NULL,
  `telegram_last_vk_id` int(11) NOT NULL DEFAULT '0',
  `meme` longtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos` (`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_join_stat`
--

DROP TABLE IF EXISTS `vk_join_stat`;
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
) ENGINE=InnoDB AUTO_INCREMENT=240738 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_oauth`
--

DROP TABLE IF EXISTS `vk_oauth`;
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

--
-- Table structure for table `vk_posts`
--

DROP TABLE IF EXISTS `vk_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_posts` (
  `post_id` int(10) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `date` int(10) unsigned NOT NULL DEFAULT '0',
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `reposts` int(10) unsigned NOT NULL DEFAULT '0',
  `comments` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`post_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_posts_comments`
--

DROP TABLE IF EXISTS `vk_posts_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_posts_comments` (
  `id` int(10) unsigned NOT NULL,
  `post_id` int(10) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`post_id`,`group_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_posts_fake_date`
--

DROP TABLE IF EXISTS `vk_posts_fake_date`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_posts_fake_date` (
  `group_id` int(10) unsigned NOT NULL,
  `fake_date` int(10) unsigned NOT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_posts_likes`
--

DROP TABLE IF EXISTS `vk_posts_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_posts_likes` (
  `post_id` int(10) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`post_id`,`group_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_posts_queue`
--

DROP TABLE IF EXISTS `vk_posts_queue`;
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
) ENGINE=InnoDB AUTO_INCREMENT=13811 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_posts_reposts`
--

DROP TABLE IF EXISTS `vk_posts_reposts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_posts_reposts` (
  `post_id` int(10) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` int(10) unsigned NOT NULL DEFAULT '0',
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `reposts` int(10) unsigned NOT NULL DEFAULT '0',
  `comments` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`post_id`,`group_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_smm_money`
--

DROP TABLE IF EXISTS `vk_smm_money`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_smm_money` (
  `group_id` int(10) unsigned NOT NULL,
  `money` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_date` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vk_smm_money_out`
--

DROP TABLE IF EXISTS `vk_smm_money_out`;
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

--
-- Table structure for table `vk_special_posts`
--

DROP TABLE IF EXISTS `vk_special_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vk_special_posts` (
  `post_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp`
--

DROP TABLE IF EXISTS `vkapp`;
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

--
-- Table structure for table `vkapp_catlist_cats`
--

DROP TABLE IF EXISTS `vkapp_catlist_cats`;
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_deny`
--

DROP TABLE IF EXISTS `vkapp_catlist_deny`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_deny` (
  `user_id` bigint(20) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_files`
--

DROP TABLE IF EXISTS `vkapp_catlist_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_files` (
  `md5` char(32) NOT NULL,
  `time` int(11) NOT NULL,
  `attach_id` varchar(64) NOT NULL,
  PRIMARY KEY (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_messages`
--

DROP TABLE IF EXISTS `vkapp_catlist_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_messages` (
  `id` varchar(64) NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_money_history`
--

DROP TABLE IF EXISTS `vkapp_catlist_money_history`;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_reposts`
--

DROP TABLE IF EXISTS `vkapp_catlist_reposts`;
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

--
-- Table structure for table `vkapp_catlist_settings`
--

DROP TABLE IF EXISTS `vkapp_catlist_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vkapp_catlist_settings` (
  `key` varchar(32) NOT NULL,
  `type` varchar(32) NOT NULL,
  `value` text,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_shop`
--

DROP TABLE IF EXISTS `vkapp_catlist_shop`;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_user_cats`
--

DROP TABLE IF EXISTS `vkapp_catlist_user_cats`;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vkapp_catlist_users`
--

DROP TABLE IF EXISTS `vkapp_catlist_users`;
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
/*!50112 SET @disable_bulk_load = IF (@is_rocksdb_supported, 'SET SESSION rocksdb_bulk_load = @old_rocksdb_bulk_load', 'SET @dummy_rocksdb_bulk_load = 0') */;
/*!50112 PREPARE s FROM @disable_bulk_load */;
/*!50112 EXECUTE s */;
/*!50112 DEALLOCATE PREPARE s */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-06-09 17:54:46
