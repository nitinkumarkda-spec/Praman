-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: pramaan_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

CREATE DATABASE IF NOT EXISTS `pramaan_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pramaan_db`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `area_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `area_code` varchar(30) NOT NULL,
  `area_name` varchar(60) NOT NULL,
  `name_hi` varchar(60) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`area_id`),
  UNIQUE KEY `uq_areas_code` (`area_code`),
  UNIQUE KEY `uq_areas_name` (`area_name`),
  KEY `ix_areas_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
INSERT INTO `areas` VALUES (1,'AREA-1','Area 1','एरिया 1','North Kota',1,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(2,'AREA-2','Area 2','एरिया 2','South Kota',2,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(3,'AREA-3','Area 3','एरिया 3','East Kota belt',3,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(4,'AREA-4','Area 4','एरिया 4','West Kota',4,1,0,1,'2026-09-30 14:59:52',NULL,NULL);
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachments` (
  `attachment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned DEFAULT NULL,
  `round_id` int(10) unsigned DEFAULT NULL,
  `property_id` int(10) unsigned NOT NULL,
  `context` varchar(30) NOT NULL DEFAULT 'TASK_RESPONSE',
  `file_name` varchar(150) NOT NULL,
  `stored_name` varchar(80) NOT NULL,
  `mime` varchar(50) NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `sha256` char(64) NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  UNIQUE KEY `uq_attachments_stored` (`stored_name`),
  KEY `ix_attachments_task` (`task_id`),
  KEY `ix_attachments_property` (`property_id`),
  KEY `fk_attachments_round` (`round_id`),
  KEY `fk_attachments_user` (`uploaded_by`),
  CONSTRAINT `fk_attachments_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`),
  CONSTRAINT `fk_attachments_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`round_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachments_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachments`
--

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
INSERT INTO `attachments` VALUES (1,38,5,7,'TASK_RESPONSE','site_inspection_photo.jpg','2026/10/aeae1720d78b9da4caa8c0769243747d.jpg','image/jpeg',16998,'92a97ba010d2e268da80f7ae8ef079120997c1bdd4c34d80031aeefebac8c3d3',14,'2026-10-28 11:59:52'),(2,38,5,7,'TASK_RESPONSE','inspection_report.pdf','2026/10/e1da5832435b2af43ccbe1397f64ee71.pdf','application/pdf',848,'13202ae536fca79895c8b40ab3517ae71582b341f1390fc44eb81a1e7a56bfe5',14,'2026-10-28 11:59:52'),(3,48,6,8,'TASK_RESPONSE','stay_order_cs_45_2023.pdf','2026/10/3679902e2ee1d583e7a0a30afbd464b2.pdf','application/pdf',787,'aa279a7a3451b5f762a9efcb72d012a021235a51fad404c5451940d556451bae',18,'2026-10-27 14:59:52'),(9,NULL,NULL,15,'SITE_PLAN','Print Online Calendar 2027.pdf','2026/10/b504b2b43e039a9e6a3f814faeeb3bf8.pdf','application/pdf',307775,'e7884dff0b4bca6f8f4a2fc629b19b8a93812c3f0752055b83b224d33c34d457',2,'2026-10-30 16:38:09');
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `property_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `performed_by` int(10) unsigned DEFAULT NULL,
  `performed_by_name` varchar(120) NOT NULL DEFAULT 'System',
  `performed_by_designation` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `ix_audit_entity` (`entity_type`,`entity_id`),
  KEY `ix_audit_property` (`property_id`,`log_id`),
  KEY `ix_audit_user` (`performed_by`,`log_id`),
  KEY `ix_audit_time` (`performed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=213 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,'SYSTEM',NULL,NULL,'DATABASE_SEEDED',NULL,'{\"roles\":8,\"users\":22}',1,'System Administrator','Programmer (IT Cell)','127.0.0.1','PRAMAAN installer','2026-09-30 14:59:52'),(2,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-10 14:29:52'),(3,'SCHEME',1,NULL,'CREATED',NULL,'{\"scheme_name\":\"Ravindra Nagar Awasiya Yojana\",\"scheme_code\":\"RNY-2019\",\"scheme_type\":\"RESIDENTIAL\",\"area_id\":1,\"year_of_scheme\":2019,\"sectors\":\"Sector-1, Sector-2, Sector-3, Sector-4\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-10 14:59:52'),(4,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-12 14:29:52'),(5,'SCHEME',2,NULL,'CREATED',NULL,'{\"scheme_name\":\"Vigyan Nagar Commercial Complex\",\"scheme_code\":\"VNC-2021\",\"scheme_type\":\"COMMERCIAL\",\"area_id\":2,\"year_of_scheme\":2021,\"sectors\":\"Block-A, Block-B\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-12 14:59:52'),(6,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-14 14:29:52'),(7,'SCHEME',3,NULL,'CREATED',NULL,'{\"scheme_name\":\"Kunhadi Industrial Area\",\"scheme_code\":\"KIA-2016\",\"scheme_type\":\"INDUSTRIAL\",\"area_id\":3,\"year_of_scheme\":2016,\"sectors\":\"Phase-I, Phase-II, Phase-III\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-14 14:59:52'),(8,'SCHEME',1,NULL,'UPDATED','{\"year_of_scheme\":2018}','{\"year_of_scheme\":2019}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-15 14:59:52'),(9,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-20 14:39:52'),(10,'PROPERTY',1,1,'CREATED',NULL,'{\"property_code\":\"PRM-000001\",\"plot_no\":\"101\",\"property_type\":\"PLOT\",\"area_value\":150,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-20 14:59:52'),(11,'PROPERTY',2,2,'CREATED',NULL,'{\"property_code\":\"PRM-000002\",\"plot_no\":\"102\",\"property_type\":\"PLOT\",\"area_value\":180,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-20 17:59:52'),(12,'PROPERTY',3,3,'CREATED',NULL,'{\"property_code\":\"PRM-000003\",\"plot_no\":\"205-A\",\"property_type\":\"PLOT\",\"area_value\":2400,\"area_unit\":\"SQFT\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-20 20:59:52'),(13,'PROPERTY',4,4,'CREATED',NULL,'{\"property_code\":\"PRM-000004\",\"plot_no\":\"310\",\"property_type\":\"FLAT\",\"area_value\":95.5,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-20 23:59:52'),(14,'PROPERTY',5,5,'CREATED',NULL,'{\"property_code\":\"PRM-000005\",\"plot_no\":\"SH-12\",\"property_type\":\"SHOP\",\"area_value\":320,\"area_unit\":\"SQFT\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-21 02:59:52'),(15,'PROPERTY',6,6,'CREATED',NULL,'{\"property_code\":\"PRM-000006\",\"plot_no\":\"K-04\",\"property_type\":\"KIOSK\",\"area_value\":12,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-21 05:59:52'),(16,'PROPERTY',7,7,'CREATED',NULL,'{\"property_code\":\"PRM-000007\",\"plot_no\":\"IND-21\",\"property_type\":\"PLOT\",\"area_value\":1000,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-21 08:59:52'),(17,'PROPERTY',8,8,'CREATED',NULL,'{\"property_code\":\"PRM-000008\",\"plot_no\":\"IND-45\",\"property_type\":\"PLOT\",\"area_value\":1500,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-21 11:59:52'),(18,'PROPERTY',9,9,'CREATED',NULL,'{\"property_code\":\"PRM-000009\",\"plot_no\":\"412\",\"property_type\":\"PLOT\",\"area_value\":200,\"area_unit\":\"SQM\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-21 14:59:52'),(19,'PROPERTY',10,10,'CREATED',NULL,'{\"property_code\":\"PRM-000010\",\"plot_no\":\"SH-15\",\"property_type\":\"SHOP\",\"area_value\":280,\"area_unit\":\"SQFT\",\"status\":\"LISTED\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-21 17:59:52'),(20,'PROPERTY',2,2,'UPDATED','{\"area_value\":175,\"remarks\":null}','{\"area_value\":180,\"remarks\":\"Area corrected as per site map\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-25 14:59:52'),(21,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:34:52'),(22,'ROUND',1,3,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:59:52'),(23,'ROUND',2,4,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:59:52'),(24,'ROUND',3,5,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:59:52'),(25,'ROUND',4,6,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:59:52'),(26,'ROUND',5,7,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:59:52'),(27,'ROUND',6,8,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-22 14:59:52'),(28,'TASK',1,3,'TASK_APPROVED',NULL,'{\"role\":\"Auction Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','127.0.0.1','PRAMAAN installer','2026-10-22 16:36:52'),(29,'TASK',2,3,'TASK_APPROVED',NULL,'{\"role\":\"Lottery Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','127.0.0.1','PRAMAAN installer','2026-10-22 18:13:52'),(30,'TASK',3,3,'TASK_APPROVED',NULL,'{\"role\":\"Sale Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',8,'Deepak Verma','Clerk, Sale Cell','127.0.0.1','PRAMAAN installer','2026-10-22 19:50:52'),(31,'TASK',4,3,'TASK_APPROVED',NULL,'{\"role\":\"Land-for-Land Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',10,'Bhanwar Lal Bairwa','Assistant, Land-for-Land','127.0.0.1','PRAMAAN installer','2026-10-22 21:27:52'),(32,'TASK',5,3,'TASK_APPROVED',NULL,'{\"role\":\"Punarvas Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',12,'Rajendra Prasad','Assistant, Punarvas Cell','127.0.0.1','PRAMAAN installer','2026-10-22 23:04:52'),(33,'TASK',17,5,'TASK_APPROVED',NULL,'{\"role\":\"Auction Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','127.0.0.1','PRAMAAN installer','2026-10-23 00:41:52'),(34,'TASK',18,5,'TASK_APPROVED',NULL,'{\"role\":\"Lottery Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','127.0.0.1','PRAMAAN installer','2026-10-23 02:18:52'),(35,'TASK',19,5,'TASK_APPROVED',NULL,'{\"role\":\"Sale Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',8,'Deepak Verma','Clerk, Sale Cell','127.0.0.1','PRAMAAN installer','2026-10-23 03:55:52'),(36,'TASK',20,5,'TASK_APPROVED',NULL,'{\"role\":\"Land-for-Land Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',10,'Bhanwar Lal Bairwa','Assistant, Land-for-Land','127.0.0.1','PRAMAAN installer','2026-10-23 05:32:52'),(37,'TASK',21,5,'TASK_APPROVED',NULL,'{\"role\":\"Punarvas Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',12,'Rajendra Prasad','Assistant, Punarvas Cell','127.0.0.1','PRAMAAN installer','2026-10-23 07:09:52'),(38,'TASK',22,5,'TASK_APPROVED',NULL,'{\"role\":\"Engineering Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-23 08:46:52'),(39,'TASK',23,5,'TASK_APPROVED',NULL,'{\"role\":\"Town Planning Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',16,'Shweta Joshi','Assistant Town Planner','127.0.0.1','PRAMAAN installer','2026-10-23 10:23:52'),(40,'TASK',24,5,'TASK_APPROVED',NULL,'{\"role\":\"Legal Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-23 12:00:52'),(41,'ROUND',3,5,'ROUND_CLOSED',NULL,'{\"result\":\"READY\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-23 12:00:52'),(42,'TASK',25,6,'TASK_APPROVED',NULL,'{\"role\":\"Auction Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','127.0.0.1','PRAMAAN installer','2026-10-23 13:37:52'),(43,'TASK',26,6,'TASK_APPROVED',NULL,'{\"role\":\"Lottery Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','127.0.0.1','PRAMAAN installer','2026-10-23 15:14:52'),(44,'TASK',27,6,'TASK_APPROVED',NULL,'{\"role\":\"Sale Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',8,'Deepak Verma','Clerk, Sale Cell','127.0.0.1','PRAMAAN installer','2026-10-23 16:51:52'),(45,'TASK',28,6,'TASK_APPROVED',NULL,'{\"role\":\"Land-for-Land Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',10,'Bhanwar Lal Bairwa','Assistant, Land-for-Land','127.0.0.1','PRAMAAN installer','2026-10-23 18:28:52'),(46,'TASK',29,6,'TASK_APPROVED',NULL,'{\"role\":\"Punarvas Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',12,'Rajendra Prasad','Assistant, Punarvas Cell','127.0.0.1','PRAMAAN installer','2026-10-23 20:05:52'),(47,'TASK',30,6,'TASK_APPROVED',NULL,'{\"role\":\"Engineering Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-23 21:42:52'),(48,'TASK',31,6,'TASK_APPROVED',NULL,'{\"role\":\"Town Planning Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',16,'Shweta Joshi','Assistant Town Planner','127.0.0.1','PRAMAAN installer','2026-10-23 23:19:52'),(49,'TASK',32,6,'TASK_APPROVED',NULL,'{\"role\":\"Legal Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-24 00:56:52'),(50,'ROUND',4,6,'ROUND_CLOSED',NULL,'{\"result\":\"READY\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-24 00:56:52'),(51,'TASK',33,7,'TASK_APPROVED',NULL,'{\"role\":\"Auction Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','127.0.0.1','PRAMAAN installer','2026-10-24 02:33:52'),(52,'TASK',34,7,'TASK_APPROVED',NULL,'{\"role\":\"Lottery Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','127.0.0.1','PRAMAAN installer','2026-10-24 04:10:52'),(53,'TASK',35,7,'TASK_APPROVED',NULL,'{\"role\":\"Sale Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',8,'Deepak Verma','Clerk, Sale Cell','127.0.0.1','PRAMAAN installer','2026-10-24 05:47:52'),(54,'TASK',36,7,'TASK_APPROVED',NULL,'{\"role\":\"Land-for-Land Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',10,'Bhanwar Lal Bairwa','Assistant, Land-for-Land','127.0.0.1','PRAMAAN installer','2026-10-24 07:24:52'),(55,'TASK',37,7,'TASK_APPROVED',NULL,'{\"role\":\"Punarvas Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',12,'Rajendra Prasad','Assistant, Punarvas Cell','127.0.0.1','PRAMAAN installer','2026-10-24 09:01:52'),(56,'TASK',39,7,'TASK_APPROVED',NULL,'{\"role\":\"Town Planning Cell\",\"round_no\":1,\"remarks\":\"Record clear - no objection.\"}',16,'Shweta Joshi','Assistant Town Planner','127.0.0.1','PRAMAAN installer','2026-10-24 10:38:52'),(57,'TASK',38,7,'TASK_REJECTED',NULL,'{\"role\":\"Engineering Cell\",\"round_no\":1,\"remarks\":\"Encroachment - temporary tin shed approx. 8 sq m on the front side.\",\"attachments\":[\"site_inspection_photo.jpg\",\"inspection_report.pdf\"]}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-28 11:59:52'),(58,'ROUND',5,7,'ROUND_CLOSED',NULL,'{\"result\":\"NOT_READY\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-28 11:59:52'),(59,'TASK',48,8,'TASK_REJECTED',NULL,'{\"role\":\"Legal Cell\",\"round_no\":1,\"remarks\":\"Stay order in civil suit no. 45\\/2023 - pending.\",\"attachments\":[\"stay_order_cs_45_2023.pdf\"]}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-27 14:59:52'),(60,'ROUND',6,8,'ROUND_CLOSED',NULL,'{\"result\":\"NOT_READY\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-27 14:59:52'),(61,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-29 14:44:52'),(62,'ROUND',7,8,'REINITIATED','{\"status\":\"NOT_READY\"}','{\"round_no\":2,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":\"Stay vacated by court order dated last month - record corrected.\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-29 14:59:53'),(63,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','127.0.0.1','PRAMAAN installer','2026-10-24 04:59:53'),(64,'AUTH',1,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',1,'System Administrator','Programmer (IT Cell)','127.0.0.1','PRAMAAN installer','2026-10-24 05:44:53'),(65,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-24 09:59:53'),(66,'AUTH',14,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-24 10:29:53'),(67,'AUTH',4,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','127.0.0.1','PRAMAAN installer','2026-10-25 11:39:53'),(68,'AUTH',4,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','127.0.0.1','PRAMAAN installer','2026-10-25 11:59:53'),(69,'AUTH',16,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',16,'Shweta Joshi','Assistant Town Planner','127.0.0.1','PRAMAAN installer','2026-10-26 07:59:53'),(70,'AUTH',16,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',16,'Shweta Joshi','Assistant Town Planner','127.0.0.1','PRAMAAN installer','2026-10-26 08:24:53'),(71,'AUTH',18,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-27 13:59:53'),(72,'AUTH',18,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-27 14:14:53'),(73,'AUTH',15,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',15,'Manoj Kumar Yadav','Assistant Engineer','127.0.0.1','PRAMAAN installer','2026-10-28 10:59:53'),(74,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','127.0.0.1','PRAMAAN installer','2026-10-29 06:39:53'),(75,'AUTH',8,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',8,'Deepak Verma','Clerk, Sale Cell','127.0.0.1','PRAMAAN installer','2026-10-29 06:59:53'),(76,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-30 09:59:53'),(77,'AUTH',3,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','127.0.0.1','PRAMAAN installer','2026-10-30 10:59:53'),(78,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','127.0.0.1','PRAMAAN installer','2026-10-30 10:59:53'),(79,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-30 12:29:53'),(80,'AUTH',14,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',14,'Vikas Choudhary','Junior Engineer','127.0.0.1','PRAMAAN installer','2026-10-30 13:04:53'),(81,'AUTH',18,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',18,'Advocate Ritu Sharma','Legal Assistant','127.0.0.1','PRAMAAN installer','2026-10-30 13:24:53'),(82,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','127.0.0.1','PRAMAAN installer','2026-10-30 14:19:53'),(83,'PROPERTY',5,5,'VALIDITY_EXPIRED','{\"status\":\"AUCTION_READY\"}','{\"status\":\"EXPIRED\",\"valid_till\":\"2026-10-28\",\"round_id\":3}',NULL,'System','Automatic','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 15:05:56'),(84,'AUTH',1,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 15:07:24'),(85,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 15:51:08'),(86,'USER',2,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"dataentry\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 15:52:01'),(87,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','curl/8.21.0','2026-10-30 15:55:19'),(93,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 15:57:21'),(94,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 15:57:48'),(95,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 15:58:16'),(96,'USER',2,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 15:58:21'),(97,'USER',2,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"dataentry\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 15:59:12'),(98,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','curl/8.21.0','2026-10-30 16:11:26'),(99,'AUTH',4,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',4,'Anil Kumar Jain','Assistant, Auction Cell','::1','curl/8.21.0','2026-10-30 16:11:27'),(100,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','curl/8.21.0','2026-10-30 16:11:27'),(101,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','curl/8.21.0','2026-10-30 16:12:06'),(103,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 16:12:30'),(104,'USER',2,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:15:19'),(105,'AUTH',1,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:18:33'),(106,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:18:39'),(107,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 16:25:34'),(108,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 16:25:47'),(109,'AUTH',8,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:26:45'),(110,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:26:50'),(111,'AUTH',6,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:27:50'),(112,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:27:59'),(113,'AUTH',8,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:30:51'),(114,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:31:06'),(115,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 16:33:57'),(116,'AUTH',6,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:34:07'),(117,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:34:14'),(118,'USER',15,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"eng2\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:34:32'),(119,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 16:34:48'),(120,'AUTH',15,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',15,'Manoj Kumar Yadav','Assistant Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:34:56'),(121,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:35:11'),(122,'USER',2,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"dataentry\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:35:19'),(123,'USER',2,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:36:26'),(124,'USER',3,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"oic\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:36:37'),(125,'USER',3,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:36:51'),(126,'USER',2,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"dataentry\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:37:00'),(127,'PROPERTY',15,15,'CREATED',NULL,'{\"scheme_id\":3,\"sector_id\":7,\"plot_no\":\"14\",\"property_type\":\"PLOT\",\"area_value\":280,\"area_unit\":\"SQM\",\"area_sqm\":280,\"remarks\":null,\"reserve_price\":12452,\"site_plan\":\"Print Online Calendar 2027.pdf\",\"_impersonated_by_admin_id\":1}',2,'Ramesh Kumar Meena [by Super Admin: System Administrator]','Data Entry Operator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:38:09'),(128,'USER',2,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:38:25'),(129,'USER',3,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"oic\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:38:40'),(130,'ROUND',8,15,'ROUND_INITIATED','{\"status\":\"LISTED\"}','{\"round_no\":1,\"roles\":[\"Auction Cell\",\"Lottery Cell\",\"Sale Cell\",\"Land-for-Land Cell\",\"Punarvas Cell\",\"Engineering Cell\",\"Town Planning Cell\",\"Legal Cell\"],\"reason\":null,\"_impersonated_by_admin_id\":1}',3,'Suresh Chand Sharma [by Super Admin: System Administrator]','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:38:51'),(131,'USER',3,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:38:59'),(132,'USER',17,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"tp2\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:39:16'),(133,'USER',17,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:39:27'),(134,'USER',3,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"oic\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:39:45'),(135,'USER',3,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:40:08'),(136,'USER',3,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"oic\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:41:55'),(137,'AUTH',3,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:49:51'),(138,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 16:50:30'),(139,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','curl/8.21.0','2026-10-30 17:07:29'),(140,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:07:33'),(141,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:07:53'),(142,'AUTH',6,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:10:08'),(143,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:10:15'),(144,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','curl/8.21.0','2026-10-30 17:17:56'),(145,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','curl/8.21.0','2026-10-30 17:17:56'),(147,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:18:28'),(148,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:18:37'),(149,'USER',3,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"oic\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:18:43'),(150,'USER',3,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:18:59'),(151,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:19:01'),(152,'USER',17,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"tp2\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:19:08'),(153,'USER',17,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:19:21'),(154,'USER',2,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"dataentry\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:20:29'),(155,'USER',2,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:22:46'),(156,'USER',15,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"eng2\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:22:55'),(157,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','curl/8.21.0','2026-10-30 17:42:23'),(158,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:43:05'),(159,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:43:34'),(160,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:43:56'),(161,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-10-30 17:44:16'),(162,'AUTH',15,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',15,'Manoj Kumar Yadav','Assistant Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:50:07'),(163,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:50:12'),(164,'USER',7,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"lottery2\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:50:19'),(165,'USER',7,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:50:28'),(166,'USER',8,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"sale1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:50:43'),(167,'USER',8,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:53:01'),(168,'USER',6,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"lottery1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-30 17:53:08'),(169,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 09:33:28'),(170,'USER',21,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"eng4\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 09:50:25'),(171,'USER',21,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:11:53'),(172,'USER',8,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"sale1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:12:07'),(173,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','curl/8.21.0','2026-11-02 10:19:25'),(174,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:19:32'),(175,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','curl/8.21.0','2026-11-02 10:31:28'),(176,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','curl/8.21.0','2026-11-02 10:31:29'),(177,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','curl/8.21.0','2026-11-02 10:31:29'),(178,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','curl/8.21.0','2026-11-02 10:31:30'),(187,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:31:53'),(188,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:32:02'),(189,'USER',8,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:33:06'),(190,'USER',14,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"eng1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:34:10'),(191,'USER',14,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:35:21'),(192,'USER',8,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"sale1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:36:00'),(193,'USER',8,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:45:21'),(194,'USER',18,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"legal1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:46:43'),(195,'USER',18,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:46:50'),(196,'USER',18,NULL,'IMPERSONATION_STARTED',NULL,'{\"target\":\"legal1\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:46:53'),(197,'USER',18,NULL,'IMPERSONATION_ENDED',NULL,NULL,1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:49:57'),(198,'AUTH',1,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:50:26'),(199,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 10:50:29'),(200,'AUTH',1,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:56:24'),(201,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:56:36'),(202,'AUTH',3,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:56:41'),(203,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:57:00'),(204,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:57:12'),(205,'AUTH',3,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:57:17'),(206,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:57:36'),(207,'AUTH',3,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',3,'Suresh Chand Sharma','OIC — Sale / Auction','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:58:00'),(208,'AUTH',6,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',6,'Mahesh Gurjar','Assistant, Lottery Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 10:58:12'),(209,'AUTH',2,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',2,'Ramesh Kumar Meena','Data Entry Operator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 11:04:34'),(210,'AUTH',14,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',14,'Vikas Choudhary','Junior Engineer','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 11:04:45'),(211,'AUTH',8,NULL,'LOGIN',NULL,'{\"message\":\"Login successful\"}',8,'Deepak Verma','Clerk, Sale Cell','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','2026-11-02 11:04:55'),(212,'AUTH',1,NULL,'LOGOUT',NULL,'{\"message\":\"User logged out\"}',1,'System Administrator','Programmer (IT Cell)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-02 11:19:00');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `attempt_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `ix_login_user` (`username`,`success`,`attempted_at`),
  KEY `ix_login_ip` (`ip_address`,`success`,`attempted_at`),
  KEY `ix_login_time` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'dataentry','127.0.0.1',1,'2026-10-10 14:29:52'),(2,'dataentry','127.0.0.1',1,'2026-10-12 14:29:52'),(3,'dataentry','127.0.0.1',1,'2026-10-14 14:29:52'),(4,'dataentry','127.0.0.1',1,'2026-10-20 14:39:52'),(5,'oic','127.0.0.1',1,'2026-10-22 14:34:52'),(6,'oic','127.0.0.1',1,'2026-10-29 14:44:52'),(7,'superadmin','127.0.0.1',1,'2026-10-24 04:59:53'),(8,'eng1','127.0.0.1',1,'2026-10-24 09:59:53'),(9,'auction1','127.0.0.1',1,'2026-10-25 11:39:53'),(10,'tp1','127.0.0.1',1,'2026-10-26 07:59:53'),(11,'legal1','127.0.0.1',1,'2026-10-27 13:59:53'),(12,'eng2','127.0.0.1',1,'2026-10-28 10:59:53'),(13,'sale1','127.0.0.1',1,'2026-10-29 06:39:53'),(14,'oic','127.0.0.1',1,'2026-10-30 09:59:53'),(15,'dataentry','127.0.0.1',1,'2026-10-30 10:59:53'),(16,'eng1','127.0.0.1',1,'2026-10-30 12:29:53'),(17,'legal1','127.0.0.1',1,'2026-10-30 13:24:53'),(18,'superadmin','127.0.0.1',1,'2026-10-30 14:19:53'),(19,'eng2','127.0.0.1',0,'2026-10-28 13:19:53'),(20,'tp1','127.0.0.1',0,'2026-10-30 13:49:53'),(21,'superadmin','::1',1,'2026-10-30 15:51:08'),(22,'dataentry','::1',1,'2026-10-30 15:55:19'),(23,'dataentry','::1',1,'2026-10-30 15:57:21'),(24,'dataentry','::1',1,'2026-10-30 15:57:48'),(25,'dataentry','::1',1,'2026-10-30 15:58:16'),(26,'sale1','::1',1,'2026-10-30 16:11:26'),(27,'auction1','::1',1,'2026-10-30 16:11:27'),(28,'lottery1','::1',1,'2026-10-30 16:11:27'),(29,'dataentry','::1',1,'2026-10-30 16:12:06'),(30,'sale1','::1',1,'2026-10-30 16:12:30'),(31,'sale1','::1',1,'2026-10-30 16:18:39'),(32,'sale1','::1',1,'2026-10-30 16:25:34'),(33,'lottery1','::1',1,'2026-10-30 16:25:47'),(34,'lottery1','::1',1,'2026-10-30 16:26:50'),(35,'sale1','::1',1,'2026-10-30 16:27:59'),(36,'lottery','::1',0,'2026-10-30 16:31:01'),(37,'lottery1','::1',1,'2026-10-30 16:31:06'),(38,'lottery1','::1',1,'2026-10-30 16:33:57'),(39,'superadmin','::1',1,'2026-10-30 16:34:14'),(40,'lottery1','::1',1,'2026-10-30 16:34:48'),(41,'superadmin','::1',1,'2026-10-30 16:35:11'),(42,'gen1','::1',0,'2026-10-30 16:50:00'),(43,'genral1','::1',0,'2026-10-30 16:50:08'),(44,'lottery1','::1',1,'2026-10-30 16:50:30'),(45,'lottery1','::1',1,'2026-10-30 17:07:29'),(46,'lottery1','::1',1,'2026-10-30 17:07:33'),(47,'lottery1','::1',1,'2026-10-30 17:07:53'),(48,'superadmin','::1',1,'2026-10-30 17:10:15'),(49,'superadmin','::1',1,'2026-10-30 17:17:56'),(50,'dataentry','::1',1,'2026-10-30 17:17:56'),(51,'superadmin','::1',1,'2026-10-30 17:18:28'),(52,'dataentry','::1',1,'2026-10-30 17:18:37'),(53,'eng1','::1',1,'2026-10-30 17:19:01'),(54,'eng1','::1',1,'2026-10-30 17:42:23'),(55,'eng1','::1',1,'2026-10-30 17:43:05'),(56,'eng1','::1',1,'2026-10-30 17:43:34'),(57,'eng1','::1',1,'2026-10-30 17:43:56'),(58,'eng1','::1',1,'2026-10-30 17:44:16'),(59,'superadmin','::1',1,'2026-10-30 17:50:12'),(60,'superadmin','::1',1,'2026-11-02 09:33:28'),(61,'sale1','::1',1,'2026-11-02 10:19:25'),(62,'sale1','::1',1,'2026-11-02 10:19:32'),(63,'dataentry','::1',1,'2026-11-02 10:31:28'),(64,'oic','::1',1,'2026-11-02 10:31:29'),(65,'eng1','::1',1,'2026-11-02 10:31:29'),(66,'sale1','::1',1,'2026-11-02 10:31:30'),(67,'eng1','::1',1,'2026-11-02 10:31:53'),(68,'lottery1','::1',1,'2026-11-02 10:32:02'),(69,'superadmin','::1',0,'2026-11-02 10:32:55'),(70,'superadmin','::1',1,'2026-11-02 10:50:29'),(71,'superadmin','::1',1,'2026-11-02 10:56:24'),(72,'oic','::1',1,'2026-11-02 10:56:36'),(73,'oic','::1',1,'2026-11-02 10:57:00'),(74,'oic','::1',1,'2026-11-02 10:57:12'),(75,'oic','::1',1,'2026-11-02 10:57:36'),(76,'oic','::1',1,'2026-11-02 10:58:00'),(77,'lottery1','::1',1,'2026-11-02 10:58:12'),(78,'dataentry','::1',1,'2026-11-02 11:04:34'),(79,'eng1','::1',1,'2026-11-02 11:04:45'),(80,'sale1','::1',1,'2026-11-02 11:04:55');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` enum('TASK','VERIFICATION','PROPERTY','ACCOUNT','SYSTEM') NOT NULL DEFAULT 'SYSTEM',
  `title` varchar(200) NOT NULL,
  `message` varchar(600) NOT NULL DEFAULT '',
  `link` varchar(255) DEFAULT NULL,
  `icon` varchar(40) NOT NULL DEFAULT 'fa-bell',
  `tone` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `ref` varchar(80) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `uq_notifications_ref` (`user_id`,`ref`),
  KEY `ix_notifications_user` (`user_id`,`deleted_at`,`notification_id`),
  KEY `ix_notifications_unread` (`user_id`,`is_read`,`deleted_at`),
  KEY `ix_notifications_deleted` (`deleted_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=352 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'ACCOUNT','Welcome to PRAMAAN','Your Super Admin account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:49'),(2,2,'ACCOUNT','Welcome to PRAMAAN','Your Data Entry account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:49'),(3,3,'ACCOUNT','Welcome to PRAMAAN','Your OIC account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:49'),(4,4,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:49'),(5,5,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(6,6,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(7,7,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(8,8,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(9,9,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(10,10,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(11,11,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:50',NULL,'2026-09-30 14:59:50'),(12,12,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(13,13,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(14,14,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(15,15,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(16,16,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(17,17,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(18,18,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(19,19,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:51',NULL,'2026-09-30 14:59:51'),(20,20,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:52',NULL,'2026-09-30 14:59:52'),(21,21,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:52',NULL,'2026-09-30 14:59:52'),(22,22,'ACCOUNT','Welcome to PRAMAAN','Your Role User account is ready.','/change-password','fa-hand-sparkles','success',NULL,1,'2026-09-30 14:59:52',NULL,'2026-09-30 14:59:52'),(23,3,'PROPERTY','New property listed — Plot 101','Ravindra Nagar Awasiya Yojana · PRM-000001 is ready to be sent for verification.','/oic/properties/1','fa-building-circle-check','info',NULL,1,'2026-10-20 14:59:52',NULL,'2026-10-20 14:59:52'),(24,3,'PROPERTY','New property listed — Plot 102','Ravindra Nagar Awasiya Yojana · PRM-000002 is ready to be sent for verification.','/oic/properties/2','fa-building-circle-check','info',NULL,1,'2026-10-20 17:59:52',NULL,'2026-10-20 17:59:52'),(25,3,'PROPERTY','New property listed — Plot 205-A','Ravindra Nagar Awasiya Yojana · PRM-000003 is ready to be sent for verification.','/oic/properties/3','fa-building-circle-check','info',NULL,1,'2026-10-20 20:59:52',NULL,'2026-10-20 20:59:52'),(26,3,'PROPERTY','New property listed — Plot 310','Ravindra Nagar Awasiya Yojana · PRM-000004 is ready to be sent for verification.','/oic/properties/4','fa-building-circle-check','info',NULL,1,'2026-10-20 23:59:52',NULL,'2026-10-20 23:59:52'),(27,3,'PROPERTY','New property listed — Plot SH-12','Vigyan Nagar Commercial Complex · PRM-000005 is ready to be sent for verification.','/oic/properties/5','fa-building-circle-check','info',NULL,1,'2026-10-21 02:59:52',NULL,'2026-10-21 02:59:52'),(28,3,'PROPERTY','New property listed — Plot K-04','Vigyan Nagar Commercial Complex · PRM-000006 is ready to be sent for verification.','/oic/properties/6','fa-building-circle-check','info',NULL,1,'2026-10-21 05:59:52',NULL,'2026-10-21 05:59:52'),(29,3,'PROPERTY','New property listed — Plot IND-21','Kunhadi Industrial Area · PRM-000007 is ready to be sent for verification.','/oic/properties/7','fa-building-circle-check','info',NULL,1,'2026-10-21 08:59:52',NULL,'2026-10-21 08:59:52'),(30,3,'PROPERTY','New property listed — Plot IND-45','Kunhadi Industrial Area · PRM-000008 is ready to be sent for verification.','/oic/properties/8','fa-building-circle-check','info',NULL,1,'2026-10-21 11:59:52',NULL,'2026-10-21 11:59:52'),(31,3,'PROPERTY','New property listed — Plot 412','Ravindra Nagar Awasiya Yojana · PRM-000009 is ready to be sent for verification.','/oic/properties/9','fa-building-circle-check','info',NULL,1,'2026-10-21 14:59:52',NULL,'2026-10-21 14:59:52'),(32,3,'PROPERTY','New property listed — Plot SH-15','Vigyan Nagar Commercial Complex · PRM-000010 is ready to be sent for verification.','/oic/properties/10','fa-building-circle-check','info',NULL,1,'2026-10-21 17:59:52',NULL,'2026-10-21 17:59:52'),(33,4,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Auction Cell · due 27-10-2026','/role/tasks/1','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(34,6,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Lottery Cell · due 27-10-2026','/role/tasks/2','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(35,8,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Sale Cell · due 27-10-2026','/role/tasks/3','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(36,10,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Land-for-Land Cell · due 27-10-2026','/role/tasks/4','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(37,12,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Punarvas Cell · due 27-10-2026','/role/tasks/5','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(38,14,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/6','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(39,15,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/6','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(40,20,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/6','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(41,21,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/6','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(42,16,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Town Planning Cell · due 29-10-2026','/role/tasks/7','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(43,18,'TASK','New verification task — Plot 205-A','Ravindra Nagar Awasiya Yojana / Sector-2 · Round R1 · Legal Cell · due 29-10-2026','/role/tasks/8','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(44,2,'VERIFICATION','Plot 205-A sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(45,4,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Auction Cell · due 27-10-2026','/role/tasks/9','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(46,6,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Lottery Cell · due 27-10-2026','/role/tasks/10','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(47,8,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Sale Cell · due 27-10-2026','/role/tasks/11','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(48,10,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Land-for-Land Cell · due 27-10-2026','/role/tasks/12','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(49,12,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Punarvas Cell · due 27-10-2026','/role/tasks/13','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(50,14,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/14','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(51,15,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/14','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(52,20,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/14','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(53,21,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/14','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(54,16,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Town Planning Cell · due 29-10-2026','/role/tasks/15','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(55,18,'TASK','New verification task — Plot 310','Ravindra Nagar Awasiya Yojana / Sector-3 · Round R1 · Legal Cell · due 29-10-2026','/role/tasks/16','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(56,2,'VERIFICATION','Plot 310 sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(57,4,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Auction Cell · due 27-10-2026','/role/tasks/17','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(58,6,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Lottery Cell · due 27-10-2026','/role/tasks/18','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(59,8,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Sale Cell · due 27-10-2026','/role/tasks/19','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(60,10,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Land-for-Land Cell · due 27-10-2026','/role/tasks/20','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(61,12,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Punarvas Cell · due 27-10-2026','/role/tasks/21','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(62,14,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/22','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(63,15,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/22','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(64,20,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/22','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(65,21,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/22','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(66,16,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Town Planning Cell · due 29-10-2026','/role/tasks/23','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(67,18,'TASK','New verification task — Plot SH-12','Vigyan Nagar Commercial Complex / Block-A · Round R1 · Legal Cell · due 29-10-2026','/role/tasks/24','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(68,2,'VERIFICATION','Plot SH-12 sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(69,4,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Auction Cell · due 27-10-2026','/role/tasks/25','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(70,6,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Lottery Cell · due 27-10-2026','/role/tasks/26','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(71,8,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Sale Cell · due 27-10-2026','/role/tasks/27','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(72,10,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Land-for-Land Cell · due 27-10-2026','/role/tasks/28','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(73,12,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Punarvas Cell · due 27-10-2026','/role/tasks/29','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(74,14,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/30','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(75,15,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/30','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(76,20,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/30','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(77,21,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/30','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(78,16,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Town Planning Cell · due 29-10-2026','/role/tasks/31','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(79,18,'TASK','New verification task — Plot K-04','Vigyan Nagar Commercial Complex / Block-B · Round R1 · Legal Cell · due 29-10-2026','/role/tasks/32','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(80,2,'VERIFICATION','Plot K-04 sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(81,4,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Auction Cell · due 27-10-2026','/role/tasks/33','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(82,6,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Lottery Cell · due 27-10-2026','/role/tasks/34','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(83,8,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Sale Cell · due 27-10-2026','/role/tasks/35','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(84,10,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Land-for-Land Cell · due 27-10-2026','/role/tasks/36','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(85,12,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Punarvas Cell · due 27-10-2026','/role/tasks/37','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(86,14,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/38','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(87,15,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/38','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(88,20,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/38','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(89,21,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/38','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(90,16,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Town Planning Cell · due 29-10-2026','/role/tasks/39','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(91,18,'TASK','New verification task — Plot IND-21','Kunhadi Industrial Area / Phase-I · Round R1 · Legal Cell · due 29-10-2026','/role/tasks/40','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(92,2,'VERIFICATION','Plot IND-21 sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(93,4,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Auction Cell · due 27-10-2026','/role/tasks/41','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(94,6,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Lottery Cell · due 27-10-2026','/role/tasks/42','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(95,8,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Sale Cell · due 27-10-2026','/role/tasks/43','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(96,10,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Land-for-Land Cell · due 27-10-2026','/role/tasks/44','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(97,12,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Punarvas Cell · due 27-10-2026','/role/tasks/45','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(98,14,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/46','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(99,15,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/46','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(100,20,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/46','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(101,21,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Engineering Cell · due 01-11-2026','/role/tasks/46','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(102,16,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Town Planning Cell · due 29-10-2026','/role/tasks/47','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(103,18,'TASK','New verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R1 · Legal Cell · due 29-10-2026','/role/tasks/48','fa-clipboard-list','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(104,2,'VERIFICATION','Plot IND-45 sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,1,'2026-10-22 14:59:52',NULL,'2026-10-22 14:59:52'),(105,3,'VERIFICATION','Plot SH-12 is AUCTION READY','All sections approved round R1. Valid for 180 days.','/oic/properties/5','fa-gavel','success',NULL,1,'2026-10-23 12:00:52',NULL,'2026-10-23 12:00:52'),(106,2,'VERIFICATION','Plot SH-12 is AUCTION READY','All sections approved round R1. Valid for 180 days.','/oic/properties/5','fa-gavel','success',NULL,1,'2026-10-23 12:00:52',NULL,'2026-10-23 12:00:52'),(107,3,'VERIFICATION','Plot K-04 is AUCTION READY','All sections approved round R1. Valid for 180 days.','/oic/properties/6','fa-gavel','success',NULL,1,'2026-10-24 00:56:52',NULL,'2026-10-24 00:56:52'),(108,2,'VERIFICATION','Plot K-04 is AUCTION READY','All sections approved round R1. Valid for 180 days.','/oic/properties/6','fa-gavel','success',NULL,1,'2026-10-24 00:56:52',NULL,'2026-10-24 00:56:52'),(109,3,'VERIFICATION','Engineering Cell rejected Plot IND-21','Round R1: \"Encroachment - temporary tin shed approx. 8 sq m on the front side.\". Property is NOT READY — re-send after correction.','/oic/properties/7','fa-circle-xmark','danger',NULL,0,NULL,NULL,'2026-10-28 11:59:52'),(110,2,'VERIFICATION','Plot IND-21 is Not Ready','Engineering Cell: Encroachment - temporary tin shed approx. 8 sq m on the front side.','/data-entry/properties','fa-circle-xmark','danger',NULL,0,NULL,NULL,'2026-10-28 11:59:52'),(111,18,'TASK','Response not required — Plot IND-21','Round R1 was closed because Engineering Cell rejected it.','/role/tasks/40','fa-minus','info',NULL,0,NULL,NULL,'2026-10-28 11:59:52'),(112,3,'VERIFICATION','Legal Cell rejected Plot IND-45','Round R1: \"Stay order in civil suit no. 45/2023 - pending.\". Property is NOT READY — re-send after correction.','/oic/properties/8','fa-circle-xmark','danger',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(113,2,'VERIFICATION','Plot IND-45 is Not Ready','Legal Cell: Stay order in civil suit no. 45/2023 - pending.','/data-entry/properties','fa-circle-xmark','danger',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(114,4,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/41','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(115,6,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/42','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(116,8,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/43','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(117,10,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/44','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(118,12,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/45','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(119,14,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/46','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(120,15,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/46','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(121,20,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/46','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(122,21,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/46','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(123,16,'TASK','Response not required — Plot IND-45','Round R1 was closed because Legal Cell rejected it.','/role/tasks/47','fa-minus','info',NULL,0,NULL,NULL,'2026-10-27 14:59:52'),(124,4,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Auction Cell · due 03-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/49','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:52'),(125,6,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Lottery Cell · due 03-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/50','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:52'),(126,8,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Sale Cell · due 03-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/51','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(127,10,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Land-for-Land Cell · due 03-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/52','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(128,12,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Punarvas Cell · due 03-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/53','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(129,14,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Engineering Cell · due 08-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/54','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(130,15,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Engineering Cell · due 08-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/54','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(131,20,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Engineering Cell · due 08-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/54','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(132,21,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Engineering Cell · due 08-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/54','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(133,16,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Town Planning Cell · due 05-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/55','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(134,18,'TASK','Re-verification task — Plot IND-45','Kunhadi Industrial Area / Phase-II · Round R2 · Legal Cell · due 05-11-2026 · Reason: Stay vacated by court order dated last month - record corrected.','/role/tasks/56','fa-clipboard-list','warning',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(135,2,'VERIFICATION','Plot IND-45 sent for verification','Round R2 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,0,NULL,NULL,'2026-10-29 14:59:53'),(136,1,'ACCOUNT','Failed login attempt — eng2','A wrong password was entered for eng2. The account locks after 5 failed attempts.','/admin/audit-logs?area=AUTH&quick=area:AUTH','fa-user-shield','warning',NULL,0,NULL,NULL,'2026-10-28 13:24:53'),(137,1,'ACCOUNT','Failed login attempt — tp1','A wrong password was entered for tp1 today.','/admin/audit-logs?area=AUTH&quick=area:AUTH','fa-user-shield','warning',NULL,0,NULL,NULL,'2026-10-30 13:54:53'),(138,1,'SYSTEM','Daily activity summary','Verification is active; 2 properties are Auction Ready and 2 need re-verification. Open Activity & Audit for details.','/admin/audit-logs','fa-chart-line','info',NULL,0,NULL,NULL,'2026-10-30 14:29:53'),(139,3,'VERIFICATION','Validity expired — Plot SH-12','Auction readiness ended on 28-10-2026. Send it for periodic re-verification before the next auction.','/oic/properties/5','fa-calendar-xmark','danger','EXPIRED-5-2026-10-28',0,NULL,NULL,'2026-10-30 15:05:56'),(142,8,'TASK','SLA overdue — Plot 310','Your verification response was due on 27-10-2026. Please respond as soon as possible.','/role/tasks/11','fa-stopwatch','danger','OVERDUE-11',0,NULL,NULL,'2026-10-30 16:11:26'),(143,4,'TASK','SLA overdue — Plot 310','Your verification response was due on 27-10-2026. Please respond as soon as possible.','/role/tasks/9','fa-stopwatch','danger','OVERDUE-9',0,NULL,NULL,'2026-10-30 16:11:27'),(144,6,'TASK','SLA overdue — Plot 310','Your verification response was due on 27-10-2026. Please respond as soon as possible.','/role/tasks/10','fa-stopwatch','danger','OVERDUE-10',0,NULL,NULL,'2026-10-30 16:11:27'),(185,3,'VERIFICATION','Re-verification due — Plot K-04','Auction readiness ends on 05-11-2026. Auction it before then or start re-verification.','/oic/properties/6','fa-calendar-day','warning','REVERIFY-6-2026-11-05',0,NULL,NULL,'2026-10-30 16:36:37'),(186,3,'PROPERTY','New property listed — Plot 14','Kunhadi Industrial Area · PRM-000015 is ready to be sent for verification.','/oic/properties/15','fa-building-circle-check','info',NULL,0,NULL,NULL,'2026-10-30 16:38:09'),(188,4,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Auction Cell · due 04-11-2026','/role/tasks/57','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(189,6,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Lottery Cell · due 04-11-2026','/role/tasks/58','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(190,8,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Sale Cell · due 04-11-2026','/role/tasks/59','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(191,10,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Land-for-Land Cell · due 04-11-2026','/role/tasks/60','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(192,12,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Punarvas Cell · due 04-11-2026','/role/tasks/61','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(193,14,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 09-11-2026','/role/tasks/62','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(194,15,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 09-11-2026','/role/tasks/62','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(195,20,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 09-11-2026','/role/tasks/62','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(196,21,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Engineering Cell · due 09-11-2026','/role/tasks/62','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(197,16,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Town Planning Cell · due 06-11-2026','/role/tasks/63','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(198,18,'TASK','New verification task — Plot 14','Kunhadi Industrial Area / Phase-I · Round R1 · Legal Cell · due 06-11-2026','/role/tasks/64','fa-clipboard-list','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(199,2,'VERIFICATION','Plot 14 sent for verification','Round R1 sent to 8 section(s) by OIC.','/data-entry/properties','fa-paper-plane','info',NULL,0,NULL,NULL,'2026-10-30 16:38:51'),(233,21,'TASK','SLA overdue — Plot 205-A','Your verification response was due on 01-11-2026. Please respond as soon as possible.','/role/tasks/6','fa-stopwatch','danger','OVERDUE-6',0,NULL,NULL,'2026-11-02 09:50:25'),(234,21,'TASK','SLA overdue — Plot 310','Your verification response was due on 01-11-2026. Please respond as soon as possible.','/role/tasks/14','fa-stopwatch','danger','OVERDUE-14',0,NULL,NULL,'2026-11-02 09:50:25'),(259,14,'TASK','SLA overdue — Plot 205-A','Your verification response was due on 01-11-2026. Please respond as soon as possible.','/role/tasks/6','fa-stopwatch','danger','OVERDUE-6',0,NULL,NULL,'2026-11-02 10:31:29'),(260,14,'TASK','SLA overdue — Plot 310','Your verification response was due on 01-11-2026. Please respond as soon as possible.','/role/tasks/14','fa-stopwatch','danger','OVERDUE-14',0,NULL,NULL,'2026-11-02 10:31:29'),(317,1,'ACCOUNT','Failed login attempt — superadmin','A wrong password was entered for superadmin. The account locks after 5 failed attempts.','/admin/audit-logs?area=AUTH&quick=area:AUTH','fa-user-shield','warning','FAILED-1-2026-11-02',0,NULL,NULL,'2026-11-02 10:32:55'),(328,18,'TASK','SLA overdue — Plot 205-A','Your verification response was due on 29-10-2026. Please respond as soon as possible.','/role/tasks/8','fa-stopwatch','danger','OVERDUE-8',0,NULL,NULL,'2026-11-02 10:46:43'),(329,18,'TASK','SLA overdue — Plot 310','Your verification response was due on 29-10-2026. Please respond as soon as possible.','/role/tasks/16','fa-stopwatch','danger','OVERDUE-16',0,NULL,NULL,'2026-11-02 10:46:43');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plot_types`
--

DROP TABLE IF EXISTS `plot_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plot_types` (
  `plot_type_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(60) NOT NULL,
  `name_hi` varchar(60) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`plot_type_id`),
  UNIQUE KEY `uq_plot_types_code` (`code`),
  UNIQUE KEY `uq_plot_types_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plot_types`
--

LOCK TABLES `plot_types` WRITE;
/*!40000 ALTER TABLE `plot_types` DISABLE KEYS */;
INSERT INTO `plot_types` VALUES (1,'PLOT','Plot','भूखंड','Open residential / commercial plot',1,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(2,'SHOP','Shop','दुकान','Built shop',2,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(3,'KIOSK','Kiosk','कियोस्क','Small kiosk / booth',3,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(4,'FARMHOUSE','Farmhouse','फार्महाउस','Farmhouse land',4,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(5,'FLAT','Flat','फ्लैट','Flat in a building',5,1,0,1,'2026-09-30 14:59:52',NULL,NULL),(6,'OTHER','Other','अन्य','Any other property',6,1,0,1,'2026-09-30 14:59:52',NULL,NULL);
/*!40000 ALTER TABLE `plot_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `properties` (
  `property_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `property_code` varchar(20) DEFAULT NULL,
  `scheme_id` int(10) unsigned NOT NULL,
  `sector_id` int(10) unsigned NOT NULL,
  `plot_no` varchar(30) NOT NULL,
  `property_type` varchar(30) NOT NULL,
  `area_value` decimal(12,2) NOT NULL,
  `area_unit` varchar(20) NOT NULL DEFAULT 'SQM',
  `area_sqm` decimal(14,4) NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `reserve_price` decimal(14,2) DEFAULT NULL,
  `current_status` varchar(30) NOT NULL DEFAULT 'LISTED',
  `current_round_id` int(10) unsigned DEFAULT NULL,
  `ready_round_id` int(10) unsigned DEFAULT NULL,
  `valid_till` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`property_id`),
  UNIQUE KEY `uq_properties_plot` (`sector_id`,`plot_no`),
  UNIQUE KEY `uq_properties_code` (`property_code`),
  KEY `ix_properties_scheme` (`scheme_id`,`is_active`),
  KEY `ix_properties_status` (`current_status`,`is_active`,`valid_till`),
  KEY `ix_properties_created` (`created_by`,`is_active`),
  KEY `fk_properties_type` (`property_type`),
  KEY `fk_properties_round` (`current_round_id`),
  KEY `fk_properties_ready` (`ready_round_id`),
  KEY `fk_properties_unit` (`area_unit`),
  CONSTRAINT `fk_properties_ready` FOREIGN KEY (`ready_round_id`) REFERENCES `rounds` (`round_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_properties_round` FOREIGN KEY (`current_round_id`) REFERENCES `rounds` (`round_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_properties_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`scheme_id`),
  CONSTRAINT `fk_properties_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`sector_id`),
  CONSTRAINT `fk_properties_status` FOREIGN KEY (`current_status`) REFERENCES `statuses` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_properties_type` FOREIGN KEY (`property_type`) REFERENCES `plot_types` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_properties_unit` FOREIGN KEY (`area_unit`) REFERENCES `units` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `ck_properties_area` CHECK (`area_value` > 0),
  CONSTRAINT `ck_properties_price` CHECK (`reserve_price` is null or `reserve_price` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties`
--

LOCK TABLES `properties` WRITE;
/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
INSERT INTO `properties` VALUES (1,'PRM-000001',1,1,'101','PLOT',150.00,'SQM',150.0000,NULL,NULL,'LISTED',NULL,NULL,NULL,1,2,'2026-10-20 14:59:52',NULL,'2026-10-20 14:59:52'),(2,'PRM-000002',1,1,'102','PLOT',180.00,'SQM',180.0000,'Area corrected as per site map',NULL,'LISTED',NULL,NULL,NULL,1,2,'2026-10-20 17:59:52',NULL,'2026-10-25 14:59:52'),(3,'PRM-000003',1,2,'205-A','PLOT',2400.00,'SQFT',222.9672,NULL,NULL,'UNDER_VERIFICATION',1,NULL,NULL,1,2,'2026-10-20 20:59:52',3,'2026-10-22 14:59:52'),(4,'PRM-000004',1,3,'310','FLAT',95.50,'SQM',95.5000,NULL,NULL,'UNDER_VERIFICATION',2,NULL,NULL,1,2,'2026-10-20 23:59:52',3,'2026-10-22 14:59:52'),(5,'PRM-000005',2,5,'SH-12','SHOP',320.00,'SQFT',29.7290,NULL,NULL,'EXPIRED',3,3,'2026-10-28',1,2,'2026-10-21 02:59:52',18,'2026-10-30 15:05:56'),(6,'PRM-000006',2,6,'K-04','KIOSK',12.00,'SQM',12.0000,NULL,NULL,'AUCTION_READY',4,4,'2026-11-05',1,2,'2026-10-21 05:59:52',18,'2026-10-30 14:59:52'),(7,'PRM-000007',3,7,'IND-21','PLOT',1000.00,'SQM',1000.0000,NULL,NULL,'NOT_READY',5,NULL,NULL,1,2,'2026-10-21 08:59:52',14,'2026-10-28 11:59:52'),(8,'PRM-000008',3,8,'IND-45','PLOT',1500.00,'SQM',1500.0000,NULL,NULL,'UNDER_VERIFICATION',7,NULL,NULL,1,2,'2026-10-21 11:59:52',3,'2026-10-29 14:59:52'),(9,'PRM-000009',1,4,'412','PLOT',200.00,'SQM',200.0000,NULL,NULL,'LISTED',NULL,NULL,NULL,1,2,'2026-10-21 14:59:52',NULL,'2026-10-21 14:59:52'),(10,'PRM-000010',2,5,'SH-15','SHOP',280.00,'SQFT',26.0128,NULL,NULL,'LISTED',NULL,NULL,NULL,1,2,'2026-10-21 17:59:52',NULL,'2026-10-21 17:59:52'),(15,'PRM-000015',3,7,'14','PLOT',280.00,'SQM',280.0000,NULL,12452.00,'UNDER_VERIFICATION',8,NULL,NULL,1,2,'2026-10-30 16:38:09',3,'2026-10-30 16:38:51');
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_users`
--

DROP TABLE IF EXISTS `property_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_users` (
  `pru_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `property_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pru_id`),
  UNIQUE KEY `uq_property_users` (`property_id`,`role_id`,`user_id`),
  KEY `ix_property_users_active` (`property_id`,`is_active`),
  KEY `ix_property_users_user` (`user_id`),
  KEY `fk_property_users_role` (`role_id`),
  CONSTRAINT `fk_property_users_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`),
  CONSTRAINT `fk_property_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `fk_property_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_users`
--

LOCK TABLES `property_users` WRITE;
/*!40000 ALTER TABLE `property_users` DISABLE KEYS */;
INSERT INTO `property_users` VALUES (1,1,1,4,1,2,'2026-10-20 14:59:52'),(2,1,2,6,1,2,'2026-10-20 14:59:52'),(3,1,3,8,1,2,'2026-10-20 14:59:52'),(4,1,4,10,1,2,'2026-10-20 14:59:52'),(5,1,5,12,1,2,'2026-10-20 14:59:52'),(6,1,6,14,1,2,'2026-10-20 14:59:52'),(7,1,6,15,1,2,'2026-10-20 14:59:52'),(8,1,6,20,1,2,'2026-10-20 14:59:52'),(9,1,6,21,1,2,'2026-10-20 14:59:52'),(10,1,7,16,1,2,'2026-10-20 14:59:52'),(11,1,8,18,1,2,'2026-10-20 14:59:52'),(12,2,1,4,1,2,'2026-10-20 17:59:52'),(13,2,2,6,1,2,'2026-10-20 17:59:52'),(14,2,3,8,1,2,'2026-10-20 17:59:52'),(15,2,4,10,1,2,'2026-10-20 17:59:52'),(16,2,5,12,1,2,'2026-10-20 17:59:52'),(17,2,6,14,1,2,'2026-10-20 17:59:52'),(18,2,6,15,1,2,'2026-10-20 17:59:52'),(19,2,6,20,1,2,'2026-10-20 17:59:52'),(20,2,6,21,1,2,'2026-10-20 17:59:52'),(21,2,7,16,1,2,'2026-10-20 17:59:52'),(22,2,8,18,1,2,'2026-10-20 17:59:52'),(23,3,1,4,1,2,'2026-10-20 20:59:52'),(24,3,2,6,1,2,'2026-10-20 20:59:52'),(25,3,3,8,1,2,'2026-10-20 20:59:52'),(26,3,4,10,1,2,'2026-10-20 20:59:52'),(27,3,5,12,1,2,'2026-10-20 20:59:52'),(28,3,6,14,1,2,'2026-10-20 20:59:52'),(29,3,6,15,1,2,'2026-10-20 20:59:52'),(30,3,6,20,1,2,'2026-10-20 20:59:52'),(31,3,6,21,1,2,'2026-10-20 20:59:52'),(32,3,7,16,1,2,'2026-10-20 20:59:52'),(33,3,8,18,1,2,'2026-10-20 20:59:52'),(34,4,1,4,1,2,'2026-10-20 23:59:52'),(35,4,2,6,1,2,'2026-10-20 23:59:52'),(36,4,3,8,1,2,'2026-10-20 23:59:52'),(37,4,4,10,1,2,'2026-10-20 23:59:52'),(38,4,5,12,1,2,'2026-10-20 23:59:52'),(39,4,6,14,1,2,'2026-10-20 23:59:52'),(40,4,6,15,1,2,'2026-10-20 23:59:52'),(41,4,6,20,1,2,'2026-10-20 23:59:52'),(42,4,6,21,1,2,'2026-10-20 23:59:52'),(43,4,7,16,1,2,'2026-10-20 23:59:52'),(44,4,8,18,1,2,'2026-10-20 23:59:52'),(45,5,1,4,1,2,'2026-10-21 02:59:52'),(46,5,2,6,1,2,'2026-10-21 02:59:52'),(47,5,3,8,1,2,'2026-10-21 02:59:52'),(48,5,4,10,1,2,'2026-10-21 02:59:52'),(49,5,5,12,1,2,'2026-10-21 02:59:52'),(50,5,6,14,1,2,'2026-10-21 02:59:52'),(51,5,6,15,1,2,'2026-10-21 02:59:52'),(52,5,6,20,1,2,'2026-10-21 02:59:52'),(53,5,6,21,1,2,'2026-10-21 02:59:52'),(54,5,7,16,1,2,'2026-10-21 02:59:52'),(55,5,8,18,1,2,'2026-10-21 02:59:52'),(56,6,1,4,1,2,'2026-10-21 05:59:52'),(57,6,2,6,1,2,'2026-10-21 05:59:52'),(58,6,3,8,1,2,'2026-10-21 05:59:52'),(59,6,4,10,1,2,'2026-10-21 05:59:52'),(60,6,5,12,1,2,'2026-10-21 05:59:52'),(61,6,6,14,1,2,'2026-10-21 05:59:52'),(62,6,6,15,1,2,'2026-10-21 05:59:52'),(63,6,6,20,1,2,'2026-10-21 05:59:52'),(64,6,6,21,1,2,'2026-10-21 05:59:52'),(65,6,7,16,1,2,'2026-10-21 05:59:52'),(66,6,8,18,1,2,'2026-10-21 05:59:52'),(67,7,1,4,1,2,'2026-10-21 08:59:52'),(68,7,2,6,1,2,'2026-10-21 08:59:52'),(69,7,3,8,1,2,'2026-10-21 08:59:52'),(70,7,4,10,1,2,'2026-10-21 08:59:52'),(71,7,5,12,1,2,'2026-10-21 08:59:52'),(72,7,6,14,1,2,'2026-10-21 08:59:52'),(73,7,6,15,1,2,'2026-10-21 08:59:52'),(74,7,6,20,1,2,'2026-10-21 08:59:52'),(75,7,6,21,1,2,'2026-10-21 08:59:52'),(76,7,7,16,1,2,'2026-10-21 08:59:52'),(77,7,8,18,1,2,'2026-10-21 08:59:52'),(78,8,1,4,1,2,'2026-10-21 11:59:52'),(79,8,2,6,1,2,'2026-10-21 11:59:52'),(80,8,3,8,1,2,'2026-10-21 11:59:52'),(81,8,4,10,1,2,'2026-10-21 11:59:52'),(82,8,5,12,1,2,'2026-10-21 11:59:52'),(83,8,6,14,1,2,'2026-10-21 11:59:52'),(84,8,6,15,1,2,'2026-10-21 11:59:52'),(85,8,6,20,1,2,'2026-10-21 11:59:52'),(86,8,6,21,1,2,'2026-10-21 11:59:52'),(87,8,7,16,1,2,'2026-10-21 11:59:52'),(88,8,8,18,1,2,'2026-10-21 11:59:52'),(89,9,1,4,1,2,'2026-10-21 14:59:52'),(90,9,2,6,1,2,'2026-10-21 14:59:52'),(91,9,3,8,1,2,'2026-10-21 14:59:52'),(92,9,4,10,1,2,'2026-10-21 14:59:52'),(93,9,5,12,1,2,'2026-10-21 14:59:52'),(94,9,6,14,1,2,'2026-10-21 14:59:52'),(95,9,6,15,1,2,'2026-10-21 14:59:52'),(96,9,6,20,1,2,'2026-10-21 14:59:52'),(97,9,6,21,1,2,'2026-10-21 14:59:52'),(98,9,7,16,1,2,'2026-10-21 14:59:52'),(99,9,8,18,1,2,'2026-10-21 14:59:52'),(100,10,1,4,1,2,'2026-10-21 17:59:52'),(101,10,2,6,1,2,'2026-10-21 17:59:52'),(102,10,3,8,1,2,'2026-10-21 17:59:52'),(103,10,4,10,1,2,'2026-10-21 17:59:52'),(104,10,5,12,1,2,'2026-10-21 17:59:52'),(105,10,6,14,1,2,'2026-10-21 17:59:52'),(106,10,6,15,1,2,'2026-10-21 17:59:52'),(107,10,6,20,1,2,'2026-10-21 17:59:52'),(108,10,6,21,1,2,'2026-10-21 17:59:52'),(109,10,7,16,1,2,'2026-10-21 17:59:52'),(110,10,8,18,1,2,'2026-10-21 17:59:52'),(155,15,1,4,1,2,'2026-10-30 16:38:09'),(156,15,2,6,1,2,'2026-10-30 16:38:09'),(157,15,3,8,1,2,'2026-10-30 16:38:09'),(158,15,4,10,1,2,'2026-10-30 16:38:09'),(159,15,5,12,1,2,'2026-10-30 16:38:09'),(160,15,6,14,1,2,'2026-10-30 16:38:09'),(161,15,6,15,1,2,'2026-10-30 16:38:09'),(162,15,6,20,1,2,'2026-10-30 16:38:09'),(163,15,6,21,1,2,'2026-10-30 16:38:09'),(164,15,7,16,1,2,'2026-10-30 16:38:09'),(165,15,8,18,1,2,'2026-10-30 16:38:09');
/*!40000 ALTER TABLE `property_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_code` varchar(40) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `role_name_hi` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sla_days` smallint(5) unsigned NOT NULL DEFAULT 5,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_code` (`role_code`),
  UNIQUE KEY `uq_roles_name` (`role_name`),
  KEY `ix_roles_active` (`is_active`,`sort_order`),
  CONSTRAINT `ck_roles_sla` CHECK (`sla_days` between 1 and 365)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'AUCTION_CELL','Auction Cell','नीलामी शाखा','Allotment by auction — allottee / cancellation record',5,1,1,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(2,'LOTTERY_CELL','Lottery Cell','लॉटरी शाखा','Allotment by lottery — allottee / cancellation record',5,1,2,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(3,'SALE_CELL','Sale Cell','विक्रय शाखा','Sale register — sold / reserved / vacant on record',5,1,3,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(4,'LAND_FOR_LAND_CELL','Land-for-Land Cell','भूमि के बदले भूमि शाखा','Land given against acquired land',5,1,4,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(5,'PUNARVAS_CELL','Punarvas Cell','पुनर्वास शाखा','Rehabilitation allotments',5,1,5,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(6,'ENGINEERING_CELL','Engineering Cell','अभियांत्रिकी शाखा','Site inspection — vacancy, encroachment, measurements',10,1,6,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(7,'TOWN_PLANNING_CELL','Town Planning Cell','नगर नियोजन शाखा','Master plan / layout / road widening check',7,1,7,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52'),(8,'LEGAL_CELL','Legal Cell','विधि शाखा','Litigation / stay order check',7,1,8,1,1,'2026-09-30 14:59:49',NULL,'2026-09-30 14:59:52');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rounds`
--

DROP TABLE IF EXISTS `rounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rounds` (
  `round_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `property_id` int(10) unsigned NOT NULL,
  `round_no` smallint(5) unsigned NOT NULL,
  `round_status` enum('OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  `result` enum('READY','NOT_READY') DEFAULT NULL,
  `result_note` text DEFAULT NULL,
  `initiated_by` int(10) unsigned NOT NULL,
  `initiated_at` datetime NOT NULL,
  `due_date` date NOT NULL,
  `resend_reason` varchar(255) DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_till` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`round_id`),
  UNIQUE KEY `uq_rounds_no` (`property_id`,`round_no`),
  KEY `ix_rounds_open` (`property_id`,`round_status`),
  KEY `ix_rounds_status` (`round_status`),
  KEY `fk_rounds_initiator` (`initiated_by`),
  CONSTRAINT `fk_rounds_initiator` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_rounds_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rounds`
--

LOCK TABLES `rounds` WRITE;
/*!40000 ALTER TABLE `rounds` DISABLE KEYS */;
INSERT INTO `rounds` VALUES (1,3,1,'OPEN',NULL,NULL,3,'2026-10-22 14:59:52','2026-11-01',NULL,NULL,NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(2,4,1,'OPEN',NULL,NULL,3,'2026-10-22 14:59:52','2026-11-01',NULL,NULL,NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(3,5,1,'CLOSED','READY','All roles approved',3,'2026-10-22 14:59:52','2026-11-01',NULL,18,'2026-10-23 12:00:52','2026-10-23','2026-10-28','2026-10-22 14:59:52','2026-10-30 14:59:52'),(4,6,1,'CLOSED','READY','All roles approved',3,'2026-10-22 14:59:52','2026-11-01',NULL,18,'2026-10-24 00:56:52','2026-10-24','2026-11-05','2026-10-22 14:59:52','2026-10-30 14:59:52'),(5,7,1,'CLOSED','NOT_READY','• Engineering Cell: Encroachment - temporary tin shed approx. 8 sq m on the front side.',3,'2026-10-22 14:59:52','2026-11-01',NULL,14,'2026-10-28 11:59:52',NULL,NULL,'2026-10-22 14:59:52','2026-10-28 11:59:52'),(6,8,1,'CLOSED','NOT_READY','• Legal Cell: Stay order in civil suit no. 45/2023 - pending.',3,'2026-10-22 14:59:52','2026-11-01',NULL,18,'2026-10-27 14:59:52',NULL,NULL,'2026-10-22 14:59:52','2026-10-27 14:59:52'),(7,8,2,'OPEN',NULL,NULL,3,'2026-10-29 14:59:52','2026-11-08','Stay vacated by court order dated last month - record corrected.',NULL,NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(8,15,1,'OPEN',NULL,NULL,3,'2026-10-30 16:38:51','2026-11-09',NULL,NULL,NULL,NULL,NULL,'2026-10-30 16:38:51',NULL);
/*!40000 ALTER TABLE `rounds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scheme_users`
--

DROP TABLE IF EXISTS `scheme_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheme_users` (
  `map_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scheme_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`map_id`),
  KEY `ix_scheme_users` (`scheme_id`,`role_id`,`is_active`),
  KEY `ix_scheme_users_user` (`user_id`,`is_active`),
  KEY `fk_scheme_users_role` (`role_id`),
  CONSTRAINT `fk_scheme_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `fk_scheme_users_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`scheme_id`),
  CONSTRAINT `fk_scheme_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheme_users`
--

LOCK TABLES `scheme_users` WRITE;
/*!40000 ALTER TABLE `scheme_users` DISABLE KEYS */;
INSERT INTO `scheme_users` VALUES (1,1,1,4,1,2,'2026-10-10 14:59:52',NULL),(2,1,2,6,1,2,'2026-10-10 14:59:52',NULL),(3,1,3,8,1,2,'2026-10-10 14:59:52',NULL),(4,1,4,10,1,2,'2026-10-10 14:59:52',NULL),(5,1,5,12,1,2,'2026-10-10 14:59:52',NULL),(6,1,6,14,1,2,'2026-10-10 14:59:52',NULL),(7,1,6,15,1,2,'2026-10-10 14:59:52',NULL),(8,1,7,16,1,2,'2026-10-10 14:59:52',NULL),(9,1,8,18,1,2,'2026-10-10 14:59:52',NULL),(10,1,6,20,1,2,'2026-10-10 14:59:52',NULL),(11,1,6,21,1,2,'2026-10-10 14:59:52',NULL),(12,2,1,4,1,2,'2026-10-12 14:59:52',NULL),(13,2,2,6,1,2,'2026-10-12 14:59:52',NULL),(14,2,3,8,1,2,'2026-10-12 14:59:52',NULL),(15,2,4,10,1,2,'2026-10-12 14:59:52',NULL),(16,2,5,12,1,2,'2026-10-12 14:59:52',NULL),(17,2,6,14,1,2,'2026-10-12 14:59:52',NULL),(18,2,6,15,1,2,'2026-10-12 14:59:52',NULL),(19,2,7,16,1,2,'2026-10-12 14:59:52',NULL),(20,2,8,18,1,2,'2026-10-12 14:59:52',NULL),(21,2,6,20,1,2,'2026-10-12 14:59:52',NULL),(22,2,6,21,1,2,'2026-10-12 14:59:52',NULL),(23,3,1,4,1,2,'2026-10-14 14:59:52',NULL),(24,3,2,6,1,2,'2026-10-14 14:59:52',NULL),(25,3,3,8,1,2,'2026-10-14 14:59:52',NULL),(26,3,4,10,1,2,'2026-10-14 14:59:52',NULL),(27,3,5,12,1,2,'2026-10-14 14:59:52',NULL),(28,3,6,14,1,2,'2026-10-14 14:59:52',NULL),(29,3,6,15,1,2,'2026-10-14 14:59:52',NULL),(30,3,7,16,1,2,'2026-10-14 14:59:52',NULL),(31,3,8,18,1,2,'2026-10-14 14:59:52',NULL),(32,3,6,20,1,2,'2026-10-14 14:59:52',NULL),(33,3,6,21,1,2,'2026-10-14 14:59:52',NULL);
/*!40000 ALTER TABLE `scheme_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schemes`
--

DROP TABLE IF EXISTS `schemes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schemes` (
  `scheme_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scheme_code` varchar(30) NOT NULL,
  `scheme_name` varchar(150) NOT NULL,
  `scheme_type` enum('RESIDENTIAL','COMMERCIAL','INDUSTRIAL','MIXED','FARMHOUSE') NOT NULL,
  `area_id` int(10) unsigned NOT NULL,
  `year_of_scheme` smallint(5) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`scheme_id`),
  UNIQUE KEY `uq_schemes_code` (`scheme_code`),
  UNIQUE KEY `uq_schemes_name` (`scheme_name`),
  KEY `ix_schemes_active` (`is_active`,`scheme_name`),
  KEY `ix_schemes_area` (`area_id`),
  CONSTRAINT `fk_schemes_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`),
  CONSTRAINT `ck_schemes_year` CHECK (`year_of_scheme` between 1950 and 2100)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schemes`
--

LOCK TABLES `schemes` WRITE;
/*!40000 ALTER TABLE `schemes` DISABLE KEYS */;
INSERT INTO `schemes` VALUES (1,'RNY-2019','Ravindra Nagar Awasiya Yojana','RESIDENTIAL',1,2019,1,2,'2026-10-10 14:59:52',NULL,NULL),(2,'VNC-2021','Vigyan Nagar Commercial Complex','COMMERCIAL',2,2021,1,2,'2026-10-12 14:59:52',NULL,NULL),(3,'KIA-2016','Kunhadi Industrial Area','INDUSTRIAL',3,2016,1,2,'2026-10-14 14:59:52',NULL,NULL);
/*!40000 ALTER TABLE `schemes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `section_checks`
--

DROP TABLE IF EXISTS `section_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `section_checks` (
  `check_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `is_sale_allotted` tinyint(1) DEFAULT NULL,
  `allottee_name` varchar(100) DEFAULT NULL,
  `allotment_date` date DEFAULT NULL,
  `is_auctioned` tinyint(1) DEFAULT NULL,
  `auction_date` date DEFAULT NULL,
  `is_inst_allotted` tinyint(1) DEFAULT NULL,
  `is_inst_in_process` tinyint(1) DEFAULT NULL,
  `inst_name` varchar(150) DEFAULT NULL,
  `inst_allotment_date` date DEFAULT NULL,
  `checked_by` int(10) unsigned NOT NULL,
  `checked_at` datetime NOT NULL,
  PRIMARY KEY (`check_id`),
  UNIQUE KEY `uq_section_checks_task` (`task_id`),
  KEY `fk_section_checks_user` (`checked_by`),
  CONSTRAINT `fk_section_checks_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_section_checks_user` FOREIGN KEY (`checked_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `section_checks`
--

LOCK TABLES `section_checks` WRITE;
/*!40000 ALTER TABLE `section_checks` DISABLE KEYS */;
/*!40000 ALTER TABLE `section_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sectors`
--

DROP TABLE IF EXISTS `sectors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sectors` (
  `sector_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scheme_id` int(10) unsigned NOT NULL,
  `sector_name` varchar(80) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`sector_id`),
  UNIQUE KEY `uq_sectors_name` (`scheme_id`,`sector_name`),
  KEY `ix_sectors_active` (`scheme_id`,`is_active`,`sort_order`),
  CONSTRAINT `fk_sectors_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`scheme_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sectors`
--

LOCK TABLES `sectors` WRITE;
/*!40000 ALTER TABLE `sectors` DISABLE KEYS */;
INSERT INTO `sectors` VALUES (1,1,'Sector-1',1,1,2,'2026-10-10 14:59:52',NULL,NULL),(2,1,'Sector-2',2,1,2,'2026-10-10 14:59:52',NULL,NULL),(3,1,'Sector-3',3,1,2,'2026-10-10 14:59:52',NULL,NULL),(4,1,'Sector-4',4,1,2,'2026-10-10 14:59:52',NULL,NULL),(5,2,'Block-A',1,1,2,'2026-10-12 14:59:52',NULL,NULL),(6,2,'Block-B',2,1,2,'2026-10-12 14:59:52',NULL,NULL),(7,3,'Phase-I',1,1,2,'2026-10-14 14:59:52',NULL,NULL),(8,3,'Phase-II',2,1,2,'2026-10-14 14:59:52',NULL,NULL),(9,3,'Phase-III',3,1,2,'2026-10-14 14:59:52',NULL,NULL);
/*!40000 ALTER TABLE `sectors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('app_full_name','Property Records & Auction-readiness Multi-section Assessment Network',NULL,'2026-10-30 14:59:49'),('app_name','PRAMAAN',NULL,'2026-10-30 14:59:49'),('app_tagline','Property Verification System',NULL,'2026-10-30 14:59:49'),('copyright_note','Govt. of Rajasthan. All Rights Reserved.',NULL,'2026-10-30 14:59:49'),('header_note','Secure portal · authorised KDA personnel',NULL,'2026-10-30 14:59:49'),('hero_eyebrow','Property verification before every auction',NULL,'2026-10-30 14:59:49'),('hero_line1','Every vacant property.',NULL,'2026-10-30 14:59:49'),('hero_line2','Verified &',NULL,'2026-10-30 14:59:49'),('hero_line3','auction-ready.',NULL,'2026-10-30 14:59:49'),('idle_timeout_minutes','30',NULL,'2026-10-30 14:59:49'),('ip_max_attempts','20',NULL,'2026-10-30 14:59:49'),('lockout_minutes','15',NULL,'2026-10-30 14:59:49'),('login_max_attempts','5',NULL,'2026-10-30 14:59:49'),('org_name','Kota Development Authority',NULL,'2026-10-30 14:59:49'),('org_short','KDA',NULL,'2026-10-30 14:59:49'),('org_website','https://kdakota.rajasthan.gov.in/',NULL,'2026-10-30 14:59:49'),('password_min_length','10',NULL,'2026-10-30 14:59:49'),('poll_seconds','60',NULL,'2026-10-30 14:59:49'),('reverify_alert_days','15',NULL,'2026-10-30 14:59:49'),('validity_days','180',NULL,'2026-10-30 14:59:49');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_inspections`
--

DROP TABLE IF EXISTS `site_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_inspections` (
  `inspection_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `length_value` decimal(10,2) DEFAULT NULL,
  `width_value` decimal(10,2) DEFAULT NULL,
  `area_value` decimal(12,2) NOT NULL,
  `area_unit` varchar(20) NOT NULL,
  `area_sqm` decimal(14,4) NOT NULL,
  `recorded_value` decimal(12,2) NOT NULL,
  `recorded_unit` varchar(20) NOT NULL,
  `difference` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference_pct` decimal(7,2) NOT NULL DEFAULT 0.00,
  `variance` enum('SAME','LESS','MORE') NOT NULL,
  `encroachment` enum('YES','NO') NOT NULL,
  `encroachment_type` enum('PARTIAL','COMPLETE') DEFAULT NULL,
  `encroachment_details` varchar(300) DEFAULT NULL,
  `road_width` smallint(5) unsigned DEFAULT NULL,
  `road_width_unit` enum('FT','M') DEFAULT NULL,
  `is_corner` tinyint(1) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `measured_by` int(10) unsigned NOT NULL,
  `measured_at` datetime NOT NULL,
  PRIMARY KEY (`inspection_id`),
  UNIQUE KEY `uq_site_inspections_task` (`task_id`),
  KEY `fk_site_inspections_user` (`measured_by`),
  KEY `fk_site_inspections_unit` (`area_unit`),
  KEY `fk_site_inspections_recorded_unit` (`recorded_unit`),
  CONSTRAINT `fk_site_inspections_recorded_unit` FOREIGN KEY (`recorded_unit`) REFERENCES `units` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_site_inspections_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_site_inspections_unit` FOREIGN KEY (`area_unit`) REFERENCES `units` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_site_inspections_user` FOREIGN KEY (`measured_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_inspections`
--

LOCK TABLES `site_inspections` WRITE;
/*!40000 ALTER TABLE `site_inspections` DISABLE KEYS */;
INSERT INTO `site_inspections` VALUES (1,38,NULL,NULL,992.00,'SQM',992.0000,1000.00,'SQM',8.00,0.80,'LESS','YES',NULL,'Temporary tin shed of about 8 sq m on the front side.',NULL,NULL,NULL,NULL,NULL,14,'2026-10-28 11:59:52');
/*!40000 ALTER TABLE `site_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slides`
--

DROP TABLE IF EXISTS `slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slides` (
  `slide_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(80) NOT NULL,
  `title` varchar(120) NOT NULL,
  `credit` varchar(200) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`slide_id`),
  UNIQUE KEY `uq_slides_image` (`image`),
  KEY `ix_slides_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slides`
--

LOCK TABLES `slides` WRITE;
/*!40000 ALTER TABLE `slides` DISABLE KEYS */;
INSERT INTO `slides` VALUES (1,'kishore_sagar.jpg','Kishore Sagar, Kota','Photo: Kushagra140, CC BY-SA 4.0, via Wikimedia Commons',1,1,NULL,'2026-10-30 14:59:49',NULL,NULL),(2,'garh_palace.jpg','Garh Palace, Kota','Photo: Shrikant Bansod, CC BY-SA 4.0, via Wikimedia Commons',2,1,NULL,'2026-10-30 14:59:49',NULL,NULL),(3,'river_front.jpg','Chambal River Front, Kota','Photo: Imakanksha, CC BY-SA 4.0, via Wikimedia Commons',3,1,NULL,'2026-10-30 14:59:49',NULL,NULL),(4,'seven_wonders.jpg','Seven Wonders Park, Kota','Photo: Arvind Sunda07, CC BY-SA 4.0, via Wikimedia Commons',4,1,NULL,'2026-10-30 14:59:49',NULL,NULL);
/*!40000 ALTER TABLE `slides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `statuses`
--

DROP TABLE IF EXISTS `statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `statuses` (
  `status_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(40) NOT NULL,
  `name_hi` varchar(40) DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'info',
  `icon` varchar(40) NOT NULL DEFAULT 'fa-flag',
  `description` varchar(150) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `uq_statuses_code` (`code`),
  UNIQUE KEY `uq_statuses_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `statuses`
--

LOCK TABLES `statuses` WRITE;
/*!40000 ALTER TABLE `statuses` DISABLE KEYS */;
INSERT INTO `statuses` VALUES (1,'LISTED','Listed',NULL,'info','fa-list','Registered, not yet sent for verification',1,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(2,'UNDER_VERIFICATION','Under Verification',NULL,'warning','fa-hourglass-half','Round open — sections are verifying',2,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(3,'AUCTION_READY','Auction Ready',NULL,'success','fa-circle-check','All sections approved',3,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(4,'NOT_READY','Not Ready',NULL,'danger','fa-circle-xmark','A section rejected — needs correction and re-send',4,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(5,'EXPIRED','Validity Expired',NULL,'danger','fa-calendar-xmark','Auction Ready validity has passed — needs periodic re-verification',5,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(6,'SENT_FOR_AUCTION','Sent for Auction',NULL,'primary','fa-gavel','Handed over to the auction cell',6,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(7,'SOLD','Sold',NULL,'dark','fa-handshake','Sold in auction',7,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(8,'WITHHELD','Withheld',NULL,'muted','fa-pause','Kept on hold by order',8,1,1,1,'2026-09-30 14:59:52',NULL,NULL),(9,'ARCHIVED','Archived',NULL,'muted','fa-box-archive','No longer active',9,1,1,1,'2026-09-30 14:59:52',NULL,NULL);
/*!40000 ALTER TABLE `statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_assignees`
--

DROP TABLE IF EXISTS `task_assignees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assignees` (
  `assignee_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assignee_id`),
  UNIQUE KEY `uq_task_assignees` (`task_id`,`user_id`),
  KEY `ix_task_assignees_user` (`user_id`,`task_id`),
  CONSTRAINT `fk_task_assignees_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_assignees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_assignees`
--

LOCK TABLES `task_assignees` WRITE;
/*!40000 ALTER TABLE `task_assignees` DISABLE KEYS */;
INSERT INTO `task_assignees` VALUES (1,1,4,'2026-10-22 14:59:52'),(2,2,6,'2026-10-22 14:59:52'),(3,3,8,'2026-10-22 14:59:52'),(4,4,10,'2026-10-22 14:59:52'),(5,5,12,'2026-10-22 14:59:52'),(6,6,14,'2026-10-22 14:59:52'),(7,6,15,'2026-10-22 14:59:52'),(8,6,20,'2026-10-22 14:59:52'),(9,6,21,'2026-10-22 14:59:52'),(10,7,16,'2026-10-22 14:59:52'),(11,8,18,'2026-10-22 14:59:52'),(12,9,4,'2026-10-22 14:59:52'),(13,10,6,'2026-10-22 14:59:52'),(14,11,8,'2026-10-22 14:59:52'),(15,12,10,'2026-10-22 14:59:52'),(16,13,12,'2026-10-22 14:59:52'),(17,14,14,'2026-10-22 14:59:52'),(18,14,15,'2026-10-22 14:59:52'),(19,14,20,'2026-10-22 14:59:52'),(20,14,21,'2026-10-22 14:59:52'),(21,15,16,'2026-10-22 14:59:52'),(22,16,18,'2026-10-22 14:59:52'),(23,17,4,'2026-10-22 14:59:52'),(24,18,6,'2026-10-22 14:59:52'),(25,19,8,'2026-10-22 14:59:52'),(26,20,10,'2026-10-22 14:59:52'),(27,21,12,'2026-10-22 14:59:52'),(28,22,14,'2026-10-22 14:59:52'),(29,22,15,'2026-10-22 14:59:52'),(30,22,20,'2026-10-22 14:59:52'),(31,22,21,'2026-10-22 14:59:52'),(32,23,16,'2026-10-22 14:59:52'),(33,24,18,'2026-10-22 14:59:52'),(34,25,4,'2026-10-22 14:59:52'),(35,26,6,'2026-10-22 14:59:52'),(36,27,8,'2026-10-22 14:59:52'),(37,28,10,'2026-10-22 14:59:52'),(38,29,12,'2026-10-22 14:59:52'),(39,30,14,'2026-10-22 14:59:52'),(40,30,15,'2026-10-22 14:59:52'),(41,30,20,'2026-10-22 14:59:52'),(42,30,21,'2026-10-22 14:59:52'),(43,31,16,'2026-10-22 14:59:52'),(44,32,18,'2026-10-22 14:59:52'),(45,33,4,'2026-10-22 14:59:52'),(46,34,6,'2026-10-22 14:59:52'),(47,35,8,'2026-10-22 14:59:52'),(48,36,10,'2026-10-22 14:59:52'),(49,37,12,'2026-10-22 14:59:52'),(50,38,14,'2026-10-22 14:59:52'),(51,38,15,'2026-10-22 14:59:52'),(52,38,20,'2026-10-22 14:59:52'),(53,38,21,'2026-10-22 14:59:52'),(54,39,16,'2026-10-22 14:59:52'),(55,40,18,'2026-10-22 14:59:52'),(56,41,4,'2026-10-22 14:59:52'),(57,42,6,'2026-10-22 14:59:52'),(58,43,8,'2026-10-22 14:59:52'),(59,44,10,'2026-10-22 14:59:52'),(60,45,12,'2026-10-22 14:59:52'),(61,46,14,'2026-10-22 14:59:52'),(62,46,15,'2026-10-22 14:59:52'),(63,46,20,'2026-10-22 14:59:52'),(64,46,21,'2026-10-22 14:59:52'),(65,47,16,'2026-10-22 14:59:52'),(66,48,18,'2026-10-22 14:59:52'),(67,49,4,'2026-10-29 14:59:52'),(68,50,6,'2026-10-29 14:59:52'),(69,51,8,'2026-10-29 14:59:52'),(70,52,10,'2026-10-29 14:59:52'),(71,53,12,'2026-10-29 14:59:52'),(72,54,14,'2026-10-29 14:59:52'),(73,54,15,'2026-10-29 14:59:52'),(74,54,20,'2026-10-29 14:59:52'),(75,54,21,'2026-10-29 14:59:52'),(76,55,16,'2026-10-29 14:59:52'),(77,56,18,'2026-10-29 14:59:52'),(78,57,4,'2026-10-30 16:38:51'),(79,58,6,'2026-10-30 16:38:51'),(80,59,8,'2026-10-30 16:38:51'),(81,60,10,'2026-10-30 16:38:51'),(82,61,12,'2026-10-30 16:38:51'),(83,62,14,'2026-10-30 16:38:51'),(84,62,15,'2026-10-30 16:38:51'),(85,62,20,'2026-10-30 16:38:51'),(86,62,21,'2026-10-30 16:38:51'),(87,63,16,'2026-10-30 16:38:51'),(88,64,18,'2026-10-30 16:38:51');
/*!40000 ALTER TABLE `task_assignees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `task_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `round_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `task_status` enum('PENDING','APPROVED','REJECTED','NO_RESPONSE') NOT NULL DEFAULT 'PENDING',
  `sla_due_date` date NOT NULL,
  `remarks` varchar(1000) DEFAULT NULL,
  `responded_by` int(10) unsigned DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`task_id`),
  UNIQUE KEY `uq_tasks_role` (`round_id`,`role_id`),
  KEY `ix_tasks_status` (`task_status`,`sla_due_date`),
  KEY `fk_tasks_role` (`role_id`),
  KEY `fk_tasks_user` (`responded_by`),
  CONSTRAINT `fk_tasks_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `fk_tasks_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`round_id`),
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`responded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES (1,1,1,'APPROVED','2026-10-27','Record clear - no objection.',4,'2026-10-22 16:36:52','2026-10-22 14:59:52','2026-10-22 16:36:52'),(2,1,2,'APPROVED','2026-10-27','Record clear - no objection.',6,'2026-10-22 18:13:52','2026-10-22 14:59:52','2026-10-22 18:13:52'),(3,1,3,'APPROVED','2026-10-27','Record clear - no objection.',8,'2026-10-22 19:50:52','2026-10-22 14:59:52','2026-10-22 19:50:52'),(4,1,4,'APPROVED','2026-10-27','Record clear - no objection.',10,'2026-10-22 21:27:52','2026-10-22 14:59:52','2026-10-22 21:27:52'),(5,1,5,'APPROVED','2026-10-27','Record clear - no objection.',12,'2026-10-22 23:04:52','2026-10-22 14:59:52','2026-10-22 23:04:52'),(6,1,6,'PENDING','2026-11-01',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(7,1,7,'PENDING','2026-10-29',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(8,1,8,'PENDING','2026-10-29',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(9,2,1,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(10,2,2,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(11,2,3,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(12,2,4,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(13,2,5,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(14,2,6,'PENDING','2026-11-01',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(15,2,7,'PENDING','2026-10-29',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(16,2,8,'PENDING','2026-10-29',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(17,3,1,'APPROVED','2026-10-27','Record clear - no objection.',4,'2026-10-23 00:41:52','2026-10-22 14:59:52','2026-10-23 00:41:52'),(18,3,2,'APPROVED','2026-10-27','Record clear - no objection.',6,'2026-10-23 02:18:52','2026-10-22 14:59:52','2026-10-23 02:18:52'),(19,3,3,'APPROVED','2026-10-27','Record clear - no objection.',8,'2026-10-23 03:55:52','2026-10-22 14:59:52','2026-10-23 03:55:52'),(20,3,4,'APPROVED','2026-10-27','Record clear - no objection.',10,'2026-10-23 05:32:52','2026-10-22 14:59:52','2026-10-23 05:32:52'),(21,3,5,'APPROVED','2026-10-27','Record clear - no objection.',12,'2026-10-23 07:09:52','2026-10-22 14:59:52','2026-10-23 07:09:52'),(22,3,6,'APPROVED','2026-11-01','Record clear - no objection.',14,'2026-10-23 08:46:52','2026-10-22 14:59:52','2026-10-23 08:46:52'),(23,3,7,'APPROVED','2026-10-29','Record clear - no objection.',16,'2026-10-23 10:23:52','2026-10-22 14:59:52','2026-10-23 10:23:52'),(24,3,8,'APPROVED','2026-10-29','Record clear - no objection.',18,'2026-10-23 12:00:52','2026-10-22 14:59:52','2026-10-23 12:00:52'),(25,4,1,'APPROVED','2026-10-27','Record clear - no objection.',4,'2026-10-23 13:37:52','2026-10-22 14:59:52','2026-10-23 13:37:52'),(26,4,2,'APPROVED','2026-10-27','Record clear - no objection.',6,'2026-10-23 15:14:52','2026-10-22 14:59:52','2026-10-23 15:14:52'),(27,4,3,'APPROVED','2026-10-27','Record clear - no objection.',8,'2026-10-23 16:51:52','2026-10-22 14:59:52','2026-10-23 16:51:52'),(28,4,4,'APPROVED','2026-10-27','Record clear - no objection.',10,'2026-10-23 18:28:52','2026-10-22 14:59:52','2026-10-23 18:28:52'),(29,4,5,'APPROVED','2026-10-27','Record clear - no objection.',12,'2026-10-23 20:05:52','2026-10-22 14:59:52','2026-10-23 20:05:52'),(30,4,6,'APPROVED','2026-11-01','Record clear - no objection.',14,'2026-10-23 21:42:52','2026-10-22 14:59:52','2026-10-23 21:42:52'),(31,4,7,'APPROVED','2026-10-29','Record clear - no objection.',16,'2026-10-23 23:19:52','2026-10-22 14:59:52','2026-10-23 23:19:52'),(32,4,8,'APPROVED','2026-10-29','Record clear - no objection.',18,'2026-10-24 00:56:52','2026-10-22 14:59:52','2026-10-24 00:56:52'),(33,5,1,'APPROVED','2026-10-27','Record clear - no objection.',4,'2026-10-24 02:33:52','2026-10-22 14:59:52','2026-10-24 02:33:52'),(34,5,2,'APPROVED','2026-10-27','Record clear - no objection.',6,'2026-10-24 04:10:52','2026-10-22 14:59:52','2026-10-24 04:10:52'),(35,5,3,'APPROVED','2026-10-27','Record clear - no objection.',8,'2026-10-24 05:47:52','2026-10-22 14:59:52','2026-10-24 05:47:52'),(36,5,4,'APPROVED','2026-10-27','Record clear - no objection.',10,'2026-10-24 07:24:52','2026-10-22 14:59:52','2026-10-24 07:24:52'),(37,5,5,'APPROVED','2026-10-27','Record clear - no objection.',12,'2026-10-24 09:01:52','2026-10-22 14:59:52','2026-10-24 09:01:52'),(38,5,6,'REJECTED','2026-11-01','Encroachment - temporary tin shed approx. 8 sq m on the front side.',14,'2026-10-28 11:59:52','2026-10-22 14:59:52','2026-10-28 11:59:52'),(39,5,7,'APPROVED','2026-10-29','Record clear - no objection.',16,'2026-10-24 10:38:52','2026-10-22 14:59:52','2026-10-24 10:38:52'),(40,5,8,'PENDING','2026-10-29',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(41,6,1,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(42,6,2,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(43,6,3,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(44,6,4,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(45,6,5,'PENDING','2026-10-27',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(46,6,6,'PENDING','2026-11-01',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(47,6,7,'PENDING','2026-10-29',NULL,NULL,NULL,'2026-10-22 14:59:52',NULL),(48,6,8,'REJECTED','2026-10-29','Stay order in civil suit no. 45/2023 - pending.',18,'2026-10-27 14:59:52','2026-10-22 14:59:52','2026-10-27 14:59:52'),(49,7,1,'PENDING','2026-11-03',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(50,7,2,'PENDING','2026-11-03',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(51,7,3,'PENDING','2026-11-03',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(52,7,4,'PENDING','2026-11-03',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(53,7,5,'PENDING','2026-11-03',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(54,7,6,'PENDING','2026-11-08',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(55,7,7,'PENDING','2026-11-05',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(56,7,8,'PENDING','2026-11-05',NULL,NULL,NULL,'2026-10-29 14:59:52',NULL),(57,8,1,'PENDING','2026-11-04',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(58,8,2,'PENDING','2026-11-04',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(59,8,3,'PENDING','2026-11-04',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(60,8,4,'PENDING','2026-11-04',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(61,8,5,'PENDING','2026-11-04',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(62,8,6,'PENDING','2026-11-09',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(63,8,7,'PENDING','2026-11-06',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL),(64,8,8,'PENDING','2026-11-06',NULL,NULL,NULL,'2026-10-30 16:38:51',NULL);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `unit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(40) NOT NULL,
  `name_hi` varchar(40) DEFAULT NULL,
  `symbol` varchar(15) NOT NULL,
  `to_sqm` decimal(16,8) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_units_code` (`code`),
  UNIQUE KEY `uq_units_name` (`name`),
  CONSTRAINT `ck_units_factor` CHECK (`to_sqm` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (1,'SQM','Square Meter','वर्ग मीटर','sq m',1.00000000,'Base unit — every area is also stored in sq m',1,1,1,NULL,'2026-10-30 17:15:43',NULL,NULL),(2,'SQFT','Square Feet','वर्ग फुट','sq ft',0.09290304,'1 sq ft = 0.09290304 sq m',2,1,0,NULL,'2026-10-30 17:15:43',NULL,NULL),(3,'SQYD','Square Yard','वर्ग गज','sq yd',0.83612736,'1 sq yd (gaj) = 0.83612736 sq m = 9 sq ft',3,1,0,NULL,'2026-10-30 17:15:43',NULL,NULL);
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `emp_code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `mobile` char(10) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `panel` enum('SUPER_ADMIN','DATA_ENTRY','OIC','ROLE_USER') NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_emp_code` (`emp_code`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_panel` (`panel`,`is_active`),
  KEY `ix_users_role` (`role_id`,`is_active`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE,
  CONSTRAINT `ck_users_role` CHECK (`panel` = 'ROLE_USER' and `role_id` is not null or `panel` <> 'ROLE_USER' and `role_id` is null)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'superadmin','KDA-IT-001','System Administrator','Programmer (IT Cell)','9800000001','superadmin@kda.local','$argon2id$v=19$m=65536,t=3,p=1$Rm9wTlk1MFFiMy9ZMUZxdw$qVI/JzyihzP9EZe54Icmajod6BvJPhuYL+bZpGjDAio','SUPER_ADMIN',NULL,1,0,0,NULL,'2026-11-02 10:56:24','::1',NULL,1,'2026-09-30 14:59:49',NULL,'2026-11-02 10:56:24'),(2,'dataentry','KDA-DE-001','Ramesh Kumar Meena','Data Entry Operator','9800000002','dataentry@kda.local','$argon2id$v=19$m=65536,t=3,p=1$S01MOGlYcDJ3ejJRUXJlNg$xZ+9vVkkATEyy72ke5m0IcOsTh1iieU7Z8qSpEocl70','DATA_ENTRY',NULL,1,0,0,NULL,'2026-11-02 11:04:34','::1',NULL,1,'2026-09-30 14:59:49',NULL,'2026-11-02 11:04:34'),(3,'oic','KDA-OIC-001','Suresh Chand Sharma','OIC — Sale / Auction','9800000003','oic@kda.local','$argon2id$v=19$m=65536,t=3,p=1$b2hucTB0cFlqVy5SY1JWVg$0Jcuse+kaY63zLpZkygu3h0pYDLT+fC4e/j0ZjyAIWk','OIC',NULL,1,0,0,NULL,'2026-11-02 10:58:00','::1',NULL,1,'2026-09-30 14:59:49',NULL,'2026-11-02 10:58:00'),(4,'auction1','KDA-AUC-001','Anil Kumar Jain','Assistant, Auction Cell','9800000011','auction1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$ZExWeGdBc0RuVS5SMk9rVA$iqJc4J8R/EyQBOxJh46iizW2brU1HTtZ3G2gmYl2z5Y','ROLE_USER',1,1,0,0,NULL,'2026-10-30 16:11:27','::1',NULL,1,'2026-09-30 14:59:49',NULL,'2026-10-30 16:11:27'),(5,'auction2','KDA-AUC-002','Pooja Saini','Section Incharge, Auction','9800000012','auction2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$enpGZXo4TE1VdmtKR2tTNA$6CPu6nhrTlcH2OngqGQmKEB/JoYVqHzkFW0JgTLirRU','ROLE_USER',1,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:50',NULL,NULL),(6,'lottery1','KDA-LOT-001','Mahesh Gurjar','Assistant, Lottery Cell','9800000021','lottery1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$dGRubHVmRW5jMzZrcTkybg$5KF0vHgYobcLKB/RY/r4qI+EdmJO060zP8eoilgTTZo','ROLE_USER',2,1,0,0,NULL,'2026-11-02 10:58:12','::1',NULL,1,'2026-09-30 14:59:50',NULL,'2026-11-02 10:58:12'),(7,'lottery2','KDA-LOT-002','Kavita Rathore','Section Incharge, Lottery','9800000022','lottery2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$aFVBUWFqU01VLlFIVFozdA$tQUkkJ5EOllaE5Hpx7f4YkRS5x+wac/BQ+XjkyS7T9w','ROLE_USER',2,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:50',NULL,NULL),(8,'sale1','KDA-SAL-001','Deepak Verma','Clerk, Sale Cell','9800000031','sale1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$cU9PdkN3LlVJMUNFYVRWWg$J2FuOrrfO2kylmId8SkkAA6duPXgH1y7g5ZVDyjqHjA','ROLE_USER',3,1,0,0,NULL,'2026-11-02 11:04:55','::1',NULL,1,'2026-09-30 14:59:50',NULL,'2026-11-02 11:04:55'),(9,'sale2','KDA-SAL-002','Neha Agarwal','Section Incharge, Sale','9800000032','sale2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$a2FUTXRqc0JzY3ppQnhaUA$a/NwNOtGyzGScQ9ME+GnhsHL8YPkNN8oiGtg7/PMeFM','ROLE_USER',3,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:50',NULL,NULL),(10,'lfl1','KDA-LFL-001','Bhanwar Lal Bairwa','Assistant, Land-for-Land','9800000041','lfl1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$c0NKUHo1b0ZrR0lBekdKVg$nA9Q3GzMtCSQbawZjLk/7OU7lBQYlCf7CbfUcljx2co','ROLE_USER',4,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:50',NULL,NULL),(11,'lfl2','KDA-LFL-002','Sunita Mehra','Section Incharge, LFL','9800000042','lfl2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$eXhIUDliWVM3bGRSWTdOTQ$RuvErNZYk9UaT0zw3RUrADjZOUiJQI4YeQVHHm7eLL8','ROLE_USER',4,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:50',NULL,NULL),(12,'punarvas1','KDA-PUN-001','Rajendra Prasad','Assistant, Punarvas Cell','9800000051','punarvas1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$aWY1aHEvaC5lcTNHMExsbw$xCereaGx8icPqgHc7HZ7xkyRHH/wvVrv9AG21J26reI','ROLE_USER',5,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:51',NULL,NULL),(13,'punarvas2','KDA-PUN-002','Asha Kanwar','Section Incharge, Punarvas','9800000052','punarvas2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$dVF5ZzFjUk1BY3FISXZvQQ$Ucq6f885QiGCZJZb0vLJgSz1EefKxRSilvkGD+L+08M','ROLE_USER',5,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:51',NULL,NULL),(14,'eng1','KDA-ENG-001','Vikas Choudhary','Junior Engineer','9800000061','eng1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$MU1iQ0o3dGt3UmpkWjdpeA$wJpwiNk4OVGeSkyuDRshtp1esqcoYSmSdObPNk8aKgc','ROLE_USER',6,1,0,0,NULL,'2026-11-02 11:04:45','::1',NULL,1,'2026-09-30 14:59:51',NULL,'2026-11-02 11:04:45'),(15,'eng2','KDA-ENG-002','Manoj Kumar Yadav','Assistant Engineer','9800000062','eng2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$YU1zV29ya2VRdXp2VTNHNw$+rVIKHjEb79uyT9cLewi69KuXpgQZVK4jiTx3PRdtM8','ROLE_USER',6,1,0,0,NULL,'2026-10-28 10:59:53','127.0.0.1',NULL,1,'2026-09-30 14:59:51',NULL,'2026-10-28 10:59:53'),(16,'tp1','KDA-TP-001','Shweta Joshi','Assistant Town Planner','9800000071','tp1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$RzRiYTlBN2FTN3pNZjBLUg$RWiUzT49/GBcFJTJjtdeXZu0MW7Julb0bFeer3wTEjE','ROLE_USER',7,1,0,0,NULL,'2026-10-26 07:59:53','127.0.0.1',NULL,1,'2026-09-30 14:59:51',NULL,'2026-10-26 07:59:53'),(17,'tp2','KDA-TP-002','Gaurav Singhal','Deputy Town Planner','9800000072','tp2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$YXdBZnVkQlhGV3ZFdW5iMA$77FFTQRe2AO3CgohBu4Fk4Q2IlFagdiKLnmkPXLq/OU','ROLE_USER',7,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:51',NULL,NULL),(18,'legal1','KDA-LEG-001','Advocate Ritu Sharma','Legal Assistant','9800000081','legal1@kda.local','$argon2id$v=19$m=65536,t=3,p=1$SDFUWDBlcGdqb1pacGQ4MQ$jq3dfHK5cohe/0R7rbekoCa39Tneii3uighcfZNTx7k','ROLE_USER',8,1,0,0,NULL,'2026-10-30 13:24:53','127.0.0.1',NULL,1,'2026-09-30 14:59:51',NULL,'2026-10-30 13:24:53'),(19,'legal2','KDA-LEG-002','Hemant Gupta','Law Officer','9800000082','legal2@kda.local','$argon2id$v=19$m=65536,t=3,p=1$WHIyT3V4S0ZNQWR4dDEzcQ$o6ItFargpdXSL12pc6m4hL4NErloxUPBMG330aSNTCk','ROLE_USER',8,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:51',NULL,NULL),(20,'eng3','KDA-ENG-003','Rohit Meena','Assistant Engineer (Civil)','9800000063','eng3@kda.local','$argon2id$v=19$m=65536,t=3,p=1$eTlBZy5mV1U1dWNLZWguOA$e/yaPZkrhWfjI4IN4edtBYzkEOSKYCcFk/9uGfWENYk','ROLE_USER',6,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:52',NULL,NULL),(21,'eng4','KDA-ENG-004','Sanjay Gurjar','Junior Engineer (Survey)','9800000064','eng4@kda.local','$argon2id$v=19$m=65536,t=3,p=1$Tjd2RjdiaEg1a2NCcXBmRg$d8xLaDV9RyasMAQGlmmMsjDCTYG2pkYeFlHyR2Ba5AI','ROLE_USER',6,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:52',NULL,NULL),(22,'legal3','KDA-LEG-003','Neelam Shekhawat','Legal Assistant','9800000083','legal3@kda.local','$argon2id$v=19$m=65536,t=3,p=1$RDM2RTVsUEo2YWxGSjUuUg$AEK1PaKQDISIWdy+6vDZKl+GDAcfyOZj9Fq0elM8yAQ','ROLE_USER',8,1,0,0,NULL,NULL,NULL,NULL,1,'2026-09-30 14:59:52',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-02 12:01:00
