-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: ai_counsellor
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `ai_providers`
--

DROP TABLE IF EXISTS `ai_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `supports_tools` tinyint(1) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_providers_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_providers`
--

LOCK TABLES `ai_providers` WRITE;
/*!40000 ALTER TABLE `ai_providers` DISABLE KEYS */;
INSERT INTO `ai_providers` VALUES (1,'openai','OpenAI',0,1,'2026-06-15 09:02:21','2026-06-15 09:02:21'),(2,'fake','Fake (tests)',0,1,'2026-06-15 10:02:17','2026-06-15 10:02:17');
/*!40000 ALTER TABLE `ai_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_runs`
--

DROP TABLE IF EXISTS `ai_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `triggering_message_id` bigint(20) unsigned DEFAULT NULL,
  `message_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(40) NOT NULL,
  `model` varchar(120) NOT NULL,
  `credential_source` varchar(32) DEFAULT NULL,
  `input_tokens` int(10) unsigned DEFAULT NULL,
  `output_tokens` int(10) unsigned DEFAULT NULL,
  `total_tokens` int(10) unsigned DEFAULT NULL,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `purpose` varchar(32) NOT NULL DEFAULT 'response',
  `error_category` varchar(64) DEFAULT NULL,
  `attempt_number` smallint(5) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_runs_tenant_request_uuid_unique` (`tenant_id`,`request_uuid`),
  KEY `ai_runs_conversation_id_foreign` (`conversation_id`),
  KEY `ai_runs_message_id_foreign` (`message_id`),
  KEY `ai_runs_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `ai_runs_tenant_id_conversation_id_index` (`tenant_id`,`conversation_id`),
  KEY `ai_runs_triggering_status_index` (`triggering_message_id`,`status`),
  CONSTRAINT `ai_runs_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `ai_runs_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_runs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `ai_runs_triggering_message_id_foreign` FOREIGN KEY (`triggering_message_id`) REFERENCES `messages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_runs`
--

LOCK TABLES `ai_runs` WRITE;
/*!40000 ALTER TABLE `ai_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `audit_logs_tenant_id_action_index` (`tenant_id`,`action`),
  KEY `audit_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  CONSTRAINT `audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,1,'tenant.created','App\\Models\\Tenant',1,'{\"name\":\"Demo Counselling\",\"slug\":\"demo-counselling\"}','127.0.0.1','2026-06-15 05:07:36'),(2,1,1,'membership.created','App\\Models\\TenantMembership',1,'{\"role\":\"owner\",\"user_id\":2}','127.0.0.1','2026-06-15 05:07:36'),(3,1,1,'tenant.activated','App\\Models\\Tenant',1,NULL,'127.0.0.1','2026-06-15 05:07:36'),(4,1,1,'subscription.assigned','App\\Models\\Subscription',1,'{\"plan_code\":\"trial\",\"status\":\"active\",\"actor_scope\":\"platform\"}','127.0.0.1','2026-06-15 13:54:51'),(5,2,4,'tenant.created','App\\Models\\Tenant',2,'{\"name\":\"Dr sSS\",\"slug\":\"dr-ss\",\"actor_scope\":\"platform\"}','127.0.0.1','2026-06-15 13:54:52'),(6,2,4,'membership.created','App\\Models\\TenantMembership',2,'{\"target_user_id\":5,\"before\":[],\"after\":{\"membership_id\":2,\"user_id\":5,\"role\":\"owner\",\"status\":\"active\",\"is_owner\":true},\"actor_scope\":\"platform\"}','127.0.0.1','2026-06-15 13:54:52'),(7,2,4,'tenant.activated','App\\Models\\Tenant',2,'{\"actor_scope\":\"platform\"}','127.0.0.1','2026-06-15 13:59:21');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('ai-counsellor-cache-1b6453892473a467d07372d45eb05abc2031647a','i:1;',1781516811),('ai-counsellor-cache-1b6453892473a467d07372d45eb05abc2031647a:timer','i:1781516811;',1781516811),('ai-counsellor-cache-ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4','i:1;',1781532080),('ai-counsellor-cache-ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4:timer','i:1781532080;',1781532080);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
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
-- Table structure for table `conversation_activities`
--

DROP TABLE IF EXISTS `conversation_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `action_type` varchar(40) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `previous_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conversation_activities_conversation_id_foreign` (`conversation_id`),
  KEY `conversation_activities_actor_user_id_foreign` (`actor_user_id`),
  KEY `conv_activities_tenant_conv_created_idx` (`tenant_id`,`conversation_id`,`created_at`),
  CONSTRAINT `conversation_activities_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_activities_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `conversation_activities_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_activities`
--

LOCK TABLES `conversation_activities` WRITE;
/*!40000 ALTER TABLE `conversation_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_handoffs`
--

DROP TABLE IF EXISTS `conversation_handoffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_handoffs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `counsellor_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_handoffs_conversation_id_foreign` (`conversation_id`),
  KEY `conversation_handoffs_counsellor_id_foreign` (`counsellor_id`),
  KEY `conversation_handoffs_assigned_by_foreign` (`assigned_by`),
  KEY `conversation_handoffs_tenant_id_conversation_id_is_current_index` (`tenant_id`,`conversation_id`,`is_current`),
  KEY `conversation_handoffs_tenant_id_counsellor_id_is_current_index` (`tenant_id`,`counsellor_id`,`is_current`),
  CONSTRAINT `conversation_handoffs_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_handoffs_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `conversation_handoffs_counsellor_id_foreign` FOREIGN KEY (`counsellor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `conversation_handoffs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_handoffs`
--

LOCK TABLES `conversation_handoffs` WRITE;
/*!40000 ALTER TABLE `conversation_handoffs` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_handoffs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_read_states`
--

DROP TABLE IF EXISTS `conversation_read_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_read_states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_read_states_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_read_states_user_id_foreign` (`user_id`),
  KEY `conversation_read_states_last_read_message_id_foreign` (`last_read_message_id`),
  KEY `conversation_read_states_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  CONSTRAINT `conversation_read_states_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `conversation_read_states_last_read_message_id_foreign` FOREIGN KEY (`last_read_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_read_states_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `conversation_read_states_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_read_states`
--

LOCK TABLES `conversation_read_states` WRITE;
/*!40000 ALTER TABLE `conversation_read_states` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_read_states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `visitor_id` bigint(20) unsigned NOT NULL,
  `messaging_integration_id` bigint(20) unsigned DEFAULT NULL,
  `messaging_contact_id` bigint(20) unsigned DEFAULT NULL,
  `external_channel_reference` varchar(128) DEFAULT NULL,
  `last_inbound_provider_message_id` varchar(255) DEFAULT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `human_owner_id` bigint(20) unsigned DEFAULT NULL,
  `target_counsellor_id` bigint(20) unsigned DEFAULT NULL,
  `handoff_request_uuid` char(36) DEFAULT NULL,
  `handoff_requested_at` timestamp NULL DEFAULT NULL,
  `human_takeover_at` timestamp NULL DEFAULT NULL,
  `human_released_at` timestamp NULL DEFAULT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'widget',
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `mode` varchar(32) NOT NULL DEFAULT 'ai',
  `source_url` varchar(2048) DEFAULT NULL,
  `origin_domain` varchar(255) DEFAULT NULL,
  `locale` varchar(12) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_visitor_message_at` timestamp NULL DEFAULT NULL,
  `last_human_message_at` timestamp NULL DEFAULT NULL,
  `counsellor_unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `visitor_last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `close_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_uuid_unique` (`uuid`),
  UNIQUE KEY `conversations_tenant_handoff_request_unique` (`tenant_id`,`handoff_request_uuid`),
  KEY `conversations_visitor_id_foreign` (`visitor_id`),
  KEY `conversations_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `conversations_tenant_id_visitor_id_index` (`tenant_id`,`visitor_id`),
  KEY `conversations_lead_id_foreign` (`lead_id`),
  KEY `conversations_human_owner_id_foreign` (`human_owner_id`),
  KEY `conversations_target_counsellor_id_foreign` (`target_counsellor_id`),
  KEY `conversations_visitor_last_read_message_id_foreign` (`visitor_last_read_message_id`),
  KEY `conversations_tenant_id_mode_index` (`tenant_id`,`mode`),
  KEY `conversations_tenant_id_human_owner_id_mode_index` (`tenant_id`,`human_owner_id`,`mode`),
  KEY `conversations_tenant_id_target_counsellor_id_mode_index` (`tenant_id`,`target_counsellor_id`,`mode`),
  KEY `conversations_tenant_id_handoff_requested_at_index` (`tenant_id`,`handoff_requested_at`),
  KEY `conversations_messaging_integration_id_foreign` (`messaging_integration_id`),
  KEY `conversations_messaging_contact_id_foreign` (`messaging_contact_id`),
  CONSTRAINT `conversations_human_owner_id_foreign` FOREIGN KEY (`human_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_messaging_contact_id_foreign` FOREIGN KEY (`messaging_contact_id`) REFERENCES `messaging_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_messaging_integration_id_foreign` FOREIGN KEY (`messaging_integration_id`) REFERENCES `tenant_messaging_integrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_target_counsellor_id_foreign` FOREIGN KEY (`target_counsellor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `conversations_visitor_id_foreign` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`),
  CONSTRAINT `conversations_visitor_last_read_message_id_foreign` FOREIGN KEY (`visitor_last_read_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `counsellor_profiles`
--

DROP TABLE IF EXISTS `counsellor_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `counsellor_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `membership_id` bigint(20) unsigned NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `max_active_leads` smallint(5) unsigned DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `availability` varchar(20) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `counsellor_profiles_membership_id_unique` (`membership_id`),
  KEY `counsellor_profiles_tenant_id_membership_id_index` (`tenant_id`,`membership_id`),
  CONSTRAINT `counsellor_profiles_membership_id_foreign` FOREIGN KEY (`membership_id`) REFERENCES `tenant_user` (`id`),
  CONSTRAINT `counsellor_profiles_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `counsellor_profiles`
--

LOCK TABLES `counsellor_profiles` WRITE;
/*!40000 ALTER TABLE `counsellor_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `counsellor_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_institution`
--

DROP TABLE IF EXISTS `course_institution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_institution` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `institution_id` bigint(20) unsigned NOT NULL,
  `intake_label` varchar(120) DEFAULT NULL,
  `fee_amount_minor` bigint(20) unsigned DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_institution_tenant_id_course_id_institution_id_unique` (`tenant_id`,`course_id`,`institution_id`),
  KEY `course_institution_course_id_foreign` (`course_id`),
  KEY `course_institution_institution_id_foreign` (`institution_id`),
  KEY `course_institution_created_by_foreign` (`created_by`),
  CONSTRAINT `course_institution_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `course_institution_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_institution_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`),
  CONSTRAINT `course_institution_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_institution`
--

LOCK TABLES `course_institution` WRITE;
/*!40000 ALTER TABLE `course_institution` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_institution` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(120) DEFAULT NULL,
  `study_mode` varchar(32) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courses_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `courses_uuid_unique` (`uuid`),
  KEY `courses_created_by_foreign` (`created_by`),
  KEY `courses_tenant_id_status_sort_order_index` (`tenant_id`,`status`,`sort_order`),
  CONSTRAINT `courses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `knowledge_item_id` bigint(20) unsigned DEFAULT NULL,
  `display_name` varchar(200) NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `checksum` char(64) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'stored',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_uuid_unique` (`uuid`),
  KEY `documents_knowledge_item_id_foreign` (`knowledge_item_id`),
  KEY `documents_created_by_foreign` (`created_by`),
  KEY `documents_tenant_id_knowledge_item_id_index` (`tenant_id`,`knowledge_item_id`),
  CONSTRAINT `documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_knowledge_item_id_foreign` FOREIGN KEY (`knowledge_item_id`) REFERENCES `knowledge_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eligibility_rules`
--

DROP TABLE IF EXISTS `eligibility_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eligibility_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `required_criteria` text DEFAULT NULL,
  `preferred_criteria` text DEFAULT NULL,
  `priority` smallint(5) unsigned NOT NULL DEFAULT 100,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eligibility_rules_uuid_unique` (`uuid`),
  KEY `eligibility_rules_service_id_foreign` (`service_id`),
  KEY `eligibility_rules_course_id_foreign` (`course_id`),
  KEY `eligibility_rules_created_by_foreign` (`created_by`),
  KEY `eligibility_rules_tenant_id_status_priority_index` (`tenant_id`,`status`,`priority`),
  CONSTRAINT `eligibility_rules_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `eligibility_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `eligibility_rules_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `eligibility_rules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eligibility_rules`
--

LOCK TABLES `eligibility_rules` WRITE;
/*!40000 ALTER TABLE `eligibility_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `eligibility_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
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
-- Table structure for table `institutions`
--

DROP TABLE IF EXISTS `institutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institutions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institutions_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `institutions_uuid_unique` (`uuid`),
  KEY `institutions_created_by_foreign` (`created_by`),
  KEY `institutions_tenant_id_status_sort_order_index` (`tenant_id`,`status`,`sort_order`),
  CONSTRAINT `institutions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institutions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institutions`
--

LOCK TABLES `institutions` WRITE;
/*!40000 ALTER TABLE `institutions` DISABLE KEYS */;
/*!40000 ALTER TABLE `institutions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
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
-- Table structure for table `knowledge_fees`
--

DROP TABLE IF EXISTS `knowledge_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_fees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `label` varchar(160) NOT NULL,
  `fee_type` varchar(255) NOT NULL DEFAULT 'exact',
  `amount_minor` bigint(20) unsigned NOT NULL,
  `amount_max_minor` bigint(20) unsigned DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `institution_id` bigint(20) unsigned DEFAULT NULL,
  `knowledge_item_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_until` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_fees_uuid_unique` (`uuid`),
  KEY `knowledge_fees_service_id_foreign` (`service_id`),
  KEY `knowledge_fees_course_id_foreign` (`course_id`),
  KEY `knowledge_fees_institution_id_foreign` (`institution_id`),
  KEY `knowledge_fees_knowledge_item_id_foreign` (`knowledge_item_id`),
  KEY `knowledge_fees_created_by_foreign` (`created_by`),
  KEY `knowledge_fees_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `knowledge_fees_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_fees_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_fees_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_fees_knowledge_item_id_foreign` FOREIGN KEY (`knowledge_item_id`) REFERENCES `knowledge_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_fees_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_fees_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_fees`
--

LOCK TABLES `knowledge_fees` WRITE;
/*!40000 ALTER TABLE `knowledge_fees` DISABLE KEYS */;
/*!40000 ALTER TABLE `knowledge_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `knowledge_items`
--

DROP TABLE IF EXISTS `knowledge_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `locale` varchar(12) NOT NULL DEFAULT 'en',
  `title` varchar(200) NOT NULL,
  `draft_title` varchar(200) DEFAULT NULL,
  `draft_body` text DEFAULT NULL,
  `current_version_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `institution_id` bigint(20) unsigned DEFAULT NULL,
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_items_uuid_unique` (`uuid`),
  KEY `knowledge_items_service_id_foreign` (`service_id`),
  KEY `knowledge_items_course_id_foreign` (`course_id`),
  KEY `knowledge_items_institution_id_foreign` (`institution_id`),
  KEY `knowledge_items_location_id_foreign` (`location_id`),
  KEY `knowledge_items_created_by_foreign` (`created_by`),
  KEY `knowledge_items_updated_by_foreign` (`updated_by`),
  KEY `knowledge_items_tenant_id_status_type_index` (`tenant_id`,`status`,`type`),
  KEY `knowledge_items_tenant_id_locale_index` (`tenant_id`,`locale`),
  KEY `knowledge_items_current_version_id_foreign` (`current_version_id`),
  CONSTRAINT `knowledge_items_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_items_current_version_id_foreign` FOREIGN KEY (`current_version_id`) REFERENCES `knowledge_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_items_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_items_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `knowledge_items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_items`
--

LOCK TABLES `knowledge_items` WRITE;
/*!40000 ALTER TABLE `knowledge_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `knowledge_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `knowledge_versions`
--

DROP TABLE IF EXISTS `knowledge_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `knowledge_item_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `content_checksum` char(64) NOT NULL,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_versions_knowledge_item_id_version_number_unique` (`knowledge_item_id`,`version_number`),
  UNIQUE KEY `knowledge_versions_uuid_unique` (`uuid`),
  KEY `knowledge_versions_published_by_foreign` (`published_by`),
  KEY `knowledge_versions_tenant_id_knowledge_item_id_index` (`tenant_id`,`knowledge_item_id`),
  CONSTRAINT `knowledge_versions_knowledge_item_id_foreign` FOREIGN KEY (`knowledge_item_id`) REFERENCES `knowledge_items` (`id`),
  CONSTRAINT `knowledge_versions_published_by_foreign` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_versions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_versions`
--

LOCK TABLES `knowledge_versions` WRITE;
/*!40000 ALTER TABLE `knowledge_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `knowledge_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_activities`
--

DROP TABLE IF EXISTS `lead_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `action_type` varchar(40) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `previous_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_activities_lead_id_foreign` (`lead_id`),
  KEY `lead_activities_actor_user_id_foreign` (`actor_user_id`),
  KEY `lead_activities_tenant_id_lead_id_created_at_index` (`tenant_id`,`lead_id`,`created_at`),
  CONSTRAINT `lead_activities_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_activities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  CONSTRAINT `lead_activities_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_activities`
--

LOCK TABLES `lead_activities` WRITE;
/*!40000 ALTER TABLE `lead_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_assignments`
--

DROP TABLE IF EXISTS `lead_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unassigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_assignments_lead_id_foreign` (`lead_id`),
  KEY `lead_assignments_assigned_to_foreign` (`assigned_to`),
  KEY `lead_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `lead_assignments_tenant_id_lead_id_is_current_index` (`tenant_id`,`lead_id`,`is_current`),
  KEY `lead_assignments_tenant_id_assigned_to_is_current_index` (`tenant_id`,`assigned_to`,`is_current`),
  CONSTRAINT `lead_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `lead_assignments_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `lead_assignments_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  CONSTRAINT `lead_assignments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_assignments`
--

LOCK TABLES `lead_assignments` WRITE;
/*!40000 ALTER TABLE `lead_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_follow_ups`
--

DROP TABLE IF EXISTS `lead_follow_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_follow_ups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `due_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(32) NOT NULL DEFAULT 'scheduled',
  `note` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_outcome` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_follow_ups_lead_id_foreign` (`lead_id`),
  KEY `lead_follow_ups_assigned_to_foreign` (`assigned_to`),
  KEY `lead_follow_ups_created_by_foreign` (`created_by`),
  KEY `lead_follow_ups_tenant_id_assigned_to_due_at_index` (`tenant_id`,`assigned_to`,`due_at`),
  KEY `lead_follow_ups_tenant_id_status_due_at_index` (`tenant_id`,`status`,`due_at`),
  CONSTRAINT `lead_follow_ups_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `lead_follow_ups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `lead_follow_ups_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  CONSTRAINT `lead_follow_ups_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_follow_ups`
--

LOCK TABLES `lead_follow_ups` WRITE;
/*!40000 ALTER TABLE `lead_follow_ups` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_follow_ups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_notes`
--

DROP TABLE IF EXISTS `lead_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `author_user_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_notes_lead_id_foreign` (`lead_id`),
  KEY `lead_notes_author_user_id_foreign` (`author_user_id`),
  KEY `lead_notes_tenant_id_lead_id_index` (`tenant_id`,`lead_id`),
  CONSTRAINT `lead_notes_author_user_id_foreign` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `lead_notes_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  CONSTRAINT `lead_notes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_notes`
--

LOCK TABLES `lead_notes` WRITE;
/*!40000 ALTER TABLE `lead_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_notifications`
--

DROP TABLE IF EXISTS `lead_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_notifications_user_id_foreign` (`user_id`),
  KEY `lead_notifications_lead_id_foreign` (`lead_id`),
  KEY `lead_notifications_tenant_id_user_id_read_at_index` (`tenant_id`,`user_id`,`read_at`),
  KEY `lead_notifications_conversation_id_foreign` (`conversation_id`),
  CONSTRAINT `lead_notifications_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_notifications_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_notifications_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `lead_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_notifications`
--

LOCK TABLES `lead_notifications` WRITE;
/*!40000 ALTER TABLE `lead_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_qualification_rules`
--

DROP TABLE IF EXISTS `lead_qualification_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_qualification_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rules`)),
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_qualification_rules_tenant_id_unique` (`tenant_id`),
  KEY `lead_qualification_rules_updated_by_foreign` (`updated_by`),
  CONSTRAINT `lead_qualification_rules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `lead_qualification_rules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_qualification_rules`
--

LOCK TABLES `lead_qualification_rules` WRITE;
/*!40000 ALTER TABLE `lead_qualification_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_qualification_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `public_reference` varchar(32) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(40) NOT NULL,
  `source_reference` varchar(120) DEFAULT NULL,
  `capture_event_uuid` char(36) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `preferred_contact_method` varchar(32) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `service_interest` varchar(255) DEFAULT NULL,
  `programme_interest` text DEFAULT NULL,
  `enquiry_summary` text DEFAULT NULL,
  `qualification_notes` text DEFAULT NULL,
  `lead_score` smallint(5) unsigned NOT NULL DEFAULT 0,
  `qualification_status` varchar(40) NOT NULL DEFAULT 'not_reviewed',
  `stage` varchar(40) NOT NULL DEFAULT 'new',
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `next_follow_up_at` timestamp NULL DEFAULT NULL,
  `last_contacted_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `lost_reason` varchar(255) DEFAULT NULL,
  `invalid_reason` varchar(255) DEFAULT NULL,
  `ai_suggested_summary` text DEFAULT NULL,
  `ai_suggested_score` smallint(5) unsigned DEFAULT NULL,
  `ai_suggested_priority` varchar(20) DEFAULT NULL,
  `score_components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`score_components`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_tenant_id_public_reference_unique` (`tenant_id`,`public_reference`),
  UNIQUE KEY `leads_uuid_unique` (`uuid`),
  UNIQUE KEY `leads_tenant_capture_event_unique` (`tenant_id`,`capture_event_uuid`),
  UNIQUE KEY `leads_tenant_source_reference_unique` (`tenant_id`,`source`,`source_reference`),
  KEY `leads_conversation_id_foreign` (`conversation_id`),
  KEY `leads_created_by_foreign` (`created_by`),
  KEY `leads_assigned_to_foreign` (`assigned_to`),
  KEY `leads_tenant_id_stage_index` (`tenant_id`,`stage`),
  KEY `leads_tenant_id_qualification_status_index` (`tenant_id`,`qualification_status`),
  KEY `leads_tenant_id_priority_index` (`tenant_id`,`priority`),
  KEY `leads_tenant_id_assigned_to_index` (`tenant_id`,`assigned_to`),
  KEY `leads_tenant_id_next_follow_up_at_index` (`tenant_id`,`next_follow_up_at`),
  KEY `leads_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `pin_code` varchar(16) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `locations_uuid_unique` (`uuid`),
  KEY `locations_created_by_foreign` (`created_by`),
  KEY `locations_tenant_id_status_sort_order_index` (`tenant_id`,`status`,`sort_order`),
  CONSTRAINT `locations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `locations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `request_uuid` char(36) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL,
  `direction` varchar(16) DEFAULT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `delivery_state` varchar(32) DEFAULT NULL,
  `template_name` varchar(128) DEFAULT NULL,
  `reply_to_provider_message_id` varchar(255) DEFAULT NULL,
  `delivery_failure_category` varchar(64) DEFAULT NULL,
  `sender_user_id` bigint(20) unsigned DEFAULT NULL,
  `sender_display_name` varchar(120) DEFAULT NULL,
  `body` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `messages_uuid_unique` (`uuid`),
  UNIQUE KEY `messages_tenant_conversation_request_uuid_unique` (`tenant_id`,`conversation_id`,`request_uuid`),
  UNIQUE KEY `messages_provider_message_unique` (`tenant_id`,`provider_message_id`),
  KEY `messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  KEY `messages_tenant_id_conversation_id_index` (`tenant_id`,`conversation_id`),
  KEY `messages_sender_user_id_foreign` (`sender_user_id`),
  KEY `messages_conversation_id_id_index` (`conversation_id`,`id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `messages_sender_user_id_foreign` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messaging_contacts`
--

DROP TABLE IF EXISTS `messaging_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messaging_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `messaging_integration_id` bigint(20) unsigned NOT NULL,
  `channel` varchar(32) NOT NULL,
  `external_contact_id` varchar(64) NOT NULL,
  `display_phone` varchar(32) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `provider_contact_id` varchar(255) DEFAULT NULL,
  `last_inbound_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `messaging_contacts_unique` (`messaging_integration_id`,`external_contact_id`),
  UNIQUE KEY `messaging_contacts_uuid_unique` (`uuid`),
  KEY `messaging_contacts_tenant_id_channel_index` (`tenant_id`,`channel`),
  CONSTRAINT `messaging_contacts_messaging_integration_id_foreign` FOREIGN KEY (`messaging_integration_id`) REFERENCES `tenant_messaging_integrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messaging_contacts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messaging_contacts`
--

LOCK TABLES `messaging_contacts` WRITE;
/*!40000 ALTER TABLE `messaging_contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `messaging_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messaging_events`
--

DROP TABLE IF EXISTS `messaging_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messaging_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `messaging_integration_id` bigint(20) unsigned DEFAULT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `message_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `processing_status` varchar(32) NOT NULL DEFAULT 'recorded',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `messaging_events_uuid_unique` (`uuid`),
  KEY `messaging_events_conversation_id_foreign` (`conversation_id`),
  KEY `messaging_events_message_id_foreign` (`message_id`),
  KEY `messaging_events_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `messaging_events_messaging_integration_id_created_at_index` (`messaging_integration_id`,`created_at`),
  CONSTRAINT `messaging_events_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messaging_events_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messaging_events_messaging_integration_id_foreign` FOREIGN KEY (`messaging_integration_id`) REFERENCES `tenant_messaging_integrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messaging_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messaging_events`
--

LOCK TABLES `messaging_events` WRITE;
/*!40000 ALTER TABLE `messaging_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `messaging_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messaging_templates`
--

DROP TABLE IF EXISTS `messaging_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messaging_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `messaging_integration_id` bigint(20) unsigned NOT NULL,
  `provider_template_name` varchar(255) NOT NULL,
  `language_code` varchar(16) NOT NULL DEFAULT 'en',
  `category` varchar(64) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `variable_definitions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variable_definitions`)),
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `messaging_templates_unique` (`messaging_integration_id`,`provider_template_name`,`language_code`),
  UNIQUE KEY `messaging_templates_uuid_unique` (`uuid`),
  KEY `messaging_templates_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `messaging_templates_messaging_integration_id_foreign` FOREIGN KEY (`messaging_integration_id`) REFERENCES `tenant_messaging_integrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messaging_templates_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messaging_templates`
--

LOCK TABLES `messaging_templates` WRITE;
/*!40000 ALTER TABLE `messaging_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `messaging_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messaging_webhook_events`
--

DROP TABLE IF EXISTS `messaging_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messaging_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `provider_event_id` varchar(255) NOT NULL,
  `event_type` varchar(128) DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `messaging_webhook_events_unique` (`provider`,`provider_event_id`),
  UNIQUE KEY `messaging_webhook_events_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messaging_webhook_events`
--

LOCK TABLES `messaging_webhook_events` WRITE;
/*!40000 ALTER TABLE `messaging_webhook_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `messaging_webhook_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_06_15_100001_add_platform_fields_to_users_table',1),(5,'2026_06_15_100002_create_tenants_table',1),(6,'2026_06_15_100003_create_tenant_user_table',1),(7,'2026_06_15_100004_create_audit_logs_table',1),(8,'2026_06_15_100005_create_tenant_notes_table',1),(9,'2026_06_15_102833_add_two_factor_columns_to_users_table',1),(10,'2026_06_15_102834_create_passkeys_table',1),(11,'2026_06_15_200001_create_tenant_widget_settings_table',2),(12,'2026_06_15_200002_create_tenant_domains_table',2),(13,'2026_06_15_200003_create_widget_keys_table',2),(14,'2026_06_15_200004_create_visitors_table',2),(15,'2026_06_15_200005_create_conversations_table',2),(16,'2026_06_15_200006_create_messages_table',2),(17,'2026_06_15_200007_create_widget_sessions_table',2),(18,'2026_06_15_300001_add_timezone_locale_to_tenants_table',3),(19,'2026_06_15_300002_create_tenant_settings_table',3),(20,'2026_06_15_300003_extend_tenant_widget_settings_table',3),(21,'2026_06_15_300004_create_tenant_office_hours_table',3),(22,'2026_06_15_300005_create_services_table',3),(23,'2026_06_15_300006_create_courses_table',3),(24,'2026_06_15_300007_create_institutions_table',3),(25,'2026_06_15_300008_create_locations_table',3),(26,'2026_06_15_400001_create_knowledge_items_table',4),(27,'2026_06_15_400002_create_knowledge_versions_table',4),(28,'2026_06_15_400003_create_knowledge_fees_table',4),(29,'2026_06_15_400004_create_eligibility_rules_table',4),(30,'2026_06_15_400005_create_documents_table',4),(31,'2026_06_15_400006_create_course_institution_table',4),(32,'2026_06_15_500001_create_ai_providers_table',5),(33,'2026_06_15_500002_create_tenant_ai_configs_table',5),(34,'2026_06_15_500003_create_ai_runs_table',5),(35,'2026_06_15_500004_enhance_ai_orchestration_security',6),(36,'2026_06_15_500005_seed_fake_ai_provider',7),(37,'2026_06_15_600001_add_suspended_by_to_tenants_table',8),(38,'2026_06_15_600002_create_platform_settings_table',8),(39,'2026_06_15_700001_create_leads_module_tables',9),(40,'2026_06_15_800001_create_human_conversation_module_tables',10),(41,'2026_06_15_800002_complete_human_conversation_module_tables',11),(42,'2026_06_15_900001_create_subscription_module_tables',12),(43,'2026_06_15_100000_create_payment_module_tables',13),(44,'2026_06_15_900002_create_payment_module_tables',12),(45,'2026_06_15_900003_create_messaging_module_tables',14);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `passkeys`
--

DROP TABLE IF EXISTS `passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `passkeys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `credential` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`credential`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkeys_credential_id_unique` (`credential_id`),
  KEY `passkeys_user_id_index` (`user_id`),
  CONSTRAINT `passkeys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `passkeys`
--

LOCK TABLES `passkeys` WRITE;
/*!40000 ALTER TABLE `passkeys` DISABLE KEYS */;
/*!40000 ALTER TABLE `passkeys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
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
-- Table structure for table `payment_events`
--

DROP TABLE IF EXISTS `payment_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `payment_order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `source` varchar(32) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_events_uuid_unique` (`uuid`),
  KEY `payment_events_tenant_id_foreign` (`tenant_id`),
  KEY `payment_events_payment_order_id_created_at_index` (`payment_order_id`,`created_at`),
  KEY `payment_events_payment_id_created_at_index` (`payment_id`,`created_at`),
  CONSTRAINT `payment_events_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_events_payment_order_id_foreign` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_events`
--

LOCK TABLES `payment_events` WRITE;
/*!40000 ALTER TABLE `payment_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_orders`
--

DROP TABLE IF EXISTS `payment_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `subscription_id` bigint(20) unsigned DEFAULT NULL,
  `checkout_request_uuid` char(36) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `provider_mode` varchar(16) NOT NULL,
  `provider_order_id` varchar(255) DEFAULT NULL,
  `internal_reference` varchar(64) NOT NULL,
  `amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `status` varchar(32) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `receipt_reference` varchar(128) DEFAULT NULL,
  `initiated_by` bigint(20) unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `subscription_activation_completed_at` timestamp NULL DEFAULT NULL,
  `activated_subscription_id` bigint(20) unsigned DEFAULT NULL,
  `notification_key` varchar(128) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_orders_checkout_unique` (`tenant_id`,`checkout_request_uuid`),
  UNIQUE KEY `payment_orders_uuid_unique` (`uuid`),
  UNIQUE KEY `payment_orders_internal_reference_unique` (`internal_reference`),
  UNIQUE KEY `payment_orders_provider_order_unique` (`provider`,`provider_mode`,`provider_order_id`),
  KEY `payment_orders_plan_id_foreign` (`plan_id`),
  KEY `payment_orders_subscription_id_foreign` (`subscription_id`),
  KEY `payment_orders_initiated_by_foreign` (`initiated_by`),
  KEY `payment_orders_activated_subscription_id_foreign` (`activated_subscription_id`),
  KEY `payment_orders_tenant_id_status_created_at_index` (`tenant_id`,`status`,`created_at`),
  KEY `payment_orders_status_expires_at_index` (`status`,`expires_at`),
  CONSTRAINT `payment_orders_activated_subscription_id_foreign` FOREIGN KEY (`activated_subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_orders_initiated_by_foreign` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_orders_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  CONSTRAINT `payment_orders_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_orders_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_orders`
--

LOCK TABLES `payment_orders` WRITE;
/*!40000 ALTER TABLE `payment_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_webhook_events`
--

DROP TABLE IF EXISTS `payment_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `provider_mode` varchar(16) NOT NULL,
  `provider_event_id` varchar(255) NOT NULL,
  `event_type` varchar(128) NOT NULL,
  `status` varchar(32) NOT NULL,
  `event_hash` varchar(64) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_webhook_events_unique` (`provider`,`provider_mode`,`provider_event_id`),
  UNIQUE KEY `payment_webhook_events_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_webhook_events`
--

LOCK TABLES `payment_webhook_events` WRITE;
/*!40000 ALTER TABLE `payment_webhook_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_webhook_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `payment_order_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(32) NOT NULL,
  `provider_mode` varchar(16) NOT NULL,
  `provider_payment_id` varchar(255) NOT NULL,
  `amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `status` varchar(32) NOT NULL,
  `payment_method_category` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `refunded_amount_minor` bigint(20) unsigned NOT NULL DEFAULT 0,
  `failure_category` varchar(64) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_provider_payment_unique` (`provider`,`provider_mode`,`provider_payment_id`),
  UNIQUE KEY `payments_uuid_unique` (`uuid`),
  KEY `payments_payment_order_id_foreign` (`payment_order_id`),
  KEY `payments_tenant_id_status_created_at_index` (`tenant_id`,`status`,`created_at`),
  CONSTRAINT `payments_payment_order_id_foreign` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders` (`id`),
  CONSTRAINT `payments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plan_features`
--

DROP TABLE IF EXISTS `plan_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_features` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` bigint(20) unsigned NOT NULL,
  `feature` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `limit_value` bigint(20) unsigned DEFAULT NULL,
  `limit_period` varchar(32) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_features_plan_id_feature_unique` (`plan_id`,`feature`),
  CONSTRAINT `plan_features_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_features`
--

LOCK TABLES `plan_features` WRITE;
/*!40000 ALTER TABLE `plan_features` DISABLE KEYS */;
INSERT INTO `plan_features` VALUES (1,1,'widget',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(2,1,'ai_responses',1,100,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(3,1,'knowledge_base',1,20,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(4,1,'lead_management',1,50,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(5,1,'counsellor_workspace',1,1,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(6,1,'human_handoff',1,2,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(7,2,'widget',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(8,2,'ai_responses',1,500,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(9,2,'knowledge_base',1,100,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(10,2,'lead_management',1,200,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(11,2,'counsellor_workspace',1,2,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(12,2,'human_handoff',0,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(13,3,'widget',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(14,3,'ai_responses',1,2000,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(15,3,'knowledge_base',1,500,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(16,3,'lead_management',1,1000,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(17,3,'counsellor_workspace',1,10,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(18,3,'human_handoff',1,20,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(19,3,'usage_reporting',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(20,3,'custom_ai_credentials',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(21,4,'widget',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(22,4,'ai_responses',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(23,4,'knowledge_base',1,NULL,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(24,4,'lead_management',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(25,4,'counsellor_workspace',1,NULL,'total','2026-06-15 13:54:29','2026-06-15 13:54:29'),(26,4,'human_handoff',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(27,4,'usage_reporting',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(28,4,'custom_ai_credentials',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(29,4,'platform_credential_fallback',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(30,4,'data_export',1,NULL,'billing_period','2026-06-15 13:54:29','2026-06-15 13:54:29'),(31,3,'whatsapp_integration',1,NULL,'billing_period','2026-06-16 02:05:29','2026-06-16 02:05:29'),(32,4,'whatsapp_integration',1,NULL,'billing_period','2026-06-16 02:05:29','2026-06-16 02:05:29');
/*!40000 ALTER TABLE `plan_features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `billing_interval` varchar(32) NOT NULL DEFAULT 'monthly',
  `currency` char(3) DEFAULT NULL,
  `amount_minor` bigint(20) unsigned DEFAULT NULL,
  `billing_interval_count` smallint(5) unsigned NOT NULL DEFAULT 1,
  `tax_treatment` varchar(32) DEFAULT NULL,
  `setup_fee_minor` bigint(20) unsigned DEFAULT NULL,
  `provider_price_id` varchar(255) DEFAULT NULL,
  `display_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `is_purchasable` tinyint(1) NOT NULL DEFAULT 0,
  `pricing_effective_from` timestamp NULL DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_uuid_unique` (`uuid`),
  UNIQUE KEY `plans_code_unique` (`code`),
  KEY `plans_created_by_foreign` (`created_by`),
  KEY `plans_updated_by_foreign` (`updated_by`),
  KEY `plans_status_display_order_index` (`status`,`display_order`),
  CONSTRAINT `plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `plans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` VALUES (1,'9c2f7809-a204-4558-a19b-96e87772a531','trial','Trial','Limited trial for evaluation.','monthly',NULL,NULL,1,NULL,NULL,NULL,1,1,0,NULL,'active',NULL,NULL,'2026-06-15 13:54:29','2026-06-15 13:54:29'),(2,'0adb832d-fe7d-4935-9e3b-4e6e36975ffa','starter','Starter','Core widget and lead management.','monthly',NULL,NULL,1,NULL,NULL,NULL,2,1,0,NULL,'active',NULL,NULL,'2026-06-15 13:54:29','2026-06-15 13:54:29'),(3,'9cb43a78-67f7-41a9-b5fb-a5acb5246257','professional','Professional','Higher limits with human handoff and usage reporting.','monthly',NULL,NULL,1,NULL,NULL,NULL,3,1,0,NULL,'active',NULL,NULL,'2026-06-15 13:54:29','2026-06-15 13:54:29'),(4,'dc2d1a55-1038-4fe6-a9db-906b2624856a','enterprise','Enterprise','Custom limits and platform-managed options.','monthly',NULL,NULL,1,NULL,NULL,NULL,4,1,0,NULL,'active',NULL,NULL,'2026-06-15 13:54:29','2026-06-15 13:54:29');
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_settings`
--

DROP TABLE IF EXISTS `platform_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(120) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_settings_key_unique` (`key`),
  KEY `platform_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `platform_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_settings`
--

LOCK TABLES `platform_settings` WRITE;
/*!40000 ALTER TABLE `platform_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `services_uuid_unique` (`uuid`),
  KEY `services_created_by_foreign` (`created_by`),
  KEY `services_tenant_id_status_sort_order_index` (`tenant_id`,`status`,`sort_order`),
  CONSTRAINT `services_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `services_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
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
INSERT INTO `sessions` VALUES ('0pc5eIzp28YtmoMUkGWXw1uIKzR2tJGx3K1XV5qg',5,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','eyJfdG9rZW4iOiIxZHQ3WnJSMTFRZEhiR0F0Vkhtc0JkNWQ0UXJaTXVFT3lOUXlNMllZIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2FwcFwvMTRhMzVlMzAtZGNkZS00MjZkLTk5ZDctNjNhMWQ0YjAxNjljXC9zdWJzY3JpcHRpb24iLCJyb3V0ZSI6InRlbmFudC5zdWJzY3JpcHRpb24ifSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjUsInVybCI6W119',1781532061);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_events`
--

DROP TABLE IF EXISTS `subscription_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `previous_status` varchar(32) DEFAULT NULL,
  `new_status` varchar(32) DEFAULT NULL,
  `effective_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_events_uuid_unique` (`uuid`),
  KEY `subscription_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `sub_events_tenant_created_idx` (`tenant_id`,`created_at`),
  KEY `sub_events_sub_created_idx` (`subscription_id`,`created_at`),
  CONSTRAINT `subscription_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_events_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription_events`
--

LOCK TABLES `subscription_events` WRITE;
/*!40000 ALTER TABLE `subscription_events` DISABLE KEYS */;
INSERT INTO `subscription_events` VALUES (1,'036f7aa3-479a-4fae-a392-f75a2091d088',1,1,'created',NULL,'active','2026-06-15 13:54:51',1,'Local bootstrap',NULL,'2026-06-15 13:54:51'),(2,'2805a04c-0dd2-458c-8f33-520917c9fba6',1,1,'activated',NULL,'active','2026-06-15 13:54:51',1,'Local bootstrap',NULL,'2026-06-15 13:54:51');
/*!40000 ALTER TABLE `subscription_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_usage_warnings`
--

DROP TABLE IF EXISTS `subscription_usage_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_usage_warnings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `metric` varchar(64) NOT NULL,
  `threshold_percent` tinyint(3) unsigned NOT NULL,
  `period_key` varchar(32) NOT NULL,
  `notified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sub_usage_warn_unique_idx` (`tenant_id`,`metric`,`threshold_percent`,`period_key`),
  CONSTRAINT `subscription_usage_warnings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription_usage_warnings`
--

LOCK TABLES `subscription_usage_warnings` WRITE;
/*!40000 ALTER TABLE `subscription_usage_warnings` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscription_usage_warnings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'manual',
  `trial_started_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_started_at` timestamp NULL DEFAULT NULL,
  `current_period_ends_at` timestamp NULL DEFAULT NULL,
  `grace_ends_at` timestamp NULL DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `provider_name` varchar(64) DEFAULT NULL,
  `provider_customer_id` varchar(255) DEFAULT NULL,
  `provider_subscription_id` varchar(255) DEFAULT NULL,
  `provider_status` varchar(64) DEFAULT NULL,
  `last_webhook_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_uuid_unique` (`uuid`),
  UNIQUE KEY `subscriptions_tenant_id_unique` (`tenant_id`),
  KEY `subscriptions_plan_id_foreign` (`plan_id`),
  KEY `subscriptions_created_by_foreign` (`created_by`),
  KEY `subscriptions_updated_by_foreign` (`updated_by`),
  KEY `subscriptions_status_current_period_ends_at_index` (`status`,`current_period_ends_at`),
  KEY `subscriptions_status_trial_ends_at_index` (`status`,`trial_ends_at`),
  KEY `subscriptions_status_grace_ends_at_index` (`status`,`grace_ends_at`),
  CONSTRAINT `subscriptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  CONSTRAINT `subscriptions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,'8739a010-23e0-4396-af28-b81a182d3904',1,1,'active','manual',NULL,NULL,'2026-06-15 13:54:51','2026-07-15 13:54:51',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-06-15 13:54:51','2026-06-15 13:54:51');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_ai_configs`
--

DROP TABLE IF EXISTS `tenant_ai_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_ai_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `provider_id` bigint(20) unsigned NOT NULL,
  `model` varchar(120) NOT NULL,
  `temperature` decimal(3,2) NOT NULL DEFAULT 0.20,
  `max_output_tokens` int(10) unsigned NOT NULL DEFAULT 400,
  `timeout_seconds` int(10) unsigned NOT NULL DEFAULT 15,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `credential_mode` varchar(64) NOT NULL DEFAULT 'platform_managed',
  `encrypted_api_key` text DEFAULT NULL,
  `secret_updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_ai_configs_tenant_id_unique` (`tenant_id`),
  UNIQUE KEY `tenant_ai_configs_uuid_unique` (`uuid`),
  KEY `tenant_ai_configs_provider_id_foreign` (`provider_id`),
  KEY `tenant_ai_configs_created_by_foreign` (`created_by`),
  KEY `tenant_ai_configs_updated_by_foreign` (`updated_by`),
  KEY `tenant_ai_configs_tenant_id_enabled_index` (`tenant_id`,`enabled`),
  CONSTRAINT `tenant_ai_configs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_ai_configs_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`id`),
  CONSTRAINT `tenant_ai_configs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `tenant_ai_configs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_ai_configs`
--

LOCK TABLES `tenant_ai_configs` WRITE;
/*!40000 ALTER TABLE `tenant_ai_configs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_ai_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_domains`
--

DROP TABLE IF EXISTS `tenant_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_domains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `domain` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_domains_tenant_id_domain_unique` (`tenant_id`,`domain`),
  KEY `tenant_domains_created_by_foreign` (`created_by`),
  KEY `tenant_domains_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `tenant_domains_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_domains_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_domains`
--

LOCK TABLES `tenant_domains` WRITE;
/*!40000 ALTER TABLE `tenant_domains` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_domains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_entitlement_overrides`
--

DROP TABLE IF EXISTS `tenant_entitlement_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_entitlement_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `feature` varchar(64) NOT NULL,
  `enabled` tinyint(1) DEFAULT NULL,
  `limit_value` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_entitlement_overrides_tenant_id_feature_unique` (`tenant_id`,`feature`),
  KEY `tenant_entitlement_overrides_created_by_foreign` (`created_by`),
  CONSTRAINT `tenant_entitlement_overrides_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_entitlement_overrides_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_entitlement_overrides`
--

LOCK TABLES `tenant_entitlement_overrides` WRITE;
/*!40000 ALTER TABLE `tenant_entitlement_overrides` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_entitlement_overrides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_messaging_integrations`
--

DROP TABLE IF EXISTS `tenant_messaging_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_messaging_integrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(32) NOT NULL,
  `environment` varchar(16) NOT NULL DEFAULT 'test',
  `status` varchar(32) NOT NULL DEFAULT 'disabled',
  `phone_number_id` varchar(255) DEFAULT NULL,
  `waba_id` varchar(255) DEFAULT NULL,
  `display_phone_number` varchar(32) DEFAULT NULL,
  `business_display_name` varchar(255) DEFAULT NULL,
  `verify_token` varchar(128) NOT NULL,
  `access_token` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`access_token`)),
  `app_secret` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`app_secret`)),
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_webhook_at` timestamp NULL DEFAULT NULL,
  `last_outbound_success_at` timestamp NULL DEFAULT NULL,
  `last_error_category` varchar(64) DEFAULT NULL,
  `configured_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_messaging_integrations_uuid_unique` (`uuid`),
  UNIQUE KEY `tenant_messaging_integrations_tenant_id_unique` (`tenant_id`),
  UNIQUE KEY `messaging_integrations_phone_unique` (`provider`,`phone_number_id`),
  KEY `tenant_messaging_integrations_configured_by_foreign` (`configured_by`),
  KEY `tenant_messaging_integrations_provider_status_index` (`provider`,`status`),
  CONSTRAINT `tenant_messaging_integrations_configured_by_foreign` FOREIGN KEY (`configured_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_messaging_integrations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_messaging_integrations`
--

LOCK TABLES `tenant_messaging_integrations` WRITE;
/*!40000 ALTER TABLE `tenant_messaging_integrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_messaging_integrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_notes`
--

DROP TABLE IF EXISTS `tenant_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_notes_created_by_foreign` (`created_by`),
  KEY `tenant_notes_tenant_id_index` (`tenant_id`),
  CONSTRAINT `tenant_notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_notes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_notes`
--

LOCK TABLES `tenant_notes` WRITE;
/*!40000 ALTER TABLE `tenant_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_office_hours`
--

DROP TABLE IF EXISTS `tenant_office_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_office_hours` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `opens_at` time DEFAULT NULL,
  `closes_at` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_office_hours_tenant_id_day_of_week_unique` (`tenant_id`,`day_of_week`),
  CONSTRAINT `tenant_office_hours_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_office_hours`
--

LOCK TABLES `tenant_office_hours` WRITE;
/*!40000 ALTER TABLE `tenant_office_hours` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_office_hours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_settings`
--

DROP TABLE IF EXISTS `tenant_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `display_name` varchar(120) DEFAULT NULL,
  `assistant_name` varchar(120) DEFAULT NULL,
  `assistant_title` varchar(120) DEFAULT NULL,
  `primary_color` char(7) NOT NULL DEFAULT '#2563EB',
  `accent_color` char(7) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `consent_text` text DEFAULT NULL,
  `consent_version` varchar(32) DEFAULT NULL,
  `ai_disclosure_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `ai_disclosure_message` varchar(500) DEFAULT NULL,
  `default_locale` varchar(12) NOT NULL DEFAULT 'en',
  `supported_locales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supported_locales`)),
  `human_transfer_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `human_transfer_label` varchar(120) NOT NULL DEFAULT 'Speak to a counsellor',
  `human_transfer_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_settings_tenant_id_unique` (`tenant_id`),
  CONSTRAINT `tenant_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_settings`
--

LOCK TABLES `tenant_settings` WRITE;
/*!40000 ALTER TABLE `tenant_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_usage_counters`
--

DROP TABLE IF EXISTS `tenant_usage_counters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_usage_counters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `metric` varchar(64) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `used_value` bigint(20) unsigned NOT NULL DEFAULT 0,
  `reserved_value` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usage_counters_unique_idx` (`tenant_id`,`metric`,`period_start`),
  KEY `tenant_usage_counters_tenant_id_metric_period_end_index` (`tenant_id`,`metric`,`period_end`),
  CONSTRAINT `tenant_usage_counters_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_usage_counters`
--

LOCK TABLES `tenant_usage_counters` WRITE;
/*!40000 ALTER TABLE `tenant_usage_counters` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_usage_counters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_user`
--

DROP TABLE IF EXISTS `tenant_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_user_tenant_id_user_id_unique` (`tenant_id`,`user_id`),
  KEY `tenant_user_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `tenant_user_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `tenant_user_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `tenant_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_user`
--

LOCK TABLES `tenant_user` WRITE;
/*!40000 ALTER TABLE `tenant_user` DISABLE KEYS */;
INSERT INTO `tenant_user` VALUES (1,1,2,'owner','active',1,'2026-06-15 05:07:36','2026-06-15 05:07:36','2026-06-15 05:07:36'),(2,2,5,'owner','active',1,'2026-06-15 13:54:52','2026-06-15 13:54:52','2026-06-15 13:54:52');
/*!40000 ALTER TABLE `tenant_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_widget_settings`
--

DROP TABLE IF EXISTS `tenant_widget_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_widget_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `welcome_message` varchar(255) DEFAULT NULL,
  `offline_message` varchar(255) DEFAULT NULL,
  `offline_form_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `widget_position` varchar(32) NOT NULL DEFAULT 'bottom_right',
  `welcome_delay_seconds` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_widget_settings_tenant_id_unique` (`tenant_id`),
  CONSTRAINT `tenant_widget_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_widget_settings`
--

LOCK TABLES `tenant_widget_settings` WRITE;
/*!40000 ALTER TABLE `tenant_widget_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_widget_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Kolkata',
  `locale` varchar(12) NOT NULL DEFAULT 'en',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `activated_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `suspension_reason` text DEFAULT NULL,
  `suspended_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_uuid_unique` (`uuid`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_created_by_foreign` (`created_by`),
  KEY `tenants_status_index` (`status`),
  KEY `tenants_suspended_by_foreign` (`suspended_by`),
  CONSTRAINT `tenants_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenants_suspended_by_foreign` FOREIGN KEY (`suspended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'5051ada0-6c41-48ce-8a8f-42f1f997538e','Demo Counselling','demo-counselling',NULL,'demo@example.test',NULL,'Asia/Kolkata','en','active','2026-06-15 05:07:36',NULL,NULL,NULL,1,'2026-06-15 05:07:36','2026-06-15 05:07:36'),(2,'14a35e30-dcde-426d-99d7-63a1d4b0169c','Dr sSS','dr-ss','Salauddin Sultan','sp7037444888@gmail.com','7017098399','Asia/Kolkata','en','active','2026-06-15 13:59:21',NULL,NULL,NULL,4,'2026-06-15 13:54:52','2026-06-15 13:59:21');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `platform_role` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'00ec1031-4b8a-4dce-b61d-e0109c3daa9a','Platform Admin','admin@example.test',NULL,'$2y$12$NwOzYiRtmnP3cXmT31euyeZUd/9sEnGALHky1m5.lAdtKUYb.txBC',NULL,NULL,NULL,'super_admin','active',NULL,NULL,'2026-06-15 05:07:13','2026-06-15 05:07:13'),(2,'063ea9d6-1af9-49ff-b252-1734aa72b117','Tenant Owner','owner@example.test',NULL,'$2y$12$m8sTmoFyIcxz3C6HIPc3duIswKK0S/uS9ulXzVs622/MYz3JoCO3i',NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-06-15 05:07:36','2026-06-15 13:54:51'),(3,'6082488b-82c9-4ce7-8c8c-1df7e6ea66dd','Smoke Admin','smoke-admin@example.test','2026-06-15 05:34:54','$2y$12$oYlbCnFRD3nKJVsHWoP/heO7mT1O/S.4PkDgnDy97EFhOvJ19ZYjK',NULL,NULL,NULL,'super_admin','active','2026-06-15 05:35:07',NULL,'2026-06-15 05:34:31','2026-06-15 05:35:07'),(4,'6490e456-5403-454d-aa44-a1c3b51556a9','sadmin','s7037444888@gmail.com','2026-06-15 09:45:51','$2y$12$ZupDoDazDwSanhxFQRVQHuF5zDva.vmzYT40zEdTUIr4R3xyiolcC',NULL,NULL,NULL,'super_admin','active','2026-06-15 13:58:55','hbPgybvw7HaGj2clMY0STcwU130De74ZQPStPcE8W7YTCWzp4TRFgjQzOqDZ','2026-06-15 09:30:51','2026-06-15 13:58:55'),(5,'4c8b8f05-9a19-4487-82cd-66371529803a','Dr. SS','sp7037444888@gmail.com','2026-06-15 14:00:20','$2y$12$p4PMjXw2JnHB8298ZHfN3e/isDaSIxsaREqjzYYZZqA0uj3LIPiIm',NULL,NULL,NULL,NULL,'active','2026-06-15 13:59:45','EpGeCYUSJjHYPHKzn8yMC9o8HigWVsQWmNUcVOZ26ChrbMebSn2qWHfmgH1k','2026-06-15 13:54:52','2026-06-15 14:00:20');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visitors`
--

DROP TABLE IF EXISTS `visitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visitors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `fingerprint_hash` varchar(255) DEFAULT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `visitors_uuid_unique` (`uuid`),
  KEY `visitors_tenant_id_fingerprint_hash_index` (`tenant_id`,`fingerprint_hash`),
  CONSTRAINT `visitors_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitors`
--

LOCK TABLES `visitors` WRITE;
/*!40000 ALTER TABLE `visitors` DISABLE KEYS */;
/*!40000 ALTER TABLE `visitors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `widget_keys`
--

DROP TABLE IF EXISTS `widget_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widget_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `public_key` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `last_rotated_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `widget_keys_uuid_unique` (`uuid`),
  UNIQUE KEY `widget_keys_public_key_unique` (`public_key`),
  KEY `widget_keys_created_by_foreign` (`created_by`),
  KEY `widget_keys_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `widget_keys_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `widget_keys_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `widget_keys`
--

LOCK TABLES `widget_keys` WRITE;
/*!40000 ALTER TABLE `widget_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `widget_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `widget_sessions`
--

DROP TABLE IF EXISTS `widget_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widget_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `visitor_id` bigint(20) unsigned NOT NULL,
  `widget_key_id` bigint(20) unsigned NOT NULL,
  `origin_domain` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `widget_sessions_uuid_unique` (`uuid`),
  KEY `widget_sessions_conversation_id_foreign` (`conversation_id`),
  KEY `widget_sessions_visitor_id_foreign` (`visitor_id`),
  KEY `widget_sessions_widget_key_id_foreign` (`widget_key_id`),
  KEY `widget_sessions_token_hash_expires_at_index` (`token_hash`,`expires_at`),
  KEY `widget_sessions_tenant_id_conversation_id_index` (`tenant_id`,`conversation_id`),
  CONSTRAINT `widget_sessions_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `widget_sessions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `widget_sessions_visitor_id_foreign` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`),
  CONSTRAINT `widget_sessions_widget_key_id_foreign` FOREIGN KEY (`widget_key_id`) REFERENCES `widget_keys` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `widget_sessions`
--

LOCK TABLES `widget_sessions` WRITE;
/*!40000 ALTER TABLE `widget_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `widget_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ai_counsellor'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-16  7:36:24
