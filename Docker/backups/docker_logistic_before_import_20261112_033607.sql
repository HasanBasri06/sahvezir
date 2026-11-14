-- MySQL dump 10.13  Distrib 8.4.13, for Linux (x86_64)
--
-- Host: localhost    Database: logistic
-- ------------------------------------------------------
-- Server version	8.4.13

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
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `car_detail`
--

DROP TABLE IF EXISTS `car_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `car_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `car_id` bigint NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_detail`
--

LOCK TABLES `car_detail` WRITE;
/*!40000 ALTER TABLE `car_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `car_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cards`
--

DROP TABLE IF EXISTS `cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `card_holder_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_four` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exp_month` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exp_year` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_card_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cards`
--

LOCK TABLES `cards` WRITE;
/*!40000 ALTER TABLE `cards` DISABLE KEYS */;
/*!40000 ALTER TABLE `cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cars`
--

DROP TABLE IF EXISTS `cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` double DEFAULT NULL,
  `cost_per_km` double DEFAULT NULL,
  `width` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `status` enum('active','inactive','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cars`
--

LOCK TABLES `cars` WRITE;
/*!40000 ALTER TABLE `cars` DISABLE KEYS */;
/*!40000 ALTER TABLE `cars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cities`
--

LOCK TABLES `cities` WRITE;
/*!40000 ALTER TABLE `cities` DISABLE KEYS */;
/*!40000 ALTER TABLE `cities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `creator_user_id` bigint unsigned DEFAULT NULL,
  `sender_user_id` int NOT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` int NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_creator_user_id_foreign` (`creator_user_id`),
  CONSTRAINT `comments_creator_user_id_foreign` FOREIGN KEY (`creator_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `districts`
--

DROP TABLE IF EXISTS `districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `districts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `city_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `districts`
--

LOCK TABLES `districts` WRITE;
/*!40000 ALTER TABLE `districts` DISABLE KEYS */;
/*!40000 ALTER TABLE `districts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `load_alarms`
--

DROP TABLE IF EXISTS `load_alarms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `load_alarms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `f_city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `load_alarms_user_id_index` (`user_id`),
  KEY `load_alarms_is_active_f_city_t_city_index` (`is_active`,`f_city`,`t_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `load_alarms`
--

LOCK TABLES `load_alarms` WRITE;
/*!40000 ALTER TABLE `load_alarms` DISABLE KEYS */;
/*!40000 ALTER TABLE `load_alarms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message`
--

DROP TABLE IF EXISTS `message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned NOT NULL,
  `receiver_id` bigint unsigned NOT NULL,
  `shipment_id` bigint unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','accepted','rejected','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accepted',
  `type` enum('normal','system','request') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sender_message` varchar(155) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_message` varchar(155) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message`
--

LOCK TABLES `message` WRITE;
/*!40000 ALTER TABLE `message` DISABLE KEYS */;
/*!40000 ALTER TABLE `message` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_01_20_000001_create_users_detail_table',1),(5,'2025_01_20_000003_create_shipments_table',1),(6,'2025_01_20_000004_create_shipments_order_table',1),(7,'2025_01_20_000005_create_cars_table',1),(8,'2025_01_20_000006_create_user_cars_table',1),(9,'2025_01_20_000007_create_requests_table',1),(10,'2026_02_07_170017_create_car_detail',1),(11,'2026_02_08_000001_create_cities_table',1),(12,'2026_02_08_000002_create_districts_table',1),(13,'2026_02_15_000001_create_post_type',1),(14,'2026_02_18_144452_create_comments',1),(15,'2026_02_19_141015_create_message',1),(16,'2026_02_19_150000_add_shipment_id_to_message_table',1),(17,'2026_02_19_160000_make_shipment_id_nullable_on_message',1),(18,'2026_02_26_000001_add_status_to_requests_table',1),(19,'2026_02_26_000002_add_status_to_shipments_table',1),(20,'2026_03_04_142604_create_personal_access_tokens_table',1),(21,'2026_03_07_000001_create_addresses_table',1),(22,'2026_03_07_231051_create_cards',1),(23,'2026_03_08_200000_add_image_to_car_detail_table',1),(24,'2026_03_12_000001_add_width_height_to_cars_table',1),(25,'2026_03_15_000001_add_email_verify_to_users_table',1),(26,'2026_03_16_000001_add_panel_tour_completed_at_to_users_table',1),(27,'2026_03_26_000002_add_phone_verify_to_users_table',1),(28,'2026_03_30_120000_add_creator_user_id_to_comments_table',1),(29,'2026_04_04_120000_add_type_to_message_table',1),(30,'2026_05_25_000001_create_settings_table',1),(31,'2026_05_27_000001_add_suitable_postings_to_users_table',1),(32,'2026_05_27_223952_create_user_devices',1),(33,'2026_05_28_000001_drop_push_columns_from_users_table',1),(34,'2026_06_03_223629_create_user_orders',1),(35,'2026_06_04_120000_add_request_type_to_message_table',1),(36,'2026_06_04_120000_user_devices_nullable_and_deny_status',1),(37,'2026_06_05_120000_settings_ios_android_versions',1),(38,'2026_06_12_000001_add_exact_location_to_users_detail_table',1),(39,'2026_06_16_000002_drop_shipment_call_agreements_table',1),(40,'2026_06_16_100000_create_shipment_call_requests_table',1),(41,'2026_06_17_100000_add_unique_shipment_call_requests_triplet',1),(42,'2026_06_17_110000_drop_listing_taken_from_shipment_call_requests',1),(43,'2026_06_19_120000_add_paytr_fields_to_user_orders_table',1),(44,'2026_06_27_173725_create_admin',1),(45,'2026_06_27_200000_add_with_admin_to_users_table',1),(46,'2026_07_02_000002_add_images_to_user_cars_table',1),(47,'2026_07_02_000003_add_explanation_to_shipments_table',1),(48,'2026_07_02_000004_move_user_car_images_to_verification_folder',1),(49,'2026_07_09_000001_create_search_analysis_table',1),(50,'2026_07_10_000001_add_is_kdv_to_shipments_table',1),(51,'2026_07_10_000002_add_part_load_to_shipments_table',1),(52,'2026_07_10_000003_create_shipment_attachments_table',1),(53,'2026_10_22_000001_create_shipment_cars_table',1),(54,'2026_11_01_000001_create_load_alarms_table',1),(55,'2026_11_01_000002_add_google_id_to_users_table',1),(56,'2026_11_01_000003_restore_missing_catalog_vehicle_images',1),(57,'2026_11_01_000004_add_capacity_to_user_cars_table',1),(58,'2026_11_02_000001_add_user_car_id_to_shipments_order_table',1),(59,'2026_11_02_000002_add_user_car_id_to_requests_table',1),(60,'2026_11_02_000003_add_started_at_to_shipments_order_table',1),(61,'2026_11_02_000004_add_progress_to_shipments_order_table',1),(62,'2026_11_04_000001_add_is_multi_cargo_owner_to_users_table',1),(64,'2026_11_04_000002_create_user_subscriptions_table',1),(65,'2026_11_06_000001_add_recurring_billing_columns',1),(66,'2026_11_06_000002_add_package_tier_columns',1),(67,'2026_11_07_000001_add_driver_delegation',1),(68,'2026_11_07_000002_add_live_location_to_shipments_order',1),(69,'2026_11_04_000001_restore_legacy_schema',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'App\\Models\\User',1,'api:user:vehicle_owner','8ba2c41c004c098dbffeb8daccc5af98718dd20d6d1db72268824d9f26dcd348','[\"*\"]','2026-11-12 00:35:37',NULL,'2026-11-12 00:34:16','2026-11-12 00:35:37');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_types`
--

DROP TABLE IF EXISTS `post_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_types`
--

LOCK TABLES `post_types` WRITE;
/*!40000 ALTER TABLE `post_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `car_type` int NOT NULL,
  `user_car_id` bigint unsigned DEFAULT NULL,
  `price` double NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','accepted','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requests`
--

LOCK TABLES `requests` WRITE;
/*!40000 ALTER TABLE `requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` enum('vehicle_owner','cargo_owner') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `search_analysis`
--

DROP TABLE IF EXISTS `search_analysis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_analysis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calling_user_id` bigint unsigned NOT NULL,
  `f_where_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_where_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_where_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_where_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `search_analysis_calling_user_id_foreign` (`calling_user_id`),
  CONSTRAINT `search_analysis_calling_user_id_foreign` FOREIGN KEY (`calling_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_analysis`
--

LOCK TABLES `search_analysis` WRITE;
/*!40000 ALTER TABLE `search_analysis` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_analysis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ios_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `android_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_attachments`
--

DROP TABLE IF EXISTS `shipment_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `creator_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_attachments_shipment_id_index` (`shipment_id`),
  KEY `shipment_attachments_creator_id_index` (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_attachments`
--

LOCK TABLES `shipment_attachments` WRITE;
/*!40000 ALTER TABLE `shipment_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipment_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_call_requests`
--

DROP TABLE IF EXISTS `shipment_call_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_call_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `vehicle_owner_id` bigint unsigned NOT NULL,
  `cargo_owner_id` bigint unsigned NOT NULL,
  `caller_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `called_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_call_requests_shipment_vehicle_cargo_unique` (`shipment_id`,`vehicle_owner_id`,`cargo_owner_id`),
  KEY `shipment_call_requests_vehicle_owner_id_foreign` (`vehicle_owner_id`),
  KEY `shipment_call_requests_cargo_owner_id_foreign` (`cargo_owner_id`),
  KEY `shipment_call_requests_shipment_id_called_at_index` (`shipment_id`,`called_at`),
  CONSTRAINT `shipment_call_requests_cargo_owner_id_foreign` FOREIGN KEY (`cargo_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_call_requests_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_call_requests_vehicle_owner_id_foreign` FOREIGN KEY (`vehicle_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_call_requests`
--

LOCK TABLES `shipment_call_requests` WRITE;
/*!40000 ALTER TABLE `shipment_call_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipment_call_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_cars`
--

DROP TABLE IF EXISTS `shipment_cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_cars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `car_id` bigint unsigned NOT NULL,
  `car_detail_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_cars_shipment_id_car_id_unique` (`shipment_id`,`car_id`),
  KEY `shipment_cars_car_id_index` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_cars`
--

LOCK TABLES `shipment_cars` WRITE;
/*!40000 ALTER TABLE `shipment_cars` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipment_cars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipments`
--

DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XFnSPCzhkJAFDkHR7dfsr57YY',
  `call_verify` tinyint(1) NOT NULL DEFAULT '0',
  `f_where_city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `t_where_city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_where_district` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `t_where_district` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hours` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departure_time` time DEFAULT NULL,
  `time_arrival` time DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `weight` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci,
  `creater_id` bigint unsigned NOT NULL,
  `car_id` bigint unsigned NOT NULL,
  `car_detail_id` bigint unsigned DEFAULT NULL,
  `post_type_id` bigint unsigned NOT NULL,
  `call_access` tinyint NOT NULL DEFAULT '0',
  `price` double NOT NULL,
  `is_kdv` tinyint(1) NOT NULL DEFAULT '0',
  `part_load` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive','pending','done','canceled','accepted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `code` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipments_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipments`
--

LOCK TABLES `shipments` WRITE;
/*!40000 ALTER TABLE `shipments` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipments_order`
--

DROP TABLE IF EXISTS `shipments_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creater_user` bigint unsigned NOT NULL,
  `buyer_user` bigint unsigned NOT NULL,
  `driver_user_id` bigint unsigned DEFAULT NULL,
  `user_car_id` bigint unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `progress` decimal(5,1) DEFAULT NULL,
  `current_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress_updated_at` timestamp NULL DEFAULT NULL,
  `last_lat` decimal(10,7) DEFAULT NULL,
  `last_lng` decimal(10,7) DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `shipment_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipments_order_driver_user_id_index` (`driver_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipments_order`
--

LOCK TABLES `shipments_order` WRITE;
/*!40000 ALTER TABLE `shipments_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipments_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_cars`
--

DROP TABLE IF EXISTS `user_cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_cars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `car_id` bigint unsigned NOT NULL,
  `car_detail_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `plate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity_kg` int unsigned DEFAULT NULL,
  `volume_m3` decimal(6,2) DEFAULT NULL,
  `image_one` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_two` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_cars_user_id_foreign` (`user_id`),
  CONSTRAINT `user_cars_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_cars`
--

LOCK TABLES `user_cars` WRITE;
/*!40000 ALTER TABLE `user_cars` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_cars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_devices`
--

DROP TABLE IF EXISTS `user_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `platform` enum('android','ios') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expo_push_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','deny') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_devices_user_id_foreign` (`user_id`),
  CONSTRAINT `user_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_devices`
--

LOCK TABLES `user_devices` WRITE;
/*!40000 ALTER TABLE `user_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_orders`
--

DROP TABLE IF EXISTS `user_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `order_type` enum('package','vehicle','renewal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'package',
  `user_car_id` bigint unsigned DEFAULT NULL,
  `renews_subscription_id` bigint unsigned DEFAULT NULL,
  `package_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_tier` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `merchant_oid` varchar(70) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_months` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_orders_user_id_foreign` (`user_id`),
  CONSTRAINT `user_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_orders`
--

LOCK TABLES `user_orders` WRITE;
/*!40000 ALTER TABLE `user_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_subscriptions`
--

DROP TABLE IF EXISTS `user_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` enum('package','vehicle') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'package',
  `user_car_id` bigint unsigned DEFAULT NULL,
  `user_order_id` bigint unsigned DEFAULT NULL,
  `source` enum('payment','trial','admin','legacy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment',
  `package_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_tier` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_limit` smallint unsigned DEFAULT NULL,
  `plan_months` smallint unsigned DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `auto_renew` tinyint(1) NOT NULL DEFAULT '1',
  `renew_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `last_renew_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_renew_attempt_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_subscriptions_user_order_id_unique` (`user_order_id`),
  KEY `user_subscriptions_user_id_status_index` (`user_id`,`status`),
  KEY `user_subscriptions_status_ends_at_index` (`status`,`ends_at`),
  KEY `user_subscriptions_type_status_ends_at_index` (`type`,`status`,`ends_at`),
  KEY `user_subscriptions_user_car_id_status_index` (`user_car_id`,`status`),
  CONSTRAINT `user_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_subscriptions`
--

LOCK TABLES `user_subscriptions` WRITE;
/*!40000 ALTER TABLE `user_subscriptions` DISABLE KEYS */;
INSERT INTO `user_subscriptions` VALUES (1,1,'package',NULL,NULL,'trial','Deneme süresi',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-12 03:34:16','2026-12-12 03:34:16','active',1,0,NULL,NULL,'2026-11-12 00:34:16','2026-11-12 00:34:16');
/*!40000 ALTER TABLE `user_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` enum('pending','active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified` tinyint NOT NULL DEFAULT '0',
  `with_admin` tinyint NOT NULL DEFAULT '0',
  `driving_licance_doc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `psikoteknik_doc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `src_doc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('vehicle_owner','cargo_owner','driver') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_user_id` bigint unsigned DEFAULT NULL,
  `is_multi_cargo_owner` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `suitable_postings` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_verify` tinyint NOT NULL DEFAULT '0',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verify` tinyint(1) NOT NULL DEFAULT '0',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verify_account` tinyint(1) NOT NULL DEFAULT '0',
  `sms_verified_at` timestamp NULL DEFAULT NULL,
  `payment_confirm` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel_tour_completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `end_payment_date` varchar(155) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paytr_utoken` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `users_parent_user_id_index` (`parent_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'active',0,0,NULL,NULL,NULL,NULL,NULL,'vehicle_owner',NULL,'no','no',NULL,'Hasan Basri','Akcıl','5061192539',1,'basriakcil06@gmail.com',NULL,0,'$2y$12$51n0YFKbf3cE2m7uuzvbteJBYYirYYarjuIaSYjEC0vJiflp5.hmS',NULL,0,NULL,1,NULL,NULL,'2026-11-12 00:33:48','2026-11-12 00:34:16','2026-12-12 03:34:16',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_detail`
--

DROP TABLE IF EXISTS `users_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exact_location` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_detail`
--

LOCK TABLES `users_detail` WRITE;
/*!40000 ALTER TABLE `users_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `users_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'logistic'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-12  3:36:08
