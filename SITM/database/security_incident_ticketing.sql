-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 24, 2026 at 11:10 AM
-- Server version: 8.0.38
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `security_incident_ticketing`
--

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int UNSIGNED NOT NULL,
  `ticket_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int UNSIGNED DEFAULT NULL,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `status` enum('open','in_progress','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `reported_by` int UNSIGNED NOT NULL,
  `assigned_to` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `ticket_number`, `title`, `description`, `category_id`, `severity`, `status`, `reported_by`, `assigned_to`, `created_at`, `updated_at`) VALUES
(1, 'INC-2026-0001', 'Suspicious Login Detected', 'Multiple failed login attempts were detected from an unknown IP address.', 3, 'high', 'in_progress', 1, 2, '2026-08-23 18:00:10', '2026-08-24 08:07:17');

-- --------------------------------------------------------

--
-- Table structure for table `incident_activity`
--

CREATE TABLE `incident_activity` (
  `id` int UNSIGNED NOT NULL,
  `incident_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_activity`
--

INSERT INTO `incident_activity` (`id`, `incident_id`, `user_id`, `action`, `old_value`, `new_value`, `comment`, `created_at`) VALUES
(1, 1, 1, 'Incident Created', NULL, 'high', 'Incident ticket created.', '2026-08-23 18:00:10'),
(2, 1, 1, 'Analyst Assigned', 'Not Assigned', 'Security Analyst', 'Incident assigned to analyst.', '2026-08-23 18:06:42'),
(3, 1, 1, 'Status Updated', 'open', 'in_progress', 'Incident moved to In Progress after analyst assignment.', '2026-08-23 18:06:42'),
(4, 1, 1, 'Status Updated', 'in_progress', 'resolved', 'Investigation has started. Login activity and source IP are being analyzed.', '2026-08-23 18:11:39'),
(5, 1, 1, 'Evidence Uploaded', NULL, NULL, 'Screenshot (1).png', '2026-08-23 19:42:05'),
(6, 1, 2, 'Status Updated', 'resolved', 'in_progress', 'Incident status updated.', '2026-08-23 20:04:47'),
(7, 1, 1, 'Evidence Uploaded', NULL, NULL, 'Capstone Project.xlsx', '2026-08-24 08:07:17'),
(8, 1, 1, 'Investigation Note Deleted', NULL, NULL, 'Investigation note was deleted.', '2026-08-24 08:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `incident_attachments`
--

CREATE TABLE `incident_attachments` (
  `id` int UNSIGNED NOT NULL,
  `incident_id` int UNSIGNED NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by` int UNSIGNED NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_categories`
--

CREATE TABLE `incident_categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_categories`
--

INSERT INTO `incident_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Malware', 'Malware related security incident', '2026-08-23 17:47:17'),
(2, 'Phishing', 'Phishing or suspicious email incident', '2026-08-23 17:47:17'),
(3, 'Unauthorized Access', 'Unauthorized access to system or account', '2026-08-23 17:47:17'),
(4, 'Data Breach', 'Loss or unauthorized disclosure of data', '2026-08-23 17:47:17'),
(5, 'Account Compromise', 'Compromised user account', '2026-08-23 17:47:17'),
(6, 'Network Attack', 'Network based security attack', '2026-08-23 17:47:17'),
(7, 'Web Attack', 'Web application related attack', '2026-08-23 17:47:17'),
(8, 'Other', 'Other security incident', '2026-08-23 17:47:17');

-- --------------------------------------------------------

--
-- Table structure for table `incident_comments`
--

CREATE TABLE `incident_comments` (
  `id` int UNSIGNED NOT NULL,
  `incident_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_comments`
--

INSERT INTO `incident_comments` (`id`, `incident_id`, `user_id`, `comment`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(1, 1, 1, 'Initial investigation found multiple failed login attempts from an unknown IP address. Login logs are being reviewed, and the account will be monitored for further suspicious activity. No confirmed unauthorized access has been identified yet.', '2026-08-24 13:44:22', '2026-08-24 14:04:53', 1);

-- --------------------------------------------------------

--
-- Table structure for table `incident_evidence`
--

CREATE TABLE `incident_evidence` (
  `id` int UNSIGNED NOT NULL,
  `incident_id` int UNSIGNED NOT NULL,
  `uploaded_by` int UNSIGNED NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_evidence`
--

INSERT INTO `incident_evidence` (`id`, `incident_id`, `uploaded_by`, `original_name`, `stored_name`, `file_path`, `mime_type`, `file_size`, `file_hash`, `created_at`) VALUES
(1, 1, 1, 'Screenshot (1).png', 'f4a33368b0e8a486a86d456e3718775e.png', 'uploads/evidence/f4a33368b0e8a486a86d456e3718775e.png', 'image/png', 230875, '4ae2326ff61101dd58f164ef96c3f5d5730e40895c96c1039d2b25551beee60a', '2026-08-24 01:12:05'),
(2, 1, 1, 'Capstone Project.xlsx', '2a13d06ce1198f08f726ec9409d21df4.xlsx', 'uploads/evidence/2a13d06ce1198f08f726ec9409d21df4.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 56660, 'c915aa2f145a217c4e6d24c9c6d8e2c09a0662500bcf091fd2c09e25ee81f349', '2026-08-24 13:37:17');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `last_attempt` datetime DEFAULT NULL,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempts`, `last_attempt`, `locked_until`, `created_at`, `updated_at`) VALUES
(2, 'admin@security.localgfgfds', '::1', 1, '2026-08-24 00:39:39', NULL, '2026-08-24 00:39:39', '2026-08-24 00:39:39'),
(4, 'admin@security.localffg', '::1', 1, '2026-08-24 00:39:58', NULL, '2026-08-24 00:39:58', '2026-08-24 00:39:58'),
(5, 'nalyst@security.local', '::1', 1, '2026-08-24 01:27:16', NULL, '2026-08-24 01:27:16', '2026-08-24 01:27:16'),
(6, 'admin@security.loca', '::1', 1, '2026-08-24 01:51:07', NULL, '2026-08-24 01:51:07', '2026-08-24 01:51:07'),
(8, 'abhishekanand3385@gmail.com', '127.0.0.1', 3, '2026-08-24 01:55:37', NULL, '2026-08-24 01:53:38', '2026-08-24 01:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `login_audit_logs`
--

CREATE TABLE `login_audit_logs` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_audit_logs`
--

INSERT INTO `login_audit_logs` (`id`, `user_id`, `email`, `ip_address`, `event_type`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:37:22'),
(2, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:37:54'),
(3, 1, 'admin@security.local', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:36'),
(4, NULL, 'admin@security.localgfgfds', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:39'),
(5, 1, 'admin@security.local', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:42'),
(6, 1, 'admin@security.local', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:43'),
(7, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:45'),
(8, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:49'),
(9, 1, 'admin@security.local', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:53'),
(10, NULL, 'admin@security.localffg', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:39:58'),
(11, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:40:00'),
(12, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:46:41'),
(13, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:10:53'),
(14, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:24:32'),
(15, NULL, 'nalyst@security.local', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:27:16'),
(16, 2, 'analyst@security.local', '::1', 'ACCOUNT_INACTIVE', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:27:39'),
(17, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:28:47'),
(18, 2, 'analyst@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:30:00'),
(19, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:40:06'),
(20, NULL, 'admin@security.loca', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:51:07'),
(21, 1, 'admin@security.local', '::1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:51:11'),
(22, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 01:51:18'),
(23, NULL, 'abhishekanand3385@gmail.com', '127.0.0.1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:154.0) Gecko/20100101 Firefox/154.0', '2026-08-24 01:53:38'),
(24, NULL, 'abhishekanand3385@gmail.com', '127.0.0.1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:154.0) Gecko/20100101 Firefox/154.0', '2026-08-24 01:53:44'),
(25, NULL, 'abhishekanand3385@gmail.com', '127.0.0.1', 'LOGIN_FAILED', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:154.0) Gecko/20100101 Firefox/154.0', '2026-08-24 01:55:37'),
(26, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 11:34:36'),
(27, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 12:53:28'),
(28, 1, 'admin@security.local', '::1', 'LOGIN_SUCCESS', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 13:36:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','analyst','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@security.local', '$2y$10$X1qvurVa55PLoXNJ/ZcoEepaGZd.HGZad7tgtN6kLMQsN.rlfoWs2', 'admin', 'active', '2026-08-23 17:50:46'),
(2, 'Security Analyst', 'analyst@security.local', '$2y$10$WuTp2HaFTHOnzm4Mstu9D.q.ql.AxJn3c5nQezlW5oYT2UNcA9gDC', 'analyst', 'inactive', '2026-08-23 17:56:48'),
(3, 'Security User', 'user@security.local', '$2y$10$ZmRQH7dJX71kqk1d424nzur3m.J7Vh8dzXOhC6SZ2WpS74S2fKwjS', 'user', 'inactive', '2026-08-23 17:57:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `incident_activity`
--
ALTER TABLE `incident_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `incident_attachments`
--
ALTER TABLE `incident_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `incident_categories`
--
ALTER TABLE `incident_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `incident_comments`
--
ALTER TABLE `incident_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_id` (`incident_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `incident_evidence`
--
ALTER TABLE `incident_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_id` (`incident_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email_ip` (`email`,`ip_address`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `login_audit_logs`
--
ALTER TABLE `login_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `incident_activity`
--
ALTER TABLE `incident_activity`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `incident_attachments`
--
ALTER TABLE `incident_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_categories`
--
ALTER TABLE `incident_categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `incident_comments`
--
ALTER TABLE `incident_comments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `incident_evidence`
--
ALTER TABLE `incident_evidence`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_audit_logs`
--
ALTER TABLE `login_audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `incident_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incident_activity`
--
ALTER TABLE `incident_activity`
  ADD CONSTRAINT `incident_activity_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incident_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `incident_attachments`
--
ALTER TABLE `incident_attachments`
  ADD CONSTRAINT `incident_attachments_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incident_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
