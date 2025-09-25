SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Database schema for chat application

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `getLastUserChat`(IN `fromId` INT, IN `toId` INT)
begin
    set @basePath = (select value from config where name = 'baseFileUrl');
    SELECT ChatboxID,
           chatFromId,
           chatToId,
           chatMessage,
           chatCreatedDate,
           chatIsDeleted,
           chatUpdatedDate,
           chatAttachmentId,
           chatStatus,
           `name` as filename,
           mimetype,
           IF(chatAttachmentId is null, null, concat(@basePath, bin_to_uuid(guid, true)))
                  as url
    FROM chatbox
             left join attachments on chatAttachmentId = Id
    WHERE (chatFromId = fromId AND chatToId = toId)
    order by chatCreatedDate DESC
    LIMIT 1;

end$$

CREATE PROCEDURE `getUserChat`(IN `fromId` INT, IN `toId` INT)
begin
    set @basePath = (select value from config where name = 'baseFileUrl');
    SELECT ChatboxID,
           chatFromId,
           chatToId,
           chatMessage,
           chatCreatedDate,
           chatIsDeleted,
           chatUpdatedDate,
           chatAttachmentId,
           chatStatus,
           `name` as filename,
           mimetype,
           IF(chatAttachmentId is null, null, concat(@basePath, bin_to_uuid(guid, true)))
                  as url
    FROM chatbox
             left join attachments on chatAttachmentId = Id
    WHERE (chatFromId = fromId
        AND chatToId = toId)
       OR (chatFromId = toId
        AND chatToId = fromId);
end$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `addrequest`
--

CREATE TABLE `addrequest`
(
    `RequestID`              int                                 NOT NULL,
    `rqstFromId`             int                                 NOT NULL,
    `rqstToId`               int                                 NOT NULL,
    `rqstStatus`             enum ('Pending','Confirm','Reject') NOT NULL,
    `rqstNotificationStatus` enum ('No','Yes')                   NOT NULL,
    `rqstDatetime`           timestamp                           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `rqstUpdateTime`         datetime                                     DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;


-- Table structure for table `attachments`
--

CREATE TABLE `attachments`
(
    `name`      varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'name',
    `guid`      binary(16)                                                    NOT NULL DEFAULT (uuid_to_bin(uuid(), true)),
    `Id`        int                                                           NOT NULL,
    `mimetype`  varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci          DEFAULT NULL,
    `extension` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci           DEFAULT NULL
);
-- --------------------------------------------------------

--
-- Table structure for table `chatbox`
--

CREATE TABLE `chatbox`
(
    `ChatboxID`        int           NOT NULL,
    `chatFromId`       int           NOT NULL,
    `chatToId`         int           NOT NULL,
    `chatMessage`      varchar(1000) NOT NULL,
    `chatCreatedDate`  datetime DEFAULT NULL,
    `chatUpdatedDate`  datetime DEFAULT NULL,
    `chatAttachmentId` int      DEFAULT NULL,
    `chatIsDeleted`    bit(1)   DEFAULT b'0',
    `chatStatus`       int      DEFAULT NULL
);

--
-- Table structure for table `chatStatus`
--

CREATE TABLE `chatStatus`
(
    `Id`     int NOT NULL,
    `status` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
);

--
-- Dumping data for table `chatStatus`
--

INSERT INTO `chatStatus` (`Id`, `status`)
VALUES (0, 'Undelivered'),
       (1, 'Delivered'),
       (2, 'Read');

-- --------------------------------------------------------

--
-- Table structure for table `config`
--

CREATE TABLE `config`
(
    `Id`    int NOT NULL,
    `name`  varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
    `value` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
);

--
-- Dumping data for table `config`
--

INSERT INTO `config` (`Id`, `name`, `value`)
VALUES (1, 'baseUploadPath', 'uploads'),
       (2, 'baseFileUrl', '/download.php?file=');

-- --------------------------------------------------------

--
-- Table structure for table `profileimg`
--

CREATE TABLE `profileImage`
(
    `ImgID`     int         NOT NULL,
    `UsersID`   int         NOT NULL,
    `imgStatus` int         NOT NULL,
    `imgType`   varchar(10) NOT NULL
);

--
-- Table structure for table `passwordReset`
--

CREATE TABLE `passwordReset`
(
    `Id`       int          NOT NULL,
    `Email`    varchar(128) NOT NULL,
    `Selector` text         NOT NULL,
    `Token`    longtext     NOT NULL,
    `Expires`  text         NOT NULL
);

--
-- Table structure for table `users`
--

CREATE TABLE `users`
(
    `UsersID`                          int                       NOT NULL,
    `usersUsername`                    varchar(128)              NOT NULL,
    `usersPassword`                    varchar(256)              NOT NULL,
    `usersEmail`                       varchar(128)              NOT NULL,
    `usersStatus`                      enum ('Offline','Active') NOT NULL                            DEFAULT 'Offline',
    `usersEmailVerified`               enum ('True','False')                                         DEFAULT 'False',
    `usersCreatedDate`                 datetime                                                      DEFAULT NULL,
    `usersVerificationToken`           varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
    `usersGuid`                        binary(16)                                                    DEFAULT (uuid_to_bin(uuid(), true)),
    `usersAreDeleted`                  bit(1)                                                        DEFAULT b'0',
    `usersVerificationTokenExpireDate` text COMMENT 'usersVerificationTokenExpireDate'
);


--
-- Table structure for table `user_settings`
--

CREATE TABLE IF NOT EXISTS user_settings ( id INT AUTO_INCREMENT PRIMARY KEY,
                                             user_id INT NOT NULL,
                                             setting_name VARCHAR(50) NOT NULL,
                                             setting_value INT NOT NULL DEFAULT 0,
                                             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                             updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                             UNIQUE KEY unique_user_setting (user_id, setting_name),
                                             FOREIGN KEY (user_id) REFERENCES users(UsersID) ON DELETE CASCADE
);

--
-- Table structure for table `usertokens`
--

CREATE TABLE `usertokens`
(
    `id`               int          NOT NULL,
    `selector`         varchar(255) NOT NULL,
    `hashed_validator` varchar(255) NOT NULL,
    `user_id`          int          NOT NULL,
    `expiry`           datetime     NOT NULL
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addrequest`
--
ALTER TABLE `addrequest`
    ADD PRIMARY KEY (`RequestID`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
    ADD PRIMARY KEY (`Id`),
    ADD UNIQUE KEY `attachments_Id_uindex` (`Id`);

--
-- Indexes for table `chatbox`
--
ALTER TABLE `chatbox`
    ADD PRIMARY KEY (`ChatboxID`),
    ADD KEY `chatbox___fk_status` (`chatStatus`);

--
-- Indexes for table `chatStatus`
--
ALTER TABLE `chatStatus`
    ADD PRIMARY KEY (`Id`),
    ADD UNIQUE KEY `chatStatus_Id_uindex` (`Id`);

--
-- Indexes for table `config`
--
ALTER TABLE `config`
    ADD PRIMARY KEY (`Id`),
    ADD UNIQUE KEY `config_name_uindex` (`name`);

--
-- Indexes for table `profileimg`
--
ALTER TABLE profileImage
    ADD PRIMARY KEY (`ImgID`);

--
-- Indexes for table `pwdreset`
--
ALTER TABLE passwordReset
    ADD PRIMARY KEY (Id);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
    ADD PRIMARY KEY (`UsersID`);

--
-- Indexes for table `usertokens`
--
ALTER TABLE `usertokens`
    ADD PRIMARY KEY (`id`),
    ADD KEY `fk_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addrequest`
--
ALTER TABLE `addrequest`
    MODIFY `RequestID` int NOT NULL AUTO_INCREMENT,
    AUTO_INCREMENT = 105;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
    MODIFY `Id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chatbox`
--
ALTER TABLE `chatbox`
    MODIFY `ChatboxID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chatStatus`
--
ALTER TABLE `chatStatus`
    MODIFY `Id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `config`
--
ALTER TABLE `config`
    MODIFY `Id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profileimg`
--
ALTER TABLE profileImage
    MODIFY `ImgID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pwdreset`
--
ALTER TABLE passwordReset
    MODIFY Id int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
    MODIFY `UsersID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usertokens`
--
ALTER TABLE `usertokens`
    MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chatbox`
--
ALTER TABLE `chatbox`
    ADD CONSTRAINT `chatbox___fk_status` FOREIGN KEY (`chatStatus`) REFERENCES `chatStatus` (`Id`);

--
-- Constraints for table `usertokens`
--
ALTER TABLE `usertokens`
    ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`UsersID`) ON DELETE CASCADE;

alter table attachments
    add (CONSTRAINT check_name_length CHECK (CHAR_LENGTH(name) < 200));
alter table attachments
    add (CONSTRAINT check_mimetype_length CHECK (CHAR_LENGTH(mimetype) < 100));
alter table attachments
    add (CONSTRAINT check_extension_length CHECK (CHAR_LENGTH(extension) < 10));
alter table chatbox
    add (CONSTRAINT check_message_length CHECK (CHAR_LENGTH(chatMessage) < 1000));
alter table chatStatus
    add (CONSTRAINT check_status_length CHECK (CHAR_LENGTH(status) < 20));
alter table config
    add (CONSTRAINT check_name_config_length CHECK (CHAR_LENGTH(name) < 30));
alter table config
    add (CONSTRAINT check_value_length CHECK (CHAR_LENGTH(value) < 60));
alter table profileImage
    add (CONSTRAINT check_type_length CHECK (CHAR_LENGTH(imgType) < 10));
alter table passwordReset
    add (CONSTRAINT check_email_length CHECK (CHAR_LENGTH(Email) < 128));
alter table passwordReset
    add (CONSTRAINT check_selector_length CHECK (CHAR_LENGTH(Selector) < 512));
alter table passwordReset
    add (CONSTRAINT check_token_length CHECK (CHAR_LENGTH(Token) < 512));
alter table passwordReset
    add (CONSTRAINT check_expires_length CHECK (CHAR_LENGTH(Expires) < 256));
alter table users
    add (CONSTRAINT check_username_length CHECK (CHAR_LENGTH(usersUsername) < 128));
alter table users
    add (CONSTRAINT check_password_length CHECK (CHAR_LENGTH(usersPassword) < 256));
alter table users
    add (CONSTRAINT check_email_user_length CHECK (CHAR_LENGTH(usersEmail) < 128));
alter table users
    add (CONSTRAINT check_ver_token_length CHECK (CHAR_LENGTH(usersVerificationToken) < 100));
alter table users
    add (CONSTRAINT check_ver_exp_date_length CHECK (CHAR_LENGTH(usersVerificationTokenExpireDate) < 100));
alter table usertokens
    add (CONSTRAINT check_selector_ut_length CHECK (CHAR_LENGTH(selector) < 255));
alter table usertokens
    add (CONSTRAINT check_validator_ut_length CHECK (CHAR_LENGTH(hashed_validator) < 255));
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;
