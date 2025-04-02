-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server-Version:               8.0.36 - MySQL Community Server - GPL
-- Server-Betriebssystem:        Win64
-- HeidiSQL Version:             12.8.0.6941
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Exportiere Datenbank-Struktur für repository
CREATE DATABASE IF NOT EXISTS `repository` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `repository`;

-- Exportiere Struktur von Tabelle repository.admins
CREATE TABLE IF NOT EXISTS `admins` (
  `loginId` int NOT NULL AUTO_INCREMENT,
  `adminname` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(450) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `passwordhash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `registered` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sessionhash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sessionstart` timestamp NULL DEFAULT NULL,
  `hidden` int DEFAULT '0',
  `forgothash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'test',
  `forgotvalid` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`loginId`),
  UNIQUE KEY `id_UNIQUE` (`loginId`) /*!80000 INVISIBLE */,
  UNIQUE KEY `adminname_UNIQUE` (`adminname`),
  UNIQUE KEY `hidden_UNIQUE` (`hidden`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.admin_groups
CREATE TABLE IF NOT EXISTS `admin_groups` (
  `groupId` int NOT NULL AUTO_INCREMENT,
  `name` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `hidden` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`groupId`),
  UNIQUE KEY `id_UNIQUE` (`groupId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.admin_group_has_permissions
CREATE TABLE IF NOT EXISTS `admin_group_has_permissions` (
  `ghpId` int NOT NULL AUTO_INCREMENT,
  `groupId` int NOT NULL,
  `permissionId` int NOT NULL,
  PRIMARY KEY (`ghpId`),
  UNIQUE KEY `id_UNIQUE` (`ghpId`),
  KEY `fk_admin_group_has_permission_admin_groups1_idx` (`groupId`),
  KEY `fk_admin_group_has_permission_admin_permissions1_idx` (`permissionId`),
  CONSTRAINT `fk_admin_group_has_permission_admin_groups1` FOREIGN KEY (`groupId`) REFERENCES `admin_groups` (`groupId`),
  CONSTRAINT `fk_admin_group_has_permission_admin_permissions1` FOREIGN KEY (`permissionId`) REFERENCES `admin_permissions` (`permissionId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.admin_has_groups
CREATE TABLE IF NOT EXISTS `admin_has_groups` (
  `lhgId` int NOT NULL AUTO_INCREMENT,
  `groupId` int NOT NULL,
  `loginId` int NOT NULL,
  PRIMARY KEY (`lhgId`),
  UNIQUE KEY `id_UNIQUE` (`lhgId`),
  KEY `fk_admin_has_groups_admin_groups1_idx` (`groupId`),
  KEY `fk_admin_has_groups_admins1_idx` (`loginId`),
  CONSTRAINT `fk_admin_has_groups_admin_groups1` FOREIGN KEY (`groupId`) REFERENCES `admin_groups` (`groupId`),
  CONSTRAINT `fk_admin_has_groups_admins1` FOREIGN KEY (`loginId`) REFERENCES `admins` (`loginId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.admin_permissions
CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `permissionId` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `noPermFallback` int DEFAULT NULL,
  `allowBypass` int NOT NULL DEFAULT '0',
  `hidden` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`permissionId`),
  UNIQUE KEY `id_UNIQUE` (`permissionId`),
  KEY `fk_admin_permissions_admin_permissions1_idx` (`noPermFallback`),
  CONSTRAINT `fk_admin_permissions_admin_permissions1` FOREIGN KEY (`noPermFallback`) REFERENCES `admin_permissions` (`permissionId`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.users
CREATE TABLE IF NOT EXISTS `users` (
  `loginId` int NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `email` varchar(450) DEFAULT NULL,
  `passwordhash` varchar(64) NOT NULL,
  `registered` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sessionhash` varchar(64) DEFAULT NULL,
  `sessionstart` timestamp NULL DEFAULT NULL,
  `hidden` int DEFAULT '0',
  `forgothash` varchar(64) NOT NULL DEFAULT 'test',
  `forgotvalid` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`loginId`),
  UNIQUE KEY `id_UNIQUE` (`loginId`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.user_groups
CREATE TABLE IF NOT EXISTS `user_groups` (
  `groupId` int NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `hidden` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`groupId`),
  UNIQUE KEY `id_UNIQUE` (`groupId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.user_group_has_permissions
CREATE TABLE IF NOT EXISTS `user_group_has_permissions` (
  `ghpId` int NOT NULL AUTO_INCREMENT,
  `groupId` int NOT NULL,
  `permissionId` int NOT NULL,
  PRIMARY KEY (`ghpId`),
  UNIQUE KEY `id_UNIQUE` (`ghpId`),
  KEY `fk_user_group_has_permission_user_groups1_idx` (`groupId`),
  KEY `fk_user_group_has_permission_user_permissions1_idx` (`permissionId`),
  CONSTRAINT `fk_user_group_has_permission_user_groups1` FOREIGN KEY (`groupId`) REFERENCES `user_groups` (`groupId`),
  CONSTRAINT `fk_user_group_has_permission_user_permissions1` FOREIGN KEY (`permissionId`) REFERENCES `user_permissions` (`permissionId`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.user_has_groups
CREATE TABLE IF NOT EXISTS `user_has_groups` (
  `lhgId` int NOT NULL AUTO_INCREMENT,
  `groupId` int NOT NULL,
  `loginId` int NOT NULL,
  PRIMARY KEY (`lhgId`),
  UNIQUE KEY `id_UNIQUE` (`lhgId`),
  KEY `fk_user_has_groups_user_groups1_idx` (`groupId`),
  KEY `fk_user_has_groups_users1_idx` (`loginId`),
  CONSTRAINT `fk_user_has_groups_user_groups1` FOREIGN KEY (`groupId`) REFERENCES `user_groups` (`groupId`),
  CONSTRAINT `fk_user_has_groups_users1` FOREIGN KEY (`loginId`) REFERENCES `users` (`loginId`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.user_permissions
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `permissionId` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `noPermFallback` int DEFAULT NULL,
  `allowBypass` int NOT NULL DEFAULT '0',
  `hidden` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`permissionId`),
  UNIQUE KEY `id_UNIQUE` (`permissionId`),
  KEY `fk_user_permissions_user_permissions1_idx` (`noPermFallback`),
  CONSTRAINT `fk_user_permissions_user_permissions1` FOREIGN KEY (`noPermFallback`) REFERENCES `user_permissions` (`permissionId`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;

-- Daten-Export vom Benutzer nicht ausgewählt

-- Exportiere Struktur von Tabelle repository.user_settings
CREATE TABLE IF NOT EXISTS `user_settings` (
  `settingId` int NOT NULL AUTO_INCREMENT,
  `loginId` int NOT NULL,
  `icon_path` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '/images/icon.png',
  `language` varchar(45) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'German',
  PRIMARY KEY (`settingId`,`loginId`),
  UNIQUE KEY `settingId_UNIQUE` (`settingId`),
  KEY `fk_user_settings_users1_idx` (`loginId`),
  CONSTRAINT `fk_user_settings_users1` FOREIGN KEY (`loginId`) REFERENCES `users` (`loginId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daten-Export vom Benutzer nicht ausgewählt

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
