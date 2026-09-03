-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: localhost    Database: qloapps_db
-- ------------------------------------------------------
-- Server version	8.0.42-0ubuntu0.24.04.2

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
-- Table structure for table `qlo_access`
--

DROP TABLE IF EXISTS `qlo_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_access` (
  `id_profile` int unsigned NOT NULL,
  `id_tab` int unsigned NOT NULL,
  `view` int NOT NULL,
  `add` int NOT NULL,
  `edit` int NOT NULL,
  `delete` int NOT NULL,
  `kpi` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_profile`,`id_tab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_access`
--

LOCK TABLES `qlo_access` WRITE;
/*!40000 ALTER TABLE `qlo_access` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_accessory`
--

DROP TABLE IF EXISTS `qlo_accessory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_accessory` (
  `id_product_1` int unsigned NOT NULL,
  `id_product_2` int unsigned NOT NULL,
  KEY `accessory_product` (`id_product_1`,`id_product_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_accessory`
--

LOCK TABLES `qlo_accessory` WRITE;
/*!40000 ALTER TABLE `qlo_accessory` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_accessory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_address`
--

DROP TABLE IF EXISTS `qlo_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_address` (
  `id_address` int unsigned NOT NULL AUTO_INCREMENT,
  `id_country` int unsigned NOT NULL,
  `id_state` int unsigned DEFAULT NULL,
  `id_customer` int unsigned NOT NULL DEFAULT '0',
  `id_manufacturer` int unsigned NOT NULL DEFAULT '0',
  `id_supplier` int unsigned NOT NULL DEFAULT '0',
  `id_warehouse` int unsigned NOT NULL DEFAULT '0',
  `id_hotel` int unsigned NOT NULL DEFAULT '0',
  `alias` varchar(32) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `lastname` varchar(32) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `address1` varchar(128) NOT NULL,
  `address2` varchar(128) DEFAULT NULL,
  `postcode` varchar(12) DEFAULT NULL,
  `city` varchar(64) NOT NULL,
  `other` text,
  `phone` varchar(32) DEFAULT NULL,
  `phone_mobile` varchar(32) DEFAULT NULL,
  `vat_number` varchar(32) DEFAULT NULL,
  `dni` varchar(16) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  `auto_generated` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_address`),
  KEY `address_customer` (`id_customer`),
  KEY `id_country` (`id_country`),
  KEY `id_state` (`id_state`),
  KEY `id_manufacturer` (`id_manufacturer`),
  KEY `id_supplier` (`id_supplier`),
  KEY `id_warehouse` (`id_warehouse`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_address`
--

LOCK TABLES `qlo_address` WRITE;
/*!40000 ALTER TABLE `qlo_address` DISABLE KEYS */;
INSERT INTO `qlo_address` VALUES (1,31,0,1,0,0,0,0,'My Address','','Doe','John','16, Main street','2nd floor','05339','Demo City','','0102030405','','','','2026-09-03 18:47:11','2026-09-03 18:47:11',1,0,0),(2,31,0,0,0,0,0,1,'The Hotel Prime','','The Hotel Prime','The Hotel Prime','Monticello Dr, Montgomery, 10010','','01076','Demo City','','0987654321','','','','2026-09-03 18:48:53','2026-09-03 18:48:53',1,0,0);
/*!40000 ALTER TABLE `qlo_address` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_address_format`
--

DROP TABLE IF EXISTS `qlo_address_format`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_address_format` (
  `id_country` int unsigned NOT NULL,
  `format` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_address_format`
--

LOCK TABLES `qlo_address_format` WRITE;
/*!40000 ALTER TABLE `qlo_address_format` DISABLE KEYS */;
INSERT INTO `qlo_address_format` VALUES (1,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(2,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(3,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(4,'firstname lastname\ncompany\naddress1\naddress2\ncity State:name postcode\nCountry:name\nphone\nphone_mobile'),(5,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(6,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(7,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(8,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(9,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(10,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nState:name\nCountry:name\nphone\nphone_mobile'),(11,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nState:name\nCountry:name\nphone\nphone_mobile'),(12,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(13,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(14,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(15,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(16,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(17,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\ncity\npostcode\nCountry:name\nphone\nphone_mobile'),(18,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(19,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(20,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(21,'firstname lastname\ncompany\naddress1 address2\ncity, State:name postcode\nCountry:name\nphone\nphone_mobile'),(22,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(23,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(24,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(25,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(26,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(27,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(28,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(29,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(30,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(31,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(32,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(33,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(34,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(35,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(36,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(37,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(38,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(39,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(40,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(41,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(42,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(43,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(44,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nState:name\nCountry:name\nphone\nphone_mobile'),(45,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(46,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(47,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(48,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(49,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(50,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(51,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(52,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(53,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(54,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(55,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(56,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(57,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(58,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(59,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(60,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(61,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(62,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(63,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(64,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(65,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(66,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(67,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(68,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(69,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(70,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(71,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(72,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(73,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(74,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(75,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(76,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(77,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(78,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(79,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(80,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(81,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(82,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(83,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(84,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(85,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(86,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(87,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(88,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(89,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(90,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(91,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(92,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(93,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(94,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(95,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(96,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(97,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(98,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(99,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(100,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(101,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(102,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(103,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(104,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(105,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(106,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(107,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(108,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(109,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(110,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(111,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nState:name\nCountry:name\nphone\nphone_mobile'),(112,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(113,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(114,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(115,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(116,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(117,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(118,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(119,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(120,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(121,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(122,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(123,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(124,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(125,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(126,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(127,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(128,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(129,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(130,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(131,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(132,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(133,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(134,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(135,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(136,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(137,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(138,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(139,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(140,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(141,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(142,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(143,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(144,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(145,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nState:name\nCountry:name\nphone\nphone_mobile'),(146,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(147,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(148,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(149,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(150,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(151,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(152,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(153,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(154,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(155,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(156,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(157,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(158,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(159,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(160,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(161,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(162,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(163,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(164,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(165,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(166,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(167,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(168,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(169,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(170,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(171,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(172,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(173,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(174,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(175,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(176,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(177,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(178,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(179,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(180,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(181,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(182,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(183,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(184,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(185,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(186,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(187,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(188,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(189,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(190,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(191,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(192,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(193,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(194,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(195,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(196,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(197,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(198,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(199,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(200,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(201,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(202,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(203,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(204,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(205,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(206,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(207,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(208,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(209,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(210,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(211,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(212,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(213,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(214,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(215,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(216,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(217,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(218,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(219,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(220,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(221,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(222,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(223,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(224,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(225,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(226,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(227,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(228,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(229,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(230,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(231,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(232,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(233,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(234,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(235,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(236,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(237,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(238,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(239,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(240,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(241,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(242,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(243,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile'),(244,'firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone\nphone_mobile');
/*!40000 ALTER TABLE `qlo_address_format` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_alias`
--

DROP TABLE IF EXISTS `qlo_alias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_alias` (
  `id_alias` int unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(255) NOT NULL,
  `search` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_alias`),
  UNIQUE KEY `alias` (`alias`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_alias`
--

LOCK TABLES `qlo_alias` WRITE;
/*!40000 ALTER TABLE `qlo_alias` DISABLE KEYS */;
INSERT INTO `qlo_alias` VALUES (1,'bloose','blouse',1),(2,'blues','blouse',1);
/*!40000 ALTER TABLE `qlo_alias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attachment`
--

DROP TABLE IF EXISTS `qlo_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attachment` (
  `id_attachment` int unsigned NOT NULL AUTO_INCREMENT,
  `file` varchar(40) NOT NULL,
  `file_name` varchar(128) NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `mime` varchar(128) NOT NULL,
  PRIMARY KEY (`id_attachment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attachment`
--

LOCK TABLES `qlo_attachment` WRITE;
/*!40000 ALTER TABLE `qlo_attachment` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attachment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attachment_lang`
--

DROP TABLE IF EXISTS `qlo_attachment_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attachment_lang` (
  `id_attachment` int unsigned NOT NULL AUTO_INCREMENT,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(32) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id_attachment`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attachment_lang`
--

LOCK TABLES `qlo_attachment_lang` WRITE;
/*!40000 ALTER TABLE `qlo_attachment_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attachment_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute`
--

DROP TABLE IF EXISTS `qlo_attribute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute` (
  `id_attribute` int unsigned NOT NULL AUTO_INCREMENT,
  `id_attribute_group` int unsigned NOT NULL,
  `color` varchar(32) DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_attribute`),
  KEY `attribute_group` (`id_attribute_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute`
--

LOCK TABLES `qlo_attribute` WRITE;
/*!40000 ALTER TABLE `qlo_attribute` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute_group`
--

DROP TABLE IF EXISTS `qlo_attribute_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute_group` (
  `id_attribute_group` int unsigned NOT NULL AUTO_INCREMENT,
  `is_color_group` tinyint(1) NOT NULL DEFAULT '0',
  `group_type` enum('select','radio','color') NOT NULL DEFAULT 'select',
  `position` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_attribute_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute_group`
--

LOCK TABLES `qlo_attribute_group` WRITE;
/*!40000 ALTER TABLE `qlo_attribute_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute_group_lang`
--

DROP TABLE IF EXISTS `qlo_attribute_group_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute_group_lang` (
  `id_attribute_group` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `public_name` varchar(64) NOT NULL,
  PRIMARY KEY (`id_attribute_group`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute_group_lang`
--

LOCK TABLES `qlo_attribute_group_lang` WRITE;
/*!40000 ALTER TABLE `qlo_attribute_group_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute_group_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute_group_shop`
--

DROP TABLE IF EXISTS `qlo_attribute_group_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute_group_shop` (
  `id_attribute_group` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_attribute_group`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute_group_shop`
--

LOCK TABLES `qlo_attribute_group_shop` WRITE;
/*!40000 ALTER TABLE `qlo_attribute_group_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute_group_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute_impact`
--

DROP TABLE IF EXISTS `qlo_attribute_impact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute_impact` (
  `id_attribute_impact` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `id_attribute` int unsigned NOT NULL,
  `weight` decimal(20,6) NOT NULL,
  `price` decimal(17,2) NOT NULL,
  PRIMARY KEY (`id_attribute_impact`),
  UNIQUE KEY `id_product` (`id_product`,`id_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute_impact`
--

LOCK TABLES `qlo_attribute_impact` WRITE;
/*!40000 ALTER TABLE `qlo_attribute_impact` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute_impact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute_lang`
--

DROP TABLE IF EXISTS `qlo_attribute_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute_lang` (
  `id_attribute` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`id_attribute`,`id_lang`),
  KEY `id_lang` (`id_lang`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute_lang`
--

LOCK TABLES `qlo_attribute_lang` WRITE;
/*!40000 ALTER TABLE `qlo_attribute_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_attribute_shop`
--

DROP TABLE IF EXISTS `qlo_attribute_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_attribute_shop` (
  `id_attribute` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_attribute`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_attribute_shop`
--

LOCK TABLES `qlo_attribute_shop` WRITE;
/*!40000 ALTER TABLE `qlo_attribute_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_attribute_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_carrier`
--

DROP TABLE IF EXISTS `qlo_carrier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_carrier` (
  `id_carrier` int unsigned NOT NULL AUTO_INCREMENT,
  `id_reference` int unsigned NOT NULL,
  `id_tax_rules_group` int unsigned DEFAULT '0',
  `name` varchar(64) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  `shipping_handling` tinyint unsigned NOT NULL DEFAULT '1',
  `range_behavior` tinyint unsigned NOT NULL DEFAULT '0',
  `is_module` tinyint unsigned NOT NULL DEFAULT '0',
  `is_free` tinyint unsigned NOT NULL DEFAULT '0',
  `shipping_external` tinyint unsigned NOT NULL DEFAULT '0',
  `need_range` tinyint unsigned NOT NULL DEFAULT '0',
  `external_module_name` varchar(64) DEFAULT NULL,
  `shipping_method` int NOT NULL DEFAULT '0',
  `position` int unsigned NOT NULL DEFAULT '0',
  `max_width` int DEFAULT '0',
  `max_height` int DEFAULT '0',
  `max_depth` int DEFAULT '0',
  `max_weight` decimal(20,6) DEFAULT '0.000000',
  `grade` int DEFAULT '0',
  PRIMARY KEY (`id_carrier`),
  KEY `deleted` (`deleted`,`active`),
  KEY `id_tax_rules_group` (`id_tax_rules_group`),
  KEY `reference` (`id_reference`,`deleted`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_carrier`
--

LOCK TABLES `qlo_carrier` WRITE;
/*!40000 ALTER TABLE `qlo_carrier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_carrier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_carrier_group`
--

DROP TABLE IF EXISTS `qlo_carrier_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_carrier_group` (
  `id_carrier` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_carrier_group`
--

LOCK TABLES `qlo_carrier_group` WRITE;
/*!40000 ALTER TABLE `qlo_carrier_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_carrier_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_carrier_lang`
--

DROP TABLE IF EXISTS `qlo_carrier_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_carrier_lang` (
  `id_carrier` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned NOT NULL,
  `delay` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id_lang`,`id_shop`,`id_carrier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_carrier_lang`
--

LOCK TABLES `qlo_carrier_lang` WRITE;
/*!40000 ALTER TABLE `qlo_carrier_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_carrier_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_carrier_shop`
--

DROP TABLE IF EXISTS `qlo_carrier_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_carrier_shop` (
  `id_carrier` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_carrier_shop`
--

LOCK TABLES `qlo_carrier_shop` WRITE;
/*!40000 ALTER TABLE `qlo_carrier_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_carrier_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_carrier_tax_rules_group_shop`
--

DROP TABLE IF EXISTS `qlo_carrier_tax_rules_group_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_carrier_tax_rules_group_shop` (
  `id_carrier` int unsigned NOT NULL,
  `id_tax_rules_group` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_tax_rules_group`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_carrier_tax_rules_group_shop`
--

LOCK TABLES `qlo_carrier_tax_rules_group_shop` WRITE;
/*!40000 ALTER TABLE `qlo_carrier_tax_rules_group_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_carrier_tax_rules_group_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_carrier_zone`
--

DROP TABLE IF EXISTS `qlo_carrier_zone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_carrier_zone` (
  `id_carrier` int unsigned NOT NULL,
  `id_zone` int unsigned NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_carrier_zone`
--

LOCK TABLES `qlo_carrier_zone` WRITE;
/*!40000 ALTER TABLE `qlo_carrier_zone` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_carrier_zone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart`
--

DROP TABLE IF EXISTS `qlo_cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart` (
  `id_cart` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_carrier` int unsigned NOT NULL,
  `delivery_option` text NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_address_delivery` int unsigned NOT NULL,
  `id_address_invoice` int unsigned NOT NULL,
  `id_currency` int unsigned NOT NULL,
  `id_customer` int unsigned NOT NULL,
  `id_guest` int unsigned NOT NULL,
  `secure_key` varchar(32) NOT NULL DEFAULT '-1',
  `recyclable` tinyint unsigned NOT NULL DEFAULT '1',
  `gift` tinyint unsigned NOT NULL DEFAULT '0',
  `gift_message` text,
  `mobile_theme` tinyint(1) NOT NULL DEFAULT '0',
  `allow_seperated_package` tinyint unsigned NOT NULL DEFAULT '0',
  `is_advance_payment` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_cart`),
  KEY `cart_customer` (`id_customer`),
  KEY `id_address_delivery` (`id_address_delivery`),
  KEY `id_address_invoice` (`id_address_invoice`),
  KEY `id_carrier` (`id_carrier`),
  KEY `id_lang` (`id_lang`),
  KEY `id_currency` (`id_currency`),
  KEY `id_guest` (`id_guest`),
  KEY `id_shop_group` (`id_shop_group`),
  KEY `id_shop_2` (`id_shop`,`date_upd`),
  KEY `id_shop` (`id_shop`,`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart`
--

LOCK TABLES `qlo_cart` WRITE;
/*!40000 ALTER TABLE `qlo_cart` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_cart_rule`
--

DROP TABLE IF EXISTS `qlo_cart_cart_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_cart_rule` (
  `id_cart` int unsigned NOT NULL,
  `id_cart_rule` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart`,`id_cart_rule`),
  KEY `id_cart_rule` (`id_cart_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_cart_rule`
--

LOCK TABLES `qlo_cart_cart_rule` WRITE;
/*!40000 ALTER TABLE `qlo_cart_cart_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_cart_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_customer_guest`
--

DROP TABLE IF EXISTS `qlo_cart_customer_guest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_customer_guest` (
  `id_customer_guest_detail` int unsigned NOT NULL,
  `id_cart` int unsigned NOT NULL,
  PRIMARY KEY (`id_customer_guest_detail`,`id_cart`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_customer_guest`
--

LOCK TABLES `qlo_cart_customer_guest` WRITE;
/*!40000 ALTER TABLE `qlo_cart_customer_guest` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_customer_guest` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_product`
--

DROP TABLE IF EXISTS `qlo_cart_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_product` (
  `id_cart` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `id_address_delivery` int unsigned NOT NULL DEFAULT '0',
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_product_attribute` int unsigned NOT NULL DEFAULT '0',
  `quantity` int unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_cart`,`id_product`,`id_product_attribute`,`id_address_delivery`),
  KEY `id_product_attribute` (`id_product_attribute`),
  KEY `id_cart_order` (`id_cart`,`date_add`,`id_product`,`id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_product`
--

LOCK TABLES `qlo_cart_product` WRITE;
/*!40000 ALTER TABLE `qlo_cart_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule`
--

DROP TABLE IF EXISTS `qlo_cart_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule` (
  `id_cart_rule` int unsigned NOT NULL AUTO_INCREMENT,
  `id_customer` int unsigned NOT NULL DEFAULT '0',
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `description` text,
  `quantity` int unsigned NOT NULL DEFAULT '0',
  `quantity_per_user` int unsigned NOT NULL DEFAULT '0',
  `priority` int unsigned NOT NULL DEFAULT '1',
  `partial_use` tinyint unsigned NOT NULL DEFAULT '0',
  `code` varchar(254) NOT NULL,
  `minimum_amount` decimal(17,2) NOT NULL DEFAULT '0.00',
  `minimum_amount_tax` tinyint(1) NOT NULL DEFAULT '0',
  `minimum_amount_currency` int unsigned NOT NULL DEFAULT '0',
  `minimum_amount_shipping` tinyint(1) NOT NULL DEFAULT '0',
  `country_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `carrier_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `group_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `cart_rule_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `product_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `shop_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `hotel_restriction` tinyint unsigned NOT NULL DEFAULT '0',
  `free_shipping` tinyint(1) NOT NULL DEFAULT '0',
  `reduction_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `reduction_amount` decimal(17,2) NOT NULL DEFAULT '0.00',
  `reduction_tax` tinyint unsigned NOT NULL DEFAULT '0',
  `reduction_currency` int unsigned NOT NULL DEFAULT '0',
  `reduction_product` int NOT NULL DEFAULT '0',
  `gift_product` int unsigned NOT NULL DEFAULT '0',
  `gift_product_attribute` int unsigned NOT NULL DEFAULT '0',
  `highlight` tinyint unsigned NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_cart_rule`),
  KEY `id_customer` (`id_customer`,`active`,`date_to`),
  KEY `group_restriction` (`group_restriction`,`active`,`date_to`),
  KEY `id_customer_2` (`id_customer`,`active`,`highlight`,`date_to`),
  KEY `group_restriction_2` (`group_restriction`,`active`,`highlight`,`date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule`
--

LOCK TABLES `qlo_cart_rule` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_carrier`
--

DROP TABLE IF EXISTS `qlo_cart_rule_carrier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_carrier` (
  `id_cart_rule` int unsigned NOT NULL,
  `id_carrier` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_carrier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_carrier`
--

LOCK TABLES `qlo_cart_rule_carrier` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_carrier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_carrier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_combination`
--

DROP TABLE IF EXISTS `qlo_cart_rule_combination`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_combination` (
  `id_cart_rule_1` int unsigned NOT NULL,
  `id_cart_rule_2` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule_1`,`id_cart_rule_2`),
  KEY `id_cart_rule_1` (`id_cart_rule_1`),
  KEY `id_cart_rule_2` (`id_cart_rule_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_combination`
--

LOCK TABLES `qlo_cart_rule_combination` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_combination` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_combination` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_country`
--

DROP TABLE IF EXISTS `qlo_cart_rule_country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_country` (
  `id_cart_rule` int unsigned NOT NULL,
  `id_country` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_country`
--

LOCK TABLES `qlo_cart_rule_country` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_country` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_group`
--

DROP TABLE IF EXISTS `qlo_cart_rule_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_group` (
  `id_cart_rule` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_group`
--

LOCK TABLES `qlo_cart_rule_group` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_hotel`
--

DROP TABLE IF EXISTS `qlo_cart_rule_hotel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_hotel` (
  `id_cart_rule` int unsigned NOT NULL,
  `id_hotel` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_hotel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_hotel`
--

LOCK TABLES `qlo_cart_rule_hotel` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_hotel` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_hotel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_lang`
--

DROP TABLE IF EXISTS `qlo_cart_rule_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_lang` (
  `id_cart_rule` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(254) NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_lang`
--

LOCK TABLES `qlo_cart_rule_lang` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_product_rule`
--

DROP TABLE IF EXISTS `qlo_cart_rule_product_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_product_rule` (
  `id_product_rule` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product_rule_group` int unsigned NOT NULL,
  `type` enum('products','categories','attributes','manufacturers','suppliers') NOT NULL,
  PRIMARY KEY (`id_product_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_product_rule`
--

LOCK TABLES `qlo_cart_rule_product_rule` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_product_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_product_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_product_rule_group`
--

DROP TABLE IF EXISTS `qlo_cart_rule_product_rule_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_product_rule_group` (
  `id_product_rule_group` int unsigned NOT NULL AUTO_INCREMENT,
  `id_cart_rule` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_product_rule_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_product_rule_group`
--

LOCK TABLES `qlo_cart_rule_product_rule_group` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_product_rule_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_product_rule_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_product_rule_value`
--

DROP TABLE IF EXISTS `qlo_cart_rule_product_rule_value`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_product_rule_value` (
  `id_product_rule` int unsigned NOT NULL,
  `id_item` int unsigned NOT NULL,
  PRIMARY KEY (`id_product_rule`,`id_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_product_rule_value`
--

LOCK TABLES `qlo_cart_rule_product_rule_value` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_product_rule_value` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_product_rule_value` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cart_rule_shop`
--

DROP TABLE IF EXISTS `qlo_cart_rule_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cart_rule_shop` (
  `id_cart_rule` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cart_rule_shop`
--

LOCK TABLES `qlo_cart_rule_shop` WRITE;
/*!40000 ALTER TABLE `qlo_cart_rule_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cart_rule_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_category`
--

DROP TABLE IF EXISTS `qlo_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_category` (
  `id_category` int unsigned NOT NULL AUTO_INCREMENT,
  `id_parent` int unsigned NOT NULL,
  `id_shop_default` int unsigned NOT NULL DEFAULT '1',
  `level_depth` tinyint unsigned NOT NULL DEFAULT '0',
  `nleft` int unsigned NOT NULL DEFAULT '0',
  `nright` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `is_root_category` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_category`),
  KEY `category_parent` (`id_parent`),
  KEY `nleftrightactive` (`nleft`,`nright`,`active`),
  KEY `level_depth` (`level_depth`),
  KEY `nright` (`nright`),
  KEY `activenleft` (`active`,`nleft`),
  KEY `activenright` (`active`,`nright`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_category`
--

LOCK TABLES `qlo_category` WRITE;
/*!40000 ALTER TABLE `qlo_category` DISABLE KEYS */;
INSERT INTO `qlo_category` VALUES (1,0,1,0,1,22,1,'2026-09-03 18:45:37','2026-09-03 18:45:37',0,0),(2,1,1,1,2,21,1,'2026-09-03 18:45:38','2026-09-03 18:45:38',0,1),(3,2,1,2,3,10,1,'2026-09-03 18:45:38','2026-09-03 18:45:38',0,0),(4,2,1,2,11,20,1,'2026-09-03 18:45:38','2026-09-03 18:45:38',0,0),(5,3,1,3,4,9,1,'2026-09-03 18:48:53','2026-09-03 18:48:53',0,0),(6,5,1,4,5,8,1,'2026-09-03 18:48:54','2026-09-03 18:48:54',0,0),(7,6,1,5,6,7,1,'2026-09-03 18:48:54','2026-09-03 18:48:54',0,0),(8,4,1,3,12,13,1,'2026-09-03 18:49:28','2026-09-03 18:49:28',0,0),(9,4,1,3,14,15,1,'2026-09-03 18:49:29','2026-09-03 18:49:29',0,0),(10,4,1,3,16,17,1,'2026-09-03 18:49:29','2026-09-03 18:49:29',0,0),(11,4,1,3,18,19,1,'2026-09-03 18:49:29','2026-09-03 18:49:29',0,0);
/*!40000 ALTER TABLE `qlo_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_category_group`
--

DROP TABLE IF EXISTS `qlo_category_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_category_group` (
  `id_category` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  PRIMARY KEY (`id_category`,`id_group`),
  KEY `id_category` (`id_category`),
  KEY `id_group` (`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_category_group`
--

LOCK TABLES `qlo_category_group` WRITE;
/*!40000 ALTER TABLE `qlo_category_group` DISABLE KEYS */;
INSERT INTO `qlo_category_group` VALUES (1,1),(1,2),(1,3),(2,1),(2,2),(2,3),(3,1),(3,2),(3,3),(4,1),(4,2),(4,3),(5,1),(5,2),(5,3),(6,1),(6,2),(6,3),(7,1),(7,2),(7,3),(8,1),(8,2),(8,3),(9,1),(9,2),(9,3),(10,1),(10,2),(10,3),(11,1),(11,2),(11,3);
/*!40000 ALTER TABLE `qlo_category_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_category_lang`
--

DROP TABLE IF EXISTS `qlo_category_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_category_lang` (
  `id_category` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` text,
  `link_rewrite` varchar(128) NOT NULL,
  `meta_title` varchar(128) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_category`,`id_shop`,`id_lang`),
  KEY `category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_category_lang`
--

LOCK TABLES `qlo_category_lang` WRITE;
/*!40000 ALTER TABLE `qlo_category_lang` DISABLE KEYS */;
INSERT INTO `qlo_category_lang` VALUES (1,1,1,'Root','','root','','',''),(2,1,1,'Home','','home','','',''),(3,1,1,'Locations','','locations','','',''),(4,1,1,'Services','','services','','',''),(5,1,1,'Nigeria','This category are for hotels only','nigeria','','',''),(6,1,1,'Demo City','This category are for hotels only','demo-city','','',''),(7,1,1,'The Hotel Prime','This category are for hotels only','the-hotel-prime','','',''),(8,1,1,'Restaurant','This category are for hotels only','restaurant','','',''),(9,1,1,'Transfers','This category are for hotels only','transfers','','',''),(10,1,1,'Activities','This category are for hotels only','activities','','',''),(11,1,1,'Operational charges','This category are for hotels only','operational-charges','','','');
/*!40000 ALTER TABLE `qlo_category_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_category_product`
--

DROP TABLE IF EXISTS `qlo_category_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_category_product` (
  `id_category` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_category`,`id_product`),
  KEY `id_product` (`id_product`),
  KEY `id_category` (`id_category`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_category_product`
--

LOCK TABLES `qlo_category_product` WRITE;
/*!40000 ALTER TABLE `qlo_category_product` DISABLE KEYS */;
INSERT INTO `qlo_category_product` VALUES (2,1,0),(2,2,1),(2,3,2),(2,4,3),(3,1,0),(3,2,1),(3,3,2),(3,4,3),(5,1,0),(5,2,1),(5,3,2),(5,4,3),(6,1,0),(6,2,1),(6,3,2),(6,4,3),(7,1,0),(7,2,1),(7,3,2),(7,4,3),(8,9,0),(8,10,1),(9,7,0),(9,8,1),(11,5,0),(11,6,1);
/*!40000 ALTER TABLE `qlo_category_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_category_shop`
--

DROP TABLE IF EXISTS `qlo_category_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_category_shop` (
  `id_category` int NOT NULL,
  `id_shop` int NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_category`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_category_shop`
--

LOCK TABLES `qlo_category_shop` WRITE;
/*!40000 ALTER TABLE `qlo_category_shop` DISABLE KEYS */;
INSERT INTO `qlo_category_shop` VALUES (1,1,0),(2,1,0),(3,1,0),(4,1,1),(5,1,0),(6,1,0),(7,1,0),(8,1,0),(9,1,1),(10,1,2),(11,1,3);
/*!40000 ALTER TABLE `qlo_category_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms`
--

DROP TABLE IF EXISTS `qlo_cms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms` (
  `id_cms` int unsigned NOT NULL AUTO_INCREMENT,
  `id_cms_category` int unsigned NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `indexation` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_cms`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms`
--

LOCK TABLES `qlo_cms` WRITE;
/*!40000 ALTER TABLE `qlo_cms` DISABLE KEYS */;
INSERT INTO `qlo_cms` VALUES (1,1,0,1,0),(2,1,1,1,0),(3,1,2,1,0),(4,1,3,1,0),(5,1,4,1,0);
/*!40000 ALTER TABLE `qlo_cms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_category`
--

DROP TABLE IF EXISTS `qlo_cms_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_category` (
  `id_cms_category` int unsigned NOT NULL AUTO_INCREMENT,
  `id_parent` int unsigned NOT NULL,
  `level_depth` tinyint unsigned NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_cms_category`),
  KEY `category_parent` (`id_parent`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_category`
--

LOCK TABLES `qlo_cms_category` WRITE;
/*!40000 ALTER TABLE `qlo_cms_category` DISABLE KEYS */;
INSERT INTO `qlo_cms_category` VALUES (1,0,1,1,'2026-09-03 18:45:39','2026-09-03 18:45:39',0);
/*!40000 ALTER TABLE `qlo_cms_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_category_lang`
--

DROP TABLE IF EXISTS `qlo_cms_category_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_category_lang` (
  `id_cms_category` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `name` varchar(128) NOT NULL,
  `description` text,
  `link_rewrite` varchar(128) NOT NULL,
  `meta_title` varchar(128) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_cms_category`,`id_shop`,`id_lang`),
  KEY `category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_category_lang`
--

LOCK TABLES `qlo_cms_category_lang` WRITE;
/*!40000 ALTER TABLE `qlo_cms_category_lang` DISABLE KEYS */;
INSERT INTO `qlo_cms_category_lang` VALUES (1,1,1,'Home','','home','','','');
/*!40000 ALTER TABLE `qlo_cms_category_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_category_shop`
--

DROP TABLE IF EXISTS `qlo_cms_category_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_category_shop` (
  `id_cms_category` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_cms_category`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_category_shop`
--

LOCK TABLES `qlo_cms_category_shop` WRITE;
/*!40000 ALTER TABLE `qlo_cms_category_shop` DISABLE KEYS */;
INSERT INTO `qlo_cms_category_shop` VALUES (1,1);
/*!40000 ALTER TABLE `qlo_cms_category_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_lang`
--

DROP TABLE IF EXISTS `qlo_cms_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_lang` (
  `id_cms` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `meta_title` varchar(128) NOT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `content` longtext,
  `link_rewrite` varchar(128) NOT NULL,
  PRIMARY KEY (`id_cms`,`id_shop`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_lang`
--

LOCK TABLES `qlo_cms_lang` WRITE;
/*!40000 ALTER TABLE `qlo_cms_lang` DISABLE KEYS */;
INSERT INTO `qlo_cms_lang` VALUES (1,1,1,'Policies','Our policies of hotel bookings','conditions, policy, guidelines, protocol, rule','<h2 class=\"page-heading bottom-indent\">Policies</h2>\n      <div>\n      <p style=\"border-left:2px solid #808080;padding-left:5px;\">This page contains demo content for illustrative purposes only. Any resemblance to actual products, services, or events is purely coincidental. Thank you for your understanding.</p>\n      <hr /></div>\n      <div>\n      <p class=\"page-subheading\">Reservation Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Guests must provide valid credit card details to secure a reservation.</li>\n      <li class=\"margin-btm-10\">Cancellation policies vary based on room type and rate plan.</li>\n      <li class=\"margin-btm-10\">Changes to reservations may be subject to availability and additional charges.</li>\n      </ul>\n      <p class=\"page-subheading\">Check-in and Check-out Times:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Check-in time: 11:00 AM</li>\n      <li class=\"margin-btm-10\">Check-out time: 10:30 AM</li>\n      <li class=\"margin-btm-10\">Early check-in and late check-out requests are subject to availability and may incur additional charges.</li>\n      </ul>\n      <p class=\"page-subheading\">Payment Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Payment for the entire stay is due upon check-in.</li>\n      <li class=\"margin-btm-10\">Acceptable forms of payment: credit cards, cash, etc.</li>\n      <li class=\"margin-btm-10\">A security deposit may be required upon arrival.</li>\n      </ul>\n      <p class=\"page-subheading\">Cancellation and No-show Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Cancellations made 7 days prior to arrival are eligible for a full refund.</li>\n      <li class=\"margin-btm-10\">No-shows will be charged the full amount of the reservation.</li>\n      </ul>\n      <p class=\"page-subheading\">Pet Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Pets are welcome with prior arrangement.</li>\n      <li class=\"margin-btm-10\">Additional charges may apply for pet-friendly accommodations.</li>\n      <li class=\"margin-btm-10\">Guests are responsible for any damages caused by their pets.</li>\n      </ul>\n      <p class=\"page-subheading\">Smoking Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Our hotel is smoke-free. Smoking is strictly prohibited indoors.</li>\n      <li class=\"margin-btm-10\">Designated smoking areas are available outside the premises.</li>\n      </ul>\n      <p class=\"page-subheading\">Privacy Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">We are committed to protecting your privacy and personal information.</li>\n      <li class=\"margin-btm-10\">Information collected during booking and stay is used solely for operational purposes.</li>\n      </ul>\n      <p class=\"page-subheading\">Accessibility Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Our hotel is committed to providing accessible accommodations and facilities for guests with disabilities.</li>\n      <li class=\"margin-btm-10\">Please contact us directly for specific accessibility requests.</li>\n      </ul>\n      <p class=\"page-subheading\">Additional Policies:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-10\">Use of hotel facilities such as the gym, spa, pool, etc.</li>\n      <li class=\"margin-btm-10\">Conduct and behavior expectations while on hotel premises.</li>\n      </ul>\n      </div>\n    ','policies'),(2,1,1,'Legal Notice','Legal notice','notice, legal, credits','\n      <h2 class=\"page-heading bottom-indent\"><strong>Legal Notice</strong></h2>\n      <div>\n      <p style=\"border-left:2px solid #808080;padding-left:5px;\">This page contains demo content for illustrative purposes only. Any resemblance to actual products, services, or events is purely coincidental. Thank you for your understanding.</p>\n      <hr /></div>\n      <p class=\"margin-btm-30 page-subheading\">Website Terms of Use:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-20\">The content of the pages of this website is for your general information and use only. It is subject to change without notice.</li>\n      <li class=\"margin-btm-20\">Neither we nor any third parties provide any warranty or guarantee as to the accuracy, timeliness, performance, completeness, or suitability of the information and materials found or offered on this website for any particular purpose. You acknowledge that such information and materials may contain inaccuracies or errors, and we expressly exclude liability for any such inaccuracies or errors to the fullest extent permitted by law.</li>\n      <li class=\"margin-btm-20\">Your use of any information or materials on this website is entirely at your own risk, for which we shall not be liable. It shall be your own responsibility to ensure that any products, services, or information available through this website meet your specific requirements.</li>\n      </ul>\n      <p class=\"page-subheading\">Intellectual Property:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-20\">This website contains material which is owned by or licensed to us. This material includes, but is not limited to, the design, layout, look, appearance, and graphics. Reproduction is prohibited other than in accordance with the copyright notice, which forms part of these terms and conditions.</li>\n      <li class=\"margin-btm-20\">All trademarks reproduced in this website, which are not the property of, or licensed to the operator, are acknowledged on the website.</li>\n      </ul>\n      <p class=\"page-subheading\">Privacy Policy:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-20\">Your privacy is important to us. Please review our Privacy Policy to understand how we collect, use, and disclose information about you.</li>\n      </ul>\n      <p class=\"page-subheading\">Limitation of Liability:</p>\n      <ul class=\"margin-btm-30\">\n      <li class=\"margin-btm-20\">Neither we nor any third parties provide any warranty or guarantee as to the accuracy, timeliness, performance, completeness, or suitability of the information and materials found or offered on this website for any particular purpose. You acknowledge that such information and materials may contain inaccuracies or errors, and we expressly exclude liability for any such inaccuracies or errors to the fullest extent permitted by law.</li>\n      <li class=\"margin-btm-20\">Your use of any information or materials on this website is entirely at your own risk, for which we shall not be liable. It shall be your own responsibility to ensure that any products, services, or information available through this website meet your specific requirements.</li>\n      </ul>\n    ','legal-notice'),(3,1,1,'Terms and Conditions','Our Terms and Conditions','conditions, terms, use, sell','\n      <h2 class=\"page-heading bottom-indent\">Terms and Conditions</h2>\n      <div>\n      <p style=\"border-left:2px solid #808080;padding-left:5px;\">This page contains demo content for illustrative purposes only. Any resemblance to actual products, services, or events is purely coincidental. Thank you for your understanding.</p>\n      <hr /></div>\n      <div>\n      <h3 class=\"page-subheading\"><strong>Cancellation Policy:</strong></h3>\n      <div>\n      <ul>\n      <li><span>Free Cancellation: Up to 48 hours before arrival for standard rates.</span></li>\n      <li><span>Bookings made with a non-refundable rate cannot be canceled or modified.</span></li>\n      <li><span>Cancellations within 24 hours of arrival will incur a fee of one night\'s stay.</span></li>\n      </ul>\n      </div>\n      <br />\n      <h3 class=\"page-subheading\"><strong>Changes to Bookings:</strong></h3>\n      <div>\n      <ul>\n      <li><span>A fee of $25 may apply to change your reservation dates after booking.</span></li>\n      <li><span>Changes to room type or number of guests are subject to availability at the hotel.</span></li>\n      </ul>\n      </div>\n      <br />\n      <h3 class=\"page-subheading\"><strong>Guest Responsibility:</strong></h3>\n      <div>\n      <ul>\n      <li><span>All guests must present a valid government-issued photo ID at check-in.</span></li>\n      <li><span>The maximum occupancy for the room type is 2 adults.</span></li>\n      <li><span>Smoking and alcohol are strictly prohibited in all guest rooms and public areas.</span></li>\n      </ul>\n      </div>\n      </div>\n    ','terms-and-conditions-of-use'),(4,1,1,'About Us','Learn more about us','about us, informations','<h2 class=\"page-heading bottom-indent\">About Us</h2><div>\n      <p style=\"border-left:2px solid #808080;padding-left:5px;\">This page contains demo content for illustrative purposes only. Any resemblance to actual products, services, or events is purely coincidental. Thank you for your understanding.</p>\n      <hr /></div>\n      <div class=\"row\">\n      <div class=\"col-xs-12 col-sm-8\">\n      <div class=\"cms-block\">\n      <div class=\"margin-btm-30\">\n      <p class=\"dark\">Welcome to our sanctuary of luxury and tranquility, where every stay is crafted to provide an unparalleled experience. Whether you\'re seeking a relaxing getaway, a productive business trip, or a memorable event, we are dedicated to making your visit exceptional.</p>\n      </div>\n      <div class=\"margin-btm-30\">\n      <p class=\"page-subheading\">Our Story</p>\n      <p>Inspired by serene surroundings and rich local culture, our hotel combines modern comfort with timeless elegance. Our thoughtfully designed rooms, impeccable service, and attention to detail reflect our commitment to providing a haven for travelers.</p>\n      </div>\n      <div class=\"margin-btm-30\">\n      <p class=\"page-subheading\">Our Mission</p>\n      <p>Our mission is to create a home away from home, offering personalized service and luxurious amenities tailored to your needs. Your comfort and satisfaction are our top priorities, and we strive to exceed your expectations at every turn.</p>\n      </div>\n      <div class=\"margin-btm-30\">\n      <p class=\"page-subheading\">Our Amenities</p>\n      <p>Indulge in the finest amenities during your stay. Relax in elegantly appointed rooms and suites with breathtaking views. Savor gourmet dining at our on-site restaurant, unwind with a cocktail at our stylish bar, and rejuvenate at our state-of-the-art spa and fitness center. Enjoy our serene outdoor pool, lush gardens, and modern business facilities to cater to all your needs.</p>\n      </div>\n      <div class=\"margin-btm-30\">\n      <p class=\"page-subheading\">Our Team</p>\n      <p>Our dedicated team of hospitality professionals is here to serve you with a passion for excellence. From personalized concierge services to meticulous housekeeping, every member of our team is committed to making your stay extraordinary.</p>\n      </div>\n      <div class=\"margin-btm-30\">\n      <p class=\"page-subheading\">Join Us</p>\n      <p>Discover the charm and elegance of our hotel for yourself. Whether visiting for leisure or business, we invite you to experience our warm hospitality and luxurious accommodations. Book your stay with us today and let us make your visit unforgettable.</p>\n      </div>\n      </div>\n      </div>\n      <div class=\"col-xs-12 col-sm-4\">\n      <div class=\"cms-box\">\n      <p class=\"page-subheading\">Testimonials</p>\n      <div class=\"testimonials\">\n      <div class=\"inner\"><span class=\"before\">“</span>Hotel Prime surpassed my expectations in every way. The Executive Room was not only luxurious but also provided a peaceful retreat with its stunning lake views. The attention to detail and personalized service made my stay truly memorable. I highly recommend Hotel Prime for both business and leisure travelers looking for a blend of comfort and sophistication<span class=\"after\">”</span></div>\n      </div>\n      <p><strong class=\"dark\">Calrk Kent</strong></p>\n      <div class=\"testimonials\">\n      <div class=\"inner\"><span class=\"before\">“</span>As a frequent traveler, I can confidently say Hotel Prime stands out for its tranquil environment and exceptional hospitality. The View Room provided a perfect retreat with its stunning cityscape views and comfortable accommodations.<span class=\"after\">”</span></div>\n      </div>\n      <p><strong class=\"dark\">john Doe</strong></p>\n      </div>\n      </div>\n      </div>\n    ','about-us'),(5,1,1,'Secure payment','Our secure payment method','secure payment, ssl, visa, mastercard, paypal','<h2 class=\"page-heading bottom-indent\">Secure payment</h2>\n      <div>\n      <p style=\"border-left:2px solid #808080;padding-left:5px;\">This page contains demo content for illustrative purposes only. Any resemblance to actual products, services, or events is purely coincidental. Thank you for your understanding.</p>\n      <hr /></div>\n      <div>We prioritize your security. All transactions made on our website are encrypted using Secure Socket Layer (SSL) technology to ensure your personal information and payment details are protected. We accept major credit cards and provide a secure environment for a seamless booking experience. For any inquiries regarding payment security, feel free to contact our customer support team.</div>\n    ','secure-payment');
/*!40000 ALTER TABLE `qlo_cms_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_role`
--

DROP TABLE IF EXISTS `qlo_cms_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_role` (
  `id_cms_role` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `id_cms` int unsigned NOT NULL,
  PRIMARY KEY (`id_cms_role`,`id_cms`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_role`
--

LOCK TABLES `qlo_cms_role` WRITE;
/*!40000 ALTER TABLE `qlo_cms_role` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cms_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_role_lang`
--

DROP TABLE IF EXISTS `qlo_cms_role_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_role_lang` (
  `id_cms_role` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id_cms_role`,`id_lang`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_role_lang`
--

LOCK TABLES `qlo_cms_role_lang` WRITE;
/*!40000 ALTER TABLE `qlo_cms_role_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_cms_role_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_cms_shop`
--

DROP TABLE IF EXISTS `qlo_cms_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_cms_shop` (
  `id_cms` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_cms`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_cms_shop`
--

LOCK TABLES `qlo_cms_shop` WRITE;
/*!40000 ALTER TABLE `qlo_cms_shop` DISABLE KEYS */;
INSERT INTO `qlo_cms_shop` VALUES (1,1),(2,1),(3,1),(4,1),(5,1);
/*!40000 ALTER TABLE `qlo_cms_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_compare`
--

DROP TABLE IF EXISTS `qlo_compare`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_compare` (
  `id_compare` int unsigned NOT NULL AUTO_INCREMENT,
  `id_customer` int unsigned NOT NULL,
  PRIMARY KEY (`id_compare`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_compare`
--

LOCK TABLES `qlo_compare` WRITE;
/*!40000 ALTER TABLE `qlo_compare` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_compare` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_compare_product`
--

DROP TABLE IF EXISTS `qlo_compare_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_compare_product` (
  `id_compare` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_compare`,`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_compare_product`
--

LOCK TABLES `qlo_compare_product` WRITE;
/*!40000 ALTER TABLE `qlo_compare_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_compare_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_configuration`
--

DROP TABLE IF EXISTS `qlo_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_configuration` (
  `id_configuration` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop_group` int unsigned DEFAULT NULL,
  `id_shop` int unsigned DEFAULT NULL,
  `name` varchar(254) NOT NULL,
  `value` text,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_configuration`),
  KEY `name` (`name`),
  KEY `id_shop` (`id_shop`),
  KEY `id_shop_group` (`id_shop_group`)
) ENGINE=InnoDB AUTO_INCREMENT=408 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_configuration`
--

LOCK TABLES `qlo_configuration` WRITE;
/*!40000 ALTER TABLE `qlo_configuration` DISABLE KEYS */;
INSERT INTO `qlo_configuration` VALUES (1,NULL,NULL,'PS_LANG_DEFAULT','1','2026-09-03 18:44:53','2026-09-03 18:44:53'),(2,NULL,NULL,'PS_VERSION_DB','1.6.1.23','2026-09-03 18:44:53','2026-09-03 18:44:53'),(3,NULL,NULL,'PS_INSTALL_VERSION','1.6.1.23','2026-09-03 18:44:53','2026-09-03 18:44:53'),(4,NULL,NULL,'QLO_VERSION_DB','1.7.0.0','2026-09-03 18:44:53','2026-09-03 18:44:53'),(5,NULL,NULL,'QLO_INSTALL_VERSION','1.7.0.0','2026-09-03 18:44:53','2026-09-03 18:44:53'),(6,NULL,NULL,'PS_GROUP_FEATURE_ACTIVE','1','2026-09-03 18:45:38','2026-09-03 18:45:38'),(7,NULL,NULL,'PS_SEARCH_INDEXATION','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(8,NULL,NULL,'PS_ONE_PHONE_AT_LEAST','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(9,NULL,NULL,'PS_CART_FOLLOWING','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(10,NULL,NULL,'PS_CARRIER_DEFAULT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(11,NULL,NULL,'PS_CURRENCY_DEFAULT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(12,NULL,NULL,'PS_COUNTRY_DEFAULT','31','0000-00-00 00:00:00','2026-09-03 18:46:26'),(13,NULL,NULL,'PS_REWRITING_SETTINGS','1','0000-00-00 00:00:00','2026-09-03 18:46:26'),(14,NULL,NULL,'PS_ORDER_OUT_OF_STOCK','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(15,NULL,NULL,'PS_LAST_QTIES','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(16,NULL,NULL,'PS_CART_REDIRECT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(17,NULL,NULL,'PS_CONDITIONS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(18,NULL,NULL,'PS_RECYCLABLE_PACK','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(19,NULL,NULL,'PS_GIFT_WRAPPING','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(20,NULL,NULL,'PS_GIFT_WRAPPING_PRICE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(21,NULL,NULL,'PS_STOCK_MANAGEMENT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(22,NULL,NULL,'PS_NAVIGATION_PIPE','>','0000-00-00 00:00:00','0000-00-00 00:00:00'),(23,NULL,NULL,'PS_PRODUCTS_PER_PAGE','12','0000-00-00 00:00:00','0000-00-00 00:00:00'),(24,NULL,NULL,'PS_HOTEL_IMAGES_PER_PAGE','9','0000-00-00 00:00:00','0000-00-00 00:00:00'),(25,NULL,NULL,'PS_PURCHASE_MINIMUM','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(26,NULL,NULL,'PS_PRODUCTS_ORDER_WAY','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(27,NULL,NULL,'PS_PRODUCTS_ORDER_BY','4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(28,NULL,NULL,'PS_DISPLAY_QTIES','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(29,NULL,NULL,'PS_SHIPPING_HANDLING','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(30,NULL,NULL,'PS_SHIPPING_FREE_PRICE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(31,NULL,NULL,'PS_SHIPPING_FREE_WEIGHT','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(32,NULL,NULL,'PS_SHIPPING_METHOD','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(33,NULL,NULL,'PS_TAX','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(34,NULL,NULL,'PS_SHOP_ENABLE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(35,NULL,NULL,'PS_ALLOW_EMP','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(36,NULL,NULL,'PS_ALLOW_EMP_MAX_ATTEMPTS','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(37,NULL,NULL,'PS_NB_DAYS_NEW_PRODUCT','20','0000-00-00 00:00:00','0000-00-00 00:00:00'),(38,NULL,NULL,'PS_KPI_BEST_SELLING_ROOM_TYPE_NB_DAYS','30','0000-00-00 00:00:00','0000-00-00 00:00:00'),(39,NULL,NULL,'PS_SSL_ENABLED','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(40,NULL,NULL,'PS_WEIGHT_UNIT','kg','0000-00-00 00:00:00','0000-00-00 00:00:00'),(41,NULL,NULL,'PS_BLOCK_CART_AJAX','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(42,NULL,NULL,'PS_ORDER_RETURN','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(43,NULL,NULL,'PS_ORDER_RETURN_NB_DAYS','14','0000-00-00 00:00:00','0000-00-00 00:00:00'),(44,NULL,NULL,'PS_MAIL_TYPE','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(45,NULL,NULL,'PS_PRODUCT_PICTURE_MAX_SIZE','8388608','0000-00-00 00:00:00','0000-00-00 00:00:00'),(46,NULL,NULL,'PS_PRODUCT_PICTURE_WIDTH','64','0000-00-00 00:00:00','0000-00-00 00:00:00'),(47,NULL,NULL,'PS_PRODUCT_PICTURE_HEIGHT','64','0000-00-00 00:00:00','0000-00-00 00:00:00'),(48,NULL,NULL,'PS_INVOICE_PREFIX','#IN','0000-00-00 00:00:00','0000-00-00 00:00:00'),(49,NULL,NULL,'PS_PAYMENT_RECEIPTS_PREFIX','#IN','0000-00-00 00:00:00','0000-00-00 00:00:00'),(50,NULL,NULL,'PS_INVCE_INVOICE_ADDR_RULES','{\"avoid\":[]}','0000-00-00 00:00:00','0000-00-00 00:00:00'),(51,NULL,NULL,'PS_INVCE_DELIVERY_ADDR_RULES','{\"avoid\":[]}','0000-00-00 00:00:00','0000-00-00 00:00:00'),(52,NULL,NULL,'PS_DELIVERY_PREFIX','#DE','0000-00-00 00:00:00','0000-00-00 00:00:00'),(53,NULL,NULL,'PS_DELIVERY_NUMBER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(54,NULL,NULL,'PS_RETURN_PREFIX','#RE','0000-00-00 00:00:00','0000-00-00 00:00:00'),(55,NULL,NULL,'PS_INVOICE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(56,NULL,NULL,'PS_PAYMENT_RECEIPTS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(57,NULL,NULL,'PS_PASSWD_TIME_BACK','360','0000-00-00 00:00:00','0000-00-00 00:00:00'),(58,NULL,NULL,'PS_PASSWD_TIME_FRONT','360','0000-00-00 00:00:00','0000-00-00 00:00:00'),(59,NULL,NULL,'PS_DISP_UNAVAILABLE_ATTR','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(60,NULL,NULL,'PS_SEARCH_MINWORDLEN','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(61,NULL,NULL,'PS_SEARCH_BLACKLIST','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(62,NULL,NULL,'PS_SEARCH_WEIGHT_PNAME','6','0000-00-00 00:00:00','0000-00-00 00:00:00'),(63,NULL,NULL,'PS_SEARCH_WEIGHT_REF','10','0000-00-00 00:00:00','0000-00-00 00:00:00'),(64,NULL,NULL,'PS_SEARCH_WEIGHT_SHORTDESC','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(65,NULL,NULL,'PS_SEARCH_WEIGHT_DESC','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(66,NULL,NULL,'PS_SEARCH_WEIGHT_CNAME','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(67,NULL,NULL,'PS_SEARCH_WEIGHT_MNAME','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(68,NULL,NULL,'PS_SEARCH_WEIGHT_TAG','4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(69,NULL,NULL,'PS_SEARCH_WEIGHT_ATTRIBUTE','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(70,NULL,NULL,'PS_SEARCH_WEIGHT_FEATURE','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(71,NULL,NULL,'PS_SEARCH_AJAX','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(72,NULL,NULL,'PS_TIMEZONE','US/Eastern','0000-00-00 00:00:00','2026-09-03 18:46:26'),(73,NULL,NULL,'PS_THEME_V11','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(74,NULL,NULL,'PRESTASTORE_LIVE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(75,NULL,NULL,'PS_TIN_ACTIVE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(76,NULL,NULL,'PS_SHOW_ALL_MODULES','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(77,NULL,NULL,'PS_BACKUP_ALL','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(78,NULL,NULL,'PS_1_3_UPDATE_DATE','2011-12-27 10:20:42','0000-00-00 00:00:00','0000-00-00 00:00:00'),(79,NULL,NULL,'PS_PRICE_ROUND_MODE','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(80,NULL,NULL,'PS_1_3_2_UPDATE_DATE','2011-12-27 10:20:42','0000-00-00 00:00:00','0000-00-00 00:00:00'),(81,NULL,NULL,'PS_CONDITIONS_CMS_ID','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(82,NULL,NULL,'PS_ORDER_LIST_PRICE_DISPLAY_CURRENCY','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(83,NULL,NULL,'PS_ALLOW_ADD_ALL_SERVICES_IN_BOOKING','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(84,NULL,NULL,'PS_ALLOW_CREATE_CUSTOM_SERVICES_IN_BOOKING','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(85,NULL,NULL,'TRACKING_DIRECT_TRAFFIC','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(86,NULL,NULL,'PS_META_KEYWORDS','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(87,NULL,NULL,'PS_DISPLAY_JQZOOM','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(88,NULL,NULL,'PS_VOLUME_UNIT','cl','0000-00-00 00:00:00','0000-00-00 00:00:00'),(89,NULL,NULL,'PS_CIPHER_ALGORITHM','0','0000-00-00 00:00:00','2026-09-03 18:46:26'),(90,NULL,NULL,'PS_ATTRIBUTE_CATEGORY_DISPLAY','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(91,NULL,NULL,'PS_CUSTOMER_SERVICE_FILE_UPLOAD','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(92,NULL,NULL,'PS_CUSTOMER_SERVICE_SIGNATURE','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(93,NULL,NULL,'PS_BLOCK_BESTSELLERS_DISPLAY','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(94,NULL,NULL,'PS_BLOCK_NEWPRODUCTS_DISPLAY','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(95,NULL,NULL,'PS_BLOCK_SPECIALS_DISPLAY','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(96,NULL,NULL,'PS_STOCK_MVT_REASON_DEFAULT','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(97,NULL,NULL,'PS_COMPARATOR_MAX_ITEM','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(98,NULL,NULL,'PS_ORDER_PROCESS_TYPE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(99,NULL,NULL,'PS_SPECIFIC_PRICE_PRIORITIES','id_shop;id_currency;id_country;id_group','0000-00-00 00:00:00','0000-00-00 00:00:00'),(100,NULL,NULL,'PS_TAX_DISPLAY','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(101,NULL,NULL,'PS_SMARTY_FORCE_COMPILE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(102,NULL,NULL,'PS_DISTANCE_UNIT','km','0000-00-00 00:00:00','0000-00-00 00:00:00'),(103,NULL,NULL,'PS_STORES_DISPLAY_CMS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(104,NULL,NULL,'PS_STORES_DISPLAY_FOOTER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(105,NULL,NULL,'PS_STORES_SIMPLIFIED','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(106,NULL,NULL,'SHOP_LOGO_WIDTH','243','0000-00-00 00:00:00','0000-00-00 00:00:00'),(107,NULL,NULL,'SHOP_LOGO_HEIGHT','120','0000-00-00 00:00:00','0000-00-00 00:00:00'),(108,NULL,NULL,'EDITORIAL_IMAGE_WIDTH','530','0000-00-00 00:00:00','0000-00-00 00:00:00'),(109,NULL,NULL,'EDITORIAL_IMAGE_HEIGHT','228','0000-00-00 00:00:00','0000-00-00 00:00:00'),(110,NULL,NULL,'PS_STATSDATA_CUSTOMER_PAGESVIEWS','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(111,NULL,NULL,'PS_STATSDATA_PAGESVIEWS','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(112,NULL,NULL,'PS_STATSDATA_PLUGINS','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(113,NULL,NULL,'PS_GEOLOCATION_ENABLED','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(114,NULL,NULL,'PS_ALLOWED_COUNTRIES','AF;ZA;AX;AL;DZ;DE;AD;AO;AI;AQ;AG;AN;SA;AR;AM;AW;AU;AT;AZ;BS;BH;BD;BB;BY;BE;BZ;BJ;BM;BT;BO;BA;BW;BV;BR;BN;BG;BF;MM;BI;KY;KH;CM;CA;CV;CF;CL;CN;CX;CY;CC;CO;KM;CG;CD;CK;KR;KP;CR;CI;HR;CU;DK;DJ;DM;EG;IE;SV;AE;EC;ER;ES;EE;ET;FK;FO;FJ;FI;FR;GA;GM;GE;GS;GH;GI;GR;GD;GL;GP;GU;GT;GG;GN;GQ;GW;GY;GF;HT;HM;HN;HK;HU;IM;MU;VG;VI;IN;ID;IR;IQ;IS;IL;IT;JM;JP;JE;JO;KZ;KE;KG;KI;KW;LA;LS;LV;LB;LR;LY;LI;LT;LU;MO;MK;MG;MY;MW;MV;ML;MT;MP;MA;MH;MQ;MR;YT;MX;FM;MD;MC;MN;ME;MS;MZ;NA;NR;NP;NI;NE;NG;NU;NF;NO;NC;NZ;IO;OM;UG;UZ;PK;PW;PS;PA;PG;PY;NL;PE;PH;PN;PL;PF;PR;PT;QA;DO;CZ;RE;RO;GB;RU;RW;EH;BL;KN;SM;MF;PM;VA;VC;LC;SB;WS;AS;ST;SN;RS;SC;SL;SG;SK;SI;SO;SD;LK;SE;CH;SR;SJ;SZ;SY;TJ;TW;TZ;TD;TF;TH;TL;TG;TK;TO;TT;TN;TM;TC;TR;TV;UA;UY;US;VU;VE;VN;WF;YE;ZM;ZW','0000-00-00 00:00:00','0000-00-00 00:00:00'),(115,NULL,NULL,'PS_GEOLOCATION_BEHAVIOR','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(116,NULL,NULL,'PS_LOCALE_LANGUAGE','en','0000-00-00 00:00:00','2026-09-03 18:46:26'),(117,NULL,NULL,'PS_LOCALE_COUNTRY','ng','0000-00-00 00:00:00','2026-09-03 18:46:26'),(118,NULL,NULL,'PS_ATTACHMENT_MAXIMUM_SIZE','8','0000-00-00 00:00:00','0000-00-00 00:00:00'),(119,NULL,NULL,'PS_SMARTY_CACHE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(120,NULL,NULL,'PS_DIMENSION_UNIT','ft','0000-00-00 00:00:00','0000-00-00 00:00:00'),(121,NULL,NULL,'PS_GUEST_CHECKOUT_ENABLED','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(122,NULL,NULL,'PS_OVERBOOKING_ORDER_ACTION','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(123,NULL,NULL,'PS_MAX_OVERBOOKING_PER_HOTEL_PER_DAY','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(124,NULL,NULL,'PS_ORDER_CONF_MAIL_TO_CUSTOMER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(125,NULL,NULL,'PS_ORDER_CONF_MAIL_TO_SUPERADMIN','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(126,NULL,NULL,'PS_ORDER_CONF_MAIL_TO_HOTEL_MANAGER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(127,NULL,NULL,'PS_ORDER_CONF_MAIL_TO_EMPLOYEE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(128,NULL,NULL,'PS_DISPLAY_SUPPLIERS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(129,NULL,NULL,'PS_DISPLAY_BEST_SELLERS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(130,NULL,NULL,'PS_CATALOG_MODE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(131,NULL,NULL,'PS_GEOLOCATION_WHITELIST','127;209.185.108;209.185.253;209.85.238;209.85.238.11;209.85.238.4;216.239.33.96;216.239.33.97;216.239.33.98;216.239.33.99;216.239.37.98;216.239.37.99;216.239.39.98;216.239.39.99;216.239.41.96;216.239.41.97;216.239.41.98;216.239.41.99;216.239.45.4;216.239.46;216.239.51.96;216.239.51.97;216.239.51.98;216.239.51.99;216.239.53.98;216.239.53.99;216.239.57.96;91.240.109;216.239.57.97;216.239.57.98;216.239.57.99;216.239.59.98;216.239.59.99;216.33.229.163;64.233.173.193;64.233.173.194;64.233.173.195;64.233.173.196;64.233.173.197;64.233.173.198;64.233.173.199;64.233.173.200;64.233.173.201;64.233.173.202;64.233.173.203;64.233.173.204;64.233.173.205;64.233.173.206;64.233.173.207;64.233.173.208;64.233.173.209;64.233.173.210;64.233.173.211;64.233.173.212;64.233.173.213;64.233.173.214;64.233.173.215;64.233.173.216;64.233.173.217;64.233.173.218;64.233.173.219;64.233.173.220;64.233.173.221;64.233.173.222;64.233.173.223;64.233.173.224;64.233.173.225;64.233.173.226;64.233.173.227;64.233.173.228;64.233.173.229;64.233.173.230;64.233.173.231;64.233.173.232;64.233.173.233;64.233.173.234;64.233.173.235;64.233.173.236;64.233.173.237;64.233.173.238;64.233.173.239;64.233.173.240;64.233.173.241;64.233.173.242;64.233.173.243;64.233.173.244;64.233.173.245;64.233.173.246;64.233.173.247;64.233.173.248;64.233.173.249;64.233.173.250;64.233.173.251;64.233.173.252;64.233.173.253;64.233.173.254;64.233.173.255;64.68.80;64.68.81;64.68.82;64.68.83;64.68.84;64.68.85;64.68.86;64.68.87;64.68.88;64.68.89;64.68.90.1;64.68.90.10;64.68.90.11;64.68.90.12;64.68.90.129;64.68.90.13;64.68.90.130;64.68.90.131;64.68.90.132;64.68.90.133;64.68.90.134;64.68.90.135;64.68.90.136;64.68.90.137;64.68.90.138;64.68.90.139;64.68.90.14;64.68.90.140;64.68.90.141;64.68.90.142;64.68.90.143;64.68.90.144;64.68.90.145;64.68.90.146;64.68.90.147;64.68.90.148;64.68.90.149;64.68.90.15;64.68.90.150;64.68.90.151;64.68.90.152;64.68.90.153;64.68.90.154;64.68.90.155;64.68.90.156;64.68.90.157;64.68.90.158;64.68.90.159;64.68.90.16;64.68.90.160;64.68.90.161;64.68.90.162;64.68.90.163;64.68.90.164;64.68.90.165;64.68.90.166;64.68.90.167;64.68.90.168;64.68.90.169;64.68.90.17;64.68.90.170;64.68.90.171;64.68.90.172;64.68.90.173;64.68.90.174;64.68.90.175;64.68.90.176;64.68.90.177;64.68.90.178;64.68.90.179;64.68.90.18;64.68.90.180;64.68.90.181;64.68.90.182;64.68.90.183;64.68.90.184;64.68.90.185;64.68.90.186;64.68.90.187;64.68.90.188;64.68.90.189;64.68.90.19;64.68.90.190;64.68.90.191;64.68.90.192;64.68.90.193;64.68.90.194;64.68.90.195;64.68.90.196;64.68.90.197;64.68.90.198;64.68.90.199;64.68.90.2;64.68.90.20;64.68.90.200;64.68.90.201;64.68.90.202;64.68.90.203;64.68.90.204;64.68.90.205;64.68.90.206;64.68.90.207;64.68.90.208;64.68.90.21;64.68.90.22;64.68.90.23;64.68.90.24;64.68.90.25;64.68.90.26;64.68.90.27;64.68.90.28;64.68.90.29;64.68.90.3;64.68.90.30;64.68.90.31;64.68.90.32;64.68.90.33;64.68.90.34;64.68.90.35;64.68.90.36;64.68.90.37;64.68.90.38;64.68.90.39;64.68.90.4;64.68.90.40;64.68.90.41;64.68.90.42;64.68.90.43;64.68.90.44;64.68.90.45;64.68.90.46;64.68.90.47;64.68.90.48;64.68.90.49;64.68.90.5;64.68.90.50;64.68.90.51;64.68.90.52;64.68.90.53;64.68.90.54;64.68.90.55;64.68.90.56;64.68.90.57;64.68.90.58;64.68.90.59;64.68.90.6;64.68.90.60;64.68.90.61;64.68.90.62;64.68.90.63;64.68.90.64;64.68.90.65;64.68.90.66;64.68.90.67;64.68.90.68;64.68.90.69;64.68.90.7;64.68.90.70;64.68.90.71;64.68.90.72;64.68.90.73;64.68.90.74;64.68.90.75;64.68.90.76;64.68.90.77;64.68.90.78;64.68.90.79;64.68.90.8;64.68.90.80;64.68.90.9;64.68.91;64.68.92;66.249.64;66.249.65;66.249.66;66.249.67;66.249.68;66.249.69;66.249.70;66.249.71;66.249.72;66.249.73;66.249.78;66.249.79;72.14.199;8.6.48','0000-00-00 00:00:00','0000-00-00 00:00:00'),(132,NULL,NULL,'PS_LOGS_BY_EMAIL','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(133,NULL,NULL,'PS_COOKIE_CHECKIP','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(134,NULL,NULL,'PS_STORES_CENTER_LAT','25.948969','0000-00-00 00:00:00','0000-00-00 00:00:00'),(135,NULL,NULL,'PS_STORES_CENTER_LONG','-80.226439','0000-00-00 00:00:00','0000-00-00 00:00:00'),(136,NULL,NULL,'PS_USE_ECOTAX','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(137,NULL,NULL,'PS_CANONICAL_REDIRECT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(138,NULL,NULL,'PS_IMG_UPDATE_TIME','1324977642','0000-00-00 00:00:00','0000-00-00 00:00:00'),(139,NULL,NULL,'PS_BACKUP_DROP_TABLE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(140,NULL,NULL,'PS_OS_AWAITING_PAYMENT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(141,NULL,NULL,'PS_OS_PAYMENT_ACCEPTED','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(142,NULL,NULL,'PS_OS_PROCESSING','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(143,NULL,NULL,'PS_OS_CANCELED','4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(144,NULL,NULL,'PS_OS_REFUND','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(145,NULL,NULL,'PS_OS_ERROR','6','0000-00-00 00:00:00','0000-00-00 00:00:00'),(146,NULL,NULL,'PS_OS_OUTOFSTOCK','7','0000-00-00 00:00:00','0000-00-00 00:00:00'),(147,NULL,NULL,'PS_OS_OUTOFSTOCK_PAID','7','0000-00-00 00:00:00','0000-00-00 00:00:00'),(148,NULL,NULL,'PS_OS_OVERBOOKING_PAID','7','0000-00-00 00:00:00','0000-00-00 00:00:00'),(149,NULL,NULL,'PS_OS_PARTIAL_PAYMENT_ACCEPTED','8','0000-00-00 00:00:00','0000-00-00 00:00:00'),(150,NULL,NULL,'PS_OS_AWAITING_REMOTE_PAYMENT','9','0000-00-00 00:00:00','0000-00-00 00:00:00'),(151,NULL,NULL,'PS_OS_WS_PAYMENT','10','0000-00-00 00:00:00','0000-00-00 00:00:00'),(152,NULL,NULL,'PS_OS_REMOTE_PAYMENT_ACCEPTED','10','0000-00-00 00:00:00','0000-00-00 00:00:00'),(153,NULL,NULL,'PS_OS_OUTOFSTOCK_UNPAID','11','0000-00-00 00:00:00','0000-00-00 00:00:00'),(154,NULL,NULL,'PS_OS_OVERBOOKING_UNPAID','11','0000-00-00 00:00:00','0000-00-00 00:00:00'),(155,NULL,NULL,'PS_OS_OVERBOOKING_PARTIAL_PAID','12','0000-00-00 00:00:00','0000-00-00 00:00:00'),(156,NULL,NULL,'PS_ORS_PENDING','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(157,NULL,NULL,'PS_ORS_DENIED','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(158,NULL,NULL,'PS_ORS_REFUNDED','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(159,NULL,NULL,'PS_LEGACY_IMAGES','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(160,NULL,NULL,'PS_IMAGE_QUALITY','png','0000-00-00 00:00:00','0000-00-00 00:00:00'),(161,NULL,NULL,'PS_PNG_QUALITY','7','0000-00-00 00:00:00','0000-00-00 00:00:00'),(162,NULL,NULL,'PS_JPEG_QUALITY','90','0000-00-00 00:00:00','0000-00-00 00:00:00'),(163,NULL,NULL,'PS_COOKIE_LIFETIME_FO','480','0000-00-00 00:00:00','0000-00-00 00:00:00'),(164,NULL,NULL,'PS_COOKIE_LIFETIME_BO','480','0000-00-00 00:00:00','0000-00-00 00:00:00'),(165,NULL,NULL,'PS_COOKIE_SAMESITE','Lax','0000-00-00 00:00:00','0000-00-00 00:00:00'),(166,NULL,NULL,'PS_RESTRICT_DELIVERED_COUNTRIES','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(167,NULL,NULL,'PS_SHOW_NEW_ORDERS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(168,NULL,NULL,'PS_SHOW_NEW_CUSTOMERS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(169,NULL,NULL,'PS_SHOW_NEW_MESSAGES','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(170,NULL,NULL,'PS_FEATURE_FEATURE_ACTIVE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(171,NULL,NULL,'PS_COMBINATION_FEATURE_ACTIVE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(172,NULL,NULL,'PS_SPECIFIC_PRICE_FEATURE_ACTIVE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(173,NULL,NULL,'PS_SCENE_FEATURE_ACTIVE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(174,NULL,NULL,'PS_VIRTUAL_PROD_FEATURE_ACTIVE','1','0000-00-00 00:00:00','2026-09-03 18:48:55'),(175,NULL,NULL,'PS_CUSTOMIZATION_FEATURE_ACTIVE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(176,NULL,NULL,'PS_CART_RULE_FEATURE_ACTIVE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(177,NULL,NULL,'PS_PACK_FEATURE_ACTIVE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(178,NULL,NULL,'PS_ALIAS_FEATURE_ACTIVE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(179,NULL,NULL,'PS_TAX_ADDRESS_TYPE','id_address_invoice','0000-00-00 00:00:00','0000-00-00 00:00:00'),(180,NULL,NULL,'PS_SHOP_DEFAULT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(181,NULL,NULL,'PS_CARRIER_DEFAULT_SORT','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(182,NULL,NULL,'PS_STOCK_MVT_INC_REASON_DEFAULT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(183,NULL,NULL,'PS_STOCK_MVT_DEC_REASON_DEFAULT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(184,NULL,NULL,'PS_ADVANCED_STOCK_MANAGEMENT','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(185,NULL,NULL,'PS_ADMINREFRESH_NOTIFICATION','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(186,NULL,NULL,'PS_STOCK_MVT_TRANSFER_TO','7','0000-00-00 00:00:00','0000-00-00 00:00:00'),(187,NULL,NULL,'PS_STOCK_MVT_TRANSFER_FROM','6','0000-00-00 00:00:00','0000-00-00 00:00:00'),(188,NULL,NULL,'PS_CARRIER_DEFAULT_ORDER','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(189,NULL,NULL,'PS_STOCK_MVT_SUPPLY_ORDER','8','0000-00-00 00:00:00','0000-00-00 00:00:00'),(190,NULL,NULL,'PS_STOCK_CUSTOMER_ORDER_REASON','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(191,NULL,NULL,'PS_UNIDENTIFIED_GROUP','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(192,NULL,NULL,'PS_GUEST_GROUP','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(193,NULL,NULL,'PS_CUSTOMER_GROUP','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(194,NULL,NULL,'PS_SMARTY_CONSOLE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(195,NULL,NULL,'PS_INVOICE_MODEL','invoice','0000-00-00 00:00:00','0000-00-00 00:00:00'),(196,NULL,NULL,'PS_LIMIT_UPLOAD_IMAGE_VALUE','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(197,NULL,NULL,'PS_LIMIT_UPLOAD_FILE_VALUE','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(198,NULL,NULL,'MB_PAY_TO_EMAIL','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(199,NULL,NULL,'MB_SECRET_WORD','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(200,NULL,NULL,'MB_HIDE_LOGIN','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(201,NULL,NULL,'MB_ID_LOGO','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(202,NULL,NULL,'MB_ID_LOGO_WALLET','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(203,NULL,NULL,'MB_PARAMETERS','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(204,NULL,NULL,'MB_PARAMETERS_2','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(205,NULL,NULL,'MB_DISPLAY_MODE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(206,NULL,NULL,'MB_CANCEL_URL','http://www.yoursite.com','0000-00-00 00:00:00','0000-00-00 00:00:00'),(207,NULL,NULL,'MB_LOCAL_METHODS','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(208,NULL,NULL,'MB_INTER_METHODS','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(209,NULL,NULL,'BANK_WIRE_CURRENCIES','2,1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(210,NULL,NULL,'CHEQUE_CURRENCIES','2,1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(211,NULL,NULL,'PRODUCTS_VIEWED_NBR','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(212,NULL,NULL,'BLOCK_CATEG_DHTML','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(213,NULL,NULL,'BLOCK_CATEG_MAX_DEPTH','4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(214,NULL,NULL,'MANUFACTURER_DISPLAY_FORM','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(215,NULL,NULL,'MANUFACTURER_DISPLAY_TEXT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(216,NULL,NULL,'MANUFACTURER_DISPLAY_TEXT_NB','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(217,NULL,NULL,'NEW_PRODUCTS_NBR','8','0000-00-00 00:00:00','0000-00-00 00:00:00'),(218,NULL,NULL,'PS_TOKEN_ENABLE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(219,NULL,NULL,'PS_STATS_RENDER','graphnvd3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(220,NULL,NULL,'PS_STATS_OLD_CONNECT_AUTO_CLEAN','never','0000-00-00 00:00:00','0000-00-00 00:00:00'),(221,NULL,NULL,'PS_STATS_GRID_RENDER','gridhtml','0000-00-00 00:00:00','0000-00-00 00:00:00'),(222,NULL,NULL,'BLOCKTAGS_NBR','10','0000-00-00 00:00:00','0000-00-00 00:00:00'),(223,NULL,NULL,'HOTEL_CHECKUP_DESCRIPTIONS_LT','100','0000-00-00 00:00:00','0000-00-00 00:00:00'),(224,NULL,NULL,'HOTEL_CHECKUP_DESCRIPTIONS_GT','400','0000-00-00 00:00:00','0000-00-00 00:00:00'),(225,NULL,NULL,'HOTEL_CHECKUP_IMAGES_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(226,NULL,NULL,'HOTEL_CHECKUP_IMAGES_GT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(227,NULL,NULL,'HOTEL_CHECKUP_ORDERS_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(228,NULL,NULL,'HOTEL_CHECKUP_ORDERS_GT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(229,NULL,NULL,'HOTEL_CHECKUP_TOTAL_ROOMS_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(230,NULL,NULL,'HOTEL_CHECKUP_TOTAL_ROOMS_GT','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(231,NULL,NULL,'ROOM_TYPE_CHECKUP_DESCRIPTIONS_LT','100','0000-00-00 00:00:00','0000-00-00 00:00:00'),(232,NULL,NULL,'ROOM_TYPE_CHECKUP_DESCRIPTIONS_GT','400','0000-00-00 00:00:00','0000-00-00 00:00:00'),(233,NULL,NULL,'ROOM_TYPE_CHECKUP_IMAGES_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(234,NULL,NULL,'ROOM_TYPE_CHECKUP_IMAGES_GT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(235,NULL,NULL,'ROOM_TYPE_CHECKUP_ORDERS_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(236,NULL,NULL,'ROOM_TYPE_CHECKUP_ORDERS_GT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(237,NULL,NULL,'ROOM_TYPE_CHECKUP_TOTAL_ROOMS_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(238,NULL,NULL,'ROOM_TYPE_CHECKUP_TOTAL_ROOMS_GT','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(239,NULL,NULL,'SERVICE_CHECKUP_DESCRIPTIONS_SHORT_LT','50','0000-00-00 00:00:00','0000-00-00 00:00:00'),(240,NULL,NULL,'SERVICE_CHECKUP_DESCRIPTIONS_GT','150','0000-00-00 00:00:00','0000-00-00 00:00:00'),(241,NULL,NULL,'SERVICE_CHECKUP_IMAGES_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(242,NULL,NULL,'SERVICE_CHECKUP_IMAGES_GT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(243,NULL,NULL,'SERVICE_CHECKUP_ORDERS_LT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(244,NULL,NULL,'SERVICE_CHECKUP_ORDERS_GT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(245,NULL,NULL,'FOOTER_CMS','0_3|0_4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(246,NULL,NULL,'FOOTER_BLOCK_ACTIVATION','0_3|0_4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(247,NULL,NULL,'FOOTER_POWEREDBY','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(248,NULL,NULL,'BLOCKADVERT_LINK','https://qloapps.com/','0000-00-00 00:00:00','0000-00-00 00:00:00'),(249,NULL,NULL,'BLOCKADVERT_IMG_EXT','jpg','0000-00-00 00:00:00','0000-00-00 00:00:00'),(250,NULL,NULL,'MOD_BLOCKTOPMENU_ITEMS','CAT3,CAT8,CAT5,LNK1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(251,NULL,NULL,'MOD_BLOCKTOPMENU_SEARCH','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(252,NULL,NULL,'BLOCKSOCIAL_FACEBOOK','https://www.facebook.com/qloapps','0000-00-00 00:00:00','2026-09-03 18:49:58'),(253,NULL,NULL,'BLOCKSOCIAL_TWITTER','https://twitter.com/qloapps','0000-00-00 00:00:00','2026-09-03 18:49:58'),(254,NULL,NULL,'SUPPLIER_DISPLAY_TEXT','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(255,NULL,NULL,'SUPPLIER_DISPLAY_TEXT_NB','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(256,NULL,NULL,'SUPPLIER_DISPLAY_FORM','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(257,NULL,NULL,'BLOCK_CATEG_NBR_COLUMN_FOOTER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(258,NULL,NULL,'UPGRADER_BACKUPDB_FILENAME','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(259,NULL,NULL,'UPGRADER_BACKUPFILES_FILENAME','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(260,NULL,NULL,'BLOCKREINSURANCE_NBBLOCKS','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(261,NULL,NULL,'PS_BASE_DISTANCE_UNIT','m','0000-00-00 00:00:00','0000-00-00 00:00:00'),(262,NULL,NULL,'PS_SHOP_DOMAIN','localhost','0000-00-00 00:00:00','0000-00-00 00:00:00'),(263,NULL,NULL,'PS_SHOP_DOMAIN_SSL','localhost','0000-00-00 00:00:00','0000-00-00 00:00:00'),(264,NULL,NULL,'PS_SHOP_NAME','Hotel animate','0000-00-00 00:00:00','2026-09-03 18:46:26'),(265,NULL,NULL,'PS_SHOP_EMAIL','hotelprime@htl.com','0000-00-00 00:00:00','2026-09-03 18:48:12'),(266,NULL,NULL,'PS_MAIL_METHOD','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(267,NULL,NULL,'PS_MAIL_SUBJECT_PREFIX','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(268,NULL,NULL,'PS_SHOP_ACTIVITY',NULL,'0000-00-00 00:00:00','2026-09-03 18:46:26'),(269,NULL,NULL,'PS_LOGO','logo.jpg','0000-00-00 00:00:00','2026-09-03 18:48:13'),(270,NULL,NULL,'PS_FAVICON','favicon.ico','0000-00-00 00:00:00','0000-00-00 00:00:00'),(271,NULL,NULL,'PS_STORES_ICON','logo_stores.gif','0000-00-00 00:00:00','2026-09-03 18:48:13'),(272,NULL,NULL,'PS_ROOT_CATEGORY','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(273,NULL,NULL,'PS_HOME_CATEGORY','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(274,NULL,NULL,'PS_LOCATIONS_CATEGORY','3','0000-00-00 00:00:00','0000-00-00 00:00:00'),(275,NULL,NULL,'PS_SERVICE_CATEGORY','4','0000-00-00 00:00:00','0000-00-00 00:00:00'),(276,NULL,NULL,'PS_CONFIGURATION_AGREMENT','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(277,NULL,NULL,'PS_MAIL_SERVER','smtp.','0000-00-00 00:00:00','0000-00-00 00:00:00'),(278,NULL,NULL,'PS_MAIL_USER','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(279,NULL,NULL,'PS_MAIL_PASSWD','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(280,NULL,NULL,'PS_MAIL_SMTP_ENCRYPTION','off','0000-00-00 00:00:00','0000-00-00 00:00:00'),(281,NULL,NULL,'PS_MAIL_SMTP_PORT','25','0000-00-00 00:00:00','0000-00-00 00:00:00'),(282,NULL,NULL,'PS_MAIL_COLOR','#db3484','0000-00-00 00:00:00','0000-00-00 00:00:00'),(283,NULL,NULL,'NW_SALT','gt38mWHIi3xqpBgk','0000-00-00 00:00:00','2026-09-03 18:49:57'),(284,NULL,NULL,'PS_PAYMENT_LOGO_CMS_ID','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(285,NULL,NULL,'HOME_FEATURED_NBR','8','0000-00-00 00:00:00','0000-00-00 00:00:00'),(286,NULL,NULL,'SEK_MIN_OCCURENCES','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(287,NULL,NULL,'SEK_FILTER_KW','','0000-00-00 00:00:00','0000-00-00 00:00:00'),(288,NULL,NULL,'PS_ALLOW_MOBILE_DEVICE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(289,NULL,NULL,'PS_CUSTOMER_CREATION_EMAIL','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(290,NULL,NULL,'PS_SMARTY_CONSOLE_KEY','SMARTY_DEBUG','0000-00-00 00:00:00','0000-00-00 00:00:00'),(291,NULL,NULL,'PS_DASHBOARD_USE_PUSH','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(292,NULL,NULL,'PS_ATTRIBUTE_ANCHOR_SEPARATOR','-','0000-00-00 00:00:00','0000-00-00 00:00:00'),(293,NULL,NULL,'CONF_AVERAGE_PRODUCT_MARGIN','40','0000-00-00 00:00:00','0000-00-00 00:00:00'),(294,NULL,NULL,'PS_DASHBOARD_SIMULATION','0','0000-00-00 00:00:00','2026-09-03 19:02:40'),(295,NULL,NULL,'PS_QUICK_VIEW','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(296,NULL,NULL,'PS_USE_HTMLPURIFIER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(297,NULL,NULL,'PS_SMARTY_CACHING_TYPE','filesystem','0000-00-00 00:00:00','0000-00-00 00:00:00'),(298,NULL,NULL,'PS_SMARTY_CLEAR_CACHE','everytime','0000-00-00 00:00:00','0000-00-00 00:00:00'),(299,NULL,NULL,'PS_DETECT_LANG','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(300,NULL,NULL,'PS_DETECT_COUNTRY','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(301,NULL,NULL,'PS_ROUND_TYPE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(302,NULL,NULL,'PS_PRICE_DISPLAY_PRECISION','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(303,NULL,NULL,'PS_LOG_EMAILS','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(304,NULL,NULL,'PS_CUSTOMER_BIRTHDATE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(305,NULL,NULL,'PS_CUSTOMER_NWSL','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(306,NULL,NULL,'PS_CUSTOMER_OPTIN','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(307,NULL,NULL,'PS_KPI_FREQUENT_CUSTOMER_NB_ORDERS','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(308,NULL,NULL,'PS_KPI_REVPAC_NB_DAYS','30','0000-00-00 00:00:00','0000-00-00 00:00:00'),(309,NULL,NULL,'PS_KPI_CONVERSION_RATE_NB_DAYS','30','0000-00-00 00:00:00','0000-00-00 00:00:00'),(310,NULL,NULL,'PS_ORDER_KPI_AVG_ORDER_VALUE_NB_DAYS','30','0000-00-00 00:00:00','0000-00-00 00:00:00'),(311,NULL,NULL,'PS_ORDER_KPI_PER_VISITOR_PROFIT_NB_DAYS','30','0000-00-00 00:00:00','0000-00-00 00:00:00'),(312,NULL,NULL,'PS_KPI_NEW_CUSTOMERS_NB_DAYS','30','0000-00-00 00:00:00','0000-00-00 00:00:00'),(313,NULL,NULL,'PS_PACK_STOCK_TYPE','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(314,NULL,NULL,'PS_LOG_MODULE_PERFS_MODULO','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(315,NULL,NULL,'PS_DISALLOW_HISTORY_REORDERING','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(316,NULL,NULL,'PS_DISPLAY_PRODUCT_WEIGHT','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(317,NULL,NULL,'PS_PRODUCT_WEIGHT_PRECISION','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(318,NULL,NULL,'PS_ADVANCED_PAYMENT_API','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(319,NULL,NULL,'PS_SERVICE_PRODUCT_CATEGORY_FILTER','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(320,NULL,NULL,'PS_BACKDATE_ORDER_SUPERADMIN','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(321,NULL,NULL,'PS_BACKDATE_ORDER_EMPLOYEES','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(322,NULL,NULL,'PS_CUSTOMER_SERVICE_REQUIRED_NAME','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(323,NULL,NULL,'PS_CUSTOMER_SERVICE_DISPLAY_NAME','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(324,NULL,NULL,'PS_CUSTOMER_SERVICE_REQUIRED_PHONE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(325,NULL,NULL,'PS_CUSTOMER_SERVICE_DISPLAY_PHONE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(326,NULL,NULL,'PS_CUSTOMER_SERVICE_DISPLAY_CONTACT','0','0000-00-00 00:00:00','0000-00-00 00:00:00'),(327,NULL,NULL,'PS_CUSTOMER_SERVICE_CONTACT','2','0000-00-00 00:00:00','0000-00-00 00:00:00'),(328,NULL,NULL,'PS_STANDARD_PRODUCT_ORDER_ADDRESS_PREFRENCE','1','0000-00-00 00:00:00','0000-00-00 00:00:00'),(329,NULL,NULL,'PS_CUSTOMER_GUEST_MAX_LIMIT','5','0000-00-00 00:00:00','0000-00-00 00:00:00'),(330,NULL,NULL,'PS_SSL_ENABLED_EVERYWHERE','0','2026-09-03 18:46:26','2026-09-03 18:46:26'),(331,NULL,NULL,'WK_HOTEL_LOCATION_ENABLE','0','2026-09-03 18:48:12','2026-09-03 18:48:12'),(332,NULL,NULL,'WK_HOTEL_NAME_ENABLE','0','2026-09-03 18:48:12','2026-09-03 18:48:12'),(333,NULL,NULL,'WK_HOTEL_NAME_SEARCH_THRESHOLD','5','2026-09-03 18:48:12','2026-09-03 18:48:12'),(334,NULL,NULL,'WK_SEARCH_AUTO_FOCUS_NEXT_FIELD','1','2026-09-03 18:48:12','2026-09-03 18:48:12'),(335,NULL,NULL,'WK_ROOM_LEFT_WARNING_NUMBER','10','2026-09-03 18:48:12','2026-09-03 18:48:12'),(336,NULL,NULL,'WK_HTL_ESTABLISHMENT_YEAR','2010','2026-09-03 18:48:12','2026-09-03 18:48:12'),(337,NULL,NULL,'PS_SHOP_ADDR1','The Hotel Prime, Monticello Dr, Montgomery, 10010','2026-09-03 18:48:12','2026-09-03 18:48:12'),(338,NULL,NULL,'PS_SHOP_PHONE','0987654321','2026-09-03 18:48:12','2026-09-03 18:48:12'),(339,NULL,NULL,'WK_CUSTOMER_SUPPORT_PHONE_NUMBER','0987654321','2026-09-03 18:48:12','2026-09-03 18:48:12'),(340,NULL,NULL,'WK_CUSTOMER_SUPPORT_EMAIL','hotelprime@htl.com','2026-09-03 18:48:12','2026-09-03 18:48:12'),(341,NULL,NULL,'WK_DISPLAY_CONTACT_PAGE_HOTEL_LIST','0','2026-09-03 18:48:12','2026-09-03 18:48:12'),(342,NULL,NULL,'WK_TITLE_HEADER_BLOCK','Hotel Prime','2026-09-03 18:48:12','2026-09-03 18:48:12'),(343,NULL,NULL,'WK_CONTENT_HEADER_BLOCK','Our hotel is the perfect destination for both business and leisure travelers seeking a memorable stay.','2026-09-03 18:48:13','2026-09-03 18:48:13'),(344,NULL,NULL,'WK_HOTEL_HEADER_IMAGE','hotel_header_image.jpg','2026-09-03 18:48:13','2026-09-03 18:48:13'),(345,NULL,NULL,'WK_ALLOW_ADVANCED_PAYMENT','1','2026-09-03 18:48:13','2026-09-03 18:48:13'),(346,NULL,NULL,'WK_ADVANCED_PAYMENT_GLOBAL_MIN_AMOUNT','10','2026-09-03 18:48:13','2026-09-03 18:48:13'),(347,NULL,NULL,'WK_ADVANCED_PAYMENT_INC_TAX','1','2026-09-03 18:48:13','2026-09-03 18:48:13'),(348,NULL,NULL,'WK_GLOBAL_CHILD_MAX_AGE','15','2026-09-03 18:48:13','2026-09-03 18:48:13'),(349,NULL,NULL,'QLO_GLOBAL_MAX_INFANT_AGE','1','2026-09-03 18:48:13','2026-09-03 18:48:13'),(350,NULL,NULL,'PS_MAX_CHECKOUT_OFFSET','365','2026-09-03 18:48:13','2026-09-03 18:48:13'),(351,NULL,NULL,'PS_MIN_BOOKING_OFFSET','0','2026-09-03 18:48:13','2026-09-03 18:48:13'),(352,NULL,NULL,'HTL_FEATURE_PRICING_PRIORITY','specific_date;special_day;date_range','2026-09-03 18:48:13','2026-09-03 18:48:13'),(353,NULL,NULL,'WK_GOOGLE_ACTIVE_MAP','0','2026-09-03 18:48:13','2026-09-03 18:48:13'),(354,NULL,NULL,'WK_MAP_HOTEL_ACTIVE_ONLY','1','2026-09-03 18:48:13','2026-09-03 18:48:13'),(355,NULL,NULL,'PS_LOGO_MAIL','logo_mail.jpg','2026-09-03 18:48:13','2026-09-03 18:48:13'),(356,NULL,NULL,'PS_LOGO_INVOICE','logo_invoice.jpg','2026-09-03 18:48:13','2026-09-03 18:48:13'),(357,NULL,NULL,'WK_HTL_CHAIN_NAME',NULL,'2026-09-03 18:48:13','2026-09-03 18:48:13'),(358,NULL,NULL,'WK_HTL_TAG_LINE',NULL,'2026-09-03 18:48:13','2026-09-03 18:48:13'),(359,NULL,NULL,'WK_HTL_SHORT_DESC',NULL,'2026-09-03 18:48:13','2026-09-03 18:48:13'),(360,NULL,NULL,'WK_DIMENSION_UNIT',NULL,'2026-09-03 18:48:14','2026-09-03 18:48:14'),(361,NULL,NULL,'PS_FRONT_SEARCH_TYPE','1','2026-09-03 18:48:14','2026-09-03 18:48:14'),(362,NULL,NULL,'PS_FRONT_OWS_SEARCH_ALGO_TYPE','1','2026-09-03 18:48:14','2026-09-03 18:48:14'),(363,NULL,NULL,'PS_FRONT_ROOM_UNIT_SELECTION_TYPE','1','2026-09-03 18:48:14','2026-09-03 18:48:14'),(364,NULL,NULL,'PS_BACKOFFICE_SEARCH_TYPE','1','2026-09-03 18:48:14','2026-09-03 18:48:14'),(365,NULL,NULL,'PS_BACKOFFICE_OWS_SEARCH_ALGO_TYPE','2','2026-09-03 18:48:14','2026-09-03 18:48:14'),(366,NULL,NULL,'PS_BACKOFFICE_ROOM_BOOKING_TYPE','1','2026-09-03 18:48:14','2026-09-03 18:48:14'),(367,NULL,NULL,'WK_PRIMARY_HOTEL','1','2026-09-03 18:48:54','2026-09-03 18:48:54'),(368,NULL,NULL,'BLOCKSOCIAL_RSS',NULL,'2026-09-03 18:49:58','2026-09-03 18:49:58'),(369,NULL,NULL,'BLOCKSOCIAL_YOUTUBE',NULL,'2026-09-03 18:49:58','2026-09-03 18:49:58'),(370,NULL,NULL,'BLOCKSOCIAL_PINTEREST',NULL,'2026-09-03 18:49:58','2026-09-03 18:49:58'),(371,NULL,NULL,'BLOCKSOCIAL_VIMEO',NULL,'2026-09-03 18:49:58','2026-09-03 18:49:58'),(372,NULL,NULL,'BLOCKSOCIAL_INSTAGRAM',NULL,'2026-09-03 18:49:58','2026-09-03 18:49:58'),(373,NULL,NULL,'SHOW_AMENITIES_FILTER','1','2026-09-03 18:49:58','2026-09-03 18:49:58'),(374,NULL,NULL,'SHOW_PRICE_FILTER','1','2026-09-03 18:49:58','2026-09-03 18:49:58'),(375,NULL,NULL,'HOTEL_INTERIOR_HEADING',NULL,'2026-09-03 18:49:59','2026-09-03 18:49:59'),(376,NULL,NULL,'HOTEL_INTERIOR_DESCRIPTION',NULL,'2026-09-03 18:49:59','2026-09-03 18:49:59'),(377,NULL,NULL,'HOTEL_AMENITIES_HEADING',NULL,'2026-09-03 18:50:06','2026-09-03 18:50:06'),(378,NULL,NULL,'HOTEL_AMENITIES_DESCRIPTION',NULL,'2026-09-03 18:50:06','2026-09-03 18:50:06'),(379,NULL,NULL,'HOTEL_ROOM_DISPLAY_HEADING',NULL,'2026-09-03 18:50:09','2026-09-03 18:50:09'),(380,NULL,NULL,'HOTEL_ROOM_DISPLAY_DESCRIPTION',NULL,'2026-09-03 18:50:09','2026-09-03 18:50:09'),(381,NULL,NULL,'HOTEL_TESIMONIAL_BLOCK_HEADING',NULL,'2026-09-03 18:50:11','2026-09-03 18:50:11'),(382,NULL,NULL,'HOTEL_TESIMONIAL_BLOCK_CONTENT',NULL,'2026-09-03 18:50:11','2026-09-03 18:50:11'),(383,NULL,NULL,'CONF_BANKWIRE_FIXED','0.2','2026-09-03 18:50:14','2026-09-03 18:50:14'),(384,NULL,NULL,'CONF_BANKWIRE_VAR','2','2026-09-03 18:50:14','2026-09-03 18:50:14'),(385,NULL,NULL,'CONF_BANKWIRE_FIXED_FOREIGN','0.2','2026-09-03 18:50:14','2026-09-03 18:50:14'),(386,NULL,NULL,'CONF_BANKWIRE_VAR_FOREIGN','2','2026-09-03 18:50:14','2026-09-03 18:50:14'),(387,NULL,NULL,'CONF_CHEQUE_FIXED','0.2','2026-09-03 18:50:14','2026-09-03 18:50:14'),(388,NULL,NULL,'CONF_CHEQUE_VAR','2','2026-09-03 18:50:15','2026-09-03 18:50:15'),(389,NULL,NULL,'CONF_CHEQUE_FIXED_FOREIGN','0.2','2026-09-03 18:50:15','2026-09-03 18:50:15'),(390,NULL,NULL,'CONF_CHEQUE_VAR_FOREIGN','2','2026-09-03 18:50:15','2026-09-03 18:50:15'),(391,NULL,NULL,'QHR_ADMIN_APPROVAL_ENABLED','1','2026-09-03 18:50:19','2026-09-03 18:50:19'),(392,NULL,NULL,'QHR_MAX_IMAGES_PER_REVIEW','5','2026-09-03 18:50:19','2026-09-03 18:50:19'),(393,NULL,NULL,'QHR_REVIEWS_AT_ONCE','5','2026-09-03 18:50:19','2026-09-03 18:50:19'),(394,NULL,NULL,'QHR_REVIEW_APPROVAL_EMAIL_ENABLED','0','2026-09-03 18:50:19','2026-09-03 18:50:19'),(395,NULL,NULL,'QHR_REVIEW_MGMT_REPLY_EMAIL_ENABLED','0','2026-09-03 18:50:19','2026-09-03 18:50:19'),(396,NULL,NULL,'WK_SHOW_FOOTER_NAVIGATION_BLOCK','1','2026-09-03 18:50:24','2026-09-03 18:50:24'),(397,NULL,NULL,'DASHACTIVITY_CART_ACTIVE','30','2026-09-03 18:50:26','2026-09-03 18:50:26'),(398,NULL,NULL,'DASHACTIVITY_CART_ABANDONED_MIN','24','2026-09-03 18:50:27','2026-09-03 18:50:27'),(399,NULL,NULL,'DASHACTIVITY_CART_ABANDONED_MAX','48','2026-09-03 18:50:27','2026-09-03 18:50:27'),(400,NULL,NULL,'DASHACTIVITY_VISITOR_ONLINE','30','2026-09-03 18:50:27','2026-09-03 18:50:27'),(401,NULL,NULL,'DASHPRODUCT_NBR_SHOW_LAST_ORDER','10','2026-09-03 18:50:29','2026-09-03 18:50:29'),(402,NULL,NULL,'DASHPRODUCT_NBR_SHOW_BEST_SELLER','10','2026-09-03 18:50:29','2026-09-03 18:50:29'),(403,NULL,NULL,'DASHPRODUCT_NBR_SHOW_MOST_VIEWED','10','2026-09-03 18:50:29','2026-09-03 18:50:29'),(404,NULL,NULL,'DASHPRODUCT_NBR_SHOW_TOP_SEARCH','10','2026-09-03 18:50:29','2026-09-03 18:50:29'),(405,NULL,NULL,'PS_DASHGOALS_CURRENT_YEAR','2026','2026-09-03 18:50:29','2026-09-03 18:50:29'),(406,NULL,NULL,'PS_BLOCK_CART_XSELL_LIMIT','12','2026-09-03 18:50:42','2026-09-03 18:50:42'),(407,NULL,NULL,'PS_BLOCK_CART_SHOW_CROSSSELLING','1','2026-09-03 18:50:42','2026-09-03 18:50:42');
/*!40000 ALTER TABLE `qlo_configuration` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_configuration_kpi`
--

DROP TABLE IF EXISTS `qlo_configuration_kpi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_configuration_kpi` (
  `id_configuration_kpi` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop_group` int unsigned DEFAULT NULL,
  `id_shop` int unsigned DEFAULT NULL,
  `name` varchar(64) NOT NULL,
  `value` text,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_configuration_kpi`),
  KEY `name` (`name`),
  KEY `id_shop` (`id_shop`),
  KEY `id_shop_group` (`id_shop_group`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_configuration_kpi`
--

LOCK TABLES `qlo_configuration_kpi` WRITE;
/*!40000 ALTER TABLE `qlo_configuration_kpi` DISABLE KEYS */;
INSERT INTO `qlo_configuration_kpi` VALUES (1,NULL,NULL,'DASHGOALS_TRAFFIC_01_2026','600','2026-09-03 18:50:29','2026-09-03 18:50:29'),(2,NULL,NULL,'DASHGOALS_CONVERSION_01_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(3,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_01_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(4,NULL,NULL,'DASHGOALS_TRAFFIC_02_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(5,NULL,NULL,'DASHGOALS_CONVERSION_02_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(6,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_02_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(7,NULL,NULL,'DASHGOALS_TRAFFIC_03_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(8,NULL,NULL,'DASHGOALS_CONVERSION_03_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(9,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_03_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(10,NULL,NULL,'DASHGOALS_TRAFFIC_04_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(11,NULL,NULL,'DASHGOALS_CONVERSION_04_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(12,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_04_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(13,NULL,NULL,'DASHGOALS_TRAFFIC_05_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(14,NULL,NULL,'DASHGOALS_CONVERSION_05_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(15,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_05_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(16,NULL,NULL,'DASHGOALS_TRAFFIC_06_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(17,NULL,NULL,'DASHGOALS_CONVERSION_06_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(18,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_06_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(19,NULL,NULL,'DASHGOALS_TRAFFIC_07_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(20,NULL,NULL,'DASHGOALS_CONVERSION_07_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(21,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_07_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(22,NULL,NULL,'DASHGOALS_TRAFFIC_08_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(23,NULL,NULL,'DASHGOALS_CONVERSION_08_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(24,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_08_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(25,NULL,NULL,'DASHGOALS_TRAFFIC_09_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(26,NULL,NULL,'DASHGOALS_CONVERSION_09_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(27,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_09_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(28,NULL,NULL,'DASHGOALS_TRAFFIC_10_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(29,NULL,NULL,'DASHGOALS_CONVERSION_10_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(30,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_10_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(31,NULL,NULL,'DASHGOALS_TRAFFIC_11_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(32,NULL,NULL,'DASHGOALS_CONVERSION_11_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(33,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_11_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30'),(34,NULL,NULL,'DASHGOALS_TRAFFIC_12_2026','600','2026-09-03 18:50:30','2026-09-03 18:50:30'),(35,NULL,NULL,'DASHGOALS_CONVERSION_12_2026','2','2026-09-03 18:50:30','2026-09-03 18:50:30'),(36,NULL,NULL,'DASHGOALS_AVG_CART_VALUE_12_2026','80','2026-09-03 18:50:30','2026-09-03 18:50:30');
/*!40000 ALTER TABLE `qlo_configuration_kpi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_configuration_kpi_lang`
--

DROP TABLE IF EXISTS `qlo_configuration_kpi_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_configuration_kpi_lang` (
  `id_configuration_kpi` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `value` text,
  `date_upd` datetime DEFAULT NULL,
  PRIMARY KEY (`id_configuration_kpi`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_configuration_kpi_lang`
--

LOCK TABLES `qlo_configuration_kpi_lang` WRITE;
/*!40000 ALTER TABLE `qlo_configuration_kpi_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_configuration_kpi_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_configuration_lang`
--

DROP TABLE IF EXISTS `qlo_configuration_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_configuration_lang` (
  `id_configuration` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `value` text,
  `date_upd` datetime DEFAULT NULL,
  PRIMARY KEY (`id_configuration`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_configuration_lang`
--

LOCK TABLES `qlo_configuration_lang` WRITE;
/*!40000 ALTER TABLE `qlo_configuration_lang` DISABLE KEYS */;
INSERT INTO `qlo_configuration_lang` VALUES (48,1,'#IN',NULL),(49,1,'#IN',NULL),(52,1,'#DE',NULL),(54,1,'#RE',NULL),(61,1,'a|about|above|after|again|against|all|am|an|and|any|are|aren|as|at|be|because|been|before|being|below|between|both|but|by|can|cannot|could|couldn|did|didn|do|does|doesn|doing|don|down|during|each|few|for|from|further|had|hadn|has|hasn|have|haven|having|he|ll|her|here|hers|herself|him|himself|his|how|ve|if|in|into|is|isn|it|its|itself|let|me|more|most|mustn|my|myself|no|nor|not|of|off|on|once|only|or|other|ought|our|ours|ourselves|out|over|own|same|shan|she|should|shouldn|so|some|such|than|that|the|their|theirs|them|themselves|then|there|these|they|re|this|those|through|to|too|under|until|up|very|was|wasn|we|were|weren|what|when|where|which|while|who|whom|why|with|won|would|wouldn|you|your|yours|yourself|yourselves',NULL),(86,1,'0',NULL),(92,1,'Dear Customer,\r\n\r\nRegards,\r\nCustomer service',NULL),(357,1,'Hotel Prime','2026-09-03 18:48:13'),(358,1,'A place where comfort and luxury are blended with nature!','2026-09-03 18:48:13'),(359,1,'We offer elegant rooms, gourmet dining, and attentive service for a memorable stay.','2026-09-03 18:48:14'),(360,1,'ft','2026-09-03 18:48:14'),(375,1,'Explore the Interiors!','2026-09-03 18:49:59'),(376,1,'Step into the sophisticated elegance of our hotel, where every detail is designed with your comfort in mind.','2026-09-03 18:49:59'),(377,1,'Amenities','2026-09-03 18:50:06'),(378,1,'Experience luxury at our hotel with top-notch amenities. Enjoy our fitness center, rejuvenating spa, serene outdoor pool, and exquisite dining.','2026-09-03 18:50:06'),(379,1,'Our Rooms','2026-09-03 18:50:09'),(380,1,'Relax in the comfort of our rooms. With modern amenities, serene decor, and stunning lake or city views, each room offers a peaceful retreat for your stay.','2026-09-03 18:50:10'),(381,1,'What our guests say?','2026-09-03 18:50:11'),(382,1,'Here are some valuable feedbacks from our guests.','2026-09-03 18:50:11');
/*!40000 ALTER TABLE `qlo_configuration_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_connections`
--

DROP TABLE IF EXISTS `qlo_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_connections` (
  `id_connections` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_guest` int unsigned NOT NULL,
  `id_page` int unsigned NOT NULL,
  `ip_address` bigint DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `http_referer` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_connections`),
  KEY `id_guest` (`id_guest`),
  KEY `date_add` (`date_add`),
  KEY `id_page` (`id_page`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_connections`
--

LOCK TABLES `qlo_connections` WRITE;
/*!40000 ALTER TABLE `qlo_connections` DISABLE KEYS */;
INSERT INTO `qlo_connections` VALUES (1,1,1,1,1,2130706433,'2026-09-03 18:47:11','https://www.qloapps.com'),(2,1,1,2,1,2886860801,'2026-09-03 18:59:25','');
/*!40000 ALTER TABLE `qlo_connections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_connections_page`
--

DROP TABLE IF EXISTS `qlo_connections_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_connections_page` (
  `id_connections` int unsigned NOT NULL,
  `id_page` int unsigned NOT NULL,
  `time_start` datetime NOT NULL,
  `time_end` datetime DEFAULT NULL,
  PRIMARY KEY (`id_connections`,`id_page`,`time_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_connections_page`
--

LOCK TABLES `qlo_connections_page` WRITE;
/*!40000 ALTER TABLE `qlo_connections_page` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_connections_page` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_connections_source`
--

DROP TABLE IF EXISTS `qlo_connections_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_connections_source` (
  `id_connections_source` int unsigned NOT NULL AUTO_INCREMENT,
  `id_connections` int unsigned NOT NULL,
  `http_referer` varchar(255) DEFAULT NULL,
  `request_uri` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_connections_source`),
  KEY `connections` (`id_connections`),
  KEY `orderby` (`date_add`),
  KEY `http_referer` (`http_referer`),
  KEY `request_uri` (`request_uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_connections_source`
--

LOCK TABLES `qlo_connections_source` WRITE;
/*!40000 ALTER TABLE `qlo_connections_source` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_connections_source` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_contact`
--

DROP TABLE IF EXISTS `qlo_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_contact` (
  `id_contact` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(128) NOT NULL,
  `customer_service` tinyint(1) NOT NULL DEFAULT '0',
  `position` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_contact`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_contact`
--

LOCK TABLES `qlo_contact` WRITE;
/*!40000 ALTER TABLE `qlo_contact` DISABLE KEYS */;
INSERT INTO `qlo_contact` VALUES (1,'supportanimatesolutions@gmail.com',1,0),(2,'supportanimatesolutions@gmail.com',1,0);
/*!40000 ALTER TABLE `qlo_contact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_contact_lang`
--

DROP TABLE IF EXISTS `qlo_contact_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_contact_lang` (
  `id_contact` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  `description` text,
  PRIMARY KEY (`id_contact`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_contact_lang`
--

LOCK TABLES `qlo_contact_lang` WRITE;
/*!40000 ALTER TABLE `qlo_contact_lang` DISABLE KEYS */;
INSERT INTO `qlo_contact_lang` VALUES (1,1,'Webmaster','If a technical problem occurs on this website'),(2,1,'Customer service','For any query regarding a hotel or a booking');
/*!40000 ALTER TABLE `qlo_contact_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_contact_shop`
--

DROP TABLE IF EXISTS `qlo_contact_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_contact_shop` (
  `id_contact` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_contact`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_contact_shop`
--

LOCK TABLES `qlo_contact_shop` WRITE;
/*!40000 ALTER TABLE `qlo_contact_shop` DISABLE KEYS */;
INSERT INTO `qlo_contact_shop` VALUES (1,1),(2,1);
/*!40000 ALTER TABLE `qlo_contact_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_country`
--

DROP TABLE IF EXISTS `qlo_country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_country` (
  `id_country` int unsigned NOT NULL AUTO_INCREMENT,
  `id_zone` int unsigned NOT NULL,
  `id_currency` int unsigned NOT NULL DEFAULT '0',
  `iso_code` varchar(3) NOT NULL,
  `call_prefix` int NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `contains_states` tinyint(1) NOT NULL DEFAULT '0',
  `need_identification_number` tinyint(1) NOT NULL DEFAULT '0',
  `need_zip_code` tinyint(1) NOT NULL DEFAULT '1',
  `zip_code_format` varchar(12) NOT NULL DEFAULT '',
  `display_tax_label` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_country`),
  KEY `country_iso_code` (`iso_code`),
  KEY `country_` (`id_zone`)
) ENGINE=InnoDB AUTO_INCREMENT=245 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_country`
--

LOCK TABLES `qlo_country` WRITE;
/*!40000 ALTER TABLE `qlo_country` DISABLE KEYS */;
INSERT INTO `qlo_country` VALUES (1,1,0,'DE',49,0,0,0,1,'NNNNN',1),(2,1,0,'AT',43,0,0,0,1,'NNNN',1),(3,1,0,'BE',32,0,0,0,1,'NNNN',1),(4,2,0,'CA',1,0,1,0,1,'LNL NLN',0),(5,3,0,'CN',86,0,0,0,1,'NNNNNN',1),(6,1,0,'ES',34,0,0,1,1,'NNNNN',1),(7,1,0,'FI',358,0,0,0,1,'NNNNN',1),(8,1,0,'FR',33,0,0,0,1,'NNNNN',1),(9,1,0,'GR',30,0,0,0,1,'NNNNN',1),(10,1,0,'IT',39,0,1,0,1,'NNNNN',1),(11,3,0,'JP',81,0,1,0,1,'NNN-NNNN',1),(12,1,0,'LU',352,0,0,0,1,'NNNN',1),(13,1,0,'NL',31,0,0,0,1,'NNNN LL',1),(14,1,0,'PL',48,0,0,0,1,'NN-NNN',1),(15,1,0,'PT',351,0,0,0,1,'NNNN-NNN',1),(16,1,0,'CZ',420,0,0,0,1,'NNN NN',1),(17,1,0,'GB',44,0,0,0,1,'',1),(18,1,0,'SE',46,0,0,0,1,'NNN NN',1),(19,7,0,'CH',41,0,0,0,1,'NNNN',1),(20,1,0,'DK',45,0,0,0,1,'NNNN',1),(21,2,0,'US',1,0,1,0,1,'NNNNN',0),(22,3,0,'HK',852,0,0,0,0,'',1),(23,7,0,'NO',47,0,0,0,1,'NNNN',1),(24,5,0,'AU',61,0,0,0,1,'NNNN',1),(25,3,0,'SG',65,0,0,0,1,'NNNNNN',1),(26,1,0,'IE',353,0,0,0,0,'',1),(27,5,0,'NZ',64,0,0,0,1,'NNNN',1),(28,3,0,'KR',82,0,0,0,1,'NNNNN',1),(29,3,0,'IL',972,0,0,0,1,'NNNNNNN',1),(30,4,0,'ZA',27,0,0,0,1,'NNNN',1),(31,4,0,'NG',234,1,0,0,1,'',1),(32,4,0,'CI',225,0,0,0,1,'',1),(33,4,0,'TG',228,0,0,0,1,'',1),(34,6,0,'BO',591,0,0,0,1,'',1),(35,4,0,'MU',230,0,0,0,1,'',1),(36,1,0,'RO',40,0,0,0,1,'NNNNNN',1),(37,1,0,'SK',421,0,0,0,1,'NNN NN',1),(38,4,0,'DZ',213,0,0,0,1,'NNNNN',1),(39,2,0,'AS',0,0,0,0,1,'',1),(40,7,0,'AD',376,0,0,0,1,'CNNN',1),(41,4,0,'AO',244,0,0,0,0,'',1),(42,8,0,'AI',0,0,0,0,1,'',1),(43,2,0,'AG',0,0,0,0,1,'',1),(44,6,0,'AR',54,0,1,0,1,'LNNNNLLL',1),(45,3,0,'AM',374,0,0,0,1,'NNNN',1),(46,8,0,'AW',297,0,0,0,1,'',1),(47,3,0,'AZ',994,0,0,0,1,'CNNNN',1),(48,2,0,'BS',0,0,0,0,1,'',1),(49,3,0,'BH',973,0,0,0,1,'',1),(50,3,0,'BD',880,0,0,0,1,'NNNN',1),(51,2,0,'BB',0,0,0,0,1,'CNNNNN',1),(52,7,0,'BY',0,0,0,0,1,'NNNNNN',1),(53,8,0,'BZ',501,0,0,0,0,'',1),(54,4,0,'BJ',229,0,0,0,0,'',1),(55,2,0,'BM',0,0,0,0,1,'',1),(56,3,0,'BT',975,0,0,0,1,'',1),(57,4,0,'BW',267,0,0,0,1,'',1),(58,6,0,'BR',55,0,0,0,1,'NNNNN-NNN',1),(59,3,0,'BN',673,0,0,0,1,'LLNNNN',1),(60,4,0,'BF',226,0,0,0,1,'',1),(61,3,0,'MM',95,0,0,0,1,'',1),(62,4,0,'BI',257,0,0,0,1,'',1),(63,3,0,'KH',855,0,0,0,1,'NNNNN',1),(64,4,0,'CM',237,0,0,0,1,'',1),(65,4,0,'CV',238,0,0,0,1,'NNNN',1),(66,4,0,'CF',236,0,0,0,1,'',1),(67,4,0,'TD',235,0,0,0,1,'',1),(68,6,0,'CL',56,0,0,0,1,'NNN-NNNN',1),(69,6,0,'CO',57,0,0,0,1,'NNNNNN',1),(70,4,0,'KM',269,0,0,0,1,'',1),(71,4,0,'CD',242,0,0,0,1,'',1),(72,4,0,'CG',243,0,0,0,1,'',1),(73,8,0,'CR',506,0,0,0,1,'NNNNN',1),(74,7,0,'HR',385,0,0,0,1,'NNNNN',1),(75,8,0,'CU',53,0,0,0,1,'',1),(76,1,0,'CY',357,0,0,0,1,'NNNN',1),(77,4,0,'DJ',253,0,0,0,1,'',1),(78,8,0,'DM',0,0,0,0,1,'',1),(79,8,0,'DO',0,0,0,0,1,'',1),(80,3,0,'TL',670,0,0,0,1,'',1),(81,6,0,'EC',593,0,0,0,1,'CNNNNNN',1),(82,4,0,'EG',20,0,0,0,1,'NNNNN',1),(83,8,0,'SV',503,0,0,0,1,'',1),(84,4,0,'GQ',240,0,0,0,1,'',1),(85,4,0,'ER',291,0,0,0,1,'',1),(86,1,0,'EE',372,0,0,0,1,'NNNNN',1),(87,4,0,'ET',251,0,0,0,1,'',1),(88,8,0,'FK',0,0,0,0,1,'LLLL NLL',1),(89,7,0,'FO',298,0,0,0,1,'',1),(90,5,0,'FJ',679,0,0,0,1,'',1),(91,4,0,'GA',241,0,0,0,1,'',1),(92,4,0,'GM',220,0,0,0,1,'',1),(93,3,0,'GE',995,0,0,0,1,'NNNN',1),(94,4,0,'GH',233,0,0,0,1,'',1),(95,8,0,'GD',0,0,0,0,1,'',1),(96,7,0,'GL',299,0,0,0,1,'',1),(97,7,0,'GI',350,0,0,0,1,'',1),(98,8,0,'GP',590,0,0,0,1,'',1),(99,5,0,'GU',0,0,0,0,1,'',1),(100,8,0,'GT',502,0,0,0,1,'',1),(101,7,0,'GG',0,0,0,0,1,'LLN NLL',1),(102,4,0,'GN',224,0,0,0,1,'',1),(103,4,0,'GW',245,0,0,0,1,'',1),(104,6,0,'GY',592,0,0,0,1,'',1),(105,8,0,'HT',509,0,0,0,1,'',1),(106,5,0,'HM',0,0,0,0,1,'',1),(107,7,0,'VA',379,0,0,0,1,'NNNNN',1),(108,8,0,'HN',504,0,0,0,1,'',1),(109,7,0,'IS',354,0,0,0,1,'NNN',1),(110,3,0,'IN',91,0,0,0,1,'NNN NNN',1),(111,3,0,'ID',62,0,1,0,1,'NNNNN',1),(112,3,0,'IR',98,0,0,0,1,'NNNNN-NNNNN',1),(113,3,0,'IQ',964,0,0,0,1,'NNNNN',1),(114,7,0,'IM',0,0,0,0,1,'CN NLL',1),(115,8,0,'JM',0,0,0,0,1,'',1),(116,7,0,'JE',0,0,0,0,1,'CN NLL',1),(117,3,0,'JO',962,0,0,0,1,'',1),(118,3,0,'KZ',7,0,0,0,1,'NNNNNN',1),(119,4,0,'KE',254,0,0,0,1,'',1),(120,5,0,'KI',686,0,0,0,1,'',1),(121,3,0,'KP',850,0,0,0,1,'',1),(122,3,0,'KW',965,0,0,0,1,'',1),(123,3,0,'KG',996,0,0,0,1,'',1),(124,3,0,'LA',856,0,0,0,1,'',1),(125,1,0,'LV',371,0,0,0,1,'C-NNNN',1),(126,3,0,'LB',961,0,0,0,1,'',1),(127,4,0,'LS',266,0,0,0,1,'',1),(128,4,0,'LR',231,0,0,0,1,'',1),(129,4,0,'LY',218,0,0,0,1,'',1),(130,1,0,'LI',423,0,0,0,1,'NNNN',1),(131,1,0,'LT',370,0,0,0,1,'NNNNN',1),(132,3,0,'MO',853,0,0,0,0,'',1),(133,7,0,'MK',389,0,0,0,1,'',1),(134,4,0,'MG',261,0,0,0,1,'',1),(135,4,0,'MW',265,0,0,0,1,'',1),(136,3,0,'MY',60,0,0,0,1,'NNNNN',1),(137,3,0,'MV',960,0,0,0,1,'',1),(138,4,0,'ML',223,0,0,0,1,'',1),(139,1,0,'MT',356,0,0,0,1,'LLL NNNN',1),(140,5,0,'MH',692,0,0,0,1,'',1),(141,8,0,'MQ',596,0,0,0,1,'',1),(142,4,0,'MR',222,0,0,0,1,'',1),(143,1,0,'HU',36,0,0,0,1,'NNNN',1),(144,4,0,'YT',262,0,0,0,1,'',1),(145,2,0,'MX',52,0,1,1,1,'NNNNN',1),(146,5,0,'FM',691,0,0,0,1,'',1),(147,7,0,'MD',373,0,0,0,1,'C-NNNN',1),(148,7,0,'MC',377,0,0,0,1,'980NN',1),(149,3,0,'MN',976,0,0,0,1,'',1),(150,7,0,'ME',382,0,0,0,1,'NNNNN',1),(151,8,0,'MS',0,0,0,0,1,'',1),(152,4,0,'MA',212,0,0,0,1,'NNNNN',1),(153,4,0,'MZ',258,0,0,0,1,'',1),(154,4,0,'NA',264,0,0,0,1,'',1),(155,5,0,'NR',674,0,0,0,1,'',1),(156,3,0,'NP',977,0,0,0,1,'',1),(157,8,0,'AN',599,0,0,0,1,'',1),(158,5,0,'NC',687,0,0,0,1,'',1),(159,8,0,'NI',505,0,0,0,1,'NNNNNN',1),(160,4,0,'NE',227,0,0,0,1,'',1),(161,5,0,'NU',683,0,0,0,1,'',1),(162,5,0,'NF',0,0,0,0,1,'',1),(163,5,0,'MP',0,0,0,0,1,'',1),(164,3,0,'OM',968,0,0,0,1,'',1),(165,3,0,'PK',92,0,0,0,1,'',1),(166,5,0,'PW',680,0,0,0,1,'',1),(167,3,0,'PS',0,0,0,0,1,'',1),(168,8,0,'PA',507,0,0,0,1,'NNNNNN',1),(169,5,0,'PG',675,0,0,0,1,'',1),(170,6,0,'PY',595,0,0,0,1,'',1),(171,6,0,'PE',51,0,0,0,1,'',1),(172,3,0,'PH',63,0,0,0,1,'NNNN',1),(173,5,0,'PN',0,0,0,0,1,'LLLL NLL',1),(174,8,0,'PR',0,0,0,0,1,'NNNNN',1),(175,3,0,'QA',974,0,0,0,1,'',1),(176,4,0,'RE',262,0,0,0,1,'',1),(177,7,0,'RU',7,0,0,0,1,'NNNNNN',1),(178,4,0,'RW',250,0,0,0,1,'',1),(179,8,0,'BL',0,0,0,0,1,'',1),(180,8,0,'KN',0,0,0,0,1,'',1),(181,8,0,'LC',0,0,0,0,1,'',1),(182,8,0,'MF',0,0,0,0,1,'',1),(183,8,0,'PM',508,0,0,0,1,'',1),(184,8,0,'VC',0,0,0,0,1,'',1),(185,5,0,'WS',685,0,0,0,1,'',1),(186,7,0,'SM',378,0,0,0,1,'NNNNN',1),(187,4,0,'ST',239,0,0,0,1,'',1),(188,3,0,'SA',966,0,0,0,1,'',1),(189,4,0,'SN',221,0,0,0,1,'',1),(190,7,0,'RS',381,0,0,0,1,'NNNNN',1),(191,4,0,'SC',248,0,0,0,1,'',1),(192,4,0,'SL',232,0,0,0,1,'',1),(193,1,0,'SI',386,0,0,0,1,'C-NNNN',1),(194,5,0,'SB',677,0,0,0,1,'',1),(195,4,0,'SO',252,0,0,0,1,'',1),(196,8,0,'GS',0,0,0,0,1,'LLLL NLL',1),(197,3,0,'LK',94,0,0,0,1,'NNNNN',1),(198,4,0,'SD',249,0,0,0,1,'',1),(199,8,0,'SR',597,0,0,0,1,'',1),(200,7,0,'SJ',0,0,0,0,1,'',1),(201,4,0,'SZ',268,0,0,0,1,'',1),(202,3,0,'SY',963,0,0,0,1,'',1),(203,3,0,'TW',886,0,0,0,1,'NNNNN',1),(204,3,0,'TJ',992,0,0,0,1,'',1),(205,4,0,'TZ',255,0,0,0,1,'',1),(206,3,0,'TH',66,0,0,0,1,'NNNNN',1),(207,5,0,'TK',690,0,0,0,1,'',1),(208,5,0,'TO',676,0,0,0,1,'',1),(209,6,0,'TT',0,0,0,0,1,'',1),(210,4,0,'TN',216,0,0,0,1,'',1),(211,7,0,'TR',90,0,0,0,1,'NNNNN',1),(212,3,0,'TM',993,0,0,0,1,'',1),(213,8,0,'TC',0,0,0,0,1,'LLLL NLL',1),(214,5,0,'TV',688,0,0,0,1,'',1),(215,4,0,'UG',256,0,0,0,1,'',1),(216,1,0,'UA',380,0,0,0,1,'NNNNN',1),(217,3,0,'AE',971,0,0,0,1,'',1),(218,6,0,'UY',598,0,0,0,1,'',1),(219,3,0,'UZ',998,0,0,0,1,'',1),(220,5,0,'VU',678,0,0,0,1,'',1),(221,6,0,'VE',58,0,0,0,1,'',1),(222,3,0,'VN',84,0,0,0,1,'NNNNNN',1),(223,2,0,'VG',0,0,0,0,1,'CNNNN',1),(224,2,0,'VI',0,0,0,0,1,'',1),(225,5,0,'WF',681,0,0,0,1,'',1),(226,4,0,'EH',0,0,0,0,1,'',1),(227,3,0,'YE',967,0,0,0,1,'',1),(228,4,0,'ZM',260,0,0,0,1,'',1),(229,4,0,'ZW',263,0,0,0,1,'',1),(230,7,0,'AL',355,0,0,0,1,'NNNN',1),(231,3,0,'AF',93,0,0,0,1,'NNNN',1),(232,5,0,'AQ',0,0,0,0,1,'',1),(233,1,0,'BA',387,0,0,0,1,'',1),(234,5,0,'BV',0,0,0,0,1,'',1),(235,5,0,'IO',0,0,0,0,1,'LLLL NLL',1),(236,1,0,'BG',359,0,0,0,1,'NNNN',1),(237,8,0,'KY',0,0,0,0,1,'',1),(238,3,0,'CX',0,0,0,0,1,'',1),(239,3,0,'CC',0,0,0,0,1,'',1),(240,5,0,'CK',682,0,0,0,1,'',1),(241,6,0,'GF',594,0,0,0,1,'',1),(242,5,0,'PF',689,0,0,0,1,'',1),(243,5,0,'TF',0,0,0,0,1,'',1),(244,7,0,'AX',0,0,0,0,1,'NNNNN',1);
/*!40000 ALTER TABLE `qlo_country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_country_lang`
--

DROP TABLE IF EXISTS `qlo_country_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_country_lang` (
  `id_country` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id_country`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_country_lang`
--

LOCK TABLES `qlo_country_lang` WRITE;
/*!40000 ALTER TABLE `qlo_country_lang` DISABLE KEYS */;
INSERT INTO `qlo_country_lang` VALUES (1,1,'Germany'),(2,1,'Austria'),(3,1,'Belgium'),(4,1,'Canada'),(5,1,'China'),(6,1,'Spain'),(7,1,'Finland'),(8,1,'France'),(9,1,'Greece'),(10,1,'Italy'),(11,1,'Japan'),(12,1,'Luxemburg'),(13,1,'Netherlands'),(14,1,'Poland'),(15,1,'Portugal'),(16,1,'Czech Republic'),(17,1,'United Kingdom'),(18,1,'Sweden'),(19,1,'Switzerland'),(20,1,'Denmark'),(21,1,'United States'),(22,1,'HongKong'),(23,1,'Norway'),(24,1,'Australia'),(25,1,'Singapore'),(26,1,'Ireland'),(27,1,'New Zealand'),(28,1,'South Korea'),(29,1,'Israel'),(30,1,'South Africa'),(31,1,'Nigeria'),(32,1,'Ivory Coast'),(33,1,'Togo'),(34,1,'Bolivia'),(35,1,'Mauritius'),(36,1,'Romania'),(37,1,'Slovakia'),(38,1,'Algeria'),(39,1,'American Samoa'),(40,1,'Andorra'),(41,1,'Angola'),(42,1,'Anguilla'),(43,1,'Antigua and Barbuda'),(44,1,'Argentina'),(45,1,'Armenia'),(46,1,'Aruba'),(47,1,'Azerbaijan'),(48,1,'Bahamas'),(49,1,'Bahrain'),(50,1,'Bangladesh'),(51,1,'Barbados'),(52,1,'Belarus'),(53,1,'Belize'),(54,1,'Benin'),(55,1,'Bermuda'),(56,1,'Bhutan'),(57,1,'Botswana'),(58,1,'Brazil'),(59,1,'Brunei'),(60,1,'Burkina Faso'),(61,1,'Burma (Myanmar)'),(62,1,'Burundi'),(63,1,'Cambodia'),(64,1,'Cameroon'),(65,1,'Cape Verde'),(66,1,'Central African Republic'),(67,1,'Chad'),(68,1,'Chile'),(69,1,'Colombia'),(70,1,'Comoros'),(71,1,'Congo, Dem. Republic'),(72,1,'Congo, Republic'),(73,1,'Costa Rica'),(74,1,'Croatia'),(75,1,'Cuba'),(76,1,'Cyprus'),(77,1,'Djibouti'),(78,1,'Dominica'),(79,1,'Dominican Republic'),(80,1,'East Timor'),(81,1,'Ecuador'),(82,1,'Egypt'),(83,1,'El Salvador'),(84,1,'Equatorial Guinea'),(85,1,'Eritrea'),(86,1,'Estonia'),(87,1,'Ethiopia'),(88,1,'Falkland Islands'),(89,1,'Faroe Islands'),(90,1,'Fiji'),(91,1,'Gabon'),(92,1,'Gambia'),(93,1,'Georgia'),(94,1,'Ghana'),(95,1,'Grenada'),(96,1,'Greenland'),(97,1,'Gibraltar'),(98,1,'Guadeloupe'),(99,1,'Guam'),(100,1,'Guatemala'),(101,1,'Guernsey'),(102,1,'Guinea'),(103,1,'Guinea-Bissau'),(104,1,'Guyana'),(105,1,'Haiti'),(106,1,'Heard Island and McDonald Islands'),(107,1,'Vatican City State'),(108,1,'Honduras'),(109,1,'Iceland'),(110,1,'India'),(111,1,'Indonesia'),(112,1,'Iran'),(113,1,'Iraq'),(114,1,'Man Island'),(115,1,'Jamaica'),(116,1,'Jersey'),(117,1,'Jordan'),(118,1,'Kazakhstan'),(119,1,'Kenya'),(120,1,'Kiribati'),(121,1,'Korea, Dem. Republic of'),(122,1,'Kuwait'),(123,1,'Kyrgyzstan'),(124,1,'Laos'),(125,1,'Latvia'),(126,1,'Lebanon'),(127,1,'Lesotho'),(128,1,'Liberia'),(129,1,'Libya'),(130,1,'Liechtenstein'),(131,1,'Lithuania'),(132,1,'Macau'),(133,1,'Macedonia'),(134,1,'Madagascar'),(135,1,'Malawi'),(136,1,'Malaysia'),(137,1,'Maldives'),(138,1,'Mali'),(139,1,'Malta'),(140,1,'Marshall Islands'),(141,1,'Martinique'),(142,1,'Mauritania'),(143,1,'Hungary'),(144,1,'Mayotte'),(145,1,'Mexico'),(146,1,'Micronesia'),(147,1,'Moldova'),(148,1,'Monaco'),(149,1,'Mongolia'),(150,1,'Montenegro'),(151,1,'Montserrat'),(152,1,'Morocco'),(153,1,'Mozambique'),(154,1,'Namibia'),(155,1,'Nauru'),(156,1,'Nepal'),(157,1,'Netherlands Antilles'),(158,1,'New Caledonia'),(159,1,'Nicaragua'),(160,1,'Niger'),(161,1,'Niue'),(162,1,'Norfolk Island'),(163,1,'Northern Mariana Islands'),(164,1,'Oman'),(165,1,'Pakistan'),(166,1,'Palau'),(167,1,'Palestinian Territories'),(168,1,'Panama'),(169,1,'Papua New Guinea'),(170,1,'Paraguay'),(171,1,'Peru'),(172,1,'Philippines'),(173,1,'Pitcairn'),(174,1,'Puerto Rico'),(175,1,'Qatar'),(176,1,'Reunion Island'),(177,1,'Russian Federation'),(178,1,'Rwanda'),(179,1,'Saint Barthelemy'),(180,1,'Saint Kitts and Nevis'),(181,1,'Saint Lucia'),(182,1,'Saint Martin'),(183,1,'Saint Pierre and Miquelon'),(184,1,'Saint Vincent and the Grenadines'),(185,1,'Samoa'),(186,1,'San Marino'),(187,1,'São Tomé and Príncipe'),(188,1,'Saudi Arabia'),(189,1,'Senegal'),(190,1,'Serbia'),(191,1,'Seychelles'),(192,1,'Sierra Leone'),(193,1,'Slovenia'),(194,1,'Solomon Islands'),(195,1,'Somalia'),(196,1,'South Georgia and the South Sandwich Islands'),(197,1,'Sri Lanka'),(198,1,'Sudan'),(199,1,'Suriname'),(200,1,'Svalbard and Jan Mayen'),(201,1,'Swaziland'),(202,1,'Syria'),(203,1,'Taiwan'),(204,1,'Tajikistan'),(205,1,'Tanzania'),(206,1,'Thailand'),(207,1,'Tokelau'),(208,1,'Tonga'),(209,1,'Trinidad and Tobago'),(210,1,'Tunisia'),(211,1,'Turkey'),(212,1,'Turkmenistan'),(213,1,'Turks and Caicos Islands'),(214,1,'Tuvalu'),(215,1,'Uganda'),(216,1,'Ukraine'),(217,1,'United Arab Emirates'),(218,1,'Uruguay'),(219,1,'Uzbekistan'),(220,1,'Vanuatu'),(221,1,'Venezuela'),(222,1,'Vietnam'),(223,1,'Virgin Islands (British)'),(224,1,'Virgin Islands (U.S.)'),(225,1,'Wallis and Futuna'),(226,1,'Western Sahara'),(227,1,'Yemen'),(228,1,'Zambia'),(229,1,'Zimbabwe'),(230,1,'Albania'),(231,1,'Afghanistan'),(232,1,'Antarctica'),(233,1,'Bosnia and Herzegovina'),(234,1,'Bouvet Island'),(235,1,'British Indian Ocean Territory'),(236,1,'Bulgaria'),(237,1,'Cayman Islands'),(238,1,'Christmas Island'),(239,1,'Cocos (Keeling) Islands'),(240,1,'Cook Islands'),(241,1,'French Guiana'),(242,1,'French Polynesia'),(243,1,'French Southern Territories'),(244,1,'Åland Islands');
/*!40000 ALTER TABLE `qlo_country_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_country_shop`
--

DROP TABLE IF EXISTS `qlo_country_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_country_shop` (
  `id_country` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_country`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_country_shop`
--

LOCK TABLES `qlo_country_shop` WRITE;
/*!40000 ALTER TABLE `qlo_country_shop` DISABLE KEYS */;
INSERT INTO `qlo_country_shop` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1),(48,1),(49,1),(50,1),(51,1),(52,1),(53,1),(54,1),(55,1),(56,1),(57,1),(58,1),(59,1),(60,1),(61,1),(62,1),(63,1),(64,1),(65,1),(66,1),(67,1),(68,1),(69,1),(70,1),(71,1),(72,1),(73,1),(74,1),(75,1),(76,1),(77,1),(78,1),(79,1),(80,1),(81,1),(82,1),(83,1),(84,1),(85,1),(86,1),(87,1),(88,1),(89,1),(90,1),(91,1),(92,1),(93,1),(94,1),(95,1),(96,1),(97,1),(98,1),(99,1),(100,1),(101,1),(102,1),(103,1),(104,1),(105,1),(106,1),(107,1),(108,1),(109,1),(110,1),(111,1),(112,1),(113,1),(114,1),(115,1),(116,1),(117,1),(118,1),(119,1),(120,1),(121,1),(122,1),(123,1),(124,1),(125,1),(126,1),(127,1),(128,1),(129,1),(130,1),(131,1),(132,1),(133,1),(134,1),(135,1),(136,1),(137,1),(138,1),(139,1),(140,1),(141,1),(142,1),(143,1),(144,1),(145,1),(146,1),(147,1),(148,1),(149,1),(150,1),(151,1),(152,1),(153,1),(154,1),(155,1),(156,1),(157,1),(158,1),(159,1),(160,1),(161,1),(162,1),(163,1),(164,1),(165,1),(166,1),(167,1),(168,1),(169,1),(170,1),(171,1),(172,1),(173,1),(174,1),(175,1),(176,1),(177,1),(178,1),(179,1),(180,1),(181,1),(182,1),(183,1),(184,1),(185,1),(186,1),(187,1),(188,1),(189,1),(190,1),(191,1),(192,1),(193,1),(194,1),(195,1),(196,1),(197,1),(198,1),(199,1),(200,1),(201,1),(202,1),(203,1),(204,1),(205,1),(206,1),(207,1),(208,1),(209,1),(210,1),(211,1),(212,1),(213,1),(214,1),(215,1),(216,1),(217,1),(218,1),(219,1),(220,1),(221,1),(222,1),(223,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(231,1),(232,1),(233,1),(234,1),(235,1),(236,1),(237,1),(238,1),(239,1),(240,1),(241,1),(242,1),(243,1),(244,1);
/*!40000 ALTER TABLE `qlo_country_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_currency`
--

DROP TABLE IF EXISTS `qlo_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_currency` (
  `id_currency` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `iso_code` varchar(3) NOT NULL DEFAULT '0',
  `iso_code_num` varchar(3) NOT NULL DEFAULT '0',
  `sign` varchar(8) NOT NULL,
  `blank` tinyint unsigned NOT NULL DEFAULT '0',
  `format` tinyint unsigned NOT NULL DEFAULT '0',
  `decimals` tinyint unsigned NOT NULL DEFAULT '1',
  `conversion_rate` decimal(13,6) NOT NULL,
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_currency`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_currency`
--

LOCK TABLES `qlo_currency` WRITE;
/*!40000 ALTER TABLE `qlo_currency` DISABLE KEYS */;
INSERT INTO `qlo_currency` VALUES (1,'Nigerian naira','NGN','566','₦',1,1,0,1.000000,0,1);
/*!40000 ALTER TABLE `qlo_currency` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_currency_shop`
--

DROP TABLE IF EXISTS `qlo_currency_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_currency_shop` (
  `id_currency` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `conversion_rate` decimal(13,6) NOT NULL,
  PRIMARY KEY (`id_currency`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_currency_shop`
--

LOCK TABLES `qlo_currency_shop` WRITE;
/*!40000 ALTER TABLE `qlo_currency_shop` DISABLE KEYS */;
INSERT INTO `qlo_currency_shop` VALUES (1,1,1.000000);
/*!40000 ALTER TABLE `qlo_currency_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customer`
--

DROP TABLE IF EXISTS `qlo_customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customer` (
  `id_customer` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_gender` int unsigned NOT NULL,
  `id_default_group` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned DEFAULT NULL,
  `id_risk` int unsigned NOT NULL DEFAULT '1',
  `company` varchar(64) DEFAULT NULL,
  `siret` varchar(14) DEFAULT NULL,
  `ape` varchar(5) DEFAULT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `email` varchar(128) NOT NULL,
  `passwd` varchar(60) NOT NULL,
  `last_passwd_gen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `birthday` date DEFAULT NULL,
  `newsletter` tinyint unsigned NOT NULL DEFAULT '0',
  `ip_registration_newsletter` varchar(15) DEFAULT NULL,
  `newsletter_date_add` datetime DEFAULT NULL,
  `optin` tinyint unsigned NOT NULL DEFAULT '0',
  `website` varchar(128) DEFAULT NULL,
  `outstanding_allow_amount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `show_public_prices` tinyint unsigned NOT NULL DEFAULT '0',
  `max_payment_days` int unsigned NOT NULL DEFAULT '60',
  `secure_key` varchar(32) NOT NULL DEFAULT '-1',
  `note` text,
  `phone` varchar(32) DEFAULT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `is_guest` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_customer`),
  KEY `customer_email` (`email`),
  KEY `customer_login` (`email`,`passwd`),
  KEY `id_customer_passwd` (`id_customer`,`passwd`),
  KEY `id_gender` (`id_gender`),
  KEY `id_shop_group` (`id_shop_group`),
  KEY `id_shop` (`id_shop`,`date_add`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customer`
--

LOCK TABLES `qlo_customer` WRITE;
/*!40000 ALTER TABLE `qlo_customer` DISABLE KEYS */;
INSERT INTO `qlo_customer` VALUES (1,1,1,1,3,1,0,'','','','John','Doe','pub@qloapps.com','$2y$12$dsdG4u8eTdaUUh8FVJTtPeYE6LvwlFmT2xVnu3C5Sd7IFZGf5eSve','2026-09-03 12:47:10','1996-09-03',1,'','2026-09-03 18:47:10',1,'',0.000000,0,0,'b4f9f26560cb868227d8bd7d5592d706','','1234567890',1,0,0,'2026-09-03 18:47:10','2026-09-03 18:47:10');
/*!40000 ALTER TABLE `qlo_customer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customer_group`
--

DROP TABLE IF EXISTS `qlo_customer_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customer_group` (
  `id_customer` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  PRIMARY KEY (`id_customer`,`id_group`),
  KEY `customer_login` (`id_group`),
  KEY `id_customer` (`id_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customer_group`
--

LOCK TABLES `qlo_customer_group` WRITE;
/*!40000 ALTER TABLE `qlo_customer_group` DISABLE KEYS */;
INSERT INTO `qlo_customer_group` VALUES (1,3);
/*!40000 ALTER TABLE `qlo_customer_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customer_guest_detail`
--

DROP TABLE IF EXISTS `qlo_customer_guest_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customer_guest_detail` (
  `id_customer_guest_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_customer` int unsigned NOT NULL,
  `id_gender` int unsigned NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `email` varchar(128) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_customer_guest_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customer_guest_detail`
--

LOCK TABLES `qlo_customer_guest_detail` WRITE;
/*!40000 ALTER TABLE `qlo_customer_guest_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customer_guest_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customer_message`
--

DROP TABLE IF EXISTS `qlo_customer_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customer_message` (
  `id_customer_message` int unsigned NOT NULL AUTO_INCREMENT,
  `id_customer_thread` int DEFAULT NULL,
  `id_employee` int unsigned DEFAULT NULL,
  `message` mediumtext NOT NULL,
  `id_product` int unsigned DEFAULT NULL,
  `file_name` varchar(18) DEFAULT NULL,
  `ip_address` varchar(16) DEFAULT NULL,
  `user_agent` varchar(128) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `private` tinyint NOT NULL DEFAULT '0',
  `read` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_customer_message`),
  KEY `id_customer_thread` (`id_customer_thread`),
  KEY `id_employee` (`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customer_message`
--

LOCK TABLES `qlo_customer_message` WRITE;
/*!40000 ALTER TABLE `qlo_customer_message` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customer_message` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customer_message_sync_imap`
--

DROP TABLE IF EXISTS `qlo_customer_message_sync_imap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customer_message_sync_imap` (
  `md5_header` varbinary(32) NOT NULL,
  KEY `md5_header_index` (`md5_header`(4))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customer_message_sync_imap`
--

LOCK TABLES `qlo_customer_message_sync_imap` WRITE;
/*!40000 ALTER TABLE `qlo_customer_message_sync_imap` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customer_message_sync_imap` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customer_thread`
--

DROP TABLE IF EXISTS `qlo_customer_thread`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customer_thread` (
  `id_customer_thread` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned NOT NULL,
  `id_contact` int unsigned NOT NULL,
  `id_customer` int unsigned DEFAULT NULL,
  `id_employee` int unsigned DEFAULT NULL,
  `id_order` int unsigned DEFAULT NULL,
  `user_name` varchar(128) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `subject` text,
  `status` int NOT NULL DEFAULT '1',
  `email` varchar(128) NOT NULL,
  `token` varchar(12) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_customer_thread`),
  KEY `id_shop` (`id_shop`),
  KEY `id_lang` (`id_lang`),
  KEY `id_contact` (`id_contact`),
  KEY `id_customer` (`id_customer`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customer_thread`
--

LOCK TABLES `qlo_customer_thread` WRITE;
/*!40000 ALTER TABLE `qlo_customer_thread` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customer_thread` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customization`
--

DROP TABLE IF EXISTS `qlo_customization`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customization` (
  `id_customization` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product_attribute` int unsigned NOT NULL DEFAULT '0',
  `id_address_delivery` int unsigned NOT NULL DEFAULT '0',
  `id_cart` int unsigned NOT NULL,
  `id_product` int NOT NULL,
  `quantity` int NOT NULL,
  `quantity_refunded` int NOT NULL DEFAULT '0',
  `quantity_returned` int NOT NULL DEFAULT '0',
  `in_cart` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_customization`,`id_cart`,`id_product`,`id_address_delivery`),
  KEY `id_product_attribute` (`id_product_attribute`),
  KEY `id_cart_product` (`id_cart`,`id_product`,`id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customization`
--

LOCK TABLES `qlo_customization` WRITE;
/*!40000 ALTER TABLE `qlo_customization` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customization` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customization_field`
--

DROP TABLE IF EXISTS `qlo_customization_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customization_field` (
  `id_customization_field` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `type` tinyint(1) NOT NULL,
  `required` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_customization_field`),
  KEY `id_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customization_field`
--

LOCK TABLES `qlo_customization_field` WRITE;
/*!40000 ALTER TABLE `qlo_customization_field` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customization_field` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customization_field_lang`
--

DROP TABLE IF EXISTS `qlo_customization_field_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customization_field_lang` (
  `id_customization_field` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_customization_field`,`id_lang`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customization_field_lang`
--

LOCK TABLES `qlo_customization_field_lang` WRITE;
/*!40000 ALTER TABLE `qlo_customization_field_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customization_field_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_customized_data`
--

DROP TABLE IF EXISTS `qlo_customized_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_customized_data` (
  `id_customization` int unsigned NOT NULL,
  `type` tinyint(1) NOT NULL,
  `index` int NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id_customization`,`type`,`index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_customized_data`
--

LOCK TABLES `qlo_customized_data` WRITE;
/*!40000 ALTER TABLE `qlo_customized_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_customized_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_date_range`
--

DROP TABLE IF EXISTS `qlo_date_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_date_range` (
  `id_date_range` int unsigned NOT NULL AUTO_INCREMENT,
  `time_start` datetime NOT NULL,
  `time_end` datetime NOT NULL,
  PRIMARY KEY (`id_date_range`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_date_range`
--

LOCK TABLES `qlo_date_range` WRITE;
/*!40000 ALTER TABLE `qlo_date_range` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_date_range` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_delivery`
--

DROP TABLE IF EXISTS `qlo_delivery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_delivery` (
  `id_delivery` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned DEFAULT NULL,
  `id_shop_group` int unsigned DEFAULT NULL,
  `id_carrier` int unsigned NOT NULL,
  `id_range_price` int unsigned DEFAULT NULL,
  `id_range_weight` int unsigned DEFAULT NULL,
  `id_zone` int unsigned NOT NULL,
  `price` decimal(20,6) NOT NULL,
  PRIMARY KEY (`id_delivery`),
  KEY `id_zone` (`id_zone`),
  KEY `id_carrier` (`id_carrier`,`id_zone`),
  KEY `id_range_price` (`id_range_price`),
  KEY `id_range_weight` (`id_range_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_delivery`
--

LOCK TABLES `qlo_delivery` WRITE;
/*!40000 ALTER TABLE `qlo_delivery` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_delivery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_employee`
--

DROP TABLE IF EXISTS `qlo_employee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_employee` (
  `id_employee` int unsigned NOT NULL AUTO_INCREMENT,
  `id_profile` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL DEFAULT '0',
  `lastname` varchar(32) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `email` varchar(128) NOT NULL,
  `passwd` varchar(60) NOT NULL,
  `last_passwd_gen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stats_date_from` date DEFAULT NULL,
  `stats_date_to` date DEFAULT NULL,
  `stats_compare_from` date DEFAULT NULL,
  `stats_compare_to` date DEFAULT NULL,
  `stats_compare_option` int unsigned NOT NULL DEFAULT '1',
  `preselect_date_range` varchar(32) DEFAULT NULL,
  `bo_color` varchar(32) DEFAULT NULL,
  `bo_theme` varchar(32) DEFAULT NULL,
  `bo_css` varchar(64) DEFAULT NULL,
  `default_tab` int unsigned NOT NULL DEFAULT '0',
  `bo_width` int unsigned NOT NULL DEFAULT '0',
  `bo_menu` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `optin` tinyint unsigned NOT NULL DEFAULT '1',
  `id_last_order` int unsigned NOT NULL DEFAULT '0',
  `id_last_customer_message` int unsigned NOT NULL DEFAULT '0',
  `id_last_customer` int unsigned NOT NULL DEFAULT '0',
  `last_connection_date` date DEFAULT '0000-00-00',
  PRIMARY KEY (`id_employee`),
  KEY `employee_login` (`email`,`passwd`),
  KEY `id_employee_passwd` (`id_employee`,`passwd`),
  KEY `id_profile` (`id_profile`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_employee`
--

LOCK TABLES `qlo_employee` WRITE;
/*!40000 ALTER TABLE `qlo_employee` DISABLE KEYS */;
INSERT INTO `qlo_employee` VALUES (1,1,1,'Solutions','Animate','supportanimatesolutions@gmail.com','$2y$12$DS/C46LGuoyMeUMPh.QnHeHHgaIqhDHXFhY9H2XSGCtvJf1YiMyyS','2026-09-03 12:46:28','2026-08-03','2026-09-03','0000-00-00','0000-00-00',1,NULL,NULL,'default','admin-theme.css',1,0,1,1,1,0,0,0,'2026-09-03');
/*!40000 ALTER TABLE `qlo_employee` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_employee_shop`
--

DROP TABLE IF EXISTS `qlo_employee_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_employee_shop` (
  `id_employee` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_employee`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_employee_shop`
--

LOCK TABLES `qlo_employee_shop` WRITE;
/*!40000 ALTER TABLE `qlo_employee_shop` DISABLE KEYS */;
INSERT INTO `qlo_employee_shop` VALUES (1,1);
/*!40000 ALTER TABLE `qlo_employee_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_feature`
--

DROP TABLE IF EXISTS `qlo_feature`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_feature` (
  `id_feature` int unsigned NOT NULL AUTO_INCREMENT,
  `position` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_feature`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_feature`
--

LOCK TABLES `qlo_feature` WRITE;
/*!40000 ALTER TABLE `qlo_feature` DISABLE KEYS */;
INSERT INTO `qlo_feature` VALUES (1,0),(2,1),(3,2),(4,3),(5,4),(6,5),(7,6),(8,7),(9,8),(10,9),(11,10),(12,11),(13,12);
/*!40000 ALTER TABLE `qlo_feature` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_feature_lang`
--

DROP TABLE IF EXISTS `qlo_feature_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_feature_lang` (
  `id_feature` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id_feature`,`id_lang`),
  KEY `id_lang` (`id_lang`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_feature_lang`
--

LOCK TABLES `qlo_feature_lang` WRITE;
/*!40000 ALTER TABLE `qlo_feature_lang` DISABLE KEYS */;
INSERT INTO `qlo_feature_lang` VALUES (8,1,'AC Type'),(6,1,'Balcony Type'),(7,1,'Bathroom Type'),(1,1,'Bed Type'),(3,1,'Crib/Cot Available'),(2,1,'Extra Bed Available'),(9,1,'Heating Type'),(4,1,'Room Size'),(12,1,'Smoke Detection'),(13,1,'Smoking Policy'),(10,1,'TV Type'),(5,1,'View'),(11,1,'Wi-Fi Speed');
/*!40000 ALTER TABLE `qlo_feature_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_feature_product`
--

DROP TABLE IF EXISTS `qlo_feature_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_feature_product` (
  `id_feature` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `id_feature_value` int unsigned NOT NULL,
  PRIMARY KEY (`id_feature`,`id_product`,`id_feature_value`),
  KEY `id_feature_value` (`id_feature_value`),
  KEY `id_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_feature_product`
--

LOCK TABLES `qlo_feature_product` WRITE;
/*!40000 ALTER TABLE `qlo_feature_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_feature_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_feature_shop`
--

DROP TABLE IF EXISTS `qlo_feature_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_feature_shop` (
  `id_feature` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_feature`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_feature_shop`
--

LOCK TABLES `qlo_feature_shop` WRITE;
/*!40000 ALTER TABLE `qlo_feature_shop` DISABLE KEYS */;
INSERT INTO `qlo_feature_shop` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1);
/*!40000 ALTER TABLE `qlo_feature_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_feature_value`
--

DROP TABLE IF EXISTS `qlo_feature_value`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_feature_value` (
  `id_feature_value` int unsigned NOT NULL AUTO_INCREMENT,
  `id_feature` int unsigned NOT NULL,
  `custom` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id_feature_value`),
  KEY `feature` (`id_feature`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_feature_value`
--

LOCK TABLES `qlo_feature_value` WRITE;
/*!40000 ALTER TABLE `qlo_feature_value` DISABLE KEYS */;
INSERT INTO `qlo_feature_value` VALUES (1,1,0),(2,1,0),(3,1,0),(4,1,0),(5,1,0),(6,1,0),(7,1,0),(8,1,0),(9,1,0),(10,1,0),(11,1,0),(12,2,0),(13,2,0),(14,2,0),(15,2,0),(16,3,0),(17,3,0),(18,3,0),(19,4,0),(20,4,0),(21,4,0),(22,4,0),(23,4,0),(24,4,0),(25,4,0),(26,4,0),(27,5,0),(28,5,0),(29,5,0),(30,5,0),(31,5,0),(32,5,0),(33,5,0),(34,5,0),(35,5,0),(36,5,0),(37,5,0),(38,6,0),(39,6,0),(40,6,0),(41,6,0),(42,6,0),(43,6,0),(44,6,0),(45,7,0),(46,7,0),(47,7,0),(48,7,0),(49,8,0),(50,8,0),(51,8,0),(52,8,0),(53,8,0),(54,9,0),(55,9,0),(56,9,0),(57,9,0),(58,9,0),(59,10,0),(60,10,0),(61,10,0),(62,10,0),(63,11,0),(64,11,0),(65,11,0),(66,11,0),(67,12,0),(68,12,0),(69,12,0),(70,13,0),(71,13,0);
/*!40000 ALTER TABLE `qlo_feature_value` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_feature_value_lang`
--

DROP TABLE IF EXISTS `qlo_feature_value_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_feature_value_lang` (
  `id_feature_value` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_feature_value`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_feature_value_lang`
--

LOCK TABLES `qlo_feature_value_lang` WRITE;
/*!40000 ALTER TABLE `qlo_feature_value_lang` DISABLE KEYS */;
INSERT INTO `qlo_feature_value_lang` VALUES (1,1,'Single'),(2,1,'Double'),(3,1,'Queen'),(4,1,'King'),(5,1,'Super King'),(6,1,'Twin'),(7,1,'Bunk Bed'),(8,1,'Sofa Bed'),(9,1,'Futon'),(10,1,'Murphy Bed'),(11,1,'Water Bed'),(12,1,'Yes'),(13,1,'No'),(14,1,'On Request'),(15,1,'Chargeable'),(16,1,'Yes'),(17,1,'No'),(18,1,'On Request'),(19,1,'150 sq ft'),(20,1,'200 sq ft'),(21,1,'300 sq ft'),(22,1,'400 sq ft'),(23,1,'500 sq ft'),(24,1,'600 sq ft'),(25,1,'800 sq ft'),(26,1,'1000+ sq ft'),(27,1,'Sea/Ocean View'),(28,1,'Garden View'),(29,1,'City View'),(30,1,'Pool View'),(31,1,'Mountain View'),(32,1,'Lake View'),(33,1,'River View'),(34,1,'Park View'),(35,1,'Courtyard View'),(36,1,'Desert View'),(37,1,'No View/Interior'),(38,1,'Private Balcony'),(39,1,'Shared Terrace'),(40,1,'Juliet Balcony'),(41,1,'Rooftop Access'),(42,1,'Patio'),(43,1,'Veranda'),(44,1,'None'),(45,1,'Private/Ensuite'),(46,1,'Shared'),(47,1,'Jack and Jill (shared between 2 rooms)'),(48,1,'Half Bath'),(49,1,'Split AC'),(50,1,'Central AC'),(51,1,'Window AC'),(52,1,'No AC'),(53,1,'Fan Only'),(54,1,'Central Heating'),(55,1,'Room Heater'),(56,1,'Underfloor Heating'),(57,1,'Fireplace'),(58,1,'No Heating'),(59,1,'No TV'),(60,1,'LED TV'),(61,1,'Smart TV'),(62,1,'OLED TV'),(63,1,'No Wi-Fi'),(64,1,'Basic'),(65,1,'High Speed'),(66,1,'Premium/Fiber'),(67,1,'Smoke Detector'),(68,1,'Sprinkler'),(69,1,'None'),(70,1,'Smoking Not Allowed'),(71,1,'Smoking Allowed');
/*!40000 ALTER TABLE `qlo_feature_value_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_gender`
--

DROP TABLE IF EXISTS `qlo_gender`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_gender` (
  `id_gender` int NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_gender`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_gender`
--

LOCK TABLES `qlo_gender` WRITE;
/*!40000 ALTER TABLE `qlo_gender` DISABLE KEYS */;
INSERT INTO `qlo_gender` VALUES (1,0),(2,1);
/*!40000 ALTER TABLE `qlo_gender` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_gender_lang`
--

DROP TABLE IF EXISTS `qlo_gender_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_gender_lang` (
  `id_gender` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id_gender`,`id_lang`),
  KEY `id_gender` (`id_gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_gender_lang`
--

LOCK TABLES `qlo_gender_lang` WRITE;
/*!40000 ALTER TABLE `qlo_gender_lang` DISABLE KEYS */;
INSERT INTO `qlo_gender_lang` VALUES (1,1,'Mr.'),(2,1,'Mrs.');
/*!40000 ALTER TABLE `qlo_gender_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_group`
--

DROP TABLE IF EXISTS `qlo_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_group` (
  `id_group` int unsigned NOT NULL AUTO_INCREMENT,
  `reduction` decimal(17,2) NOT NULL DEFAULT '0.00',
  `price_display_method` tinyint NOT NULL DEFAULT '0',
  `show_prices` tinyint unsigned NOT NULL DEFAULT '1',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_group`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_group`
--

LOCK TABLES `qlo_group` WRITE;
/*!40000 ALTER TABLE `qlo_group` DISABLE KEYS */;
INSERT INTO `qlo_group` VALUES (1,0.00,0,1,'2026-09-03 18:45:38','2026-09-03 18:45:38'),(2,0.00,0,1,'2026-09-03 18:45:39','2026-09-03 18:45:39'),(3,0.00,0,1,'2026-09-03 18:45:39','2026-09-03 18:45:39');
/*!40000 ALTER TABLE `qlo_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_group_lang`
--

DROP TABLE IF EXISTS `qlo_group_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_group_lang` (
  `id_group` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id_group`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_group_lang`
--

LOCK TABLES `qlo_group_lang` WRITE;
/*!40000 ALTER TABLE `qlo_group_lang` DISABLE KEYS */;
INSERT INTO `qlo_group_lang` VALUES (1,1,'Visitor'),(2,1,'Guest'),(3,1,'Customer');
/*!40000 ALTER TABLE `qlo_group_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_group_reduction`
--

DROP TABLE IF EXISTS `qlo_group_reduction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_group_reduction` (
  `id_group_reduction` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `id_group` int unsigned NOT NULL,
  `id_category` int unsigned NOT NULL,
  `reduction` decimal(4,3) NOT NULL,
  PRIMARY KEY (`id_group_reduction`),
  UNIQUE KEY `id_group` (`id_group`,`id_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_group_reduction`
--

LOCK TABLES `qlo_group_reduction` WRITE;
/*!40000 ALTER TABLE `qlo_group_reduction` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_group_reduction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_group_shop`
--

DROP TABLE IF EXISTS `qlo_group_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_group_shop` (
  `id_group` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_group`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_group_shop`
--

LOCK TABLES `qlo_group_shop` WRITE;
/*!40000 ALTER TABLE `qlo_group_shop` DISABLE KEYS */;
INSERT INTO `qlo_group_shop` VALUES (1,1),(2,1),(3,1);
/*!40000 ALTER TABLE `qlo_group_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_guest`
--

DROP TABLE IF EXISTS `qlo_guest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_guest` (
  `id_guest` int unsigned NOT NULL AUTO_INCREMENT,
  `id_operating_system` int unsigned DEFAULT NULL,
  `id_web_browser` int unsigned DEFAULT NULL,
  `id_customer` int unsigned DEFAULT NULL,
  `javascript` tinyint(1) DEFAULT '0',
  `screen_resolution_x` smallint unsigned DEFAULT NULL,
  `screen_resolution_y` smallint unsigned DEFAULT NULL,
  `screen_color` tinyint unsigned DEFAULT NULL,
  `sun_java` tinyint(1) DEFAULT NULL,
  `adobe_flash` tinyint(1) DEFAULT NULL,
  `adobe_director` tinyint(1) DEFAULT NULL,
  `apple_quicktime` tinyint(1) DEFAULT NULL,
  `real_player` tinyint(1) DEFAULT NULL,
  `windows_media` tinyint(1) DEFAULT NULL,
  `accept_language` varchar(8) DEFAULT NULL,
  `mobile_theme` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_guest`),
  KEY `id_customer` (`id_customer`),
  KEY `id_operating_system` (`id_operating_system`),
  KEY `id_web_browser` (`id_web_browser`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_guest`
--

LOCK TABLES `qlo_guest` WRITE;
/*!40000 ALTER TABLE `qlo_guest` DISABLE KEYS */;
INSERT INTO `qlo_guest` VALUES (1,0,0,1,0,0,0,0,0,0,0,0,0,0,'',0),(2,0,11,0,0,0,0,0,0,0,0,0,0,0,'en',0);
/*!40000 ALTER TABLE `qlo_guest` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_hook`
--

DROP TABLE IF EXISTS `qlo_hook`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_hook` (
  `id_hook` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `title` varchar(64) NOT NULL,
  `description` text,
  `position` tinyint(1) NOT NULL DEFAULT '1',
  `live_edit` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_hook`),
  UNIQUE KEY `hook_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_hook`
--

LOCK TABLES `qlo_hook` WRITE;
/*!40000 ALTER TABLE `qlo_hook` DISABLE KEYS */;
INSERT INTO `qlo_hook` VALUES (1,'displayPayment','Payment','This hook displays new elements on the payment page',1,1),(2,'actionValidateOrder','New orders','',1,0),(3,'displayMaintenance','Maintenance Page','This hook displays new elements on the maintenance page',1,0),(4,'actionPaymentConfirmation','Payment confirmation','This hook displays new elements after the payment is validated',1,0),(5,'displayPaymentReturn','Payment return','',1,0),(6,'actionUpdateQuantity','Quantity update','Quantity is updated only when a customer effectively places their order',1,0),(7,'displayRightColumn','Right column blocks','This hook displays new elements in the right-hand column',1,1),(8,'displayLeftColumn','Left column blocks','This hook displays new elements in the left-hand column',1,1),(9,'displayHome','Homepage content','This hook displays new elements on the homepage',1,1),(10,'Header','Pages html head section','This hook adds additional elements in the head section of your pages (head section of html)',1,0),(11,'actionCartSave','Cart creation and update','This hook is displayed when a product is added to the cart or if the cart\'s content is modified',1,0),(12,'actionAuthentication','Successful customer authentication','This hook is displayed after a customer successfully signs in',1,0),(13,'actionProductAdd','Product creation','This hook is displayed after a product is created',1,0),(14,'actionProductUpdate','Product update','This hook is displayed after a product has been updated',1,0),(15,'displayTop','Top of pages','This hook displays additional elements at the top of your pages',1,0),(16,'displayRightColumnProduct','New elements on the product page (right column)','This hook displays new elements in the right-hand column of the product page',1,0),(17,'actionProductDelete','Product deletion','This hook is called when a product is deleted',1,0),(18,'displayFooterProduct','Product footer','This hook adds new blocks under the product\'s description',1,1),(19,'displayInvoice','Invoice','This hook displays new blocks on the invoice (order)',1,0),(20,'actionOrderStatusUpdate','Order status update - Event','This hook launches modules when the status of an order changes.',1,0),(21,'displayAdminOrder','Display new elements in the Back Office, tab AdminOrder','This hook launches modules when the AdminOrder tab is displayed in the Back Office',1,0),(22,'displayAdminOrderTabOrder','Display new elements in Back Office, AdminOrder, panel Order','This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Order panel tabs',1,0),(23,'displayAdminOrderTabShip','Display new elements in Back Office, AdminOrder, panel Shipping','This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Shipping panel tabs',1,0),(24,'displayAdminOrderContentOrder','Display new elements in Back Office, AdminOrder, panel Order','This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Order panel content',1,0),(25,'displayAdminOrderContentShip','Display new elements in Back Office, AdminOrder, panel Shipping','This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Shipping panel content',1,0),(26,'displayFooter','Footer','This hook displays new blocks in the footer',1,0),(27,'displayPDFInvoice','PDF Invoice','This hook allows you to display additional information on PDF invoices',1,0),(28,'displayInvoiceLegalFreeText','PDF Invoice - Legal Free Text','This hook allows you to modify the legal free text on PDF invoices',1,0),(29,'displayAdminCustomers','Display new elements in the Back Office, tab AdminCustomers','This hook launches modules when the AdminCustomers tab is displayed in the Back Office',1,0),(30,'displayOrderConfirmation','Order confirmation page','This hook is called within an order\'s confirmation page',1,0),(31,'actionCustomerAccountAdd','Successful customer account creation','This hook is called when a new customer creates an account successfully',1,0),(32,'displayCustomerAccount','Customer account displayed in Front Office','This hook displays new elements on the customer account page',1,0),(33,'displayCustomerIdentityForm','Customer identity form displayed in Front Office','This hook displays new elements on the form to update a customer identity',1,0),(34,'actionOrderSlipAdd','Order slip creation','This hook is called when a new credit slip is added regarding client order',1,0),(35,'displayProductTab','Tabs on product page','This hook is called on the product page\'s tab',1,0),(36,'displayProductTabContent','Tabs content on the product page','This hook is called on the product page\'s tab',1,0),(37,'displayShoppingCartFooter','Shopping cart footer','This hook displays some specific information on the shopping cart\'s page',1,0),(38,'displayCustomerAccountForm','Customer account creation form','This hook displays some information on the form to create a customer account',1,0),(39,'displayAdminStatsModules','Stats - Modules','',1,0),(40,'displayAdminStatsGraphEngine','Graph engines','',1,0),(41,'actionOrderReturn','Returned product','This hook is displayed when a customer returns a product ',1,0),(42,'displayProductButtons','Product page actions','This hook adds new action buttons on the product page',1,0),(43,'displayBackOfficeHome','Administration panel homepage','This hook is displayed on the admin panel\'s homepage',1,0),(44,'displayAdminStatsGridEngine','Grid engines','',1,0),(45,'actionWatermark','Watermark','',1,0),(46,'actionProductCancel','Product cancelled','This hook is called when you cancel a product in an order',1,0),(47,'displayLeftColumnProduct','New elements on the product page (left column)','This hook displays new elements in the left-hand column of the product page',1,0),(48,'actionProductOutOfStock','Out-of-stock product','This hook displays new action buttons if a product is out of stock',1,0),(49,'actionProductAttributeUpdate','Product attribute update','This hook is displayed when a product\'s attribute is updated',1,0),(50,'displayCarrierList','Extra carrier (module mode)','',1,0),(51,'displayShoppingCart','Shopping cart - Additional button','This hook displays new action buttons within the shopping cart',1,0),(52,'actionSearch','Search','',1,0),(53,'displayBeforePayment','Redirect during the order process','This hook redirects the user to the module instead of displaying payment modules',1,0),(54,'actionCarrierUpdate','Carrier Update','This hook is called when a carrier is updated',1,0),(55,'actionOrderStatusPostUpdate','Post update of order status','',1,0),(56,'displayCustomerAccountFormTop','Block above the form for create an account','This hook is displayed above the customer\'s account creation form',1,0),(57,'displayBackOfficeHeader','Administration panel header','This hook is displayed in the header of the admin panel',1,0),(58,'displayBackOfficeTop','Administration panel hover the tabs','This hook is displayed on the roll hover of the tabs within the admin panel',1,0),(59,'displayBackOfficeFooter','Administration panel footer','This hook is displayed within the admin panel\'s footer',1,0),(60,'actionProductAttributeDelete','Product attribute deletion','This hook is displayed when a product\'s attribute is deleted',1,0),(61,'actionCarrierProcess','Carrier process','',1,0),(62,'actionOrderDetail','Order detail','This hook is used to set the follow-up in Smarty when an order\'s detail is called',1,0),(63,'displayBeforeCarrier','Before carriers list','This hook is displayed before the carrier list in Front Office',1,0),(64,'displayOrderDetail','Order detail','This hook is displayed within the order\'s details in Front Office',1,0),(65,'actionPaymentCCAdd','Payment CC added','',1,0),(66,'displayProductComparison','Extra product comparison','',1,0),(67,'actionCategoryAdd','Category creation','This hook is displayed when a category is created',1,0),(68,'actionCategoryUpdate','Category modification','This hook is displayed when a category is modified',1,0),(69,'actionCategoryDelete','Category deletion','This hook is displayed when a category is deleted',1,0),(70,'actionBeforeAuthentication','Before authentication','This hook is displayed before the customer\'s authentication',1,0),(71,'actionSubmitIdentityBefore','Before identity modification','This hook is called before customer\'s identity modification',1,0),(72,'displayCartRoomImageAfter','After cart room image on checkout page','This hook is displayed after cart room image on checkout page',1,0),(73,'displayPaymentTop','Top of payment page','This hook is displayed at the top of the payment page',1,0),(74,'actionHtaccessCreate','After htaccess creation','This hook is displayed after the htaccess creation',1,0),(75,'actionAdminMetaSave','After saving the configuration in AdminMeta','This hook is displayed after saving the configuration in AdminMeta',1,0),(76,'displayAttributeGroupForm','Add fields to the form \'attribute group\'','This hook adds fields to the form \'attribute group\'',1,0),(77,'actionAttributeGroupSave','Saving an attribute group','This hook is called while saving an attributes group',1,0),(78,'actionAttributeGroupDelete','Deleting attribute group','This hook is called while deleting an attributes  group',1,0),(79,'displayFeatureForm','Add fields to the form \'feature\'','This hook adds fields to the form \'feature\'',1,0),(80,'actionFeatureSave','Saving attributes\' features','This hook is called while saving an attributes features',1,0),(81,'actionFeatureDelete','Deleting attributes\' features','This hook is called while deleting an attributes features',1,0),(82,'actionProductSave','Saving products','This hook is called while saving products',1,0),(83,'actionProductListOverride','Assign a products list to a category','This hook assigns a products list to a category',1,0),(84,'displayAttributeGroupPostProcess','On post-process in admin attribute group','This hook is called on post-process in admin attribute group',1,0),(85,'displayFeaturePostProcess','On post-process in admin feature','This hook is called on post-process in admin feature',1,0),(86,'displayFeatureValueForm','Add fields to the form \'feature value\'','This hook adds fields to the form \'feature value\'',1,0),(87,'displayFeatureValuePostProcess','On post-process in admin feature value','This hook is called on post-process in admin feature value',1,0),(88,'actionFeatureValueDelete','Deleting attributes\' features\' values','This hook is called while deleting an attributes features value',1,0),(89,'actionFeatureValueSave','Saving an attributes features value','This hook is called while saving an attributes features value',1,0),(90,'displayAttributeForm','Add fields to the form \'attribute value\'','This hook adds fields to the form \'attribute value\'',1,0),(91,'actionAttributePostProcess','On post-process in admin feature value','This hook is called on post-process in admin feature value',1,0),(92,'actionAttributeDelete','Deleting an attributes features value','This hook is called while deleting an attributes features value',1,0),(93,'actionAttributeSave','Saving an attributes features value','This hook is called while saving an attributes features value',1,0),(94,'actionTaxManager','Tax Manager Factory','',1,0),(95,'displayMyAccountBlock','My account block','This hook displays extra information within the \'my account\' block\"',1,0),(96,'actionModuleInstallBefore','actionModuleInstallBefore','',1,0),(97,'actionModuleInstallAfter','actionModuleInstallAfter','',1,0),(98,'displayHomeTab','Home Page Tabs','This hook displays new elements on the homepage tabs',1,1),(99,'displayHomeTabContent','Home Page Tabs Content','This hook displays new elements on the homepage tabs content',1,1),(100,'displayTopColumn','Top column blocks','This hook displays new elements in the top of columns',1,1),(101,'displayBackOfficeCategory','Display new elements in the Back Office, tab AdminCategories','This hook launches modules when the AdminCategories tab is displayed in the Back Office',1,0),(102,'displayProductListFunctionalButtons','Display new elements in the Front Office, products list','This hook launches modules when the products list is displayed in the Front Office',1,0),(103,'displayNav','Navigation','',1,1),(104,'displayOverrideTemplate','Change the default template of current controller','',1,0),(105,'actionAdminLoginControllerSetMedia','Set media on admin login page header','This hook is called after adding media to admin login page header',1,0),(106,'actionOrderEdited','Order edited','This hook is called when an order is edited.',1,0),(107,'actionEmailAddBeforeContent','Add extra content before mail content','This hook is called just before fetching mail template',1,0),(108,'actionEmailAddAfterContent','Add extra content after mail content','This hook is called just after fetching mail template',1,0),(109,'displayCartExtraProductActions','Extra buttons in shopping cart','This hook adds extra buttons to the product lines, in the shopping cart',1,0),(110,'displayAfterHookTop','displayAfterHookTop','',1,1),(111,'actionOrderHistoryAddAfter','actionOrderHistoryAddAfter','',0,0),(112,'actionObjectProductDeleteBefore','actionObjectProductDeleteBefore','',0,0),(113,'displayAfterDefautlFooterHook','displayAfterDefautlFooterHook','',1,1),(114,'addWebserviceResources','addWebserviceResources','',0,0),(115,'actionObjectLanguageAddAfter','actionObjectLanguageAddAfter','',0,0),(116,'actionObjectProfileAddAfter','actionObjectProfileAddAfter','',0,0),(117,'actionObjectProfileDeleteBefore','actionObjectProfileDeleteBefore','',0,0),(118,'actionObjectGroupDeleteBefore','actionObjectGroupDeleteBefore','',0,0),(119,'actionCartSummary','actionCartSummary','',0,0),(120,'actionFrontControllerSetMedia','actionFrontControllerSetMedia','',0,0),(121,'displayExternalNavigationHook','displayExternalNavigationHook','',1,1),(122,'displayAfterHeaderHotelDesc','displayAfterHeaderHotelDesc','',1,1),(123,'displayAddModuleSettingLink','displayAddModuleSettingLink','',1,1),(124,'displayFooterNotificationHook','displayFooterNotificationHook','',1,1),(125,'registerGDPRConsent','registerGDPRConsent','',0,0),(126,'actionExportGDPRData','actionExportGDPRData','',0,0),(127,'actionObjectCustomerUpdateBefore','actionObjectCustomerUpdateBefore','',0,0),(128,'actionDeleteGDPRCustomer','actionDeleteGDPRCustomer','',0,0),(129,'actionCustomerCartRulesModifier','actionCustomerCartRulesModifier','',0,0),(130,'actionValidateCartRule','actionValidateCartRule','',0,0),(131,'actionObjectCustomerDeleteAfter','actionObjectCustomerDeleteAfter','',0,0),(132,'displayFooterMostLeftBlock','displayFooterMostLeftBlock','',1,1),(133,'addOtherModuleSetting','addOtherModuleSetting','',0,0),(134,'displayFooterExploreSectionHook','displayFooterExploreSectionHook','',1,1),(135,'actionCleanData','actionCleanData','',0,0),(136,'displayPaymentEU','displayPaymentEU','',1,1),(138,'actionModuleRegisterHookAfter','actionModuleRegisterHookAfter','',0,0),(139,'actionModuleUnRegisterHookAfter','actionModuleUnRegisterHookAfter','',0,0),(140,'displayFooterBefore','displayFooterBefore','',1,1),(141,'displayRoomTypeDetailRoomTypeNameAfter','displayRoomTypeDetailRoomTypeNameAfter','',1,1),(142,'actionRoomBookingStatusUpdateAfter','actionRoomBookingStatusUpdateAfter','',0,0),(143,'displayBookingAction','displayBookingAction','',1,1),(144,'displayAdminAfterHeader','displayAdminAfterHeader','',1,1),(145,'displayFooterPaymentInfo','displayFooterPaymentInfo','',1,1),(146,'actionObjectCMSUpdateAfter','actionObjectCMSUpdateAfter','',0,0),(147,'actionObjectCMSDeleteAfter','actionObjectCMSDeleteAfter','',0,0),(148,'displayDefaultNavigationHook','displayDefaultNavigationHook','',1,1),(149,'displayNavigationHook','displayNavigationHook','',1,1),(150,'actionAdminDashboardKPIListingModifier','actionAdminDashboardKPIListingModifier','',0,0),(151,'dashboardZoneTwo','dashboardZoneTwo','',0,0),(152,'dashboardData','dashboardData','',0,0),(153,'actionAdminControllerSetMedia','actionAdminControllerSetMedia','',0,0),(154,'dashboardZoneOne','dashboardZoneOne','',0,0),(155,'actionObjectOrderAddAfter','actionObjectOrderAddAfter','',0,0),(156,'actionObjectCustomerAddAfter','actionObjectCustomerAddAfter','',0,0),(157,'actionObjectCustomerMessageAddAfter','actionObjectCustomerMessageAddAfter','',0,0),(158,'actionObjectCustomerThreadAddAfter','actionObjectCustomerThreadAddAfter','',0,0),(159,'actionObjectOrderReturnAddAfter','actionObjectOrderReturnAddAfter','',0,0),(160,'actionCartListOverride','actionCartListOverride','',0,0),(161,'displayAdminListBefore','displayAdminListBefore','',1,1),(162,'moduleRoutes','moduleRoutes','',0,0),(163,'actionPulseEvent','actionPulseEvent','',0,0);
/*!40000 ALTER TABLE `qlo_hook` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_hook_alias`
--

DROP TABLE IF EXISTS `qlo_hook_alias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_hook_alias` (
  `id_hook_alias` int unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id_hook_alias`),
  UNIQUE KEY `alias` (`alias`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_hook_alias`
--

LOCK TABLES `qlo_hook_alias` WRITE;
/*!40000 ALTER TABLE `qlo_hook_alias` DISABLE KEYS */;
INSERT INTO `qlo_hook_alias` VALUES (1,'payment','displayPayment'),(2,'newOrder','actionValidateOrder'),(3,'paymentConfirm','actionPaymentConfirmation'),(4,'paymentReturn','displayPaymentReturn'),(5,'updateQuantity','actionUpdateQuantity'),(6,'rightColumn','displayRightColumn'),(7,'leftColumn','displayLeftColumn'),(8,'home','displayHome'),(9,'displayHeader','Header'),(10,'cart','actionCartSave'),(11,'authentication','actionAuthentication'),(12,'addproduct','actionProductAdd'),(13,'updateproduct','actionProductUpdate'),(14,'top','displayTop'),(15,'extraRight','displayRightColumnProduct'),(16,'deleteproduct','actionProductDelete'),(17,'productfooter','displayFooterProduct'),(18,'invoice','displayInvoice'),(19,'updateOrderStatus','actionOrderStatusUpdate'),(20,'adminOrder','displayAdminOrder'),(21,'footer','displayFooter'),(22,'PDFInvoice','displayPDFInvoice'),(23,'adminCustomers','displayAdminCustomers'),(24,'orderConfirmation','displayOrderConfirmation'),(25,'createAccount','actionCustomerAccountAdd'),(26,'customerAccount','displayCustomerAccount'),(27,'orderSlip','actionOrderSlipAdd'),(28,'productTab','displayProductTab'),(29,'productTabContent','displayProductTabContent'),(30,'shoppingCart','displayShoppingCartFooter'),(31,'createAccountForm','displayCustomerAccountForm'),(32,'AdminStatsModules','displayAdminStatsModules'),(33,'GraphEngine','displayAdminStatsGraphEngine'),(34,'orderReturn','actionOrderReturn'),(35,'productActions','displayProductButtons'),(36,'backOfficeHome','displayBackOfficeHome'),(37,'GridEngine','displayAdminStatsGridEngine'),(38,'watermark','actionWatermark'),(39,'cancelProduct','actionProductCancel'),(40,'extraLeft','displayLeftColumnProduct'),(41,'productOutOfStock','actionProductOutOfStock'),(42,'updateProductAttribute','actionProductAttributeUpdate'),(43,'extraCarrier','displayCarrierList'),(44,'shoppingCartExtra','displayShoppingCart'),(45,'search','actionSearch'),(46,'backBeforePayment','displayBeforePayment'),(47,'updateCarrier','actionCarrierUpdate'),(48,'postUpdateOrderStatus','actionOrderStatusPostUpdate'),(49,'createAccountTop','displayCustomerAccountFormTop'),(50,'backOfficeHeader','displayBackOfficeHeader'),(51,'backOfficeTop','displayBackOfficeTop'),(52,'backOfficeFooter','displayBackOfficeFooter'),(53,'deleteProductAttribute','actionProductAttributeDelete'),(54,'processCarrier','actionCarrierProcess'),(55,'orderDetail','actionOrderDetail'),(56,'beforeCarrier','displayBeforeCarrier'),(57,'orderDetailDisplayed','displayOrderDetail'),(58,'paymentCCAdded','actionPaymentCCAdd'),(59,'extraProductComparison','displayProductComparison'),(60,'categoryAddition','actionCategoryAdd'),(61,'categoryUpdate','actionCategoryUpdate'),(62,'categoryDeletion','actionCategoryDelete'),(63,'beforeAuthentication','actionBeforeAuthentication'),(64,'paymentTop','displayPaymentTop'),(65,'afterCreateHtaccess','actionHtaccessCreate'),(66,'afterSaveAdminMeta','actionAdminMetaSave'),(67,'attributeGroupForm','displayAttributeGroupForm'),(68,'afterSaveAttributeGroup','actionAttributeGroupSave'),(69,'afterDeleteAttributeGroup','actionAttributeGroupDelete'),(70,'featureForm','displayFeatureForm'),(71,'afterSaveFeature','actionFeatureSave'),(72,'afterDeleteFeature','actionFeatureDelete'),(73,'afterSaveProduct','actionProductSave'),(74,'productListAssign','actionProductListOverride'),(75,'postProcessAttributeGroup','displayAttributeGroupPostProcess'),(76,'postProcessFeature','displayFeaturePostProcess'),(77,'featureValueForm','displayFeatureValueForm'),(78,'postProcessFeatureValue','displayFeatureValuePostProcess'),(79,'afterDeleteFeatureValue','actionFeatureValueDelete'),(80,'afterSaveFeatureValue','actionFeatureValueSave'),(81,'attributeForm','displayAttributeForm'),(82,'postProcessAttribute','actionAttributePostProcess'),(83,'afterDeleteAttribute','actionAttributeDelete'),(84,'afterSaveAttribute','actionAttributeSave'),(85,'taxManager','actionTaxManager'),(86,'myAccountBlock','displayMyAccountBlock');
/*!40000 ALTER TABLE `qlo_hook_alias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_hook_module`
--

DROP TABLE IF EXISTS `qlo_hook_module`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_hook_module` (
  `id_module` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_hook` int unsigned NOT NULL,
  `position` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id_module`,`id_hook`,`id_shop`),
  KEY `id_hook` (`id_hook`),
  KEY `id_module` (`id_module`),
  KEY `position` (`id_shop`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_hook_module`
--

LOCK TABLES `qlo_hook_module` WRITE;
/*!40000 ALTER TABLE `qlo_hook_module` DISABLE KEYS */;
INSERT INTO `qlo_hook_module` VALUES (1,1,10,1),(1,1,15,1),(1,1,26,1),(1,1,55,1),(1,1,57,1),(1,1,103,1),(1,1,110,1),(1,1,111,1),(1,1,112,1),(1,1,113,1),(1,1,114,1),(1,1,115,1),(1,1,116,1),(1,1,117,1),(1,1,118,1),(1,1,119,1),(1,1,120,1),(1,1,121,1),(2,1,8,1),(2,1,122,1),(2,1,123,1),(3,1,26,1),(3,1,31,1),(3,1,124,1),(3,1,125,1),(3,1,126,1),(3,1,127,1),(3,1,128,1),(3,1,129,1),(3,1,130,1),(3,1,131,1),(4,1,132,1),(5,1,133,1),(6,1,9,1),(6,1,134,1),(8,1,17,1),(8,1,82,1),(8,1,135,1),(10,1,1,1),(10,1,5,1),(10,1,136,1),(12,1,138,1),(12,1,139,1),(15,1,35,1),(15,1,36,1),(15,1,140,1),(15,1,141,1),(15,1,142,1),(15,1,143,1),(15,1,144,1),(18,1,145,1),(20,1,146,1),(20,1,147,1),(20,1,148,1),(20,1,149,1),(21,1,150,1),(21,1,151,1),(21,1,152,1),(21,1,153,1),(22,1,154,1),(23,1,155,1),(23,1,156,1),(23,1,157,1),(23,1,158,1),(23,1,159,1),(27,1,52,1),(30,1,40,1),(32,1,39,1),(51,1,160,1),(53,1,2,1),(53,1,161,1),(54,1,162,1),(54,1,163,1),(55,1,58,1),(2,1,10,2),(2,1,110,2),(2,1,120,2),(5,1,8,2),(6,1,115,2),(7,1,9,2),(7,1,134,2),(11,1,1,2),(11,1,5,2),(11,1,136,2),(13,1,103,2),(13,1,121,2),(15,1,57,2),(15,1,135,2),(20,1,15,2),(22,1,152,2),(22,1,153,2),(24,1,55,2),(24,1,151,2),(27,1,155,2),(29,1,154,2),(33,1,39,2),(3,1,10,3),(7,1,115,3),(8,1,9,3),(8,1,134,3),(12,1,8,3),(13,1,120,3),(14,1,103,3),(14,1,121,3),(16,1,26,3),(23,1,152,3),(23,1,153,3),(23,1,154,3),(25,1,151,3),(34,1,39,3),(35,1,15,3),(53,1,57,3),(1,1,8,4),(5,1,10,4),(8,1,115,4),(9,1,9,4),(9,1,134,4),(14,1,120,4),(17,1,26,4),(24,1,152,4),(24,1,153,4),(26,1,151,4),(35,1,39,4),(44,1,15,4),(52,1,121,4),(54,1,57,4),(9,1,115,5),(12,1,10,5),(15,1,120,5),(19,1,26,5),(25,1,152,5),(25,1,153,5),(27,1,151,5),(36,1,39,5),(51,1,15,5),(55,1,57,5),(20,1,26,6),(20,1,115,6),(26,1,152,6),(26,1,153,6),(29,1,151,6),(37,1,39,6),(51,1,10,6),(52,1,15,6),(27,1,152,7),(27,1,153,7),(28,1,151,7),(31,1,26,7),(38,1,39,7),(52,1,10,7),(28,1,152,8),(28,1,153,8),(39,1,39,8),(29,1,152,9),(29,1,153,9),(40,1,39,9),(30,1,153,10),(41,1,39,10),(38,1,153,11),(42,1,39,11),(43,1,39,12),(53,1,153,12),(44,1,39,13),(54,1,153,13),(45,1,39,14),(55,1,153,14),(46,1,39,15),(47,1,39,16),(48,1,39,17),(49,1,39,18),(50,1,39,19);
/*!40000 ALTER TABLE `qlo_hook_module` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_hook_module_exceptions`
--

DROP TABLE IF EXISTS `qlo_hook_module_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_hook_module_exceptions` (
  `id_hook_module_exceptions` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_module` int unsigned NOT NULL,
  `id_hook` int unsigned NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_hook_module_exceptions`),
  KEY `id_module` (`id_module`),
  KEY `id_hook` (`id_hook`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_hook_module_exceptions`
--

LOCK TABLES `qlo_hook_module_exceptions` WRITE;
/*!40000 ALTER TABLE `qlo_hook_module_exceptions` DISABLE KEYS */;
INSERT INTO `qlo_hook_module_exceptions` VALUES (1,1,12,8,'category');
/*!40000 ALTER TABLE `qlo_hook_module_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_access`
--

DROP TABLE IF EXISTS `qlo_htl_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_access` (
  `id_profile` int unsigned NOT NULL,
  `id_hotel` int unsigned NOT NULL,
  `access` int NOT NULL,
  PRIMARY KEY (`id_profile`,`id_hotel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_access`
--

LOCK TABLES `qlo_htl_access` WRITE;
/*!40000 ALTER TABLE `qlo_htl_access` DISABLE KEYS */;
INSERT INTO `qlo_htl_access` VALUES (1,1,1);
/*!40000 ALTER TABLE `qlo_htl_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_advance_payment`
--

DROP TABLE IF EXISTS `qlo_htl_advance_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_advance_payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `payment_type` tinyint NOT NULL,
  `value` decimal(20,6) NOT NULL,
  `id_currency` int NOT NULL,
  `tax_include` tinyint NOT NULL,
  `calculate_from` tinyint NOT NULL,
  `active` tinyint NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_advance_payment`
--

LOCK TABLES `qlo_htl_advance_payment` WRITE;
/*!40000 ALTER TABLE `qlo_htl_advance_payment` DISABLE KEYS */;
INSERT INTO `qlo_htl_advance_payment` VALUES (1,1,0,0.000000,0,0,0,0,'2026-09-03 18:49:04','2026-09-03 18:49:04'),(2,2,0,0.000000,0,0,0,0,'2026-09-03 18:49:13','2026-09-03 18:49:13'),(3,3,0,0.000000,0,0,0,0,'2026-09-03 18:49:21','2026-09-03 18:49:21'),(4,4,0,0.000000,0,0,0,0,'2026-09-03 18:49:28','2026-09-03 18:49:28');
/*!40000 ALTER TABLE `qlo_htl_advance_payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_amenity`
--

DROP TABLE IF EXISTS `qlo_htl_amenity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_amenity` (
  `id_amenity` int unsigned NOT NULL AUTO_INCREMENT,
  `id_parent` int unsigned NOT NULL,
  `position` int unsigned NOT NULL,
  `active` int NOT NULL DEFAULT '0',
  `logo_type` varchar(10) NOT NULL DEFAULT 'icon',
  `logo` varchar(255) NOT NULL DEFAULT '',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_amenity`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_amenity`
--

LOCK TABLES `qlo_htl_amenity` WRITE;
/*!40000 ALTER TABLE `qlo_htl_amenity` DISABLE KEYS */;
INSERT INTO `qlo_htl_amenity` VALUES (1,0,1,1,'','','2026-09-03 18:48:19','2026-09-03 18:48:19'),(2,1,0,1,'image','2.jpg','2026-09-03 18:48:19','2026-09-03 18:48:20'),(3,1,0,1,'image','3.jpg','2026-09-03 18:48:20','2026-09-03 18:48:20'),(4,1,0,1,'image','4.jpg','2026-09-03 18:48:20','2026-09-03 18:48:20'),(5,1,0,1,'image','5.jpg','2026-09-03 18:48:20','2026-09-03 18:48:20'),(6,1,0,1,'image','6.jpg','2026-09-03 18:48:20','2026-09-03 18:48:21'),(7,1,0,1,'image','7.jpg','2026-09-03 18:48:21','2026-09-03 18:48:21'),(8,1,0,1,'image','8.jpg','2026-09-03 18:48:21','2026-09-03 18:48:21'),(9,0,2,1,'','','2026-09-03 18:48:21','2026-09-03 18:48:21'),(10,9,0,1,'image','10.jpg','2026-09-03 18:48:21','2026-09-03 18:48:21'),(11,9,0,1,'image','11.jpg','2026-09-03 18:48:21','2026-09-03 18:48:21'),(12,9,0,1,'image','12.jpg','2026-09-03 18:48:22','2026-09-03 18:48:22'),(13,9,0,1,'image','13.jpg','2026-09-03 18:48:22','2026-09-03 18:48:22'),(14,9,0,1,'image','14.jpg','2026-09-03 18:48:22','2026-09-03 18:48:22'),(15,0,3,1,'','','2026-09-03 18:48:22','2026-09-03 18:48:22'),(16,15,0,1,'image','16.jpg','2026-09-03 18:48:22','2026-09-03 18:48:22'),(17,15,0,1,'image','17.jpg','2026-09-03 18:48:22','2026-09-03 18:48:23'),(18,15,0,1,'image','18.jpg','2026-09-03 18:48:23','2026-09-03 18:48:23'),(19,15,0,1,'image','19.jpg','2026-09-03 18:48:23','2026-09-03 18:48:23'),(20,15,0,1,'image','20.jpg','2026-09-03 18:48:23','2026-09-03 18:48:23'),(21,15,0,1,'image','21.jpg','2026-09-03 18:48:23','2026-09-03 18:48:24'),(22,15,0,1,'image','22.jpg','2026-09-03 18:48:24','2026-09-03 18:48:24'),(23,0,4,1,'','','2026-09-03 18:48:24','2026-09-03 18:48:24'),(24,23,0,1,'image','24.jpg','2026-09-03 18:48:24','2026-09-03 18:48:24'),(25,23,0,1,'image','25.jpg','2026-09-03 18:48:24','2026-09-03 18:48:24'),(26,23,0,1,'image','26.jpg','2026-09-03 18:48:24','2026-09-03 18:48:24'),(27,23,0,1,'image','27.jpg','2026-09-03 18:48:24','2026-09-03 18:48:25'),(28,23,0,1,'image','28.jpg','2026-09-03 18:48:25','2026-09-03 18:48:25'),(29,23,0,1,'image','29.jpg','2026-09-03 18:48:25','2026-09-03 18:48:25'),(30,23,0,1,'image','30.jpg','2026-09-03 18:48:25','2026-09-03 18:48:25'),(31,23,0,1,'image','31.jpg','2026-09-03 18:48:25','2026-09-03 18:48:25'),(32,23,0,1,'image','32.jpg','2026-09-03 18:48:25','2026-09-03 18:48:26'),(33,23,0,1,'image','33.jpg','2026-09-03 18:48:26','2026-09-03 18:48:26'),(34,23,0,1,'image','34.jpg','2026-09-03 18:48:26','2026-09-03 18:48:26'),(35,23,0,1,'image','35.jpg','2026-09-03 18:48:26','2026-09-03 18:48:26'),(36,23,0,1,'image','36.jpg','2026-09-03 18:48:26','2026-09-03 18:48:26'),(37,0,5,1,'','','2026-09-03 18:48:26','2026-09-03 18:48:26'),(38,37,0,1,'image','38.jpg','2026-09-03 18:48:27','2026-09-03 18:48:27'),(39,37,0,1,'image','39.jpg','2026-09-03 18:48:27','2026-09-03 18:48:27'),(40,37,0,1,'image','40.jpg','2026-09-03 18:48:27','2026-09-03 18:48:28'),(41,37,0,1,'image','41.jpg','2026-09-03 18:48:28','2026-09-03 18:48:28'),(42,37,0,1,'image','42.jpg','2026-09-03 18:48:28','2026-09-03 18:48:29'),(43,37,0,1,'image','43.jpg','2026-09-03 18:48:29','2026-09-03 18:48:29'),(44,37,0,1,'image','44.jpg','2026-09-03 18:48:29','2026-09-03 18:48:30'),(45,37,0,1,'image','45.jpg','2026-09-03 18:48:30','2026-09-03 18:48:30'),(46,37,0,1,'image','46.jpg','2026-09-03 18:48:30','2026-09-03 18:48:31'),(47,0,6,1,'','','2026-09-03 18:48:31','2026-09-03 18:48:31'),(48,47,0,1,'image','48.jpg','2026-09-03 18:48:31','2026-09-03 18:48:31'),(49,47,0,1,'image','49.jpg','2026-09-03 18:48:31','2026-09-03 18:48:32'),(50,47,0,1,'image','50.jpg','2026-09-03 18:48:32','2026-09-03 18:48:32'),(51,0,7,1,'','','2026-09-03 18:48:32','2026-09-03 18:48:32'),(52,51,0,1,'image','52.jpg','2026-09-03 18:48:32','2026-09-03 18:48:33'),(53,51,0,1,'image','53.jpg','2026-09-03 18:48:33','2026-09-03 18:48:33'),(54,0,8,1,'','','2026-09-03 18:48:33','2026-09-03 18:48:33'),(55,54,0,1,'image','55.jpg','2026-09-03 18:48:33','2026-09-03 18:48:34'),(56,54,0,1,'image','56.jpg','2026-09-03 18:48:34','2026-09-03 18:48:34'),(57,54,0,1,'image','57.jpg','2026-09-03 18:48:34','2026-09-03 18:48:35'),(58,54,0,1,'image','58.jpg','2026-09-03 18:48:35','2026-09-03 18:48:35'),(59,54,0,1,'image','59.jpg','2026-09-03 18:48:35','2026-09-03 18:48:36'),(60,54,0,1,'image','60.jpg','2026-09-03 18:48:36','2026-09-03 18:48:36'),(61,54,0,1,'image','61.jpg','2026-09-03 18:48:36','2026-09-03 18:48:37'),(62,54,0,1,'image','62.jpg','2026-09-03 18:48:37','2026-09-03 18:48:37'),(63,54,0,1,'image','63.jpg','2026-09-03 18:48:37','2026-09-03 18:48:38'),(64,0,9,1,'','','2026-09-03 18:48:38','2026-09-03 18:48:38'),(65,64,0,1,'image','65.jpg','2026-09-03 18:48:38','2026-09-03 18:48:38'),(66,64,0,1,'image','66.jpg','2026-09-03 18:48:38','2026-09-03 18:48:39'),(67,64,0,1,'image','67.jpg','2026-09-03 18:48:39','2026-09-03 18:48:40'),(68,0,10,1,'','','2026-09-03 18:48:40','2026-09-03 18:48:40'),(69,68,0,1,'image','69.jpg','2026-09-03 18:48:40','2026-09-03 18:48:40'),(70,68,0,1,'image','70.jpg','2026-09-03 18:48:40','2026-09-03 18:48:41'),(71,68,0,1,'image','71.jpg','2026-09-03 18:48:41','2026-09-03 18:48:41'),(72,68,0,1,'image','72.jpg','2026-09-03 18:48:41','2026-09-03 18:48:42'),(73,68,0,1,'image','73.jpg','2026-09-03 18:48:42','2026-09-03 18:48:42'),(74,68,0,1,'image','74.jpg','2026-09-03 18:48:42','2026-09-03 18:48:43'),(75,68,0,1,'image','75.jpg','2026-09-03 18:48:43','2026-09-03 18:48:43'),(76,68,0,1,'image','76.jpg','2026-09-03 18:48:44','2026-09-03 18:48:44'),(77,68,0,1,'image','77.jpg','2026-09-03 18:48:44','2026-09-03 18:48:44'),(78,68,0,1,'image','78.jpg','2026-09-03 18:48:45','2026-09-03 18:48:45'),(79,68,0,1,'','','2026-09-03 18:48:45','2026-09-03 18:48:45'),(80,68,0,1,'image','80.jpg','2026-09-03 18:48:45','2026-09-03 18:48:45'),(81,68,0,1,'image','81.jpg','2026-09-03 18:48:45','2026-09-03 18:48:46'),(82,68,0,1,'image','82.jpg','2026-09-03 18:48:46','2026-09-03 18:48:46'),(83,68,0,1,'image','83.jpg','2026-09-03 18:48:46','2026-09-03 18:48:47'),(84,68,0,1,'image','84.jpg','2026-09-03 18:48:47','2026-09-03 18:48:47'),(85,68,0,1,'image','85.jpg','2026-09-03 18:48:47','2026-09-03 18:48:48'),(86,68,0,1,'image','86.jpg','2026-09-03 18:48:48','2026-09-03 18:48:48'),(87,68,0,1,'image','87.jpg','2026-09-03 18:48:48','2026-09-03 18:48:49'),(88,68,0,1,'image','88.jpg','2026-09-03 18:48:49','2026-09-03 18:48:49'),(89,68,0,1,'image','89.jpg','2026-09-03 18:48:49','2026-09-03 18:48:49'),(90,68,0,1,'image','90.jpg','2026-09-03 18:48:49','2026-09-03 18:48:49'),(91,68,0,1,'image','91.jpg','2026-09-03 18:48:49','2026-09-03 18:48:49'),(92,0,11,1,'','','2026-09-03 18:48:50','2026-09-03 18:48:50'),(93,92,0,1,'image','93.jpg','2026-09-03 18:48:50','2026-09-03 18:48:50'),(94,92,0,1,'image','94.jpg','2026-09-03 18:48:50','2026-09-03 18:48:50'),(95,92,0,1,'image','95.jpg','2026-09-03 18:48:50','2026-09-03 18:48:50'),(96,92,0,1,'image','96.jpg','2026-09-03 18:48:50','2026-09-03 18:48:50'),(97,92,0,1,'image','97.jpg','2026-09-03 18:48:50','2026-09-03 18:48:51'),(98,0,12,1,'','','2026-09-03 18:48:51','2026-09-03 18:48:51'),(99,98,0,1,'image','99.jpg','2026-09-03 18:48:51','2026-09-03 18:48:51'),(100,98,0,1,'image','100.jpg','2026-09-03 18:48:51','2026-09-03 18:48:51'),(101,98,0,1,'image','101.jpg','2026-09-03 18:48:51','2026-09-03 18:48:51'),(102,98,0,1,'image','102.jpg','2026-09-03 18:48:51','2026-09-03 18:48:51'),(103,98,0,1,'image','103.jpg','2026-09-03 18:48:52','2026-09-03 18:48:52'),(104,98,0,1,'image','104.jpg','2026-09-03 18:48:52','2026-09-03 18:48:52'),(105,98,0,1,'image','105.jpg','2026-09-03 18:48:52','2026-09-03 18:48:52'),(106,98,0,1,'image','106.jpg','2026-09-03 18:48:52','2026-09-03 18:48:52'),(107,98,0,1,'image','107.jpg','2026-09-03 18:48:52','2026-09-03 18:48:53'),(108,98,0,1,'image','108.jpg','2026-09-03 18:48:53','2026-09-03 18:48:53'),(109,98,0,1,'image','109.jpg','2026-09-03 18:48:53','2026-09-03 18:48:53'),(110,98,0,1,'image','110.jpg','2026-09-03 18:48:53','2026-09-03 18:48:53');
/*!40000 ALTER TABLE `qlo_htl_amenity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_amenity_lang`
--

DROP TABLE IF EXISTS `qlo_htl_amenity_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_amenity_lang` (
  `id_amenity` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_amenity`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_amenity_lang`
--

LOCK TABLES `qlo_htl_amenity_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_amenity_lang` DISABLE KEYS */;
INSERT INTO `qlo_htl_amenity_lang` VALUES (1,1,'Business Services'),(2,1,'Business Center'),(3,1,'Audio-Visual Equipment'),(4,1,'Board room'),(5,1,'Conference Facilities'),(6,1,'Secretarial Services'),(7,1,'Fax Machine'),(8,1,'Internet Access'),(9,1,'Complementary'),(10,1,'Internet Access Free'),(11,1,'Transfer Available'),(12,1,'NewsPaper In Lobby'),(13,1,'Shopping Drop Facility'),(14,1,'Welcome Drinks'),(15,1,'Entertainment'),(16,1,'DiscoTheatre'),(17,1,'Casino'),(18,1,'Amphitheatre'),(19,1,'Dance Performances(On Demand)'),(20,1,'Karoke'),(21,1,'Mini Theatre'),(22,1,'Night Club'),(23,1,'Facilities'),(24,1,'Laundry Service'),(25,1,'Power Backup'),(26,1,'ATM/Banking'),(27,1,'Currency Exchange'),(28,1,'Dry Cleaning'),(29,1,'Library'),(30,1,'Doctor On Call'),(31,1,'Party Hall'),(32,1,'Yoga Hall'),(33,1,'Pets Allowed'),(34,1,'Kids Play Zone'),(35,1,'Wedding Services Facilities'),(36,1,'Fire Place Available'),(37,1,'General Services'),(38,1,'Room Service'),(39,1,'Cook Service'),(40,1,'Car Rental'),(41,1,'Door Man'),(42,1,'Grocery'),(43,1,'Medical Assistance'),(44,1,'Postal Services'),(45,1,'Spa Services'),(46,1,'Multilingual Staff'),(47,1,'Indoors'),(48,1,'Parking'),(49,1,'Solarium'),(50,1,'Veranda'),(51,1,'Internet'),(52,1,'Internet Access-Surcharge'),(53,1,'Internet / Fax (Reception area only)'),(54,1,'Outdoors'),(55,1,'Gardens'),(56,1,'Outdoor Parking - Secured'),(57,1,'Barbecue AreaCampfire / Bon Fire'),(58,1,'Childrens Park'),(59,1,'Fishing'),(60,1,'Golf Course'),(61,1,'Outdoor Parking - Non Secured'),(62,1,'Private Beach'),(63,1,'Rooftop Garden'),(64,1,'Parking'),(65,1,'Parking (Surcharge)'),(66,1,'Parking Facilities Available'),(67,1,'Valet service'),(68,1,'Sports And Recreation'),(69,1,'Health Club / Gym Facility Available'),(70,1,'Bike on Rent'),(71,1,'Badminttion Court'),(72,1,'Basketball Court'),(73,1,'Billiards'),(74,1,'Boating'),(75,1,'Bowling'),(76,1,'Camel Ride'),(77,1,'Clubhouse'),(78,1,'Fitness Equipment'),(79,1,'Fun Floats'),(80,1,'Games Zone'),(81,1,'Horse Ride ( Chargeable )'),(82,1,'Marina On Site'),(83,1,'Nature Walk'),(84,1,'Pool Table'),(85,1,'Safari'),(86,1,'Skiing Facility'),(87,1,'Available Spa Services'),(88,1,'Nearby Squash court'),(89,1,'Table Tennis'),(90,1,'Tennis Court'),(91,1,'Virtual Golf'),(92,1,'Water Amenities'),(93,1,'Swimming Pool'),(94,1,'Jacuzzi'),(95,1,'Private / Plunge Pool'),(96,1,'Sauna'),(97,1,'Whirlpool Bath / Shower Cubicle'),(98,1,'Wine And Dine'),(99,1,'Bar / Lounge'),(100,1,'Multi Cuisine Restaurant'),(101,1,'Catering'),(102,1,'Coffee Shop / Cafe'),(103,1,'Food Facility'),(104,1,'Hookah Lounge'),(105,1,'Kitchen available (home cook food on request)'),(106,1,'Open Air Restaurant / Dining'),(107,1,'Pool Cafe'),(108,1,'Poolside Bar'),(109,1,'Restaurant Veg / Non Veg Kitchens Separate'),(110,1,'Vegetarian Food / Jain Food Available');
/*!40000 ALTER TABLE `qlo_htl_amenity_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_booking_demands`
--

DROP TABLE IF EXISTS `qlo_htl_booking_demands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_booking_demands` (
  `id_booking_demand` int NOT NULL AUTO_INCREMENT,
  `id_htl_booking` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `unit_price_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unit_price_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_price_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_price_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `price_calc_method` int unsigned DEFAULT '0',
  `id_tax_rules_group` int unsigned DEFAULT '0',
  `tax_computation_method` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_booking_demand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_booking_demands`
--

LOCK TABLES `qlo_htl_booking_demands` WRITE;
/*!40000 ALTER TABLE `qlo_htl_booking_demands` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_booking_demands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_booking_demands_tax`
--

DROP TABLE IF EXISTS `qlo_htl_booking_demands_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_booking_demands_tax` (
  `id_booking_demand` int NOT NULL AUTO_INCREMENT,
  `id_tax` int NOT NULL,
  `unit_amount` decimal(16,6) NOT NULL DEFAULT '0.000000',
  `total_amount` decimal(16,6) NOT NULL DEFAULT '0.000000',
  PRIMARY KEY (`id_booking_demand`,`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_booking_demands_tax`
--

LOCK TABLES `qlo_htl_booking_demands_tax` WRITE;
/*!40000 ALTER TABLE `qlo_htl_booking_demands_tax` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_booking_demands_tax` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_booking_detail`
--

DROP TABLE IF EXISTS `qlo_htl_booking_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_booking_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `id_order` int NOT NULL,
  `id_order_detail` int NOT NULL,
  `id_cart` int NOT NULL,
  `id_room` int NOT NULL,
  `id_hotel` int NOT NULL,
  `id_customer` int NOT NULL,
  `booking_type` tinyint NOT NULL,
  `id_status` int NOT NULL,
  `comment` text NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime NOT NULL,
  `planned_check_out` datetime NOT NULL,
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `total_price_tax_excl` decimal(20,6) NOT NULL,
  `total_price_tax_incl` decimal(20,6) NOT NULL,
  `total_paid_amount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `is_back_order` tinyint NOT NULL,
  `hotel_name` varchar(255) DEFAULT NULL,
  `room_type_name` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zipcode` varchar(12) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `check_in_time` varchar(32) DEFAULT NULL,
  `check_out_time` varchar(32) DEFAULT NULL,
  `room_num` varchar(225) DEFAULT NULL,
  `adults` smallint NOT NULL DEFAULT '0',
  `children` smallint NOT NULL DEFAULT '0',
  `child_ages` text NOT NULL,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_booking_detail`
--

LOCK TABLES `qlo_htl_booking_detail` WRITE;
/*!40000 ALTER TABLE `qlo_htl_booking_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_booking_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_booking_document`
--

DROP TABLE IF EXISTS `qlo_htl_booking_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_booking_document` (
  `id_htl_booking_document` int NOT NULL AUTO_INCREMENT,
  `id_htl_booking` int NOT NULL,
  `title` varchar(32) NOT NULL DEFAULT '',
  `file_type` tinyint(1) NOT NULL DEFAULT '0',
  `file_name` varchar(8) NOT NULL DEFAULT '',
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_htl_booking_document`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_booking_document`
--

LOCK TABLES `qlo_htl_booking_document` WRITE;
/*!40000 ALTER TABLE `qlo_htl_booking_document` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_booking_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_branch_amenity`
--

DROP TABLE IF EXISTS `qlo_htl_branch_amenity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_branch_amenity` (
  `id_branch_amenity` int unsigned NOT NULL AUTO_INCREMENT,
  `id_hotel` int unsigned NOT NULL,
  `amenity_id` int unsigned NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_branch_amenity`),
  KEY `fk_htl_branch_amenity_amenity` (`amenity_id`),
  CONSTRAINT `fk_htl_branch_amenity_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `qlo_htl_amenity` (`id_amenity`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_branch_amenity`
--

LOCK TABLES `qlo_htl_branch_amenity` WRITE;
/*!40000 ALTER TABLE `qlo_htl_branch_amenity` DISABLE KEYS */;
INSERT INTO `qlo_htl_branch_amenity` VALUES (1,1,2,0,'2026-09-03 18:48:54','2026-09-03 18:48:54'),(2,1,4,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(3,1,7,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(4,1,8,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(5,1,11,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(6,1,12,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(7,1,14,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(8,1,16,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(9,1,17,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(10,1,18,0,'2026-09-03 18:48:55','2026-09-03 18:48:55'),(11,1,21,0,'2026-09-03 18:48:55','2026-09-03 18:48:55');
/*!40000 ALTER TABLE `qlo_htl_branch_amenity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_branch_info`
--

DROP TABLE IF EXISTS `qlo_htl_branch_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_branch_info` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_category` int unsigned NOT NULL,
  `email` varchar(128) NOT NULL,
  `check_in` varchar(255) DEFAULT NULL,
  `check_out` varchar(255) DEFAULT NULL,
  `rating` int unsigned NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `map_formated_address` text NOT NULL,
  `map_input_text` text NOT NULL,
  `active_refund` tinyint unsigned NOT NULL DEFAULT '0',
  `fax` varchar(255) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_branch_info`
--

LOCK TABLES `qlo_htl_branch_info` WRITE;
/*!40000 ALTER TABLE `qlo_htl_branch_info` DISABLE KEYS */;
INSERT INTO `qlo_htl_branch_info` VALUES (1,7,'hotelprime@htl.com','12:00','11:00',3,1,0.00000000,0.00000000,'','',0,'','2026-09-03 18:48:53','2026-09-03 18:48:54');
/*!40000 ALTER TABLE `qlo_htl_branch_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_branch_info_lang`
--

DROP TABLE IF EXISTS `qlo_htl_branch_info_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_branch_info_lang` (
  `id` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `hotel_name` varchar(255) DEFAULT NULL,
  `short_description` text,
  `description` text,
  `policies` text,
  PRIMARY KEY (`id`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_branch_info_lang`
--

LOCK TABLES `qlo_htl_branch_info_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_branch_info_lang` DISABLE KEYS */;
INSERT INTO `qlo_htl_branch_info_lang` VALUES (1,1,'The Hotel Prime','The Hotel Prime is the perfect destination for both business and leisure travelers seeking a memorable stay.','<div>\r\n                <h4><strong>Welcome to The Hotel Prime!</strong></h4>\r\n                <br />\r\n                <div>Our hotel is the perfect destination for both business and leisure travelers seeking a memorable stay.</div>\r\n                <br />\r\n                <div>Benefits of staying at The Hotel Prime!</div>\r\n                <br />\r\n                <div><strong>Accommodation:</strong></div>\r\n                <div>Indulge in our well-appointed rooms and suites, exquisitely designed to provide a tranquil haven after a long\r\n                    day of exploration or meetings. Each room is tastefully furnished with modern amenities, including a plush bed,\r\n                    a spacious work desk, high-speed Wi-Fi, a TV, and a private bathroom adorned with luxurious toiletries.</div>\r\n                <br />\r\n                <div><strong>Dining:</strong></div>\r\n                <div>Savor a delightful culinary experience at our onsite restaurant, where our world-class chefs craft delectable\r\n                    dishes using the finest ingredients. Whether you crave international flavors or local specialties, our diverse\r\n                    menu is sure to satisfy every palate. Enjoy a romantic dinner for two or gather with friends and family in our\r\n                    inviting dining ambiance.</div>\r\n                <br />\r\n                <div><strong>Facilities:</strong></div>\r\n                <div>We believe in providing our guests with a range of facilities to enhance their stay. Take a refreshing dip in\r\n                    our sparkling swimming pool, work up a sweat in our state-of-the-art fitness center, or unwind with a\r\n                    rejuvenating spa treatment. Our attentive staff is always on hand to ensure your needs are met with utmost care\r\n                    and professionalism.</div>\r\n                <br />\r\n                <div><strong>Events and Meetings:</strong></div>\r\n                <div>Host your next corporate event or special occasion in our versatile event spaces, equipped with the latest\r\n                    audiovisual technology and flexible seating arrangements. From intimate boardroom meetings to grand\r\n                    celebrations, our dedicated event planners will assist you in creating a seamless and successful gathering.\r\n                </div>\r\n                <br />\r\n                <div><strong>Location:</strong></div>\r\n                <div>Conveniently located close to major attractions and business districts, our hotel offers easy access to\r\n                    Alabamas vibrant shopping districts, cultural landmarks, and entertainment venues. Whether you\'re here for\r\n                    business or leisure, our prime location ensures that you\'re never far from the action.</div>\r\n                <br />\r\n                <div><strong>Exceptional Service:</strong></div>\r\n                <div>At The Hotel Prime, we take pride in delivering exceptional service to our guests. From the moment you step\r\n                    through our doors, our friendly and knowledgeable staff will cater to your every need, ensuring a memorable and\r\n                    personalized stay.</div>\r\n                <br />\r\n                <div><strong>Book Your Stay:</strong></div>\r\n                <div>Ready to experience the epitome of luxury and comfort? Book your stay at The Hotel Prime today and let us\r\n                    create an unforgettable experience for you. Whether you\'re traveling solo, with a loved one, or with a group,\r\n                    our hotel is dedicated to surpassing your expectations and making your stay truly exceptional.</div></div>','<div>\r\n                <div>- Accommodation will only be provided to guests whose details are registered with the hotel front desk.</div>\r\n                <div>- Guests are required to show a valid photo identification during check-in.</div>\r\n                <div>- GST / Taxes are charged extra and applicable as per government directives.</div>\r\n                <div>- 100 % advance Payment deposit at the time of Check-in.</div>\r\n                <div>- The check-in time is 12:00 PM &amp; check-out time is 11:00 AM. (Subject to availability, early check-in, and late check-out will be considered)</div>\r\n                <div>- The hotel may deny further accommodation to a guest who does not prove to be decent and comply with the hotel policy and rules.</div>\r\n                <div>- The guest has to bear any loss caused by them to the hotel property.</div></div>');
/*!40000 ALTER TABLE `qlo_htl_branch_info_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_branch_refund_rules`
--

DROP TABLE IF EXISTS `qlo_htl_branch_refund_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_branch_refund_rules` (
  `id_hotel_refund_rule` int NOT NULL AUTO_INCREMENT,
  `id_refund_rule` int unsigned NOT NULL,
  `id_hotel` int unsigned NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_hotel_refund_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_branch_refund_rules`
--

LOCK TABLES `qlo_htl_branch_refund_rules` WRITE;
/*!40000 ALTER TABLE `qlo_htl_branch_refund_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_branch_refund_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_cart_booking_data`
--

DROP TABLE IF EXISTS `qlo_htl_cart_booking_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_cart_booking_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cart` int NOT NULL,
  `id_guest` int NOT NULL,
  `id_order` int NOT NULL,
  `id_customer` int NOT NULL,
  `id_currency` int NOT NULL,
  `id_product` int NOT NULL,
  `id_room` int NOT NULL,
  `id_hotel` int NOT NULL,
  `quantity` int NOT NULL,
  `booking_type` tinyint NOT NULL,
  `comment` text NOT NULL,
  `is_back_order` tinyint NOT NULL,
  `extra_demands` text NOT NULL,
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `adults` smallint NOT NULL,
  `children` smallint NOT NULL,
  `child_ages` text NOT NULL,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_cart_booking_data`
--

LOCK TABLES `qlo_htl_cart_booking_data` WRITE;
/*!40000 ALTER TABLE `qlo_htl_cart_booking_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_cart_booking_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_connected_room`
--

DROP TABLE IF EXISTS `qlo_htl_connected_room`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_connected_room` (
  `id_connected_room` int NOT NULL AUTO_INCREMENT,
  `id_room` int NOT NULL,
  `id_room_connected` int NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_connected_room`),
  UNIQUE KEY `uniq_room_connection` (`id_room`,`id_room_connected`),
  KEY `idx_id_room` (`id_room`),
  KEY `idx_id_room_connected` (`id_room_connected`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_connected_room`
--

LOCK TABLES `qlo_htl_connected_room` WRITE;
/*!40000 ALTER TABLE `qlo_htl_connected_room` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_connected_room` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_custom_navigation_link`
--

DROP TABLE IF EXISTS `qlo_htl_custom_navigation_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_custom_navigation_link` (
  `id_navigation_link` int NOT NULL AUTO_INCREMENT,
  `link` text NOT NULL,
  `is_custom_link` tinyint(1) NOT NULL,
  `id_cms` int NOT NULL DEFAULT '0',
  `position` int unsigned NOT NULL DEFAULT '0',
  `show_at_navigation` tinyint(1) NOT NULL DEFAULT '0',
  `show_at_footer` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_navigation_link`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_custom_navigation_link`
--

LOCK TABLES `qlo_htl_custom_navigation_link` WRITE;
/*!40000 ALTER TABLE `qlo_htl_custom_navigation_link` DISABLE KEYS */;
INSERT INTO `qlo_htl_custom_navigation_link` VALUES (1,'index',0,0,0,1,0,1,'2026-09-03 18:50:24','2026-09-03 18:50:24'),(2,'our-properties',0,0,1,1,0,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(3,'/#hotelInteriorBlock',1,0,2,1,0,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(4,'/#hotelAmenitiesBlock',1,0,3,1,0,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(5,'/#hotelRoomsBlock',1,0,4,1,0,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(6,'/#hotelTestimonialBlock',1,0,5,1,0,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(7,'',0,1,6,0,1,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(8,'',0,2,7,1,1,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(9,'',0,3,8,0,1,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(10,'',0,4,9,1,1,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(11,'',0,5,10,0,1,1,'2026-09-03 18:50:25','2026-09-03 18:50:25'),(12,'contact',0,0,11,1,0,1,'2026-09-03 18:50:25','2026-09-03 18:50:25');
/*!40000 ALTER TABLE `qlo_htl_custom_navigation_link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_custom_navigation_link_lang`
--

DROP TABLE IF EXISTS `qlo_htl_custom_navigation_link_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_custom_navigation_link_lang` (
  `id_navigation_link` int NOT NULL,
  `id_lang` int NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_navigation_link`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_custom_navigation_link_lang`
--

LOCK TABLES `qlo_htl_custom_navigation_link_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_custom_navigation_link_lang` DISABLE KEYS */;
INSERT INTO `qlo_htl_custom_navigation_link_lang` VALUES (1,1,'Home'),(2,1,'Our Properties'),(3,1,'Interior'),(4,1,'Amenities'),(5,1,'Rooms'),(6,1,'Testimonials'),(7,1,''),(8,1,''),(9,1,''),(10,1,''),(11,1,''),(12,1,'Contact Us');
/*!40000 ALTER TABLE `qlo_htl_custom_navigation_link_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_features_block_data`
--

DROP TABLE IF EXISTS `qlo_htl_features_block_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_features_block_data` (
  `id_features_block` int NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL,
  `position` int NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_features_block`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_features_block_data`
--

LOCK TABLES `qlo_htl_features_block_data` WRITE;
/*!40000 ALTER TABLE `qlo_htl_features_block_data` DISABLE KEYS */;
INSERT INTO `qlo_htl_features_block_data` VALUES (1,1,0,'2026-09-03 18:50:06','2026-09-03 18:50:06'),(2,1,1,'2026-09-03 18:50:07','2026-09-03 18:50:07'),(3,1,2,'2026-09-03 18:50:07','2026-09-03 18:50:07'),(4,1,3,'2026-09-03 18:50:08','2026-09-03 18:50:08');
/*!40000 ALTER TABLE `qlo_htl_features_block_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_features_block_data_lang`
--

DROP TABLE IF EXISTS `qlo_htl_features_block_data_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_features_block_data_lang` (
  `id_features_block` int NOT NULL,
  `id_lang` int NOT NULL,
  `feature_title` text NOT NULL,
  `feature_description` text NOT NULL,
  PRIMARY KEY (`id_features_block`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_features_block_data_lang`
--

LOCK TABLES `qlo_htl_features_block_data_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_features_block_data_lang` DISABLE KEYS */;
INSERT INTO `qlo_htl_features_block_data_lang` VALUES (1,1,'Luxurious Rooms','Experience unparalleled comfort in our luxurious rooms, featuring premium amenities and stunning views of the lake or cityscape.'),(2,1,'World class chefs','Experience culinary excellence with world-class chefs, where gourmet dishes are crafted from the finest local ingredients.'),(3,1,'Restaurants','Savor exquisite dining at our restaurant, where a delightful menu is crafted from the finest local ingredients.'),(4,1,'Gym & Spa','Rejuvenate with our state-of-the-art gym and spa, offering a sanctuary for relaxation and fitness.');
/*!40000 ALTER TABLE `qlo_htl_features_block_data_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_footer_payment_block_info`
--

DROP TABLE IF EXISTS `qlo_htl_footer_payment_block_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_footer_payment_block_info` (
  `id_payment_block` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_payment_block`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_footer_payment_block_info`
--

LOCK TABLES `qlo_htl_footer_payment_block_info` WRITE;
/*!40000 ALTER TABLE `qlo_htl_footer_payment_block_info` DISABLE KEYS */;
INSERT INTO `qlo_htl_footer_payment_block_info` VALUES (1,'Visa',1,0,'2026-09-03 18:50:21','2026-09-03 18:50:21'),(2,'American Express',1,1,'2026-09-03 18:50:22','2026-09-03 18:50:22'),(3,'MasterCard',1,2,'2026-09-03 18:50:22','2026-09-03 18:50:22'),(4,'Paypal',1,3,'2026-09-03 18:50:22','2026-09-03 18:50:22');
/*!40000 ALTER TABLE `qlo_htl_footer_payment_block_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_image`
--

DROP TABLE IF EXISTS `qlo_htl_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_image` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_hotel` int unsigned NOT NULL,
  `id_htl_image_category` int unsigned DEFAULT NULL,
  `cover` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_hotel` (`id_hotel`),
  KEY `id_htl_image_category` (`id_htl_image_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_image`
--

LOCK TABLES `qlo_htl_image` WRITE;
/*!40000 ALTER TABLE `qlo_htl_image` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_image` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_image_category`
--

DROP TABLE IF EXISTS `qlo_htl_image_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_image_category` (
  `id_htl_image_category` int unsigned NOT NULL AUTO_INCREMENT,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_htl_image_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_image_category`
--

LOCK TABLES `qlo_htl_image_category` WRITE;
/*!40000 ALTER TABLE `qlo_htl_image_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_image_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_image_category_lang`
--

DROP TABLE IF EXISTS `qlo_htl_image_category_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_image_category_lang` (
  `id_htl_image_category` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`id_htl_image_category`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_image_category_lang`
--

LOCK TABLES `qlo_htl_image_category_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_image_category_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_image_category_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_interior_image`
--

DROP TABLE IF EXISTS `qlo_htl_interior_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_interior_image` (
  `id_interior_image` int NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `display_name` text NOT NULL,
  `active` tinyint(1) NOT NULL,
  `position` int NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_interior_image`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_interior_image`
--

LOCK TABLES `qlo_htl_interior_image` WRITE;
/*!40000 ALTER TABLE `qlo_htl_interior_image` DISABLE KEYS */;
INSERT INTO `qlo_htl_interior_image` VALUES (1,'1','Dummy Image 1',1,0,'2026-09-03 18:49:59','2026-09-03 18:49:59'),(2,'2','Dummy Image 2',1,1,'2026-09-03 18:50:00','2026-09-03 18:50:00'),(3,'3','Dummy Image 3',1,2,'2026-09-03 18:50:00','2026-09-03 18:50:00'),(4,'4','Dummy Image 4',1,3,'2026-09-03 18:50:00','2026-09-03 18:50:00'),(5,'5','Dummy Image 5',1,4,'2026-09-03 18:50:00','2026-09-03 18:50:00'),(6,'6','Dummy Image 6',1,5,'2026-09-03 18:50:01','2026-09-03 18:50:01'),(7,'7','Dummy Image 7',1,6,'2026-09-03 18:50:02','2026-09-03 18:50:02'),(8,'8','Dummy Image 8',1,7,'2026-09-03 18:50:02','2026-09-03 18:50:02'),(9,'9','Dummy Image 9',1,8,'2026-09-03 18:50:03','2026-09-03 18:50:03'),(10,'10','Dummy Image 10',1,9,'2026-09-03 18:50:04','2026-09-03 18:50:04'),(11,'11','Dummy Image 11',1,10,'2026-09-03 18:50:04','2026-09-03 18:50:04'),(12,'12','Dummy Image 12',1,11,'2026-09-03 18:50:05','2026-09-03 18:50:05');
/*!40000 ALTER TABLE `qlo_htl_interior_image` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_order_refund_rules`
--

DROP TABLE IF EXISTS `qlo_htl_order_refund_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_order_refund_rules` (
  `id_refund_rule` int NOT NULL AUTO_INCREMENT,
  `payment_type` int unsigned NOT NULL,
  `deduction_value_full_pay` decimal(20,6) NOT NULL,
  `deduction_value_adv_pay` decimal(20,6) NOT NULL,
  `days` decimal(35,0) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_refund_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_order_refund_rules`
--

LOCK TABLES `qlo_htl_order_refund_rules` WRITE;
/*!40000 ALTER TABLE `qlo_htl_order_refund_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_order_refund_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_order_refund_rules_lang`
--

DROP TABLE IF EXISTS `qlo_htl_order_refund_rules_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_order_refund_rules_lang` (
  `id_refund_rule` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id_refund_rule`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_order_refund_rules_lang`
--

LOCK TABLES `qlo_htl_order_refund_rules_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_order_refund_rules_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_order_refund_rules_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_order_restrict_date`
--

DROP TABLE IF EXISTS `qlo_htl_order_restrict_date`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_order_restrict_date` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_hotel` int NOT NULL,
  `use_global_max_checkout_offset` tinyint(1) NOT NULL,
  `max_checkout_offset` int NOT NULL,
  `use_global_min_booking_offset` tinyint(1) NOT NULL,
  `min_booking_offset` int NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_order_restrict_date`
--

LOCK TABLES `qlo_htl_order_restrict_date` WRITE;
/*!40000 ALTER TABLE `qlo_htl_order_restrict_date` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_order_restrict_date` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_block_data`
--

DROP TABLE IF EXISTS `qlo_htl_room_block_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_block_data` (
  `id_room_block` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `active` tinyint NOT NULL,
  `position` int NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_room_block`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_block_data`
--

LOCK TABLES `qlo_htl_room_block_data` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_block_data` DISABLE KEYS */;
INSERT INTO `qlo_htl_room_block_data` VALUES (1,2,1,0,'2026-09-03 18:50:10','2026-09-03 18:50:10'),(2,3,1,1,'2026-09-03 18:50:10','2026-09-03 18:50:10'),(3,1,1,2,'2026-09-03 18:50:10','2026-09-03 18:50:10'),(4,4,1,3,'2026-09-03 18:50:10','2026-09-03 18:50:10');
/*!40000 ALTER TABLE `qlo_htl_room_block_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_disable_dates`
--

DROP TABLE IF EXISTS `qlo_htl_room_disable_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_disable_dates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_room_type` int NOT NULL,
  `id_room` int NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `reason` text,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_disable_dates`
--

LOCK TABLES `qlo_htl_room_disable_dates` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_disable_dates` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_disable_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_information`
--

DROP TABLE IF EXISTS `qlo_htl_room_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_information` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `id_hotel` int NOT NULL,
  `room_num` varchar(225) NOT NULL,
  `id_status` int NOT NULL,
  `floor` text NOT NULL,
  `comment` text NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_information`
--

LOCK TABLES `qlo_htl_room_information` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_information` DISABLE KEYS */;
INSERT INTO `qlo_htl_room_information` VALUES (1,1,1,'GR-101',1,'First','','2026-09-03 18:49:04','2026-09-03 18:49:04'),(2,1,1,'GR-102',1,'First','','2026-09-03 18:49:04','2026-09-03 18:49:04'),(3,1,1,'GR-103',1,'First','','2026-09-03 18:49:04','2026-09-03 18:49:04'),(4,1,1,'GR-104',1,'First','','2026-09-03 18:49:04','2026-09-03 18:49:04'),(5,1,1,'GR-105',1,'First','','2026-09-03 18:49:04','2026-09-03 18:49:04'),(6,2,1,'DR-101',1,'First','','2026-09-03 18:49:12','2026-09-03 18:49:12'),(7,2,1,'DR-102',1,'First','','2026-09-03 18:49:12','2026-09-03 18:49:13'),(8,2,1,'DR-103',1,'First','','2026-09-03 18:49:13','2026-09-03 18:49:13'),(9,2,1,'DR-104',1,'First','','2026-09-03 18:49:13','2026-09-03 18:49:13'),(10,2,1,'DR-105',1,'First','','2026-09-03 18:49:13','2026-09-03 18:49:13'),(11,3,1,'ER-101',1,'First','','2026-09-03 18:49:20','2026-09-03 18:49:20'),(12,3,1,'ER-102',1,'First','','2026-09-03 18:49:20','2026-09-03 18:49:20'),(13,3,1,'ER-103',1,'First','','2026-09-03 18:49:20','2026-09-03 18:49:20'),(14,3,1,'ER-104',1,'First','','2026-09-03 18:49:20','2026-09-03 18:49:20'),(15,3,1,'ER-105',1,'First','','2026-09-03 18:49:21','2026-09-03 18:49:21'),(16,4,1,'LR-101',1,'First','','2026-09-03 18:49:28','2026-09-03 18:49:28'),(17,4,1,'LR-102',1,'First','','2026-09-03 18:49:28','2026-09-03 18:49:28'),(18,4,1,'LR-103',1,'First','','2026-09-03 18:49:28','2026-09-03 18:49:28'),(19,4,1,'LR-104',1,'First','','2026-09-03 18:49:28','2026-09-03 18:49:28'),(20,4,1,'LR-105',1,'First','','2026-09-03 18:49:28','2026-09-03 18:49:28');
/*!40000 ALTER TABLE `qlo_htl_room_information` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_status`
--

DROP TABLE IF EXISTS `qlo_htl_room_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_status`
--

LOCK TABLES `qlo_htl_room_status` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_status` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type`
--

DROP TABLE IF EXISTS `qlo_htl_room_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `id_hotel` int NOT NULL,
  `adults` smallint NOT NULL DEFAULT '2',
  `children` smallint NOT NULL DEFAULT '0',
  `max_adults` smallint NOT NULL DEFAULT '2',
  `max_children` smallint NOT NULL DEFAULT '0',
  `max_guests` smallint NOT NULL DEFAULT '2',
  `min_los` smallint NOT NULL DEFAULT '1',
  `max_los` smallint NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type`
--

LOCK TABLES `qlo_htl_room_type` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type` DISABLE KEYS */;
INSERT INTO `qlo_htl_room_type` VALUES (1,1,1,2,2,2,2,4,1,0,'2026-09-03 18:49:04','2026-09-03 18:49:04'),(2,2,1,2,2,2,2,4,1,0,'2026-09-03 18:49:13','2026-09-03 18:49:13'),(3,3,1,2,2,2,2,4,1,0,'2026-09-03 18:49:21','2026-09-03 18:49:21'),(4,4,1,2,2,2,2,4,1,0,'2026-09-03 18:49:28','2026-09-03 18:49:28');
/*!40000 ALTER TABLE `qlo_htl_room_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_amenity`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_amenity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_amenity` (
  `id_room_type_amenity` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `amenity_id` int unsigned NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_room_type_amenity`),
  KEY `id_product` (`id_product`),
  KEY `fk_htl_room_type_amenity_amenity` (`amenity_id`),
  CONSTRAINT `fk_htl_room_type_amenity_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `qlo_htl_amenity` (`id_amenity`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_amenity`
--

LOCK TABLES `qlo_htl_room_type_amenity` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_amenity` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_amenity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_demand`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_demand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_demand` (
  `id_room_type_demand` int NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `id_global_demand` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_room_type_demand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_demand`
--

LOCK TABLES `qlo_htl_room_type_demand` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_demand` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_demand` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_demand_price`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_demand_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_demand_price` (
  `id_room_type_demand_price` int NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `id_global_demand` int unsigned NOT NULL,
  `id_option` int unsigned NOT NULL,
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_room_type_demand_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_demand_price`
--

LOCK TABLES `qlo_htl_room_type_demand_price` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_demand_price` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_demand_price` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_feature_pricing`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_feature_pricing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_feature_pricing` (
  `id_feature_price` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `id_cart` int NOT NULL DEFAULT '0',
  `id_guest` int NOT NULL DEFAULT '0',
  `id_room` int NOT NULL DEFAULT '0',
  `impact_way` tinyint(1) NOT NULL,
  `impact_type` tinyint(1) NOT NULL,
  `impact_value` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_feature_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_feature_pricing`
--

LOCK TABLES `qlo_htl_room_type_feature_pricing` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_feature_pricing_group`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_feature_pricing_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_feature_pricing_group` (
  `id_feature_price` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  PRIMARY KEY (`id_feature_price`,`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_feature_pricing_group`
--

LOCK TABLES `qlo_htl_room_type_feature_pricing_group` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_feature_pricing_lang`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_feature_pricing_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_feature_pricing_lang` (
  `id_feature_price` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `feature_price_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id_feature_price`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_feature_pricing_lang`
--

LOCK TABLES `qlo_htl_room_type_feature_pricing_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_feature_pricing_restriction`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_feature_pricing_restriction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_feature_pricing_restriction` (
  `id_feature_price_restriction` int NOT NULL AUTO_INCREMENT,
  `id_feature_price` int NOT NULL,
  `is_special_days_exists` tinyint(1) NOT NULL,
  `date_selection_type` tinyint(1) NOT NULL,
  `special_days` text,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_feature_price_restriction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_feature_pricing_restriction`
--

LOCK TABLES `qlo_htl_room_type_feature_pricing_restriction` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing_restriction` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_feature_pricing_restriction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_global_demand`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_global_demand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_global_demand` (
  `id_global_demand` int NOT NULL AUTO_INCREMENT,
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `id_tax_rules_group` int unsigned NOT NULL DEFAULT '0',
  `price_calc_method` tinyint(1) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_global_demand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_global_demand`
--

LOCK TABLES `qlo_htl_room_type_global_demand` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_global_demand_advance_option`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_global_demand_advance_option`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_global_demand_advance_option` (
  `id_option` int NOT NULL AUTO_INCREMENT,
  `id_global_demand` int NOT NULL,
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_global_demand_advance_option`
--

LOCK TABLES `qlo_htl_room_type_global_demand_advance_option` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand_advance_option` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand_advance_option` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_global_demand_advance_option_lang`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_global_demand_advance_option_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_global_demand_advance_option_lang` (
  `id_option` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id_option`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_global_demand_advance_option_lang`
--

LOCK TABLES `qlo_htl_room_type_global_demand_advance_option_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand_advance_option_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand_advance_option_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_global_demand_lang`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_global_demand_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_global_demand_lang` (
  `id_global_demand` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id_global_demand`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_global_demand_lang`
--

LOCK TABLES `qlo_htl_room_type_global_demand_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_global_demand_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_restriction_date_range`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_restriction_date_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_restriction_date_range` (
  `id_rt_restriction` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `min_los` smallint unsigned NOT NULL DEFAULT '1',
  `max_los` smallint unsigned NOT NULL DEFAULT '0',
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_rt_restriction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_restriction_date_range`
--

LOCK TABLES `qlo_htl_room_type_restriction_date_range` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_restriction_date_range` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_restriction_date_range` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_service_product`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_service_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_service_product` (
  `id_room_type_service_product` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `id_element` int unsigned NOT NULL,
  `element_type` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id_room_type_service_product`),
  KEY `id_product` (`id_product`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_service_product`
--

LOCK TABLES `qlo_htl_room_type_service_product` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_service_product` DISABLE KEYS */;
INSERT INTO `qlo_htl_room_type_service_product` VALUES (1,5,0,1,2),(2,5,0,2,2),(3,5,0,3,2),(4,5,0,4,2),(5,6,1,1,2),(6,6,1,2,2),(7,6,1,3,2),(8,6,1,4,2),(9,7,2,1,2),(10,7,2,2,2),(11,7,2,3,2),(12,7,2,4,2),(13,8,3,1,2),(14,8,3,2,2),(15,8,3,3,2),(16,8,3,4,2),(17,9,4,1,2),(18,9,4,2,2),(19,9,4,3,2),(20,9,4,4,2),(21,10,5,1,2),(22,10,5,2,2),(23,10,5,3,2),(24,10,5,4,2);
/*!40000 ALTER TABLE `qlo_htl_room_type_service_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_room_type_service_product_price`
--

DROP TABLE IF EXISTS `qlo_htl_room_type_service_product_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_room_type_service_product_price` (
  `id_room_type_service_product_price` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `id_tax_rules_group` int unsigned NOT NULL,
  `id_element` int unsigned NOT NULL,
  `element_type` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id_room_type_service_product_price`),
  KEY `id_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_room_type_service_product_price`
--

LOCK TABLES `qlo_htl_room_type_service_product_price` WRITE;
/*!40000 ALTER TABLE `qlo_htl_room_type_service_product_price` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_htl_room_type_service_product_price` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_settings_link`
--

DROP TABLE IF EXISTS `qlo_htl_settings_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_settings_link` (
  `id_settings_link` int NOT NULL AUTO_INCREMENT,
  `icon` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `link` text NOT NULL,
  `new_window` tinyint(1) NOT NULL DEFAULT '0',
  `position` int unsigned NOT NULL DEFAULT '0',
  `unremovable` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_settings_link`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_settings_link`
--

LOCK TABLES `qlo_htl_settings_link` WRITE;
/*!40000 ALTER TABLE `qlo_htl_settings_link` DISABLE KEYS */;
INSERT INTO `qlo_htl_settings_link` VALUES (1,'icon-cogs','index.php?controller=AdminHotelGeneralSettings',0,0,1,1,'2026-09-03 13:18:05','2026-09-03 13:18:05'),(2,'icon-dollar','index.php?controller=AdminHotelFeaturePricesSettings',0,2,1,1,'2026-09-03 13:18:05','2026-09-03 13:18:05'),(3,'icon-plus-square','index.php?controller=AdminRoomTypeGlobalDemand',0,3,1,1,'2026-09-03 13:18:05','2026-09-03 13:18:05'),(4,'icon-file-text','index.php?controller=AdminAboutHotelBlockSetting',0,4,0,1,'2026-09-03 13:18:05','2026-09-03 13:18:05'),(5,'icon-th-list','index.php?controller=AdminFeaturesModuleSetting',0,5,0,1,'2026-09-03 13:18:05','2026-09-03 13:18:05');
/*!40000 ALTER TABLE `qlo_htl_settings_link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_settings_link_lang`
--

DROP TABLE IF EXISTS `qlo_htl_settings_link_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_settings_link_lang` (
  `id_settings_link` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `hint` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id_settings_link`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_settings_link_lang`
--

LOCK TABLES `qlo_htl_settings_link_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_settings_link_lang` DISABLE KEYS */;
INSERT INTO `qlo_htl_settings_link_lang` VALUES (1,1,'General Settings','Configure Your hotel general settings using this option.'),(2,1,'Advanced Price Rules','Here set Advanced price rules for specific dates.'),(3,1,'Additional Facilities','Here create Additional facilities and their prices for room types.'),(4,1,'Hotel Interior Block','Configure Hotel Interior block. You can display hotel interior images using this block. This block will be displayed on home page.'),(5,1,'Hotel Amenities Block','Configure Hotels Amenities settings. You can display hotel amenities images using this block. This block will be displayed on home page.');
/*!40000 ALTER TABLE `qlo_htl_settings_link_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_testimonials_block_data`
--

DROP TABLE IF EXISTS `qlo_htl_testimonials_block_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_testimonials_block_data` (
  `id_testimonial_block` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `designation` text NOT NULL,
  `active` tinyint(1) NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_testimonial_block`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_testimonials_block_data`
--

LOCK TABLES `qlo_htl_testimonials_block_data` WRITE;
/*!40000 ALTER TABLE `qlo_htl_testimonials_block_data` DISABLE KEYS */;
INSERT INTO `qlo_htl_testimonials_block_data` VALUES (1,'Steve Rogers','Eon Comics CEO',1,0,'2026-09-03 18:50:12','2026-09-03 18:50:12'),(2,'Calrk Kent','Ken Comics Kal',1,1,'2026-09-03 18:50:12','2026-09-03 18:50:12'),(3,'John Doe','Jan Comics Joe',1,2,'2026-09-03 18:50:13','2026-09-03 18:50:13');
/*!40000 ALTER TABLE `qlo_htl_testimonials_block_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_htl_testimonials_block_data_lang`
--

DROP TABLE IF EXISTS `qlo_htl_testimonials_block_data_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_htl_testimonials_block_data_lang` (
  `id_testimonial_block` int NOT NULL,
  `id_lang` int NOT NULL,
  `testimonial_content` text NOT NULL,
  PRIMARY KEY (`id_testimonial_block`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_htl_testimonials_block_data_lang`
--

LOCK TABLES `qlo_htl_testimonials_block_data_lang` WRITE;
/*!40000 ALTER TABLE `qlo_htl_testimonials_block_data_lang` DISABLE KEYS */;
INSERT INTO `qlo_htl_testimonials_block_data_lang` VALUES (1,1,'As a frequent traveler, I can confidently say Hotel Prime stands out for its tranquil environment and exceptional hospitality. The View Room provided a perfect retreat with its stunning cityscape views and comfortable accommodations.'),(2,1,'Hotel Prime surpassed my expectations in every way. The Executive Room was not only luxurious but also provided a peaceful retreat with its stunning lake views. The attention to detail and personalized service made my stay truly memorable. I highly recommend Hotel Prime for both business and leisure travelers looking for a blend of comfort and sophistication.'),(3,1,'My stay at Hotel Prime was absolutely rejuvenating. The Executive Room was spacious, elegant, and offered breathtaking lake views. The staff’s attention to detail and impeccable service made my business trip both productive and relaxing.');
/*!40000 ALTER TABLE `qlo_htl_testimonials_block_data_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_image`
--

DROP TABLE IF EXISTS `qlo_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_image` (
  `id_image` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `cover` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id_image`),
  UNIQUE KEY `id_product_cover` (`id_product`,`cover`),
  UNIQUE KEY `idx_product_image` (`id_image`,`id_product`,`cover`),
  KEY `image_product` (`id_product`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_image`
--

LOCK TABLES `qlo_image` WRITE;
/*!40000 ALTER TABLE `qlo_image` DISABLE KEYS */;
INSERT INTO `qlo_image` VALUES (1,1,1,1),(2,1,2,NULL),(3,1,3,NULL),(4,1,4,NULL),(5,2,1,1),(6,2,2,NULL),(7,2,3,NULL),(8,2,4,NULL),(9,3,1,1),(10,3,2,NULL),(11,3,3,NULL),(12,3,4,NULL),(13,4,1,1),(14,4,2,NULL),(15,4,3,NULL),(16,4,4,NULL),(17,4,5,NULL),(18,5,1,1),(19,6,1,1),(20,7,1,1),(21,8,1,1),(22,9,1,1),(23,10,1,1);
/*!40000 ALTER TABLE `qlo_image` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_image_lang`
--

DROP TABLE IF EXISTS `qlo_image_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_image_lang` (
  `id_image` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `legend` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id_image`,`id_lang`),
  KEY `id_image` (`id_image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_image_lang`
--

LOCK TABLES `qlo_image_lang` WRITE;
/*!40000 ALTER TABLE `qlo_image_lang` DISABLE KEYS */;
INSERT INTO `qlo_image_lang` VALUES (1,1,''),(2,1,''),(3,1,''),(4,1,''),(5,1,''),(6,1,''),(7,1,''),(8,1,''),(9,1,''),(10,1,''),(11,1,''),(12,1,''),(13,1,''),(14,1,''),(15,1,''),(16,1,''),(17,1,''),(18,1,''),(19,1,''),(20,1,''),(21,1,''),(22,1,''),(23,1,'');
/*!40000 ALTER TABLE `qlo_image_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_image_shop`
--

DROP TABLE IF EXISTS `qlo_image_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_image_shop` (
  `id_product` int unsigned NOT NULL,
  `id_image` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `cover` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id_image`,`id_shop`),
  UNIQUE KEY `id_product` (`id_product`,`id_shop`,`cover`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_image_shop`
--

LOCK TABLES `qlo_image_shop` WRITE;
/*!40000 ALTER TABLE `qlo_image_shop` DISABLE KEYS */;
INSERT INTO `qlo_image_shop` VALUES (1,2,1,NULL),(1,3,1,NULL),(1,4,1,NULL),(1,1,1,1),(2,6,1,NULL),(2,7,1,NULL),(2,8,1,NULL),(2,5,1,1),(3,10,1,NULL),(3,11,1,NULL),(3,12,1,NULL),(3,9,1,1),(4,14,1,NULL),(4,15,1,NULL),(4,16,1,NULL),(4,17,1,NULL),(4,13,1,1),(5,18,1,1),(6,19,1,1),(7,20,1,1),(8,21,1,1),(9,22,1,1),(10,23,1,1);
/*!40000 ALTER TABLE `qlo_image_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_image_type`
--

DROP TABLE IF EXISTS `qlo_image_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_image_type` (
  `id_image_type` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `width` int unsigned NOT NULL,
  `height` int unsigned NOT NULL,
  `products` tinyint(1) NOT NULL DEFAULT '1',
  `categories` tinyint(1) NOT NULL DEFAULT '1',
  `hotels` tinyint(1) NOT NULL DEFAULT '1',
  `manufacturers` tinyint(1) NOT NULL DEFAULT '1',
  `suppliers` tinyint(1) NOT NULL DEFAULT '1',
  `scenes` tinyint(1) NOT NULL DEFAULT '1',
  `stores` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_image_type`),
  KEY `image_type_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_image_type`
--

LOCK TABLES `qlo_image_type` WRITE;
/*!40000 ALTER TABLE `qlo_image_type` DISABLE KEYS */;
INSERT INTO `qlo_image_type` VALUES (1,'cart_default',80,80,1,0,0,0,0,0,0),(2,'small_default',98,98,1,0,1,1,1,0,0),(3,'medium_default',125,125,1,1,1,1,1,0,1),(4,'home_default',250,250,1,0,0,0,0,0,0),(5,'large_default',720,720,1,0,1,1,1,0,0),(6,'thickbox_default',800,800,1,0,0,0,0,0,0),(7,'category_default',870,217,0,1,0,0,0,0,0);
/*!40000 ALTER TABLE `qlo_image_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_import_match`
--

DROP TABLE IF EXISTS `qlo_import_match`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_import_match` (
  `id_import_match` int NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `match` text NOT NULL,
  `skip` int NOT NULL,
  PRIMARY KEY (`id_import_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_import_match`
--

LOCK TABLES `qlo_import_match` WRITE;
/*!40000 ALTER TABLE `qlo_import_match` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_import_match` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_lang`
--

DROP TABLE IF EXISTS `qlo_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_lang` (
  `id_lang` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `iso_code` char(2) NOT NULL,
  `language_code` char(5) NOT NULL,
  `date_format_lite` char(32) NOT NULL DEFAULT 'Y-m-d',
  `date_format_full` char(32) NOT NULL DEFAULT 'Y-m-d H:i:s',
  `is_rtl` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_lang`),
  KEY `lang_iso_code` (`iso_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_lang`
--

LOCK TABLES `qlo_lang` WRITE;
/*!40000 ALTER TABLE `qlo_lang` DISABLE KEYS */;
INSERT INTO `qlo_lang` VALUES (1,'English (English)',1,'en','en-us','m/d/Y','m/d/Y H:i:s',0);
/*!40000 ALTER TABLE `qlo_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_lang_shop`
--

DROP TABLE IF EXISTS `qlo_lang_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_lang_shop` (
  `id_lang` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_lang`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_lang_shop`
--

LOCK TABLES `qlo_lang_shop` WRITE;
/*!40000 ALTER TABLE `qlo_lang_shop` DISABLE KEYS */;
INSERT INTO `qlo_lang_shop` VALUES (1,1);
/*!40000 ALTER TABLE `qlo_lang_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_log`
--

DROP TABLE IF EXISTS `qlo_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_log` (
  `id_log` int unsigned NOT NULL AUTO_INCREMENT,
  `severity` tinyint(1) NOT NULL,
  `error_code` int DEFAULT NULL,
  `message` text NOT NULL,
  `object_type` varchar(32) DEFAULT NULL,
  `object_id` int unsigned DEFAULT NULL,
  `id_employee` int unsigned DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_log`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_log`
--

LOCK TABLES `qlo_log` WRITE;
/*!40000 ALTER TABLE `qlo_log` DISABLE KEYS */;
INSERT INTO `qlo_log` VALUES (1,1,0,'Back Office connection from 172.18.0.1','',0,1,'2026-09-03 19:01:30','2026-09-03 19:01:30');
/*!40000 ALTER TABLE `qlo_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_mail`
--

DROP TABLE IF EXISTS `qlo_mail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_mail` (
  `id_mail` int unsigned NOT NULL AUTO_INCREMENT,
  `recipient` varchar(126) NOT NULL,
  `template` varchar(62) NOT NULL,
  `subject` varchar(254) NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mail`),
  KEY `recipient` (`recipient`(10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_mail`
--

LOCK TABLES `qlo_mail` WRITE;
/*!40000 ALTER TABLE `qlo_mail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_mail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_maintenance_access`
--

DROP TABLE IF EXISTS `qlo_maintenance_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_maintenance_access` (
  `id_maintenance_access` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(50) NOT NULL,
  `email` varchar(128) NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_maintenance_access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_maintenance_access`
--

LOCK TABLES `qlo_maintenance_access` WRITE;
/*!40000 ALTER TABLE `qlo_maintenance_access` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_maintenance_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_manufacturer`
--

DROP TABLE IF EXISTS `qlo_manufacturer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_manufacturer` (
  `id_manufacturer` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_manufacturer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_manufacturer`
--

LOCK TABLES `qlo_manufacturer` WRITE;
/*!40000 ALTER TABLE `qlo_manufacturer` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_manufacturer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_manufacturer_lang`
--

DROP TABLE IF EXISTS `qlo_manufacturer_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_manufacturer_lang` (
  `id_manufacturer` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `description` text,
  `short_description` text,
  `meta_title` varchar(128) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_manufacturer`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_manufacturer_lang`
--

LOCK TABLES `qlo_manufacturer_lang` WRITE;
/*!40000 ALTER TABLE `qlo_manufacturer_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_manufacturer_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_manufacturer_shop`
--

DROP TABLE IF EXISTS `qlo_manufacturer_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_manufacturer_shop` (
  `id_manufacturer` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_manufacturer`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_manufacturer_shop`
--

LOCK TABLES `qlo_manufacturer_shop` WRITE;
/*!40000 ALTER TABLE `qlo_manufacturer_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_manufacturer_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_memcached_servers`
--

DROP TABLE IF EXISTS `qlo_memcached_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_memcached_servers` (
  `id_memcached_server` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(254) NOT NULL,
  `port` int unsigned NOT NULL,
  `weight` int unsigned NOT NULL,
  PRIMARY KEY (`id_memcached_server`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_memcached_servers`
--

LOCK TABLES `qlo_memcached_servers` WRITE;
/*!40000 ALTER TABLE `qlo_memcached_servers` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_memcached_servers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_message`
--

DROP TABLE IF EXISTS `qlo_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_message` (
  `id_message` int unsigned NOT NULL AUTO_INCREMENT,
  `id_cart` int unsigned DEFAULT NULL,
  `id_customer` int unsigned NOT NULL,
  `id_employee` int unsigned DEFAULT NULL,
  `id_order` int unsigned NOT NULL,
  `message` text NOT NULL,
  `private` tinyint unsigned NOT NULL DEFAULT '1',
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_message`),
  KEY `message_order` (`id_order`),
  KEY `id_cart` (`id_cart`),
  KEY `id_customer` (`id_customer`),
  KEY `id_employee` (`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_message`
--

LOCK TABLES `qlo_message` WRITE;
/*!40000 ALTER TABLE `qlo_message` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_message` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_message_readed`
--

DROP TABLE IF EXISTS `qlo_message_readed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_message_readed` (
  `id_message` int unsigned NOT NULL,
  `id_employee` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_message`,`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_message_readed`
--

LOCK TABLES `qlo_message_readed` WRITE;
/*!40000 ALTER TABLE `qlo_message_readed` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_message_readed` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_meta`
--

DROP TABLE IF EXISTS `qlo_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_meta` (
  `id_meta` int unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(64) NOT NULL,
  `configurable` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_meta`),
  UNIQUE KEY `page` (`page`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_meta`
--

LOCK TABLES `qlo_meta` WRITE;
/*!40000 ALTER TABLE `qlo_meta` DISABLE KEYS */;
INSERT INTO `qlo_meta` VALUES (1,'pagenotfound',1),(2,'best-sales',1),(3,'contact',1),(4,'index',1),(5,'manufacturer',1),(6,'new-products',1),(7,'password',1),(8,'supplier',1),(9,'address',1),(10,'addresses',1),(11,'authentication',1),(12,'cart',1),(13,'discount',1),(14,'history',1),(15,'order-detail',1),(16,'identity',1),(17,'my-account',1),(18,'order-follow',1),(19,'order-slip',1),(20,'order',1),(21,'order-opc',1),(22,'guest-tracking',1),(23,'order-confirmation',1),(24,'product',0),(25,'category',0),(26,'cms',0),(27,'module-cheque-payment',0),(28,'module-cheque-validation',0),(29,'module-bankwire-validation',0),(30,'module-bankwire-payment',0),(31,'module-cashondelivery-validation',0),(32,'our-properties',1);
/*!40000 ALTER TABLE `qlo_meta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_meta_lang`
--

DROP TABLE IF EXISTS `qlo_meta_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_meta_lang` (
  `id_meta` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned NOT NULL,
  `title` varchar(128) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `url_rewrite` varchar(254) NOT NULL,
  PRIMARY KEY (`id_meta`,`id_shop`,`id_lang`),
  KEY `id_shop` (`id_shop`),
  KEY `id_lang` (`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_meta_lang`
--

LOCK TABLES `qlo_meta_lang` WRITE;
/*!40000 ALTER TABLE `qlo_meta_lang` DISABLE KEYS */;
INSERT INTO `qlo_meta_lang` VALUES (1,1,1,'404 error','This page cannot be found','','page-not-found'),(2,1,1,'Best sales','Our best sales','','best-sales'),(3,1,1,'Contact us','Use our form to contact us','','contact-us'),(4,1,1,'','','',''),(5,1,1,'Manufacturers','Manufacturers list','','manufacturers'),(6,1,1,'New products','Our new products','','new-products'),(7,1,1,'Forgot your password','Enter the e-mail address you use to sign in to receive an e-mail with a new password','','password-recovery'),(8,1,1,'Suppliers','Suppliers list','','supplier'),(9,1,1,'Address','','','address'),(10,1,1,'Addresses','','','addresses'),(11,1,1,'Login','','','login'),(12,1,1,'Cart','','','cart'),(13,1,1,'Discount','','','discount'),(14,1,1,'Order history','','','order-history'),(15,1,1,'Booking details','','','order-detail'),(16,1,1,'Identity','','','identity'),(17,1,1,'My account','','','my-account'),(18,1,1,'Order follow','','','order-follow'),(19,1,1,'Credit slip','','','credit-slip'),(20,1,1,'Order','','','order'),(21,1,1,'Order','','','quick-order'),(22,1,1,'Guest tracking','','','guest-tracking'),(23,1,1,'Order confirmation','','','order-confirmation'),(32,1,1,'Our Properties','','','our-properties');
/*!40000 ALTER TABLE `qlo_meta_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module`
--

DROP TABLE IF EXISTS `qlo_module`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module` (
  `id_module` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `version` varchar(8) NOT NULL,
  PRIMARY KEY (`id_module`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module`
--

LOCK TABLES `qlo_module` WRITE;
/*!40000 ALTER TABLE `qlo_module` DISABLE KEYS */;
INSERT INTO `qlo_module` VALUES (1,'hotelreservationsystem',1,'1.7.1'),(2,'wkroomsearchblock',1,'1.1.5'),(3,'blocknewsletter',1,'2.3.1'),(4,'blocksocial',1,'1.2.2'),(5,'wkhotelfilterblock',1,'1.0.7'),(6,'wkabouthotelblock',1,'1.2.0'),(7,'wkhotelfeaturesblock',1,'2.0.8'),(8,'wkhotelroom',1,'1.2.0'),(9,'wktestimonialblock',1,'1.1.8'),(10,'bankwire',1,'1.1.7'),(11,'cheque',1,'2.6.8'),(12,'blockmyaccount',1,'1.4.3'),(13,'blockcurrencies',1,'1.1.1'),(14,'blocklanguages',1,'1.6.1'),(15,'qlohotelreview',1,'1.0.2'),(16,'wkfooterlangcurrencyblock',1,'1.0.2'),(17,'wkfooterpaymentinfoblockcontainer',1,'1.0.2'),(18,'wkfooterpaymentblock',1,'1.1.6'),(19,'wkfooternotificationblock',1,'1.0.2'),(20,'blocknavigationmenu',1,'1.1.4'),(21,'dashguestcycle',1,'1.0.3'),(22,'dashoccupancy',1,'1.0.3'),(23,'dashactivity',1,'1.0.4'),(24,'dashtrends',1,'1.0.4'),(25,'dashavailability',1,'1.0.3'),(26,'dashperformance',1,'1.0.3'),(27,'dashproducts',1,'1.0.4'),(28,'dashgoals',1,'1.0.3'),(29,'dashinsights',1,'1.0.2'),(30,'graphnvd3',1,'1.5.4'),(31,'statsdata',1,'1.6.4'),(32,'statsvisits',1,'1.6.3'),(33,'statsorigin',1,'1.4.3'),(34,'statslive',1,'1.3.3'),(35,'sekeywords',1,'1.4.3'),(36,'statssales',1,'1.3.4'),(37,'statspersonalinfos',1,'1.4.3'),(38,'statsforecast',1,'1.4.5'),(39,'statsbestcategories',1,'1.5.5'),(40,'statsproduct',1,'1.5.4'),(41,'statscheckup',1,'1.5.4'),(42,'statscatalog',1,'1.4.3'),(43,'statsbestproducts',1,'1.5.5'),(44,'pagesnotfound',1,'1.5.2'),(45,'statsnewsletter',1,'1.4.4'),(46,'statsregistrations',1,'1.4.3'),(47,'statsbestvouchers',1,'1.5.3'),(48,'statsbestcustomers',1,'1.5.4'),(49,'statsequipment',1,'1.3.3'),(50,'qlostatsserviceproducts',1,'1.0.1'),(51,'blockcart',1,'1.6.7'),(52,'blockuserinfo',1,'1.0.1'),(53,'qlochannelmanagerconnector',1,'1.0.1'),(54,'pulsecore',1,'0.1.0'),(55,'pulselicense',1,'1.0.0');
/*!40000 ALTER TABLE `qlo_module` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module_access`
--

DROP TABLE IF EXISTS `qlo_module_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module_access` (
  `id_profile` int unsigned NOT NULL,
  `id_module` int unsigned NOT NULL,
  `view` tinyint(1) NOT NULL DEFAULT '0',
  `configure` tinyint(1) NOT NULL DEFAULT '0',
  `uninstall` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_profile`,`id_module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module_access`
--

LOCK TABLES `qlo_module_access` WRITE;
/*!40000 ALTER TABLE `qlo_module_access` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_module_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module_country`
--

DROP TABLE IF EXISTS `qlo_module_country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module_country` (
  `id_module` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_country` int unsigned NOT NULL,
  PRIMARY KEY (`id_module`,`id_shop`,`id_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module_country`
--

LOCK TABLES `qlo_module_country` WRITE;
/*!40000 ALTER TABLE `qlo_module_country` DISABLE KEYS */;
INSERT INTO `qlo_module_country` VALUES (10,1,31),(11,1,31);
/*!40000 ALTER TABLE `qlo_module_country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module_currency`
--

DROP TABLE IF EXISTS `qlo_module_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module_currency` (
  `id_module` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_currency` int NOT NULL,
  PRIMARY KEY (`id_module`,`id_shop`,`id_currency`),
  KEY `id_module` (`id_module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module_currency`
--

LOCK TABLES `qlo_module_currency` WRITE;
/*!40000 ALTER TABLE `qlo_module_currency` DISABLE KEYS */;
INSERT INTO `qlo_module_currency` VALUES (10,1,1),(11,1,1);
/*!40000 ALTER TABLE `qlo_module_currency` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module_group`
--

DROP TABLE IF EXISTS `qlo_module_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module_group` (
  `id_module` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_group` int unsigned NOT NULL,
  PRIMARY KEY (`id_module`,`id_shop`,`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module_group`
--

LOCK TABLES `qlo_module_group` WRITE;
/*!40000 ALTER TABLE `qlo_module_group` DISABLE KEYS */;
INSERT INTO `qlo_module_group` VALUES (1,1,1),(1,1,2),(1,1,3),(2,1,1),(2,1,2),(2,1,3),(3,1,1),(3,1,2),(3,1,3),(4,1,1),(4,1,2),(4,1,3),(5,1,1),(5,1,2),(5,1,3),(6,1,1),(6,1,2),(6,1,3),(7,1,1),(7,1,2),(7,1,3),(8,1,1),(8,1,2),(8,1,3),(9,1,1),(9,1,2),(9,1,3),(10,1,1),(10,1,2),(10,1,3),(11,1,1),(11,1,2),(11,1,3),(12,1,1),(12,1,2),(12,1,3),(13,1,1),(13,1,2),(13,1,3),(14,1,1),(14,1,2),(14,1,3),(15,1,1),(15,1,2),(15,1,3),(16,1,1),(16,1,2),(16,1,3),(17,1,1),(17,1,2),(17,1,3),(18,1,1),(18,1,2),(18,1,3),(19,1,1),(19,1,2),(19,1,3),(20,1,1),(20,1,2),(20,1,3),(21,1,1),(21,1,2),(21,1,3),(22,1,1),(22,1,2),(22,1,3),(23,1,1),(23,1,2),(23,1,3),(24,1,1),(24,1,2),(24,1,3),(25,1,1),(25,1,2),(25,1,3),(26,1,1),(26,1,2),(26,1,3),(27,1,1),(27,1,2),(27,1,3),(28,1,1),(28,1,2),(28,1,3),(29,1,1),(29,1,2),(29,1,3),(30,1,1),(30,1,2),(30,1,3),(31,1,1),(31,1,2),(31,1,3),(32,1,1),(32,1,2),(32,1,3),(33,1,1),(33,1,2),(33,1,3),(34,1,1),(34,1,2),(34,1,3),(35,1,1),(35,1,2),(35,1,3),(36,1,1),(36,1,2),(36,1,3),(37,1,1),(37,1,2),(37,1,3),(38,1,1),(38,1,2),(38,1,3),(39,1,1),(39,1,2),(39,1,3),(40,1,1),(40,1,2),(40,1,3),(41,1,1),(41,1,2),(41,1,3),(42,1,1),(42,1,2),(42,1,3),(43,1,1),(43,1,2),(43,1,3),(44,1,1),(44,1,2),(44,1,3),(45,1,1),(45,1,2),(45,1,3),(46,1,1),(46,1,2),(46,1,3),(47,1,1),(47,1,2),(47,1,3),(48,1,1),(48,1,2),(48,1,3),(49,1,1),(49,1,2),(49,1,3),(50,1,1),(50,1,2),(50,1,3),(51,1,1),(51,1,2),(51,1,3),(52,1,1),(52,1,2),(52,1,3),(53,1,1),(53,1,2),(53,1,3),(54,1,1),(54,1,2),(54,1,3),(55,1,1),(55,1,2),(55,1,3);
/*!40000 ALTER TABLE `qlo_module_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module_preference`
--

DROP TABLE IF EXISTS `qlo_module_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module_preference` (
  `id_module_preference` int NOT NULL AUTO_INCREMENT,
  `id_employee` int NOT NULL,
  `module` varchar(255) NOT NULL,
  `interest` tinyint(1) DEFAULT NULL,
  `favorite` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_module_preference`),
  UNIQUE KEY `employee_module` (`id_employee`,`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module_preference`
--

LOCK TABLES `qlo_module_preference` WRITE;
/*!40000 ALTER TABLE `qlo_module_preference` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_module_preference` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_module_shop`
--

DROP TABLE IF EXISTS `qlo_module_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_module_shop` (
  `id_module` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `enable_device` tinyint(1) NOT NULL DEFAULT '7',
  PRIMARY KEY (`id_module`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_module_shop`
--

LOCK TABLES `qlo_module_shop` WRITE;
/*!40000 ALTER TABLE `qlo_module_shop` DISABLE KEYS */;
INSERT INTO `qlo_module_shop` VALUES (1,1,7),(2,1,7),(3,1,7),(4,1,7),(5,1,7),(6,1,7),(7,1,7),(8,1,7),(9,1,7),(10,1,7),(11,1,7),(12,1,7),(13,1,7),(14,1,7),(15,1,7),(16,1,7),(17,1,7),(18,1,7),(19,1,7),(20,1,7),(21,1,7),(22,1,7),(23,1,7),(24,1,7),(25,1,7),(26,1,7),(27,1,7),(28,1,7),(29,1,7),(30,1,7),(31,1,7),(32,1,7),(33,1,7),(34,1,7),(35,1,7),(36,1,7),(37,1,7),(38,1,7),(39,1,7),(40,1,7),(41,1,7),(42,1,7),(43,1,7),(44,1,7),(45,1,7),(46,1,7),(47,1,7),(48,1,7),(49,1,7),(50,1,7),(51,1,7),(52,1,7),(53,1,7),(54,1,7),(55,1,7);
/*!40000 ALTER TABLE `qlo_module_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_modules_perfs`
--

DROP TABLE IF EXISTS `qlo_modules_perfs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_modules_perfs` (
  `id_modules_perfs` int unsigned NOT NULL AUTO_INCREMENT,
  `session` int unsigned NOT NULL,
  `module` varchar(62) NOT NULL,
  `method` varchar(126) NOT NULL,
  `time_start` double unsigned NOT NULL,
  `time_end` double unsigned NOT NULL,
  `memory_start` int unsigned NOT NULL,
  `memory_end` int unsigned NOT NULL,
  PRIMARY KEY (`id_modules_perfs`),
  KEY `session` (`session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_modules_perfs`
--

LOCK TABLES `qlo_modules_perfs` WRITE;
/*!40000 ALTER TABLE `qlo_modules_perfs` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_modules_perfs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_newsletter`
--

DROP TABLE IF EXISTS `qlo_newsletter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_newsletter` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `email` varchar(255) NOT NULL,
  `newsletter_date_add` datetime DEFAULT NULL,
  `ip_registration_newsletter` varchar(15) NOT NULL,
  `http_referer` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_newsletter`
--

LOCK TABLES `qlo_newsletter` WRITE;
/*!40000 ALTER TABLE `qlo_newsletter` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_newsletter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_operating_system`
--

DROP TABLE IF EXISTS `qlo_operating_system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_operating_system` (
  `id_operating_system` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id_operating_system`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_operating_system`
--

LOCK TABLES `qlo_operating_system` WRITE;
/*!40000 ALTER TABLE `qlo_operating_system` DISABLE KEYS */;
INSERT INTO `qlo_operating_system` VALUES (1,'Windows XP'),(2,'Windows Vista'),(3,'Windows 7'),(4,'Windows 8'),(5,'MacOsX'),(6,'Linux'),(7,'Android');
/*!40000 ALTER TABLE `qlo_operating_system` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_carrier`
--

DROP TABLE IF EXISTS `qlo_order_carrier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_carrier` (
  `id_order_carrier` int NOT NULL AUTO_INCREMENT,
  `id_order` int unsigned NOT NULL,
  `id_carrier` int unsigned NOT NULL,
  `id_order_invoice` int unsigned DEFAULT NULL,
  `weight` decimal(20,6) DEFAULT NULL,
  `shipping_cost_tax_excl` decimal(20,6) DEFAULT NULL,
  `shipping_cost_tax_incl` decimal(20,6) DEFAULT NULL,
  `tracking_number` varchar(64) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_carrier`),
  KEY `id_order` (`id_order`),
  KEY `id_carrier` (`id_carrier`),
  KEY `id_order_invoice` (`id_order_invoice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_carrier`
--

LOCK TABLES `qlo_order_carrier` WRITE;
/*!40000 ALTER TABLE `qlo_order_carrier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_carrier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_cart_rule`
--

DROP TABLE IF EXISTS `qlo_order_cart_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_cart_rule` (
  `id_order_cart_rule` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int unsigned NOT NULL,
  `id_cart_rule` int unsigned NOT NULL,
  `id_order_invoice` int unsigned DEFAULT '0',
  `name` varchar(254) NOT NULL,
  `value` decimal(17,2) NOT NULL DEFAULT '0.00',
  `value_tax_excl` decimal(17,2) NOT NULL DEFAULT '0.00',
  `free_shipping` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_order_cart_rule`),
  KEY `id_order` (`id_order`),
  KEY `id_cart_rule` (`id_cart_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_cart_rule`
--

LOCK TABLES `qlo_order_cart_rule` WRITE;
/*!40000 ALTER TABLE `qlo_order_cart_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_cart_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_customer_guest_detail`
--

DROP TABLE IF EXISTS `qlo_order_customer_guest_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_customer_guest_detail` (
  `id_order_customer_guest_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int unsigned NOT NULL,
  `id_gender` int unsigned NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `email` varchar(128) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_order_customer_guest_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_customer_guest_detail`
--

LOCK TABLES `qlo_order_customer_guest_detail` WRITE;
/*!40000 ALTER TABLE `qlo_order_customer_guest_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_customer_guest_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_detail`
--

DROP TABLE IF EXISTS `qlo_order_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_detail` (
  `id_order_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int unsigned NOT NULL,
  `id_order_invoice` int DEFAULT NULL,
  `id_warehouse` int unsigned DEFAULT '0',
  `id_shop` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `product_attribute_id` int unsigned DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_quantity` int unsigned NOT NULL DEFAULT '0',
  `product_quantity_in_stock` int NOT NULL DEFAULT '0',
  `product_quantity_refunded` int unsigned NOT NULL DEFAULT '0',
  `product_quantity_return` int unsigned NOT NULL DEFAULT '0',
  `product_quantity_reinjected` int unsigned NOT NULL DEFAULT '0',
  `product_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `reduction_percent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reduction_amount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `reduction_amount_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `reduction_amount_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `group_reduction` decimal(10,2) NOT NULL DEFAULT '0.00',
  `product_quantity_discount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `is_booking_product` tinyint(1) NOT NULL DEFAULT '0',
  `selling_preference_type` tinyint(1) NOT NULL DEFAULT '1',
  `product_auto_add` tinyint(1) NOT NULL DEFAULT '0',
  `product_price_addition_type` tinyint(1) NOT NULL DEFAULT '0',
  `product_allow_multiple_quantity` tinyint unsigned NOT NULL DEFAULT '1',
  `product_price_calculation_method` tinyint unsigned NOT NULL DEFAULT '1',
  `product_ean13` varchar(13) DEFAULT NULL,
  `product_upc` varchar(12) DEFAULT NULL,
  `product_reference` varchar(32) DEFAULT NULL,
  `product_supplier_reference` varchar(32) DEFAULT NULL,
  `product_weight` decimal(20,6) NOT NULL,
  `id_tax_rules_group` int unsigned DEFAULT '0',
  `tax_computation_method` tinyint unsigned NOT NULL DEFAULT '0',
  `tax_name` varchar(16) NOT NULL,
  `tax_rate` decimal(10,3) NOT NULL DEFAULT '0.000',
  `ecotax` decimal(21,6) NOT NULL DEFAULT '0.000000',
  `ecotax_tax_rate` decimal(5,3) NOT NULL DEFAULT '0.000',
  `discount_quantity_applied` tinyint(1) NOT NULL DEFAULT '0',
  `download_hash` varchar(255) DEFAULT NULL,
  `download_nb` int unsigned DEFAULT '0',
  `download_deadline` datetime DEFAULT NULL,
  `total_price_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_price_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unit_price_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unit_price_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping_price_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping_price_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `purchase_supplier_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `original_product_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `original_wholesale_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  PRIMARY KEY (`id_order_detail`),
  KEY `order_detail_order` (`id_order`),
  KEY `product_id` (`product_id`),
  KEY `product_attribute_id` (`product_attribute_id`),
  KEY `id_tax_rules_group` (`id_tax_rules_group`),
  KEY `id_order_id_order_detail` (`id_order`,`id_order_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_detail`
--

LOCK TABLES `qlo_order_detail` WRITE;
/*!40000 ALTER TABLE `qlo_order_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_detail_tax`
--

DROP TABLE IF EXISTS `qlo_order_detail_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_detail_tax` (
  `id_order_detail` int NOT NULL,
  `id_tax` int NOT NULL,
  `unit_amount` decimal(16,6) NOT NULL DEFAULT '0.000000',
  `total_amount` decimal(16,6) NOT NULL DEFAULT '0.000000',
  KEY `id_order_detail` (`id_order_detail`),
  KEY `id_tax` (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_detail_tax`
--

LOCK TABLES `qlo_order_detail_tax` WRITE;
/*!40000 ALTER TABLE `qlo_order_detail_tax` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_detail_tax` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_history`
--

DROP TABLE IF EXISTS `qlo_order_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_history` (
  `id_order_history` int unsigned NOT NULL AUTO_INCREMENT,
  `id_employee` int unsigned NOT NULL,
  `id_order` int unsigned NOT NULL,
  `id_order_state` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_history`),
  KEY `order_history_order` (`id_order`),
  KEY `id_employee` (`id_employee`),
  KEY `id_order_state` (`id_order_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_history`
--

LOCK TABLES `qlo_order_history` WRITE;
/*!40000 ALTER TABLE `qlo_order_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_invoice`
--

DROP TABLE IF EXISTS `qlo_order_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_invoice` (
  `id_order_invoice` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int NOT NULL,
  `number` int NOT NULL,
  `delivery_number` int NOT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `total_discount_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_discount_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_paid_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_paid_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_products` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_products_wt` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `shipping_tax_computation_method` int unsigned NOT NULL,
  `total_wrapping_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_wrapping_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `shop_address` text,
  `invoice_address` text,
  `delivery_address` text,
  `note` text,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_invoice`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_invoice`
--

LOCK TABLES `qlo_order_invoice` WRITE;
/*!40000 ALTER TABLE `qlo_order_invoice` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_invoice` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_invoice_payment`
--

DROP TABLE IF EXISTS `qlo_order_invoice_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_invoice_payment` (
  `id_order_invoice` int unsigned NOT NULL,
  `id_order_payment` int unsigned NOT NULL,
  `id_order_payment_detail` int unsigned NOT NULL,
  `id_order` int unsigned NOT NULL,
  PRIMARY KEY (`id_order_invoice`,`id_order_payment`),
  KEY `order_payment` (`id_order_payment`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_invoice_payment`
--

LOCK TABLES `qlo_order_invoice_payment` WRITE;
/*!40000 ALTER TABLE `qlo_order_invoice_payment` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_invoice_payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_invoice_tax`
--

DROP TABLE IF EXISTS `qlo_order_invoice_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_invoice_tax` (
  `id_order_invoice` int NOT NULL,
  `type` varchar(15) NOT NULL,
  `id_tax` int NOT NULL,
  `amount` decimal(10,6) NOT NULL DEFAULT '0.000000',
  KEY `id_tax` (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_invoice_tax`
--

LOCK TABLES `qlo_order_invoice_tax` WRITE;
/*!40000 ALTER TABLE `qlo_order_invoice_tax` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_invoice_tax` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_message`
--

DROP TABLE IF EXISTS `qlo_order_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_message` (
  `id_order_message` int unsigned NOT NULL AUTO_INCREMENT,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_message`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_message`
--

LOCK TABLES `qlo_order_message` WRITE;
/*!40000 ALTER TABLE `qlo_order_message` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_message` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_message_lang`
--

DROP TABLE IF EXISTS `qlo_order_message_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_message_lang` (
  `id_order_message` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id_order_message`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_message_lang`
--

LOCK TABLES `qlo_order_message_lang` WRITE;
/*!40000 ALTER TABLE `qlo_order_message_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_message_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_payment`
--

DROP TABLE IF EXISTS `qlo_order_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_payment` (
  `id_order_payment` int NOT NULL AUTO_INCREMENT,
  `order_reference` varchar(9) DEFAULT NULL,
  `id_currency` int unsigned NOT NULL,
  `amount` decimal(20,6) NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `payment_type` int unsigned NOT NULL,
  `conversion_rate` decimal(13,6) NOT NULL DEFAULT '1.000000',
  `transaction_id` varchar(254) DEFAULT NULL,
  `card_number` varchar(254) DEFAULT NULL,
  `card_brand` varchar(254) DEFAULT NULL,
  `card_expiration` char(7) DEFAULT NULL,
  `card_holder` varchar(254) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_payment`),
  KEY `order_reference` (`order_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_payment`
--

LOCK TABLES `qlo_order_payment` WRITE;
/*!40000 ALTER TABLE `qlo_order_payment` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_payment_detail`
--

DROP TABLE IF EXISTS `qlo_order_payment_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_payment_detail` (
  `id_order_payment_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order_payment` int unsigned NOT NULL,
  `id_order` int unsigned NOT NULL,
  `amount` decimal(20,6) NOT NULL,
  `receipt_number` int NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_payment_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_payment_detail`
--

LOCK TABLES `qlo_order_payment_detail` WRITE;
/*!40000 ALTER TABLE `qlo_order_payment_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_payment_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_return`
--

DROP TABLE IF EXISTS `qlo_order_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_return` (
  `id_order_return` int unsigned NOT NULL AUTO_INCREMENT,
  `id_customer` int unsigned NOT NULL,
  `id_order` int unsigned NOT NULL,
  `state` int unsigned NOT NULL DEFAULT '1',
  `id_transaction` varchar(100) NOT NULL DEFAULT '',
  `payment_mode` varchar(255) NOT NULL DEFAULT '',
  `refunded_amount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `question` text NOT NULL,
  `by_admin` tinyint unsigned NOT NULL DEFAULT '0',
  `id_return_type` tinyint unsigned NOT NULL DEFAULT '0',
  `return_type` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_order_return`),
  KEY `order_return_customer` (`id_customer`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_return`
--

LOCK TABLES `qlo_order_return` WRITE;
/*!40000 ALTER TABLE `qlo_order_return` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_return` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_return_detail`
--

DROP TABLE IF EXISTS `qlo_order_return_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_return_detail` (
  `id_order_return_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order_return` int unsigned NOT NULL,
  `id_htl_booking` int NOT NULL,
  `id_service_product_order_detail` int NOT NULL,
  `refunded_amount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `id_order_detail` int unsigned NOT NULL DEFAULT '0',
  `id_customization` int unsigned NOT NULL DEFAULT '0',
  `product_quantity` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_order_return_detail`),
  KEY `id_htl_booking` (`id_htl_booking`),
  KEY `id_service_product_order_detail` (`id_service_product_order_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_return_detail`
--

LOCK TABLES `qlo_order_return_detail` WRITE;
/*!40000 ALTER TABLE `qlo_order_return_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_return_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_return_state`
--

DROP TABLE IF EXISTS `qlo_order_return_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_return_state` (
  `id_order_return_state` int unsigned NOT NULL AUTO_INCREMENT,
  `color` varchar(32) DEFAULT NULL,
  `send_email_to_customer` tinyint unsigned NOT NULL DEFAULT '0',
  `send_email_to_superadmin` tinyint unsigned NOT NULL DEFAULT '0',
  `send_email_to_employee` tinyint unsigned NOT NULL DEFAULT '0',
  `send_email_to_hotelier` tinyint unsigned NOT NULL DEFAULT '0',
  `denied` tinyint unsigned NOT NULL DEFAULT '0',
  `refunded` tinyint unsigned NOT NULL DEFAULT '0',
  `module_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_order_return_state`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_return_state`
--

LOCK TABLES `qlo_order_return_state` WRITE;
/*!40000 ALTER TABLE `qlo_order_return_state` DISABLE KEYS */;
INSERT INTO `qlo_order_return_state` VALUES (1,'#4169E1',1,1,1,1,0,0,''),(2,'#DC143C',1,1,1,1,1,0,''),(3,'#108510',1,1,1,1,0,1,'');
/*!40000 ALTER TABLE `qlo_order_return_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_return_state_lang`
--

DROP TABLE IF EXISTS `qlo_order_return_state_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_return_state_lang` (
  `id_order_return_state` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `customer_template` varchar(64) NOT NULL,
  `admin_template` varchar(64) NOT NULL,
  PRIMARY KEY (`id_order_return_state`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_return_state_lang`
--

LOCK TABLES `qlo_order_return_state_lang` WRITE;
/*!40000 ALTER TABLE `qlo_order_return_state_lang` DISABLE KEYS */;
INSERT INTO `qlo_order_return_state_lang` VALUES (1,1,'Pending','order_refund_pending_customer','order_refund_pending_admin'),(2,1,'Denied','order_refund_denied_customer','order_refund_denied_admin'),(3,1,'Completed','order_refund_completed_customer','order_refund_completed_admin');
/*!40000 ALTER TABLE `qlo_order_return_state_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_slip`
--

DROP TABLE IF EXISTS `qlo_order_slip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_slip` (
  `id_order_slip` int unsigned NOT NULL AUTO_INCREMENT,
  `conversion_rate` decimal(13,6) NOT NULL DEFAULT '1.000000',
  `id_customer` int unsigned NOT NULL,
  `id_order` int unsigned NOT NULL,
  `total_products_tax_excl` decimal(20,6) DEFAULT NULL,
  `total_products_tax_incl` decimal(20,6) DEFAULT NULL,
  `total_shipping_tax_excl` decimal(20,6) DEFAULT NULL,
  `total_shipping_tax_incl` decimal(20,6) DEFAULT NULL,
  `shipping_cost` tinyint unsigned NOT NULL DEFAULT '0',
  `amount` decimal(10,2) NOT NULL,
  `shipping_cost_amount` decimal(10,2) NOT NULL,
  `partial` tinyint(1) NOT NULL,
  `redeem_status` tinyint(1) NOT NULL DEFAULT '0',
  `id_cart_rule` int unsigned NOT NULL DEFAULT '0',
  `order_slip_type` tinyint unsigned NOT NULL DEFAULT '0',
  `remark` text,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_order_slip`),
  KEY `order_slip_customer` (`id_customer`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_slip`
--

LOCK TABLES `qlo_order_slip` WRITE;
/*!40000 ALTER TABLE `qlo_order_slip` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_slip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_slip_detail`
--

DROP TABLE IF EXISTS `qlo_order_slip_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_slip_detail` (
  `id_order_slip` int unsigned NOT NULL,
  `id_order_detail` int unsigned NOT NULL,
  `id_htl_booking` int unsigned NOT NULL,
  `id_service_product_order_detail` int unsigned NOT NULL,
  `product_quantity` int unsigned NOT NULL DEFAULT '0',
  `unit_price_tax_excl` decimal(20,6) DEFAULT NULL,
  `unit_price_tax_incl` decimal(20,6) DEFAULT NULL,
  `total_price_tax_excl` decimal(20,6) DEFAULT NULL,
  `total_price_tax_incl` decimal(20,6) DEFAULT NULL,
  `amount_tax_excl` decimal(20,6) DEFAULT NULL,
  `amount_tax_incl` decimal(20,6) DEFAULT NULL,
  PRIMARY KEY (`id_order_slip`,`id_htl_booking`,`id_service_product_order_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_slip_detail`
--

LOCK TABLES `qlo_order_slip_detail` WRITE;
/*!40000 ALTER TABLE `qlo_order_slip_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_slip_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_slip_detail_tax`
--

DROP TABLE IF EXISTS `qlo_order_slip_detail_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_slip_detail_tax` (
  `id_order_slip_detail` int unsigned NOT NULL,
  `id_tax` int unsigned NOT NULL,
  `unit_amount` decimal(16,6) NOT NULL DEFAULT '0.000000',
  `total_amount` decimal(16,6) NOT NULL DEFAULT '0.000000',
  KEY `id_order_slip_detail` (`id_order_slip_detail`),
  KEY `id_tax` (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_slip_detail_tax`
--

LOCK TABLES `qlo_order_slip_detail_tax` WRITE;
/*!40000 ALTER TABLE `qlo_order_slip_detail_tax` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_order_slip_detail_tax` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_state`
--

DROP TABLE IF EXISTS `qlo_order_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_state` (
  `id_order_state` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice` tinyint unsigned DEFAULT '0',
  `send_email` tinyint unsigned NOT NULL DEFAULT '0',
  `module_name` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `unremovable` tinyint unsigned NOT NULL,
  `hidden` tinyint unsigned NOT NULL DEFAULT '0',
  `logable` tinyint(1) NOT NULL DEFAULT '0',
  `delivery` tinyint unsigned NOT NULL DEFAULT '0',
  `shipped` tinyint unsigned NOT NULL DEFAULT '0',
  `paid` tinyint unsigned NOT NULL DEFAULT '0',
  `pdf_invoice` tinyint unsigned NOT NULL DEFAULT '0',
  `pdf_delivery` tinyint unsigned NOT NULL DEFAULT '0',
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_order_state`),
  KEY `module_name` (`module_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_state`
--

LOCK TABLES `qlo_order_state` WRITE;
/*!40000 ALTER TABLE `qlo_order_state` DISABLE KEYS */;
INSERT INTO `qlo_order_state` VALUES (1,0,1,'','#4169E1',1,0,0,0,0,0,0,0,0),(2,1,1,'','#32CD32',1,0,1,0,0,1,1,0,0),(3,1,1,'','#FF8C00',1,0,0,0,0,0,0,0,0),(4,0,1,'','#DC143C',1,0,0,0,0,0,0,0,0),(5,1,1,'','#ec2e15',1,0,0,0,0,0,0,0,0),(6,0,1,'','#8f0621',1,0,0,0,0,0,0,0,0),(7,1,0,'','#FF69B4',1,0,0,0,0,1,0,0,0),(8,1,1,'','#F0B656',1,0,1,0,0,0,0,0,0),(9,0,0,'','#4169E1',1,0,0,0,0,0,0,0,0),(10,1,0,'','#32CD32',1,0,1,0,0,0,0,0,0),(11,0,0,'','#FF69B4',1,0,0,0,0,0,0,0,0),(12,0,0,'','#FF69B4',1,0,0,0,0,0,0,0,0);
/*!40000 ALTER TABLE `qlo_order_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_order_state_lang`
--

DROP TABLE IF EXISTS `qlo_order_state_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_order_state_lang` (
  `id_order_state` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `template` varchar(64) NOT NULL,
  PRIMARY KEY (`id_order_state`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_order_state_lang`
--

LOCK TABLES `qlo_order_state_lang` WRITE;
/*!40000 ALTER TABLE `qlo_order_state_lang` DISABLE KEYS */;
INSERT INTO `qlo_order_state_lang` VALUES (1,1,'Awaiting payment','awaiting_payment'),(2,1,'Complete payment received','payment_accepted'),(3,1,'Processing in progress','processing'),(4,1,'Canceled','order_canceled'),(5,1,'Refunded','refund'),(6,1,'Payment error','payment_error'),(7,1,'Overbooking (Paid)',''),(8,1,'Partial payment received','partial_payment_accepted'),(9,1,'Awaiting remote payment',''),(10,1,'Remote payment accepted','payment_accepted'),(11,1,'Overbooking (Not paid)',''),(12,1,'Overbooking (Partial payment)','');
/*!40000 ALTER TABLE `qlo_order_state_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_orders`
--

DROP TABLE IF EXISTS `qlo_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_orders` (
  `id_order` int unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(9) DEFAULT NULL,
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_carrier` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_customer` int unsigned NOT NULL,
  `id_cart` int unsigned NOT NULL,
  `id_currency` int unsigned NOT NULL,
  `id_address_delivery` int unsigned NOT NULL,
  `id_address_invoice` int unsigned NOT NULL,
  `id_address_tax` int unsigned NOT NULL,
  `current_state` int unsigned NOT NULL,
  `secure_key` varchar(32) NOT NULL DEFAULT '-1',
  `payment` varchar(255) NOT NULL,
  `payment_type` tinyint unsigned NOT NULL,
  `conversion_rate` decimal(13,6) NOT NULL DEFAULT '1.000000',
  `module` varchar(255) DEFAULT NULL,
  `recyclable` tinyint unsigned NOT NULL DEFAULT '0',
  `gift` tinyint unsigned NOT NULL DEFAULT '0',
  `gift_message` text,
  `mobile_theme` tinyint(1) NOT NULL DEFAULT '0',
  `shipping_number` varchar(64) DEFAULT NULL,
  `total_discounts` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_discounts_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_discounts_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_paid` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_paid_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_paid_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_paid_real` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_products` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_products_wt` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_shipping_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `carrier_tax_rate` decimal(10,3) NOT NULL DEFAULT '0.000',
  `total_wrapping` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_wrapping_tax_incl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `total_wrapping_tax_excl` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `round_mode` tinyint(1) NOT NULL DEFAULT '2',
  `round_type` tinyint(1) NOT NULL DEFAULT '1',
  `invoice_number` int unsigned NOT NULL DEFAULT '0',
  `delivery_number` int unsigned NOT NULL DEFAULT '0',
  `invoice_date` datetime NOT NULL,
  `delivery_date` datetime NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `valid` int unsigned NOT NULL DEFAULT '0',
  `is_advance_payment` tinyint(1) NOT NULL DEFAULT '0',
  `advance_paid_amount` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `with_occupancy` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_order`),
  KEY `reference` (`reference`),
  KEY `id_customer` (`id_customer`),
  KEY `id_cart` (`id_cart`),
  KEY `invoice_number` (`invoice_number`),
  KEY `id_carrier` (`id_carrier`),
  KEY `id_lang` (`id_lang`),
  KEY `id_currency` (`id_currency`),
  KEY `id_address_delivery` (`id_address_delivery`),
  KEY `id_address_invoice` (`id_address_invoice`),
  KEY `id_shop_group` (`id_shop_group`),
  KEY `current_state` (`current_state`),
  KEY `id_shop` (`id_shop`),
  KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_orders`
--

LOCK TABLES `qlo_orders` WRITE;
/*!40000 ALTER TABLE `qlo_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_pack`
--

DROP TABLE IF EXISTS `qlo_pack`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_pack` (
  `id_product_pack` int unsigned NOT NULL,
  `id_product_item` int unsigned NOT NULL,
  `id_product_attribute_item` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_product_pack`,`id_product_item`,`id_product_attribute_item`),
  KEY `product_item` (`id_product_item`,`id_product_attribute_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_pack`
--

LOCK TABLES `qlo_pack` WRITE;
/*!40000 ALTER TABLE `qlo_pack` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_pack` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_page`
--

DROP TABLE IF EXISTS `qlo_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_page` (
  `id_page` int unsigned NOT NULL AUTO_INCREMENT,
  `id_page_type` int unsigned NOT NULL,
  `id_object` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id_page`),
  KEY `id_page_type` (`id_page_type`),
  KEY `id_object` (`id_object`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_page`
--

LOCK TABLES `qlo_page` WRITE;
/*!40000 ALTER TABLE `qlo_page` DISABLE KEYS */;
INSERT INTO `qlo_page` VALUES (1,1,NULL);
/*!40000 ALTER TABLE `qlo_page` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_page_type`
--

DROP TABLE IF EXISTS `qlo_page_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_page_type` (
  `id_page_type` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_page_type`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_page_type`
--

LOCK TABLES `qlo_page_type` WRITE;
/*!40000 ALTER TABLE `qlo_page_type` DISABLE KEYS */;
INSERT INTO `qlo_page_type` VALUES (1,'index');
/*!40000 ALTER TABLE `qlo_page_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_page_viewed`
--

DROP TABLE IF EXISTS `qlo_page_viewed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_page_viewed` (
  `id_page` int unsigned NOT NULL,
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_date_range` int unsigned NOT NULL,
  `counter` int unsigned NOT NULL,
  PRIMARY KEY (`id_page`,`id_date_range`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_page_viewed`
--

LOCK TABLES `qlo_page_viewed` WRITE;
/*!40000 ALTER TABLE `qlo_page_viewed` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_page_viewed` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_pagenotfound`
--

DROP TABLE IF EXISTS `qlo_pagenotfound`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_pagenotfound` (
  `id_pagenotfound` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `request_uri` varchar(256) NOT NULL,
  `http_referer` varchar(256) NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_pagenotfound`),
  KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_pagenotfound`
--

LOCK TABLES `qlo_pagenotfound` WRITE;
/*!40000 ALTER TABLE `qlo_pagenotfound` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_pagenotfound` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product`
--

DROP TABLE IF EXISTS `qlo_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product` (
  `id_product` int unsigned NOT NULL AUTO_INCREMENT,
  `id_supplier` int unsigned DEFAULT NULL,
  `id_manufacturer` int unsigned DEFAULT NULL,
  `id_category_default` int unsigned DEFAULT NULL,
  `id_shop_default` int unsigned NOT NULL DEFAULT '1',
  `id_tax_rules_group` int unsigned NOT NULL,
  `on_sale` tinyint unsigned NOT NULL DEFAULT '0',
  `online_only` tinyint unsigned NOT NULL DEFAULT '0',
  `ean13` varchar(13) DEFAULT NULL,
  `upc` varchar(12) DEFAULT NULL,
  `ecotax` decimal(17,6) NOT NULL DEFAULT '0.000000',
  `quantity` int NOT NULL DEFAULT '0',
  `minimal_quantity` int unsigned NOT NULL DEFAULT '1',
  `allow_multiple_quantity` tinyint unsigned NOT NULL DEFAULT '1',
  `max_quantity` int unsigned NOT NULL DEFAULT '1',
  `price_calculation_method` tinyint unsigned NOT NULL DEFAULT '1',
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `wholesale_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unity` varchar(255) DEFAULT NULL,
  `unit_price_ratio` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `additional_shipping_cost` decimal(20,2) NOT NULL DEFAULT '0.00',
  `reference` varchar(32) DEFAULT NULL,
  `supplier_reference` varchar(32) DEFAULT NULL,
  `location` varchar(64) DEFAULT NULL,
  `width` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `height` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `depth` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `weight` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `out_of_stock` int unsigned NOT NULL DEFAULT '2',
  `quantity_discount` tinyint(1) DEFAULT '0',
  `customizable` tinyint NOT NULL DEFAULT '0',
  `uploadable_files` tinyint NOT NULL DEFAULT '0',
  `text_fields` tinyint NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `redirect_type` enum('','404','301','302') NOT NULL DEFAULT '',
  `id_product_redirected` int unsigned NOT NULL DEFAULT '0',
  `available_for_order` tinyint(1) NOT NULL DEFAULT '1',
  `auto_add_to_cart` tinyint(1) NOT NULL DEFAULT '0',
  `price_addition_type` tinyint(1) NOT NULL DEFAULT '1',
  `show_at_front` tinyint(1) NOT NULL DEFAULT '1',
  `selling_preference_type` tinyint(1) NOT NULL DEFAULT '1',
  `price_display_method` tinyint(1) NOT NULL DEFAULT '1',
  `available_date` date NOT NULL DEFAULT '0000-00-00',
  `condition` enum('new','used','refurbished') NOT NULL DEFAULT 'new',
  `show_price` tinyint(1) NOT NULL DEFAULT '1',
  `indexed` tinyint(1) NOT NULL DEFAULT '0',
  `visibility` enum('both','catalog','search','none') NOT NULL DEFAULT 'both',
  `cache_is_pack` tinyint(1) NOT NULL DEFAULT '0',
  `cache_has_attachments` tinyint(1) NOT NULL DEFAULT '0',
  `is_virtual` tinyint(1) NOT NULL DEFAULT '0',
  `booking_product` tinyint(1) NOT NULL DEFAULT '1',
  `cache_default_attribute` int unsigned DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `advanced_stock_management` tinyint(1) NOT NULL DEFAULT '0',
  `pack_stock_type` int unsigned NOT NULL DEFAULT '3',
  PRIMARY KEY (`id_product`),
  KEY `product_supplier` (`id_supplier`),
  KEY `product_manufacturer` (`id_manufacturer`,`id_product`),
  KEY `id_category_default` (`id_category_default`),
  KEY `indexed` (`indexed`),
  KEY `date_add` (`date_add`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product`
--

LOCK TABLES `qlo_product` WRITE;
/*!40000 ALTER TABLE `qlo_product` DISABLE KEYS */;
INSERT INTO `qlo_product` VALUES (1,0,0,7,1,1,0,0,'','',0.000000,0,1,0,0,0,1000.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,0,1,0,1,'0000-00-00','new',1,1,'both',0,0,1,1,0,'2026-09-03 18:48:55','2026-09-03 18:48:55',0,3),(2,0,0,7,1,1,0,0,'','',0.000000,0,1,0,0,0,1500.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,0,1,0,1,'0000-00-00','new',1,1,'both',0,0,1,1,0,'2026-09-03 18:49:04','2026-09-03 18:49:05',0,3),(3,0,0,7,1,1,0,0,'','',0.000000,0,1,0,0,0,2000.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,0,1,0,1,'0000-00-00','new',1,1,'both',0,0,1,1,0,'2026-09-03 18:49:13','2026-09-03 18:49:13',0,3),(4,0,0,7,1,1,0,0,'','',0.000000,0,1,0,0,0,2500.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,0,1,0,1,'0000-00-00','new',1,1,'both',0,0,1,1,0,'2026-09-03 18:49:21','2026-09-03 18:49:21',0,3),(5,0,0,11,1,0,0,0,'','',0.000000,0,1,0,0,2,250.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,1,1,0,1,1,'0000-00-00','new',1,1,'both',0,0,1,0,0,'2026-09-03 18:49:30','2026-09-03 18:49:30',0,3),(6,0,0,11,1,1,0,0,'','',0.000000,0,1,0,0,1,250.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,1,2,0,1,1,'0000-00-00','new',1,1,'both',0,0,1,0,0,'2026-09-03 18:49:34','2026-09-03 18:49:34',0,3),(7,0,0,9,1,1,0,0,'','',0.000000,0,1,0,0,1,50.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,1,1,1,1,'0000-00-00','new',1,1,'both',0,0,1,0,0,'2026-09-03 18:49:37','2026-09-03 18:49:37',0,3),(8,0,0,9,1,1,0,0,'','',0.000000,0,1,0,0,1,200.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,1,1,1,1,'0000-00-00','new',1,1,'both',0,0,1,0,0,'2026-09-03 18:49:42','2026-09-03 18:49:42',0,3),(9,0,0,8,1,1,0,0,'','',0.000000,0,1,0,0,2,350.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,1,1,1,1,'0000-00-00','new',1,1,'both',0,0,1,0,0,'2026-09-03 18:49:47','2026-09-03 18:49:47',0,3),(10,0,0,8,1,1,0,0,'','',0.000000,0,1,0,0,2,450.000000,0.000000,'',0.000000,0.00,'','','',0.000000,0.000000,0.000000,0.000000,2,0,0,0,0,1,'',0,1,0,1,1,1,1,'0000-00-00','new',1,1,'both',0,0,1,0,0,'2026-09-03 18:49:51','2026-09-03 18:49:51',0,3);
/*!40000 ALTER TABLE `qlo_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_attachment`
--

DROP TABLE IF EXISTS `qlo_product_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_attachment` (
  `id_product` int unsigned NOT NULL,
  `id_attachment` int unsigned NOT NULL,
  PRIMARY KEY (`id_product`,`id_attachment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_attachment`
--

LOCK TABLES `qlo_product_attachment` WRITE;
/*!40000 ALTER TABLE `qlo_product_attachment` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_attachment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_attribute`
--

DROP TABLE IF EXISTS `qlo_product_attribute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_attribute` (
  `id_product_attribute` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `reference` varchar(32) DEFAULT NULL,
  `supplier_reference` varchar(32) DEFAULT NULL,
  `location` varchar(64) DEFAULT NULL,
  `ean13` varchar(13) DEFAULT NULL,
  `upc` varchar(12) DEFAULT NULL,
  `wholesale_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `ecotax` decimal(17,6) NOT NULL DEFAULT '0.000000',
  `quantity` int NOT NULL DEFAULT '0',
  `weight` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unit_price_impact` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `default_on` tinyint unsigned DEFAULT NULL,
  `minimal_quantity` int unsigned NOT NULL DEFAULT '1',
  `available_date` date NOT NULL DEFAULT '0000-00-00',
  PRIMARY KEY (`id_product_attribute`),
  UNIQUE KEY `product_default` (`id_product`,`default_on`),
  KEY `product_attribute_product` (`id_product`),
  KEY `reference` (`reference`),
  KEY `supplier_reference` (`supplier_reference`),
  KEY `id_product_id_product_attribute` (`id_product_attribute`,`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_attribute`
--

LOCK TABLES `qlo_product_attribute` WRITE;
/*!40000 ALTER TABLE `qlo_product_attribute` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_attribute` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_attribute_combination`
--

DROP TABLE IF EXISTS `qlo_product_attribute_combination`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_attribute_combination` (
  `id_attribute` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  PRIMARY KEY (`id_attribute`,`id_product_attribute`),
  KEY `id_product_attribute` (`id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_attribute_combination`
--

LOCK TABLES `qlo_product_attribute_combination` WRITE;
/*!40000 ALTER TABLE `qlo_product_attribute_combination` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_attribute_combination` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_attribute_image`
--

DROP TABLE IF EXISTS `qlo_product_attribute_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_attribute_image` (
  `id_product_attribute` int unsigned NOT NULL,
  `id_image` int unsigned NOT NULL,
  PRIMARY KEY (`id_product_attribute`,`id_image`),
  KEY `id_image` (`id_image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_attribute_image`
--

LOCK TABLES `qlo_product_attribute_image` WRITE;
/*!40000 ALTER TABLE `qlo_product_attribute_image` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_attribute_image` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_attribute_shop`
--

DROP TABLE IF EXISTS `qlo_product_attribute_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_attribute_shop` (
  `id_product` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `wholesale_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `ecotax` decimal(17,6) NOT NULL DEFAULT '0.000000',
  `weight` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unit_price_impact` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `default_on` tinyint unsigned DEFAULT NULL,
  `minimal_quantity` int unsigned NOT NULL DEFAULT '1',
  `available_date` date NOT NULL DEFAULT '0000-00-00',
  PRIMARY KEY (`id_product_attribute`,`id_shop`),
  UNIQUE KEY `id_product` (`id_product`,`id_shop`,`default_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_attribute_shop`
--

LOCK TABLES `qlo_product_attribute_shop` WRITE;
/*!40000 ALTER TABLE `qlo_product_attribute_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_attribute_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_carrier`
--

DROP TABLE IF EXISTS `qlo_product_carrier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_carrier` (
  `id_product` int unsigned NOT NULL,
  `id_carrier_reference` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_product`,`id_carrier_reference`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_carrier`
--

LOCK TABLES `qlo_product_carrier` WRITE;
/*!40000 ALTER TABLE `qlo_product_carrier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_carrier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_country_tax`
--

DROP TABLE IF EXISTS `qlo_product_country_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_country_tax` (
  `id_product` int NOT NULL,
  `id_country` int NOT NULL,
  `id_tax` int NOT NULL,
  PRIMARY KEY (`id_product`,`id_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_country_tax`
--

LOCK TABLES `qlo_product_country_tax` WRITE;
/*!40000 ALTER TABLE `qlo_product_country_tax` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_country_tax` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_download`
--

DROP TABLE IF EXISTS `qlo_product_download`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_download` (
  `id_product_download` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `display_filename` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_expiration` datetime DEFAULT NULL,
  `nb_days_accessible` int unsigned DEFAULT NULL,
  `nb_downloadable` int unsigned DEFAULT '1',
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  `is_shareable` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_product_download`),
  UNIQUE KEY `id_product` (`id_product`),
  KEY `product_active` (`id_product`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_download`
--

LOCK TABLES `qlo_product_download` WRITE;
/*!40000 ALTER TABLE `qlo_product_download` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_download` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_group_reduction_cache`
--

DROP TABLE IF EXISTS `qlo_product_group_reduction_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_group_reduction_cache` (
  `id_product` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  `reduction` decimal(4,3) NOT NULL,
  PRIMARY KEY (`id_product`,`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_group_reduction_cache`
--

LOCK TABLES `qlo_product_group_reduction_cache` WRITE;
/*!40000 ALTER TABLE `qlo_product_group_reduction_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_group_reduction_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_lang`
--

DROP TABLE IF EXISTS `qlo_product_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_lang` (
  `id_product` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned NOT NULL,
  `description` text,
  `description_short` text,
  `link_rewrite` varchar(128) NOT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_title` varchar(128) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `available_now` varchar(255) DEFAULT NULL,
  `available_later` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_product`,`id_shop`,`id_lang`),
  KEY `id_lang` (`id_lang`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_lang`
--

LOCK TABLES `qlo_product_lang` WRITE;
/*!40000 ALTER TABLE `qlo_product_lang` DISABLE KEYS */;
INSERT INTO `qlo_product_lang` VALUES (1,1,1,'Make yourself at home in our spacious General Rooms, tailored for families or groups seeking comfort and convenience. This accommodation offers multiple bedrooms, ensuring everyone has their own space to relax and recharge. The cozy living area is perfect for bonding over games or movie nights, while the well-equipped kitchenette allows for effortless meal preparation. Each room is equipped with flat-screen TVs and complimentary Wi-Fi, ensuring everyone stays entertained and connected throughout their stay. Ideal for creating lasting memories together in a setting of warmth and hospitality.','Our General Rooms offer space and comfort with multiple bedrooms and a cozy living area. Enjoy flat-screen TVs, complimentary Wi-Fi, and a kitchenette for a perfect family getaway.','general-rooms','','','','General Rooms','',''),(2,1,1,'Experience the epitome of luxury in our Deluxe Room. Gaze out over serene waters from the comfort of your spacious accommodation, furnished with a plush king-sized bed and elegant decor. Whether unwinding in the sitting area or enjoying modern amenities like complimentary Wi-Fi and a flat-screen TV, every detail ensures your stay is both relaxing and indulgent. Perfect for guests seeking a peaceful retreat with breathtaking views and refined comfort.','Enjoy lake views from our Deluxe Rooms with a king-sized bed, elegant furnishings, and a spacious sitting area. Perfect for guests seeking comfort, luxury, and modern amenities.','delux-rooms','','','','Delux Rooms','',''),(3,1,1,'Elevate your stay with the expansive comfort of our Executive Room. Designed for discerning travelers, this room features separate living and sleeping areas adorned with sophisticated furnishings and luxurious touches. Pamper yourself in the deluxe bathroom, complete with a soaking tub and premium toiletries. Enjoy exclusive access to our executive lounge for unwinding with complimentary refreshments and tranquil surroundings. Ideal for business travelers or those seeking extra space and privacy in a setting of unparalleled sophistication.','Indulge in our Executive Rooms, featuring separate living and sleeping areas, a luxurious bathroom, and exclusive lounge access. Ideal for business travelers seeking privacy','executive-rooms','','','','Executive Rooms','',''),(4,1,1,'Indulge in ultimate tranquility in our Luxury Room, where expansive vistas and serene surroundings create a peaceful oasis. Wake up to panoramic views complemented by elegant decor and a comfortable queen-sized bed. Whether catching up on work at the well-appointed workspace or simply relaxing in serene ambiance, this room offers a luxurious retreat for both business and leisure travelers alike. Perfect for those seeking comfort, style, and breathtaking views in an unparalleled setting.','Retreat to tranquility in our Luxury Rooms with expansive views. Featuring a queen-sized bed, workspace, and serene decor, perfect for business and leisure travelers alike.','luxury-rooms','','','','Luxury Rooms','',''),(5,1,1,'Ensure a comfortable stay with our room maintenance service, keeping your accommodation pristine and hassle-free throughout your visit.','Ensure a comfortable stay with our room maintenance service, keeping your accommodation pristine and hassle-free throughout your visit.','room-maintenance-fees','','','','Room Maintenance Fees','',''),(6,1,1,'Navigate our website effortlessly with seamless handling, ensuring reliable, high-speed access for an enjoyable browsing experience throughout your online journey.','Navigate our website effortlessly with seamless handling, ensuring reliable, high-speed access for an enjoyable browsing experience throughout your online journey.','internet-handling-charges','','','','Internet Handling Charges','',''),(7,1,1,'Experience convenience from touchdown to check-in with our efficient airport shuttle service, whisking you to your accommodation with ease and comfort.','Experience convenience from touchdown to check-in with our efficient airport shuttle service, whisking you to your accommodation with ease and comfort.','airport-shuttle','','','','Airport Shuttle','',''),(8,1,1,'Explore the city conveniently with our cab-on-demand service, giving you the freedom to travel and discover local attractions at your own pace.','Explore the city conveniently with our cab-on-demand service, giving you the freedom to travel and discover local attractions at your own pace.','cab-on-demand','','','','Cab on Demand','',''),(9,1,1,'Start your day right with a delicious and hearty breakfast, thoughtfully prepared to fuel your adventures and make your mornings exceptional.','Start your day right with a delicious and hearty breakfast, thoughtfully prepared to fuel your adventures and make your mornings exceptional.','breakfast','','','','Breakfast','',''),(10,1,1,'Wind down in the evening with a delectable dinner spread, offering a culinary journey that delights your taste buds and completes your day with satisfaction.','Wind down in the evening with a delectable dinner spread, offering a culinary journey that delights your taste buds and completes your day with satisfaction.','dinner','','','','Dinner','','');
/*!40000 ALTER TABLE `qlo_product_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_option`
--

DROP TABLE IF EXISTS `qlo_product_option`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_option` (
  `id_product_option` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `price_impact` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_product_option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_option`
--

LOCK TABLES `qlo_product_option` WRITE;
/*!40000 ALTER TABLE `qlo_product_option` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_option` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_option_lang`
--

DROP TABLE IF EXISTS `qlo_product_option_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_option_lang` (
  `id_product_option` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id_product_option`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_option_lang`
--

LOCK TABLES `qlo_product_option_lang` WRITE;
/*!40000 ALTER TABLE `qlo_product_option_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_option_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_sale`
--

DROP TABLE IF EXISTS `qlo_product_sale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_sale` (
  `id_product` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '0',
  `sale_nbr` int unsigned NOT NULL DEFAULT '0',
  `date_upd` date NOT NULL,
  PRIMARY KEY (`id_product`),
  KEY `quantity` (`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_sale`
--

LOCK TABLES `qlo_product_sale` WRITE;
/*!40000 ALTER TABLE `qlo_product_sale` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_sale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_shop`
--

DROP TABLE IF EXISTS `qlo_product_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_shop` (
  `id_product` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `id_category_default` int unsigned DEFAULT NULL,
  `id_tax_rules_group` int unsigned NOT NULL,
  `on_sale` tinyint unsigned NOT NULL DEFAULT '0',
  `online_only` tinyint unsigned NOT NULL DEFAULT '0',
  `ecotax` decimal(17,6) NOT NULL DEFAULT '0.000000',
  `minimal_quantity` int unsigned NOT NULL DEFAULT '1',
  `allow_multiple_quantity` tinyint unsigned NOT NULL DEFAULT '1',
  `max_quantity` int unsigned NOT NULL DEFAULT '1',
  `price_calculation_method` tinyint unsigned NOT NULL DEFAULT '1',
  `price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `wholesale_price` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `unity` varchar(255) DEFAULT NULL,
  `unit_price_ratio` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `additional_shipping_cost` decimal(20,2) NOT NULL DEFAULT '0.00',
  `customizable` tinyint NOT NULL DEFAULT '0',
  `uploadable_files` tinyint NOT NULL DEFAULT '0',
  `text_fields` tinyint NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `redirect_type` enum('','404','301','302') NOT NULL DEFAULT '',
  `id_product_redirected` int unsigned NOT NULL DEFAULT '0',
  `available_for_order` tinyint(1) NOT NULL DEFAULT '1',
  `auto_add_to_cart` tinyint(1) NOT NULL DEFAULT '0',
  `price_addition_type` tinyint(1) NOT NULL DEFAULT '1',
  `show_at_front` tinyint(1) NOT NULL DEFAULT '1',
  `price_display_method` tinyint(1) NOT NULL DEFAULT '1',
  `available_date` date NOT NULL DEFAULT '0000-00-00',
  `condition` enum('new','used','refurbished') NOT NULL DEFAULT 'new',
  `show_price` tinyint(1) NOT NULL DEFAULT '1',
  `indexed` tinyint(1) NOT NULL DEFAULT '0',
  `visibility` enum('both','catalog','search','none') NOT NULL DEFAULT 'both',
  `cache_default_attribute` int unsigned DEFAULT NULL,
  `advanced_stock_management` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `pack_stock_type` int unsigned NOT NULL DEFAULT '3',
  PRIMARY KEY (`id_product`,`id_shop`),
  KEY `id_category_default` (`id_category_default`),
  KEY `date_add` (`date_add`,`active`,`visibility`),
  KEY `indexed` (`indexed`,`active`,`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_shop`
--

LOCK TABLES `qlo_product_shop` WRITE;
/*!40000 ALTER TABLE `qlo_product_shop` DISABLE KEYS */;
INSERT INTO `qlo_product_shop` VALUES (1,1,7,1,0,0,0.000000,1,0,0,0,1000.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,0,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:48:55','2026-09-03 18:48:55',3),(2,1,7,1,0,0,0.000000,1,0,0,0,1500.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,0,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:04','2026-09-03 18:49:05',3),(3,1,7,1,0,0,0.000000,1,0,0,0,2000.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,0,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:13','2026-09-03 18:49:13',3),(4,1,7,1,0,0,0.000000,1,0,0,0,2500.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,0,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:21','2026-09-03 18:49:21',3),(5,1,11,0,0,0,0.000000,1,0,0,2,250.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,1,1,0,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:30','2026-09-03 18:49:30',3),(6,1,11,1,0,0,0.000000,1,0,0,1,250.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,1,2,0,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:34','2026-09-03 18:49:34',3),(7,1,9,1,0,0,0.000000,1,0,0,1,50.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,1,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:37','2026-09-03 18:49:37',3),(8,1,9,1,0,0,0.000000,1,0,0,1,200.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,1,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:42','2026-09-03 18:49:42',3),(9,1,8,1,0,0,0.000000,1,0,0,2,350.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,1,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:47','2026-09-03 18:49:47',3),(10,1,8,1,0,0,0.000000,1,0,0,2,450.000000,0.000000,'',0.000000,0.00,0,0,0,1,'',0,1,0,1,1,1,'0000-00-00','new',1,1,'both',0,0,'2026-09-03 18:49:51','2026-09-03 18:49:51',3);
/*!40000 ALTER TABLE `qlo_product_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_supplier`
--

DROP TABLE IF EXISTS `qlo_product_supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_supplier` (
  `id_product_supplier` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL DEFAULT '0',
  `id_supplier` int unsigned NOT NULL,
  `product_supplier_reference` varchar(32) DEFAULT NULL,
  `product_supplier_price_te` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `id_currency` int unsigned NOT NULL,
  PRIMARY KEY (`id_product_supplier`),
  UNIQUE KEY `id_product` (`id_product`,`id_product_attribute`,`id_supplier`),
  KEY `id_supplier` (`id_supplier`,`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_supplier`
--

LOCK TABLES `qlo_product_supplier` WRITE;
/*!40000 ALTER TABLE `qlo_product_supplier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_product_tag`
--

DROP TABLE IF EXISTS `qlo_product_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_product_tag` (
  `id_product` int unsigned NOT NULL,
  `id_tag` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  PRIMARY KEY (`id_product`,`id_tag`),
  KEY `id_tag` (`id_tag`),
  KEY `id_lang` (`id_lang`,`id_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_product_tag`
--

LOCK TABLES `qlo_product_tag` WRITE;
/*!40000 ALTER TABLE `qlo_product_tag` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_product_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_profile`
--

DROP TABLE IF EXISTS `qlo_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_profile` (
  `id_profile` int unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id_profile`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_profile`
--

LOCK TABLES `qlo_profile` WRITE;
/*!40000 ALTER TABLE `qlo_profile` DISABLE KEYS */;
INSERT INTO `qlo_profile` VALUES (1);
/*!40000 ALTER TABLE `qlo_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_profile_lang`
--

DROP TABLE IF EXISTS `qlo_profile_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_profile_lang` (
  `id_lang` int unsigned NOT NULL,
  `id_profile` int unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`id_profile`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_profile_lang`
--

LOCK TABLES `qlo_profile_lang` WRITE;
/*!40000 ALTER TABLE `qlo_profile_lang` DISABLE KEYS */;
INSERT INTO `qlo_profile_lang` VALUES (1,1,'SuperAdmin');
/*!40000 ALTER TABLE `qlo_profile_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_pulse_api_token`
--

DROP TABLE IF EXISTS `qlo_pulse_api_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_pulse_api_token` (
  `id_pulse_api_token` int unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(128) DEFAULT NULL,
  `token` char(64) NOT NULL,
  `scopes` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_pulse_api_token`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_pulse_api_token`
--

LOCK TABLES `qlo_pulse_api_token` WRITE;
/*!40000 ALTER TABLE `qlo_pulse_api_token` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_pulse_api_token` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_pulse_audit`
--

DROP TABLE IF EXISTS `qlo_pulse_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_pulse_audit` (
  `id_pulse_audit` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(64) NOT NULL,
  `event` varchar(64) NOT NULL,
  `id_employee` int unsigned DEFAULT NULL,
  `entity` varchar(64) DEFAULT NULL,
  `id_entity` int unsigned DEFAULT NULL,
  `payload` text,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_pulse_audit`),
  KEY `mod_ev` (`module`,`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_pulse_audit`
--

LOCK TABLES `qlo_pulse_audit` WRITE;
/*!40000 ALTER TABLE `qlo_pulse_audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_pulse_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_pulse_license_log`
--

DROP TABLE IF EXISTS `qlo_pulse_license_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_pulse_license_log` (
  `id_pulse_license_log` int unsigned NOT NULL AUTO_INCREMENT,
  `event` varchar(32) NOT NULL,
  `lid` varchar(64) DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `fingerprint` char(40) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_pulse_license_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_pulse_license_log`
--

LOCK TABLES `qlo_pulse_license_log` WRITE;
/*!40000 ALTER TABLE `qlo_pulse_license_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_pulse_license_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_pulse_setting`
--

DROP TABLE IF EXISTS `qlo_pulse_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_pulse_setting` (
  `id_pulse_setting` int unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `value` text,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_pulse_setting`),
  UNIQUE KEY `mod_name` (`module`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_pulse_setting`
--

LOCK TABLES `qlo_pulse_setting` WRITE;
/*!40000 ALTER TABLE `qlo_pulse_setting` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_pulse_setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qcmc_channel_manager_booking`
--

DROP TABLE IF EXISTS `qlo_qcmc_channel_manager_booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qcmc_channel_manager_booking` (
  `id_channel_manager_booking` int unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_channel_manager_booking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qcmc_channel_manager_booking`
--

LOCK TABLES `qlo_qcmc_channel_manager_booking` WRITE;
/*!40000 ALTER TABLE `qlo_qcmc_channel_manager_booking` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_qcmc_channel_manager_booking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_category`
--

DROP TABLE IF EXISTS `qlo_qhr_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_category` (
  `id_category` int NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) DEFAULT '1',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_category`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_category`
--

LOCK TABLES `qlo_qhr_category` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_category` DISABLE KEYS */;
INSERT INTO `qlo_qhr_category` VALUES (1,1,'2026-09-03 18:50:19','2026-09-03 18:50:19'),(2,1,'2026-09-03 18:50:19','2026-09-03 18:50:19');
/*!40000 ALTER TABLE `qlo_qhr_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_category_lang`
--

DROP TABLE IF EXISTS `qlo_qhr_category_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_category_lang` (
  `id_category` int NOT NULL,
  `id_lang` int NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_category`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_category_lang`
--

LOCK TABLES `qlo_qhr_category_lang` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_category_lang` DISABLE KEYS */;
INSERT INTO `qlo_qhr_category_lang` VALUES (1,1,'Food'),(2,1,'Room Service');
/*!40000 ALTER TABLE `qlo_qhr_category_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_hotel_review`
--

DROP TABLE IF EXISTS `qlo_qhr_hotel_review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_hotel_review` (
  `id_hotel_review` int NOT NULL AUTO_INCREMENT,
  `id_hotel` int NOT NULL,
  `id_order` int NOT NULL,
  `rating` float unsigned NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status_abusive` tinyint(1) DEFAULT '0',
  `status` tinyint(1) DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_hotel_review`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_hotel_review`
--

LOCK TABLES `qlo_qhr_hotel_review` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_hotel_review` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_qhr_hotel_review` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_review_category_rating`
--

DROP TABLE IF EXISTS `qlo_qhr_review_category_rating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_review_category_rating` (
  `id_hotel_review` int NOT NULL,
  `id_category` int NOT NULL,
  `rating` float unsigned NOT NULL,
  PRIMARY KEY (`id_hotel_review`,`id_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_review_category_rating`
--

LOCK TABLES `qlo_qhr_review_category_rating` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_review_category_rating` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_qhr_review_category_rating` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_review_reply`
--

DROP TABLE IF EXISTS `qlo_qhr_review_reply`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_review_reply` (
  `id_review_reply` int NOT NULL AUTO_INCREMENT,
  `id_hotel_review` int NOT NULL,
  `id_employee` int NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_review_reply`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_review_reply`
--

LOCK TABLES `qlo_qhr_review_reply` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_review_reply` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_qhr_review_reply` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_review_report`
--

DROP TABLE IF EXISTS `qlo_qhr_review_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_review_report` (
  `id_hotel_review` int NOT NULL,
  `id_customer` int NOT NULL,
  PRIMARY KEY (`id_hotel_review`,`id_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_review_report`
--

LOCK TABLES `qlo_qhr_review_report` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_review_report` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_qhr_review_report` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_qhr_review_usefulness`
--

DROP TABLE IF EXISTS `qlo_qhr_review_usefulness`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_qhr_review_usefulness` (
  `id_hotel_review` int NOT NULL,
  `id_customer` int NOT NULL,
  PRIMARY KEY (`id_hotel_review`,`id_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_qhr_review_usefulness`
--

LOCK TABLES `qlo_qhr_review_usefulness` WRITE;
/*!40000 ALTER TABLE `qlo_qhr_review_usefulness` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_qhr_review_usefulness` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_quick_access`
--

DROP TABLE IF EXISTS `qlo_quick_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_quick_access` (
  `id_quick_access` int unsigned NOT NULL AUTO_INCREMENT,
  `new_window` tinyint(1) NOT NULL DEFAULT '0',
  `link` varchar(255) NOT NULL,
  PRIMARY KEY (`id_quick_access`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_quick_access`
--

LOCK TABLES `qlo_quick_access` WRITE;
/*!40000 ALTER TABLE `qlo_quick_access` DISABLE KEYS */;
INSERT INTO `qlo_quick_access` VALUES (1,0,'index.php?controller=AdminProducts&addproduct'),(2,0,'index.php?controller=AdminCartRules&addcart_rule');
/*!40000 ALTER TABLE `qlo_quick_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_quick_access_lang`
--

DROP TABLE IF EXISTS `qlo_quick_access_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_quick_access_lang` (
  `id_quick_access` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id_quick_access`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_quick_access_lang`
--

LOCK TABLES `qlo_quick_access_lang` WRITE;
/*!40000 ALTER TABLE `qlo_quick_access_lang` DISABLE KEYS */;
INSERT INTO `qlo_quick_access_lang` VALUES (1,1,'New room type'),(2,1,'New voucher');
/*!40000 ALTER TABLE `qlo_quick_access_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_range_price`
--

DROP TABLE IF EXISTS `qlo_range_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_range_price` (
  `id_range_price` int unsigned NOT NULL AUTO_INCREMENT,
  `id_carrier` int unsigned NOT NULL,
  `delimiter1` decimal(20,6) NOT NULL,
  `delimiter2` decimal(20,6) NOT NULL,
  PRIMARY KEY (`id_range_price`),
  UNIQUE KEY `id_carrier` (`id_carrier`,`delimiter1`,`delimiter2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_range_price`
--

LOCK TABLES `qlo_range_price` WRITE;
/*!40000 ALTER TABLE `qlo_range_price` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_range_price` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_range_weight`
--

DROP TABLE IF EXISTS `qlo_range_weight`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_range_weight` (
  `id_range_weight` int unsigned NOT NULL AUTO_INCREMENT,
  `id_carrier` int unsigned NOT NULL,
  `delimiter1` decimal(20,6) NOT NULL,
  `delimiter2` decimal(20,6) NOT NULL,
  PRIMARY KEY (`id_range_weight`),
  UNIQUE KEY `id_carrier` (`id_carrier`,`delimiter1`,`delimiter2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_range_weight`
--

LOCK TABLES `qlo_range_weight` WRITE;
/*!40000 ALTER TABLE `qlo_range_weight` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_range_weight` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_referrer`
--

DROP TABLE IF EXISTS `qlo_referrer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_referrer` (
  `id_referrer` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `passwd` varchar(60) DEFAULT NULL,
  `http_referer_regexp` varchar(64) DEFAULT NULL,
  `http_referer_like` varchar(64) DEFAULT NULL,
  `request_uri_regexp` varchar(64) DEFAULT NULL,
  `request_uri_like` varchar(64) DEFAULT NULL,
  `http_referer_regexp_not` varchar(64) DEFAULT NULL,
  `http_referer_like_not` varchar(64) DEFAULT NULL,
  `request_uri_regexp_not` varchar(64) DEFAULT NULL,
  `request_uri_like_not` varchar(64) DEFAULT NULL,
  `base_fee` decimal(5,2) NOT NULL DEFAULT '0.00',
  `percent_fee` decimal(5,2) NOT NULL DEFAULT '0.00',
  `click_fee` decimal(5,2) NOT NULL DEFAULT '0.00',
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_referrer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_referrer`
--

LOCK TABLES `qlo_referrer` WRITE;
/*!40000 ALTER TABLE `qlo_referrer` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_referrer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_referrer_cache`
--

DROP TABLE IF EXISTS `qlo_referrer_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_referrer_cache` (
  `id_connections_source` int unsigned NOT NULL,
  `id_referrer` int unsigned NOT NULL,
  PRIMARY KEY (`id_connections_source`,`id_referrer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_referrer_cache`
--

LOCK TABLES `qlo_referrer_cache` WRITE;
/*!40000 ALTER TABLE `qlo_referrer_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_referrer_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_referrer_shop`
--

DROP TABLE IF EXISTS `qlo_referrer_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_referrer_shop` (
  `id_referrer` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `cache_visitors` int DEFAULT NULL,
  `cache_visits` int DEFAULT NULL,
  `cache_pages` int DEFAULT NULL,
  `cache_registrations` int DEFAULT NULL,
  `cache_orders` int DEFAULT NULL,
  `cache_sales` decimal(17,2) DEFAULT NULL,
  `cache_reg_rate` decimal(5,4) DEFAULT NULL,
  `cache_order_rate` decimal(5,4) DEFAULT NULL,
  PRIMARY KEY (`id_referrer`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_referrer_shop`
--

LOCK TABLES `qlo_referrer_shop` WRITE;
/*!40000 ALTER TABLE `qlo_referrer_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_referrer_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_request_sql`
--

DROP TABLE IF EXISTS `qlo_request_sql`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_request_sql` (
  `id_request_sql` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `sql` text NOT NULL,
  PRIMARY KEY (`id_request_sql`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_request_sql`
--

LOCK TABLES `qlo_request_sql` WRITE;
/*!40000 ALTER TABLE `qlo_request_sql` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_request_sql` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_required_field`
--

DROP TABLE IF EXISTS `qlo_required_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_required_field` (
  `id_required_field` int NOT NULL AUTO_INCREMENT,
  `object_name` varchar(32) NOT NULL,
  `field_name` varchar(32) NOT NULL,
  PRIMARY KEY (`id_required_field`),
  KEY `object_name` (`object_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_required_field`
--

LOCK TABLES `qlo_required_field` WRITE;
/*!40000 ALTER TABLE `qlo_required_field` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_required_field` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_risk`
--

DROP TABLE IF EXISTS `qlo_risk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_risk` (
  `id_risk` int unsigned NOT NULL AUTO_INCREMENT,
  `percent` tinyint NOT NULL,
  `color` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id_risk`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_risk`
--

LOCK TABLES `qlo_risk` WRITE;
/*!40000 ALTER TABLE `qlo_risk` DISABLE KEYS */;
INSERT INTO `qlo_risk` VALUES (1,0,'#32CD32'),(2,35,'#FF8C00'),(3,75,'#DC143C'),(4,100,'#ec2e15');
/*!40000 ALTER TABLE `qlo_risk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_risk_lang`
--

DROP TABLE IF EXISTS `qlo_risk_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_risk_lang` (
  `id_risk` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id_risk`,`id_lang`),
  KEY `id_risk` (`id_risk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_risk_lang`
--

LOCK TABLES `qlo_risk_lang` WRITE;
/*!40000 ALTER TABLE `qlo_risk_lang` DISABLE KEYS */;
INSERT INTO `qlo_risk_lang` VALUES (1,1,'None'),(2,1,'Low'),(3,1,'Medium'),(4,1,'High');
/*!40000 ALTER TABLE `qlo_risk_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_scene`
--

DROP TABLE IF EXISTS `qlo_scene`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_scene` (
  `id_scene` int unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_scene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_scene`
--

LOCK TABLES `qlo_scene` WRITE;
/*!40000 ALTER TABLE `qlo_scene` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_scene` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_scene_category`
--

DROP TABLE IF EXISTS `qlo_scene_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_scene_category` (
  `id_scene` int unsigned NOT NULL,
  `id_category` int unsigned NOT NULL,
  PRIMARY KEY (`id_scene`,`id_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_scene_category`
--

LOCK TABLES `qlo_scene_category` WRITE;
/*!40000 ALTER TABLE `qlo_scene_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_scene_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_scene_lang`
--

DROP TABLE IF EXISTS `qlo_scene_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_scene_lang` (
  `id_scene` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id_scene`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_scene_lang`
--

LOCK TABLES `qlo_scene_lang` WRITE;
/*!40000 ALTER TABLE `qlo_scene_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_scene_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_scene_products`
--

DROP TABLE IF EXISTS `qlo_scene_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_scene_products` (
  `id_scene` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `x_axis` int NOT NULL,
  `y_axis` int NOT NULL,
  `zone_width` int NOT NULL,
  `zone_height` int NOT NULL,
  PRIMARY KEY (`id_scene`,`id_product`,`x_axis`,`y_axis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_scene_products`
--

LOCK TABLES `qlo_scene_products` WRITE;
/*!40000 ALTER TABLE `qlo_scene_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_scene_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_scene_shop`
--

DROP TABLE IF EXISTS `qlo_scene_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_scene_shop` (
  `id_scene` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_scene`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_scene_shop`
--

LOCK TABLES `qlo_scene_shop` WRITE;
/*!40000 ALTER TABLE `qlo_scene_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_scene_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_search_engine`
--

DROP TABLE IF EXISTS `qlo_search_engine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_search_engine` (
  `id_search_engine` int unsigned NOT NULL AUTO_INCREMENT,
  `server` varchar(64) NOT NULL,
  `getvar` varchar(16) NOT NULL,
  PRIMARY KEY (`id_search_engine`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_search_engine`
--

LOCK TABLES `qlo_search_engine` WRITE;
/*!40000 ALTER TABLE `qlo_search_engine` DISABLE KEYS */;
INSERT INTO `qlo_search_engine` VALUES (1,'google','q'),(2,'aol','q'),(3,'yandex','text'),(4,'ask.com','q'),(5,'nhl.com','q'),(6,'yahoo','p'),(7,'baidu','wd'),(8,'lycos','query'),(9,'exalead','q'),(10,'search.live','q'),(11,'voila','rdata'),(12,'altavista','q'),(13,'bing','q'),(14,'daum','q'),(15,'eniro','search_word'),(16,'naver','query'),(17,'msn','q'),(18,'netscape','query'),(19,'cnn','query'),(20,'about','terms'),(21,'mamma','query'),(22,'alltheweb','q'),(23,'virgilio','qs'),(24,'alice','qs'),(25,'najdi','q'),(26,'mama','query'),(27,'seznam','q'),(28,'onet','qt'),(29,'szukacz','q'),(30,'yam','k'),(31,'pchome','q'),(32,'kvasir','q'),(33,'sesam','q'),(34,'ozu','q'),(35,'terra','query'),(36,'mynet','q'),(37,'ekolay','q'),(38,'rambler','words');
/*!40000 ALTER TABLE `qlo_search_engine` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_search_index`
--

DROP TABLE IF EXISTS `qlo_search_index`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_search_index` (
  `id_product` int unsigned NOT NULL,
  `id_word` int unsigned NOT NULL,
  `weight` smallint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_word`,`id_product`),
  KEY `id_product` (`id_product`,`weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_search_index`
--

LOCK TABLES `qlo_search_index` WRITE;
/*!40000 ALTER TABLE `qlo_search_index` DISABLE KEYS */;
INSERT INTO `qlo_search_index` VALUES (1,3,1),(1,11,1),(1,18,1),(1,19,1),(1,20,1),(1,22,1),(1,23,1),(1,24,1),(1,25,1),(1,26,1),(1,27,1),(1,28,1),(1,29,1),(1,32,1),(1,33,1),(1,34,1),(1,35,1),(1,36,1),(1,37,1),(1,38,1),(1,40,1),(1,41,1),(1,42,1),(1,43,1),(1,44,1),(1,45,1),(1,46,1),(1,47,1),(1,48,1),(1,49,1),(1,50,1),(1,51,1),(1,52,1),(1,53,1),(1,54,1),(1,55,1),(1,56,1),(1,57,1),(1,4,2),(1,5,2),(1,6,2),(1,7,2),(1,8,2),(1,9,2),(1,10,2),(1,12,2),(1,13,2),(1,14,2),(1,15,2),(1,16,2),(1,17,2),(1,30,2),(1,31,2),(1,39,2),(1,21,4),(1,1,8),(1,2,8),(2,11,1),(2,12,1),(2,13,1),(2,15,1),(2,28,1),(2,44,1),(2,49,1),(2,59,1),(2,66,1),(2,72,1),(2,73,1),(2,74,1),(2,75,1),(2,76,1),(2,77,1),(2,78,1),(2,79,1),(2,80,1),(2,81,1),(2,82,1),(2,83,1),(2,84,1),(2,85,1),(2,86,1),(2,87,1),(2,88,1),(2,89,1),(2,90,1),(2,91,1),(2,92,1),(2,10,2),(2,17,2),(2,22,2),(2,26,2),(2,60,2),(2,61,2),(2,62,2),(2,63,2),(2,64,2),(2,65,2),(2,67,2),(2,68,2),(2,69,2),(2,70,2),(2,71,2),(2,5,3),(2,21,3),(2,58,6),(2,2,7),(3,4,1),(3,5,1),(3,11,1),(3,15,1),(3,49,1),(3,55,1),(3,61,1),(3,66,1),(3,81,1),(3,108,1),(3,109,1),(3,121,1),(3,122,1),(3,123,1),(3,124,1),(3,125,1),(3,126,1),(3,127,1),(3,128,1),(3,129,1),(3,130,1),(3,131,1),(3,132,1),(3,133,1),(3,134,1),(3,135,1),(3,136,1),(3,137,1),(3,138,1),(3,139,1),(3,140,1),(3,9,2),(3,26,2),(3,44,2),(3,50,2),(3,110,2),(3,111,2),(3,112,2),(3,113,2),(3,114,2),(3,115,2),(3,116,2),(3,117,2),(3,118,2),(3,120,2),(3,21,3),(3,119,3),(3,2,7),(3,107,9),(4,5,1),(4,26,1),(4,29,1),(4,38,1),(4,55,1),(4,65,1),(4,80,1),(4,87,1),(4,89,1),(4,91,1),(4,108,1),(4,109,1),(4,113,1),(4,137,1),(4,139,1),(4,161,1),(4,162,1),(4,163,1),(4,164,1),(4,165,1),(4,166,1),(4,167,1),(4,168,1),(4,169,1),(4,170,1),(4,171,1),(4,172,1),(4,173,1),(4,174,1),(4,17,2),(4,44,2),(4,63,2),(4,64,2),(4,79,2),(4,90,2),(4,118,2),(4,119,2),(4,122,2),(4,156,2),(4,157,2),(4,158,2),(4,159,2),(4,160,2),(4,21,3),(4,60,3),(4,75,3),(4,2,7),(4,69,8),(5,28,2),(5,48,2),(5,49,2),(5,168,2),(5,206,2),(5,207,2),(5,208,2),(5,209,2),(5,210,2),(5,211,2),(5,212,2),(5,213,3),(5,214,3),(5,205,6),(5,44,8),(5,204,8),(6,30,2),(6,48,2),(6,72,2),(6,117,2),(6,222,2),(6,223,2),(6,224,2),(6,225,2),(6,226,2),(6,227,2),(6,228,2),(6,229,2),(6,230,2),(6,231,2),(6,232,2),(6,213,3),(6,220,6),(6,221,8),(6,214,9),(7,5,2),(7,27,2),(7,28,2),(7,72,2),(7,207,2),(7,241,2),(7,242,2),(7,243,2),(7,244,2),(7,245,2),(7,246,3),(7,239,8),(7,240,8),(8,207,2),(8,254,2),(8,255,2),(8,256,2),(8,257,2),(8,258,2),(8,259,2),(8,260,2),(8,261,2),(8,262,2),(8,263,2),(8,246,3),(8,252,8),(8,253,8),(9,20,2),(9,267,2),(9,268,2),(9,269,2),(9,270,2),(9,271,2),(9,272,2),(9,273,2),(9,274,2),(9,275,2),(9,276,2),(9,277,2),(9,278,3),(9,266,8),(10,232,2),(10,268,2),(10,281,2),(10,282,2),(10,283,2),(10,284,2),(10,285,2),(10,286,2),(10,287,2),(10,288,2),(10,289,2),(10,290,2),(10,291,2),(10,278,3),(10,280,8);
/*!40000 ALTER TABLE `qlo_search_index` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_search_word`
--

DROP TABLE IF EXISTS `qlo_search_word`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_search_word` (
  `id_word` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_lang` int unsigned NOT NULL,
  `word` varchar(15) NOT NULL,
  PRIMARY KEY (`id_word`),
  UNIQUE KEY `id_lang` (`id_lang`,`id_shop`,`word`)
) ENGINE=InnoDB AUTO_INCREMENT=295 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_search_word`
--

LOCK TABLES `qlo_search_word` WRITE;
/*!40000 ALTER TABLE `qlo_search_word` DISABLE KEYS */;
INSERT INTO `qlo_search_word` VALUES (117,1,1,'access'),(28,1,1,'accommodation'),(126,1,1,'adorned'),(275,1,1,'adventures'),(239,1,1,'airport'),(160,1,1,'alike'),(40,1,1,'allows'),(173,1,1,'ambiance'),(71,1,1,'amenities'),(171,1,1,'appointed'),(10,1,1,'area'),(112,1,1,'areas'),(262,1,1,'attractions'),(114,1,1,'bathroom'),(64,1,1,'bed'),(7,1,1,'bedrooms'),(34,1,1,'bonding'),(266,1,1,'breakfast'),(91,1,1,'breathtaking'),(230,1,1,'browsing'),(289,1,1,'buds'),(118,1,1,'business'),(252,1,1,'cab'),(169,1,1,'catching'),(214,1,1,'charges'),(242,1,1,'check'),(255,1,1,'city'),(5,1,1,'comfort'),(168,1,1,'comfortable'),(167,1,1,'complemented'),(130,1,1,'complete'),(290,1,1,'completes'),(15,1,1,'complimentary'),(47,1,1,'connected'),(27,1,1,'convenience'),(256,1,1,'conveniently'),(8,1,1,'cozy'),(163,1,1,'create'),(51,1,1,'creating'),(286,1,1,'culinary'),(268,1,1,'day'),(79,1,1,'decor'),(283,1,1,'delectable'),(270,1,1,'delicious'),(287,1,1,'delights'),(58,1,1,'delux'),(61,1,1,'deluxe'),(253,1,1,'demand'),(123,1,1,'designed'),(85,1,1,'detail'),(280,1,1,'dinner'),(124,1,1,'discerning'),(260,1,1,'discover'),(245,1,1,'ease'),(243,1,1,'efficient'),(41,1,1,'effortless'),(224,1,1,'effortlessly'),(65,1,1,'elegant'),(121,1,1,'elevate'),(11,1,1,'enjoy'),(229,1,1,'enjoyable'),(82,1,1,'enjoying'),(206,1,1,'ensure'),(86,1,1,'ensures'),(30,1,1,'ensuring'),(46,1,1,'entertained'),(73,1,1,'epitome'),(39,1,1,'equipped'),(282,1,1,'evening'),(84,1,1,'every'),(31,1,1,'everyone'),(277,1,1,'exceptional'),(115,1,1,'exclusive'),(107,1,1,'executive'),(122,1,1,'expansive'),(72,1,1,'experience'),(254,1,1,'explore'),(138,1,1,'extra'),(24,1,1,'families'),(18,1,1,'family'),(125,1,1,'features'),(109,1,1,'featuring'),(205,1,1,'fees'),(12,1,1,'flat'),(211,1,1,'free'),(258,1,1,'freedom'),(274,1,1,'fuel'),(77,1,1,'furnished'),(66,1,1,'furnishings'),(35,1,1,'games'),(74,1,1,'gaze'),(1,1,1,'general'),(19,1,1,'getaway'),(257,1,1,'giving'),(25,1,1,'groups'),(68,1,1,'guests'),(221,1,1,'handling'),(210,1,1,'hassle'),(271,1,1,'hearty'),(227,1,1,'high'),(21,1,1,'home'),(57,1,1,'hospitality'),(50,1,1,'ideal'),(108,1,1,'indulge'),(88,1,1,'indulgent'),(220,1,1,'internet'),(232,1,1,'journey'),(208,1,1,'keeping'),(62,1,1,'king'),(16,1,1,'kitchenette'),(59,1,1,'lake'),(52,1,1,'lasting'),(159,1,1,'leisure'),(83,1,1,'like'),(9,1,1,'living'),(261,1,1,'local'),(116,1,1,'lounge'),(113,1,1,'luxurious'),(69,1,1,'luxury'),(204,1,1,'maintenance'),(20,1,1,'make'),(42,1,1,'meal'),(53,1,1,'memories'),(70,1,1,'modern'),(276,1,1,'mornings'),(36,1,1,'movie'),(6,1,1,'multiple'),(222,1,1,'navigate'),(37,1,1,'nights'),(164,1,1,'oasis'),(3,1,1,'offer'),(285,1,1,'offering'),(29,1,1,'offers'),(231,1,1,'online'),(213,1,1,'operational'),(263,1,1,'pace'),(129,1,1,'pamper'),(166,1,1,'panoramic'),(89,1,1,'peaceful'),(17,1,1,'perfect'),(78,1,1,'plush'),(133,1,1,'premium'),(43,1,1,'preparation'),(273,1,1,'prepared'),(209,1,1,'pristine'),(120,1,1,'privacy'),(157,1,1,'queen'),(33,1,1,'recharge'),(92,1,1,'refined'),(135,1,1,'refreshments'),(32,1,1,'relax'),(87,1,1,'relaxing'),(226,1,1,'reliable'),(278,1,1,'restaurant'),(90,1,1,'retreat'),(269,1,1,'right'),(44,1,1,'room'),(2,1,1,'rooms'),(291,1,1,'satisfaction'),(13,1,1,'screen'),(225,1,1,'seamless'),(26,1,1,'seeking'),(110,1,1,'separate'),(75,1,1,'serene'),(207,1,1,'service'),(55,1,1,'setting'),(240,1,1,'shuttle'),(172,1,1,'simply'),(67,1,1,'sitting'),(63,1,1,'sized'),(111,1,1,'sleeping'),(131,1,1,'soaking'),(127,1,1,'sophisticated'),(140,1,1,'sophistication'),(4,1,1,'space'),(22,1,1,'spacious'),(228,1,1,'speed'),(284,1,1,'spread'),(267,1,1,'start'),(49,1,1,'stay'),(45,1,1,'stays'),(174,1,1,'style'),(137,1,1,'surroundings'),(23,1,1,'tailored'),(288,1,1,'taste'),(272,1,1,'thoughtfully'),(48,1,1,'throughout'),(54,1,1,'together'),(134,1,1,'toiletries'),(241,1,1,'touchdown'),(128,1,1,'touches'),(136,1,1,'tranquil'),(156,1,1,'tranquility'),(246,1,1,'transfers'),(259,1,1,'travel'),(119,1,1,'travelers'),(132,1,1,'tub'),(14,1,1,'tvs'),(161,1,1,'ultimate'),(139,1,1,'unparalleled'),(81,1,1,'unwinding'),(60,1,1,'views'),(212,1,1,'visit'),(162,1,1,'vistas'),(165,1,1,'wake'),(56,1,1,'warmth'),(76,1,1,'waters'),(223,1,1,'website'),(38,1,1,'well'),(80,1,1,'whether'),(244,1,1,'whisking'),(281,1,1,'wind'),(170,1,1,'work'),(158,1,1,'workspace');
/*!40000 ALTER TABLE `qlo_search_word` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_sekeyword`
--

DROP TABLE IF EXISTS `qlo_sekeyword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_sekeyword` (
  `id_sekeyword` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_shop_group` int unsigned NOT NULL DEFAULT '1',
  `keyword` varchar(256) NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_sekeyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_sekeyword`
--

LOCK TABLES `qlo_sekeyword` WRITE;
/*!40000 ALTER TABLE `qlo_sekeyword` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_sekeyword` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_service_product_cart_detail`
--

DROP TABLE IF EXISTS `qlo_service_product_cart_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_service_product_cart_detail` (
  `id_service_product_cart_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL,
  `id_cart` int unsigned NOT NULL,
  `id_hotel` int unsigned NOT NULL,
  `htl_cart_booking_id` int unsigned NOT NULL,
  `id_product_option` int unsigned NOT NULL,
  PRIMARY KEY (`id_service_product_cart_detail`),
  KEY `id_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_service_product_cart_detail`
--

LOCK TABLES `qlo_service_product_cart_detail` WRITE;
/*!40000 ALTER TABLE `qlo_service_product_cart_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_service_product_cart_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_service_product_order_detail`
--

DROP TABLE IF EXISTS `qlo_service_product_order_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_service_product_order_detail` (
  `id_service_product_order_detail` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `id_order` int NOT NULL,
  `id_order_detail` int NOT NULL,
  `id_cart` int NOT NULL,
  `id_hotel` int NOT NULL,
  `id_htl_booking_detail` int NOT NULL,
  `id_product_option` int NOT NULL,
  `tax_computation_method` tinyint unsigned NOT NULL DEFAULT '0',
  `id_tax_rules_group` int unsigned NOT NULL DEFAULT '0',
  `unit_price_tax_excl` decimal(20,6) NOT NULL,
  `unit_price_tax_incl` decimal(20,6) NOT NULL,
  `total_price_tax_excl` decimal(20,6) NOT NULL,
  `total_price_tax_incl` decimal(20,6) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `option_name` varchar(255) DEFAULT NULL,
  `hotel_name` varchar(255) DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `auto_added` tinyint unsigned NOT NULL,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_service_product_order_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_service_product_order_detail`
--

LOCK TABLES `qlo_service_product_order_detail` WRITE;
/*!40000 ALTER TABLE `qlo_service_product_order_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_service_product_order_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_shop`
--

DROP TABLE IF EXISTS `qlo_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_shop` (
  `id_shop` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop_group` int unsigned NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `id_category` int unsigned NOT NULL DEFAULT '1',
  `id_theme` int unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_shop`),
  KEY `id_shop_group` (`id_shop_group`,`deleted`),
  KEY `id_category` (`id_category`),
  KEY `id_theme` (`id_theme`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_shop`
--

LOCK TABLES `qlo_shop` WRITE;
/*!40000 ALTER TABLE `qlo_shop` DISABLE KEYS */;
INSERT INTO `qlo_shop` VALUES (1,1,'Hotel animate',2,1,1,0);
/*!40000 ALTER TABLE `qlo_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_shop_group`
--

DROP TABLE IF EXISTS `qlo_shop_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_shop_group` (
  `id_shop_group` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `share_customer` tinyint(1) NOT NULL,
  `share_order` tinyint(1) NOT NULL,
  `share_stock` tinyint(1) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_shop_group`),
  KEY `deleted` (`deleted`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_shop_group`
--

LOCK TABLES `qlo_shop_group` WRITE;
/*!40000 ALTER TABLE `qlo_shop_group` DISABLE KEYS */;
INSERT INTO `qlo_shop_group` VALUES (1,'Default',0,0,0,1,0);
/*!40000 ALTER TABLE `qlo_shop_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_shop_url`
--

DROP TABLE IF EXISTS `qlo_shop_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_shop_url` (
  `id_shop_url` int unsigned NOT NULL AUTO_INCREMENT,
  `id_shop` int unsigned NOT NULL,
  `domain` varchar(150) NOT NULL,
  `domain_ssl` varchar(150) NOT NULL,
  `physical_uri` varchar(256) NOT NULL,
  `virtual_uri` varchar(256) NOT NULL,
  `main` tinyint(1) NOT NULL,
  `active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_shop_url`),
  UNIQUE KEY `full_shop_url` (`domain`,`physical_uri`,`virtual_uri`),
  UNIQUE KEY `full_shop_url_ssl` (`domain_ssl`,`physical_uri`,`virtual_uri`),
  KEY `id_shop` (`id_shop`,`main`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_shop_url`
--

LOCK TABLES `qlo_shop_url` WRITE;
/*!40000 ALTER TABLE `qlo_shop_url` DISABLE KEYS */;
INSERT INTO `qlo_shop_url` VALUES (1,1,'localhost','localhost','/','',1,1);
/*!40000 ALTER TABLE `qlo_shop_url` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_smarty_cache`
--

DROP TABLE IF EXISTS `qlo_smarty_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_smarty_cache` (
  `id_smarty_cache` char(40) NOT NULL,
  `name` char(40) NOT NULL,
  `cache_id` varchar(254) DEFAULT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `content` longtext NOT NULL,
  PRIMARY KEY (`id_smarty_cache`),
  KEY `name` (`name`),
  KEY `cache_id` (`cache_id`),
  KEY `modified` (`modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_smarty_cache`
--

LOCK TABLES `qlo_smarty_cache` WRITE;
/*!40000 ALTER TABLE `qlo_smarty_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_smarty_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_smarty_last_flush`
--

DROP TABLE IF EXISTS `qlo_smarty_last_flush`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_smarty_last_flush` (
  `type` enum('compile','template') NOT NULL,
  `last_flush` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_smarty_last_flush`
--

LOCK TABLES `qlo_smarty_last_flush` WRITE;
/*!40000 ALTER TABLE `qlo_smarty_last_flush` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_smarty_last_flush` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_smarty_lazy_cache`
--

DROP TABLE IF EXISTS `qlo_smarty_lazy_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_smarty_lazy_cache` (
  `template_hash` varchar(32) NOT NULL DEFAULT '',
  `cache_id` varchar(255) NOT NULL DEFAULT '',
  `compile_id` varchar(32) NOT NULL DEFAULT '',
  `filepath` varchar(255) NOT NULL DEFAULT '',
  `last_update` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`template_hash`,`cache_id`,`compile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_smarty_lazy_cache`
--

LOCK TABLES `qlo_smarty_lazy_cache` WRITE;
/*!40000 ALTER TABLE `qlo_smarty_lazy_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_smarty_lazy_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_specific_price`
--

DROP TABLE IF EXISTS `qlo_specific_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_specific_price` (
  `id_specific_price` int unsigned NOT NULL AUTO_INCREMENT,
  `id_specific_price_rule` int unsigned NOT NULL,
  `id_cart` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_shop_group` int unsigned NOT NULL,
  `id_currency` int unsigned NOT NULL,
  `id_country` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  `id_customer` int unsigned NOT NULL,
  `id_htl_cart_booking` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  `price` decimal(20,6) NOT NULL,
  `from_quantity` mediumint unsigned NOT NULL,
  `reduction` decimal(20,6) NOT NULL,
  `reduction_tax` tinyint(1) NOT NULL DEFAULT '1',
  `reduction_type` enum('amount','percentage') NOT NULL,
  `from` datetime NOT NULL,
  `to` datetime NOT NULL,
  PRIMARY KEY (`id_specific_price`),
  UNIQUE KEY `id_product_2` (`id_product`,`id_product_attribute`,`id_customer`,`id_cart`,`from`,`to`,`id_shop`,`id_shop_group`,`id_currency`,`id_country`,`id_group`,`from_quantity`,`id_specific_price_rule`,`id_htl_cart_booking`),
  KEY `id_product` (`id_product`,`id_shop`,`id_currency`,`id_country`,`id_group`,`id_customer`,`from_quantity`,`from`,`to`),
  KEY `from_quantity` (`from_quantity`),
  KEY `id_specific_price_rule` (`id_specific_price_rule`),
  KEY `id_cart` (`id_cart`),
  KEY `id_product_attribute` (`id_product_attribute`),
  KEY `id_shop` (`id_shop`),
  KEY `id_customer` (`id_customer`),
  KEY `from` (`from`),
  KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_specific_price`
--

LOCK TABLES `qlo_specific_price` WRITE;
/*!40000 ALTER TABLE `qlo_specific_price` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_specific_price` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_specific_price_priority`
--

DROP TABLE IF EXISTS `qlo_specific_price_priority`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_specific_price_priority` (
  `id_specific_price_priority` int NOT NULL AUTO_INCREMENT,
  `id_product` int NOT NULL,
  `priority` varchar(80) NOT NULL,
  PRIMARY KEY (`id_specific_price_priority`,`id_product`),
  UNIQUE KEY `id_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_specific_price_priority`
--

LOCK TABLES `qlo_specific_price_priority` WRITE;
/*!40000 ALTER TABLE `qlo_specific_price_priority` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_specific_price_priority` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_specific_price_rule`
--

DROP TABLE IF EXISTS `qlo_specific_price_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_specific_price_rule` (
  `id_specific_price_rule` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `id_shop` int unsigned NOT NULL DEFAULT '1',
  `id_currency` int unsigned NOT NULL,
  `id_country` int unsigned NOT NULL,
  `id_group` int unsigned NOT NULL,
  `from_quantity` mediumint unsigned NOT NULL,
  `price` decimal(20,6) DEFAULT NULL,
  `reduction` decimal(20,6) NOT NULL,
  `reduction_tax` tinyint(1) NOT NULL DEFAULT '1',
  `reduction_type` enum('amount','percentage') NOT NULL,
  `from` datetime NOT NULL,
  `to` datetime NOT NULL,
  PRIMARY KEY (`id_specific_price_rule`),
  KEY `id_product` (`id_shop`,`id_currency`,`id_country`,`id_group`,`from_quantity`,`from`,`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_specific_price_rule`
--

LOCK TABLES `qlo_specific_price_rule` WRITE;
/*!40000 ALTER TABLE `qlo_specific_price_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_specific_price_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_specific_price_rule_condition`
--

DROP TABLE IF EXISTS `qlo_specific_price_rule_condition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_specific_price_rule_condition` (
  `id_specific_price_rule_condition` int unsigned NOT NULL AUTO_INCREMENT,
  `id_specific_price_rule_condition_group` int unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id_specific_price_rule_condition`),
  KEY `id_specific_price_rule_condition_group` (`id_specific_price_rule_condition_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_specific_price_rule_condition`
--

LOCK TABLES `qlo_specific_price_rule_condition` WRITE;
/*!40000 ALTER TABLE `qlo_specific_price_rule_condition` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_specific_price_rule_condition` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_specific_price_rule_condition_group`
--

DROP TABLE IF EXISTS `qlo_specific_price_rule_condition_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_specific_price_rule_condition_group` (
  `id_specific_price_rule_condition_group` int unsigned NOT NULL AUTO_INCREMENT,
  `id_specific_price_rule` int unsigned NOT NULL,
  PRIMARY KEY (`id_specific_price_rule_condition_group`,`id_specific_price_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_specific_price_rule_condition_group`
--

LOCK TABLES `qlo_specific_price_rule_condition_group` WRITE;
/*!40000 ALTER TABLE `qlo_specific_price_rule_condition_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_specific_price_rule_condition_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_state`
--

DROP TABLE IF EXISTS `qlo_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_state` (
  `id_state` int unsigned NOT NULL AUTO_INCREMENT,
  `id_country` int unsigned NOT NULL,
  `id_zone` int unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `iso_code` varchar(7) NOT NULL,
  `tax_behavior` smallint NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_state`),
  KEY `id_country` (`id_country`),
  KEY `name` (`name`),
  KEY `id_zone` (`id_zone`)
) ENGINE=InnoDB AUTO_INCREMENT=313 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_state`
--

LOCK TABLES `qlo_state` WRITE;
/*!40000 ALTER TABLE `qlo_state` DISABLE KEYS */;
INSERT INTO `qlo_state` VALUES (1,21,2,'Alabama','AL',0,1),(2,21,2,'Alaska','AK',0,1),(3,21,2,'Arizona','AZ',0,1),(4,21,2,'Arkansas','AR',0,1),(5,21,2,'California','CA',0,1),(6,21,2,'Colorado','CO',0,1),(7,21,2,'Connecticut','CT',0,1),(8,21,2,'Delaware','DE',0,1),(9,21,2,'Florida','FL',0,1),(10,21,2,'Georgia','GA',0,1),(11,21,2,'Hawaii','HI',0,1),(12,21,2,'Idaho','ID',0,1),(13,21,2,'Illinois','IL',0,1),(14,21,2,'Indiana','IN',0,1),(15,21,2,'Iowa','IA',0,1),(16,21,2,'Kansas','KS',0,1),(17,21,2,'Kentucky','KY',0,1),(18,21,2,'Louisiana','LA',0,1),(19,21,2,'Maine','ME',0,1),(20,21,2,'Maryland','MD',0,1),(21,21,2,'Massachusetts','MA',0,1),(22,21,2,'Michigan','MI',0,1),(23,21,2,'Minnesota','MN',0,1),(24,21,2,'Mississippi','MS',0,1),(25,21,2,'Missouri','MO',0,1),(26,21,2,'Montana','MT',0,1),(27,21,2,'Nebraska','NE',0,1),(28,21,2,'Nevada','NV',0,1),(29,21,2,'New Hampshire','NH',0,1),(30,21,2,'New Jersey','NJ',0,1),(31,21,2,'New Mexico','NM',0,1),(32,21,2,'New York','NY',0,1),(33,21,2,'North Carolina','NC',0,1),(34,21,2,'North Dakota','ND',0,1),(35,21,2,'Ohio','OH',0,1),(36,21,2,'Oklahoma','OK',0,1),(37,21,2,'Oregon','OR',0,1),(38,21,2,'Pennsylvania','PA',0,1),(39,21,2,'Rhode Island','RI',0,1),(40,21,2,'South Carolina','SC',0,1),(41,21,2,'South Dakota','SD',0,1),(42,21,2,'Tennessee','TN',0,1),(43,21,2,'Texas','TX',0,1),(44,21,2,'Utah','UT',0,1),(45,21,2,'Vermont','VT',0,1),(46,21,2,'Virginia','VA',0,1),(47,21,2,'Washington','WA',0,1),(48,21,2,'West Virginia','WV',0,1),(49,21,2,'Wisconsin','WI',0,1),(50,21,2,'Wyoming','WY',0,1),(51,21,2,'Puerto Rico','PR',0,1),(52,21,2,'US Virgin Islands','VI',0,1),(53,21,2,'District of Columbia','DC',0,1),(54,145,2,'Aguascalientes','AGS',0,1),(55,145,2,'Baja California','BCN',0,1),(56,145,2,'Baja California Sur','BCS',0,1),(57,145,2,'Campeche','CAM',0,1),(58,145,2,'Chiapas','CHP',0,1),(59,145,2,'Chihuahua','CHH',0,1),(60,145,2,'Coahuila','COA',0,1),(61,145,2,'Colima','COL',0,1),(62,145,2,'Distrito Federal','DIF',0,1),(63,145,2,'Durango','DUR',0,1),(64,145,2,'Guanajuato','GUA',0,1),(65,145,2,'Guerrero','GRO',0,1),(66,145,2,'Hidalgo','HID',0,1),(67,145,2,'Jalisco','JAL',0,1),(68,145,2,'Estado de México','MEX',0,1),(69,145,2,'Michoacán','MIC',0,1),(70,145,2,'Morelos','MOR',0,1),(71,145,2,'Nayarit','NAY',0,1),(72,145,2,'Nuevo León','NLE',0,1),(73,145,2,'Oaxaca','OAX',0,1),(74,145,2,'Puebla','PUE',0,1),(75,145,2,'Querétaro','QUE',0,1),(76,145,2,'Quintana Roo','ROO',0,1),(77,145,2,'San Luis Potosí','SLP',0,1),(78,145,2,'Sinaloa','SIN',0,1),(79,145,2,'Sonora','SON',0,1),(80,145,2,'Tabasco','TAB',0,1),(81,145,2,'Tamaulipas','TAM',0,1),(82,145,2,'Tlaxcala','TLA',0,1),(83,145,2,'Veracruz','VER',0,1),(84,145,2,'Yucatán','YUC',0,1),(85,145,2,'Zacatecas','ZAC',0,1),(86,4,2,'Ontario','ON',0,1),(87,4,2,'Quebec','QC',0,1),(88,4,2,'British Columbia','BC',0,1),(89,4,2,'Alberta','AB',0,1),(90,4,2,'Manitoba','MB',0,1),(91,4,2,'Saskatchewan','SK',0,1),(92,4,2,'Nova Scotia','NS',0,1),(93,4,2,'New Brunswick','NB',0,1),(94,4,2,'Newfoundland and Labrador','NL',0,1),(95,4,2,'Prince Edward Island','PE',0,1),(96,4,2,'Northwest Territories','NT',0,1),(97,4,2,'Yukon','YT',0,1),(98,4,2,'Nunavut','NU',0,1),(99,44,6,'Buenos Aires','B',0,1),(100,44,6,'Catamarca','K',0,1),(101,44,6,'Chaco','H',0,1),(102,44,6,'Chubut','U',0,1),(103,44,6,'Ciudad de Buenos Aires','C',0,1),(104,44,6,'Córdoba','X',0,1),(105,44,6,'Corrientes','W',0,1),(106,44,6,'Entre Ríos','E',0,1),(107,44,6,'Formosa','P',0,1),(108,44,6,'Jujuy','Y',0,1),(109,44,6,'La Pampa','L',0,1),(110,44,6,'La Rioja','F',0,1),(111,44,6,'Mendoza','M',0,1),(112,44,6,'Misiones','N',0,1),(113,44,6,'Neuquén','Q',0,1),(114,44,6,'Río Negro','R',0,1),(115,44,6,'Salta','A',0,1),(116,44,6,'San Juan','J',0,1),(117,44,6,'San Luis','D',0,1),(118,44,6,'Santa Cruz','Z',0,1),(119,44,6,'Santa Fe','S',0,1),(120,44,6,'Santiago del Estero','G',0,1),(121,44,6,'Tierra del Fuego','V',0,1),(122,44,6,'Tucumán','T',0,1),(123,10,1,'Agrigento','AG',0,1),(124,10,1,'Alessandria','AL',0,1),(125,10,1,'Ancona','AN',0,1),(126,10,1,'Aosta','AO',0,1),(127,10,1,'Arezzo','AR',0,1),(128,10,1,'Ascoli Piceno','AP',0,1),(129,10,1,'Asti','AT',0,1),(130,10,1,'Avellino','AV',0,1),(131,10,1,'Bari','BA',0,1),(132,10,1,'Barletta-Andria-Trani','BT',0,1),(133,10,1,'Belluno','BL',0,1),(134,10,1,'Benevento','BN',0,1),(135,10,1,'Bergamo','BG',0,1),(136,10,1,'Biella','BI',0,1),(137,10,1,'Bologna','BO',0,1),(138,10,1,'Bolzano','BZ',0,1),(139,10,1,'Brescia','BS',0,1),(140,10,1,'Brindisi','BR',0,1),(141,10,1,'Cagliari','CA',0,1),(142,10,1,'Caltanissetta','CL',0,1),(143,10,1,'Campobasso','CB',0,1),(144,10,1,'Carbonia-Iglesias','CI',0,1),(145,10,1,'Caserta','CE',0,1),(146,10,1,'Catania','CT',0,1),(147,10,1,'Catanzaro','CZ',0,1),(148,10,1,'Chieti','CH',0,1),(149,10,1,'Como','CO',0,1),(150,10,1,'Cosenza','CS',0,1),(151,10,1,'Cremona','CR',0,1),(152,10,1,'Crotone','KR',0,1),(153,10,1,'Cuneo','CN',0,1),(154,10,1,'Enna','EN',0,1),(155,10,1,'Fermo','FM',0,1),(156,10,1,'Ferrara','FE',0,1),(157,10,1,'Firenze','FI',0,1),(158,10,1,'Foggia','FG',0,1),(159,10,1,'Forlì-Cesena','FC',0,1),(160,10,1,'Frosinone','FR',0,1),(161,10,1,'Genova','GE',0,1),(162,10,1,'Gorizia','GO',0,1),(163,10,1,'Grosseto','GR',0,1),(164,10,1,'Imperia','IM',0,1),(165,10,1,'Isernia','IS',0,1),(166,10,1,'L\'Aquila','AQ',0,1),(167,10,1,'La Spezia','SP',0,1),(168,10,1,'Latina','LT',0,1),(169,10,1,'Lecce','LE',0,1),(170,10,1,'Lecco','LC',0,1),(171,10,1,'Livorno','LI',0,1),(172,10,1,'Lodi','LO',0,1),(173,10,1,'Lucca','LU',0,1),(174,10,1,'Macerata','MC',0,1),(175,10,1,'Mantova','MN',0,1),(176,10,1,'Massa','MS',0,1),(177,10,1,'Matera','MT',0,1),(178,10,1,'Medio Campidano','VS',0,1),(179,10,1,'Messina','ME',0,1),(180,10,1,'Milano','MI',0,1),(181,10,1,'Modena','MO',0,1),(182,10,1,'Monza e della Brianza','MB',0,1),(183,10,1,'Napoli','NA',0,1),(184,10,1,'Novara','NO',0,1),(185,10,1,'Nuoro','NU',0,1),(186,10,1,'Ogliastra','OG',0,1),(187,10,1,'Olbia-Tempio','OT',0,1),(188,10,1,'Oristano','OR',0,1),(189,10,1,'Padova','PD',0,1),(190,10,1,'Palermo','PA',0,1),(191,10,1,'Parma','PR',0,1),(192,10,1,'Pavia','PV',0,1),(193,10,1,'Perugia','PG',0,1),(194,10,1,'Pesaro-Urbino','PU',0,1),(195,10,1,'Pescara','PE',0,1),(196,10,1,'Piacenza','PC',0,1),(197,10,1,'Pisa','PI',0,1),(198,10,1,'Pistoia','PT',0,1),(199,10,1,'Pordenone','PN',0,1),(200,10,1,'Potenza','PZ',0,1),(201,10,1,'Prato','PO',0,1),(202,10,1,'Ragusa','RG',0,1),(203,10,1,'Ravenna','RA',0,1),(204,10,1,'Reggio Calabria','RC',0,1),(205,10,1,'Reggio Emilia','RE',0,1),(206,10,1,'Rieti','RI',0,1),(207,10,1,'Rimini','RN',0,1),(208,10,1,'Roma','RM',0,1),(209,10,1,'Rovigo','RO',0,1),(210,10,1,'Salerno','SA',0,1),(211,10,1,'Sassari','SS',0,1),(212,10,1,'Savona','SV',0,1),(213,10,1,'Siena','SI',0,1),(214,10,1,'Siracusa','SR',0,1),(215,10,1,'Sondrio','SO',0,1),(216,10,1,'Taranto','TA',0,1),(217,10,1,'Teramo','TE',0,1),(218,10,1,'Terni','TR',0,1),(219,10,1,'Torino','TO',0,1),(220,10,1,'Trapani','TP',0,1),(221,10,1,'Trento','TN',0,1),(222,10,1,'Treviso','TV',0,1),(223,10,1,'Trieste','TS',0,1),(224,10,1,'Udine','UD',0,1),(225,10,1,'Varese','VA',0,1),(226,10,1,'Venezia','VE',0,1),(227,10,1,'Verbano-Cusio-Ossola','VB',0,1),(228,10,1,'Vercelli','VC',0,1),(229,10,1,'Verona','VR',0,1),(230,10,1,'Vibo Valentia','VV',0,1),(231,10,1,'Vicenza','VI',0,1),(232,10,1,'Viterbo','VT',0,1),(233,111,3,'Aceh','AC',0,1),(234,111,3,'Bali','BA',0,1),(235,111,3,'Bangka','BB',0,1),(236,111,3,'Banten','BT',0,1),(237,111,3,'Bengkulu','BE',0,1),(238,111,3,'Central Java','JT',0,1),(239,111,3,'Central Kalimantan','KT',0,1),(240,111,3,'Central Sulawesi','ST',0,1),(241,111,3,'Coat of arms of East Java','JI',0,1),(242,111,3,'East kalimantan','KI',0,1),(243,111,3,'East Nusa Tenggara','NT',0,1),(244,111,3,'Lambang propinsi','GO',0,1),(245,111,3,'Jakarta','JK',0,1),(246,111,3,'Jambi','JA',0,1),(247,111,3,'Lampung','LA',0,1),(248,111,3,'Maluku','MA',0,1),(249,111,3,'North Maluku','MU',0,1),(250,111,3,'North Sulawesi','SA',0,1),(251,111,3,'North Sumatra','SU',0,1),(252,111,3,'Papua','PA',0,1),(253,111,3,'Riau','RI',0,1),(254,111,3,'Lambang Riau','KR',0,1),(255,111,3,'Southeast Sulawesi','SG',0,1),(256,111,3,'South Kalimantan','KS',0,1),(257,111,3,'South Sulawesi','SN',0,1),(258,111,3,'South Sumatra','SS',0,1),(259,111,3,'West Java','JB',0,1),(260,111,3,'West Kalimantan','KB',0,1),(261,111,3,'West Nusa Tenggara','NB',0,1),(262,111,3,'Lambang Provinsi Papua Barat','PB',0,1),(263,111,3,'West Sulawesi','SR',0,1),(264,111,3,'West Sumatra','SB',0,1),(265,111,3,'Yogyakarta','YO',0,1),(266,11,3,'Aichi','23',0,1),(267,11,3,'Akita','05',0,1),(268,11,3,'Aomori','02',0,1),(269,11,3,'Chiba','12',0,1),(270,11,3,'Ehime','38',0,1),(271,11,3,'Fukui','18',0,1),(272,11,3,'Fukuoka','40',0,1),(273,11,3,'Fukushima','07',0,1),(274,11,3,'Gifu','21',0,1),(275,11,3,'Gunma','10',0,1),(276,11,3,'Hiroshima','34',0,1),(277,11,3,'Hokkaido','01',0,1),(278,11,3,'Hyogo','28',0,1),(279,11,3,'Ibaraki','08',0,1),(280,11,3,'Ishikawa','17',0,1),(281,11,3,'Iwate','03',0,1),(282,11,3,'Kagawa','37',0,1),(283,11,3,'Kagoshima','46',0,1),(284,11,3,'Kanagawa','14',0,1),(285,11,3,'Kochi','39',0,1),(286,11,3,'Kumamoto','43',0,1),(287,11,3,'Kyoto','26',0,1),(288,11,3,'Mie','24',0,1),(289,11,3,'Miyagi','04',0,1),(290,11,3,'Miyazaki','45',0,1),(291,11,3,'Nagano','20',0,1),(292,11,3,'Nagasaki','42',0,1),(293,11,3,'Nara','29',0,1),(294,11,3,'Niigata','15',0,1),(295,11,3,'Oita','44',0,1),(296,11,3,'Okayama','33',0,1),(297,11,3,'Okinawa','47',0,1),(298,11,3,'Osaka','27',0,1),(299,11,3,'Saga','41',0,1),(300,11,3,'Saitama','11',0,1),(301,11,3,'Shiga','25',0,1),(302,11,3,'Shimane','32',0,1),(303,11,3,'Shizuoka','22',0,1),(304,11,3,'Tochigi','09',0,1),(305,11,3,'Tokushima','36',0,1),(306,11,3,'Tokyo','13',0,1),(307,11,3,'Tottori','31',0,1),(308,11,3,'Toyama','16',0,1),(309,11,3,'Wakayama','30',0,1),(310,11,3,'Yamagata','06',0,1),(311,11,3,'Yamaguchi','35',0,1),(312,11,3,'Yamanashi','19',0,1);
/*!40000 ALTER TABLE `qlo_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_stock`
--

DROP TABLE IF EXISTS `qlo_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_stock` (
  `id_stock` int unsigned NOT NULL AUTO_INCREMENT,
  `id_warehouse` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  `reference` varchar(32) NOT NULL,
  `ean13` varchar(13) DEFAULT NULL,
  `upc` varchar(12) DEFAULT NULL,
  `physical_quantity` int unsigned NOT NULL,
  `usable_quantity` int unsigned NOT NULL,
  `price_te` decimal(20,6) DEFAULT '0.000000',
  PRIMARY KEY (`id_stock`),
  KEY `id_warehouse` (`id_warehouse`),
  KEY `id_product` (`id_product`),
  KEY `id_product_attribute` (`id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_stock`
--

LOCK TABLES `qlo_stock` WRITE;
/*!40000 ALTER TABLE `qlo_stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_stock_available`
--

DROP TABLE IF EXISTS `qlo_stock_available`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_stock_available` (
  `id_stock_available` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `id_shop_group` int unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `depends_on_stock` tinyint unsigned NOT NULL DEFAULT '0',
  `out_of_stock` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_stock_available`),
  UNIQUE KEY `product_sqlstock` (`id_product`,`id_product_attribute`,`id_shop`,`id_shop_group`),
  KEY `id_shop` (`id_shop`),
  KEY `id_shop_group` (`id_shop_group`),
  KEY `id_product` (`id_product`),
  KEY `id_product_attribute` (`id_product_attribute`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_stock_available`
--

LOCK TABLES `qlo_stock_available` WRITE;
/*!40000 ALTER TABLE `qlo_stock_available` DISABLE KEYS */;
INSERT INTO `qlo_stock_available` VALUES (1,1,0,1,0,999999999,0,1),(2,2,0,1,0,999999999,0,1),(3,3,0,1,0,999999999,0,1),(4,4,0,1,0,999999999,0,1),(5,5,0,1,0,999999999,0,1),(6,6,0,1,0,999999999,0,1),(7,7,0,1,0,999999999,0,1),(8,8,0,1,0,999999999,0,1),(9,9,0,1,0,999999999,0,1),(10,10,0,1,0,999999999,0,1);
/*!40000 ALTER TABLE `qlo_stock_available` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_stock_mvt`
--

DROP TABLE IF EXISTS `qlo_stock_mvt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_stock_mvt` (
  `id_stock_mvt` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_stock` int unsigned NOT NULL,
  `id_order` int unsigned DEFAULT NULL,
  `id_supply_order` int unsigned DEFAULT NULL,
  `id_stock_mvt_reason` int unsigned NOT NULL,
  `id_employee` int unsigned NOT NULL,
  `employee_lastname` varchar(32) DEFAULT '',
  `employee_firstname` varchar(32) DEFAULT '',
  `physical_quantity` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `sign` tinyint(1) NOT NULL DEFAULT '1',
  `price_te` decimal(20,6) DEFAULT '0.000000',
  `last_wa` decimal(20,6) DEFAULT '0.000000',
  `current_wa` decimal(20,6) DEFAULT '0.000000',
  `referer` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id_stock_mvt`),
  KEY `id_stock` (`id_stock`),
  KEY `id_stock_mvt_reason` (`id_stock_mvt_reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_stock_mvt`
--

LOCK TABLES `qlo_stock_mvt` WRITE;
/*!40000 ALTER TABLE `qlo_stock_mvt` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_stock_mvt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_stock_mvt_reason`
--

DROP TABLE IF EXISTS `qlo_stock_mvt_reason`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_stock_mvt_reason` (
  `id_stock_mvt_reason` int unsigned NOT NULL AUTO_INCREMENT,
  `sign` tinyint(1) NOT NULL DEFAULT '1',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_stock_mvt_reason`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_stock_mvt_reason`
--

LOCK TABLES `qlo_stock_mvt_reason` WRITE;
/*!40000 ALTER TABLE `qlo_stock_mvt_reason` DISABLE KEYS */;
INSERT INTO `qlo_stock_mvt_reason` VALUES (1,1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(2,-1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(3,-1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(4,-1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(5,1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(6,-1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(7,1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0),(8,1,'2026-09-03 18:45:46','2026-09-03 18:45:46',0);
/*!40000 ALTER TABLE `qlo_stock_mvt_reason` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_stock_mvt_reason_lang`
--

DROP TABLE IF EXISTS `qlo_stock_mvt_reason_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_stock_mvt_reason_lang` (
  `id_stock_mvt_reason` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id_stock_mvt_reason`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_stock_mvt_reason_lang`
--

LOCK TABLES `qlo_stock_mvt_reason_lang` WRITE;
/*!40000 ALTER TABLE `qlo_stock_mvt_reason_lang` DISABLE KEYS */;
INSERT INTO `qlo_stock_mvt_reason_lang` VALUES (1,1,'Increase'),(2,1,'Decrease'),(3,1,'Customer Order'),(4,1,'Regulation following an inventory of stock'),(5,1,'Regulation following an inventory of stock'),(6,1,'Transfer to another warehouse'),(7,1,'Transfer from another warehouse'),(8,1,'Supply Order');
/*!40000 ALTER TABLE `qlo_stock_mvt_reason_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_store`
--

DROP TABLE IF EXISTS `qlo_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_store` (
  `id_store` int unsigned NOT NULL AUTO_INCREMENT,
  `id_country` int unsigned NOT NULL,
  `id_state` int unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `address1` varchar(128) NOT NULL,
  `address2` varchar(128) DEFAULT NULL,
  `city` varchar(64) NOT NULL,
  `postcode` varchar(12) NOT NULL,
  `latitude` decimal(13,8) DEFAULT NULL,
  `longitude` decimal(13,8) DEFAULT NULL,
  `hours` varchar(254) DEFAULT NULL,
  `phone` varchar(16) DEFAULT NULL,
  `fax` varchar(16) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `note` text,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_store`
--

LOCK TABLES `qlo_store` WRITE;
/*!40000 ALTER TABLE `qlo_store` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_store` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_store_shop`
--

DROP TABLE IF EXISTS `qlo_store_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_store_shop` (
  `id_store` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_store`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_store_shop`
--

LOCK TABLES `qlo_store_shop` WRITE;
/*!40000 ALTER TABLE `qlo_store_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_store_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supplier`
--

DROP TABLE IF EXISTS `qlo_supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supplier` (
  `id_supplier` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supplier`
--

LOCK TABLES `qlo_supplier` WRITE;
/*!40000 ALTER TABLE `qlo_supplier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supplier_lang`
--

DROP TABLE IF EXISTS `qlo_supplier_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supplier_lang` (
  `id_supplier` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `description` text,
  `meta_title` varchar(128) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_supplier`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supplier_lang`
--

LOCK TABLES `qlo_supplier_lang` WRITE;
/*!40000 ALTER TABLE `qlo_supplier_lang` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supplier_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supplier_shop`
--

DROP TABLE IF EXISTS `qlo_supplier_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supplier_shop` (
  `id_supplier` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_supplier`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supplier_shop`
--

LOCK TABLES `qlo_supplier_shop` WRITE;
/*!40000 ALTER TABLE `qlo_supplier_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supplier_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supply_order`
--

DROP TABLE IF EXISTS `qlo_supply_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supply_order` (
  `id_supply_order` int unsigned NOT NULL AUTO_INCREMENT,
  `id_supplier` int unsigned NOT NULL,
  `supplier_name` varchar(64) NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `id_warehouse` int unsigned NOT NULL,
  `id_supply_order_state` int unsigned NOT NULL,
  `id_currency` int unsigned NOT NULL,
  `id_ref_currency` int unsigned NOT NULL,
  `reference` varchar(64) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  `date_delivery_expected` datetime DEFAULT NULL,
  `total_te` decimal(20,6) DEFAULT '0.000000',
  `total_with_discount_te` decimal(20,6) DEFAULT '0.000000',
  `total_tax` decimal(20,6) DEFAULT '0.000000',
  `total_ti` decimal(20,6) DEFAULT '0.000000',
  `discount_rate` decimal(20,6) DEFAULT '0.000000',
  `discount_value_te` decimal(20,6) DEFAULT '0.000000',
  `is_template` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_supply_order`),
  KEY `id_supplier` (`id_supplier`),
  KEY `id_warehouse` (`id_warehouse`),
  KEY `reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supply_order`
--

LOCK TABLES `qlo_supply_order` WRITE;
/*!40000 ALTER TABLE `qlo_supply_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supply_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supply_order_detail`
--

DROP TABLE IF EXISTS `qlo_supply_order_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supply_order_detail` (
  `id_supply_order_detail` int unsigned NOT NULL AUTO_INCREMENT,
  `id_supply_order` int unsigned NOT NULL,
  `id_currency` int unsigned NOT NULL,
  `id_product` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  `reference` varchar(32) NOT NULL,
  `supplier_reference` varchar(32) NOT NULL,
  `name` varchar(128) NOT NULL,
  `ean13` varchar(13) DEFAULT NULL,
  `upc` varchar(12) DEFAULT NULL,
  `exchange_rate` decimal(20,6) DEFAULT '0.000000',
  `unit_price_te` decimal(20,6) DEFAULT '0.000000',
  `quantity_expected` int unsigned NOT NULL,
  `quantity_received` int unsigned NOT NULL,
  `price_te` decimal(20,6) DEFAULT '0.000000',
  `discount_rate` decimal(20,6) DEFAULT '0.000000',
  `discount_value_te` decimal(20,6) DEFAULT '0.000000',
  `price_with_discount_te` decimal(20,6) DEFAULT '0.000000',
  `tax_rate` decimal(20,6) DEFAULT '0.000000',
  `tax_value` decimal(20,6) DEFAULT '0.000000',
  `price_ti` decimal(20,6) DEFAULT '0.000000',
  `tax_value_with_order_discount` decimal(20,6) DEFAULT '0.000000',
  `price_with_order_discount_te` decimal(20,6) DEFAULT '0.000000',
  PRIMARY KEY (`id_supply_order_detail`),
  KEY `id_supply_order` (`id_supply_order`,`id_product`),
  KEY `id_product_attribute` (`id_product_attribute`),
  KEY `id_product_product_attribute` (`id_product`,`id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supply_order_detail`
--

LOCK TABLES `qlo_supply_order_detail` WRITE;
/*!40000 ALTER TABLE `qlo_supply_order_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supply_order_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supply_order_history`
--

DROP TABLE IF EXISTS `qlo_supply_order_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supply_order_history` (
  `id_supply_order_history` int unsigned NOT NULL AUTO_INCREMENT,
  `id_supply_order` int unsigned NOT NULL,
  `id_employee` int unsigned NOT NULL,
  `employee_lastname` varchar(32) DEFAULT '',
  `employee_firstname` varchar(32) DEFAULT '',
  `id_state` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_supply_order_history`),
  KEY `id_supply_order` (`id_supply_order`),
  KEY `id_employee` (`id_employee`),
  KEY `id_state` (`id_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supply_order_history`
--

LOCK TABLES `qlo_supply_order_history` WRITE;
/*!40000 ALTER TABLE `qlo_supply_order_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supply_order_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supply_order_receipt_history`
--

DROP TABLE IF EXISTS `qlo_supply_order_receipt_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supply_order_receipt_history` (
  `id_supply_order_receipt_history` int unsigned NOT NULL AUTO_INCREMENT,
  `id_supply_order_detail` int unsigned NOT NULL,
  `id_employee` int unsigned NOT NULL,
  `employee_lastname` varchar(32) DEFAULT '',
  `employee_firstname` varchar(32) DEFAULT '',
  `id_supply_order_state` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_supply_order_receipt_history`),
  KEY `id_supply_order_detail` (`id_supply_order_detail`),
  KEY `id_supply_order_state` (`id_supply_order_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supply_order_receipt_history`
--

LOCK TABLES `qlo_supply_order_receipt_history` WRITE;
/*!40000 ALTER TABLE `qlo_supply_order_receipt_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_supply_order_receipt_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supply_order_state`
--

DROP TABLE IF EXISTS `qlo_supply_order_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supply_order_state` (
  `id_supply_order_state` int unsigned NOT NULL AUTO_INCREMENT,
  `delivery_note` tinyint(1) NOT NULL DEFAULT '0',
  `editable` tinyint(1) NOT NULL DEFAULT '0',
  `receipt_state` tinyint(1) NOT NULL DEFAULT '0',
  `pending_receipt` tinyint(1) NOT NULL DEFAULT '0',
  `enclosed` tinyint(1) NOT NULL DEFAULT '0',
  `color` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id_supply_order_state`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supply_order_state`
--

LOCK TABLES `qlo_supply_order_state` WRITE;
/*!40000 ALTER TABLE `qlo_supply_order_state` DISABLE KEYS */;
INSERT INTO `qlo_supply_order_state` VALUES (1,0,1,0,0,0,'#faab00'),(2,1,0,0,0,0,'#273cff'),(3,0,0,0,1,0,'#ff37f5'),(4,0,0,1,1,0,'#ff3e33'),(5,0,0,1,0,1,'#00d60c'),(6,0,0,0,0,1,'#666666');
/*!40000 ALTER TABLE `qlo_supply_order_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_supply_order_state_lang`
--

DROP TABLE IF EXISTS `qlo_supply_order_state_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_supply_order_state_lang` (
  `id_supply_order_state` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id_supply_order_state`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_supply_order_state_lang`
--

LOCK TABLES `qlo_supply_order_state_lang` WRITE;
/*!40000 ALTER TABLE `qlo_supply_order_state_lang` DISABLE KEYS */;
INSERT INTO `qlo_supply_order_state_lang` VALUES (1,1,'1 - Creation in progress'),(2,1,'2 - Order validated'),(3,1,'3 - Pending receipt'),(4,1,'4 - Order received in part'),(5,1,'5 - Order received completely'),(6,1,'6 - Order canceled');
/*!40000 ALTER TABLE `qlo_supply_order_state_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tab`
--

DROP TABLE IF EXISTS `qlo_tab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tab` (
  `id_tab` int unsigned NOT NULL AUTO_INCREMENT,
  `id_parent` int NOT NULL,
  `class_name` varchar(64) NOT NULL,
  `module` varchar(64) DEFAULT NULL,
  `position` int unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `hide_host_mode` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_tab`),
  KEY `class_name` (`class_name`),
  KEY `id_parent` (`id_parent`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tab`
--

LOCK TABLES `qlo_tab` WRITE;
/*!40000 ALTER TABLE `qlo_tab` DISABLE KEYS */;
INSERT INTO `qlo_tab` VALUES (1,0,'AdminDashboard',NULL,0,1,0),(2,-1,'AdminCms',NULL,0,1,0),(3,-1,'AdminCmsCategories',NULL,1,1,0),(4,-1,'AdminSearch',NULL,2,1,0),(5,-1,'AdminLogin',NULL,3,1,0),(6,-1,'AdminShop',NULL,4,1,0),(7,-1,'AdminShopUrl',NULL,5,1,0),(8,0,'AdminCatalog',NULL,1,1,0),(9,0,'AdminParentOrders',NULL,2,1,0),(10,0,'AdminParentCustomer',NULL,3,1,0),(11,0,'AdminPriceRule',NULL,4,1,0),(12,0,'AdminParentModules',NULL,7,1,0),(13,0,'AdminParentLocalization',NULL,8,1,0),(14,0,'AdminParentPreferences',NULL,9,1,0),(15,0,'AdminTools',NULL,10,1,0),(16,0,'AdminAdmin',NULL,11,1,0),(17,0,'AdminStock',NULL,12,1,0),(18,8,'AdminProducts',NULL,0,1,0),(19,8,'AdminNormalProducts',NULL,1,1,0),(20,8,'AdminCategories',NULL,2,1,0),(21,8,'AdminFeatures',NULL,3,1,0),(22,9,'AdminOrders',NULL,0,1,0),(23,9,'AdminInvoices',NULL,1,1,0),(24,9,'AdminPaymentReceipts',NULL,2,1,0),(25,9,'AdminSlip',NULL,3,1,0),(26,9,'AdminStatuses',NULL,4,1,0),(27,9,'AdminOrderMessage',NULL,5,1,0),(28,9,'AdminGuestRegistration',NULL,6,1,0),(29,10,'AdminCustomers',NULL,0,1,0),(30,10,'AdminAddresses',NULL,1,1,0),(31,10,'AdminGroups',NULL,2,1,0),(32,10,'AdminCarts',NULL,3,1,0),(33,10,'AdminCustomerThreads',NULL,4,1,0),(34,10,'AdminContacts',NULL,5,1,0),(35,10,'AdminGenders',NULL,6,1,0),(36,10,'AdminOutstanding',NULL,7,0,0),(37,11,'AdminCartRules',NULL,0,1,0),(38,11,'AdminSpecificPriceRule',NULL,1,1,0),(39,13,'AdminLocalization',NULL,0,1,0),(40,13,'AdminLanguages',NULL,1,1,0),(41,13,'AdminZones',NULL,2,1,0),(42,13,'AdminCountries',NULL,3,1,0),(43,13,'AdminStates',NULL,4,1,0),(44,13,'AdminCurrencies',NULL,5,1,0),(45,13,'AdminTaxes',NULL,6,1,0),(46,13,'AdminTaxRulesGroup',NULL,7,1,0),(47,13,'AdminTranslations',NULL,8,1,0),(48,12,'AdminModules',NULL,0,1,0),(49,12,'AdminModulesCatalog',NULL,1,1,0),(50,12,'AdminModulesPositions',NULL,2,1,0),(51,12,'AdminPayment',NULL,3,1,0),(52,14,'AdminPreferences',NULL,0,1,0),(53,14,'AdminOrderPreferences',NULL,1,1,0),(54,14,'AdminPPreferences',NULL,2,1,0),(55,14,'AdminCustomerPreferences',NULL,3,1,0),(56,14,'AdminThemes',NULL,4,1,0),(57,14,'AdminMeta',NULL,5,1,0),(58,14,'AdminCmsContent',NULL,6,1,0),(59,14,'AdminImages',NULL,7,1,0),(60,14,'AdminMaintenance',NULL,8,1,0),(61,14,'AdminGeolocation',NULL,9,1,0),(62,15,'AdminInformation',NULL,0,1,0),(63,15,'AdminPerformance',NULL,1,1,0),(64,15,'AdminEmails',NULL,2,1,0),(65,15,'AdminShopGroup',NULL,3,0,0),(66,15,'AdminImport',NULL,4,1,0),(67,15,'AdminBackup',NULL,5,1,0),(68,15,'AdminRequestSql',NULL,6,1,0),(69,15,'AdminLogs',NULL,7,1,0),(70,15,'AdminWebservice',NULL,8,1,0),(71,16,'AdminAdminPreferences',NULL,0,1,0),(72,16,'AdminQuickAccesses',NULL,1,1,0),(73,16,'AdminEmployees',NULL,2,1,0),(74,16,'AdminProfiles',NULL,3,1,0),(75,16,'AdminAccess',NULL,4,1,0),(76,16,'AdminTabs',NULL,5,1,0),(77,0,'AdminStats',NULL,13,1,0),(78,17,'AdminWarehouses',NULL,0,1,0),(79,17,'AdminStockManagement',NULL,1,1,0),(80,17,'AdminStockMvt',NULL,2,1,0),(81,17,'AdminStockInstantState',NULL,3,1,0),(82,17,'AdminStockCover',NULL,4,1,0),(83,17,'AdminStockConfiguration',NULL,5,1,0),(84,0,'AdminHotelReservationSystemManagement','hotelreservationsystem',5,1,0),(85,84,'AdminAddHotel','hotelreservationsystem',1,1,0),(86,84,'AdminHotelRoomsBooking','hotelreservationsystem',2,1,0),(87,84,'AdminHotelAmenities','hotelreservationsystem',3,1,0),(88,84,'AdminOrderRefundRules','hotelreservationsystem',4,1,0),(89,84,'AdminOrderRefundRequests','hotelreservationsystem',5,1,0),(90,84,'AdminHotelConfigurationSetting','hotelreservationsystem',6,1,0),(91,90,'AdminHotelGeneralSettings','hotelreservationsystem',1,1,0),(92,90,'AdminHotelFeaturePricesSettings','hotelreservationsystem',2,1,0),(93,90,'AdminRoomTypeGlobalDemand','hotelreservationsystem',3,1,0),(94,-1,'AdminBookingDocument','hotelreservationsystem',6,1,0),(95,-1,'AdminHotelImageCategory','hotelreservationsystem',7,1,0),(96,-1,'AdminParentNewsletter','blocknewsletter',8,1,0),(97,96,'AdminNewsletter','blocknewsletter',1,1,0),(98,-1,'AdminAboutHotelBlockSetting','wkabouthotelblock',9,1,0),(99,-1,'AdminFeaturesModuleSetting','wkhotelfeaturesblock',10,1,0),(100,-1,'AdminHotelRoomModuleSetting','wkhotelroom',11,1,0),(101,-1,'AdminTestimonialsModuleSetting','wktestimonialblock',12,1,0),(102,-1,'AdminParentHotelReview','qlohotelreview',13,1,0),(103,102,'AdminHotelReviewCategory','qlohotelreview',1,1,0),(104,102,'AdminHotelReviewHotelReview','qlohotelreview',2,1,0),(105,-1,'AdminFooterPaymentBlockSetting','wkfooterpaymentblock',14,1,0),(106,-1,'AdminCustomNavigationLinkSetting','blocknavigationmenu',15,1,0),(107,-1,'AdminDashgoals','dashgoals',16,1,0),(108,0,'AdminQloappsChannelManagerConnector','qlochannelmanagerconnector',6,1,0),(109,0,'AdminPulseCore','pulsecore',14,1,0),(110,109,'AdminPulseLicense','pulselicense',1,1,0);
/*!40000 ALTER TABLE `qlo_tab` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tab_lang`
--

DROP TABLE IF EXISTS `qlo_tab_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tab_lang` (
  `id_tab` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id_tab`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tab_lang`
--

LOCK TABLES `qlo_tab_lang` WRITE;
/*!40000 ALTER TABLE `qlo_tab_lang` DISABLE KEYS */;
INSERT INTO `qlo_tab_lang` VALUES (1,1,'Dashboard'),(2,1,'CMS Pages'),(3,1,'CMS Categories'),(4,1,'Search'),(5,1,'Login'),(6,1,'Shops'),(7,1,'Shop URLs'),(8,1,'Catalog'),(9,1,'Orders'),(10,1,'Customers'),(11,1,'Manage Discounts'),(12,1,'Modules and Services'),(13,1,'Localization'),(14,1,'Preferences'),(15,1,'Advanced Parameters'),(16,1,'Administration'),(17,1,'Stock'),(18,1,'Manage Room Types'),(19,1,'Manage Service Products'),(20,1,'Categories'),(21,1,'Features'),(22,1,'Orders'),(23,1,'Invoices'),(24,1,'Payment Receipts'),(25,1,'Credit Slips'),(26,1,'Statuses'),(27,1,'Order Messages'),(28,1,'Guest Registration Card'),(29,1,'Customers'),(30,1,'Addresses'),(31,1,'Groups'),(32,1,'Booking Carts'),(33,1,'Customer Service'),(34,1,'Contacts'),(35,1,'Titles'),(36,1,'Outstanding'),(37,1,'Cart Rules'),(38,1,'Catalog Price Rules'),(39,1,'Localization'),(40,1,'Languages'),(41,1,'Zones'),(42,1,'Countries'),(43,1,'States'),(44,1,'Currencies'),(45,1,'Taxes'),(46,1,'Tax Rules'),(47,1,'Translations'),(48,1,'Manage Modules'),(49,1,'Modules Catalog'),(50,1,'Positions'),(51,1,'Payment'),(52,1,'General'),(53,1,'Orders'),(54,1,'Room Types'),(55,1,'Customers'),(56,1,'Themes'),(57,1,'SEO & URLs'),(58,1,'CMS'),(59,1,'Images'),(60,1,'Maintenance'),(61,1,'Geolocation'),(62,1,'Configuration Information'),(63,1,'Performance'),(64,1,'E-mail'),(65,1,'Multistore'),(66,1,'CSV Import'),(67,1,'DB Backup'),(68,1,'SQL Manager'),(69,1,'Logs'),(70,1,'Webservice'),(71,1,'Preferences'),(72,1,'Quick Access'),(73,1,'Employees'),(74,1,'Profiles'),(75,1,'Permissions'),(76,1,'Menus'),(77,1,'Stats'),(78,1,'Warehouses'),(79,1,'Stock Management'),(80,1,'Stock Movement'),(81,1,'Instant Stock Status'),(82,1,'Stock Coverage'),(83,1,'Configuration'),(84,1,'Hotel Reservation System'),(85,1,'Manage Hotel'),(86,1,'Book Now'),(87,1,'Manage Amenities'),(88,1,'Manage Order Refund Rules'),(89,1,'Manage Order Refund Requests'),(90,1,'General Settings'),(91,1,'Hotel General Configuration'),(92,1,'Advanced Price Rules'),(93,1,'Additional Demand Configuration'),(94,1,'Booking Documents'),(95,1,'Hotel Image Category'),(96,1,'Newsletter'),(97,1,'Configuration'),(98,1,'Hotel Description Configuration'),(99,1,'Hotel Amenities Configurations'),(100,1,'Manage Hotel Rooms Display'),(101,1,'Testimonial configuration'),(102,1,'Hotel Reviews'),(103,1,'Configuration'),(104,1,'Reviews'),(105,1,'Manage Footer Payment Block'),(106,1,'Manage Custom Navigation Links'),(107,1,'Dashgoals'),(108,1,'Channel Manager'),(109,1,'Pulse'),(110,1,'License');
/*!40000 ALTER TABLE `qlo_tab_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tab_module_preference`
--

DROP TABLE IF EXISTS `qlo_tab_module_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tab_module_preference` (
  `id_tab_module_preference` int NOT NULL AUTO_INCREMENT,
  `id_employee` int NOT NULL,
  `id_tab` int NOT NULL,
  `module` varchar(255) NOT NULL,
  PRIMARY KEY (`id_tab_module_preference`),
  UNIQUE KEY `employee_module` (`id_employee`,`id_tab`,`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tab_module_preference`
--

LOCK TABLES `qlo_tab_module_preference` WRITE;
/*!40000 ALTER TABLE `qlo_tab_module_preference` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_tab_module_preference` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tag`
--

DROP TABLE IF EXISTS `qlo_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tag` (
  `id_tag` int unsigned NOT NULL AUTO_INCREMENT,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id_tag`),
  KEY `tag_name` (`name`),
  KEY `id_lang` (`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tag`
--

LOCK TABLES `qlo_tag` WRITE;
/*!40000 ALTER TABLE `qlo_tag` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tag_count`
--

DROP TABLE IF EXISTS `qlo_tag_count`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tag_count` (
  `id_group` int unsigned NOT NULL DEFAULT '0',
  `id_tag` int unsigned NOT NULL DEFAULT '0',
  `id_lang` int unsigned NOT NULL DEFAULT '0',
  `id_shop` int unsigned NOT NULL DEFAULT '0',
  `counter` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_group`,`id_tag`),
  KEY `id_group` (`id_group`,`id_lang`,`id_shop`,`counter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tag_count`
--

LOCK TABLES `qlo_tag_count` WRITE;
/*!40000 ALTER TABLE `qlo_tag_count` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_tag_count` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tax`
--

DROP TABLE IF EXISTS `qlo_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tax` (
  `id_tax` int unsigned NOT NULL AUTO_INCREMENT,
  `rate` decimal(10,3) NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_tax`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tax`
--

LOCK TABLES `qlo_tax` WRITE;
/*!40000 ALTER TABLE `qlo_tax` DISABLE KEYS */;
INSERT INTO `qlo_tax` VALUES (1,5.000,1,0),(2,0.000,1,0);
/*!40000 ALTER TABLE `qlo_tax` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tax_lang`
--

DROP TABLE IF EXISTS `qlo_tax_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tax_lang` (
  `id_tax` int unsigned NOT NULL,
  `id_lang` int unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id_tax`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tax_lang`
--

LOCK TABLES `qlo_tax_lang` WRITE;
/*!40000 ALTER TABLE `qlo_tax_lang` DISABLE KEYS */;
INSERT INTO `qlo_tax_lang` VALUES (1,1,'Nigeria Standard VAT 5%'),(2,1,'Nigeria Reduced VAT 0%');
/*!40000 ALTER TABLE `qlo_tax_lang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tax_rule`
--

DROP TABLE IF EXISTS `qlo_tax_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tax_rule` (
  `id_tax_rule` int NOT NULL AUTO_INCREMENT,
  `id_tax_rules_group` int NOT NULL,
  `id_country` int NOT NULL,
  `id_state` int NOT NULL,
  `zipcode_from` varchar(12) NOT NULL,
  `zipcode_to` varchar(12) NOT NULL,
  `id_tax` int NOT NULL,
  `behavior` int NOT NULL,
  `description` varchar(100) NOT NULL,
  PRIMARY KEY (`id_tax_rule`),
  KEY `id_tax_rules_group` (`id_tax_rules_group`),
  KEY `id_tax` (`id_tax`),
  KEY `category_getproducts` (`id_tax_rules_group`,`id_country`,`id_state`,`zipcode_from`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tax_rule`
--

LOCK TABLES `qlo_tax_rule` WRITE;
/*!40000 ALTER TABLE `qlo_tax_rule` DISABLE KEYS */;
INSERT INTO `qlo_tax_rule` VALUES (1,1,31,0,'0','0',1,0,''),(2,2,31,0,'0','0',2,0,'');
/*!40000 ALTER TABLE `qlo_tax_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tax_rules_group`
--

DROP TABLE IF EXISTS `qlo_tax_rules_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tax_rules_group` (
  `id_tax_rules_group` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `active` int NOT NULL,
  `deleted` tinyint unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_tax_rules_group`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tax_rules_group`
--

LOCK TABLES `qlo_tax_rules_group` WRITE;
/*!40000 ALTER TABLE `qlo_tax_rules_group` DISABLE KEYS */;
INSERT INTO `qlo_tax_rules_group` VALUES (1,'Nigeria Standard Rate 5%',1,0,'2026-09-03 18:46:27','2026-09-03 18:46:27'),(2,'Nigeria Reduced Rate 0%',1,0,'2026-09-03 18:46:27','2026-09-03 18:46:27');
/*!40000 ALTER TABLE `qlo_tax_rules_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_tax_rules_group_shop`
--

DROP TABLE IF EXISTS `qlo_tax_rules_group_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_tax_rules_group_shop` (
  `id_tax_rules_group` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_tax_rules_group`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_tax_rules_group_shop`
--

LOCK TABLES `qlo_tax_rules_group_shop` WRITE;
/*!40000 ALTER TABLE `qlo_tax_rules_group_shop` DISABLE KEYS */;
INSERT INTO `qlo_tax_rules_group_shop` VALUES (1,1),(2,1);
/*!40000 ALTER TABLE `qlo_tax_rules_group_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_theme`
--

DROP TABLE IF EXISTS `qlo_theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_theme` (
  `id_theme` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `directory` varchar(64) NOT NULL,
  `responsive` tinyint(1) NOT NULL DEFAULT '0',
  `default_left_column` tinyint(1) NOT NULL DEFAULT '0',
  `default_right_column` tinyint(1) NOT NULL DEFAULT '0',
  `product_per_page` int unsigned NOT NULL,
  PRIMARY KEY (`id_theme`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_theme`
--

LOCK TABLES `qlo_theme` WRITE;
/*!40000 ALTER TABLE `qlo_theme` DISABLE KEYS */;
INSERT INTO `qlo_theme` VALUES (1,'hotel-reservation-theme','hotel-reservation-theme',1,1,0,12);
/*!40000 ALTER TABLE `qlo_theme` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_theme_meta`
--

DROP TABLE IF EXISTS `qlo_theme_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_theme_meta` (
  `id_theme_meta` int NOT NULL AUTO_INCREMENT,
  `id_theme` int NOT NULL,
  `id_meta` int unsigned NOT NULL,
  `left_column` tinyint(1) NOT NULL DEFAULT '1',
  `right_column` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_theme_meta`),
  UNIQUE KEY `id_theme_2` (`id_theme`,`id_meta`),
  KEY `id_theme` (`id_theme`),
  KEY `id_meta` (`id_meta`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_theme_meta`
--

LOCK TABLES `qlo_theme_meta` WRITE;
/*!40000 ALTER TABLE `qlo_theme_meta` DISABLE KEYS */;
INSERT INTO `qlo_theme_meta` VALUES (1,1,1,0,0),(2,1,2,1,0),(3,1,3,0,0),(4,1,4,0,0),(5,1,5,1,0),(6,1,6,1,0),(7,1,7,0,0),(8,1,8,1,0),(9,1,9,0,0),(10,1,10,0,0),(11,1,11,0,0),(12,1,12,0,0),(13,1,13,0,0),(14,1,14,0,0),(15,1,15,0,0),(16,1,16,0,0),(17,1,17,0,0),(18,1,18,0,0),(19,1,19,0,0),(20,1,20,0,0),(21,1,21,0,0),(22,1,22,0,0),(23,1,23,0,0),(24,1,24,0,0),(25,1,25,1,0),(26,1,26,0,0),(27,1,27,0,0),(28,1,28,0,0),(29,1,29,1,0),(30,1,30,0,0),(31,1,31,0,0),(32,1,32,0,0);
/*!40000 ALTER TABLE `qlo_theme_meta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_theme_specific`
--

DROP TABLE IF EXISTS `qlo_theme_specific`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_theme_specific` (
  `id_theme` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  `entity` int unsigned NOT NULL,
  `id_object` int unsigned NOT NULL,
  PRIMARY KEY (`id_theme`,`id_shop`,`entity`,`id_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_theme_specific`
--

LOCK TABLES `qlo_theme_specific` WRITE;
/*!40000 ALTER TABLE `qlo_theme_specific` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_theme_specific` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_timezone`
--

DROP TABLE IF EXISTS `qlo_timezone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_timezone` (
  `id_timezone` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id_timezone`)
) ENGINE=InnoDB AUTO_INCREMENT=561 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_timezone`
--

LOCK TABLES `qlo_timezone` WRITE;
/*!40000 ALTER TABLE `qlo_timezone` DISABLE KEYS */;
INSERT INTO `qlo_timezone` VALUES (1,'Africa/Abidjan'),(2,'Africa/Accra'),(3,'Africa/Addis_Ababa'),(4,'Africa/Algiers'),(5,'Africa/Asmara'),(6,'Africa/Asmera'),(7,'Africa/Bamako'),(8,'Africa/Bangui'),(9,'Africa/Banjul'),(10,'Africa/Bissau'),(11,'Africa/Blantyre'),(12,'Africa/Brazzaville'),(13,'Africa/Bujumbura'),(14,'Africa/Cairo'),(15,'Africa/Casablanca'),(16,'Africa/Ceuta'),(17,'Africa/Conakry'),(18,'Africa/Dakar'),(19,'Africa/Dar_es_Salaam'),(20,'Africa/Djibouti'),(21,'Africa/Douala'),(22,'Africa/El_Aaiun'),(23,'Africa/Freetown'),(24,'Africa/Gaborone'),(25,'Africa/Harare'),(26,'Africa/Johannesburg'),(27,'Africa/Kampala'),(28,'Africa/Khartoum'),(29,'Africa/Kigali'),(30,'Africa/Kinshasa'),(31,'Africa/Lagos'),(32,'Africa/Libreville'),(33,'Africa/Lome'),(34,'Africa/Luanda'),(35,'Africa/Lubumbashi'),(36,'Africa/Lusaka'),(37,'Africa/Malabo'),(38,'Africa/Maputo'),(39,'Africa/Maseru'),(40,'Africa/Mbabane'),(41,'Africa/Mogadishu'),(42,'Africa/Monrovia'),(43,'Africa/Nairobi'),(44,'Africa/Ndjamena'),(45,'Africa/Niamey'),(46,'Africa/Nouakchott'),(47,'Africa/Ouagadougou'),(48,'Africa/Porto-Novo'),(49,'Africa/Sao_Tome'),(50,'Africa/Timbuktu'),(51,'Africa/Tripoli'),(52,'Africa/Tunis'),(53,'Africa/Windhoek'),(54,'America/Adak'),(55,'America/Anchorage '),(56,'America/Anguilla'),(57,'America/Antigua'),(58,'America/Araguaina'),(59,'America/Argentina/Buenos_Aires'),(60,'America/Argentina/Catamarca'),(61,'America/Argentina/ComodRivadavia'),(62,'America/Argentina/Cordoba'),(63,'America/Argentina/Jujuy'),(64,'America/Argentina/La_Rioja'),(65,'America/Argentina/Mendoza'),(66,'America/Argentina/Rio_Gallegos'),(67,'America/Argentina/Salta'),(68,'America/Argentina/San_Juan'),(69,'America/Argentina/San_Luis'),(70,'America/Argentina/Tucuman'),(71,'America/Argentina/Ushuaia'),(72,'America/Aruba'),(73,'America/Asuncion'),(74,'America/Atikokan'),(75,'America/Atka'),(76,'America/Bahia'),(77,'America/Barbados'),(78,'America/Belem'),(79,'America/Belize'),(80,'America/Blanc-Sablon'),(81,'America/Boa_Vista'),(82,'America/Bogota'),(83,'America/Boise'),(84,'America/Buenos_Aires'),(85,'America/Cambridge_Bay'),(86,'America/Campo_Grande'),(87,'America/Cancun'),(88,'America/Caracas'),(89,'America/Catamarca'),(90,'America/Cayenne'),(91,'America/Cayman'),(92,'America/Chicago'),(93,'America/Chihuahua'),(94,'America/Coral_Harbour'),(95,'America/Cordoba'),(96,'America/Costa_Rica'),(97,'America/Cuiaba'),(98,'America/Curacao'),(99,'America/Danmarkshavn'),(100,'America/Dawson'),(101,'America/Dawson_Creek'),(102,'America/Denver'),(103,'America/Detroit'),(104,'America/Dominica'),(105,'America/Edmonton'),(106,'America/Eirunepe'),(107,'America/El_Salvador'),(108,'America/Ensenada'),(109,'America/Fort_Wayne'),(110,'America/Fortaleza'),(111,'America/Glace_Bay'),(112,'America/Godthab'),(113,'America/Goose_Bay'),(114,'America/Grand_Turk'),(115,'America/Grenada'),(116,'America/Guadeloupe'),(117,'America/Guatemala'),(118,'America/Guayaquil'),(119,'America/Guyana'),(120,'America/Halifax'),(121,'America/Havana'),(122,'America/Hermosillo'),(123,'America/Indiana/Indianapolis'),(124,'America/Indiana/Knox'),(125,'America/Indiana/Marengo'),(126,'America/Indiana/Petersburg'),(127,'America/Indiana/Tell_City'),(128,'America/Indiana/Vevay'),(129,'America/Indiana/Vincennes'),(130,'America/Indiana/Winamac'),(131,'America/Indianapolis'),(132,'America/Inuvik'),(133,'America/Iqaluit'),(134,'America/Jamaica'),(135,'America/Jujuy'),(136,'America/Juneau'),(137,'America/Kentucky/Louisville'),(138,'America/Kentucky/Monticello'),(139,'America/Knox_IN'),(140,'America/La_Paz'),(141,'America/Lima'),(142,'America/Los_Angeles'),(143,'America/Louisville'),(144,'America/Maceio'),(145,'America/Managua'),(146,'America/Manaus'),(147,'America/Marigot'),(148,'America/Martinique'),(149,'America/Mazatlan'),(150,'America/Mendoza'),(151,'America/Menominee'),(152,'America/Merida'),(153,'America/Mexico_City'),(154,'America/Miquelon'),(155,'America/Moncton'),(156,'America/Monterrey'),(157,'America/Montevideo'),(158,'America/Montreal'),(159,'America/Montserrat'),(160,'America/Nassau'),(161,'America/New_York'),(162,'America/Nipigon'),(163,'America/Nome'),(164,'America/Noronha'),(165,'America/North_Dakota/Center'),(166,'America/North_Dakota/New_Salem'),(167,'America/Panama'),(168,'America/Pangnirtung'),(169,'America/Paramaribo'),(170,'America/Phoenix'),(171,'America/Port-au-Prince'),(172,'America/Port_of_Spain'),(173,'America/Porto_Acre'),(174,'America/Porto_Velho'),(175,'America/Puerto_Rico'),(176,'America/Rainy_River'),(177,'America/Rankin_Inlet'),(178,'America/Recife'),(179,'America/Regina'),(180,'America/Resolute'),(181,'America/Rio_Branco'),(182,'America/Rosario'),(183,'America/Santarem'),(184,'America/Santiago'),(185,'America/Santo_Domingo'),(186,'America/Sao_Paulo'),(187,'America/Scoresbysund'),(188,'America/Shiprock'),(189,'America/St_Barthelemy'),(190,'America/St_Johns'),(191,'America/St_Kitts'),(192,'America/St_Lucia'),(193,'America/St_Thomas'),(194,'America/St_Vincent'),(195,'America/Swift_Current'),(196,'America/Tegucigalpa'),(197,'America/Thule'),(198,'America/Thunder_Bay'),(199,'America/Tijuana'),(200,'America/Toronto'),(201,'America/Tortola'),(202,'America/Vancouver'),(203,'America/Virgin'),(204,'America/Whitehorse'),(205,'America/Winnipeg'),(206,'America/Yakutat'),(207,'America/Yellowknife'),(208,'Antarctica/Casey'),(209,'Antarctica/Davis'),(210,'Antarctica/DumontDUrville'),(211,'Antarctica/Mawson'),(212,'Antarctica/McMurdo'),(213,'Antarctica/Palmer'),(214,'Antarctica/Rothera'),(215,'Antarctica/South_Pole'),(216,'Antarctica/Syowa'),(217,'Antarctica/Vostok'),(218,'Arctic/Longyearbyen'),(219,'Asia/Aden'),(220,'Asia/Almaty'),(221,'Asia/Amman'),(222,'Asia/Anadyr'),(223,'Asia/Aqtau'),(224,'Asia/Aqtobe'),(225,'Asia/Ashgabat'),(226,'Asia/Ashkhabad'),(227,'Asia/Baghdad'),(228,'Asia/Bahrain'),(229,'Asia/Baku'),(230,'Asia/Bangkok'),(231,'Asia/Beirut'),(232,'Asia/Bishkek'),(233,'Asia/Brunei'),(234,'Asia/Calcutta'),(235,'Asia/Choibalsan'),(236,'Asia/Chongqing'),(237,'Asia/Chungking'),(238,'Asia/Colombo'),(239,'Asia/Dacca'),(240,'Asia/Damascus'),(241,'Asia/Dhaka'),(242,'Asia/Dili'),(243,'Asia/Dubai'),(244,'Asia/Dushanbe'),(245,'Asia/Gaza'),(246,'Asia/Harbin'),(247,'Asia/Ho_Chi_Minh'),(248,'Asia/Hong_Kong'),(249,'Asia/Hovd'),(250,'Asia/Irkutsk'),(251,'Asia/Istanbul'),(252,'Asia/Jakarta'),(253,'Asia/Jayapura'),(254,'Asia/Jerusalem'),(255,'Asia/Kabul'),(256,'Asia/Kamchatka'),(257,'Asia/Karachi'),(258,'Asia/Kashgar'),(259,'Asia/Kathmandu'),(260,'Asia/Katmandu'),(261,'Asia/Kolkata'),(262,'Asia/Krasnoyarsk'),(263,'Asia/Kuala_Lumpur'),(264,'Asia/Kuching'),(265,'Asia/Kuwait'),(266,'Asia/Macao'),(267,'Asia/Macau'),(268,'Asia/Magadan'),(269,'Asia/Makassar'),(270,'Asia/Manila'),(271,'Asia/Muscat'),(272,'Asia/Nicosia'),(273,'Asia/Novosibirsk'),(274,'Asia/Omsk'),(275,'Asia/Oral'),(276,'Asia/Phnom_Penh'),(277,'Asia/Pontianak'),(278,'Asia/Pyongyang'),(279,'Asia/Qatar'),(280,'Asia/Qyzylorda'),(281,'Asia/Rangoon'),(282,'Asia/Riyadh'),(283,'Asia/Saigon'),(284,'Asia/Sakhalin'),(285,'Asia/Samarkand'),(286,'Asia/Seoul'),(287,'Asia/Shanghai'),(288,'Asia/Singapore'),(289,'Asia/Taipei'),(290,'Asia/Tashkent'),(291,'Asia/Tbilisi'),(292,'Asia/Tehran'),(293,'Asia/Tel_Aviv'),(294,'Asia/Thimbu'),(295,'Asia/Thimphu'),(296,'Asia/Tokyo'),(297,'Asia/Ujung_Pandang'),(298,'Asia/Ulaanbaatar'),(299,'Asia/Ulan_Bator'),(300,'Asia/Urumqi'),(301,'Asia/Vientiane'),(302,'Asia/Vladivostok'),(303,'Asia/Yakutsk'),(304,'Asia/Yekaterinburg'),(305,'Asia/Yerevan'),(306,'Atlantic/Azores'),(307,'Atlantic/Bermuda'),(308,'Atlantic/Canary'),(309,'Atlantic/Cape_Verde'),(310,'Atlantic/Faeroe'),(311,'Atlantic/Faroe'),(312,'Atlantic/Jan_Mayen'),(313,'Atlantic/Madeira'),(314,'Atlantic/Reykjavik'),(315,'Atlantic/South_Georgia'),(316,'Atlantic/St_Helena'),(317,'Atlantic/Stanley'),(318,'Australia/ACT'),(319,'Australia/Adelaide'),(320,'Australia/Brisbane'),(321,'Australia/Broken_Hill'),(322,'Australia/Canberra'),(323,'Australia/Currie'),(324,'Australia/Darwin'),(325,'Australia/Eucla'),(326,'Australia/Hobart'),(327,'Australia/LHI'),(328,'Australia/Lindeman'),(329,'Australia/Lord_Howe'),(330,'Australia/Melbourne'),(331,'Australia/North'),(332,'Australia/NSW'),(333,'Australia/Perth'),(334,'Australia/Queensland'),(335,'Australia/South'),(336,'Australia/Sydney'),(337,'Australia/Tasmania'),(338,'Australia/Victoria'),(339,'Australia/West'),(340,'Australia/Yancowinna'),(341,'Europe/Amsterdam'),(342,'Europe/Andorra'),(343,'Europe/Athens'),(344,'Europe/Belfast'),(345,'Europe/Belgrade'),(346,'Europe/Berlin'),(347,'Europe/Bratislava'),(348,'Europe/Brussels'),(349,'Europe/Bucharest'),(350,'Europe/Budapest'),(351,'Europe/Chisinau'),(352,'Europe/Copenhagen'),(353,'Europe/Dublin'),(354,'Europe/Gibraltar'),(355,'Europe/Guernsey'),(356,'Europe/Helsinki'),(357,'Europe/Isle_of_Man'),(358,'Europe/Istanbul'),(359,'Europe/Jersey'),(360,'Europe/Kaliningrad'),(361,'Europe/Kiev'),(362,'Europe/Lisbon'),(363,'Europe/Ljubljana'),(364,'Europe/London'),(365,'Europe/Luxembourg'),(366,'Europe/Madrid'),(367,'Europe/Malta'),(368,'Europe/Mariehamn'),(369,'Europe/Minsk'),(370,'Europe/Monaco'),(371,'Europe/Moscow'),(372,'Europe/Nicosia'),(373,'Europe/Oslo'),(374,'Europe/Paris'),(375,'Europe/Podgorica'),(376,'Europe/Prague'),(377,'Europe/Riga'),(378,'Europe/Rome'),(379,'Europe/Samara'),(380,'Europe/San_Marino'),(381,'Europe/Sarajevo'),(382,'Europe/Simferopol'),(383,'Europe/Skopje'),(384,'Europe/Sofia'),(385,'Europe/Stockholm'),(386,'Europe/Tallinn'),(387,'Europe/Tirane'),(388,'Europe/Tiraspol'),(389,'Europe/Uzhgorod'),(390,'Europe/Vaduz'),(391,'Europe/Vatican'),(392,'Europe/Vienna'),(393,'Europe/Vilnius'),(394,'Europe/Volgograd'),(395,'Europe/Warsaw'),(396,'Europe/Zagreb'),(397,'Europe/Zaporozhye'),(398,'Europe/Zurich'),(399,'Indian/Antananarivo'),(400,'Indian/Chagos'),(401,'Indian/Christmas'),(402,'Indian/Cocos'),(403,'Indian/Comoro'),(404,'Indian/Kerguelen'),(405,'Indian/Mahe'),(406,'Indian/Maldives'),(407,'Indian/Mauritius'),(408,'Indian/Mayotte'),(409,'Indian/Reunion'),(410,'Pacific/Apia'),(411,'Pacific/Auckland'),(412,'Pacific/Chatham'),(413,'Pacific/Easter'),(414,'Pacific/Efate'),(415,'Pacific/Enderbury'),(416,'Pacific/Fakaofo'),(417,'Pacific/Fiji'),(418,'Pacific/Funafuti'),(419,'Pacific/Galapagos'),(420,'Pacific/Gambier'),(421,'Pacific/Guadalcanal'),(422,'Pacific/Guam'),(423,'Pacific/Honolulu'),(424,'Pacific/Johnston'),(425,'Pacific/Kiritimati'),(426,'Pacific/Kosrae'),(427,'Pacific/Kwajalein'),(428,'Pacific/Majuro'),(429,'Pacific/Marquesas'),(430,'Pacific/Midway'),(431,'Pacific/Nauru'),(432,'Pacific/Niue'),(433,'Pacific/Norfolk'),(434,'Pacific/Noumea'),(435,'Pacific/Pago_Pago'),(436,'Pacific/Palau'),(437,'Pacific/Pitcairn'),(438,'Pacific/Ponape'),(439,'Pacific/Port_Moresby'),(440,'Pacific/Rarotonga'),(441,'Pacific/Saipan'),(442,'Pacific/Samoa'),(443,'Pacific/Tahiti'),(444,'Pacific/Tarawa'),(445,'Pacific/Tongatapu'),(446,'Pacific/Truk'),(447,'Pacific/Wake'),(448,'Pacific/Wallis'),(449,'Pacific/Yap'),(450,'Brazil/Acre'),(451,'Brazil/DeNoronha'),(452,'Brazil/East'),(453,'Brazil/West'),(454,'Canada/Atlantic'),(455,'Canada/Central'),(456,'Canada/East-Saskatchewan'),(457,'Canada/Eastern'),(458,'Canada/Mountain'),(459,'Canada/Newfoundland'),(460,'Canada/Pacific'),(461,'Canada/Saskatchewan'),(462,'Canada/Yukon'),(463,'CET'),(464,'Chile/Continental'),(465,'Chile/EasterIsland'),(466,'CST6CDT'),(467,'Cuba'),(468,'EET'),(469,'Egypt'),(470,'Eire'),(471,'EST'),(472,'EST5EDT'),(473,'Etc/GMT'),(474,'Etc/GMT+0'),(475,'Etc/GMT+1'),(476,'Etc/GMT+10'),(477,'Etc/GMT+11'),(478,'Etc/GMT+12'),(479,'Etc/GMT+2'),(480,'Etc/GMT+3'),(481,'Etc/GMT+4'),(482,'Etc/GMT+5'),(483,'Etc/GMT+6'),(484,'Etc/GMT+7'),(485,'Etc/GMT+8'),(486,'Etc/GMT+9'),(487,'Etc/GMT-0'),(488,'Etc/GMT-1'),(489,'Etc/GMT-10'),(490,'Etc/GMT-11'),(491,'Etc/GMT-12'),(492,'Etc/GMT-13'),(493,'Etc/GMT-14'),(494,'Etc/GMT-2'),(495,'Etc/GMT-3'),(496,'Etc/GMT-4'),(497,'Etc/GMT-5'),(498,'Etc/GMT-6'),(499,'Etc/GMT-7'),(500,'Etc/GMT-8'),(501,'Etc/GMT-9'),(502,'Etc/GMT0'),(503,'Etc/Greenwich'),(504,'Etc/UCT'),(505,'Etc/Universal'),(506,'Etc/UTC'),(507,'Etc/Zulu'),(508,'Factory'),(509,'GB'),(510,'GB-Eire'),(511,'GMT'),(512,'GMT+0'),(513,'GMT-0'),(514,'GMT0'),(515,'Greenwich'),(516,'Hongkong'),(517,'HST'),(518,'Iceland'),(519,'Iran'),(520,'Israel'),(521,'Jamaica'),(522,'Japan'),(523,'Kwajalein'),(524,'Libya'),(525,'MET'),(526,'Mexico/BajaNorte'),(527,'Mexico/BajaSur'),(528,'Mexico/General'),(529,'MST'),(530,'MST7MDT'),(531,'Navajo'),(532,'NZ'),(533,'NZ-CHAT'),(534,'Poland'),(535,'Portugal'),(536,'PRC'),(537,'PST8PDT'),(538,'ROC'),(539,'ROK'),(540,'Singapore'),(541,'Turkey'),(542,'UCT'),(543,'Universal'),(544,'US/Alaska'),(545,'US/Aleutian'),(546,'US/Arizona'),(547,'US/Central'),(548,'US/East-Indiana'),(549,'US/Eastern'),(550,'US/Hawaii'),(551,'US/Indiana-Starke'),(552,'US/Michigan'),(553,'US/Mountain'),(554,'US/Pacific'),(555,'US/Pacific-New'),(556,'US/Samoa'),(557,'UTC'),(558,'W-SU'),(559,'WET'),(560,'Zulu');
/*!40000 ALTER TABLE `qlo_timezone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_warehouse`
--

DROP TABLE IF EXISTS `qlo_warehouse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_warehouse` (
  `id_warehouse` int unsigned NOT NULL AUTO_INCREMENT,
  `id_currency` int unsigned NOT NULL,
  `id_address` int unsigned NOT NULL,
  `id_employee` int unsigned NOT NULL,
  `reference` varchar(32) DEFAULT NULL,
  `name` varchar(45) NOT NULL,
  `management_type` enum('WA','FIFO','LIFO') NOT NULL DEFAULT 'WA',
  `deleted` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_warehouse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_warehouse`
--

LOCK TABLES `qlo_warehouse` WRITE;
/*!40000 ALTER TABLE `qlo_warehouse` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_warehouse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_warehouse_carrier`
--

DROP TABLE IF EXISTS `qlo_warehouse_carrier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_warehouse_carrier` (
  `id_carrier` int unsigned NOT NULL,
  `id_warehouse` int unsigned NOT NULL,
  PRIMARY KEY (`id_warehouse`,`id_carrier`),
  KEY `id_warehouse` (`id_warehouse`),
  KEY `id_carrier` (`id_carrier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_warehouse_carrier`
--

LOCK TABLES `qlo_warehouse_carrier` WRITE;
/*!40000 ALTER TABLE `qlo_warehouse_carrier` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_warehouse_carrier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_warehouse_product_location`
--

DROP TABLE IF EXISTS `qlo_warehouse_product_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_warehouse_product_location` (
  `id_warehouse_product_location` int unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int unsigned NOT NULL,
  `id_product_attribute` int unsigned NOT NULL,
  `id_warehouse` int unsigned NOT NULL,
  `location` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id_warehouse_product_location`),
  UNIQUE KEY `id_product` (`id_product`,`id_product_attribute`,`id_warehouse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_warehouse_product_location`
--

LOCK TABLES `qlo_warehouse_product_location` WRITE;
/*!40000 ALTER TABLE `qlo_warehouse_product_location` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_warehouse_product_location` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_warehouse_shop`
--

DROP TABLE IF EXISTS `qlo_warehouse_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_warehouse_shop` (
  `id_shop` int unsigned NOT NULL,
  `id_warehouse` int unsigned NOT NULL,
  PRIMARY KEY (`id_warehouse`,`id_shop`),
  KEY `id_warehouse` (`id_warehouse`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_warehouse_shop`
--

LOCK TABLES `qlo_warehouse_shop` WRITE;
/*!40000 ALTER TABLE `qlo_warehouse_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_warehouse_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_web_browser`
--

DROP TABLE IF EXISTS `qlo_web_browser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_web_browser` (
  `id_web_browser` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id_web_browser`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_web_browser`
--

LOCK TABLES `qlo_web_browser` WRITE;
/*!40000 ALTER TABLE `qlo_web_browser` DISABLE KEYS */;
INSERT INTO `qlo_web_browser` VALUES (1,'Safari'),(2,'Safari iPad'),(3,'Firefox'),(4,'Opera'),(5,'IE 6'),(6,'IE 7'),(7,'IE 8'),(8,'IE 9'),(9,'IE 10'),(10,'IE 11'),(11,'Chrome');
/*!40000 ALTER TABLE `qlo_web_browser` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_webservice_account`
--

DROP TABLE IF EXISTS `qlo_webservice_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_webservice_account` (
  `id_webservice_account` int NOT NULL AUTO_INCREMENT,
  `key` varchar(32) NOT NULL,
  `description` text,
  `class_name` varchar(50) NOT NULL DEFAULT 'WebserviceRequest',
  `is_module` tinyint NOT NULL DEFAULT '0',
  `module_name` varchar(50) DEFAULT NULL,
  `active` tinyint NOT NULL,
  PRIMARY KEY (`id_webservice_account`),
  KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_webservice_account`
--

LOCK TABLES `qlo_webservice_account` WRITE;
/*!40000 ALTER TABLE `qlo_webservice_account` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_webservice_account` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_webservice_account_shop`
--

DROP TABLE IF EXISTS `qlo_webservice_account_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_webservice_account_shop` (
  `id_webservice_account` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_webservice_account`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_webservice_account_shop`
--

LOCK TABLES `qlo_webservice_account_shop` WRITE;
/*!40000 ALTER TABLE `qlo_webservice_account_shop` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_webservice_account_shop` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_webservice_permission`
--

DROP TABLE IF EXISTS `qlo_webservice_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_webservice_permission` (
  `id_webservice_permission` int NOT NULL AUTO_INCREMENT,
  `resource` varchar(50) NOT NULL,
  `method` enum('GET','POST','PUT','DELETE','HEAD') NOT NULL,
  `id_webservice_account` int NOT NULL,
  PRIMARY KEY (`id_webservice_permission`),
  UNIQUE KEY `resource_2` (`resource`,`method`,`id_webservice_account`),
  KEY `resource` (`resource`),
  KEY `method` (`method`),
  KEY `id_webservice_account` (`id_webservice_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_webservice_permission`
--

LOCK TABLES `qlo_webservice_permission` WRITE;
/*!40000 ALTER TABLE `qlo_webservice_permission` DISABLE KEYS */;
/*!40000 ALTER TABLE `qlo_webservice_permission` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_zone`
--

DROP TABLE IF EXISTS `qlo_zone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_zone` (
  `id_zone` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `active` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_zone`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_zone`
--

LOCK TABLES `qlo_zone` WRITE;
/*!40000 ALTER TABLE `qlo_zone` DISABLE KEYS */;
INSERT INTO `qlo_zone` VALUES (1,'Europe',1),(2,'North America',1),(3,'Asia',1),(4,'Africa',1),(5,'Oceania',1),(6,'South America',1),(7,'Europe (non-EU)',1),(8,'Central America/Antilla',1);
/*!40000 ALTER TABLE `qlo_zone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qlo_zone_shop`
--

DROP TABLE IF EXISTS `qlo_zone_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qlo_zone_shop` (
  `id_zone` int unsigned NOT NULL,
  `id_shop` int unsigned NOT NULL,
  PRIMARY KEY (`id_zone`,`id_shop`),
  KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qlo_zone_shop`
--

LOCK TABLES `qlo_zone_shop` WRITE;
/*!40000 ALTER TABLE `qlo_zone_shop` DISABLE KEYS */;
INSERT INTO `qlo_zone_shop` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1);
/*!40000 ALTER TABLE `qlo_zone_shop` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-03 13:39:14
