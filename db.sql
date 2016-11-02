SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE `vk_comm_users` (
  `cid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_groups` (
  `pos` int(11) NOT NULL,
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `period_from` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `period_to` int(10) UNSIGNED NOT NULL DEFAULT '86399',
  `interval` int(10) UNSIGNED NOT NULL DEFAULT '7200'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vk_join_stat` (
  `id` int(10) UNSIGNED NOT NULL,
  `cid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `type` tinyint(4) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `users_cnt` int(10) UNSIGNED NOT NULL
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

CREATE TABLE `vk_posts_likes` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL
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


ALTER TABLE `vk_comm_users`
  ADD PRIMARY KEY (`cid`,`uid`);

ALTER TABLE `vk_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pos` (`pos`);

ALTER TABLE `vk_join_stat`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `vk_posts`
  ADD PRIMARY KEY (`post_id`,`group_id`);

ALTER TABLE `vk_posts_comments`
  ADD PRIMARY KEY (`id`,`post_id`,`group_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `vk_posts_likes`
  ADD PRIMARY KEY (`post_id`,`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `vk_posts_reposts`
  ADD PRIMARY KEY (`post_id`,`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);


ALTER TABLE `vk_join_stat`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
