-- Schema dump for app_database
-- ------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `addrequest`
--

DROP TABLE IF EXISTS `addrequest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addrequest` (
  `request_status` enum('Pending','Confirm','Reject') NOT NULL,
  `request_notification_status` enum('No','Yes') NOT NULL,
  `request_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `request_update_time` datetime DEFAULT NULL,
  `request_from_guid` binary(16) DEFAULT NULL,
  `request_to_guid` binary(16) DEFAULT NULL,
  `request_guid` binary(16) NOT NULL,
  PRIMARY KEY (`request_guid`),
  KEY `idx_addrequest_from_guid` (`request_from_guid`),
  KEY `idx_addrequest_to_guid` (`request_to_guid`),
  KEY `idx_addrequest_request_guid` (`request_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachments` (
  `name` varchar(200) COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `guid` binary(16) NOT NULL DEFAULT (uuid_to_bin(uuid(),true)),
  `mime_type` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `file_extension` varchar(10) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `chat_type` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_guid_1` binary(16) DEFAULT NULL,
  `user_guid_2` binary(16) DEFAULT NULL,
  `group_guid` binary(16) DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`guid`),
  CONSTRAINT `check_chat_type_length` CHECK ((char_length(`chat_type`) < 20)),
  CONSTRAINT `check_file_extension_length` CHECK ((char_length(`file_extension`) < 10)),
  CONSTRAINT `check_file_path_length` CHECK ((char_length(`file_path`) < 500)),
  CONSTRAINT `check_mime_type_length` CHECK ((char_length(`mime_type`) < 100)),
  CONSTRAINT `check_name_length` CHECK ((char_length(`name`) < 200))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chatbox`
--

DROP TABLE IF EXISTS `chatbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbox` (
  `chat_message` varchar(1000) DEFAULT NULL,
  `chat_created_date` datetime DEFAULT NULL,
  `chat_updated_date` datetime DEFAULT NULL,
  `chat_attachment_guid` binary(16) DEFAULT NULL,
  `chat_is_deleted` bit(1) DEFAULT b'0',
  `chat_status` int DEFAULT NULL,
  `chat_from_guid` binary(16) DEFAULT NULL,
  `chat_to_guid` binary(16) DEFAULT NULL,
  `message_guid` binary(16) NOT NULL,
  PRIMARY KEY (`message_guid`),
  KEY `idx_chatbox_from_guid` (`chat_from_guid`),
  KEY `idx_chatbox_to_guid` (`chat_to_guid`),
  KEY `idx_chatbox_conversation_guid` (`chat_from_guid`,`chat_to_guid`),
  KEY `idx_chatbox_message_guid` (`message_guid`),
  KEY `idx_chatbox_attachment` (`chat_attachment_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_notification_throttle`
--

DROP TABLE IF EXISTS `email_notification_throttle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_notification_throttle` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_guid` binary(16) DEFAULT NULL,
  `sender_guid` binary(16) DEFAULT NULL,
  `last_email_sent` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_sender_guid_only` (`user_guid`,`sender_guid`),
  UNIQUE KEY `unique_user_sender_guid` (`user_guid`,`sender_guid`),
  KEY `idx_user_guid` (`user_guid`),
  KEY `idx_sender_guid` (`sender_guid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_chats`
--

DROP TABLE IF EXISTS `group_chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_chats` (
  `group_name` varchar(50) COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `group_image` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `image_guid` binary(16) DEFAULT NULL,
  `creator_guid` binary(16) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `max_members` int DEFAULT '50',
  `message_count` int DEFAULT '0',
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `group_guid` binary(16) NOT NULL,
  PRIMARY KEY (`group_guid`),
  UNIQUE KEY `idx_group_chats_guid` (`group_guid`),
  KEY `idx_active` (`is_active`),
  KEY `idx_last_activity` (`last_activity`),
  KEY `idx_creator_guid` (`creator_guid`),
  KEY `idx_group_chats_file_path` (`file_path`),
  KEY `idx_group_chats_image_guid` (`image_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
/*!50032 DROP TRIGGER IF EXISTS group_chats_image_guid_trigger */;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `group_chats_image_guid_trigger` BEFORE UPDATE ON `group_chats` FOR EACH ROW BEGIN
    
    IF NEW.file_path IS NOT NULL AND NEW.file_path != '' AND NEW.image_guid IS NULL THEN
        SET NEW.image_guid = UUID_TO_BIN(UUID(), true);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `group_members`
--

DROP TABLE IF EXISTS `group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_members` (
  `role` enum('admin','member') COLLATE utf8mb4_0900_ai_ci DEFAULT 'member',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `unread_count` int DEFAULT '0',
  `group_guid` binary(16) NOT NULL,
  `user_guid` binary(16) NOT NULL,
  PRIMARY KEY (`group_guid`,`user_guid`),
  UNIQUE KEY `unique_membership_guid` (`group_guid`,`user_guid`),
  KEY `idx_role` (`role`),
  KEY `idx_group_members_group_guid` (`group_guid`),
  KEY `idx_group_members_user_guid` (`user_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_message_reads`
--

DROP TABLE IF EXISTS `group_message_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_message_reads` (
  `message_id` bigint NOT NULL,
  `read_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_guid` binary(16) NOT NULL,
  `message_guid` binary(16) NOT NULL,
  PRIMARY KEY (`message_guid`,`user_guid`),
  KEY `idx_group_message_reads_user_guid` (`user_guid`),
  KEY `idx_user_reads_guid` (`user_guid`,`read_at`),
  CONSTRAINT `fk_group_message_reads_message` FOREIGN KEY (`message_guid`) REFERENCES `group_messages` (`message_guid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_messages`
--

DROP TABLE IF EXISTS `group_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_messages` (
  `message_content` text COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  `group_guid` binary(16) DEFAULT NULL,
  `sender_guid` binary(16) DEFAULT NULL,
  `attachment_guid` binary(16) DEFAULT NULL,
  `message_guid` binary(16) NOT NULL,
  PRIMARY KEY (`message_guid`),
  UNIQUE KEY `idx_group_messages_message_guid` (`message_guid`),
  KEY `idx_group_messages_group_guid` (`group_guid`),
  KEY `idx_group_messages_sender_guid` (`sender_guid`),
  KEY `idx_group_messages_attachment_guid` (`attachment_guid`),
  FULLTEXT KEY `ft_message_content` (`message_content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_notifications`
--

DROP TABLE IF EXISTS `group_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_notifications` (
  `notification_guid` binary(16) NOT NULL,
  `user_guid` binary(16) NOT NULL,
  `group_guid` binary(16) NOT NULL,
  `notification_type` enum('added_to_group','group_deleted','group_deleted_account_removed','group_deactivated','group_reactivated','removed_from_group','became_admin') COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `group_name` varchar(50) COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `is_acknowledged` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`notification_guid`),
  KEY `idx_user_pending` (`user_guid`,`is_acknowledged`),
  KEY `idx_group_guid` (`group_guid`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `passwordReset`
--

DROP TABLE IF EXISTS `passwordReset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `passwordReset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `selector` varchar(255) DEFAULT NULL,
  `token` blob,
  `expires` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `check_email_length` CHECK ((char_length(`Email`) < 128)),
  CONSTRAINT `check_expires_length` CHECK ((char_length(`Expires`) < 256)),
  CONSTRAINT `check_selector_length` CHECK ((char_length(`Selector`) < 512)),
  CONSTRAINT `check_token_length` CHECK ((char_length(`Token`) < 512))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profileImage`
--

DROP TABLE IF EXISTS `profileImage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profileImage` (
  `image_guid` binary(16) NOT NULL DEFAULT (uuid_to_bin(uuid(),true)),
  `image_type` varchar(10) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `user_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`image_guid`),
  UNIQUE KEY `profileImage_imgGuid_uindex` (`image_guid`),
  UNIQUE KEY `unique_user_profile_image` (`user_guid`),
  KEY `idx_profileimage_user_guid` (`user_guid`),
  KEY `idx_profileimage_file_path` (`file_path`),
  CONSTRAINT `fk_profileimage_user_guid` FOREIGN KEY (`user_guid`) REFERENCES `users` (`user_guid`) ON DELETE CASCADE,
  CONSTRAINT `check_type_length` CHECK ((char_length(`image_type`) < 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_guid` binary(16) NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_guid` binary(16) DEFAULT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_setting_guid` (`user_guid`,`setting_name`),
  UNIQUE KEY `unique_user_setting_guid_only` (`user_guid`,`setting_name`),
  KEY `idx_user_settings_guid` (`user_guid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_username` varchar(128) DEFAULT NULL,
  `user_password` varchar(256) DEFAULT NULL,
  `user_email` varchar(128) DEFAULT NULL,
  `user_role` enum('user','admin') DEFAULT 'user',
  `user_status` enum('Offline','Active') DEFAULT 'Offline',
  `user_email_verified` enum('True','False') DEFAULT 'False',
  `user_created_date` datetime DEFAULT NULL,
  `user_verification_token` varchar(128) DEFAULT NULL,
  `user_guid` binary(16) NOT NULL,
  `user_is_deleted` bit(1) DEFAULT b'0',
  `user_banned` bit(1) DEFAULT b'0',
  `user_verification_token_expire_date` text,
  PRIMARY KEY (`user_guid`),
  UNIQUE KEY `idx_users_user_guid` (`user_guid`),
  UNIQUE KEY `idx_users_email` (`user_email`),
  UNIQUE KEY `idx_users_username` (`user_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usertokens`
--

DROP TABLE IF EXISTS `usertokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usertokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `selector` varchar(255) NOT NULL,
  `hashed_validator` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `user_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usertokens_user_guid` (`user_guid`),
  CONSTRAINT `check_selector_ut_length` CHECK ((char_length(`selector`) < 255)),
  CONSTRAINT `check_validator_ut_length` CHECK ((char_length(`hashed_validator`) < 255))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'app_database'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
