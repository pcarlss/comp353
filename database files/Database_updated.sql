-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 08, 2024 at 01:15 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `project`
--

-- --------------------------------------------------------

--
-- Table structure for table `AccessControl`
--
DROP TABLE IF EXISTS `AccessControl`;

CREATE TABLE `AccessControl` (
  `AccessID` int(50) NOT NULL,
  `PostID` int(50) NOT NULL,
  `GroupID` int(50) NOT NULL,
  `CanComment` tinyint(1) NOT NULL,
  `CanView` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Blocked`
--

CREATE TABLE `Blocked` (
  `BlockedID` int(50) NOT NULL,
  `MemberID1` int(50) NOT NULL,
  `MemberID2` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Privacy`
--
DROP TABLE IF EXISTS `Privacy`;
CREATE TABLE `Privacy` (
  `PrivacyID` int(50) NOT NULL,
  `Fname` tinyint(1) NOT NULL DEFAULT 0,
  `Lname` tinyint(1) NOT NULL DEFAULT 0,
  `BirthDate` tinyint(1) NOT NULL DEFAULT 0,
  `pCity` tinyint(1) NOT NULL DEFAULT 0,
  `pCountry` tinyint(1) NOT NULL DEFAULT 0,
  `Work` tinyint(1) NOT NULL DEFAULT 0,
  `pStatus` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- --------------------------------------------------------

--
-- Table structure for table `Comment`
--

CREATE TABLE `Comment` (
  `CommentID` int(50) NOT NULL,
  `PostID` int(50) NOT NULL,
  `MemberID` int(50) NOT NULL,
  `CommentContent` text NOT NULL,
  `CommentedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Event`
--

CREATE TABLE `Event` (
  `EventID` int(50) NOT NULL AUTO_INCREMENT,
  `EventName` varchar(50) NOT NULL,
  `EventDesc` varchar(50) NOT NULL,
  `EventCreatorID` int(50) NOT NULL,
  `EventGroupID` int(50) NULL,
  `EventPostedAt` datetime NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`EventID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `EventOptions`
--

CREATE TABLE `EventOptions` (
  `OptionID` int(50) NOT NULL AUTO_INCREMENT,
  `EventID` int(50) NOT NULL,
  `Date_Time_Location` varchar(50) NOT NULL,
  `Votes` int(50) NOT NULL DEFAULT 0,
  PRIMARY KEY (`OptionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FriendOrGroupRequest`
--

CREATE TABLE `FriendOrGroupRequest` (
  `RequestID` int(50) NOT NULL,
  `RequestorID` int(50) NOT NULL,
  `RequesteeID` int(50) NOT NULL,
  `RequestMadeAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Friendship`
--

CREATE TABLE `Friendship` (
  `FriendshipID` int(50) NOT NULL,
  `MemberID1` int(50) NOT NULL,
  `MemberID2` int(50) NOT NULL,
  `RelationshipType` enum('Family','Friend','Colleague') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Gift`
--

CREATE TABLE `Gift` (
  `GiftID` int(50) NOT NULL AUTO_INCREMENT,
  `GiftExchangeEventID` int(50) NOT NULL,
  `GiftName` text NOT NULL,
  `GiftforID` int(50) NOT NULL,
  PRIMARY KEY (`GiftID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GiftExchange`
--

CREATE TABLE `GiftExchange` (
  `GiftExchangeID` int(50) NOT NULL AUTO_INCREMENT,
  `MemberID` int(50) NOT NULL,               -- Member who created the gift exchange
  `GiftGroupID` int(50) NULL,            -- Uncommented this line
  `GiftExchangeName` varchar(50) NOT NULL,
  `GiftExchangeDesc` varchar(50) NOT NULL,
  `GiftExchangeDate` varchar(50) NOT NULL,
  `GiftExchangeCreatedAt` datetime NOT NULL,
  PRIMARY KEY (`GiftExchangeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Member`
--
CREATE TABLE `Member` (
  `MemberID` int(50) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(50) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `DateOfBirth` date DEFAULT NULL,
  `City` varchar(50) DEFAULT NULL,
  `Country` varchar(50) DEFAULT NULL,
  `Email` varchar(50) NOT NULL,
  `Profession` varchar(50) DEFAULT NULL,
  `Privilege` enum('Administrator','Senior','Junior') NOT NULL,
  `Status` enum('Active','Inactive','Suspended') NOT NULL,
  `BusinessAccount` tinyint(1) DEFAULT NULL,
  `UserCreatedAt` date NOT NULL,
  `UserUpdatedAt` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `Member` (`MemberID`, `Username`, `Password`, `FirstName`, `LastName`, `DateOfBirth`, `City`, `Country`, `Email`, `Profession`, `Privilege`, `Status`, `BusinessAccount`, `UserCreatedAt`, `UserUpdatedAt`) VALUES
(1, 'testuser', 'testpassword', 'Test', 'User', NULL, NULL, NULL, 'testuser@example.com', NULL, 'Administrator', 'Active', 0, '2024-11-06', '2024-11-06');

--
-- Triggers `Member`
--
DELIMITER $$
CREATE TRIGGER `after_member_insert` AFTER INSERT ON `Member` FOR EACH ROW BEGIN
    INSERT INTO Privacy (PrivacyID) VALUES (NEW.MemberID);
END
$$
DELIMITER ;



--
-- Indexes for table `Member`
--
ALTER TABLE `Member`
  ADD PRIMARY KEY (`MemberID`),
  ADD UNIQUE KEY `UniqueEmail` (`Email`),
  ADD UNIQUE KEY `UniqueUsername` (`Username`) USING BTREE;
--
-- Table structure for table `GroupList`
--

CREATE TABLE `GroupList` (
  `GroupID` int(50) NOT NULL,
  `GroupName` varchar(50) NOT NULL,
  `OwnerID` int(50) NOT NULL,
  `GroupCreatedAt` date NOT NULL,
  `GroupUpdatedAt` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `GroupList`
--
ALTER TABLE `GroupList`
  ADD PRIMARY KEY (`GroupID`),
  ADD KEY `OwnerID` (`OwnerID`);
-- --------------------------------------------------------

--
-- Table structure for table `GroupMember`
--

CREATE TABLE `GroupMember` (
  `GroupMemberID` int(50) NOT NULL,
  `GroupID` int(50) NOT NULL,
  `MemberID` int(50) NOT NULL,
  `JoinedGroupAt` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


--
-- Table structure for table `JoinRequest` to request to join a group
--

CREATE TABLE `JoinRequests` (
  RequestID INT(50) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  GroupID INT(50) NOT NULL,
  MemberID INT(50) NOT NULL,
  RequestDate DATE NOT NULL,
  FOREIGN KEY (GroupID) REFERENCES GroupList(GroupID) ON DELETE CASCADE,
  FOREIGN KEY (MemberID) REFERENCES Member(MemberID) ON DELETE CASCADE
);

ALTER TABLE `JoinRequests` DROP FOREIGN KEY `joinrequests_ibfk_1`;

ALTER TABLE `GroupList`
MODIFY `GroupID` int(50) NOT NULL AUTO_INCREMENT;

ALTER TABLE `JoinRequests`
ADD CONSTRAINT `joinrequests_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `GroupList`(`GroupID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Table structure for table `Message`
--

CREATE TABLE `Message` (
  `MessageID` int(50) NOT NULL,
  `MemberID1` int(50) NOT NULL,
  `MemberID2` int(50) NOT NULL,
  `MessageContent` text NOT NULL,
  `SentAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Post`
--

CREATE TABLE `Post` (
  `PostID` int(50) NOT NULL AUTO_INCREMENT,  -- Auto-incrementing primary key
  `MemberID` int(50) NOT NULL,               -- Member who created the post
  `PostText` text NOT NULL,                  -- Content of the post
  `PostImages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`PostImages`)),  -- Post images in JSON format
  `PostedAt` date NOT NULL,                  -- Date when the post was made
  `Visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`Visibility`)), -- Visibility settings in JSON format
  `PostType` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,  -- Type of the post (e.g., text, image, etc.)
  PRIMARY KEY (`PostID`)                     -- Primary key for the table
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Vote`
--

CREATE TABLE `Vote` (
  `VoteID` int(50) NOT NULL,
  `VoterID` int(50) NOT NULL,
  `SelectedOptionID` int(50) NOT NULL,
  `VotedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `AccessControl`
--
ALTER TABLE `AccessControl`
  ADD PRIMARY KEY (`AccessID`),
  ADD KEY `PostID` (`PostID`),
  ADD KEY `GroupID` (`GroupID`);

--
-- Indexes for table `Blocked`
--
ALTER TABLE `Blocked`
  ADD PRIMARY KEY (`BlockedID`),
  ADD KEY `MemberID1` (`MemberID1`),
  ADD KEY `MemberID2` (`MemberID2`);

--
-- Indexes for table `Privacy`
--
ALTER TABLE `Privacy`
  ADD PRIMARY KEY (`PrivacyID`);

--
-- Indexes for table `Comment`
--
ALTER TABLE `Comment`
  ADD PRIMARY KEY (`CommentID`),
  ADD KEY `PostID` (`PostID`),
  ADD KEY `MemberID` (`MemberID`);

--
-- Indexes for table `Event`
--
ALTER TABLE `Event`
  ADD KEY `EventCreatorID` (`EventCreatorID`),
  ADD KEY `EventGroupID` (`EventGroupID`);

--
-- Indexes for table `EventOptions`
--
ALTER TABLE `EventOptions`
  ADD KEY `EventID` (`EventID`);

--
-- Indexes for table `FriendOrGroupRequest`
--
ALTER TABLE `FriendOrGroupRequest`
  ADD PRIMARY KEY (`RequestID`),
  ADD KEY `RequestorID` (`RequestorID`),
  ADD KEY `RequesteeID` (`RequesteeID`);

--
-- Indexes for table `Friendship`
--
ALTER TABLE `Friendship`
  ADD PRIMARY KEY (`FriendshipID`),
  ADD KEY `MemberID1` (`MemberID1`),
  ADD KEY `MemberID2` (`MemberID2`);

--
-- Indexes for table `Gift`
--
ALTER TABLE `Gift`
  ADD KEY `GiftExchangeEventID` (`GiftExchangeEventID`),
  ADD KEY `GiftforID` (`GiftforID`);

--
-- Indexes for table `GiftExchange`
--
ALTER TABLE `GiftExchange`
  ADD KEY `GiftGroupID` (`GiftGroupID`);


--
-- Indexes for table `GroupMember`
--
ALTER TABLE `GroupMember`
  ADD PRIMARY KEY (`GroupMemberID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `MemberID` (`MemberID`);

--
-- Indexes for table `Message`
--
ALTER TABLE `Message`
  ADD PRIMARY KEY (`MessageID`),
  ADD KEY `MemberID1` (`MemberID1`),
  ADD KEY `MemberID2` (`MemberID2`);

--
-- Indexes for table `Post`
--
ALTER TABLE `Post`
  ADD KEY `MemberID` (`MemberID`);

--
-- Indexes for table `Vote`
--
ALTER TABLE `Vote`
  ADD PRIMARY KEY (`VoteID`),
  ADD KEY `VoterID` (`VoterID`),
  ADD KEY `SelectedOptionID` (`SelectedOptionID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `AccessControl`
--
ALTER TABLE `AccessControl`
  MODIFY `AccessID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Blocked`
--

ALTER TABLE `Blocked`
  MODIFY `BlockedID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Comment`
--
ALTER TABLE `Comment`
  MODIFY `CommentID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Event`
--
ALTER TABLE `Event`
  MODIFY `EventID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `EventOptions`
--
ALTER TABLE `EventOptions`
  MODIFY `OptionID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `FriendOrGroupRequest`
--
ALTER TABLE `FriendOrGroupRequest`
  MODIFY `RequestID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Friendship`
--
ALTER TABLE `Friendship`
  MODIFY `FriendshipID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Privacy`
--
ALTER TABLE `Privacy`
  MODIFY `PrivacyID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `GiftExchange`
--
ALTER TABLE `GiftExchange`
  MODIFY `GiftExchangeID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `GroupList`
--
ALTER TABLE `GroupList`
  MODIFY `GroupID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `GroupMember`
--
ALTER TABLE `GroupMember`
  MODIFY `GroupMemberID` int(50) NOT NULL AUTO_INCREMENT;

ALTER TABLE `JoinRequests` DROP FOREIGN KEY `joinrequests_ibfk_2`;
--
-- AUTO_INCREMENT for table `Member`
--
ALTER TABLE `Member`
  MODIFY `MemberID` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


--
-- AUTO_INCREMENT for table `Message`
--
ALTER TABLE `Message`
  MODIFY `MessageID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Post`
--
ALTER TABLE `Post`
  MODIFY `PostID` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Vote`
--
ALTER TABLE `Vote`
  MODIFY `VoteID` int(50) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `AccessControl`
--
ALTER TABLE `AccessControl`
  ADD CONSTRAINT `accesscontrol_ibfk_1` FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `accesscontrol_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `GroupList` (`GroupID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Modify MemberID column
ALTER TABLE `Member`
  MODIFY `MemberID` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `Blocked`
  ADD CONSTRAINT `blocked_ibfk_1` FOREIGN KEY (`MemberID1`) REFERENCES `Member`(`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `Blocked`
  ADD CONSTRAINT `blocked_ibfk_2` FOREIGN KEY (`MemberID2`) REFERENCES `Member`(`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;


--
-- Constraints for table `Privacy`
--
ALTER TABLE `Privacy`
  ADD CONSTRAINT `privacy_ibfk_1` FOREIGN KEY (`PrivacyID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

--
-- Constraints for table `Comment`
--
ALTER TABLE `Comment`
  ADD CONSTRAINT `comment_ibfk_1` FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `comment_ibfk_2` FOREIGN KEY (`MemberID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Event`
--
ALTER TABLE `Event`
  ADD CONSTRAINT `event_ibfk_1` FOREIGN KEY (`EventCreatorID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `event_ibfk_2` FOREIGN KEY (`EventGroupID`) REFERENCES `GroupList` (`GroupID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `EventOptions`
--
ALTER TABLE `EventOptions`
  ADD CONSTRAINT `eventoptions_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `Event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `FriendOrGroupRequest`
--
ALTER TABLE `FriendOrGroupRequest`
  ADD CONSTRAINT `friendorgrouprequest_ibfk_1` FOREIGN KEY (`RequesteeID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `friendorgrouprequest_ibfk_2` FOREIGN KEY (`RequestorID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Friendship`
--
ALTER TABLE `Friendship`
  ADD CONSTRAINT `friendship_ibfk_1` FOREIGN KEY (`MemberID1`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `friendship_ibfk_2` FOREIGN KEY (`MemberID2`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Gift`
--
ALTER TABLE `Gift`
  ADD CONSTRAINT `gift_ibfk_1` FOREIGN KEY (`GiftExchangeEventID`) REFERENCES `GiftExchange` (`GiftExchangeID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `gift_ibfk_2` FOREIGN KEY (`GiftforID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `GiftExchange`
--
ALTER TABLE `GiftExchange`
  ADD CONSTRAINT `giftexchange_ibfk_1` FOREIGN KEY (`GiftGroupID`) REFERENCES `GroupList` (`GroupID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `GroupList`
--
ALTER TABLE `GroupList`
  ADD CONSTRAINT `grouplist_ibfk_1` FOREIGN KEY (`OwnerID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `GroupMember`
--
ALTER TABLE `GroupMember`
  ADD CONSTRAINT `groupmember_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `GroupList` (`GroupID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `groupmember_ibfk_2` FOREIGN KEY (`MemberID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Message`
--
ALTER TABLE `Message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`MemberID1`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`MemberID2`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Post`
--
ALTER TABLE `Post`
  ADD CONSTRAINT `post_ibfk_1` FOREIGN KEY (`MemberID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Vote`
--
ALTER TABLE `Vote`
  ADD CONSTRAINT `vote_ibfk_1` FOREIGN KEY (`VoterID`) REFERENCES `Member` (`MemberID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vote_ibfk_2` FOREIGN KEY (`SelectedOptionID`) REFERENCES `EventOptions` (`OptionID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

ALTER TABLE `Member`
ADD COLUMN ProfilePic VARCHAR(255) DEFAULT 'uploads/images/default_pfp.png';


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
