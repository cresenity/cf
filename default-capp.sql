-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               5.6.21 - MySQL Community Server (GPL)
-- Server OS:                    Win32
-- HeidiSQL Version:             9.3.0.4984
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

DROP TABLE IF EXISTS `org`;
CREATE TABLE IF NOT EXISTS `org` (
  `org_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) DEFAULT '',
  `domain` varchar(255) DEFAULT '',
  `name` varchar(50) DEFAULT NULL,
  `address` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `contact_person` varchar(50) DEFAULT NULL,
  `auth_type` varchar(50) DEFAULT 'dev',
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`org_id`),
  UNIQUE KEY `org_id` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table torsb2c.org: ~0 rows (approximately)
/*!40000 ALTER TABLE `org` DISABLE KEYS */;
INSERT INTO `org` (`org_id`, `code`, `name`, `address`, `city`,  `email`, `phone`,  `contact_person`, `created`, `createdby`, `updated`, `updatedby`, `status`) VALUES
	(1, 'cresenity', 'Cresenity', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1);
/*!40000 ALTER TABLE `org` ENABLE KEYS */;


-- Dumping structure for table torsb2c.roles
DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `depth` bigint(20) unsigned DEFAULT NULL,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `lft` bigint(20) DEFAULT NULL,
  `rgt` bigint(20) DEFAULT NULL,
  `is_base` tinyint(4) NOT NULL DEFAULT '0',
  `name` varchar(60) DEFAULT NULL COMMENT 'The name of an entity (record) is used as an default search option in addition to the search key. The name is up to 60 characters in length.',
  `type` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `store_id` bigint(20) DEFAULT NULL,
  `role_level` int(11) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `roleid` (`role_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`role_id`, `parent_id`, `depth`, `org_id`, `lft`, `rgt`, `is_base`, `name`, `description`, `store_id`, `created`, `createdby`, `updated`, `updatedby`, `status`, `role_level`) VALUES
	(1, NULL, 0, NULL, 1, 2, 1, 'SUPERADMIN', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;


DROP TABLE IF EXISTS `role_nav`;
CREATE TABLE IF NOT EXISTS `role_nav` (
  `role_nav_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `app_id` bigint(20) unsigned DEFAULT NULL,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `nav` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`role_nav_id`),
  UNIQUE KEY `role_nav_id` (`role_nav_id`),
  CONSTRAINT `role_nav_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  CONSTRAINT `role_nav_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `role_permission`;
CREATE TABLE IF NOT EXISTS `role_permission` (
  `role_permission_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `app_id` bigint(20) unsigned DEFAULT NULL,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `nav` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`role_permission_id`),
  UNIQUE KEY `role_permission_id` (`role_permission_id`),
  CONSTRAINT `role_permission_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  CONSTRAINT `role_permission_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



DROP TABLE IF EXISTS `sys_counter`;
CREATE TABLE IF NOT EXISTS `sys_counter` (
  `sys_counter_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `counter` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`sys_counter_id`),
  UNIQUE KEY `counter_id` (`sys_counter_id`),
  UNIQUE KEY `sys_counter_org_key` (`org_id`,`key`),
  CONSTRAINT `sys_counter_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `sys_row_count`;
CREATE TABLE IF NOT EXISTS `sys_row_count` (
  `sys_row_count_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `table` varchar(255) DEFAULT NULL,
  `row_count` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`sys_row_count_id`),
  UNIQUE KEY `sys_row_count_id` (`sys_row_count_id`),
  CONSTRAINT `sys_row_count_id_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `user_photo` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_request` datetime DEFAULT NULL,
  `login_count` int(11) DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `activation_code` varchar(255) DEFAULT NULL,
  `activation_date` datetime DEFAULT NULL,
  `have_notification` int(11) DEFAULT '0',
  `is_base` tinyint(1) NOT NULL DEFAULT '0',
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_users_roles` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`user_id`, `role_id`, `org_id`, `username`, `user_photo`, `first_name`, `last_name`, `password`, `email`, `last_login`, `last_request`, `login_count`, `description`, `activation_code`, `activation_date`, `have_notification`, `is_base`, `is_disabled`, `created`, `createdby`, `updated`, `updatedby`, `status`) VALUES
	(1, 1, NULL, 'superadmin', NULL, NULL, NULL, '21232f297a57a5a743894a0e4a801fc3', NULL, '2016-01-13 21:13:17', '2016-01-13 22:07:39', 195, NULL, NULL, NULL, 0, 0, 0, '2015-07-24 11:10:49', NULL, NULL, NULL, 1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;


DROP TABLE IF EXISTS `var`;
CREATE TABLE IF NOT EXISTS `var` (
  `var_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned NULL,
  `key` varchar(32) NOT NULL,
  `caption` varchar(255) NOT NULL,
  `value` longtext,
  `is_var_user` tinyint(1) NOT NULL DEFAULT '0',
  `data_type` varchar(255) NOT NULL DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`var_id`),
  UNIQUE KEY `var_id` (`var_id`),
  CONSTRAINT `var_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40000 ALTER TABLE `var` DISABLE KEYS */;
/*!40000 ALTER TABLE `var` ENABLE KEYS */;


DROP TABLE IF EXISTS `var_user`;
CREATE TABLE IF NOT EXISTS `var_user` (
  `var_user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `key` varchar(32) NOT NULL,
  `value` longtext,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`var_user_id`),
  UNIQUE KEY `var_user_id` (`var_user_id`),
  CONSTRAINT `var_user_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `resource`;
CREATE TABLE `resource` (
  `resource_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `model_type` varchar(255) DEFAULT NULL,
  `model_id` bigint(20) unsigned DEFAULT NULL,
  `collection_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `disk` varchar(255) DEFAULT NULL,
  `size` int(20) unsigned DEFAULT NULL,
  `manipulations` longtext,
  `custom_properties` longtext,
  `responsive_images` longtext,
  `order_column` int(20) unsigned DEFAULT NULL,
  `version` int,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `is_active` int(11) DEFAULT '1',
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`resource_id`),
  CONSTRAINT `resource_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  KEY `resource__model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



DROP TABLE IF EXISTS `queue`;
CREATE TABLE `queue` (
  `queue_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `app_code` varchar(200) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `attempts` tinyint unsigned DEFAULT NULL,
  `reserved` tinyint unsigned DEFAULT NULL,
  `closure_data` longtext DEFAULT NULL,
  `reserved_at` datetime DEFAULT NULL,
  `available_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`queue_id`),
  UNIQUE KEY `queue_id` (`queue_id`),
  CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `queue_failed`;
CREATE TABLE `queue_failed` (
  `queue_failed_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`queue_failed_id`),
  KEY `queue_failed__failed_at_index` (`failed_at`),
  KEY `queue_failed__queue_index` (`queue`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `queue_batch`;
CREATE TABLE `queue_batch` (
  `queue_batch_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `app_code` varchar(200) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `id` varchar(191) DEFAULT NULL,
  `total_jobs` int DEFAULT NULL,
  `pending_jobs` int DEFAULT NULL,
  `failed_jobs` int DEFAULT NULL,
  `failed_job_ids` TEXT DEFAULT NULL,
  `options` LONGTEXT DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`queue_batch_id`),
  UNIQUE KEY `queue_batch_id` (`queue_batch_id`),
  UNIQUE KEY `id` (`id`),
  CONSTRAINT `queue_batch_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `meta`;
CREATE TABLE `meta` (
  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `metable_type` varchar(255) NOT NULL,
  `metable_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `meta_id` (`meta_id`),
  CONSTRAINT `meta_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  KEY `meta__metable_id_metable_type_index` (`metable_id`,`metable_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `visit`;
CREATE TABLE `visit` (
  `visit_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `method` varchar(255) DEFAULT NULL,
  `request` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referer` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `languages` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `useragent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headers` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visitable_type` varchar(255) DEFAULT NULL,
  `visitable_id` bigint(20) unsigned DEFAULT NULL,
  `visitor_type` varchar(255) DEFAULT NULL,
  `visitor_id` bigint(20) unsigned DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`visit_id`),
  UNIQUE KEY `visit_id` (`visit_id`),
  CONSTRAINT `visit_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  KEY `visit__visitable_id_visitable_type_index` (`visitable_id`,`visitable_type`),
  KEY `visit__visitor_id_visitor_type_index` (`visitor_id`,`visitor_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `notification`;
CREATE TABLE `notification` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `ref_type` varchar(255) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `notification_id` (`notification_id`),
  CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  KEY `notification__notifiable_id_notifiable_type_index` (`notifiable_id`,`notifiable_type`),
  KEY `notification__ref_id_ref_type_index` (`ref_id`,`ref_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `translation`;
CREATE TABLE `translation` (
  `translation_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned,
  `translatable_type` varchar(255) NOT NULL,
  `translatable_id` bigint(20) unsigned NOT NULL,
  `language` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`translation_id`),
  UNIQUE KEY `translation_id` (`translation_id`),
  CONSTRAINT `translation_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`),
  KEY `translation__translatable_id_translatable_type_index` (`translatable_id`,`translatable_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `log_activity`;
CREATE TABLE `log_activity` (
  `log_activity_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(100) DEFAULT NULL,
  `app_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `remote_addr` varchar(50) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `platform_version` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `browser_version` varchar(255) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `uri` varchar(255) DEFAULT NULL,
  `routed_uri` varchar(255) DEFAULT NULL,
  `controller` varchar(255) DEFAULT NULL,
  `method` varchar(255) DEFAULT NULL,
  `query_string` varchar(255) DEFAULT NULL,
  `nav` varchar(255) DEFAULT NULL,
  `nav_label` varchar(255) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `action_label` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `activity_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`log_activity_id`),
  UNIQUE KEY `log_activity_id` (`log_activity_id`),
  KEY `log_activity_created_index` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `log_sse`;
CREATE TABLE `log_sse` (
  `log_sse_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `ref_type` varchar(255) DEFAULT NULL,
  `message` longtext DEFAULT NULL,
  `event` varchar(50) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `delivered` tinyint DEFAULT 0,
  `client` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`log_sse_id`),
  UNIQUE KEY `log_sse_id` (`log_sse_id`),
  CONSTRAINT `log_sse_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `log_notification`;
CREATE TABLE `log_notification` (
  `log_notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `member_type` varchar(50) DEFAULT NULL,
  `message_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` int(11) NOT NULL DEFAULT 0,
  `recipient` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error` longtext DEFAULT NULL,
  `vendor_response` longtext DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `ref_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updatedip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`log_notification_id`),
  UNIQUE KEY `log_notification_id` (`log_notification_id`),
  KEY `org_id` (`org_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `log_notification_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `log_login`;
CREATE TABLE IF NOT EXISTS `log_login` (
  `log_login_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(100) DEFAULT NULL,
  `app_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `remote_addr` varchar(50) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `platform_version` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `browser_version` varchar(255) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `login_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`log_login_id`),
  UNIQUE KEY `log_login_id` (`log_login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `log_login_fail`;
CREATE TABLE IF NOT EXISTS `log_login_fail` (
  `log_login_fail_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(100) DEFAULT NULL,
  `app_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `remote_addr` varchar(50) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `platform_version` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `browser_version` varchar(255) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `login_fail_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`log_login_fail_id`),
  UNIQUE KEY `log_login_fail_id` (`log_login_fail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `access_token`;
CREATE TABLE `access_token` (
  `access_token_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tokenable_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`access_token_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `websocket_statistic`;
CREATE TABLE `websocket_statistic` (
  `websocket_statistic_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `app` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peak_connection_count` int(11) DEFAULT NULL,
  `websocket_message_count` int(11) DEFAULT NULL,
  `api_message_count` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`websocket_statistic_id`),
  UNIQUE KEY `websocket_statistic_id` (`websocket_statistic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `cache_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiration` bigint(20) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`cache_id`),
  UNIQUE KEY `cache_id` (`cache_id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cache_lock`;
CREATE TABLE `cache_lock` (
  `cache_lock_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiration` bigint(20) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`cache_lock_id`),
  UNIQUE KEY `cache_lock_id` (`cache_lock_id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `session`;
CREATE TABLE `session` (
  `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_activity` int(11) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `updatedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `deletedby` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `session_id` (`session_id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
