-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2025 at 11:11 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cail9460_group`
--

-- --------------------------------------------------------

--
-- Table structure for table `groupware_branches`
--

CREATE TABLE `groupware_branches` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_kana` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `groupware_branches`
--

INSERT INTO `groupware_branches` (`id`, `name`, `name_kana`, `postal_code`, `address1`, `address2`, `tel`, `fax`, `email`, `type`, `description`, `created_at`, `updated_at`) VALUES
(1, '名古屋本社', '', '464-0066', '愛知県名古屋市千種区池下町2-15', 'ハクビ池下ビル8F', '052-757-3255', '052-757-3353', 'guis@guis.co.jp', '1', '', '2025-05-22 10:53:53', '2025-05-22 10:56:00'),
(2, '東京オフィス', '', '102-0093', '東京都千代田区平河町1丁目3番13号', 'サークルズ平河町9F', '052-757-3255', '052-757-3353', 'guis@guis.co.jp', '2', '', '2025-05-22 10:55:52', '2025-05-22 10:56:33');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_departments`
--

CREATE TABLE `groupware_departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `can_project` varchar(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `groupware_departments`
--

INSERT INTO `groupware_departments` (`id`, `name`, `description`, `created_at`, `updated_at`, `can_project`) VALUES
(1, '設備設計(技)', '', '2025-05-22 01:56:54', '2025-05-22 01:56:54', '1'),
(2, '設備設計(管)', '', '2025-05-22 01:57:01', '2025-05-22 01:57:01', '1'),
(3, '省エネ計算(技)', '', '2025-05-22 01:57:09', '2025-05-22 01:57:09', '1'),
(4, '省エネ計算(管)', '', '2025-05-22 01:57:31', '2025-05-22 01:57:31', '1'),
(5, '意匠設計(管)', '', '2025-05-22 01:57:40', '2025-05-22 01:57:40', '1'),
(6, 'なし', '', '2025-05-22 08:03:14', '2025-05-22 08:33:48', '0');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_projects`
--

CREATE TABLE `groupware_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `estimated_hours` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_project_members`
--

CREATE TABLE `groupware_project_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` mediumtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(50) DEFAULT 'member'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_tasks`
--

CREATE TABLE `groupware_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `priority` varchar(50) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `estimated_hours` float DEFAULT NULL,
  `actual_hours` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_user_department`
--

CREATE TABLE `groupware_user_department` (
  `id` int(11) NOT NULL,
  `userid` varchar(51) NOT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `groupware_user_department`
--

INSERT INTO `groupware_user_department` (`id`, `userid`, `department_id`) VALUES
(19, 'admin', 6),
(21, 'saito', 5),
(22, 'saito', 3),
(25, 'takasaki', 5),
(26, 'kobayashi_h', 5),
(27, 'minoura', 5),
(28, 'minoura', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `groupware_branches`
--
ALTER TABLE `groupware_branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_departments`
--
ALTER TABLE `groupware_departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_projects`
--
ALTER TABLE `groupware_projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_project_members`
--
ALTER TABLE `groupware_project_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_tasks`
--
ALTER TABLE `groupware_tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_user_department`
--
ALTER TABLE `groupware_user_department`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `groupware_branches`
--
ALTER TABLE `groupware_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `groupware_departments`
--
ALTER TABLE `groupware_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `groupware_projects`
--
ALTER TABLE `groupware_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_project_members`
--
ALTER TABLE `groupware_project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_tasks`
--
ALTER TABLE `groupware_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_user_department`
--
ALTER TABLE `groupware_user_department`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
