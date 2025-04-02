-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 02, 2025 at 09:58 AM
-- Server version: 10.4.25-MariaDB
-- PHP Version: 7.4.30

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
-- Table structure for table `achieve`
--

CREATE TABLE `achieve` (
  `achieve_id` int(11) NOT NULL,
  `achieve_order` int(11) NOT NULL,
  `date_create` varchar(15) NOT NULL,
  `achieve_status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `adressデータ`
--

CREATE TABLE `adressデータ` (
  `id` int(2) DEFAULT NULL,
  `folder_id` int(1) DEFAULT NULL,
  `addressbook_type` int(1) DEFAULT NULL,
  `addressbook_name` varchar(42) DEFAULT NULL,
  `addressbook_ruby` varchar(15) DEFAULT NULL,
  `addressbook_company` varchar(42) DEFAULT NULL,
  `addressbook_companyruby` varchar(63) DEFAULT NULL,
  `addressbook_department` varchar(18) DEFAULT NULL,
  `addressbook_position` varchar(15) DEFAULT NULL,
  `addressbook_postcode` varchar(8) DEFAULT NULL,
  `addressbook_address` varchar(48) DEFAULT NULL,
  `addressbook_addressruby` varchar(10) DEFAULT NULL,
  `addressbook_phone` varchar(19) DEFAULT NULL,
  `addressbook_fax` varchar(13) DEFAULT NULL,
  `addressbook_mobile` varchar(13) DEFAULT NULL,
  `addressbook_email` varchar(30) DEFAULT NULL,
  `addressbook_url` varchar(10) DEFAULT NULL,
  `addressbook_comment` varchar(51) DEFAULT NULL,
  `addressbook_parent` int(1) DEFAULT NULL,
  `public_level` int(1) DEFAULT NULL,
  `public_group` varchar(10) DEFAULT NULL,
  `public_user` varchar(10) DEFAULT NULL,
  `edit_level` int(1) DEFAULT NULL,
  `edit_group` varchar(10) DEFAULT NULL,
  `edit_user` varchar(10) DEFAULT NULL,
  `owner` varchar(5) DEFAULT NULL,
  `editor` varchar(5) DEFAULT NULL,
  `created` varchar(19) DEFAULT NULL,
  `updated` varchar(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_addressbook`
--

CREATE TABLE `groupware_addressbook` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `addressbook_type` int(11) NOT NULL,
  `addressbook_name` mediumtext DEFAULT NULL,
  `addressbook_ruby` mediumtext DEFAULT NULL,
  `addressbook_company` mediumtext DEFAULT NULL,
  `addressbook_companyruby` mediumtext DEFAULT NULL,
  `addressbook_department` mediumtext DEFAULT NULL,
  `addressbook_position` mediumtext DEFAULT NULL,
  `addressbook_postcode` mediumtext DEFAULT NULL,
  `addressbook_address` mediumtext DEFAULT NULL,
  `addressbook_addressruby` mediumtext DEFAULT NULL,
  `addressbook_phone` mediumtext DEFAULT NULL,
  `addressbook_fax` mediumtext DEFAULT NULL,
  `addressbook_mobile` mediumtext DEFAULT NULL,
  `addressbook_email` mediumtext DEFAULT NULL,
  `addressbook_url` mediumtext DEFAULT NULL,
  `addressbook_comment` mediumtext DEFAULT NULL,
  `addressbook_parent` int(11) DEFAULT NULL,
  `public_level` int(11) NOT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_addressbook`
--

INSERT INTO `groupware_addressbook` (`id`, `folder_id`, `addressbook_type`, `addressbook_name`, `addressbook_ruby`, `addressbook_company`, `addressbook_companyruby`, `addressbook_department`, `addressbook_position`, `addressbook_postcode`, `addressbook_address`, `addressbook_addressruby`, `addressbook_phone`, `addressbook_fax`, `addressbook_mobile`, `addressbook_email`, `addressbook_url`, `addressbook_comment`, `addressbook_parent`, `public_level`, `public_group`, `public_user`, `edit_level`, `edit_group`, `edit_user`, `owner`, `editor`, `created`, `updated`) VALUES
(75, 2, 0, '日本建設　清岡様', NULL, '日本建設', 'にほんせっけい', '営業部 設計課', NULL, NULL, NULL, NULL, '052-587-7831', '052-587-7832', NULL, 'kiyooka@nihonkensetsu.co.jp', NULL, '柳ヶ瀬', 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(74, 2, 0, '原田設備設計　原田様', 'はらだ', '原田設備設計', 'はらだつびせっけい', NULL, NULL, NULL, NULL, NULL, '0562-46-8745', '0562-46-8748', NULL, 'm-harada@ma.medias.ne.jp', NULL, '柳ヶ瀬　衛生', 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(78, 3, 0, '神戸支店　設計課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '神戸支店', '設計課', '', '', '', '078-927-6265', '', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:29:49'),
(77, 3, 0, '神戸支店 業務課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '神戸支店', '業務課', '', '', '', '078-927-6200', '', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:29:34'),
(76, 2, 0, ' 富士防災設備　長坂様', NULL, ' 富士防災設備', 'ふじぼうさいせつび', '名古屋支店', NULL, NULL, NULL, NULL, '052-413-3911', '250-412-2285', NULL, 'nagasaka@fbs-na.co.jp', NULL, '柳ヶ瀬 防災設備調査', 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(79, 3, 0, '京都支店　業務課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '京都支店', '業務課', '612-8443', '京都府京都市伏見区竹田藁屋町７２　アクアパレス１階', '', '075-604-5401', '075-604-5417', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:30:11'),
(80, 3, 0, '京都支店　設計課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '京都支店', '設計課', '612-8443', '京都府京都市伏見区竹田藁屋町７２　アクアパレス１階', '', '075-604-5407', '075-604-5417', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:30:26'),
(81, 3, 0, '千葉南支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '千葉南支店', NULL, NULL, NULL, NULL, '0436-24-7151', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(82, 3, 0, '下関支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '下関支店', NULL, NULL, NULL, NULL, '083-257-3425', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(83, 3, 0, '沼津支店　設計課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '沼津支店', '設計課', '', '', '', '055-976-1026', '055-976-1006', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:30:44'),
(84, 3, 0, '浜松支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '浜松支店', NULL, NULL, NULL, NULL, '053-448-7092', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(85, 3, 0, '埼玉南支店　設計課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '埼玉南支店', '設計課', '353-0002', '埼玉県志木市中宗岡1-11-12　カンファートゥリー　１Ｆ', '', '048-476-2646', '048-476-2679', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:31:06'),
(87, 3, 0, '川越支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '川越支店', NULL, NULL, NULL, NULL, '049-247-3551', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(88, 3, 0, '富山支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '富山支店', NULL, NULL, NULL, NULL, '0764-92-8700', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(86, 3, 0, '岡崎支店　設計課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '岡崎支店', '設計課', '', '', '', '0564-58-1911', '', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:31:23'),
(89, 3, 0, '豊川支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '豊川支店', NULL, NULL, NULL, NULL, '053-389-0050', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(91, 3, 0, '豊田支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '豊田支店', NULL, NULL, NULL, NULL, '0565-35-2292', '0565-35-2296', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(90, 3, 0, '大和支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '大和支店', NULL, NULL, NULL, NULL, '0462-782-666', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(93, 3, 0, '小牧支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '小牧支店', NULL, NULL, NULL, NULL, '0568-75-6131', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(94, 3, 0, '川崎東支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '川崎東支店', NULL, NULL, NULL, NULL, '0442-10-1063', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(92, 3, 0, '春日井支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '春日井支店', NULL, NULL, NULL, NULL, '0568-877-320', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(95, 3, 0, '舟橋支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '舟橋支店', NULL, NULL, NULL, NULL, '0474-33-5031', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(96, 3, 0, '半田支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '半田支店', NULL, NULL, NULL, NULL, '0569-23-0361', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(98, 3, 0, '品川支店　設計課', '', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '品川支店', '設計課', '', '', '', '03-5461-4753', '', '', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-22 12:31:59'),
(97, 3, 0, '品川支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '品川支店', NULL, NULL, NULL, NULL, '03-3472-0931', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(99, 3, 0, '奈良支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '奈良支店', NULL, NULL, NULL, NULL, '074-236-0053', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(100, 3, 0, '掛川支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '掛川支店', NULL, NULL, NULL, NULL, '0537-23-8551', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(102, 3, 0, '富山支店　設計課　中路様（携帯）', 'なかじ', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '富山支店', '設計課', '', '', '', '090-6918-9181', '', '090-6918-9181', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-25 10:51:05'),
(101, 3, 0, '京都北支店', NULL, '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '京都北支店', NULL, NULL, NULL, NULL, '075-703-3580', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'admin', 'admin', '2011-08-19 17:03:32', NULL),
(104, 3, 0, '岡崎支店　設計課　坂上様（携帯）', 'さかうえ', '大東建託株式会社', 'だいとうけんたくかぶしきがいしゃ', '岡崎支店', '設計課', '', '', '', '090-6767-3936', '', '090-6767-3936', '', '', '', 0, 0, '', '', 0, '', '', 'admin', 'sawada', '2011-08-19 17:03:32', '2011-08-25 10:50:39');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_bookmark`
--

CREATE TABLE `groupware_bookmark` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `bookmark_title` mediumtext DEFAULT NULL,
  `bookmark_name` mediumtext DEFAULT NULL,
  `bookmark_url` mediumtext DEFAULT NULL,
  `bookmark_date` mediumtext DEFAULT NULL,
  `bookmark_comment` mediumtext DEFAULT NULL,
  `bookmark_order` int(11) DEFAULT NULL,
  `public_level` int(11) NOT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_config`
--

CREATE TABLE `groupware_config` (
  `id` int(11) NOT NULL,
  `config_type` mediumtext NOT NULL,
  `config_key` mediumtext NOT NULL,
  `config_value` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL,
  `config_name` varchar(50) DEFAULT '正社員'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_config`
--

INSERT INTO `groupware_config` (`id`, `config_type`, `config_key`, `config_value`, `owner`, `editor`, `created`, `updated`, `config_name`) VALUES
(1, 'timecard', 'openhour', '7', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(2, 'timecard', 'openminute', '30', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(3, 'timecard', 'closehour', '17', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(4, 'timecard', 'closeminute', '0', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(5, 'timecard', 'timeround', '0', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(6, 'timecard', 'lunchopenhour', '11', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(7, 'timecard', 'lunchopenminute', '30', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(8, 'timecard', 'lunchclosehour', '13', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(9, 'timecard', 'lunchcloseminute', '0', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(10, 'timecard', 'intervalround', '0', 'admin', 'admin', '2011-07-14 13:18:18', '2024-09-26 15:49:39', 'NV chính thức'),
(11, 'timecard20240925092540', 'openhour', '7', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(12, 'timecard20240925092540', 'openminute', '30', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(13, 'timecard20240925092540', 'closehour', '16', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(14, 'timecard20240925092540', 'closeminute', '0', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(15, 'timecard20240925092540', 'timeround', '0', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(16, 'timecard20240925092540', 'lunchopenhour', '11', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(17, 'timecard20240925092540', 'lunchopenminute', '30', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(18, 'timecard20240925092540', 'lunchclosehour', '12', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(19, 'timecard20240925092540', 'lunchcloseminute', '0', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt'),
(20, 'timecard20240925092540', 'intervalround', '0', 'admin', 'admin', '2024-09-25 09:26:19', '2024-09-26 15:50:17', 'TH Đặc biệt');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_dayoff`
--

CREATE TABLE `groupware_dayoff` (
  `id` int(11) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `date_start` varchar(20) NOT NULL,
  `date_end` varchar(20) DEFAULT NULL,
  `time_start` varchar(10) DEFAULT NULL,
  `time_end` varchar(10) DEFAULT NULL,
  `allday` varchar(6) DEFAULT NULL,
  `offtype` varchar(50) NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `confirm_userid` varchar(50) DEFAULT NULL,
  `confirm_real_userid` varchar(50) DEFAULT NULL,
  `confirm_real_name` varchar(50) DEFAULT NULL,
  `confirm_name` varchar(50) DEFAULT NULL,
  `confirm_date` varchar(20) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `comment` varchar(200) DEFAULT NULL,
  `created_time` varchar(20) NOT NULL,
  `req_edit_by` varchar(50) DEFAULT NULL,
  `req_edit_time` varchar(20) DEFAULT NULL,
  `req_edit_name` varchar(50) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 0,
  `update_date` varchar(20) NOT NULL,
  `update_by` varchar(20) NOT NULL,
  `minus_leave` varchar(1) DEFAULT '1',
  `is_repeat` varchar(1) DEFAULT '0',
  `cancel_repeat_dates` text DEFAULT NULL,
  `is_overwork` varchar(1) DEFAULT NULL,
  `is_BHXH` varchar(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_dayoff`
--

INSERT INTO `groupware_dayoff` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`, `minus_leave`, `is_repeat`, `cancel_repeat_dates`, `is_overwork`, `is_BHXH`) VALUES
(1, 'long', 14, '2024-09-13', '2024-09-13', '00:00', '00:00', 'True', 'Nghỉ phép', 'Có việc bận cá nhân, xin nghỉ phép năm', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '01/08/2024 09:06', '1', '', '01/08/2024 07:32', NULL, NULL, NULL, 1, '2024-08-01 09:06', 'hoai', '1', '0', NULL, NULL, NULL),
(2, 'quocthinh_web', 7, '2024-10-03', '2024-10-07', '00:00', '00:00', 'True', 'Việc cá nhân', 'Lý do bận việc cá nhân, đã đăng với chị Takasaki vào tháng 12/06/2024', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '26/09/2024 08:01', '1', '', '01/08/2024 07:36', 'minhthanhonly', '26/09/2024 07:59', 'Dinh Minh Thanh', 6, '2024-09-26 08:01', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(3, 'ly', 14, '2024-08-02', '2024-08-02', '13:00', '17:00', 'False', 'Nghỉ phép', 'bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '01/08/2024 07:47', '1', '', '01/08/2024 07:37', NULL, NULL, NULL, 1, '2024-08-01 07:47', 'bi', '1', '0', NULL, NULL, NULL),
(4, 'vanphuc', 7, '2024-08-26', '2024-08-26', '00:00', '00:00', 'True', 'Nghỉ phép', 'Có việc gia đình.', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '01/08/2024 08:38', '1', '', '01/08/2024 07:50', NULL, NULL, NULL, 1, '2024-08-01 08:38', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(5, 'vanphuc', 7, '2024-08-02', '2024-08-02', '00:00', '00:00', 'True', 'Tang người thân', 'Người nhà mất.', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '01/08/2024 08:05', '1', 'ok', '01/08/2024 07:52', NULL, NULL, NULL, 1, '2024-08-01 08:06', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(6, 'chautuan', 11, '2024-08-02', '2024-08-02', '13:00', '17:00', 'False', 'Nghỉ phép', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '02/08/2024 07:12', '1', 'OK', '01/08/2024 07:55', NULL, NULL, NULL, 1, '2024-08-02 07:12', 'thanh', '1', '0', NULL, NULL, NULL),
(7, 'bien', 15, '2024-08-15', '2024-08-16', '00:00', '00:00', 'True', 'Nghỉ phép', 'nghỉ phép', 'ngocthuy', 'bien', 'Huynh Trong Bien', 'Luong Ngoc Thuy', '01/08/2024 08:35', '3', '', '01/08/2024 08:31', NULL, NULL, NULL, 1, '2024-08-01 08:35', 'bien', '1', '0', NULL, NULL, NULL),
(8, 'bien', 15, '2024-08-26', '2024-08-30', '00:00', '00:00', 'True', 'Nghỉ phép', 'việc riêng', 'ngocthuy', 'bien', 'Huynh Trong Bien', 'Luong Ngoc Thuy', '01/08/2024 08:35', '3', '', '01/08/2024 08:34', NULL, NULL, NULL, 1, '2024-08-01 08:35', 'bien', '1', '0', NULL, NULL, NULL),
(9, 'hoai', 14, '2024-08-02', '2024-08-02', '00:00', '00:00', 'True', 'Nghỉ phép', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '01/08/2024 08:59', '1', 'Chúc e nghỉ vui', '01/08/2024 08:52', 'dat', '01/08/2024 08:54', 'Tran Vinh Dat', 3, '2024-08-01 08:59', 'dat', '1', '0', NULL, NULL, NULL),
(10, 'bi', 14, '2024-08-02', '2024-08-02', '13:00', '17:00', 'False', 'Nghỉ phép', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '01/08/2024 09:00', '1', 'Chúc e nghỉ dui', '01/08/2024 08:57', NULL, NULL, NULL, 1, '2024-08-01 09:00', 'dat', '1', '0', NULL, NULL, NULL),
(12, 'van', 12, '2024-08-01', '2024-08-01', '00:00', '00:00', 'True', 'Nghỉ phép', 'Giỗ ba em', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '01/08/2024 09:32', '1', '', '01/08/2024 09:28', NULL, NULL, NULL, 1, '2024-08-01 09:32', 'hien', '1', '0', NULL, NULL, NULL),
(13, 'minhthanhonly', 7, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Giỗ', 'Về quê', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '22/08/2024 14:38', '1', 'Duyệt tự động', '01/08/2024 09:54', NULL, NULL, NULL, 2, '2024-08-22 14:38', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(14, 'trieu', 17, '2024-08-02', '2024-08-02', '00:00', '00:00', 'True', 'Nghỉ phép', 'nghỉ phép việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '01/08/2024 13:02', '1', 'Đồng ý', '01/08/2024 13:01', NULL, NULL, NULL, 1, '2024-08-01 13:03', 'quyen', '1', '0', NULL, NULL, NULL),
(15, 'truong', 15, '2024-08-26', '2024-08-30', '00:00', '00:00', 'True', 'Khác', 'cùng gia đình về quê giỗ ông nội.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '01/08/2024 13:21', '1', '', '01/08/2024 13:20', NULL, NULL, NULL, 1, '2024-08-01 13:21', 'bien', '1', '0', NULL, NULL, NULL),
(16, 'bien', 15, '2024-08-15', '2024-08-16', '00:00', '00:00', 'True', 'Nghỉ phép', 'NGHI ĐI CHƠI', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '01/08/2024 14:56', '1', 'đã xác nhận ', '01/08/2024 14:22', NULL, NULL, NULL, 1, '2024-08-01 14:56', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(17, 'thanh', 11, '2024-08-01', '2024-08-02', '00:00', '00:00', 'True', 'Nghỉ phép', 'Nghỉ phép ', 'tranvinh.loc', 'thanh', 'Doan Huu Thanh', 'Tran Vinh Loc', '20/08/2024 17:01', '3', 'Trùng đơn đăng ký, hãy hủy', '02/08/2024 08:44', 'minhthanhonly', '20/08/2024 13:53', 'Dinh Minh Thanh', 5, '2024-08-20 17:01', 'thanh', '1', '0', NULL, NULL, NULL),
(18, 'thanh', 11, '2024-08-01', '2024-08-01', '00:00', '00:00', 'True', 'Nghỉ phép', 'Nghỉ phép', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '02/08/2024 10:48', '1', '', '02/08/2024 08:45', NULL, NULL, NULL, 1, '2024-08-02 08:48', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(19, 'tuyen', 8, '2024-08-02', '2024-08-02', '13:00', '17:00', 'False', 'Nghỉ phép', 'Nghỉ ốm', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '02/08/2024 08:52', '1', 'Đã xác nhận', '02/08/2024 08:46', NULL, NULL, NULL, 1, '2024-08-02 08:51', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(20, 'ngoc', 10, '2024-08-02', '2024-08-02', '15:00', '17:00', 'False', 'Về sớm', 'mở lại thẻ ngân hàng bị khóa', 'nhan', 'minhthanhonly', 'Dinh Minh Thanh', 'Diep Thanh Nhan', '22/08/2024 12:52', '2', 'Trường hợp không cần đăng ký phép.', '02/08/2024 09:32', NULL, NULL, NULL, 2, '2024-08-22 12:52', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(21, 'thiet', 14, '2024-08-05', '2024-08-05', '00:00', '00:00', 'True', 'Nghỉ phép', 'Có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '02/08/2024 11:33', '1', '', '02/08/2024 11:33', NULL, NULL, NULL, 1, '2024-08-02 11:33', 'bi', '1', '0', NULL, NULL, NULL),
(22, 'tra_web', 7, '2024-08-12', '2024-08-14', '00:00', '00:00', 'True', 'Nghỉ phép', 'Nghỉ Phép', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '02/08/2024 15:00', '1', '', '02/08/2024 14:51', NULL, NULL, NULL, 1, '2024-08-02 15:00', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(23, 'ngocle', 17, '2024-08-05', '2024-08-05', '00:00', '00:00', 'True', 'Nghỉ phép', 'Việc riêng', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '05/08/2024 07:06', '1', '', '05/08/2024 06:49', NULL, NULL, NULL, 1, '2024-08-05 07:06', 'nguyen', '1', '0', NULL, NULL, NULL),
(24, 'bich', 17, '2024-08-05', '2024-08-05', '01:00', '17:00', 'True', 'Nghỉ phép', 'đưa con đi khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '05/08/2024 11:19', '1', 'Đồng ý cho nghỉ phép', '05/08/2024 11:18', NULL, NULL, NULL, 1, '2024-08-05 11:19', 'khang', '1', '0', NULL, NULL, NULL),
(25, 'khanh', 8, '2024-08-06', '2024-08-06', '07:30', '11:30', 'False', 'Nghỉ phép', 'Ban viec gia dinh.', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '05/08/2024 16:34', '1', 'Đã xác nhận ', '05/08/2024 16:33', NULL, NULL, NULL, 1, '2024-08-05 16:34', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(26, 'duya', 18, '2024-08-09', '2024-08-09', '00:00', '00:00', 'True', 'Nghỉ phép', 'Nghỉ phép năm.', 'tester', 'duya', 'Do Van Duya', 'tester', '05/08/2024 16:54', '3', '', '05/08/2024 16:49', NULL, NULL, NULL, 1, '2024-08-05 16:54', 'duya', '1', '0', NULL, NULL, NULL),
(27, 'duya', 18, '2024-08-09', '2024-08-09', '00:00', '00:00', 'True', 'Nghỉ phép', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '06/08/2024 07:12', '1', '', '05/08/2024 16:54', NULL, NULL, NULL, 1, '2024-08-06 07:12', 'luan', '1', '0', NULL, NULL, NULL),
(28, 'thuylinh', 18, '2024-09-27', '2024-09-30', '00:00', '00:00', 'True', 'Nghỉ phép', 'nghỉ phép năm', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '06/08/2024 07:12', '1', '', '05/08/2024 16:58', NULL, NULL, NULL, 1, '2024-08-06 07:12', 'luan', '1', '0', NULL, NULL, NULL),
(29, 'bich', 17, '2024-08-06', '2024-08-07', '00:00', '00:00', 'True', 'Chăm bệnh', 'con bị bệnh nghỉ BHXH', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '25/09/2024 12:59', '1', 'Hãy chọn loại nghỉ BHXH', '06/08/2024 07:04', 'minhthanhonly', '24/09/2024 12:48', 'Dinh Minh Thanh', 3, '2024-09-25 12:59', 'khang', '1', '0', NULL, NULL, NULL),
(30, 'bi', 14, '2024-08-05', '2024-08-05', '00:00', '00:00', 'True', 'Việc đột xuất', 'Bận việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '06/08/2024 08:48', '1', 'chúc e nghỉ dui', '06/08/2024 08:15', NULL, NULL, NULL, 1, '2024-08-06 08:48', 'dat', '1', '0', NULL, NULL, NULL),
(31, 'hoai', 14, '2024-08-05', '2024-08-05', '00:00', '00:00', 'True', 'Việc đột xuất', 'Bận việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '06/08/2024 08:48', '1', 'chúc e nghỉ dui', '06/08/2024 08:15', NULL, NULL, NULL, 1, '2024-08-06 08:48', 'dat', '1', '0', NULL, NULL, NULL),
(32, 'ly', 14, '2024-08-05', '2024-08-05', '00:00', '00:00', 'True', 'Việc đột xuất', 'Bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '06/08/2024 08:17', '1', '', '06/08/2024 08:17', NULL, NULL, NULL, 1, '2024-08-06 08:17', 'bi', '1', '0', NULL, NULL, NULL),
(33, 'tu_web', 7, '2024-08-01', '2024-08-02', '00:00', '00:00', 'True', 'Khác', 'Đầy tháng cháu', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '06/08/2024 08:38', '1', '', '06/08/2024 08:35', NULL, NULL, NULL, 1, '2024-08-06 08:38', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(34, 'tam', 8, '2024-09-05', '2024-09-06', '00:00', '00:00', 'True', 'Khác', 'việc gia đình', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '06/08/2024 15:13', '1', 'Đã xác nhận', '06/08/2024 15:10', NULL, NULL, NULL, 1, '2024-08-06 15:12', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(35, 'hien', 12, '2024-08-09', '2024-08-12', '00:00', '00:00', 'True', 'Khác', 'Về quê', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/08/2024 09:51', '1', '\r\n', '07/08/2024 07:28', 'tranvinh.loc', '07/08/2024 09:50', 'Tran Vinh Loc', 4, '2024-08-07 07:51', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(36, 'huyhoang', 17, '2024-08-09', '2024-08-09', '07:30', '17:00', 'False', 'Ra ngoài', 'Bận việc cá nhân', 'minhthomonly', 'huyhoang', 'Nguyen Huy Hoang', 'Dinh Minh Thom', '08/08/2024 14:26', '3', 'Hoàng đăng kí nhầm nên xuất 2 lần,\r\nđề nghị hệ thống xóa bỏ đăng kí này', '08/08/2024 07:23', 'minhthanhonly', '08/08/2024 14:05', 'Dinh Minh Thanh', 6, '2024-08-08 14:26', 'huyhoang', '1', '0', NULL, NULL, NULL),
(37, 'thanh', 11, '2024-08-08', '2024-08-08', '13:00', '17:00', 'False', 'Việc đột xuất', 'nghi phep buoi chieu', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '08/08/2024 09:17', '1', '', '08/08/2024 07:12', NULL, NULL, NULL, 1, '2024-08-08 07:17', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(38, 'huyhoang', 17, '2024-08-09', '2024-08-09', '00:00', '00:00', 'True', 'Khác', 'Bận việc cá nhân.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '08/08/2024 07:24', '1', '', '08/08/2024 07:23', NULL, NULL, NULL, 1, '2024-08-08 07:24', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(39, 'dai', 14, '2024-08-08', '2024-08-08', '07:30', '11:30', 'False', 'Việc đột xuất', 'Có việc riêng', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '08/08/2024 12:58', '1', '', '08/08/2024 12:55', NULL, NULL, NULL, 1, '2024-08-08 12:58', 'bi', '1', '0', NULL, NULL, NULL),
(40, 'minhthomonly', 17, '2024-08-12', '2024-08-12', '00:00', '00:00', 'True', 'Du lịch', 'nghỉ phép năm', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '08/08/2024 13:33', '1', 'Duyệt tự động', '08/08/2024 13:33', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, NULL),
(41, 'lien', 9, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Giỗ', 'về quê ăn đám giỗ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '09/08/2024 09:34', '1', '', '09/08/2024 07:31', NULL, NULL, NULL, 1, '2024-08-09 07:34', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(42, 'tham', 20, '2024-08-15', '2024-08-16', '00:00', '00:00', 'True', 'Khác', 'Về quê', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '09/08/2024 15:00', '1', 'đồng ý', '09/08/2024 07:42', NULL, NULL, NULL, 1, '2024-08-09 15:01', 'huy', '1', '0', NULL, NULL, NULL),
(43, 'ly', 14, '2024-08-09', '2024-08-09', '13:00', '17:00', 'False', 'Việc đột xuất', 'Bận việc đột xuất', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '09/08/2024 11:57', '1', '', '09/08/2024 11:56', NULL, NULL, NULL, 1, '2024-08-09 11:57', 'bi', '1', '0', NULL, NULL, NULL),
(44, 'tranvinh.loc', 1, '2024-08-12', '2024-08-12', '00:00', '00:00', 'True', 'Giảm stress', 'bên Nhật nghỉ lễ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '09/08/2024 14:37', '1', '', '09/08/2024 14:35', NULL, NULL, NULL, 1, '2024-08-09 14:37', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(45, 'huy', 20, '2024-08-14', '2024-08-14', '00:00', '00:00', 'True', 'Khác', 'việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '09/08/2024 17:25', '1', '', '09/08/2024 15:02', NULL, NULL, NULL, 1, '2024-08-09 15:25', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(46, 'duyhoang', 17, '2024-08-12', '2024-08-12', '00:00', '00:00', 'True', 'Giảm stress', 'nghỉ phép', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '09/08/2024 15:24', '1', '', '09/08/2024 15:23', NULL, NULL, NULL, 1, '2024-08-09 15:24', 'nguyen', '1', '0', NULL, NULL, NULL),
(47, 'vi', 14, '2024-08-30', '2024-08-30', '00:00', '00:00', 'True', 'Khác', 'Bận việc cá nhân', 'hoai', 'vi', 'Huynh Tuong Vi', 'Nguyen Thi Hoai', '09/08/2024 15:43', '3', '', '09/08/2024 15:40', NULL, NULL, NULL, 1, '2024-08-09 15:43', 'vi', '1', '0', NULL, NULL, NULL),
(48, 'vi', 14, '2024-08-09', '2024-08-09', '00:00', '00:00', 'True', 'Khác', 'nghỉ phép', 'hoai', 'vi', 'Huynh Tuong Vi', 'Nguyen Thi Hoai', '09/08/2024 15:44', '3', '', '09/08/2024 15:42', NULL, NULL, NULL, 2, '2024-08-09 15:44', 'vi', '1', '0', NULL, NULL, NULL),
(49, 'vi', 14, '2024-08-30', '2024-08-30', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '09/08/2024 15:51', '1', '', '09/08/2024 15:45', 'hoai', '09/08/2024 15:51', 'Nguyen Thi Hoai', 4, '2024-08-09 15:51', 'hoai', '1', '0', NULL, NULL, NULL),
(50, 'dat', 14, '2024-08-12', '2024-08-12', '13:00', '17:00', 'False', 'Việc đột xuất', 'có việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/08/2024 12:52', '1', '', '12/08/2024 07:13', NULL, NULL, NULL, 1, '2024-08-13 12:52', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(51, 'ha', 10, '2024-08-15', '2024-08-15', '00:00', '00:00', 'True', 'Khác', 'lí do cá nhân', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '12/08/2024 07:27', '1', 'Leader ok!', '12/08/2024 07:21', NULL, NULL, NULL, 1, '2024-08-12 07:27', 'nhan', '1', '0', NULL, NULL, NULL),
(52, 'bi', 14, '2024-08-30', '2024-08-30', '13:00', '17:00', 'False', 'Khác', 'Có việc riêng', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '12/08/2024 07:29', '1', 'Chúc e nghỉ dui dẻ', '12/08/2024 07:28', NULL, NULL, NULL, 1, '2024-08-12 07:30', 'dat', '1', '0', NULL, NULL, NULL),
(53, 'chautuan', 11, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Khác', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '12/08/2024 07:45', '1', 'OK', '12/08/2024 07:44', NULL, NULL, NULL, 1, '2024-08-12 07:45', 'thanh', '1', '0', NULL, NULL, NULL),
(54, 'hoai', 14, '2024-08-30', '2024-08-30', '13:00', '17:00', 'False', 'Khác', 'Bận việc riêng', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '12/08/2024 11:34', '1', 'chúc e nghỉ thiệt dui', '12/08/2024 09:22', NULL, NULL, NULL, 1, '2024-08-12 11:34', 'dat', '1', '0', NULL, NULL, NULL),
(55, 'thoi', 17, '2024-08-30', '2024-08-30', '00:00', '00:00', 'True', 'Khác', 'về quê ', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '12, 08, 2024 11:07', '1', '', '12/08/2024 11:06', NULL, NULL, NULL, 1, '2024-08-12 11:07', 'huyhoang', '1', '0', NULL, NULL, NULL),
(56, 'nhu', 17, '2024-08-14', '2024-08-14', '00:00', '00:00', 'True', 'Khám bệnh', 'Khám bệnh ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/08/2024 12:52', '1', '', '13/08/2024 07:45', NULL, NULL, NULL, 1, '2024-08-13 12:52', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(57, 'duykhanh', 17, '2024-08-19', '2024-08-19', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép, có việc riêng', 'quyen', 'duykhanh', 'Bui Duy Khanh', 'Nguyen Thi Thu Quyen', '13/08/2024 09:04', '3', '', '13/08/2024 08:48', NULL, NULL, NULL, 1, '2024-08-13 09:04', 'duykhanh', '1', '0', NULL, NULL, NULL),
(58, 'vutrang', 14, '2024-08-13', '2024-08-13', '07:30', '11:30', 'False', 'Bệnh', 'bị đau bụng', 'bi', 'bi', 'Nguyen Van Bi', 'Tran Vinh Dat', '13/08/2024 13:06', '1', '', '13/08/2024 12:53', NULL, NULL, NULL, 2, '2024-08-13 13:06', 'bi', '1', '0', NULL, NULL, NULL),
(59, 'khanh', 8, '2024-08-13', '2024-08-13', '13:00', '17:00', 'False', 'Giỗ', 'Ban viec gia dinh', 'tuyet2015', 'khanh', 'Diep Hoang Khanh', 'Huynh Thi Anh Tuyet', '13/08/2024 13:04', '3', '', '13/08/2024 13:02', NULL, NULL, NULL, 1, '2024-08-13 13:04', 'khanh', '1', '0', NULL, NULL, NULL),
(60, 'khanh', 8, '2024-08-16', '2024-08-16', '13:00', '17:00', 'False', 'Giỗ', 'Ban viec gia dinh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '13/08/2024 13:06', '1', 'Đã xác nhận', '13/08/2024 13:05', NULL, NULL, NULL, 1, '2024-08-13 13:05', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(61, 'duykhanh', 17, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép có việc riêng', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '14/08/2024 15:39', '1', '', '13/08/2024 16:00', NULL, NULL, NULL, 1, '2024-08-14 15:39', 'quyen', '1', '0', NULL, NULL, NULL),
(62, 'duya', 18, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Giảm stress', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '15/08/2024 14:42', '1', '', '15/08/2024 11:17', NULL, NULL, NULL, 1, '2024-08-15 14:42', 'luan', '1', '0', NULL, NULL, NULL),
(63, 'vi', 14, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '15/08/2024 13:19', '1', '', '15/08/2024 13:19', NULL, NULL, NULL, 1, '2024-08-15 13:19', 'hoai', '1', '0', NULL, NULL, NULL),
(64, 'minhthang', 14, '2024-08-19', '2024-08-19', '00:00', '00:00', 'True', 'Khác', 'Bận việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '15/08/2024 13:29', '1', '', '15/08/2024 13:27', NULL, NULL, NULL, 1, '2024-08-15 13:29', 'hoai', '1', '0', NULL, NULL, NULL),
(65, 'khang', 17, '2024-08-16', '2024-08-16', '00:00', '00:00', 'True', 'Tang người thân', 'Bà Ngoại mất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '16/08/2024 12:56', '1', '', '16/08/2024 06:40', NULL, NULL, NULL, 2, '2024-08-16 12:56', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(66, 'thienquan', 12, '2024-08-16', '2024-08-16', '13:00', '17:00', 'False', 'Khác', 'Nghỉ phép', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '16/08/2024 08:42', '1', '', '16/08/2024 07:34', NULL, NULL, NULL, 1, '2024-08-16 08:42', 'hien', '1', '0', NULL, NULL, NULL),
(67, 'tuyet2015', 8, '2024-08-23', '2024-08-23', '00:00', '00:00', 'True', 'Khác', 'Việc gia đình', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '16/08/2024 18:03', '1', '', '16/08/2024 16:02', NULL, NULL, NULL, 1, '2024-08-16 16:03', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(68, 'quyen', 17, '2024-08-26', '2024-08-27', '00:00', '00:00', 'True', 'Khác', 'Về quê có việc gia đình', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/08/2024 17:24', '1', '', '19/08/2024 08:07', NULL, NULL, NULL, 1, '2024-08-19 17:24', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(69, 'thuylinh', 18, '2024-08-29', '2024-08-30', '00:00', '00:00', 'True', 'Khác', 'Về quê', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '19/08/2024 13:12', '1', '', '19/08/2024 10:30', NULL, NULL, NULL, 1, '2024-08-19 13:12', 'luan', '1', '0', NULL, NULL, NULL),
(70, 'bien', 15, '2024-08-02', '2024-08-02', '07:30', '07:50', 'False', 'Lỗi bất khả kháng', 'CHECK IN LUC 6H50 , DO PHẦN MỀM CẦN THÊM BƯỚC XÁC NHẬN NỮA NHƯNG DO KHÔNG ĐỂ Ý NÊN KO THẤY', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/08/2024 17:24', '1', '', '19/08/2024 11:37', NULL, NULL, NULL, 2, '2024-08-19 17:24', 'minhthomonly', '0', '0', NULL, NULL, NULL),
(71, 'quoc', 15, '2024-08-14', '2024-08-14', '07:30', '07:38', 'False', 'Đi trễ', 'ngủ quên', 'bien', 'minhthanhonly', 'Dinh Minh Thanh', 'Huynh Trong Bien', '22/08/2024 11:37', '2', 'Đi trễ do ngủ quên, ko tính là phép', '19/08/2024 11:38', NULL, NULL, NULL, 2, '2024-08-22 11:37', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(72, 'luan', 18, '2024-08-23', '2024-08-23', '00:00', '00:00', 'True', 'Giảm stress', 'Nghỉ phép năm', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '19/08/2024 13:00', '1', '', '19/08/2024 11:56', NULL, NULL, NULL, 1, '2024-08-19 13:00', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(73, 'dinh', 8, '2024-08-19', '2024-08-19', '07:30', '11:30', 'False', 'Khám bệnh', 'dẫn con đi khám bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '20/08/2024 15:54', '1', 'Đã xác nhận', '19/08/2024 12:30', NULL, NULL, NULL, 1, '2024-08-20 15:54', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(74, 'long', 14, '2024-08-19', '2024-08-19', '07:30', '11:30', 'False', 'Việc đột xuất', 'Bận việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '19/08/2024 13:02', '1', '', '19/08/2024 12:40', NULL, NULL, NULL, 1, '2024-08-19 13:02', 'hoai', '1', '0', NULL, NULL, NULL),
(75, 'tham', 20, '2024-08-07', '2024-08-07', '07:30', '08:00', 'False', 'Lỗi bất khả kháng', 'Bấm giờ trễ do lỗi phần mềm', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '19/08/2024 13:08', '1', 'đồng ý', '19/08/2024 13:05', NULL, NULL, NULL, 1, '2024-08-19 13:08', 'huy', '0', '0', NULL, NULL, NULL),
(76, 'minhthanhonly', 7, '2024-08-20', '2024-08-20', '07:30', '11:20', 'False', 'Khác', 'test', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '27/08/2024 16:34', '3', 'Duyệt tự động', '19/08/2024 14:04', 'minhthanhonly', '22/08/2024 11:45', 'Dinh Minh Thanh', 13, '2024-08-27 16:34', 'minhthanhonly', '0', '1', NULL, NULL, NULL),
(77, 'minhthang', 14, '2024-08-02', '2024-08-02', '07:15', '17:02', 'False', 'Đi trễ', 'bấm giờ trễ do lỗi phần mềm', 'hoai', 'minhthang', 'Nguyen Minh Thang', 'Nguyen Thi Hoai', '20/08/2024 13:58', '3', 'Sửa lại thời gian: Từ 7h30 đến lúc khi check in.', '20/08/2024 07:34', 'minhthanhonly', '20/08/2024 13:52', 'Dinh Minh Thanh', 3, '2024-08-20 13:58', 'minhthang', '1', '0', NULL, NULL, NULL),
(78, 'lethuy', 7, '2024-08-23', '2024-08-23', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép năm', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '20/08/2024 13:36', '1', '', '20/08/2024 07:37', NULL, NULL, NULL, 1, '2024-08-20 13:36', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(79, 'minhthang', 14, '2024-08-16', '2024-08-16', '07:06', '17:02', 'False', 'Khác', 'Quên check out', 'hoai', 'minhthanhonly', 'Dinh Minh Thanh', 'Nguyen Thi Hoai', '22/08/2024 12:25', '2', 'Trường hợp không cần đăng ký phép', '20/08/2024 08:05', NULL, NULL, NULL, 2, '2024-08-22 12:25', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(80, 'thienquan', 12, '2024-08-20', '2024-08-20', '07:30', '08:00', 'False', 'Lỗi bất khả kháng', 'Bấm giờ trễ do lỗi phần mềm', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '20/08/2024 08:18', '1', '', '20/08/2024 08:13', NULL, NULL, NULL, 1, '2024-08-20 08:18', 'hien', '0', '0', NULL, NULL, NULL),
(81, 'minhthang', 14, '2024-08-20', '2024-08-20', '16:30', '17:00', 'False', 'Về sớm', 'Bận việc cá nhân', 'hoai', 'minhthanhonly', 'Dinh Minh Thanh', 'Nguyen Thi Hoai', '22/08/2024 12:58', '2', 'Trường hợp không cần đăng ký phép.', '20/08/2024 13:24', 'minhthanhonly', '20/08/2024 13:45', 'Dinh Minh Thanh', 5, '2024-08-22 12:58', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(82, 'huy', 20, '2024-08-26', '2024-08-26', '00:00', '00:00', 'True', 'Khác', 'Việc gia đình', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 19:03', '1', '', '20/08/2024 17:02', NULL, NULL, NULL, 1, '2024-08-20 17:03', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(83, 'bich', 17, '2024-08-28', '2024-08-28', '13:00', '17:00', 'False', 'Khác', 'gia đình có việc', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '21/08/2024 08:11', '1', 'Đồng ý cho nghỉ phép', '21/08/2024 07:26', NULL, NULL, NULL, 1, '2024-08-21 08:11', 'khang', '1', '0', NULL, NULL, NULL),
(84, 'tu_web', 7, '2024-08-19', '2024-08-19', '00:00', '00:00', 'True', 'Bệnh', 'Bị bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '21/08/2024 07:28', '1', '', '21/08/2024 07:27', NULL, NULL, NULL, 1, '2024-08-21 07:28', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(85, 'bich', 17, '2024-09-04', '2024-09-04', '13:00', '17:00', 'False', 'Khác', 'gia đình có việc riêng', 'khang', 'bich', 'Nguyen Thi Ngoc Bich', 'Pham Nguyen Khang', '04/09/2024 08:03', '3', 'không cần nghỉ phép nữa', '21/08/2024 07:28', 'khang', '04/09/2024 08:02', 'Pham Nguyen Khang', 4, '2024-09-04 08:03', 'bich', '1', '0', NULL, NULL, NULL),
(86, 'ngocle', 17, '2024-08-22', '2024-08-22', '13:00', '17:00', 'False', 'Khác', 'Nghĩ phép', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '21/08/2024 09:16', '1', '', '21/08/2024 08:54', NULL, NULL, NULL, 1, '2024-08-21 09:16', 'nguyen', '1', '0', NULL, NULL, NULL),
(87, 'tranvinh.loc', 1, '2024-08-21', '2024-08-21', '07:30', '11:30', 'False', 'Việc đột xuất', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/08/2024 12:59', '1', '', '21/08/2024 12:55', NULL, NULL, NULL, 1, '2024-08-21 12:59', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(88, 'bich', 17, '2024-08-21', '2024-08-21', '16:30', '17:00', 'False', 'Việc đột xuất', 'gia đình có việc riêng', 'khang', 'minhthanhonly', 'Dinh Minh Thanh', 'Pham Nguyen Khang', '22/08/2024 12:29', '2', 'Trường hợp không cần đăng ký phép.', '21/08/2024 16:22', NULL, NULL, NULL, 2, '2024-08-22 12:29', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(89, 'vinh', 17, '2024-08-19', '2024-08-19', '07:30', '08:05', 'False', 'Đi trễ', 'có việc gia đình. xin đi trễ 45 phút.', 'khang', 'minhthanhonly', 'Dinh Minh Thanh', 'Pham Nguyen Khang', '22/08/2024 12:29', '1', '', '21/08/2024 17:14', NULL, NULL, NULL, 2, '2024-08-22 12:29', 'minhthanhonly', '0', '0', NULL, '1', NULL),
(90, 'bi', 14, '2024-08-22', '2024-08-22', '07:30', '11:30', 'False', 'Khác', 'Mất điện', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '26/08/2024 07:13', '1', '', '22/08/2024 06:19', NULL, NULL, NULL, 2, '2024-08-26 07:13', 'dat', '1', '0', NULL, NULL, NULL),
(91, 'hoai', 14, '2024-08-22', '2024-08-22', '00:00', '00:00', 'True', 'Khác', 'mất điện', 'dat', 'hoai', 'Nguyen Thi Hoai', 'Tran Vinh Dat', '22/08/2024 12:43', '3', '', '22/08/2024 06:21', NULL, NULL, NULL, 1, '2024-08-22 12:43', 'hoai', '1', '0', NULL, NULL, NULL),
(92, 'ly', 14, '2024-08-22', '2024-08-22', '00:00', '00:00', 'True', 'Khác', 'Mất điện', 'bi', 'ly', 'Nguyen Thi Ly', 'Nguyen Van Bi', '22/08/2024 12:47', '3', '', '22/08/2024 06:21', NULL, NULL, NULL, 1, '2024-08-22 12:47', 'ly', '1', '0', NULL, NULL, NULL),
(93, 'vankhanh', 9, '2024-08-26', '2024-08-27', '00:00', '00:00', 'True', 'Khác', 'Bận việc riêng.', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '22/08/2024 07:45', '1', 'đã duyệt', '22/08/2024 07:43', NULL, NULL, NULL, 1, '2024-08-22 07:45', 'lien', '1', '0', NULL, NULL, NULL),
(94, 'hoai', 14, '2024-08-22', '2024-08-22', '07:30', '11:30', 'False', 'Khác', 'Do bị mất điện', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '26/08/2024 07:13', '1', '', '22/08/2024 12:43', NULL, NULL, NULL, 1, '2024-08-26 07:14', 'dat', '1', '0', NULL, NULL, NULL),
(95, 'thanh', 11, '2024-08-22', '2024-08-22', '10:30', '11:15', 'False', 'Khác', 'Mất điện đột xuất', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/08/2024 14:59', '1', '', '22/08/2024 12:54', NULL, NULL, NULL, 1, '2024-08-22 12:59', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(96, 'dai', 14, '2024-08-22', '2024-08-22', '07:30', '11:30', 'False', 'Khác', 'Mất điện buổi sáng', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '22/08/2024 17:11', '1', '', '22/08/2024 12:58', NULL, NULL, NULL, 1, '2024-08-22 17:11', 'bi', '1', '0', NULL, NULL, NULL),
(97, 'thiet', 14, '2024-08-23', '2024-08-23', '13:00', '17:00', 'False', 'Khác', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '23/08/2024 11:04', '1', '', '23/08/2024 11:03', NULL, NULL, NULL, 1, '2024-08-23 11:04', 'bi', '1', '0', NULL, NULL, NULL),
(98, 'dat', 14, '2024-08-19', '2024-08-23', '00:00', '00:00', 'True', 'Nghỉ thai sản', 'nghỉ thai sản', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/08/2024 07:17', '1', '', '26/08/2024 07:13', NULL, NULL, NULL, 1, '2024-08-26 07:17', 'minhthomonly', '0', '0', NULL, NULL, '1'),
(99, 'dat', 14, '2024-08-28', '2024-08-28', '00:00', '00:00', 'True', 'Nghỉ thai sản', 'nghỉ thai sản', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/08/2024 07:38', '1', '', '26/08/2024 07:21', NULL, NULL, NULL, 1, '2024-08-26 07:38', 'minhthomonly', '0', '0', NULL, NULL, '1'),
(100, 'khang', 17, '2024-08-30', '2024-08-30', '00:00', '00:00', 'True', 'Giảm stress', 'Về quê.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/08/2024 07:38', '1', '', '26/08/2024 07:37', NULL, NULL, NULL, 1, '2024-08-26 07:38', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(101, 'thiet', 14, '2024-08-26', '2024-08-26', '13:00', '17:00', 'False', 'Việc đột xuất', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '26/08/2024 11:31', '1', '', '26/08/2024 10:27', NULL, NULL, NULL, 1, '2024-08-26 11:31', 'bi', '1', '0', NULL, NULL, NULL),
(102, 'tranvinh.loc', 1, '2024-08-26', '2024-08-26', '07:30', '11:30', 'False', 'Khác', 'Về lại Sài Gòn không kịp', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/08/2024 13:00', '1', 'đã xác nhận', '26/08/2024 12:58', NULL, NULL, NULL, 1, '2024-08-26 13:00', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(103, 'quoc', 15, '2024-09-16', '2024-09-16', '00:00', '00:00', 'True', 'Khác', 'viec rieng ca nhan', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '26/08/2024 16:44', '1', '', '26/08/2024 16:42', NULL, NULL, NULL, 1, '2024-08-26 16:44', 'bien', '1', '0', NULL, NULL, NULL),
(104, 'dinh', 8, '2024-08-26', '2024-08-26', '00:00', '00:00', 'True', 'Khám bệnh', 'Đưa mẹ đi khám bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '29/08/2024 10:46', '1', 'Đã xác nhận', '27/08/2024 06:37', NULL, NULL, NULL, 1, '2024-08-29 10:45', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(105, 'van', 12, '2024-08-27', '2024-08-27', '13:00', '17:00', 'False', 'Khám bệnh', '(trừ phép năm)', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '29/08/2024 08:51', '1', '', '27/08/2024 07:19', 'minhthanhonly', '27/08/2024 08:43', 'Dinh Minh Thanh', 7, '2024-08-29 08:51', 'hien', '1', '0', NULL, NULL, NULL),
(106, 'vutrang', 14, '2024-08-26', '2024-08-26', '00:00', '00:00', 'True', 'Việc đột xuất', 'xem trường cho con', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/08/2024 07:56', '1', '', '27/08/2024 07:23', NULL, NULL, NULL, 1, '2024-08-27 07:56', 'bi', '1', '0', NULL, NULL, NULL),
(107, 'minhthanhonly', 7, '2024-08-01', '2024-08-30', '16:00', '17:00', 'False', 'Phụ nữ có con nhỏ', 'test', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '28/08/2024 17:28', '3', 'Duyệt tự động', '27/08/2024 08:47', NULL, NULL, NULL, 11, '2024-08-28 17:28', 'minhthanhonly', '0', '1', NULL, NULL, NULL),
(108, 'minhthanhonly', 7, '2024-08-27', '2024-08-27', '13:00', '17:00', 'False', 'Việc đột xuất', 'test', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '27/08/2024 16:34', '3', 'Duyệt tự động', '27/08/2024 11:17', NULL, NULL, NULL, 1, '2024-08-27 16:34', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(109, 'vutrang', 14, '2024-09-05', '2024-09-05', '00:00', '00:00', 'True', 'Khác', 'bận việc Gia Đình', 'bi', 'vutrang', 'Tran Vu Trang', 'Nguyen Van Bi', '04/09/2024 09:20', '3', '', '27/08/2024 11:31', 'bi', '04/09/2024 09:19', 'Nguyen Van Bi', 3, '2024-09-04 09:20', 'vutrang', '1', '0', NULL, NULL, NULL),
(110, 'thiet', 14, '2024-08-27', '2024-08-27', '13:00', '17:00', 'False', 'Việc đột xuất', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/08/2024 11:50', '1', '', '27/08/2024 11:50', NULL, NULL, NULL, 1, '2024-08-27 11:50', 'bi', '1', '0', NULL, NULL, NULL),
(111, 'khanh', 8, '2024-08-30', '2024-08-30', '00:00', '00:00', 'True', 'Khác', 'Ban viec gia dinh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '29/08/2024 10:46', '1', 'Đã xác nhận', '27/08/2024 15:13', NULL, NULL, NULL, 1, '2024-08-29 10:45', 'tuyet2015', '1', '0', NULL, NULL, NULL),
(112, 'trieu', 17, '2024-08-28', '2024-08-28', '07:30', '08:00', 'False', 'Đi trễ', 'Quên bấm giờ bắt đầu', 'quyen', 'trieu', 'Phan Tan Trieu', 'Nguyen Thi Thu Quyen', '28/08/2024 08:28', '3', '', '28/08/2024 08:15', NULL, NULL, NULL, 1, '2024-08-28 08:29', 'trieu', '1', '0', NULL, NULL, NULL),
(113, 'minhthanhonly', 7, '2024-08-28', '2024-08-28', '07:30', '08:00', 'False', 'Lỗi bất khả kháng', 'lỗi', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '28/08/2024 17:28', '3', 'Duyệt tự động', '28/08/2024 13:41', NULL, NULL, NULL, 1, '2024-08-28 17:28', 'minhthanhonly', '0', '0', NULL, NULL, NULL),
(114, 'thinh_web', 7, '2024-08-27', '2024-08-27', '00:00', '00:00', 'True', 'Việc đột xuất', 'Đưa người thân nhập việc đột xuất', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '28/08/2024 17:29', '1', '', '28/08/2024 17:27', NULL, NULL, NULL, 1, '2024-08-28 17:29', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(115, 'ngoc', 10, '2024-08-29', '2024-08-29', '13:00', '17:00', 'False', 'Khác', 'Có việc riêng', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '29/08/2024 08:03', '1', 'Leader ok!', '29/08/2024 07:43', NULL, NULL, NULL, 1, '2024-08-29 08:03', 'nhan', '1', '0', NULL, NULL, NULL),
(116, 'minhthanhonly', 7, '2024-08-29', '2024-08-29', '07:30', '09:30', 'False', 'Lỗi bất khả kháng', '4454', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/08/2024 17:00', '3', 'Duyệt tự động', '29/08/2024 14:46', NULL, NULL, NULL, 1, '2024-08-30 17:00', 'minhthanhonly', '0', '0', NULL, NULL, NULL),
(117, 'vantoan', 17, '2024-08-30', '2024-08-30', '00:00', '00:00', 'True', 'Việc đột xuất', 'Việc bận gia đình', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '29/08/2024 15:06', '1', '', '29/08/2024 15:06', NULL, NULL, NULL, 1, '2024-08-29 15:06', 'nhu', '1', '0', NULL, NULL, NULL),
(118, 'ly', 14, '2024-08-22', '2024-08-22', '07:30', '11:30', 'False', 'Mất điện', 'Mất điện buổi sáng', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', NULL, '1', NULL, '22/08/2024 17:11', NULL, NULL, NULL, 0, '', '', '0', '0', NULL, '1', NULL),
(119, 'dai', 14, '2024-08-29', '2024-08-29', '00:00', '00:00', 'True', 'Việc đột xuất', 'Nghỉ 1 ngày phép năm do mất điện', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '30/08/2024 07:32', '1', '', '30/08/2024 07:22', NULL, NULL, NULL, 1, '2024-08-30 07:32', 'bi', '1', '0', NULL, NULL, NULL),
(120, 'hoai', 14, '2024-08-29', '2024-08-29', '00:00', '00:00', 'True', 'Khác', 'Do bị mất điện', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '30-08-2024 10:25', '1', 'mau hết cúp điện e nhé!', '30/08/2024 07:31', NULL, NULL, NULL, 1, '2024-08-30 10:25', 'dat', '1', '0', NULL, NULL, NULL),
(121, 'bi', 14, '2024-08-29', '2024-08-29', '00:00', '00:00', 'True', 'Khác', 'Do bị mất điện', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '30-08-2024 10:25', '1', 'mau hết cúp điện e nhé!', '30/08/2024 07:31', NULL, NULL, NULL, 1, '2024-08-30 10:25', 'dat', '1', '0', NULL, NULL, NULL),
(122, 'ly', 14, '2024-08-29', '2024-08-29', '00:00', '00:00', 'True', 'Khác', 'Mất điện ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '30/08/2024 08:08', '1', '', '30/08/2024 07:33', NULL, NULL, NULL, 1, '2024-08-30 08:08', 'bi', '1', '0', NULL, NULL, NULL),
(123, 'trieu', 17, '2024-08-30', '2024-08-30', '13:00', '17:00', 'False', 'Khác', 'Nghỉ phép có việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '30/08/2024 07:55', '1', '', '30/08/2024 07:48', NULL, NULL, NULL, 1, '2024-08-30 07:55', 'quyen', '1', '0', NULL, NULL, NULL),
(124, 'cong', 11, '2024-08-30', '2024-08-30', '13:00', '17:00', 'False', 'Khác', 'đi lấy bánh trung thu', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '30/08/2024 09:21', '1', 'OK', '30/08/2024 08:20', NULL, NULL, NULL, 1, '2024-08-30 09:22', 'thanh', '1', '0', NULL, NULL, NULL),
(125, 'thienquan', 12, '2024-08-30', '2024-08-30', '13:00', '17:00', 'False', 'Khác', 'Trừ phép năm', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '30/08/2024 10:21', '1', '', '30/08/2024 08:48', NULL, NULL, NULL, 1, '2024-08-30 10:21', 'hien', '1', '0', NULL, NULL, NULL),
(126, 'minhthang', 14, '2024-08-30', '2024-08-30', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc đột xuất', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '30/08/2024 11:17', '1', '', '30/08/2024 11:15', NULL, NULL, NULL, 1, '2024-08-30 11:17', 'hoai', '1', '0', NULL, NULL, NULL),
(127, 'chautuan', 11, '2024-08-28', '2024-08-28', '13:00', '17:00', 'False', 'Mất điện', 'mất điện buổi chiều', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '04/09/2024 07:16', '1', 'ok', '04/09/2024 07:04', NULL, NULL, NULL, 1, '2024-09-04 07:16', 'thanh', '1', '0', NULL, NULL, NULL),
(128, 'dai', 14, '2024-09-06', '2024-09-06', '07:30', '11:30', 'False', 'Việc cá nhân', 'bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '04/09/2024 08:08', '1', '', '04/09/2024 07:20', NULL, NULL, NULL, 1, '2024-09-04 08:08', 'bi', '1', '0', NULL, NULL, NULL),
(129, 'thienquan', 12, '2024-09-06', '2024-09-06', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép năm', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '05/09/2024 16:53', '1', '', '04/09/2024 07:35', NULL, NULL, NULL, 1, '2024-09-05 16:53', 'hien', '1', '0', NULL, NULL, NULL),
(130, 'truong', 15, '2024-09-04', '2024-09-04', '07:30', '07:37', 'False', 'Lỗi bất khả kháng', 'Lỗi cập nhật bản nâng cấp phần mềm thẻ giờ và phần mềm skyper bị lỗi.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '04/09/2024 09:24', '1', 'chú ý , lần sau báo cáo sớm hơn để kịp thời giải quyết', '04/09/2024 09:23', NULL, NULL, NULL, 1, '2024-09-04 09:24', 'bien', '0', '0', NULL, NULL, NULL),
(131, 'vutrang', 14, '2024-09-06', '2024-09-06', '07:30', '11:30', 'False', 'Việc cá nhân', 'bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '04/09/2024 09:42', '1', '', '04/09/2024 09:41', NULL, NULL, NULL, 1, '2024-09-04 09:42', 'bi', '1', '0', NULL, NULL, NULL),
(132, 'thinh_web', 7, '2024-09-13', '2024-09-13', '00:00', '00:00', 'True', 'Khám bệnh', 'Đưa người thân đi khám bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '04/09/2024 10:40', '1', '', '04/09/2024 10:37', NULL, NULL, NULL, 1, '2024-09-04 10:40', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(133, 'quocthinh_web', 7, '2024-09-04', '2024-09-04', '07:30', '11:30', 'False', 'Khám bệnh', 'di kham benh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '06/09/2024 11:26', '1', '', '04/09/2024 12:21', NULL, NULL, NULL, 1, '2024-09-06 11:26', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(134, 'hanhthach', 20, '2024-09-04', '2024-09-04', '13:00', '17:00', 'False', 'Việc đột xuất', 'giải quyết  giấy tờ', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '04/09/2024 13:10', '1', 'đồng ý', '04/09/2024 12:33', NULL, NULL, NULL, 1, '2024-09-04 13:10', 'huy', '1', '0', NULL, NULL, NULL),
(135, 'ngan', 13, '2024-09-04', '2024-09-04', ' 7:00', '11:30', 'False', 'Hư máy', 'Sửa  mạng internet', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '16/09/2024 14:53', '1', '', '04/09/2024 12:59', 'minhthanhonly', '13/09/2024 15:55', 'Dinh Minh Thanh', 6, '2024-09-16 14:53', 'van.tu', '1', '0', NULL, '0', NULL),
(136, 'chautuan', 11, '2024-09-04', '2024-09-04', '14:20', '15:00', 'False', 'Mất điện', 'mất điện từ 14h20-15h', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '09/09/2024 07:50', '1', 'ok', '04/09/2024 15:08', 'minhthanhonly', '06/09/2024 15:55', 'Dinh Minh Thanh', 6, '2024-09-09 07:50', 'thanh', '0', '0', NULL, '1', NULL),
(137, 'vankhanh', 9, '2024-09-06', '2024-09-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc riêng.', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '04/09/2024 17:11', '1', 'đã duyệt', '04/09/2024 17:10', NULL, NULL, NULL, 1, '2024-09-04 17:11', 'lien', '1', '0', NULL, NULL, NULL),
(138, 'thiet', 14, '2024-09-05', '2024-09-05', '16:00', '17:00', 'False', 'Về sớm', 'có việc đột xuất ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '05/09/2024 16:09', '1', '', '05/09/2024 07:11', NULL, NULL, NULL, 1, '2024-09-05 16:09', 'bi', '0', '0', NULL, NULL, NULL),
(139, 'dat', 14, '2024-09-06', '2024-09-06', '00:00', '00:00', 'True', 'Nghỉ thai sản', 'nghỉ thai sản', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/09/2024 07:53', '1', '', '05/09/2024 07:13', NULL, NULL, NULL, 2, '2024-09-05 07:53', 'minhthomonly', '0', '0', NULL, '0', '1'),
(140, 'vinh', 17, '2024-09-04', '2024-09-04', '07:30', '12:00', 'False', 'Khám bệnh', 'khám bệnh cho bé.', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '05/09/2024 07:21', '1', '', '05/09/2024 07:18', NULL, NULL, NULL, 1, '2024-09-05 07:21', 'khang', '1', '0', NULL, NULL, NULL),
(141, 'ngocle', 17, '2024-09-04', '2024-09-04', '13:00', '17:00', 'False', 'Mất điện', 'mất điện ', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '05/09/2024 07:49', '1', '', '05/09/2024 07:32', NULL, NULL, NULL, 1, '2024-09-05 07:49', 'nguyen', '1', '0', NULL, NULL, NULL),
(142, 'khang', 17, '2024-09-04', '2024-09-04', '13:00', '17:00', 'False', 'Mất điện', 'Giông gió sét đánh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/09/2024 07:53', '1', '', '05/09/2024 07:35', NULL, NULL, NULL, 1, '2024-09-05 07:53', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(143, 'duykhanh', 17, '2024-09-06', '2024-09-06', '00:00', '00:00', 'True', 'Khác', 'Bận việc riêng', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '05/09/2024 14:03', '1', '', '05/09/2024 13:55', NULL, NULL, NULL, 1, '2024-09-05 14:03', 'quyen', '1', '0', NULL, NULL, NULL),
(144, 'trieu', 17, '2024-09-04', '2024-09-04', '00:00', '00:00', 'True', 'Khác', 'xin nghỉ phép năm', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '05/09/2024 14:03', '1', '', '05/09/2024 13:56', NULL, NULL, NULL, 1, '2024-09-05 14:03', 'quyen', '1', '0', NULL, NULL, NULL),
(145, 'ngoc', 10, '2024-09-06', '2024-09-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê có việc đột xuất.', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '05/09/2024 16:14', '1', 'Leader ok!', '05/09/2024 15:49', NULL, NULL, NULL, 1, '2024-09-05 16:14', 'nhan', '1', '0', NULL, NULL, NULL),
(146, 'diemai', 7, '2024-09-04', '2024-12-02', '16:00', '17:00', 'False', 'Phụ nữ có con nhỏ', 'có con nhỏ dưới 1 tuổi', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '06/09/2024 11:27', '1', '', '05/09/2024 15:55', NULL, NULL, NULL, 1, '2024-09-06 11:27', 'minhthanhonly', '0', '1', NULL, NULL, NULL),
(147, 'truong', 15, '2024-09-13', '2024-09-13', '00:00', '00:00', 'True', 'Việc cá nhân', 'khám sức khỏe, đổi bằng lái xe. việc cá nhân.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '06/09/2024 12:56', '1', '', '06/09/2024 12:54', NULL, NULL, NULL, 1, '2024-09-06 12:56', 'bien', '1', '0', NULL, NULL, NULL),
(148, 'tuyet2015', 8, '2024-09-09', '2024-09-09', ' 7:30', '11:30', 'False', 'Khác', 'Giải quyết giấy tờ ', 'tranvinh.loc', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Tran Vinh Loc', '09/09/2024 10:01', '3', '', '06/09/2024 13:44', 'minhthanhonly', '09/09/2024 09:59', 'Dinh Minh Thanh', 7, '2024-09-09 10:01', 'tuyet2015', '1', '0', NULL, '0', NULL),
(149, 'ngan', 13, '2024-09-06', '2024-09-06', '14:00', '15:00', 'False', 'Lỗi bất khả kháng', 'Cúp điện', 'van.tu', 'minhthanhonly', 'Dinh Minh Thanh', 'Huynh Van Tu', '13/09/2024 15:59', '1', '', '06/09/2024 15:10', 'minhthanhonly', '13/09/2024 15:55', 'Dinh Minh Thanh', 5, '2024-09-13 15:59', 'minhthanhonly', '0', '0', NULL, NULL, NULL),
(150, 'vutrang', 14, '2024-09-09', '2024-09-09', '13:00', '17:00', 'False', 'Việc đột xuất', 'bận việc Gia Đình', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '09/09/2024 12:14', '1', '', '09/09/2024 11:22', NULL, NULL, NULL, 1, '2024-09-09 12:14', 'bi', '1', '0', NULL, NULL, NULL),
(151, 'long', 14, '2024-09-12', '2024-09-12', '13:00', '17:00', 'False', 'Việc đột xuất', 'Hãng bay thay đổi lịch bay nên xin nghỉ phép buổi chiều để kịp thời gian bay', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '09/09/2024 12:57', '1', '', '09/09/2024 11:29', NULL, NULL, NULL, 1, '2024-09-09 12:57', 'hoai', '1', '0', NULL, NULL, NULL),
(152, 'nguyen', 17, '2024-09-11', '2024-09-11', '13:00', '17:00', 'False', 'Khám bệnh', 'Đi khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/09/2024 07:35', '1', '', '10/09/2024 07:47', NULL, NULL, NULL, 1, '2024-09-11 07:35', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(153, 'hanhthach', 20, '2024-09-09', '2024-09-09', '13:00', '17:00', 'False', 'Việc đột xuất', 'o que lên', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '10/09/2024 08:14', '1', 'đồng ý', '10/09/2024 08:00', NULL, NULL, NULL, 1, '2024-09-10 08:14', 'huy', '1', '0', NULL, NULL, NULL),
(154, 'duya', 18, '2024-09-16', '2024-09-16', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '10/09/2024 13:27', '1', '', '10/09/2024 08:03', NULL, NULL, NULL, 1, '2024-09-10 13:27', 'luan', '1', '0', NULL, NULL, NULL),
(155, 'huyhoang', 17, '2024-09-12', '2024-09-12', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/09/2024 07:38', '1', '', '11/09/2024 07:37', NULL, NULL, NULL, 1, '2024-09-11 07:38', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(156, 'minhthanhonly', 7, '2024-09-02', '2024-09-02', '00:00', '00:00', 'True', 'Nghỉ thai sản', 'test', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '12/09/2024 07:33', '3', 'Duyệt tự động', '11/09/2024 14:19', NULL, NULL, NULL, 5, '2024-09-12 07:33', 'minhthanhonly', '0', '0', NULL, '0', '1'),
(157, 'hoaibao', 13, '2024-09-19', '2024-09-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc gia đình', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '12/09/2024 10:56', '1', '', '12/09/2024 08:06', NULL, NULL, NULL, 1, '2024-09-12 10:56', 'van.tu', '1', '0', NULL, NULL, NULL),
(158, 'thiet', 14, '2024-09-12', '2024-09-12', '13:00', '17:00', 'False', 'Việc cá nhân', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '12/09/2024 10:08', '1', '', '12/09/2024 10:07', NULL, NULL, NULL, 1, '2024-09-12 10:08', 'bi', '1', '0', NULL, NULL, NULL),
(159, 'luan', 18, '2024-09-20', '2024-09-20', '00:00', '00:00', 'True', 'Khác', 'nghỉ phép', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '13/09/2024 14:11', '1', '', '12/09/2024 11:11', NULL, NULL, NULL, 1, '2024-09-13 14:11', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(160, 'tham', 20, '2024-09-16', '2024-09-16', '07:30', '11:30', 'False', 'Khác', 'Về quê', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '13/09/2024 13:48', '1', 'đồng ý\r\n', '12/09/2024 13:58', NULL, NULL, NULL, 1, '2024-09-13 13:48', 'huy', '1', '0', NULL, NULL, NULL),
(161, 'thanh', 11, '2024-09-16', '2024-09-16', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/09/2024 16:16', '1', '', '12/09/2024 16:16', NULL, NULL, NULL, 1, '2024-09-12 16:16', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(162, 'huy', 20, '2024-09-16', '2024-09-16', '00:00', '00:00', 'True', 'Khác', 'việc gia đình', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/09/2024 13:58', '1', '', '13/09/2024 13:49', NULL, NULL, NULL, 1, '2024-09-13 13:58', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(163, 'nhan', 10, '2024-09-16', '2024-09-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/09/2024 14:54', '1', '', '13/09/2024 14:54', NULL, NULL, NULL, 1, '2024-09-13 14:54', 'tranvinh.loc', '1', '0', NULL, NULL, NULL),
(164, 'minhthanhonly', 7, '2024-09-23', '2024-09-23', '00:00', '00:00', 'True', 'Giảm stress', 'Giảm stress', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '13/09/2024 15:51', '1', 'Duyệt tự động', '13/09/2024 15:51', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, NULL),
(165, 'ly', 14, '2024-09-16', '2024-09-16', '00:00', '00:00', 'True', 'Việc đột xuất', 'Việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '16/09/2024 06:35', '1', '', '16/09/2024 06:34', NULL, NULL, NULL, 1, '2024-09-16 06:35', 'bi', '1', '0', NULL, NULL, NULL);
INSERT INTO `groupware_dayoff` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`, `minus_leave`, `is_repeat`, `cancel_repeat_dates`, `is_overwork`, `is_BHXH`) VALUES
(166, 'minhthomonly', 17, '2024-09-16', '2024-09-16', '13:00', '17:00', 'False', 'Bệnh', 'Người không khỏe, xin nghỉ phép buổi chiều', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '16/09/2024 11:50', '1', 'Duyệt tự động', '16/09/2024 11:50', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, NULL),
(167, 'vi', 14, '2024-09-16', '2024-09-16', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'vi', 'Huynh Tuong Vi', 'Nguyen Thi Hoai', '25/09/2024 13:30', '3', 'Hủy đi', '16/09/2024 12:59', 'minhthanhonly', '25/09/2024 13:28', 'Dinh Minh Thanh', 5, '2024-09-25 13:30', 'vi', '1', '0', NULL, NULL, NULL),
(168, 'tra_web', 7, '2024-10-14', '2024-10-14', '00:00', '00:00', 'True', 'Khác', 'Nghỉ Phép ', 'minhthanhonly', 'tra_web', 'Tran Thi My Tra', 'Dinh Minh Thanh', '16/09/2024 16:56', '3', '', '16/09/2024 16:04', NULL, NULL, NULL, 2, '2024-09-16 16:56', 'tra_web', '1', '0', NULL, '0', NULL),
(169, 'tra_web', 7, '2024-11-18', '2024-11-19', '00:00', '00:00', 'True', 'Giỗ', 'Nghỉ Phép ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '26/11/2024 07:32', '2', '', '16/09/2024 16:06', NULL, NULL, NULL, 2, '2024-11-26 07:32', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(170, 'tra_web', 7, '2024-10-18', '2024-10-18', '00:00', '00:00', 'True', 'Khác', 'Nghỉ Phép ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '17/09/2024 07:19', '1', '', '16/09/2024 17:04', NULL, NULL, NULL, 1, '2024-09-17 07:19', 'minhthanhonly', '1', '0', NULL, NULL, NULL),
(171, 'lien', 9, '2024-09-16', '2024-09-16', '00:00', '00:00', 'True', 'Hư máy', 'mạng bị trục trặc', 'tranvinh.loc', 'minhthanhonly', 'Dinh Minh Thanh', 'Tran Vinh Loc', '27/09/2024 13:21', '1', '', '17/09/2024 06:54', 'minhthanhonly', '24/09/2024 12:43', 'Dinh Minh Thanh', 4, '2024-09-27 13:21', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(172, 'huyhoang', 17, '2024-09-16', '2024-09-16', '07:30', '11:30', 'False', 'Bệnh', 'Bị bệnh.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/09/2024 07:39', '1', '', '17/09/2024 07:33', NULL, NULL, NULL, 1, '2024-09-18 07:39', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(173, 'minhthang', 14, '2024-09-18', '2024-09-18', '07:30', '11:30', 'False', 'Việc đột xuất', 'Việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '17/09/2024 08:45', '1', '', '17/09/2024 08:43', NULL, NULL, NULL, 2, '2024-09-17 08:45', 'hoai', '1', '0', NULL, '0', NULL),
(174, 'vi', 14, '2024-09-17', '2024-09-17', '07:30', '11:30', 'False', 'Việc cá nhân', 'Bận việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '25/09/2024 13:23', '1', 'Thanh: Yêu cầu chỉnh loại hình đúng với thực tế', '17/09/2024 13:02', 'minhthanhonly', '25/09/2024 13:18', 'Dinh Minh Thanh', 6, '2024-09-25 13:23', 'hoai', '1', '0', NULL, '0', '0'),
(175, 'quyen', 17, '2024-09-18', '2024-09-18', '13:00', '17:00', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/09/2024 07:39', '1', '', '17/09/2024 15:21', NULL, NULL, NULL, 1, '2024-09-18 07:39', 'minhthomonly', '1', '0', NULL, NULL, NULL),
(176, 'quocthinh_web', 7, '2024-09-18', '2024-09-18', '07:30', '07:46', 'False', 'Hư máy', 'máy hư, không lên màn hình, bị lỗi ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '18/09/2024 08:31', '2', '', '18/09/2024 07:53', NULL, NULL, NULL, 1, '2024-09-18 08:31', 'minhthanhonly', '0', '0', NULL, NULL, NULL),
(177, 'vi', 14, '2024-09-20', '2024-09-20', '13:00', '17:00', 'False', 'Việc đột xuất', 'bận việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '19/09/2024 08:51', '1', '', '19/09/2024 06:58', NULL, NULL, NULL, 1, '2024-09-19 08:51', 'hoai', '1', '0', NULL, NULL, NULL),
(178, 'lethuy', 7, '2024-09-23', '2024-09-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép năm', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '24/09/2024 07:36', '1', '', '19/09/2024 07:37', 'minhthanhonly', '20/09/2024 07:22', 'Dinh Minh Thanh', 4, '2024-09-24 07:36', 'minhthanhonly', '1', '0', NULL, '0', NULL),
(179, 'ha', 10, '2024-09-19', '2024-09-19', '17:00', '17:35', 'False', 'Mất điện', 'trời mưa gió nên bị cup điện', 'nhan', 'ha', 'Ho Thi Ha', 'Diep Thanh Nhan', '19/09/2024 13:21', '3', '', '19/09/2024 13:10', NULL, NULL, NULL, 1, '2024-09-19 13:21', 'ha', '0', '0', NULL, NULL, NULL),
(180, 'ha', 10, '2024-09-19', '2024-09-19', '17:00', '17:35', 'False', 'Mất điện', 'mưa gió to nên bị cup điện', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '19/09/2024 13:32', '1', 'Leader ok!', '19/09/2024 13:27', NULL, NULL, NULL, 2, '2024-09-19 13:32', 'nhan', '0', '0', NULL, '0', NULL),
(181, 'hanhthach', 20, '2024-09-17', '2024-09-17', '00:00', '00:00', 'True', 'Chăm bệnh', 'chồng nằm viện', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '20/09/2024 09:52', '1', 'đồng ý', '20/09/2024 08:30', NULL, NULL, NULL, 2, '2024-09-20 09:52', 'huy', '1', '0', NULL, '0', NULL),
(182, 'hanhthach', 20, '2024-09-18', '2024-09-18', '07:30', '11:30', 'False', 'Chăm bệnh', 'chồng nằm viện', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '20/09/2024 09:52', '1', 'đồng ý\r\n', '20/09/2024 08:32', NULL, NULL, NULL, 2, '2024-09-20 09:52', 'huy', '1', '0', NULL, '0', NULL),
(183, 'hanhthach', 20, '2024-09-19', '2024-09-19', '07:30', '11:30', 'False', 'Chăm bệnh', 'chồng nằm viện', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '20/09/2024 09:52', '1', 'đồng ý', '20/09/2024 08:33', NULL, NULL, NULL, 3, '2024-09-20 09:52', 'huy', '1', '0', NULL, '0', NULL),
(184, 'huy', 20, '2024-09-23', '2024-09-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/09/2024 09:57', '1', '', '20/09/2024 09:56', NULL, NULL, NULL, 2, '2024-09-20 09:57', 'tranvinh.loc', '1', '0', NULL, '0', NULL),
(185, 'chautuan', 11, '2024-09-24', '2024-09-24', '00:00', '00:00', 'True', 'Khác', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '23/09/2024 07:33', '1', 'OK', '23/09/2024 07:32', NULL, NULL, NULL, 1, '2024-09-23 07:33', 'thanh', '1', '0', NULL, NULL, NULL),
(186, 'trieu', 17, '2024-09-23', '2024-09-23', '00:00', '00:00', 'True', 'Khác', 'có việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '24/09/2024 07:42', '1', '', '24/09/2024 07:22', NULL, NULL, NULL, 1, '2024-09-24 07:42', 'quyen', '1', '0', NULL, NULL, NULL),
(187, 'dat', 14, '2024-09-24', '2024-09-24', '13:00', '17:00', 'False', 'Việc đột xuất', 'việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '24/09/2024 08:07', '1', '', '24/09/2024 08:04', NULL, NULL, NULL, 1, '2024-09-24 08:07', 'minhthomonly', '0', '0', NULL, NULL, NULL),
(188, 'tranvinh.loc', 1, '2024-09-24', '2024-09-24', ' 7:30', '11:30', 'False', 'Mất điện', 'cúp điện đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/09/2024 07:39', '1', 'Thanh: Chọn loại hình Mất điện', '24/09/2024 08:05', 'minhthanhonly', '27/09/2024 07:33', 'Dinh Minh Thanh', 4, '2024-09-27 07:39', 'minhthomonly', '1', '0', NULL, '0', '0'),
(189, 'van.tu', 13, '2024-09-25', '2024-09-25', '07:30', '11:30', 'False', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '25/09/2024 07:18', '1', '', '25/09/2024 06:19', NULL, NULL, NULL, 1, '2024-09-25 07:18', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(190, 'khanh', 8, '2024-09-25', '2024-09-25', '15:00', '17:00', 'False', 'Về sớm', 'ban viec gia dinh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '25/09/2024 10:30', '1', 'Đã xác nhận', '25/09/2024 10:27', NULL, NULL, NULL, 1, '2024-09-25 10:30', 'tuyet2015', '0', '0', NULL, NULL, '0'),
(191, 'vinh', 17, '2024-09-25', '2024-09-25', '07:30', '11:30', 'False', 'Khám bệnh', 'đưa con khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '26/09/2024 07:13', '1', 'Đồng ý cho nghỉ phép ', '25/09/2024 17:12', NULL, NULL, NULL, 1, '2024-09-26 07:13', 'khang', '1', '0', NULL, NULL, '0'),
(192, 'dat', 14, '2024-09-26', '2024-09-26', '07:30', '11:30', 'False', 'Bệnh', 'không khỏe', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/09/2024 12:20', '1', '', '26/09/2024 11:15', NULL, NULL, NULL, 1, '2024-09-26 12:20', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(193, 'quyen', 17, '2024-09-27', '2024-09-27', '07:30', '11:30', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/09/2024 07:37', '1', '', '27/09/2024 07:27', NULL, NULL, NULL, 1, '2024-09-27 07:37', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(194, 'duykhanh', 17, '2024-09-30', '2024-09-30', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '01/10/2024 07:34', '1', '', '27/09/2024 07:48', NULL, NULL, NULL, 1, '2024-10-01 07:34', 'quyen', '1', '0', NULL, NULL, '0'),
(195, 'hien', 12, '2024-09-27', '2024-09-27', '07:30', '11:30', 'False', 'Mất điện', 'Bị mất điện (có trừ phép năm)', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/09/2024 13:16', '1', '', '27/09/2024 13:15', NULL, NULL, NULL, 1, '2024-09-27 13:16', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(196, 'tuyet2015', 8, '2024-09-30', '2024-09-30', '07:30', '11:30', 'False', 'Khác', 'VỀ QUÊ CÓ VIỆC GIA ĐÌNH', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '30/09/2024 07:14', '1', '', '27/09/2024 17:14', NULL, NULL, NULL, 1, '2024-09-30 07:14', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(197, 'diemai', 7, '2024-09-09', '2024-09-09', '00:00', '00:00', 'True', 'Chăm bệnh', 'Dẫn con khám bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/09/2024 07:42', '1', '', '30/09/2024 07:41', NULL, NULL, NULL, 1, '2024-09-30 07:42', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(198, 'diemai', 7, '2024-09-30', '2024-09-30', '00:00', '00:00', 'True', 'Bệnh', 'Bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/09/2024 07:42', '1', '', '30/09/2024 07:42', NULL, NULL, NULL, 1, '2024-09-30 07:42', 'minhthanhonly', '0', '0', NULL, NULL, '0'),
(199, 'vantoan', 17, '2024-10-04', '2024-10-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc bận gia đình', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '08/10/2024 07:39', '1', 'Thanh: Có sử dụng phép năm hay không? Nếu có hãy check sử dụng PN', '30/09/2024 07:43', 'minhthanhonly', '08/10/2024 07:37', 'Dinh Minh Thanh', 4, '2024-10-08 07:39', 'nhu', '1', '0', NULL, '0', '0'),
(200, 'bi', 14, '2024-10-07', '2024-10-07', '07:30', '11:30', 'False', 'Việc đột xuất', 'Bận việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '30/09/2024 17:05', '1', '', '30/09/2024 11:01', NULL, NULL, NULL, 1, '2024-09-30 17:05', 'dat', '1', '0', NULL, NULL, '0'),
(201, 'tuyen', 8, '2024-09-30', '2024-09-30', '13:00', '17:00', 'False', 'Bệnh', 'Nghỉ ốm', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '30/09/2024 12:56', '1', 'Đã xác nhận.\r\n', '30/09/2024 12:50', NULL, NULL, NULL, 1, '2024-09-30 12:56', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(202, 'bich', 17, '2024-09-30', '2024-09-30', '07:30', '11:30', 'False', 'Mất điện', 'Mất điện đột xuất', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '30/09/2024 12:59', '1', 'Đồng ý cho nghỉ phép', '30/09/2024 12:51', NULL, NULL, NULL, 1, '2024-09-30 12:59', 'khang', '1', '0', NULL, NULL, '0'),
(203, 'minhthanhonly', 7, '2024-10-14', '2024-10-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'Làm giấy tờ', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '01/10/2024 12:19', '1', 'Duyệt tự động', '01/10/2024 12:20', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(204, 'huyhoang', 17, '2024-10-04', '2024-10-04', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '02/10/2024 16:21', '1', '', '02/10/2024 08:09', NULL, NULL, NULL, 1, '2024-10-02 16:21', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(205, 'chautuan', 11, '2024-10-04', '2024-10-04', '00:00', '00:00', 'True', 'Khác', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '02/10/2024 10:57', '1', 'OK', '02/10/2024 10:55', NULL, NULL, NULL, 1, '2024-10-02 10:57', 'thanh', '1', '0', NULL, NULL, '0'),
(206, 'bich', 17, '2024-10-02', '2024-10-02', '07:30', '11:30', 'False', 'Việc cá nhân', 'nhà hư đường nước', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '03/10/2024 07:24', '1', 'đồng ý cho nghỉ phép', '02/10/2024 12:48', NULL, NULL, NULL, 1, '2024-10-03 07:24', 'khang', '1', '0', NULL, NULL, '0'),
(207, 'minhthang', 14, '2024-10-07', '2024-10-07', '00:00', '00:00', 'True', 'Khác', 'Việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '03/10/2024 14:13', '1', '', '03/10/2024 14:08', NULL, NULL, NULL, 1, '2024-10-03 14:13', 'hoai', '1', '0', NULL, NULL, '0'),
(208, 'ha', 10, '2024-10-07', '2024-10-07', '07:30', '11:30', 'False', 'Việc cá nhân', 'nghỉ 0,5 ngày, trừ phép năm', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '04/10/2024 08:13', '1', 'Leader ok!', '04/10/2024 08:09', NULL, NULL, NULL, 2, '2024-10-04 08:13', 'nhan', '1', '0', NULL, '0', '0'),
(209, 'thinh_web', 7, '2024-10-21', '2024-10-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'Dự đám cưới người thân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '07/10/2024 07:42', '1', '', '04/10/2024 14:05', 'minhthanhonly', '07/10/2024 07:39', 'Dinh Minh Thanh', 5, '2024-10-07 07:42', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(210, 'minhthanhonly', 7, '2024-11-15', '2024-11-15', '00:00', '00:00', 'True', 'Giảm stress', 'Giảm stress', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '07/10/2024 07:36', '1', 'Duyệt tự động', '07/10/2024 07:36', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(211, 'minhthanhonly', 7, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Làm giấy tờ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '25/10/2024 14:53', '3', 'Duyệt tự động', '07/10/2024 07:37', NULL, NULL, NULL, 1, '2024-10-25 14:53', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(212, 'long', 14, '2024-10-04', '2024-10-04', '00:00', '00:00', 'True', 'Bệnh', 'Xin nghỉ phép do bị bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '07/10/2024 17:14', '1', '', '07/10/2024 09:20', NULL, NULL, NULL, 1, '2024-10-07 17:14', 'hoai', '1', '0', NULL, NULL, '0'),
(213, 'tuyet2015', 8, '2024-10-08', '2024-10-08', '07:30', '11:30', 'False', 'Khám bệnh', 'Cha mẹ tái khám', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 14:55', '1', '', '07/10/2024 14:55', NULL, NULL, NULL, 1, '2024-10-07 14:55', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(214, 'huy', 20, '2024-10-14', '2024-10-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '09/10/2024 09:00', '1', '', '09/10/2024 08:59', NULL, NULL, NULL, 1, '2024-10-09 09:00', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(215, 'trieu', 17, '2024-10-09', '2024-10-09', '07:30', '11:30', 'False', 'Bệnh', 'Không khỏe trong người', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '09/10/2024 13:08', '1', '', '09/10/2024 13:07', NULL, NULL, NULL, 1, '2024-10-09 13:08', 'quyen', '1', '0', NULL, NULL, '0'),
(216, 'vutrang', 14, '2024-10-10', '2024-10-10', '00:00', '00:00', 'True', 'Việc đột xuất', 'việc gia đình', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '09/10/2024 16:31', '1', '', '09/10/2024 16:31', NULL, NULL, NULL, 1, '2024-10-09 16:31', 'bi', '1', '0', NULL, NULL, '0'),
(217, 'tham', 20, '2024-10-14', '2024-10-14', '00:00', '00:00', 'True', 'Khác', 'về quê', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '09/10/2024 17:00', '1', 'đồng ý', '09/10/2024 16:59', NULL, NULL, NULL, 1, '2024-10-09 17:00', 'huy', '1', '0', NULL, NULL, '0'),
(218, 'khang', 17, '2024-10-10', '2024-10-10', '07:30', '11:30', 'False', 'Chăm bệnh', 'Đưa bé đi khám.\r\nĐã chỉnh sang trừ phép năm.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/10/2024 07:51', '1', 'chỉnh lại từ nghỉ trừ lương sang nghỉ phép năm', '10/10/2024 06:51', 'minhthomonly', '17/10/2024 07:29', 'Dinh Minh Thom', 4, '2024-10-18 07:51', 'minhthomonly', '1', '0', NULL, '0', '0'),
(219, 'thanh', 11, '2024-10-10', '2024-10-10', '00:00', '00:00', 'True', 'Việc đột xuất', 'Nghỉ phép', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/10/2024 07:21', '1', '', '10/10/2024 07:05', NULL, NULL, NULL, 1, '2024-10-10 07:21', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(220, 'nhu', 17, '2024-10-10', '2024-10-10', '13:00', '17:00', 'False', 'Việc đột xuất', 'Bận việc nhà', 'minhthomonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thom', '17/10/2024 07:53', '1', '', '10/10/2024 08:33', 'minhthanhonly', '17/10/2024 07:37', 'Dinh Minh Thanh', 5, '2024-10-17 07:53', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(221, 'minhthang', 14, '2024-10-11', '2024-10-11', '00:00', '00:00', 'True', 'Việc cá nhân', 'Đưa người thân đi khám bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '10/10/2024 09:56', '1', '', '10/10/2024 09:50', NULL, NULL, NULL, 1, '2024-10-10 09:56', 'hoai', '1', '0', NULL, NULL, '0'),
(222, 'quoc', 15, '2024-10-14', '2024-10-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'làm lại sổ bảo hiểm', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '10/10/2024 13:54', '1', '', '10/10/2024 13:53', NULL, NULL, NULL, 1, '2024-10-10 13:54', 'bien', '1', '0', NULL, NULL, '0'),
(223, 'duyhoang', 17, '2024-10-11', '2024-10-11', '00:00', '00:00', 'True', 'Giảm stress', 'Giảm stress', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '10/10/2024 16:31', '1', '', '10/10/2024 16:24', NULL, NULL, NULL, 1, '2024-10-10 16:31', 'nguyen', '1', '0', NULL, NULL, '0'),
(224, 'ngocle', 17, '2024-10-18', '2024-10-18', '00:00', '00:00', 'True', 'Khác', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '10/10/2024 16:32', '1', '', '10/10/2024 16:24', NULL, NULL, NULL, 1, '2024-10-10 16:32', 'nguyen', '1', '0', NULL, NULL, '0'),
(225, 'thienquan', 12, '2024-10-11', '2024-10-11', '13:00', '17:00', 'False', 'Khác', 'Nghỉ 1/2 PN\r\nViệc cá nhân', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '11/10/2024 10:33', '1', '', '11/10/2024 10:09', NULL, NULL, NULL, 5, '2024-10-11 10:33', 'hien', '1', '0', NULL, '0', '0'),
(226, 'tuyet2015', 8, '2024-10-11', '2024-10-11', ' 7:30', '10:00', 'False', 'Lỗi bất khả kháng', 'Mang máy lên VPMDC', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/10/2024 10:13', '1', '', '11/10/2024 10:13', NULL, NULL, NULL, 1, '2024-10-11 10:13', 'tranvinh.loc', '0', '0', NULL, NULL, '0'),
(227, 'ngan', 13, '2024-10-14', '2024-10-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '11/10/2024 10:24', '1', '', '11/10/2024 10:24', NULL, NULL, NULL, 1, '2024-10-11 10:24', 'van.tu', '1', '0', NULL, NULL, '0'),
(228, 'nhu', 17, '2024-10-14', '2024-10-14', '13:00', '17:00', 'False', 'Việc cá nhân', 'Bận việc nhà', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '14/10/2024 11:24', '1', '', '14/10/2024 08:08', NULL, NULL, NULL, 2, '2024-10-14 11:24', 'minhthomonly', '1', '0', NULL, '0', '0'),
(229, 'vankhanh', 9, '2024-10-18', '2024-10-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc gia đình', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '14/10/2024 09:42', '1', 'đã duyệt', '14/10/2024 09:23', NULL, NULL, NULL, 1, '2024-10-14 09:42', 'lien', '1', '0', NULL, NULL, '0'),
(230, 'truong', 15, '2024-10-14', '2024-10-14', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc đột xuất.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '14/10/2024 12:26', '1', '', '14/10/2024 12:01', NULL, NULL, NULL, 1, '2024-10-14 12:26', 'bien', '1', '0', NULL, NULL, '0'),
(231, 'thoi', 17, '2024-10-15', '2024-10-15', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân ', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '14/10/2024 13:33', '1', '', '14/10/2024 12:45', NULL, NULL, NULL, 1, '2024-10-14 13:33', 'huyhoang', '1', '0', NULL, NULL, '0'),
(232, 'vi', 14, '2024-10-15', '2024-10-15', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc đột xuất', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '14/10/2024 14:00', '1', '', '14/10/2024 12:54', NULL, NULL, NULL, 1, '2024-10-14 14:00', 'hoai', '1', '0', NULL, NULL, '0'),
(233, 'thiet', 14, '2024-10-15', '2024-10-15', '00:00', '00:00', 'True', 'Việc đột xuất', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '14/10/2024 14:07', '1', '', '14/10/2024 14:06', NULL, NULL, NULL, 1, '2024-10-14 14:07', 'bi', '1', '0', NULL, NULL, '0'),
(234, 'vutrang', 14, '2024-10-18', '2024-10-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc gia đình', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '14/10/2024 14:08', '1', '', '14/10/2024 14:07', NULL, NULL, NULL, 1, '2024-10-14 14:08', 'bi', '1', '0', NULL, NULL, '0'),
(235, 'minhthomonly', 17, '2024-10-14', '2024-10-14', '13:00', '17:00', 'False', 'Giảm stress', 'Nghỉ phép buổi chiều ', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '14/10/2024 14:15', '1', 'Duyệt tự động', '14/10/2024 14:13', NULL, NULL, NULL, 1, '2024-10-14 14:15', 'minhthomonly', '1', '0', NULL, '0', '0'),
(236, 'van', 12, '2024-10-15', '2024-10-15', '00:00', '00:00', 'True', 'Bệnh', 'Em khám bệnh', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '14/10/2024 16:37', '1', '', '14/10/2024 16:24', NULL, NULL, NULL, 2, '2024-10-14 16:37', 'hien', '1', '0', NULL, '0', '0'),
(237, 'luan', 18, '2024-10-18', '2024-10-18', '00:00', '00:00', 'True', 'Giảm stress', 'Nghỉ phép', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '15/10/2024 08:34', '1', '', '15/10/2024 07:46', NULL, NULL, NULL, 1, '2024-10-15 08:34', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(238, 'hanhthach', 20, '2024-10-18', '2024-10-18', '00:00', '00:00', 'True', 'Việc cá nhân', 'gia dinh co viec', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '15/10/2024 08:46', '1', 'đồng ý', '15/10/2024 08:39', NULL, NULL, NULL, 1, '2024-10-15 08:46', 'huy', '1', '0', NULL, NULL, '0'),
(239, 'hanhthach', 20, '2024-10-21', '2024-10-21', '07:30', '11:30', 'False', 'Việc cá nhân', 'gia dinh có viec', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '15/10/2024 08:46', '1', 'đồng ý', '15/10/2024 08:40', NULL, NULL, NULL, 1, '2024-10-15 08:46', 'huy', '1', '0', NULL, NULL, '0'),
(240, 'dat', 14, '2024-10-17', '2024-10-17', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'dat', 'Tran Vinh Dat', 'Dinh Minh Thom', '17/10/2024 08:26', '3', 'yêu cầu chỉnh sửa lại', '16/10/2024 07:30', 'minhthomonly', '17/10/2024 08:25', 'Dinh Minh Thom', 4, '2024-10-17 08:26', 'dat', '1', '0', NULL, '0', '0'),
(241, 'quyen', 17, '2024-10-18', '2024-10-18', '13:00', '17:00', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '16/10/2024 13:19', '1', '', '16/10/2024 07:37', NULL, NULL, NULL, 1, '2024-10-16 13:19', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(242, 'duya', 18, '2024-10-16', '2024-10-16', '13:00', '17:00', 'False', 'Về sớm', 'Bị sốt.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '16/10/2024 10:52', '1', '', '16/10/2024 10:51', NULL, NULL, NULL, 1, '2024-10-16 10:52', 'luan', '1', '0', NULL, NULL, '0'),
(243, 'huyhoang', 17, '2024-10-17', '2024-10-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '16/10/2024 16:59', '1', '', '16/10/2024 16:59', NULL, NULL, NULL, 1, '2024-10-16 16:59', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(244, 'tam', 8, '2024-10-17', '2024-10-17', '00:00', '00:00', 'True', 'Khám bệnh', 'ốm', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '16/10/2024 17:09', '1', 'Đã xác nhận\r\n', '16/10/2024 17:03', NULL, NULL, NULL, 1, '2024-10-16 17:09', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(245, 'minhthang', 14, '2024-10-14', '2024-10-16', '00:00', '00:00', 'True', 'Chăm bệnh', 'Chăm bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '17/10/2024 07:42', '1', '', '17/10/2024 07:41', NULL, NULL, NULL, 1, '2024-10-17 07:42', 'hoai', '1', '0', NULL, NULL, '0'),
(246, 'thinh_web', 7, '2024-10-18', '2024-10-18', '13:00', '17:00', 'False', 'Tang người thân', 'Em xin phép nghỉ buổi chiều đám tang em họ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '17/10/2024 11:01', '1', '', '17/10/2024 10:42', NULL, NULL, NULL, 1, '2024-10-17 11:01', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(247, 'thinh_web', 7, '2024-10-16', '2024-10-16', '00:00', '00:00', 'True', 'Mất điện', 'Mất điện - Việc đột xuất có tang người thân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '17/10/2024 11:01', '1', '', '17/10/2024 10:46', NULL, NULL, NULL, 1, '2024-10-17 11:01', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(248, 'nhu', 17, '2024-10-18', '2024-10-18', '07:30', '11:30', 'False', 'Việc đột xuất', 'Bận việc nhà', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/10/2024 07:51', '1', '', '18/10/2024 07:36', NULL, NULL, NULL, 1, '2024-10-18 07:51', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(249, 'vi', 14, '2024-10-21', '2024-10-21', '00:00', '00:00', 'True', 'Việc đột xuất', 'con bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '21/10/2024 07:37', '1', '', '21/10/2024 07:04', NULL, NULL, NULL, 1, '2024-10-21 07:37', 'hoai', '1', '0', NULL, NULL, '0'),
(250, 'minhthang', 14, '2024-10-22', '2024-10-22', '00:00', '00:00', 'True', 'Chăm bệnh', 'Chăm bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '21/10/2024 07:38', '1', '', '21/10/2024 07:37', NULL, NULL, NULL, 1, '2024-10-21 07:38', 'hoai', '1', '0', NULL, NULL, '0'),
(251, 'minhthang', 14, '2024-10-23', '2024-10-23', '00:00', '00:00', 'True', 'Chăm bệnh', 'Chăm bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '21/10/2024 07:39', '1', '', '21/10/2024 07:38', NULL, NULL, NULL, 1, '2024-10-21 07:39', 'hoai', '0', '0', NULL, NULL, '0'),
(252, 'thuylinh', 18, '2024-11-04', '2024-11-05', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '21/10/2024 07:45', '1', '', '21/10/2024 07:44', NULL, NULL, NULL, 1, '2024-10-21 07:45', 'luan', '1', '0', NULL, NULL, '0'),
(253, 'tranvinh.loc', 1, '2024-10-21', '2024-10-21', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc cá nhân đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/10/2024 07:50', '1', '', '21/10/2024 07:47', NULL, NULL, NULL, 1, '2024-10-21 07:50', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(254, 'tra_web', 7, '2024-10-23', '2024-10-23', '13:00', '17:00', 'False', 'Khám bệnh', 'khám bệnh ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '21/10/2024 08:26', '1', '', '21/10/2024 08:21', NULL, NULL, NULL, 1, '2024-10-21 08:26', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(255, 'khang', 17, '2024-10-25', '2024-10-25', '00:00', '00:00', 'True', 'Giảm stress', 'Về quê.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/10/2024 13:00', '1', '', '21/10/2024 08:33', NULL, NULL, NULL, 1, '2024-10-21 13:00', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(256, 'tuyet2015', 8, '2024-10-21', '2024-10-21', '07:30', '11:30', 'False', 'Khám bệnh', 'Tái khám ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/10/2024 07:13', '1', '', '21/10/2024 12:52', NULL, NULL, NULL, 1, '2024-10-22 07:13', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(257, 'chautuan', 11, '2024-10-25', '2024-10-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '23/10/2024 07:54', '1', 'OK', '21/10/2024 15:52', 'thanh', '23/10/2024 07:53', 'Doan Huu Thanh', 4, '2024-10-23 07:54', 'thanh', '1', '0', NULL, '0', '0'),
(258, 'khanh', 8, '2024-10-22', '2024-10-22', '07:30', '11:30', 'False', 'Khám bệnh', 'Kham benh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '22/10/2024 07:30', '1', 'Đã xác nhận', '22/10/2024 07:24', NULL, NULL, NULL, 1, '2024-10-22 07:30', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(259, 'huyhoang', 17, '2024-10-22', '2024-10-22', '13:00', '17:00', 'False', 'Giỗ', 'Đám giỗ ông', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '22/10/2024 13:23', '1', '', '22/10/2024 08:34', NULL, NULL, NULL, 1, '2024-10-22 13:23', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(260, 'ha', 10, '2024-10-22', '2024-10-22', '07:30', '08:05', 'False', 'Mất điện', '- Check giờ trễ do mất điện\r\n- Không tăng ca, làm bù giờ mất điện: 17h~17h45\r\n\r\n', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '22/10/2024 16:27', '1', 'Leader ok!', '22/10/2024 16:26', NULL, NULL, NULL, 1, '2024-10-22 16:27', 'nhan', '0', '0', NULL, NULL, '0'),
(261, 'tranvinh.loc', 1, '2024-10-23', '2024-10-23', '13:00', '17:00', 'False', 'Việc cá nhân', 'Ra phường làm giấy tờ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/10/2024 12:56', '1', '', '23/10/2024 08:35', NULL, NULL, NULL, 1, '2024-10-23 12:56', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(262, 'dat', 14, '2024-10-23', '2024-10-23', '07:30', '11:30', 'False', 'Bệnh', 'bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/10/2024 12:56', '1', '', '23/10/2024 12:46', NULL, NULL, NULL, 1, '2024-10-23 12:56', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(263, 'dat', 14, '2024-10-24', '2024-10-24', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/10/2024 12:56', '1', '', '23/10/2024 12:48', NULL, NULL, NULL, 1, '2024-10-23 12:56', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(264, 'vinh', 17, '2024-10-23', '2024-10-23', '00:00', '00:00', 'True', 'Lỗi bất khả kháng', 'nghỉ phép do cúp điện đột ngột lúc 9h sáng ( điện lực không báo trước và chưa biết khi nào có lại )', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '29/10/2024 08:23', '1', 'Thanh: Sửa loại hình là mất điện, từ 9h đến 17h. ', '23/10/2024 13:52', 'minhthanhonly', '29/10/2024 07:42', 'Dinh Minh Thanh', 3, '2024-10-29 08:23', 'khang', '1', '0', NULL, NULL, '0'),
(265, 'ngocle', 17, '2024-10-25', '2024-10-25', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '24/10/2024 13:17', '1', '', '24/10/2024 12:58', NULL, NULL, NULL, 1, '2024-10-24 13:17', 'nguyen', '1', '0', NULL, NULL, '0'),
(266, 'huy', 20, '2024-10-25', '2024-10-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC RIÊNG', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '25/10/2024 07:21', '1', '', '24/10/2024 17:12', NULL, NULL, NULL, 1, '2024-10-25 07:21', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(267, 'diemai', 7, '2024-11-15', '2024-11-15', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '25/10/2024 11:49', '1', '', '25/10/2024 07:57', NULL, NULL, NULL, 1, '2024-10-25 11:49', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(268, 'khanh', 8, '2024-10-25', '2024-10-25', '15:00', '17:00', 'False', 'Về sớm', 'Viec ca nhan', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '25/10/2024 13:00', '1', 'Đã xác nhận', '25/10/2024 11:33', NULL, NULL, NULL, 1, '2024-10-25 13:00', 'tuyet2015', '0', '0', NULL, NULL, '0'),
(269, 'ngoc', 10, '2024-10-29', '2024-10-29', '07:30', '11:30', 'False', 'Việc cá nhân', 'Bận việc gia đình', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '25/10/2024 16:18', '1', 'Leader ok!', '25/10/2024 16:17', NULL, NULL, NULL, 1, '2024-10-25 16:18', 'nhan', '1', '0', NULL, NULL, '0'),
(270, 'trieu', 17, '2024-10-28', '2024-10-28', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '28/10/2024 13:21', '1', '', '28/10/2024 12:48', NULL, NULL, NULL, 1, '2024-10-28 13:21', 'quyen', '1', '0', NULL, NULL, '0'),
(271, 'tu_web', 7, '2024-11-01', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '29/10/2024 07:43', '1', '', '28/10/2024 14:20', NULL, NULL, NULL, 1, '2024-10-29 07:43', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(272, 'vantoan', 17, '2024-10-29', '2024-10-29', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc bận gia đình', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '28/10/2024 15:54', '1', 'Đồng Ý ', '28/10/2024 15:53', NULL, NULL, NULL, 2, '2024-10-28 15:54', 'nhu', '1', '0', NULL, '0', '0'),
(273, 'dat', 14, '2024-10-29', '2024-10-29', '07:30', '11:30', 'False', 'Khác', 'lên VP lấy kết quả khám sức khỏe', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '28/10/2024 16:39', '1', '', '28/10/2024 16:36', NULL, NULL, NULL, 1, '2024-10-28 16:39', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(274, 'bich', 17, '2024-10-29', '2024-10-29', '07:30', '11:30', 'False', 'Việc cá nhân', 'Gia đình có việc riêng', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '28/10/2024 16:38', '1', 'Đồng ý cho nghỉ phép', '28/10/2024 16:36', NULL, NULL, NULL, 1, '2024-10-28 16:38', 'khang', '1', '0', NULL, NULL, '0'),
(275, 'ha', 10, '2024-11-01', '2024-11-01', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ phép năm-1 ngày', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '29/10/2024 09:00', '1', 'Leader ok!', '29/10/2024 08:51', 'nhan', '29/10/2024 08:59', 'Diep Thanh Nhan', 5, '2024-10-29 09:00', 'nhan', '1', '0', NULL, '0', '0'),
(276, 'ngan', 13, '2024-10-29', '2024-10-29', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '29/10/2024 09:52', '1', '', '29/10/2024 09:40', 'minhthanhonly', '29/10/2024 09:50', 'Dinh Minh Thanh', 4, '2024-10-29 09:52', 'van.tu', '1', '0', NULL, '0', '0'),
(277, 'tra_web', 7, '2024-10-30', '2024-10-30', '00:00', '00:00', 'True', 'Khám bệnh', 'Khám bệnh ', 'minhthanhonly', 'tra_web', 'Tran Thi My Tra', 'Dinh Minh Thanh', '31/10/2024 08:39', '3', '', '29/10/2024 11:25', NULL, NULL, NULL, 1, '2024-10-31 08:39', 'tra_web', '1', '0', NULL, NULL, '0'),
(278, 'tranvinh.loc', 1, '2024-10-30', '2024-10-30', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '29/10/2024 15:51', '1', '', '29/10/2024 15:11', NULL, NULL, NULL, 1, '2024-10-29 15:51', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(279, 'huyhoang', 17, '2024-10-30', '2024-10-30', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '29/10/2024 15:51', '1', '', '29/10/2024 15:13', NULL, NULL, NULL, 1, '2024-10-29 15:51', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(280, 'ngocle', 17, '2024-10-30', '2024-10-30', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '30/10/2024 07:24', '1', '', '30/10/2024 07:05', NULL, NULL, NULL, 1, '2024-10-30 07:24', 'nguyen', '1', '0', NULL, NULL, '0'),
(281, 'long', 14, '2024-10-30', '2024-10-30', '13:00', '17:00', 'False', 'Việc cá nhân', 'Xin nghỉ để đi lấy hồ sơ khám sức khỏe định kỳ', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '30/10/2024 09:56', '1', '', '30/10/2024 09:49', NULL, NULL, NULL, 1, '2024-10-30 09:56', 'hoai', '1', '0', NULL, NULL, '0'),
(282, 'tham', 20, '2024-11-01', '2024-11-04', '13:00', '17:00', 'False', 'Khác', 'về quê', 'huy', 'tham', 'Tran Thi Anh Tham', 'Tran Thanh Huy', '30/10/2024 10:15', '3', '', '30/10/2024 10:14', NULL, NULL, NULL, 1, '2024-10-30 10:15', 'tham', '1', '0', NULL, NULL, '0'),
(283, 'tham', 20, '2024-11-01', '2024-11-01', '13:00', '17:00', 'False', 'Khác', 'về quê', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '01/11/2024 07:37', '1', 'đồng ý', '30/10/2024 10:16', NULL, NULL, NULL, 3, '2024-11-01 07:37', 'huy', '1', '0', NULL, '0', '0'),
(284, 'tham', 20, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Khác', 'về quê', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '01/11/2024 07:38', '1', 'đồng ý\r\n', '30/10/2024 10:17', NULL, NULL, NULL, 1, '2024-11-01 07:38', 'huy', '1', '0', NULL, NULL, '0'),
(285, 'bich', 17, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Giảm stress', 'Giảm stress', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '31/10/2024 07:24', '1', 'Đồng ý cho nghỉ phép', '31/10/2024 07:24', NULL, NULL, NULL, 1, '2024-10-31 07:24', 'khang', '1', '0', NULL, NULL, '0'),
(286, 'minhthang', 14, '2024-11-01', '2024-11-01', '00:00', '00:00', 'True', 'Khám bệnh', 'Tái khám', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '31/10/2024 07:49', '1', '', '31/10/2024 07:48', NULL, NULL, NULL, 1, '2024-10-31 07:49', 'hoai', '1', '0', NULL, NULL, '0'),
(287, 'duykhanh', 17, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '31/10/2024 10:21', '1', '', '31/10/2024 10:20', NULL, NULL, NULL, 1, '2024-10-31 10:21', 'quyen', '1', '0', NULL, NULL, '0'),
(288, 'van.tu', 13, '2024-10-31', '2024-10-31', '07:30', '11:30', 'False', 'Việc cá nhân', 'VIỆC GIA ĐÌNH', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '31/10/2024 13:32', '1', '', '31/10/2024 13:30', NULL, NULL, NULL, 2, '2024-10-31 13:32', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(289, 'van.tu', 13, '2024-10-31', '2024-10-31', '13:00', '13:30', 'False', 'Đi trễ', 'VIỆC GIA ĐÌNH', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '31/10/2024 13:32', '1', '', '31/10/2024 13:31', NULL, NULL, NULL, 1, '2024-10-31 13:32', 'tranvinh.loc', '0', '0', NULL, NULL, '0'),
(290, 'dat', 14, '2024-11-01', '2024-11-01', '07:30', '11:30', 'False', 'Việc đột xuất', 'việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '01/11/2024 07:39', '1', '', '31/10/2024 20:40', NULL, NULL, NULL, 1, '2024-11-01 07:39', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(291, 'diemai', 7, '2024-10-04', '2024-10-04', '00:00', '00:00', 'True', 'Khám bệnh', 'Kham benh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '01/11/2024 07:44', '1', '', '01/11/2024 07:44', NULL, NULL, NULL, 1, '2024-11-01 07:44', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(292, 'ducanh', 9, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'BAN VIEC RIENG', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '01/11/2024 08:25', '1', 'đã duyệt', '01/11/2024 08:24', NULL, NULL, NULL, 1, '2024-11-01 08:25', 'lien', '1', '0', NULL, NULL, '0'),
(293, 'diemai', 7, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'viec ca nhan', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '01/11/2024 11:43', '1', '', '01/11/2024 10:20', NULL, NULL, NULL, 1, '2024-11-01 11:43', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(294, 'thinh_web', 7, '2024-11-08', '2024-11-08', '00:00', '00:00', 'True', 'Khám bệnh', 'Đưa người thân khám bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '01/11/2024 11:44', '1', '', '01/11/2024 11:43', NULL, NULL, NULL, 1, '2024-11-01 11:44', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(295, 'nhan', 10, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '01/11/2024 14:43', '1', '', '01/11/2024 14:42', NULL, NULL, NULL, 1, '2024-11-01 14:43', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(296, 'hien', 12, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Giảm stress', 'việc cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '01/11/2024 14:57', '1', '', '01/11/2024 14:57', NULL, NULL, NULL, 1, '2024-11-01 14:57', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(297, 'tra_web', 7, '2024-11-08', '2024-11-19', '00:00', '00:00', 'True', 'Khám bệnh', 'chữa bệnh ', 'minhthanhonly', 'tra_web', 'Tran Thi My Tra', 'Dinh Minh Thanh', '01/11/2024 15:41', '3', '', '01/11/2024 15:13', NULL, NULL, NULL, 1, '2024-11-01 15:41', 'tra_web', '0', '0', NULL, NULL, '0'),
(298, 'vi', 14, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân ', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '01/11/2024 16:30', '1', '', '01/11/2024 16:03', NULL, NULL, NULL, 1, '2024-11-01 16:30', 'hoai', '1', '0', NULL, NULL, '0'),
(299, 'hoaibao', 13, '2024-11-05', '2024-11-05', '00:00', '00:00', 'True', 'Mất điện', 'cúp điện cả ngày', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '01/11/2024 16:55', '1', '', '01/11/2024 16:52', NULL, NULL, NULL, 2, '2024-11-01 16:55', 'van.tu', '1', '0', NULL, '0', '0'),
(300, 'hoaibao', 13, '2024-11-07', '2024-11-07', '07:30', '11:30', 'False', 'Mất điện', 'Cúp điện nửa ngày', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '01/11/2024 16:55', '1', '', '01/11/2024 16:54', NULL, NULL, NULL, 2, '2024-11-01 16:55', 'van.tu', '1', '0', NULL, '0', '0'),
(301, 'vanphuc', 7, '2024-12-12', '2024-12-13', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '11/11/2024 09:45', '1', '', '01/11/2024 16:58', NULL, NULL, NULL, 1, '2024-11-11 09:45', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(302, 'vankhanh', 9, '2024-11-08', '2024-11-11', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc riêng.', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '01/11/2024 17:03', '1', 'đã duyệt\r\n', '01/11/2024 17:00', NULL, NULL, NULL, 1, '2024-11-01 17:03', 'lien', '1', '0', NULL, NULL, '0'),
(303, 'thoi', 17, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc đột xuất', 'việc gia đình', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '04/11/2024 07:22', '1', '', '04/11/2024 04:56', NULL, NULL, NULL, 1, '2024-11-04 07:22', 'huyhoang', '0', '0', NULL, NULL, '0'),
(304, 'nhu', 17, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép ', 'minhthomonly', 'nhu', 'Nguyen Thi Nhu', 'Dinh Minh Thom', '04/11/2024 06:17', '3', '', '04/11/2024 06:17', NULL, NULL, NULL, 1, '2024-11-04 06:17', 'nhu', '0', '0', NULL, NULL, '0'),
(305, 'nhu', 17, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/11/2024 08:48', '1', '', '04/11/2024 06:18', NULL, NULL, NULL, 1, '2024-11-05 08:48', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(306, 'ly', 14, '2024-11-04', '2024-11-04', '07:30', '11:30', 'False', 'Việc đột xuất', 'Có việc đột xuất', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '04/11/2024 07:49', '1', '', '04/11/2024 06:39', NULL, NULL, NULL, 1, '2024-11-04 07:49', 'bi', '1', '0', NULL, NULL, '0'),
(307, 'dinh', 8, '2024-11-01', '2024-11-01', '13:00', '17:00', 'False', 'Mất điện', 'bị cúp điện đột xuất', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '06/11/2024 15:28', '1', 'Đã xác nhận ', '04/11/2024 06:55', NULL, NULL, NULL, 1, '2024-11-06 15:28', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(308, 'minhthomonly', 17, '2024-11-04', '2024-11-04', '07:30', '17:00', 'False', 'Giảm stress', 'Nghỉ phép', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '04/11/2024 07:25', '1', 'Duyệt tự động', '04/11/2024 07:25', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(309, 'dat', 14, '2024-11-04', '2024-11-04', '00:00', '00:00', 'True', 'Giảm stress', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/11/2024 08:48', '1', '', '04/11/2024 15:50', NULL, NULL, NULL, 1, '2024-11-05 08:48', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(310, 'thienquan', 12, '2024-11-04', '2024-11-04', '16:30', '17:00', 'False', 'Mất điện', 'Mất điện, làm bù giờ', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '05/11/2024 09:05', '1', '', '04/11/2024 18:28', NULL, NULL, NULL, 2, '2024-11-05 09:05', 'hien', '0', '0', NULL, '1', '0'),
(311, 'bich', 17, '2024-11-05', '2024-11-05', '07:30', '11:30', 'False', 'Chăm bệnh', 'con ốm', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '05/11/2024 13:00', '1', 'Đồng ý cho nghỉ phép', '05/11/2024 12:41', NULL, NULL, NULL, 1, '2024-11-05 13:00', 'khang', '1', '0', NULL, NULL, '0'),
(312, 'nguyen', 17, '2024-11-06', '2024-11-06', '13:00', '17:00', 'False', 'Khám bệnh', 'Khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '06/11/2024 07:34', '1', '', '06/11/2024 07:33', NULL, NULL, NULL, 1, '2024-11-06 07:34', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(313, 'huy', 20, '2024-11-07', '2024-11-07', '00:00', '00:00', 'True', 'Giỗ', 'Đám giỗ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '06/11/2024 07:35', '1', '', '06/11/2024 07:34', NULL, NULL, NULL, 1, '2024-11-06 07:35', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(314, 'ly', 14, '2024-11-06', '2024-11-06', '13:00', '17:00', 'False', 'Việc đột xuất', 'Có việc đột xuất', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '06/11/2024 10:08', '1', '', '06/11/2024 10:06', NULL, NULL, NULL, 1, '2024-11-06 10:08', 'bi', '1', '0', NULL, NULL, '0'),
(315, 'duyhoang', 17, '2024-11-08', '2024-11-08', '00:00', '00:00', 'True', 'Giỗ', 'đám dỗ', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '06/11/2024 11:24', '1', '', '06/11/2024 10:58', 'nguyen', '06/11/2024 11:19', 'Lam Duy Nguyen', 4, '2024-11-06 11:24', 'nguyen', '1', '0', NULL, '0', '0'),
(316, 'ngocle', 17, '2024-11-15', '2024-11-15', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '06/11/2024 11:03', '1', '', '06/11/2024 11:02', NULL, NULL, NULL, 1, '2024-11-06 11:03', 'nguyen', '1', '0', NULL, NULL, '0'),
(317, 'tam', 8, '2024-11-07', '2024-11-07', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '06/11/2024 15:28', '1', 'Đã xác nhận', '06/11/2024 15:26', NULL, NULL, NULL, 1, '2024-11-06 15:28', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(318, 'hoai', 14, '2024-11-07', '2024-11-08', '00:00', '00:00', 'True', 'Tang người thân', 'Gia đình có chuyện buồn', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '11/11/2024 07:55', '1', 'chia buồn cùng em và gia đình.', '07/11/2024 08:41', 'minhthanhonly', '11/11/2024 07:50', 'Dinh Minh Thanh', 4, '2024-11-11 07:55', 'dat', '1', '0', NULL, '0', '0'),
(319, 'nhan', 10, '2024-11-07', '2024-11-07', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân! ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/11/2024 09:17', '1', '', '07/11/2024 09:17', NULL, NULL, NULL, 1, '2024-11-07 09:17', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(320, 'bi', 14, '2024-11-07', '2024-11-08', '01:00', '17:00', 'False', 'Việc đột xuất', 'Có việc gấp', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '07/11/2024 10:39', '1', '', '07/11/2024 10:36', NULL, NULL, NULL, 2, '2024-11-07 10:39', 'dat', '1', '0', NULL, '0', '0'),
(321, 'huyhoang', 17, '2024-11-08', '2024-11-08', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '07/11/2024 14:30', '1', '', '07/11/2024 14:29', NULL, NULL, NULL, 1, '2024-11-07 14:30', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(322, 'lien', 9, '2024-11-08', '2024-11-08', '13:00', '17:00', 'False', 'Việc cá nhân', 'về quê', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '08/11/2024 09:28', '1', '', '08/11/2024 09:26', NULL, NULL, NULL, 2, '2024-11-08 09:28', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(323, 'thienquan', 12, '2024-11-08', '2024-11-08', '13:00', '17:00', 'False', 'Việc cá nhân', 'Nghỉ 1/2 phép việc cá nhân', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '08/11/2024 11:26', '1', '', '08/11/2024 10:55', NULL, NULL, NULL, 1, '2024-11-08 11:26', 'hien', '1', '0', NULL, NULL, '0'),
(324, 'nhu', 17, '2024-11-08', '2024-11-08', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/11/2024 10:33', '1', '', '08/11/2024 11:32', NULL, NULL, NULL, 1, '2024-11-11 10:33', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(325, 'tuyen', 8, '2024-11-11', '2024-11-11', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc gia đình', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '08/11/2024 16:48', '1', 'Đã xác nhận', '08/11/2024 16:48', NULL, NULL, NULL, 1, '2024-11-08 16:48', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(326, 'long', 14, '2024-11-08', '2024-11-08', '00:00', '00:00', 'True', 'Bệnh', 'Bệnh nên xin nghỉ phép 1 ngày', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '11/11/2024 14:01', '1', '', '11/11/2024 07:25', NULL, NULL, NULL, 1, '2024-11-11 14:01', 'hoai', '1', '0', NULL, NULL, '0'),
(327, 'van.tu', 13, '2024-11-13', '2024-11-13', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/11/2024 08:00', '1', '', '11/11/2024 07:53', NULL, NULL, NULL, 1, '2024-11-11 08:00', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(328, 'minhthomonly', 17, '2024-11-11', '2024-11-11', '07:30', '11:30', 'False', 'Bệnh', 'người không khỏe', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '11/11/2024 10:32', '1', 'Duyệt tự động', '11/11/2024 10:32', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0');
INSERT INTO `groupware_dayoff` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`, `minus_leave`, `is_repeat`, `cancel_repeat_dates`, `is_overwork`, `is_BHXH`) VALUES
(329, 'thiet', 14, '2024-11-11', '2024-11-11', '07:30', '11:30', 'False', 'Việc đột xuất', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '11/11/2024 15:09', '1', '', '11/11/2024 15:07', NULL, NULL, NULL, 1, '2024-11-11 15:09', 'bi', '1', '0', NULL, NULL, '0'),
(330, 'vinh', 17, '2024-11-12', '2024-11-12', '00:00', '00:00', 'True', 'Bệnh', 'bị ốm.', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '12/11/2024 08:25', '1', 'Đồng ý cho nghỉ phép', '12/11/2024 08:25', NULL, NULL, NULL, 1, '2024-11-12 08:25', 'khang', '1', '0', NULL, NULL, '0'),
(331, 'vutrang', 14, '2024-11-12', '2024-11-12', '13:00', '17:00', 'False', 'Việc đột xuất', ' Bận việc cá nhân, ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '12/11/2024 10:25', '1', '', '12/11/2024 10:24', NULL, NULL, NULL, 1, '2024-11-12 10:25', 'bi', '1', '0', NULL, NULL, '0'),
(332, 'nhan', 10, '2024-11-13', '2024-11-13', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/11/2024 11:20', '1', '', '12/11/2024 11:20', NULL, NULL, NULL, 1, '2024-11-12 11:20', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(333, 'truong', 15, '2024-11-22', '2024-11-22', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc gia đình.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '12/11/2024 13:19', '1', '', '12/11/2024 13:17', NULL, NULL, NULL, 1, '2024-11-12 13:19', 'bien', '1', '0', NULL, NULL, '0'),
(334, 'truong', 15, '2024-11-29', '2024-11-29', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc gia đình.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '12/11/2024 13:19', '1', '', '12/11/2024 13:18', NULL, NULL, NULL, 1, '2024-11-12 13:19', 'bien', '1', '0', NULL, NULL, '0'),
(335, 'quoc', 15, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '12/11/2024 13:23', '1', '', '12/11/2024 13:22', NULL, NULL, NULL, 1, '2024-11-12 13:23', 'bien', '1', '0', NULL, NULL, '0'),
(336, 'thanh', 11, '2024-11-14', '2024-11-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/11/2024 13:30', '1', '', '12/11/2024 13:29', NULL, NULL, NULL, 1, '2024-11-12 13:30', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(337, 'lien', 9, '2024-11-13', '2024-11-13', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/11/2024 14:36', '1', '', '12/11/2024 14:36', NULL, NULL, NULL, 1, '2024-11-12 14:36', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(338, 'bich', 17, '2024-11-13', '2024-11-13', '07:30', '11:30', 'False', 'Chăm bệnh', 'đưa phụ huynh đi khám bệnh', 'khang', 'minhthanhonly', 'Dinh Minh Thanh', 'Pham Nguyen Khang', '20/11/2024 07:50', '2', 'nhập sai', '12/11/2024 15:11', NULL, NULL, NULL, 2, '2024-11-20 07:50', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(339, 'bi', 14, '2024-11-13', '2024-11-13', '00:00', '00:00', 'True', 'Việc cá nhân', 'Có việc riêng', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '13/11/2024 12:04', '1', '', '13/11/2024 06:46', NULL, NULL, NULL, 1, '2024-11-13 12:04', 'dat', '1', '0', NULL, NULL, '0'),
(340, 'thoi', 17, '2024-11-18', '2024-11-18', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '13/11/2024 07:51', '1', '', '13/11/2024 07:06', NULL, NULL, NULL, 1, '2024-11-13 07:51', 'huyhoang', '1', '0', NULL, NULL, '0'),
(341, 'ngoc', 10, '2024-11-14', '2024-11-14', '00:00', '00:00', 'True', 'Khám bệnh', 'đi khám bệnh', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '13/11/2024 16:48', '1', 'Leader ok!', '13/11/2024 16:48', NULL, NULL, NULL, 1, '2024-11-13 16:48', 'nhan', '1', '0', NULL, NULL, '0'),
(342, 'quocthinh_web', 7, '2024-12-25', '2024-12-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/12/2024 16:25', '2', '', '14/11/2024 07:26', NULL, NULL, NULL, 2, '2024-12-30 16:25', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(343, 'thienquan', 12, '2024-11-14', '2024-11-14', '07:30', '11:30', 'False', 'Bệnh', 'Bệnh, nghỉ 0.5 phép năm', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '14/11/2024 13:18', '1', '', '14/11/2024 12:55', NULL, NULL, NULL, 1, '2024-11-14 13:18', 'hien', '1', '0', NULL, NULL, '0'),
(344, 'vi', 14, '2024-11-15', '2024-11-15', '13:00', '17:00', 'False', 'Bệnh', 'Bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '15/11/2024 07:04', '1', '', '15/11/2024 06:45', NULL, NULL, NULL, 1, '2024-11-15 07:04', 'hoai', '1', '0', NULL, NULL, '0'),
(345, 'duya', 18, '2024-11-25', '2024-11-25', '00:00', '00:00', 'True', 'Giảm stress', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '15/11/2024 07:29', '1', '', '15/11/2024 07:28', NULL, NULL, NULL, 1, '2024-11-15 07:29', 'luan', '1', '0', NULL, NULL, '0'),
(346, 'luan', 18, '2024-11-22', '2024-11-22', '00:00', '00:00', 'True', 'Việc đột xuất', 'Việc đột xuất', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '19/11/2024 07:46', '1', '', '15/11/2024 07:31', NULL, NULL, NULL, 2, '2024-11-19 07:46', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(347, 'hanhthach', 20, '2024-11-15', '2024-11-15', '13:00', '17:00', 'False', 'Việc cá nhân', 'có viec gia dinh', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '15/11/2024 11:20', '1', 'đồng ý\r\n', '15/11/2024 11:18', NULL, NULL, NULL, 1, '2024-11-15 11:20', 'huy', '1', '0', NULL, NULL, '0'),
(348, 'van.tu', 13, '2024-11-18', '2024-11-18', '07:30', '11:30', 'False', 'Mất điện', 'MẤT ĐIỆN BUỔI SÁNG', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/11/2024 08:05', '1', '', '18/11/2024 05:54', NULL, NULL, NULL, 1, '2024-11-18 08:05', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(349, 'thienquan', 12, '2024-11-18', '2024-11-18', '13:00', '17:00', 'False', 'Việc cá nhân', 'Nghỉ việc cá nhân trừ phép năm 0.5 ngày', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '18/11/2024 08:08', '1', '', '18/11/2024 07:37', NULL, NULL, NULL, 1, '2024-11-18 08:08', 'hien', '1', '0', NULL, NULL, '0'),
(350, 'lethuy', 7, '2024-11-22', '2024-11-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'về quê', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '19/11/2024 07:50', '1', '', '18/11/2024 13:37', NULL, NULL, NULL, 1, '2024-11-19 07:50', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(351, 'tuyet2015', 8, '2024-11-20', '2024-11-20', '07:30', '11:30', 'False', 'Việc cá nhân', 'Dự lễ cùng con gái', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/11/2024 15:44', '1', '', '18/11/2024 15:42', NULL, NULL, NULL, 1, '2024-11-18 15:44', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(352, 'hoaibao', 13, '2024-11-22', '2024-11-22', '13:00', '17:00', 'False', 'Việc cá nhân', 'bận việc gia đình', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '18/11/2024 17:01', '1', '', '18/11/2024 16:27', NULL, NULL, NULL, 1, '2024-11-18 17:01', 'van.tu', '1', '0', NULL, NULL, '0'),
(353, 'luan', 18, '2024-11-19', '2024-11-19', '00:00', '00:00', 'True', 'Việc đột xuất', 'Việc đột xuất', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '19/11/2024 07:46', '1', '', '18/11/2024 20:29', NULL, NULL, NULL, 1, '2024-11-19 07:46', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(354, 'nhu', 17, '2024-11-19', '2024-11-19', '13:00', '17:00', 'False', 'Về sớm', 'Bận việc cá nhân .', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/11/2024 12:59', '1', '', '19/11/2024 08:51', NULL, NULL, NULL, 1, '2024-11-19 12:59', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(355, 'vantoan', 17, '2024-11-22', '2024-11-22', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc bận gia đình', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '19/11/2024 11:25', '1', 'ĐÃ XÁC NHẬN', '19/11/2024 11:20', NULL, NULL, NULL, 2, '2024-11-19 11:25', 'nhu', '1', '0', NULL, '0', '0'),
(356, 'bien', 15, '2024-11-19', '2024-11-19', '00:00', '00:00', 'True', 'Khám bệnh', 'bị cảm', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/11/2024 12:59', '1', '', '19/11/2024 12:07', NULL, NULL, NULL, 1, '2024-11-19 12:59', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(357, 'hien', 12, '2024-11-20', '2024-11-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '19/11/2024 14:43', '1', '', '19/11/2024 14:42', NULL, NULL, NULL, 1, '2024-11-19 14:43', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(358, 'hanhthach', 20, '2024-11-20', '2024-11-20', '00:00', '00:00', 'True', 'Việc đột xuất', 'ứng phép tháng 12 ( 0.5 ngày )', 'huy', 'minhthanhonly', 'Dinh Minh Thanh', 'Tran Thanh Huy', '03/12/2024 07:28', '1', 'đồng ý\r\n', '19/11/2024 16:30', 'huy', '19/11/2024 16:37', 'Tran Thanh Huy', 4, '2024-12-03 07:28', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(359, 'bich', 17, '2024-11-12', '2024-11-12', '13:00', '17:00', 'False', 'Chăm bệnh', 'Chăm bệnh', 'khang', 'minhthanhonly', 'Dinh Minh Thanh', 'Pham Nguyen Khang', '20/11/2024 07:49', '2', 'nhập sai', '20/11/2024 06:43', NULL, NULL, NULL, 2, '2024-11-20 07:49', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(360, 'bich', 17, '2024-11-13', '2024-11-18', '00:00', '00:00', 'True', 'Chăm bệnh', 'Chăm bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '20/11/2024 07:37', '1', '', '20/11/2024 06:44', NULL, NULL, NULL, 1, '2024-11-20 07:37', 'khang', '1', '0', NULL, NULL, '0'),
(361, 'bich', 17, '2024-11-19', '2024-11-19', '00:00', '00:00', 'True', 'Lỗi bất khả kháng', 'chăm bệnh ( Trư lương )', 'khang', 'bich', 'Nguyen Thi Ngoc Bich', 'Pham Nguyen Khang', '20/11/2024 06:44', '3', '', '20/11/2024 06:44', NULL, NULL, NULL, 1, '2024-11-20 06:44', 'bich', '0', '0', NULL, NULL, '0'),
(362, 'bich', 17, '2024-11-19', '2024-11-19', '00:00', '00:00', 'True', 'Chăm bệnh', 'chăm bệnh ( trừ lương)', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '20/11/2024 07:37', '1', '', '20/11/2024 06:45', NULL, NULL, NULL, 1, '2024-11-20 07:37', 'khang', '0', '0', NULL, NULL, '0'),
(363, 'bien', 15, '2024-11-20', '2024-11-20', '00:00', '00:00', 'True', 'Khác', 'cảm sốt , đang uống thuốc theo giỏi bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/11/2024 07:37', '1', '', '20/11/2024 07:27', NULL, NULL, NULL, 1, '2024-11-20 07:37', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(364, 'vinh', 17, '2024-11-21', '2024-11-21', '07:30', '11:30', 'False', 'Khám bệnh', 'khám bệnh cho bé.', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '21/11/2024 13:00', '1', '', '21/11/2024 07:07', NULL, NULL, NULL, 1, '2024-11-21 13:00', 'khang', '1', '0', NULL, NULL, '0'),
(365, 'thuylinh', 18, '2024-11-29', '2024-11-29', '00:00', '00:00', 'True', 'Giảm stress', 'nghỉ phép năm', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '21/11/2024 07:55', '1', '', '21/11/2024 07:53', NULL, NULL, NULL, 1, '2024-11-21 07:55', 'luan', '1', '0', NULL, NULL, '0'),
(366, 'thuylinh', 18, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Khác', 'nghỉ phép năm', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '21/11/2024 07:55', '1', '', '21/11/2024 07:55', NULL, NULL, NULL, 1, '2024-11-21 07:55', 'luan', '1', '0', NULL, NULL, '0'),
(367, 'tham', 20, '2024-11-22', '2024-11-22', '00:00', '00:00', 'True', 'Việc cá nhân', 'đưa mẹ đi khám bệnh', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '21/11/2024 15:59', '1', 'đồng ý', '21/11/2024 12:52', NULL, NULL, NULL, 1, '2024-11-21 15:59', 'huy', '1', '0', NULL, NULL, '0'),
(368, 'thoi', 17, '2024-11-22', '2024-11-22', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc đột xuất', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '21/11/2024 14:45', '1', '', '21/11/2024 14:24', NULL, NULL, NULL, 1, '2024-11-21 14:45', 'huyhoang', '1', '0', NULL, NULL, '0'),
(369, 'dai', 14, '2024-11-22', '2024-11-22', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc riêng', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '21/11/2024 16:15', '1', '', '21/11/2024 16:07', NULL, NULL, NULL, 1, '2024-11-21 16:15', 'bi', '1', '0', NULL, NULL, '0'),
(370, 'chautuan', 11, '2024-11-27', '2024-11-27', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '21/11/2024 16:26', '1', 'OK', '21/11/2024 16:24', NULL, NULL, NULL, 1, '2024-11-21 16:26', 'thanh', '1', '0', NULL, NULL, '0'),
(371, 'tra_web', 7, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '26/11/2024 07:32', '1', '', '21/11/2024 16:38', NULL, NULL, NULL, 1, '2024-11-26 07:32', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(372, 'tra_web', 7, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Khám bệnh', 'khám bệnh ', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '26/11/2024 07:32', '1', '', '21/11/2024 16:39', NULL, NULL, NULL, 1, '2024-11-26 07:32', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(373, 'cong', 11, '2024-11-28', '2024-11-28', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc gia đình', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '22/11/2024 08:38', '1', 'OK', '22/11/2024 08:13', NULL, NULL, NULL, 1, '2024-11-22 08:38', 'thanh', '1', '0', NULL, NULL, '0'),
(374, 'van.tu', 13, '2024-11-18', '2024-11-18', '13:00', '13:13', 'False', 'Mất điện', 'MẤT ĐIỆN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/11/2024 08:36', '1', '', '22/11/2024 08:33', NULL, NULL, NULL, 1, '2024-11-22 08:36', 'tranvinh.loc', '0', '0', NULL, NULL, '0'),
(375, 'van.tu', 13, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'tranvinh.loc', 'van.tu', 'Huynh Van Tu', 'Tran Vinh Loc', '29/11/2024 07:31', '3', '', '22/11/2024 08:33', 'tranvinh.loc', '29/11/2024 09:25', 'Tran Vinh Loc', 5, '2024-11-29 07:31', 'van.tu', '1', '0', NULL, '0', '0'),
(376, 'long', 14, '2024-11-22', '2024-11-22', '13:00', '17:00', 'False', 'Việc đột xuất', 'Xin nghỉ vì có việc đột xuất', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '22/11/2024 10:08', '1', '', '22/11/2024 10:00', NULL, NULL, NULL, 1, '2024-11-22 10:08', 'hoai', '1', '0', NULL, NULL, '0'),
(377, 'thiet', 14, '2024-11-22', '2024-11-22', '13:00', '17:00', 'False', 'Việc đột xuất', 'có việc riêng ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '22/11/2024 10:56', '1', '', '22/11/2024 10:56', NULL, NULL, NULL, 1, '2024-11-22 10:56', 'bi', '0', '0', NULL, NULL, '0'),
(378, 'long', 14, '2024-11-25', '2024-11-25', '00:00', '00:00', 'True', 'Khám bệnh', 'Xin nghỉ phép để đi khám bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '25/11/2024 13:54', '1', '', '25/11/2024 07:13', NULL, NULL, NULL, 1, '2024-11-25 13:54', 'hoai', '1', '0', NULL, NULL, '0'),
(379, 'minhthang', 14, '2024-11-29', '2024-11-29', '00:00', '00:00', 'True', 'Khám bệnh', 'Khám bệnh', 'hoai', 'minhthang', 'Nguyen Minh Thang', 'Nguyen Thi Hoai', '25/11/2024 13:51', '3', '', '25/11/2024 13:51', NULL, NULL, NULL, 1, '2024-11-25 13:51', 'minhthang', '1', '0', NULL, NULL, '0'),
(380, 'minhthang', 14, '2024-11-29', '2024-11-29', '00:00', '00:00', 'True', 'Khám bệnh', 'Khám bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '25/11/2024 13:54', '1', '', '25/11/2024 13:53', NULL, NULL, NULL, 1, '2024-11-25 13:54', 'hoai', '1', '0', NULL, NULL, '0'),
(381, 'quoc', 15, '2024-11-28', '2024-11-28', '13:00', '17:00', 'False', 'Việc đột xuất', 'việc cá nhân', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '25/11/2024 16:52', '1', '', '25/11/2024 16:41', NULL, NULL, NULL, 1, '2024-11-25 16:52', 'bien', '1', '0', NULL, NULL, '0'),
(382, 'huy', 20, '2024-11-27', '2024-11-27', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '25/11/2024 16:53', '1', '', '25/11/2024 16:53', NULL, NULL, NULL, 1, '2024-11-25 16:53', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(383, 'quyen', 17, '2024-11-27', '2024-11-27', '13:00', '17:00', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/11/2024 07:39', '1', '', '27/11/2024 07:39', NULL, NULL, NULL, 1, '2024-11-27 07:39', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(384, 'tranvinh.loc', 1, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Giỗ', 'về quê ăn giỗ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/11/2024 08:00', '1', '', '27/11/2024 07:58', NULL, NULL, NULL, 1, '2024-11-27 08:00', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(385, 'truong', 15, '2024-12-05', '2024-12-05', '00:00', '00:00', 'True', 'Khám bệnh', 'ĐI KHÁM BÊNH.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '27/11/2024 08:19', '1', '', '27/11/2024 08:14', NULL, NULL, NULL, 1, '2024-11-27 08:19', 'bien', '0', '0', NULL, NULL, '0'),
(386, 'truong', 15, '2024-12-18', '2024-12-18', '00:00', '00:00', 'True', 'Khám bệnh', 'ĐI KHÁM BỆNH.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '27/11/2024 08:19', '1', '', '27/11/2024 08:14', NULL, NULL, NULL, 1, '2024-11-27 08:19', 'bien', '0', '0', NULL, NULL, '0'),
(387, 'bien', 15, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép trùng với dịp nghỉ tết bên nhật (việc cá nhân)\r\n', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/11/2024 08:23', '1', '', '27/11/2024 08:15', NULL, NULL, NULL, 1, '2024-11-27 08:23', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(388, 'ducanh', 9, '2024-11-26', '2024-11-26', '13:00', '17:00', 'False', 'Mất điện', 'mất điện', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '27/11/2024 12:50', '1', 'đã duyệt\r\n', '27/11/2024 12:49', NULL, NULL, NULL, 1, '2024-11-27 12:50', 'lien', '1', '0', NULL, NULL, '0'),
(389, 'vankhanh', 9, '2024-12-02', '2024-12-03', '00:00', '00:00', 'True', 'Khác', 'Bận việc riêng', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '27/11/2024 15:30', '1', 'da duyet', '27/11/2024 15:29', NULL, NULL, NULL, 1, '2024-11-27 15:30', 'lien', '1', '0', NULL, NULL, '0'),
(390, 'vutrang', 14, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/11/2024 15:46', '1', '', '27/11/2024 15:45', NULL, NULL, NULL, 1, '2024-11-27 15:46', 'bi', '1', '0', NULL, NULL, '0'),
(391, 'duykhanh', 17, '2024-11-29', '2024-11-29', '00:00', '00:00', 'True', 'Khác', 'Bận việc riêng', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '02/12/2024 07:52', '1', '', '28/11/2024 07:24', NULL, NULL, NULL, 1, '2024-12-02 07:52', 'quyen', '1', '0', NULL, NULL, '0'),
(392, 'khang', 17, '2024-11-27', '2024-11-27', '13:00', '17:00', 'False', 'Lỗi bất khả kháng', 'Mất điện lúc 12h35, tới 14h00 vẫn chưa có điện nên xin nghỉ phép 0.5 ngày.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '29/11/2024 07:49', '1', '', '28/11/2024 07:54', NULL, NULL, NULL, 1, '2024-11-29 07:49', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(393, 'thuylinh', 18, '2024-11-28', '2024-11-28', '00:00', '00:00', 'True', 'Bệnh', 'bệnh', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '28/11/2024 16:59', '1', '', '28/11/2024 09:06', NULL, NULL, NULL, 1, '2024-11-28 16:59', 'luan', '1', '0', NULL, NULL, '0'),
(394, 'ha', 10, '2024-11-29', '2024-11-29', '13:00', '17:00', 'False', 'Việc cá nhân', 'nghỉ phép năm 0.5 ngày', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '28/11/2024 13:59', '1', 'Leader ok!', '28/11/2024 13:49', NULL, NULL, NULL, 1, '2024-11-28 13:59', 'nhan', '1', '0', NULL, NULL, '0'),
(395, 'ngan', 13, '2024-11-29', '2024-11-29', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '28/11/2024 16:47', '1', '', '28/11/2024 16:17', NULL, NULL, NULL, 1, '2024-11-28 16:47', 'van.tu', '1', '0', NULL, NULL, '0'),
(396, 'lethuy', 7, '2024-12-02', '2024-12-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'về quê', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '29/11/2024 09:32', '1', '', '29/11/2024 08:29', NULL, NULL, NULL, 1, '2024-11-29 09:32', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(397, 'ly', 14, '2024-12-19', '2024-12-19', '13:00', '17:00', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '04/12/2024 13:42', '1', '', '29/11/2024 08:56', 'bi', '04/12/2024 13:37', 'Nguyen Van Bi', 4, '2024-12-04 13:42', 'bi', '1', '0', NULL, '0', '0'),
(398, 'tam', 8, '2024-11-29', '2024-11-29', '07:30', '11:30', 'False', 'Việc cá nhân', 'con bị ốm đưa bé đi khám bệnh', 'tuyet2015', 'minhthanhonly', 'Dinh Minh Thanh', 'Huynh Thi Anh Tuyet', '29/11/2024 10:55', '2', 'sửa giờ', '29/11/2024 10:39', 'minhthanhonly', '29/11/2024 10:51', 'Dinh Minh Thanh', 3, '2024-11-29 10:55', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(399, 'cong', 11, '2024-12-06', '2024-12-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '29/11/2024 10:43', '1', 'OK', '29/11/2024 10:42', NULL, NULL, NULL, 1, '2024-11-29 10:43', 'thanh', '1', '0', NULL, NULL, '0'),
(400, 'tam', 8, '2024-11-29', '2024-11-29', '13:00', '17:00', 'False', 'Việc cá nhân', 'con ốm đưa bé đi khám bệnh', 'tuyet2015', 'minhthanhonly', 'Dinh Minh Thanh', 'Huynh Thi Anh Tuyet', '29/11/2024 10:55', '1', '', '29/11/2024 10:53', NULL, NULL, NULL, 1, '2024-11-29 10:55', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(401, 'hien', 12, '2024-12-02', '2024-12-02', '07:30', '11:30', 'False', 'Việc cá nhân', 'bận việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '29/11/2024 10:55', '1', '', '29/11/2024 10:55', NULL, NULL, NULL, 1, '2024-11-29 10:55', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(402, 'vi', 14, '2024-11-29', '2024-11-29', '13:00', '17:00', 'False', 'Việc đột xuất', ' bận việc đột xuất ', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '29/11/2024 11:29', '1', '', '29/11/2024 11:25', NULL, NULL, NULL, 1, '2024-11-29 11:29', 'hoai', '1', '0', NULL, NULL, '0'),
(403, 'hoai', 14, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Việc cá nhân', 'Có việc nên xin nghỉ phép', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '29/11/2024 17:04', '1', '', '29/11/2024 11:32', NULL, NULL, NULL, 1, '2024-11-29 17:04', 'dat', '1', '0', NULL, NULL, '0'),
(404, 'tuyen', 8, '2024-12-02', '2024-12-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Mất giấy tờ xe máy, về quê xin cấp lại giấy tờ', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '29/11/2024 16:17', '1', 'Đã xác nhận', '29/11/2024 16:05', NULL, NULL, NULL, 1, '2024-11-29 16:17', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(405, 'duyhoang', 17, '2024-12-06', '2024-12-06', '00:00', '00:00', 'True', 'Giảm stress', 'GIảm stress', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '02/12/2024 07:43', '1', '', '02/12/2024 07:42', NULL, NULL, NULL, 1, '2024-12-02 07:43', 'nguyen', '1', '0', NULL, NULL, '0'),
(406, 'luan', 18, '2024-12-19', '2024-12-19', '00:00', '00:00', 'True', 'Việc cá nhân', 'Có việc cần xử lý', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '03/12/2024 07:28', '1', '', '02/12/2024 07:48', NULL, NULL, NULL, 1, '2024-12-03 07:28', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(407, 'thoi', 17, '2024-12-09', '2024-12-09', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '02/12/2024 07:58', '1', '', '02/12/2024 07:51', NULL, NULL, NULL, 1, '2024-12-02 07:58', 'huyhoang', '1', '0', NULL, NULL, '0'),
(408, 'dinh', 8, '2024-11-13', '2024-11-13', '13:00', '17:00', 'False', 'Việc đột xuất', 'đưa con đi khám bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '02/12/2024 09:08', '1', 'Đã xác nhận', '02/12/2024 09:08', NULL, NULL, NULL, 1, '2024-12-02 09:08', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(409, 'cong', 11, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân ', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '02/12/2024 10:03', '1', 'OK', '02/12/2024 10:02', NULL, NULL, NULL, 1, '2024-12-02 10:03', 'thanh', '1', '0', NULL, NULL, '0'),
(410, 'duya', 18, '2024-12-02', '2024-12-02', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ bệnh.', 'luan', 'duya', 'Do Van Duya', 'Vo Minh Luan', '02/12/2024 13:20', '3', '', '02/12/2024 13:19', NULL, NULL, NULL, 1, '2024-12-02 13:20', 'duya', '0', '0', NULL, NULL, '0'),
(411, 'duya', 18, '2024-12-02', '2024-12-02', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ bệnh.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '02/12/2024 13:21', '1', '', '02/12/2024 13:20', NULL, NULL, NULL, 1, '2024-12-02 13:21', 'luan', '1', '0', NULL, NULL, '0'),
(412, 'thanh', 11, '2024-12-04', '2024-12-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '03/12/2024 07:19', '1', '', '02/12/2024 15:46', NULL, NULL, NULL, 1, '2024-12-03 07:19', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(413, 'huyhoang', 17, '2024-12-03', '2024-12-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '03/12/2024 15:22', '1', '', '02/12/2024 17:08', NULL, NULL, NULL, 1, '2024-12-03 15:22', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(414, 'vi', 14, '2024-12-09', '2024-12-09', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '03/12/2024 10:52', '1', '', '03/12/2024 07:03', NULL, NULL, NULL, 1, '2024-12-03 10:52', 'hoai', '1', '0', NULL, NULL, '0'),
(415, 'vi', 14, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '03/12/2024 10:52', '1', '', '03/12/2024 07:04', NULL, NULL, NULL, 1, '2024-12-03 10:52', 'hoai', '1', '0', NULL, NULL, '0'),
(416, 'trieu', 17, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Khám bệnh', 'Đi khám bệnh', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '04/12/2024 08:21', '1', '', '03/12/2024 07:21', NULL, NULL, NULL, 1, '2024-12-04 08:21', 'quyen', '1', '0', NULL, NULL, '0'),
(417, 'quocthinh_web', 7, '2024-12-03', '2024-12-03', '11:30', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '03/12/2024 09:13', '1', '', '03/12/2024 07:31', NULL, NULL, NULL, 1, '2024-12-03 09:13', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(418, 'ngocle', 17, '2024-12-13', '2024-12-13', '00:00', '00:00', 'True', 'Mất điện', 'Mất điện', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '03/12/2024 08:47', '1', '', '03/12/2024 08:43', NULL, NULL, NULL, 1, '2024-12-03 08:47', 'nguyen', '1', '0', NULL, NULL, '0'),
(419, 'long', 14, '2024-12-04', '2024-12-04', '07:30', '11:30', 'False', 'Khám bệnh', 'Mổ răng', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '03/12/2024 17:02', '1', '', '03/12/2024 16:59', NULL, NULL, NULL, 1, '2024-12-03 17:02', 'hoai', '1', '0', NULL, NULL, '0'),
(420, 'tam', 8, '2024-12-05', '2024-12-05', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc gia đình', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '12/12/2024 16:12', '1', 'Đã xác nhận', '04/12/2024 09:17', 'minhthanhonly', '12/12/2024 16:02', 'Dinh Minh Thanh', 4, '2024-12-12 16:12', 'tuyet2015', '1', '0', NULL, '0', '0'),
(421, 'ly', 14, '2024-12-20', '2024-12-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '04/12/2024 13:42', '1', '', '04/12/2024 13:40', NULL, NULL, NULL, 1, '2024-12-04 13:42', 'bi', '1', '0', NULL, NULL, '0'),
(422, 'van.tu', 13, '2024-12-04', '2024-12-04', '07:30', '11:30', 'False', 'Mất điện', 'MẤT ĐIỆN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/12/2024 14:19', '1', '', '04/12/2024 14:18', NULL, NULL, NULL, 1, '2024-12-04 14:19', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(423, 'ha', 10, '2024-12-04', '2024-12-04', '13:00', '17:00', 'False', 'Mất điện', 'mất điện 13h45-16h51, nên nghỉ phép năm 0.5 ngày', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '05/12/2024 07:34', '1', 'Leader ok!', '05/12/2024 07:25', NULL, NULL, NULL, 2, '2024-12-05 07:34', 'nhan', '1', '0', NULL, '0', '0'),
(424, 'ngan', 13, '2024-12-06', '2024-12-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ viêcj cá nhân', 'van.tu', 'ngan', 'Nguyen Thanh Ngan', 'Huynh Van Tu', '05/12/2024 07:38', '3', '', '05/12/2024 07:37', NULL, NULL, NULL, 1, '2024-12-05 07:38', 'ngan', '0', '0', NULL, NULL, '1'),
(425, 'ngan', 13, '2024-12-06', '2024-12-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '05/12/2024 07:51', '1', '', '05/12/2024 07:39', NULL, NULL, NULL, 1, '2024-12-05 07:51', 'van.tu', '1', '0', NULL, NULL, '0'),
(426, 'khang', 17, '2024-12-06', '2024-12-06', '00:00', '00:00', 'True', 'Giảm stress', 'Giảm stress công việc.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/12/2024 17:04', '1', '', '05/12/2024 08:16', NULL, NULL, NULL, 1, '2024-12-05 17:04', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(427, 'hoaibao', 13, '2024-12-06', '2024-12-06', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc gia đình đột xuất', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '05/12/2024 10:40', '1', '', '05/12/2024 10:37', NULL, NULL, NULL, 1, '2024-12-05 10:40', 'van.tu', '1', '0', NULL, NULL, '0'),
(428, 'minhthomonly', 17, '2024-12-06', '2024-12-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ phép', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '05/12/2024 13:07', '1', 'Duyệt tự động', '05/12/2024 13:07', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(429, 'tuyet2015', 8, '2024-12-09', '2024-12-09', '00:00', '00:00', 'True', 'Việc cá nhân', 'Đi làm giấy tờ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/12/2024 13:43', '1', '', '05/12/2024 13:42', NULL, NULL, NULL, 1, '2024-12-05 13:43', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(430, 'nhu', 17, '2024-12-06', '2024-12-06', '07:30', '11:30', 'False', 'Đi trễ', 'Bận việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/12/2024 13:39', '1', '', '06/12/2024 06:19', NULL, NULL, NULL, 1, '2024-12-11 13:39', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(431, 'minhthanhonly', 7, '2024-12-02', '2024-12-02', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '06/12/2024 07:46', '1', 'Duyệt tự động', '06/12/2024 07:47', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(432, 'khanh', 8, '2024-12-06', '2024-12-06', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân.', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '06/12/2024 11:27', '1', 'Đã xác nhận', '06/12/2024 11:25', NULL, NULL, NULL, 1, '2024-12-06 11:27', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(433, 'nhan', 10, '2024-12-09', '2024-12-09', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '06/12/2024 12:59', '1', '', '06/12/2024 12:57', NULL, NULL, NULL, 1, '2024-12-06 12:59', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(434, 'vinh', 17, '2024-12-06', '2024-12-06', '7 :30', '13:00', 'False', 'Khám bệnh', 'kham benh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '13/01/2025 14:39', '1', '', '06/12/2024 13:09', 'minhthanhonly', '31/12/2024 09:13', 'Dinh Minh Thanh', 7, '2025-01-13 14:39', 'khang', '1', '0', NULL, '0', '0'),
(435, 'hoai', 14, '2024-12-19', '2024-12-19', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '09/12/2024 12:16', '1', '', '09/12/2024 08:21', NULL, NULL, NULL, 1, '2024-12-09 12:16', 'dat', '1', '0', NULL, NULL, '0'),
(436, 'hoai', 14, '2024-12-27', '2024-12-27', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '09/12/2024 12:16', '1', '', '09/12/2024 08:22', NULL, NULL, NULL, 1, '2024-12-09 12:16', 'dat', '1', '0', NULL, NULL, '0'),
(437, 'cong', 11, '2024-12-13', '2024-12-13', '00:00', '00:00', 'True', 'Mất điện', 'mất điện ', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '09/12/2024 10:14', '1', 'OK', '09/12/2024 10:14', NULL, NULL, NULL, 1, '2024-12-09 10:14', 'thanh', '1', '0', NULL, NULL, '0'),
(438, 'dai', 14, '2024-12-10', '2024-12-10', '00:00', '00:00', 'True', 'Bệnh', 'Nghỉ bệnh', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '09/12/2024 17:00', '1', '', '09/12/2024 16:55', 'bi', '09/12/2024 16:59', 'Nguyen Van Bi', 6, '2024-12-09 17:00', 'bi', '1', '0', NULL, '0', '0'),
(439, 'thinh_web', 7, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'Có việc cá nhân', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '10/12/2024 15:20', '1', '', '10/12/2024 08:54', NULL, NULL, NULL, 1, '2024-12-10 15:20', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(440, 'van.tu', 13, '2024-12-10', '2024-12-10', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/12/2024 12:55', '1', '', '10/12/2024 12:12', NULL, NULL, NULL, 1, '2024-12-10 12:55', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(441, 'dat', 14, '2024-12-10', '2024-12-10', '07:30', '11:30', 'False', 'Bệnh', 'bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/12/2024 13:38', '1', '', '10/12/2024 12:34', NULL, NULL, NULL, 1, '2024-12-11 13:38', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(442, 'bi', 14, '2024-12-11', '2024-12-11', '07:30', '11:30', 'False', 'Việc cá nhân', 'Có việc riêng', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '11/12/2024 07:19', '1', '', '11/12/2024 06:50', NULL, NULL, NULL, 1, '2024-12-11 07:19', 'dat', '1', '0', NULL, NULL, '0'),
(443, 'luan', 18, '2024-12-16', '2024-12-16', '00:00', '00:00', 'True', 'Giảm stress', 'nghỉ phép', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '12/12/2024 15:37', '1', '', '11/12/2024 07:35', NULL, NULL, NULL, 1, '2024-12-12 15:37', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(444, 'luan', 18, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Giảm stress', 'nghỉ phép', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '12/12/2024 15:37', '1', '', '11/12/2024 07:36', NULL, NULL, NULL, 1, '2024-12-12 15:37', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(445, 'thuylinh', 18, '2024-12-10', '2024-12-10', '00:00', '00:00', 'True', 'Việc đột xuất', 'Việc cá nhân', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '11/12/2024 07:37', '1', '', '11/12/2024 07:37', NULL, NULL, NULL, 1, '2024-12-11 07:37', 'luan', '1', '0', NULL, NULL, '0'),
(446, 'tuyet2015', 8, '2024-12-11', '2024-12-11', '13:00', '17:00', 'False', 'Khám bệnh', 'Khám bệnh cho bé', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/12/2024 09:35', '1', '', '11/12/2024 09:35', NULL, NULL, NULL, 1, '2024-12-11 09:35', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(447, 'duya', 18, '2024-12-12', '2024-12-12', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '12/12/2024 11:31', '1', '', '12/12/2024 11:31', NULL, NULL, NULL, 1, '2024-12-12 11:31', 'luan', '1', '0', NULL, NULL, '0'),
(448, 'tam', 8, '2024-12-12', '2024-12-12', '07:30', '11:30', 'False', 'Khám bệnh', 'khám bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '12/12/2024 15:41', '1', 'Đã xác nhận', '12/12/2024 11:44', 'minhthanhonly', '12/12/2024 15:38', 'Dinh Minh Thanh', 4, '2024-12-12 15:41', 'tuyet2015', '1', '0', NULL, '0', '0'),
(449, 'long', 14, '2024-12-13', '2024-12-13', '00:00', '00:00', 'True', 'Khám bệnh', 'Xin nghỉ phép đi khám bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '12/12/2024 15:58', '1', '', '12/12/2024 15:57', NULL, NULL, NULL, 1, '2024-12-12 15:58', 'hoai', '1', '0', NULL, NULL, '0'),
(450, 'ngoc', 10, '2024-12-13', '2024-12-13', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc riêng', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '12/12/2024 16:58', '1', 'Leader ok!', '12/12/2024 16:57', NULL, NULL, NULL, 1, '2024-12-12 16:58', 'nhan', '1', '0', NULL, NULL, '0'),
(451, 'thienquan', 12, '2024-12-16', '2024-12-16', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép năm việc cá nhân', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '13/12/2024 11:32', '1', '', '13/12/2024 07:29', NULL, NULL, NULL, 1, '2024-12-13 11:32', 'hien', '1', '0', NULL, NULL, '0'),
(452, 'chautuan', 11, '2024-12-16', '2024-12-16', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '13/12/2024 15:02', '1', 'OK\r\n', '13/12/2024 15:01', NULL, NULL, NULL, 1, '2024-12-13 15:02', 'thanh', '1', '0', NULL, NULL, '0'),
(453, 'bich', 17, '2024-12-13', '2024-12-13', '11:25', '17:00', 'False', 'Mất điện', '0.5 ngày ( cúp điện )', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '13/12/2024 16:38', '1', 'Đồng ý cho nghỉ phép', '13/12/2024 16:38', NULL, NULL, NULL, 1, '2024-12-13 16:38', 'khang', '1', '0', NULL, NULL, '0'),
(454, 'van.tu', 13, '2024-12-17', '2024-12-17', ' 7:30', '11:30', 'False', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '02/01/2025 07:54', '1', '', '13/12/2024 17:04', 'minhthanhonly', '30/12/2024 16:07', 'Dinh Minh Thanh', 4, '2025-01-02 07:54', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(455, 'bich', 17, '2024-12-17', '2024-12-17', '07:30', '11:30', 'False', 'Việc cá nhân', 'đưa người thân đi khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '16/12/2024 07:24', '1', '', '16/12/2024 07:23', NULL, NULL, NULL, 1, '2024-12-16 07:24', 'khang', '1', '0', NULL, NULL, '0'),
(456, 'huy', 20, '2024-12-16', '2024-12-16', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc Riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '16/12/2024 11:28', '1', '', '16/12/2024 11:27', NULL, NULL, NULL, 1, '2024-12-16 11:28', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(457, 'vankhanh', 9, '2024-12-26', '2024-12-27', '00:00', '00:00', 'True', 'Khác', 'Việc riêng', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '16/12/2024 11:28', '1', 'đã duyệt\r\n', '16/12/2024 11:27', NULL, NULL, NULL, 1, '2024-12-16 11:28', 'lien', '1', '0', NULL, NULL, '0'),
(458, 'minhthang', 14, '2024-12-16', '2024-12-16', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '16/12/2024 12:56', '1', '', '16/12/2024 12:53', NULL, NULL, NULL, 1, '2024-12-16 12:56', 'hoai', '1', '0', NULL, NULL, '0'),
(459, 'cong', 11, '2024-12-20', '2024-12-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân ', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '16/12/2024 16:07', '1', 'OK\r\n', '16/12/2024 16:06', NULL, NULL, NULL, 1, '2024-12-16 16:07', 'thanh', '1', '0', NULL, NULL, '0'),
(460, 'dat', 14, '2024-12-18', '2024-12-18', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/12/2024 07:41', '1', '', '17/12/2024 07:19', NULL, NULL, NULL, 1, '2024-12-17 07:41', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(461, 'hoaibao', 13, '2024-12-18', '2024-12-18', '11:30', '17:00', 'False', 'Việc cá nhân', 'bận việc gia đình', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '17/12/2024 14:00', '1', '', '17/12/2024 13:59', NULL, NULL, NULL, 1, '2024-12-17 14:00', 'van.tu', '1', '0', NULL, NULL, '0'),
(462, 'lien', 9, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'về quê ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '02/01/2025 07:54', '1', '', '17/12/2024 14:25', 'minhthanhonly', '30/12/2024 16:08', 'Dinh Minh Thanh', 5, '2025-01-02 07:54', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(463, 'ducanh', 9, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '17/12/2024 14:30', '1', 'đã duyệt\r\n', '17/12/2024 14:29', NULL, NULL, NULL, 1, '2024-12-17 14:30', 'lien', '1', '0', NULL, NULL, '0'),
(464, 'dat', 14, '2024-12-18', '2024-12-18', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/12/2024 15:57', '1', '', '17/12/2024 15:49', NULL, NULL, NULL, 1, '2024-12-17 15:57', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(465, 'dinh', 8, '2024-12-09', '2024-12-09', '00:00', '00:00', 'True', 'Chăm bệnh', 'chăm mẹ nhập viện', 'tuyet2015', 'dinh', 'Tran Huu Dinh', 'Huynh Thi Anh Tuyet', '18/12/2024 12:04', '3', '', '17/12/2024 18:22', NULL, NULL, NULL, 1, '2024-12-18 12:04', 'dinh', '0', '0', NULL, NULL, '0'),
(466, 'quyen', 17, '2024-12-20', '2024-12-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'Có việc gia đình', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/12/2024 11:08', '1', '', '18/12/2024 07:37', NULL, NULL, NULL, 1, '2024-12-18 11:08', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(467, 'duyhoang', 17, '2024-12-19', '2024-12-19', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '18/12/2024 07:57', '1', '', '18/12/2024 07:56', NULL, NULL, NULL, 1, '2024-12-18 07:57', 'nguyen', '1', '0', NULL, NULL, '0'),
(468, 'hien', 12, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'có việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/12/2024 09:13', '1', '', '18/12/2024 09:13', NULL, NULL, NULL, 1, '2024-12-18 09:13', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(469, 'tam', 8, '2024-12-18', '2024-12-18', '07:30', '11:30', 'False', 'Việc cá nhân', 'Đưa con đi khám bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '18/12/2024 11:38', '1', 'Đã xác nhận', '18/12/2024 11:37', NULL, NULL, NULL, 1, '2024-12-18 11:38', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(470, 'dinh', 8, '2024-12-09', '2024-12-09', '00:00', '00:00', 'True', 'Chăm bệnh', 'mẹ nhập viện', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '18/12/2024 16:43', '1', 'Đã xác nhận', '18/12/2024 12:18', NULL, NULL, NULL, 1, '2024-12-18 16:43', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(471, 'duya', 18, '2024-12-18', '2024-12-18', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '18/12/2024 13:48', '1', '', '18/12/2024 13:48', NULL, NULL, NULL, 1, '2024-12-18 13:48', 'luan', '1', '0', NULL, NULL, '0'),
(472, 'ha', 10, '2024-12-18', '2024-12-18', '13:00', '13:40', 'False', 'Mất điện', 'K tăng ca, làm bù giờ mất điện (17h-17h40)', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '18/12/2024 16:04', '1', 'Leader ok!\r\n', '18/12/2024 16:03', NULL, NULL, NULL, 1, '2024-12-18 16:04', 'nhan', '0', '0', NULL, NULL, '0'),
(473, 'tuyen', 8, '2024-12-19', '2024-12-19', '00:00', '00:00', 'True', 'Bệnh', 'Nghỉ ốm', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '18/12/2024 16:42', '1', 'Đã xác nhận', '18/12/2024 16:38', NULL, NULL, NULL, 1, '2024-12-18 16:42', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(474, 'van', 12, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'Em có việc cá nhân', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '19/12/2024 08:22', '1', '', '19/12/2024 07:21', NULL, NULL, NULL, 1, '2024-12-19 08:22', 'hien', '1', '0', NULL, NULL, '0'),
(475, 'nhu', 17, '2024-12-19', '2024-12-19', '13:00', '17:00', 'False', 'Về sớm', 'Bận việc ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/12/2024 11:20', '1', '', '19/12/2024 07:34', NULL, NULL, NULL, 1, '2024-12-19 11:20', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(476, 'truong', 15, '2025-01-07', '2025-01-07', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '19/12/2024 11:16', '1', '', '19/12/2024 11:11', NULL, NULL, NULL, 1, '2024-12-19 11:16', 'bien', '1', '0', NULL, NULL, '0'),
(477, 'truong', 15, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân.', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '19/12/2024 11:16', '1', '', '19/12/2024 11:12', NULL, NULL, NULL, 1, '2024-12-19 11:16', 'bien', '1', '0', NULL, NULL, '0'),
(478, 'quoc', 15, '2025-01-08', '2025-01-09', '00:00', '00:00', 'True', 'Lỗi bất khả kháng', 'VIỆC RIÊNG CÁ NHÂN', 'bien', 'quoc', 'Tran The Quoc', 'Huynh Trong Bien', '19/12/2024 11:19', '3', '', '19/12/2024 11:12', NULL, NULL, NULL, 1, '2024-12-19 11:19', 'quoc', '1', '0', NULL, NULL, '0'),
(479, 'bien', 15, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/12/2024 11:20', '1', '', '19/12/2024 11:12', NULL, NULL, NULL, 1, '2024-12-19 11:20', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(480, 'quoc', 15, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC RIÊNG CÁ NHÂN', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '19/12/2024 11:16', '1', '', '19/12/2024 11:13', NULL, NULL, NULL, 1, '2024-12-19 11:16', 'bien', '1', '0', NULL, NULL, '0'),
(481, 'quoc', 15, '2025-01-08', '2025-01-09', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '19/12/2024 11:18', '1', '', '19/12/2024 11:15', NULL, NULL, NULL, 1, '2024-12-19 11:18', 'bien', '1', '0', NULL, NULL, '0'),
(482, 'huy', 20, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC RIÊNG', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/12/2024 07:31', '1', '', '19/12/2024 17:11', NULL, NULL, NULL, 1, '2024-12-20 07:31', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(483, 'huy', 20, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC RIÊNG', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/12/2024 07:32', '1', '', '19/12/2024 17:12', NULL, NULL, NULL, 1, '2024-12-20 07:32', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(484, 'nhu', 17, '2024-12-20', '2024-12-20', '13:00', '17:00', 'False', 'Về sớm', 'Bận việc gia đình', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/12/2024 17:10', '1', '', '20/12/2024 07:40', NULL, NULL, NULL, 1, '2024-12-20 17:10', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(485, 'khanh', 8, '2024-12-20', '2024-12-20', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân.', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '20/12/2024 08:46', '1', 'Đã xác nhận', '20/12/2024 08:43', NULL, NULL, NULL, 1, '2024-12-20 08:46', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(486, 'huyhoang', 17, '2024-12-23', '2024-12-23', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/12/2024 17:10', '1', '', '20/12/2024 17:06', NULL, NULL, NULL, 1, '2024-12-20 17:10', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(487, 'ly', 14, '2024-12-23', '2024-12-23', '07:30', '11:30', 'False', 'Việc đột xuất', 'Có việc gấp', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '23/12/2024 06:46', '1', '', '23/12/2024 06:46', NULL, NULL, NULL, 1, '2024-12-23 06:46', 'bi', '1', '0', NULL, NULL, '0'),
(488, 'dat', 14, '2024-12-23', '2024-12-23', '13:00', '17:00', 'False', 'Khám bệnh', 'đi khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/12/2024 07:22', '1', '', '23/12/2024 07:19', NULL, NULL, NULL, 1, '2024-12-23 07:22', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(489, 'thanh', 11, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ phép', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '23/12/2024 08:26', '1', 'có trừ PN hay ko ?', '23/12/2024 08:25', 'tranvinh.loc', '23/12/2024 10:25', 'Tran Vinh Loc', 3, '2024-12-23 08:26', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(490, 'tranvinh.loc', 1, '2024-12-23', '2024-12-23', '13:00', '17:00', 'False', 'Việc cá nhân', 'Ra ngoài có việc', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/12/2024 08:45', '1', '', '23/12/2024 08:44', NULL, NULL, NULL, 1, '2024-12-23 08:45', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(491, 'vanphuc', 7, '2024-12-24', '2024-12-24', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân.', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '25/12/2024 08:45', '1', '', '23/12/2024 10:55', NULL, NULL, NULL, 2, '2024-12-25 08:45', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(492, 'ngocle', 17, '2024-12-26', '2024-12-26', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '23/12/2024 11:01', '1', '', '23/12/2024 10:59', NULL, NULL, NULL, 1, '2024-12-23 11:01', 'nguyen', '1', '0', NULL, NULL, '0'),
(493, 'ha', 10, '2024-12-25', '2024-12-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ phép năm 1 ngày', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '23/12/2024 11:31', '1', 'Leader ok!', '23/12/2024 11:31', NULL, NULL, NULL, 1, '2024-12-23 11:31', 'nhan', '1', '0', NULL, NULL, '0');
INSERT INTO `groupware_dayoff` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`, `minus_leave`, `is_repeat`, `cancel_repeat_dates`, `is_overwork`, `is_BHXH`) VALUES
(494, 'ngan', 13, '2024-12-24', '2024-12-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '23/12/2024 16:01', '1', '', '23/12/2024 15:43', NULL, NULL, NULL, 1, '2024-12-23 16:01', 'van.tu', '1', '0', NULL, NULL, '0'),
(495, 'thienquan', 12, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép năm việc cá nhân', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '24/12/2024 07:47', '1', '', '24/12/2024 07:23', NULL, NULL, NULL, 1, '2024-12-24 07:47', 'hien', '1', '0', NULL, NULL, '0'),
(496, 'tu_web', 7, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Khác', 'Nghi Phep', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '25/12/2024 08:45', '1', '', '24/12/2024 08:01', NULL, NULL, NULL, 1, '2024-12-25 08:45', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(497, 'tu_web', 7, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Ve tham gia dinh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '25/12/2024 08:45', '1', '', '24/12/2024 08:02', NULL, NULL, NULL, 1, '2024-12-25 08:45', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(498, 'hanhthach', 20, '2024-12-25', '2024-12-25', '07:30', '11:30', 'False', 'Việc đột xuất', 'viec dot xuat', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '24/12/2024 09:02', '1', 'đồng ý', '24/12/2024 08:59', NULL, NULL, NULL, 1, '2024-12-24 09:02', 'huy', '1', '0', NULL, NULL, '0'),
(499, 'hanhthach', 20, '2024-12-24', '2024-12-24', '00:00', '00:00', 'True', 'Việc đột xuất', 'viec dot xuat', 'huy', 'hanhthach', 'Thach Thi Tuyet Hanh', 'Tran Thanh Huy', '24/12/2024 09:00', '3', '', '24/12/2024 09:00', NULL, NULL, NULL, 1, '2024-12-24 09:00', 'hanhthach', '0', '0', NULL, NULL, '0'),
(500, 'tham', 20, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '24/12/2024 09:02', '1', 'đồng ý', '24/12/2024 09:01', NULL, NULL, NULL, 1, '2024-12-24 09:02', 'huy', '1', '0', NULL, NULL, '0'),
(501, 'huyhoang', 17, '2024-12-24', '2024-12-24', '13:00', '17:00', 'False', 'Việc cá nhân', 'bận việc cá nhân (về sớm lúc 15h30 nhưng chọn trừ nửa ngày phép năm)', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '24/12/2024 15:36', '1', '', '24/12/2024 13:13', NULL, NULL, NULL, 2, '2024-12-24 15:36', 'minhthomonly', '1', '0', NULL, '0', '0'),
(502, 'long', 14, '2024-12-24', '2024-12-24', '13:00', '17:00', 'False', 'Việc đột xuất', 'Có việc đột xuất nên xin nghỉ buổi chiều ạ', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '24/12/2024 14:01', '1', '', '24/12/2024 13:58', NULL, NULL, NULL, 1, '2024-12-24 14:01', 'hoai', '1', '0', NULL, NULL, '0'),
(503, 'chautuan', 11, '2024-12-25', '2024-12-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '24/12/2024 15:14', '1', 'OK\r\n', '24/12/2024 15:13', NULL, NULL, NULL, 1, '2024-12-24 15:14', 'thanh', '1', '0', NULL, NULL, '0'),
(504, 'chautuan', 11, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '30/12/2024 21:48', '1', 'OK', '24/12/2024 15:13', 'minhthanhonly', '30/12/2024 16:08', 'Dinh Minh Thanh', 4, '2024-12-30 21:48', 'thanh', '1', '0', NULL, '0', '0'),
(505, 'thoi', 17, '2024-12-27', '2024-12-27', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '25/12/2024 17:02', '1', '', '25/12/2024 07:18', NULL, NULL, NULL, 1, '2024-12-25 17:02', 'huyhoang', '1', '0', NULL, NULL, '0'),
(506, 'thoi', 17, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '25/12/2024 17:02', '1', '', '25/12/2024 07:19', NULL, NULL, NULL, 1, '2024-12-25 17:02', 'huyhoang', '1', '0', NULL, NULL, '0'),
(507, 'vutrang', 14, '2024-12-27', '2024-12-27', '00:00', '00:00', 'True', 'Khám bệnh', 'bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '25/12/2024 16:55', '1', '', '25/12/2024 10:27', NULL, NULL, NULL, 1, '2024-12-25 16:55', 'bi', '1', '0', NULL, NULL, '0'),
(508, 'nhan', 10, '2024-12-26', '2024-12-26', '00:00', '00:00', 'True', 'Việc cá nhân', 'Lý do cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '25/12/2024 15:44', '1', '', '25/12/2024 15:43', NULL, NULL, NULL, 1, '2024-12-25 15:44', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(509, 'duykhanh', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép, có việc riêng', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '27/12/2024 09:56', '1', '', '26/12/2024 08:29', NULL, NULL, NULL, 1, '2024-12-27 09:56', 'quyen', '1', '0', NULL, NULL, '0'),
(510, 'khang', 17, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Giảm stress', 'Về quê', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/12/2024 17:04', '1', '', '26/12/2024 11:28', NULL, NULL, NULL, 1, '2024-12-26 17:04', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(511, 'khang', 17, '2025-01-02', '2025-01-03', '00:00', '00:00', 'True', 'Giảm stress', 'Về quê', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/12/2024 17:04', '1', '', '26/12/2024 11:29', NULL, NULL, NULL, 1, '2024-12-26 17:04', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(512, 'hien', 12, '2024-12-27', '2024-12-27', '00:00', '00:00', 'True', 'Việc đột xuất', 'bận việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '26/12/2024 14:30', '1', '', '26/12/2024 14:30', NULL, NULL, NULL, 1, '2024-12-26 14:30', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(513, 'vinh', 17, '2024-12-27', '2024-12-27', '00:00', '00:00', 'True', 'Việc cá nhân', 'có việc cá nhân', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '26/12/2024 15:58', '1', '', '26/12/2024 15:58', NULL, NULL, NULL, 1, '2024-12-26 15:58', 'khang', '1', '0', NULL, NULL, '0'),
(514, 'quyen', 17, '2024-12-19', '2024-12-19', '15:00', '17:00', 'False', 'Mất điện', 'Cúp điện \r\nGhi chú: trừ tiền.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/12/2024 17:04', '1', '', '26/12/2024 16:12', NULL, NULL, NULL, 1, '2024-12-26 17:04', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(515, 'ngan', 13, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '30/12/2024 05:32', '1', '', '27/12/2024 07:26', NULL, NULL, NULL, 1, '2024-12-30 05:32', 'van.tu', '1', '0', NULL, NULL, '0'),
(516, 'duya', 18, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Giảm stress', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '27/12/2024 07:38', '1', '', '27/12/2024 07:37', NULL, NULL, NULL, 1, '2024-12-27 07:38', 'luan', '1', '0', NULL, NULL, '0'),
(517, 'duya', 18, '2024-12-27', '2024-12-27', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép năm', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '27/12/2024 07:40', '1', '', '27/12/2024 07:40', NULL, NULL, NULL, 1, '2024-12-27 07:40', 'luan', '1', '0', NULL, NULL, '0'),
(518, 'tranvinh.loc', 1, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Khác', 'Bên Nhật nghỉ lễ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/12/2024 09:49', '1', '', '27/12/2024 09:48', NULL, NULL, NULL, 1, '2024-12-27 09:49', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(519, 'ha', 10, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ phép năm 1 ngày', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '27/12/2024 11:31', '1', 'Leader ok!', '27/12/2024 09:55', NULL, NULL, NULL, 1, '2024-12-27 11:31', 'nhan', '1', '0', NULL, NULL, '0'),
(520, 'trieu', 17, '2024-12-30', '2024-12-30', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '27/12/2024 09:56', '1', '', '27/12/2024 09:55', NULL, NULL, NULL, 1, '2024-12-27 09:56', 'quyen', '1', '0', NULL, NULL, '0'),
(521, 'ngocle', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '27/12/2024 17:08', '1', '', '27/12/2024 09:59', NULL, NULL, NULL, 2, '2024-12-27 17:08', 'nguyen', '1', '0', NULL, '0', '0'),
(522, 'ngoc', 10, '2024-12-30', '2024-12-30', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc riêng', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '27/12/2024 11:31', '1', 'Leader ok!', '27/12/2024 10:00', NULL, NULL, NULL, 1, '2024-12-27 11:31', 'nhan', '1', '0', NULL, NULL, '0'),
(523, 'duyhoang', 17, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '27/12/2024 17:08', '1', '', '27/12/2024 10:03', NULL, NULL, NULL, 1, '2024-12-27 17:08', 'nguyen', '1', '0', NULL, NULL, '0'),
(524, 'minhthomonly', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ xả stress', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '27/12/2024 10:07', '1', 'Duyệt tự động', '27/12/2024 10:07', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(525, 'vi', 14, '2024-12-30', '2024-12-30', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '30/12/2024 06:44', '1', '', '27/12/2024 11:13', NULL, NULL, NULL, 1, '2024-12-30 06:44', 'hoai', '1', '0', NULL, NULL, '0'),
(526, 'vutrang', 14, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Khác', 'xin nghỉ phép', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/12/2024 14:02', '1', '', '27/12/2024 11:44', NULL, NULL, NULL, 1, '2024-12-27 14:02', 'bi', '1', '0', NULL, NULL, '0'),
(527, 'thuylinh', 18, '2024-12-27', '2024-12-27', '13:00', '17:00', 'False', 'Về sớm', 'Việc đột xuất', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '27/12/2024 12:07', '1', '', '27/12/2024 12:07', NULL, NULL, NULL, 1, '2024-12-27 12:07', 'luan', '0', '0', NULL, NULL, '0'),
(528, 'dat', 14, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Giảm stress', 'giảm stress', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/12/2024 13:07', '1', '', '27/12/2024 12:46', NULL, NULL, NULL, 1, '2024-12-27 13:07', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(529, 'thiet', 14, '2024-12-30', '2024-12-30', '11:30', '17:00', 'False', 'Việc cá nhân', 'CÓ VIỆC RIÊNG ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/12/2024 14:02', '1', '', '27/12/2024 12:58', NULL, NULL, NULL, 1, '2024-12-27 14:02', 'bi', '1', '0', NULL, NULL, '0'),
(530, 'thiet', 14, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'CÓ VIỆC RIÊNG ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/12/2024 14:02', '1', '', '27/12/2024 12:59', NULL, NULL, NULL, 1, '2024-12-27 14:02', 'bi', '1', '0', NULL, NULL, '0'),
(531, 'dai', 14, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Khác', 'Nghỉ phép năm', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/12/2024 14:02', '1', '', '27/12/2024 13:06', NULL, NULL, NULL, 1, '2024-12-27 14:02', 'bi', '1', '0', NULL, NULL, '0'),
(532, 'khanh', 8, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân.', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '27/12/2024 13:09', '1', 'Đã xác nhận', '27/12/2024 13:08', NULL, NULL, NULL, 1, '2024-12-27 13:09', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(533, 'tuyet2015', 8, '2024-12-30', '2024-12-30', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê.', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/12/2024 13:13', '1', '', '27/12/2024 13:12', NULL, NULL, NULL, 1, '2024-12-27 13:13', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(534, 'tuyen', 8, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc gia đình', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '27/12/2024 13:13', '1', 'Đã xác nhận', '27/12/2024 13:13', NULL, NULL, NULL, 1, '2024-12-27 13:13', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(535, 'tuyet2015', 8, '2024-12-31', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê.', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/12/2024 13:13', '1', '', '27/12/2024 13:13', NULL, NULL, NULL, 1, '2024-12-27 13:13', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(536, 'nguyen', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/12/2024 13:48', '1', '', '27/12/2024 13:32', NULL, NULL, NULL, 1, '2024-12-27 13:48', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(537, 'bi', 14, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Khác', 'Nghỉ cuối năm', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '27/12/2024 15:58', '1', '', '27/12/2024 14:03', NULL, NULL, NULL, 1, '2024-12-27 15:58', 'dat', '1', '0', NULL, NULL, '0'),
(538, 'thoi', 17, '2024-12-27', '2024-12-27', '11:30', '17:00', 'False', 'Việc đột xuất', 'việc đột xuất', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '27/12/2024 14:16', '1', '', '27/12/2024 14:16', NULL, NULL, NULL, 1, '2024-12-27 14:16', 'huyhoang', '1', '0', NULL, NULL, '0'),
(539, 'ly', 14, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Khác', 'Việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '27/12/2024 16:16', '1', '', '27/12/2024 15:41', NULL, NULL, NULL, 1, '2024-12-27 16:16', 'bi', '1', '0', NULL, NULL, '0'),
(540, 'nhu', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Giảm stress', 'Nghỉ Phép ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '02/01/2025 07:40', '1', '', '27/12/2024 16:09', NULL, NULL, NULL, 1, '2025-01-02 07:40', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(541, 'vantoan', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Giảm stress', 'Xả stress lấy năng lượng làm việc cho năm mới. ', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '27/12/2024 16:21', '1', '', '27/12/2024 16:15', NULL, NULL, NULL, 2, '2024-12-27 16:21', 'nhu', '1', '0', NULL, '0', '0'),
(542, 'hoaibao', 13, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc gia đình', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '30/12/2024 14:29', '1', '', '30/12/2024 07:07', NULL, NULL, NULL, 1, '2024-12-30 14:29', 'van.tu', '1', '0', NULL, NULL, '0'),
(543, 'thoi', 17, '2024-12-30', '2024-12-30', '11:30', '17:00', 'False', 'Bệnh', 'bệnh ', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '30/12/2024 08:39', '1', '', '30/12/2024 08:39', NULL, NULL, NULL, 1, '2024-12-30 08:39', 'khang', '1', '0', NULL, NULL, '0'),
(544, 'hanhthach', 20, '2024-12-31', '2024-12-31', '13:00', '17:00', 'False', 'Việc đột xuất', 'viec dot xuat', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '30/12/2024 10:53', '1', 'đồng ý\r\n', '30/12/2024 10:52', NULL, NULL, NULL, 1, '2024-12-30 10:53', 'huy', '1', '0', NULL, NULL, '0'),
(545, 'vinh', 17, '2024-12-31', '2024-12-31', '13:00', '17:00', 'False', 'Việc đột xuất', 'nghỉ phép có việc riêng', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '30/12/2024 11:30', '1', '', '30/12/2024 11:28', NULL, NULL, NULL, 2, '2024-12-30 11:30', 'khang', '1', '0', NULL, '0', '0'),
(546, 'minhthang', 14, '2024-12-31', '2024-12-31', '13:00', '17:00', 'False', 'Khác', 'nghỉ phép', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '30/12/2024 15:07', '1', '', '30/12/2024 14:59', NULL, NULL, NULL, 1, '2024-12-30 15:07', 'hoai', '1', '0', NULL, NULL, '0'),
(547, 'hoai', 14, '2024-12-31', '2024-12-31', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '31/12/2024 08:30', '1', '', '30/12/2024 15:25', NULL, NULL, NULL, 1, '2024-12-31 08:30', 'dat', '1', '0', NULL, NULL, '0'),
(548, 'diemai', 7, '2024-12-23', '2024-12-28', '00:00', '00:00', 'True', 'Chăm bệnh', 'Chăm con nhập viện', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/12/2024 16:21', '1', '', '30/12/2024 16:20', NULL, NULL, NULL, 1, '2024-12-30 16:21', 'minhthanhonly', '0', '0', NULL, NULL, '1'),
(549, 'diemai', 7, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/12/2024 16:21', '1', '', '30/12/2024 16:20', NULL, NULL, NULL, 1, '2024-12-30 16:21', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(550, 'minhthanhonly', 7, '2024-12-23', '2024-12-23', '13:00', '17:00', 'False', 'Chăm bệnh', 'chăm con', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '30/12/2024 16:21', '1', 'Duyệt tự động', '30/12/2024 16:22', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(551, 'chautuan', 11, '2024-12-23', '2024-12-23', '00:00', '00:00', 'True', 'Lỗi bất khả kháng', 'không đăng nhập được vào app, bắt đầu từ 7h30 kết thúc 17h00', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '30/12/2024 21:48', '1', 'OK', '30/12/2024 19:01', NULL, NULL, NULL, 1, '2024-12-30 21:48', 'thanh', '0', '0', NULL, NULL, '0'),
(552, 'long', 14, '2024-12-31', '2024-12-31', '13:00', '17:00', 'False', 'Việc cá nhân', 'Xin nghỉ phép vì có việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '31/12/2024 07:36', '1', '', '31/12/2024 07:35', NULL, NULL, NULL, 1, '2024-12-31 07:36', 'hoai', '1', '0', NULL, NULL, '0'),
(553, 'van', 12, '2025-01-03', '2025-01-03', '00:00', '00:00', 'True', 'Bệnh', 'Em bị cảm cúm', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '02/01/2025 08:25', '1', '', '02/01/2025 07:20', NULL, NULL, NULL, 1, '2025-01-02 08:25', 'hien', '1', '0', NULL, NULL, '0'),
(554, 'huyhoang', 17, '2024-12-30', '2024-12-31', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '02/01/2025 07:40', '1', '', '02/01/2025 07:23', NULL, NULL, NULL, 2, '2025-01-02 07:40', 'minhthomonly', '1', '0', NULL, '0', '0'),
(555, 'quyen', 17, '2024-12-31', '2024-12-31', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '02/01/2025 07:40', '1', '', '02/01/2025 07:40', NULL, NULL, NULL, 1, '2025-01-02 07:40', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(556, 'ngan', 13, '2025-01-02', '2025-01-02', '09:30', '10:30', 'False', 'Mất điện', 'Làm bù giờ 17:00-18:00', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '02/01/2025 12:55', '1', '', '02/01/2025 10:37', NULL, NULL, NULL, 1, '2025-01-02 12:55', 'van.tu', '0', '0', NULL, NULL, '0'),
(557, 'chautuan', 11, '2025-01-10', '2025-01-10', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '02/01/2025 16:45', '1', 'OK', '02/01/2025 16:44', NULL, NULL, NULL, 1, '2025-01-02 16:45', 'thanh', '1', '0', NULL, NULL, '0'),
(558, 'hoaibao', 13, '2025-01-08', '2025-01-08', '11:30', '17:00', 'False', 'Việc cá nhân', 'Bận việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '03/01/2025 13:14', '1', '', '03/01/2025 11:07', NULL, NULL, NULL, 1, '2025-01-03 13:14', 'van.tu', '1', '0', NULL, NULL, '0'),
(559, 'thoi', 17, '2025-01-06', '2025-01-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '03/01/2025 15:14', '1', '', '03/01/2025 15:14', NULL, NULL, NULL, 1, '2025-01-03 15:14', 'huyhoang', '1', '0', NULL, NULL, '0'),
(560, 'thuylinh', 18, '2025-01-10', '2025-01-10', '13:00', '17:00', 'False', 'Về sớm', 'bận việc cá nhân', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '06/01/2025 07:48', '1', '', '06/01/2025 07:47', NULL, NULL, NULL, 1, '2025-01-06 07:48', 'luan', '1', '0', NULL, NULL, '0'),
(561, 'luan', 18, '2025-02-03', '2025-02-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '06/01/2025 08:05', '1', '', '06/01/2025 07:48', NULL, NULL, NULL, 1, '2025-01-06 08:05', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(562, 'thuylinh', 18, '2025-02-13', '2025-02-17', '00:00', '00:00', 'True', 'Du lịch', 'Đi du lịch', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '21/01/2025 07:43', '1', '', '06/01/2025 07:53', NULL, NULL, NULL, 1, '2025-01-21 07:43', 'luan', '1', '0', NULL, NULL, '0'),
(563, 'thinh_web', 7, '2025-01-13', '2025-01-13', '07:30', '11:30', 'False', 'Khám bệnh', 'Đưa người thân khám bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '06/01/2025 10:55', '1', '', '06/01/2025 08:49', NULL, NULL, NULL, 1, '2025-01-06 10:55', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(564, 'tam', 8, '2025-01-06', '2025-01-06', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '06/01/2025 11:31', '1', 'Đã xác nhận', '06/01/2025 11:30', NULL, NULL, NULL, 1, '2025-01-06 11:31', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(565, 'thiet', 14, '2025-01-06', '2025-01-06', '15:00', '17:00', 'False', 'Việc đột xuất', 'việc cá nhân ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '06/01/2025 15:18', '1', '', '06/01/2025 15:16', NULL, NULL, NULL, 1, '2025-01-06 15:18', 'bi', '0', '0', NULL, NULL, '0'),
(566, 'nhan', 10, '2025-01-07', '2025-01-07', '00:00', '00:00', 'True', 'Việc cá nhân', 'Lý do cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '06/01/2025 15:50', '1', '', '06/01/2025 15:49', NULL, NULL, NULL, 1, '2025-01-06 15:50', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(567, 'dinh', 8, '2025-01-06', '2025-01-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'Chăm bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '06/01/2025 15:58', '1', 'Đã xác nhận', '06/01/2025 15:58', NULL, NULL, NULL, 1, '2025-01-06 15:58', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(568, 'vi', 14, '2025-01-17', '2025-01-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '07/01/2025 08:11', '1', '', '07/01/2025 07:47', NULL, NULL, NULL, 1, '2025-01-07 08:11', 'hoai', '1', '0', NULL, NULL, '0'),
(569, 'ngocle', 17, '2025-01-13', '2025-01-13', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '07/01/2025 08:04', '1', '', '07/01/2025 07:59', NULL, NULL, NULL, 1, '2025-01-07 08:04', 'nguyen', '1', '0', NULL, NULL, '0'),
(570, 'bi', 14, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08/01/2025 12:53', '1', '', '08/01/2025 07:02', NULL, NULL, NULL, 1, '2025-01-08 12:53', 'dat', '1', '0', NULL, NULL, '0'),
(571, 'hoai', 14, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08/01/2025 12:53', '1', '', '08/01/2025 07:08', NULL, NULL, NULL, 1, '2025-01-08 12:53', 'dat', '1', '0', NULL, NULL, '0'),
(572, 'nguyen', 17, '2025-01-08', '2025-01-08', '13:00', '17:00', 'False', 'Khám bệnh', 'Khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '09/01/2025 09:24', '1', '', '08/01/2025 07:36', NULL, NULL, NULL, 1, '2025-01-09 09:24', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(573, 'lien', 9, '2025-01-08', '2025-01-08', '16:00', '17:00', 'False', 'Chăm bệnh', 'con bệnh', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '08/01/2025 15:43', '1', '', '08/01/2025 15:42', NULL, NULL, NULL, 1, '2025-01-08 15:43', 'tranvinh.loc', '0', '0', NULL, NULL, '0'),
(574, 'bi', 14, '2025-01-09', '2025-01-09', '07:30', '11:30', 'False', 'Việc đột xuất', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08/01/2025 17:10', '1', '', '08/01/2025 16:53', NULL, NULL, NULL, 1, '2025-01-08 17:10', 'dat', '1', '0', NULL, NULL, '0'),
(575, 'tuyet2015', 8, '2025-01-09', '2025-01-09', '13:00', '17:00', 'False', 'Khám bệnh', 'Đi khám bệnh', 'tranvinh.loc', 'minhthanhonly', 'Dinh Minh Thanh', 'Tran Vinh Loc', '14/01/2025 08:14', '1', '', '09/01/2025 09:06', 'minhthanhonly', '14/01/2025 08:11', 'Dinh Minh Thanh', 4, '2025-01-14 08:14', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(576, 'lien', 9, '2025-01-09', '2025-01-09', '07:30', '11:30', 'False', 'Việc đột xuất', 'làm thẻ ngân hàng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '09/01/2025 13:07', '1', '', '09/01/2025 13:06', NULL, NULL, NULL, 1, '2025-01-09 13:07', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(577, 'vankhanh', 9, '2025-01-10', '2025-01-10', '07:30', '11:30', 'False', 'Khác', 'Lên cty', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '09/01/2025 15:10', '1', 'đã duyệt', '09/01/2025 15:09', NULL, NULL, NULL, 1, '2025-01-09 15:10', 'lien', '1', '0', NULL, NULL, '0'),
(578, 'tam', 8, '2025-01-10', '2025-01-10', '13:00', '17:00', 'False', 'Việc cá nhân', 'đưa con đi khám bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '15/01/2025 07:48', '1', 'Đã xác nhận', '10/01/2025 11:53', NULL, NULL, NULL, 1, '2025-01-15 07:48', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(579, 'vantoan', 17, '2025-01-10', '2025-01-10', '07:30', '11:30', 'False', 'Việc đột xuất', 'Khám bệnh cho con!', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '10/01/2025 13:05', '1', 'Đă xác nhận', '10/01/2025 13:04', NULL, NULL, NULL, 1, '2025-01-10 13:05', 'nhu', '1', '0', NULL, NULL, '0'),
(580, 'minhthang', 14, '2025-01-17', '2025-01-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '13/01/2025 08:02', '1', '', '13/01/2025 08:02', NULL, NULL, NULL, 1, '2025-01-13 08:02', 'hoai', '1', '0', NULL, NULL, '0'),
(581, 'dat', 14, '2025-01-13', '2025-01-13', '13:00', '17:00', 'False', 'Việc đột xuất', 'việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/01/2025 08:09', '1', '', '13/01/2025 08:07', NULL, NULL, NULL, 1, '2025-01-13 08:09', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(582, 'long', 14, '2025-01-13', '2025-01-13', '07:30', '11:30', 'False', 'Việc đột xuất', 'Việc đột xuất nên xin nghỉ phép nữa buổi sáng', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '13/01/2025 11:33', '1', '', '13/01/2025 11:27', NULL, NULL, NULL, 1, '2025-01-13 11:33', 'hoai', '1', '0', NULL, NULL, '0'),
(583, 'bich', 17, '2025-01-13', '2025-01-13', '07:30', '11:30', 'False', 'Chăm bệnh', 'cho bé đi khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '13/01/2025 14:38', '1', '', '13/01/2025 12:49', NULL, NULL, NULL, 1, '2025-01-13 14:38', 'khang', '1', '0', NULL, NULL, '0'),
(584, 'tuyet2015', 8, '2025-01-13', '2025-01-13', '07:30', '11:30', 'False', 'Khám bệnh', 'Tái khám ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/01/2025 13:02', '1', '', '13/01/2025 12:55', NULL, NULL, NULL, 1, '2025-01-13 13:02', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(585, 'cong', 11, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '14/01/2025 11:28', '1', 'OK', '14/01/2025 10:51', NULL, NULL, NULL, 1, '2025-01-14 11:28', 'thanh', '1', '0', NULL, NULL, '0'),
(586, 'van.tu', 13, '2025-01-20', '2025-01-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '15/01/2025 07:37', '1', '', '14/01/2025 17:03', NULL, NULL, NULL, 1, '2025-01-15 07:37', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(587, 'ha', 10, '2025-01-15', '2025-01-15', '00:00', '00:00', 'True', 'Bệnh', 'nghỉ phép năm 1 ngày', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '15/01/2025 07:27', '1', 'Leader ok!', '15/01/2025 05:07', NULL, NULL, NULL, 1, '2025-01-15 07:27', 'nhan', '1', '0', NULL, NULL, '0'),
(588, 'vutrang', 14, '2025-01-16', '2025-01-17', '13:00', '00:00', 'False', 'Việc cá nhân', 'bận việc GĐ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '16/01/2025 06:55', '1', '', '15/01/2025 07:08', NULL, NULL, NULL, 1, '2025-01-16 06:55', 'bi', '1', '0', NULL, NULL, '0'),
(589, 'tam', 8, '2025-01-15', '2025-01-15', '07:30', '11:30', 'False', 'Bệnh', 'bệnh', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '15/01/2025 07:49', '1', 'Đã xác nhận', '15/01/2025 07:48', NULL, NULL, NULL, 1, '2025-01-15 07:49', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(590, 'vankhanh', 9, '2025-01-16', '2025-01-16', '00:00', '00:00', 'True', 'Khác', 'về quê.', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '15/01/2025 08:29', '1', 'đã duyệt\r\n', '15/01/2025 08:28', NULL, NULL, NULL, 1, '2025-01-15 08:29', 'lien', '1', '0', NULL, NULL, '0'),
(591, 'nhu', 17, '2025-01-15', '2025-01-15', '16:00', '17:00', 'False', 'Về sớm', 'bận việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '16/01/2025 07:36', '1', '', '15/01/2025 16:05', NULL, NULL, NULL, 1, '2025-01-16 07:36', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(592, 'ngan', 13, '2025-01-17', '2025-01-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '16/01/2025 16:06', '1', '', '16/01/2025 16:06', NULL, NULL, NULL, 1, '2025-01-16 16:06', 'van.tu', '1', '0', NULL, NULL, '0'),
(593, 'ngoc', 10, '2025-01-20', '2025-01-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'có việc riêng', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '17/01/2025 08:48', '1', 'Leader ok!', '17/01/2025 08:23', NULL, NULL, NULL, 1, '2025-01-17 08:48', 'nhan', '1', '0', NULL, NULL, '0'),
(594, 'khanh', 8, '2025-01-17', '2025-01-17', '11:30', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân.', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '17/01/2025 12:57', '1', 'Đã xác nhận', '17/01/2025 10:31', NULL, NULL, NULL, 1, '2025-01-17 12:57', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(595, 'hien', 12, '2025-01-20', '2025-01-20', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/01/2025 14:59', '1', '', '17/01/2025 14:59', NULL, NULL, NULL, 1, '2025-01-17 14:59', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(596, 'vutrang', 14, '2025-01-20', '2025-01-20', '07:30', '11:30', 'False', 'Mất điện', 'mất điện', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '20/01/2025 07:40', '1', '', '19/01/2025 21:21', NULL, NULL, NULL, 1, '2025-01-20 07:40', 'bi', '1', '0', NULL, NULL, '0'),
(597, 'quyen', 17, '2025-01-22', '2025-01-22', '07:30', '11:30', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/01/2025 15:48', '1', '', '20/01/2025 09:07', NULL, NULL, NULL, 1, '2025-01-20 15:48', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(598, 'huyhoang', 17, '2025-01-21', '2025-01-21', '00:00', '00:00', 'True', 'Giỗ', 'Việc gia đình.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/01/2025 17:06', '1', '', '20/01/2025 16:03', NULL, NULL, NULL, 1, '2025-01-21 17:06', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(599, 'ngan', 13, '2025-01-20', '2025-01-20', '09:15', '10:00', 'False', 'Mất điện', 'Làm bù giờ 17:00-17:45', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '21/01/2025 06:52', '1', '', '20/01/2025 17:47', NULL, NULL, NULL, 1, '2025-01-21 06:52', 'van.tu', '0', '0', NULL, NULL, '0'),
(600, 'duya', 18, '2025-01-21', '2025-01-21', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '21/01/2025 07:41', '1', '', '21/01/2025 07:40', NULL, NULL, NULL, 1, '2025-01-21 07:41', 'luan', '1', '0', NULL, NULL, '0'),
(601, 'thienquan', 12, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép năm việc cá nhân', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '21/01/2025 09:48', '1', '', '21/01/2025 09:01', NULL, NULL, NULL, 1, '2025-01-21 09:48', 'hien', '1', '0', NULL, NULL, '0'),
(602, 'hanhthach', 20, '2025-01-22', '2025-01-22', '07:30', '11:30', 'False', 'Việc đột xuất', 'có viec ban', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '21/01/2025 14:48', '1', 'đồng ý', '21/01/2025 14:45', NULL, NULL, NULL, 1, '2025-01-21 14:48', 'huy', '1', '0', NULL, NULL, '0'),
(603, 'hanhthach', 20, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc đột xuất', 'nghi tet', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '21/01/2025 14:47', '1', 'đồng ý', '21/01/2025 14:46', NULL, NULL, NULL, 2, '2025-01-21 14:47', 'huy', '1', '0', NULL, '0', '0'),
(604, 'ngan', 13, '2025-01-22', '2025-01-22', '07:30', '11:30', 'False', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '22/01/2025 07:06', '1', '', '21/01/2025 18:18', NULL, NULL, NULL, 1, '2025-01-22 07:06', 'van.tu', '1', '0', NULL, NULL, '0'),
(605, 'dai', 14, '2025-01-22', '2025-01-22', '07:30', '11:30', 'False', 'Việc cá nhân', 'bận việc riêng', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '22/01/2025 07:02', '1', '', '21/01/2025 19:30', NULL, NULL, NULL, 1, '2025-01-22 07:02', 'bi', '1', '0', NULL, NULL, '0'),
(606, 'van.tu', 13, '2025-01-21', '2025-01-21', '16:30', '17:00', 'False', 'Mất điện', 'MẤT ĐIỆN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 07:25', '1', '', '22/01/2025 07:06', NULL, NULL, NULL, 1, '2025-01-22 07:25', 'tranvinh.loc', '0', '0', NULL, NULL, '0'),
(607, 'duykhanh', 17, '2025-01-20', '2025-01-21', '00:00', '00:00', 'True', 'Tang người thân', 'Tang người thân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '23/01/2025 16:38', '1', '', '22/01/2025 07:18', NULL, NULL, NULL, 1, '2025-01-23 16:38', 'quyen', '1', '0', NULL, NULL, '0'),
(608, 'huy', 20, '2025-01-23', '2025-01-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc riêng', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 13:05', '1', 'chưa chọn PN', '22/01/2025 10:59', 'tranvinh.loc', '22/01/2025 13:00', 'Tran Vinh Loc', 3, '2025-01-22 13:05', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(609, 'bich', 17, '2025-01-23', '2025-01-23', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '22/01/2025 15:29', '1', '', '22/01/2025 15:28', NULL, NULL, NULL, 1, '2025-01-22 15:29', 'khang', '1', '0', NULL, NULL, '0'),
(610, 'van.tu', 13, '2025-01-23', '2025-01-23', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆT CÁ NHÂN ĐỘT XUẤT', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '23/01/2025 07:32', '1', '', '23/01/2025 07:06', NULL, NULL, NULL, 1, '2025-01-23 07:32', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(611, 'bich', 17, '2025-02-03', '2025-02-03', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '23/01/2025 07:23', '1', '', '23/01/2025 07:23', NULL, NULL, NULL, 1, '2025-01-23 07:23', 'khang', '1', '0', NULL, NULL, '0'),
(612, 'nhu', 17, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ về quê', 'minhthomonly', 'nhu', 'Nguyen Thi Nhu', 'Dinh Minh Thom', '23/01/2025 07:43', '3', '', '23/01/2025 07:43', NULL, NULL, NULL, 1, '2025-01-23 07:43', 'nhu', '0', '0', NULL, NULL, '0'),
(613, 'nhu', 17, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về quê', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/01/2025 15:26', '1', '', '23/01/2025 07:44', NULL, NULL, NULL, 1, '2025-01-23 15:26', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(614, 'ngan', 13, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '24/01/2025 06:18', '1', '', '23/01/2025 09:01', NULL, NULL, NULL, 1, '2025-01-24 06:18', 'van.tu', '1', '0', NULL, NULL, '0'),
(615, 'minhthang', 14, '2025-02-11', '2025-02-11', '00:00', '00:00', 'True', 'Khám bệnh', 'khám bệnh', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '23/01/2025 13:10', '1', '', '23/01/2025 12:55', NULL, NULL, NULL, 1, '2025-01-23 13:10', 'hoai', '1', '0', NULL, NULL, '0'),
(616, 'vi', 14, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc đột xuất', 'việc đột xuất', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '23/01/2025 13:09', '1', '', '23/01/2025 13:03', NULL, NULL, NULL, 1, '2025-01-23 13:09', 'hoai', '1', '0', NULL, NULL, '0'),
(617, 'tam', 8, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '23/01/2025 13:59', '1', 'Đã xác nhận', '23/01/2025 13:58', NULL, NULL, NULL, 1, '2025-01-23 13:59', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(618, 'ngoc', 10, '2025-01-23', '2025-01-23', '10:45', '14:15', 'False', 'Mất điện', 'ko tăng ca, làm bù mật điện (17h-19h)', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '23/01/2025 14:27', '1', 'Leader ok!', '23/01/2025 14:27', NULL, NULL, NULL, 1, '2025-01-23 14:27', 'nhan', '0', '0', NULL, NULL, '0'),
(619, 'thoi', 17, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc đột xuất', 'việc đột xuất ', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '23/01/2025 14:46', '1', '', '23/01/2025 14:43', NULL, NULL, NULL, 1, '2025-01-23 14:46', 'huyhoang', '1', '0', NULL, NULL, '0'),
(620, 'khanh', 8, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ Tết.', 'tuyet2015', 'minhthanhonly', 'Dinh Minh Thanh', 'Huynh Thi Anh Tuyet', '24/01/2025 07:47', '1', 'Sửa ngày', '23/01/2025 15:49', 'minhthanhonly', '24/01/2025 07:45', 'Dinh Minh Thanh', 4, '2025-01-24 07:47', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(621, 'vantoan', 17, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc bận gia đình ', 'nhu', 'vantoan', 'Doan Van Toan', 'Nguyen Thi Nhu', '23/01/2025 17:03', '3', '', '23/01/2025 16:55', NULL, NULL, NULL, 2, '2025-01-23 17:03', 'vantoan', '1', '0', NULL, '0', '0'),
(622, 'vantoan', 17, '2025-02-04', '2025-02-05', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc bận gia đình', 'nhu', 'vantoan', 'Doan Van Toan', 'Nguyen Thi Nhu', '23/01/2025 17:03', '3', '', '23/01/2025 16:56', NULL, NULL, NULL, 2, '2025-01-23 17:03', 'vantoan', '0', '0', NULL, '0', '0'),
(623, 'vantoan', 17, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc bận gia đình', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '06/02/2025 07:12', '1', 'Đã Xác nhận', '24/01/2025 08:35', NULL, NULL, NULL, 1, '2025-02-06 07:12', 'nhu', '1', '0', NULL, NULL, '0'),
(624, 'vantoan', 17, '2025-02-04', '2025-02-05', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc bận gia đình', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '06/02/2025 07:12', '1', 'Đã Xác nhận', '24/01/2025 08:36', NULL, NULL, NULL, 1, '2025-02-06 07:12', 'nhu', '0', '0', NULL, NULL, '0'),
(625, 'vankhanh', 9, '2025-01-20', '2025-01-20', '00:00', '00:00', 'True', 'Mất điện', 'mất điện', 'lien', 'minhthanhonly', 'Dinh Minh Thanh', 'Vu Thi Lien', '24/01/2025 08:58', '1', '', '24/01/2025 08:58', NULL, NULL, NULL, 1, '2025-01-24 08:58', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(626, 'tuyet2015', 8, '2025-01-24', '2025-01-24', '13:00', '17:00', 'False', 'Việc cá nhân', 'Về quê nghỉ tết.', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '24/01/2025 10:30', '1', '', '24/01/2025 10:30', NULL, NULL, NULL, 1, '2025-01-24 10:30', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(627, 'tuyen', 8, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ việc cá nhân', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '24/01/2025 10:40', '1', 'Đã xác nhận', '24/01/2025 10:33', NULL, NULL, NULL, 1, '2025-01-24 10:40', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(628, 'tuyet2015', 8, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ thêm sau tết.', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '24/01/2025 10:52', '1', '', '24/01/2025 10:42', NULL, NULL, NULL, 1, '2025-01-24 10:52', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(629, 'lien', 9, '2025-01-24', '2025-01-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'về quê ăn tết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '03/02/2025 07:18', '1', '', '03/02/2025 06:40', NULL, NULL, NULL, 1, '2025-02-03 07:18', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(630, 'ngocle', 17, '2025-02-04', '2025-02-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '03/02/2025 08:33', '1', '', '03/02/2025 08:20', NULL, NULL, NULL, 1, '2025-02-03 08:33', 'nguyen', '1', '0', NULL, NULL, '0'),
(631, 'dat', 14, '2025-02-03', '2025-02-03', '07:30', '11:30', 'False', 'Bệnh', 'bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '03/02/2025 08:54', '1', '', '03/02/2025 08:48', NULL, NULL, NULL, 1, '2025-02-03 08:54', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(632, 'duykhanh', 17, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '07/02/2025 13:06', '1', '', '04/02/2025 07:41', NULL, NULL, NULL, 1, '2025-02-07 13:06', 'quyen', '1', '0', NULL, NULL, '0'),
(633, 'huyhoang', 17, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '04/02/2025 07:54', '1', '', '04/02/2025 07:53', NULL, NULL, NULL, 1, '2025-02-04 07:54', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(634, 'nguyen', 17, '2025-02-05', '2025-02-05', '13:00', '17:00', 'False', 'Khám bệnh', 'Khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/02/2025 15:38', '1', '', '05/02/2025 07:21', NULL, NULL, NULL, 1, '2025-02-05 15:38', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(635, 'dinh', 8, '2025-02-04', '2025-02-04', '00:00', '00:00', 'True', 'Chăm bệnh', 'chăm mẹ nằm viện', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '05/02/2025 07:45', '1', 'Đã xác nhận', '05/02/2025 07:44', NULL, NULL, NULL, 1, '2025-02-05 07:45', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(636, 'trieu', 17, '2025-02-03', '2025-02-04', '00:00', '00:00', 'True', 'Khác', 'về quê ăn Tết', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '07/02/2025 13:06', '1', '', '05/02/2025 12:47', NULL, NULL, NULL, 1, '2025-02-07 13:06', 'quyen', '1', '0', NULL, NULL, '0'),
(637, 'trieu', 17, '2025-02-05', '2025-02-05', '07:30', '11:30', 'False', 'Khác', 'sự cố việc cá nhân', 'quyen', 'quyen', 'Nguyen Thi Thu Quyen', 'Nguyen Thi Thu Quyen', '07/02/2025 13:06', '1', '', '05/02/2025 12:49', NULL, NULL, NULL, 1, '2025-02-07 13:06', 'quyen', '0', '0', NULL, NULL, '0'),
(638, 'nhan', 10, '2025-02-06', '2025-02-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'Lý do cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 14:32', '1', '', '05/02/2025 14:31', NULL, NULL, NULL, 1, '2025-02-05 14:32', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(639, 'hanhthach', 20, '2025-02-04', '2025-02-04', '13:00', '17:00', 'False', 'Việc đột xuất', 'viec dot xuat', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '05/02/2025 17:15', '1', 'đồng ý', '05/02/2025 16:28', NULL, NULL, NULL, 1, '2025-02-05 17:15', 'huy', '1', '0', NULL, NULL, '0'),
(640, 'van.tu', 13, '2025-02-06', '2025-02-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 16:56', '1', '', '05/02/2025 16:53', NULL, NULL, NULL, 1, '2025-02-05 16:56', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(641, 'thinh_web', 7, '2025-02-12', '2025-02-12', '07:30', '11:30', 'False', 'Khám bệnh', 'Đưa mẹ đi khám bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '24/02/2025 07:30', '1', '', '06/02/2025 08:23', NULL, NULL, NULL, 1, '2025-02-24 07:30', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(642, 'huyhoang', 17, '2025-02-06', '2025-02-06', '07:30', '11:30', 'False', 'Khám bệnh', 'khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/02/2025 13:12', '1', '', '06/02/2025 09:52', NULL, NULL, NULL, 1, '2025-02-10 13:12', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(643, 'ngocle', 17, '2025-02-06', '2025-02-06', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '06/02/2025 10:58', '1', '', '06/02/2025 10:27', NULL, NULL, NULL, 1, '2025-02-06 10:58', 'nguyen', '1', '0', NULL, NULL, '0'),
(644, 'ly', 14, '2025-02-06', '2025-02-06', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc đột xuất', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '06/02/2025 12:54', '1', '', '06/02/2025 12:44', NULL, NULL, NULL, 1, '2025-02-06 12:54', 'bi', '1', '0', NULL, NULL, '0'),
(645, 'vi', 14, '2025-02-21', '2025-02-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '17/02/2025 13:37', '1', '', '07/02/2025 07:29', 'hoai', '17/02/2025 13:35', 'Nguyen Thi Hoai', 5, '2025-02-17 13:37', 'hoai', '1', '0', NULL, '0', '0'),
(646, 'long', 14, '2025-02-07', '2025-02-07', '13:00', '17:00', 'False', 'Việc đột xuất', 'Xin nghỉ phép có việc đột xuất.', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '07/02/2025 09:09', '1', '', '07/02/2025 09:09', NULL, NULL, NULL, 1, '2025-02-07 09:09', 'hoai', '1', '0', NULL, NULL, '0'),
(647, 'hanhthach', 20, '2025-02-10', '2025-02-10', '07:30', '11:30', 'False', 'Việc cá nhân', 'dam gio', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '10/02/2025 07:22', '1', 'đồng ý', '07/02/2025 16:21', NULL, NULL, NULL, 1, '2025-02-10 07:22', 'huy', '1', '0', NULL, NULL, '0'),
(648, 'quyen', 17, '2025-02-12', '2025-02-12', '07:30', '11:30', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/02/2025 13:12', '1', '', '10/02/2025 10:04', NULL, NULL, NULL, 1, '2025-02-10 13:12', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(649, 'quoc', 15, '2025-02-28', '2025-02-28', '07:30', '11:30', 'False', 'Việc cá nhân', 'viec ca nhan', 'bien', 'quoc', 'Tran The Quoc', 'Huynh Trong Bien', '10/02/2025 10:58', '3', '', '10/02/2025 10:54', NULL, NULL, NULL, 1, '2025-02-10 10:58', 'quoc', '1', '0', NULL, NULL, '0'),
(650, 'quoc', 15, '2025-03-04', '2025-03-07', '00:00', '00:00', 'True', 'Cưới hỏi', 'cưới hỏi', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '10/02/2025 10:59', '1', '', '10/02/2025 10:55', NULL, NULL, NULL, 1, '2025-02-10 10:59', 'bien', '1', '0', NULL, NULL, '0'),
(651, 'quoc', 15, '2025-02-28', '2025-02-28', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'bien', 'bien', 'Huynh Trong Bien', 'Huynh Trong Bien', '10/02/2025 10:59', '1', '', '10/02/2025 10:57', NULL, NULL, NULL, 1, '2025-02-10 10:59', 'bien', '1', '0', NULL, NULL, '0'),
(652, 'van.tu', 13, '2025-02-11', '2025-02-11', '11:00', '11:30', 'False', 'Việc cá nhân', 'VIỆC CÁ NHÂN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/02/2025 11:12', '1', '', '11/02/2025 11:11', NULL, NULL, NULL, 1, '2025-02-11 11:12', 'tranvinh.loc', '0', '0', NULL, NULL, '0'),
(653, 'van', 12, '2025-02-12', '2025-02-12', '13:00', '17:00', 'False', 'Khám bệnh', 'Em bị cảm nặng', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '12/02/2025 08:04', '1', '', '12/02/2025 07:11', NULL, NULL, NULL, 1, '2025-02-12 08:04', 'hien', '1', '0', NULL, NULL, '0'),
(654, 'huyhoang', 17, '2025-02-12', '2025-02-12', '00:00', '00:00', 'True', 'Bệnh', 'Đau mắt', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '12/02/2025 13:38', '1', '', '12/02/2025 07:29', NULL, NULL, NULL, 1, '2025-02-12 13:38', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(655, 'duya', 18, '2025-02-13', '2025-02-13', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép  năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '13/02/2025 11:30', '1', '', '13/02/2025 07:11', NULL, NULL, NULL, 1, '2025-02-13 11:30', 'luan', '1', '0', NULL, NULL, '0'),
(656, 'nhan', 10, '2025-02-17', '2025-02-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'Lý do cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/02/2025 10:07', '1', '', '13/02/2025 07:21', NULL, NULL, NULL, 1, '2025-02-13 10:07', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(657, 'ducanh', 9, '2025-02-14', '2025-02-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc riêng', 'lien', 'lien', 'Vu Thi Lien', 'Vu Thi Lien', '13/02/2025 10:26', '1', 'đã duyệt', '13/02/2025 10:25', NULL, NULL, NULL, 1, '2025-02-13 10:26', 'lien', '1', '0', NULL, NULL, '0'),
(658, 'nhu', 17, '2025-02-13', '2025-02-13', '13:00', '17:00', 'False', 'Việc cá nhân', 'Bận việc ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/02/2025 16:26', '1', '', '13/02/2025 12:59', NULL, NULL, NULL, 1, '2025-02-13 16:26', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(659, 'thiet', 14, '2025-02-13', '2025-02-13', '07:30', '11:30', 'False', 'Khác', 'cúp điện ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '13/02/2025 13:12', '1', '', '13/02/2025 13:10', NULL, NULL, NULL, 1, '2025-02-13 13:12', 'bi', '1', '0', NULL, NULL, '0'),
(660, 'dinh', 8, '2025-02-14', '2025-02-14', '00:00', '00:00', 'True', 'Chăm bệnh', 'dẫn mẹ đi tái khám', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '13/02/2025 15:37', '1', 'Đã xác nhận', '13/02/2025 15:36', NULL, NULL, NULL, 1, '2025-02-13 15:37', 'tuyet2015', '1', '0', NULL, NULL, '0');
INSERT INTO `groupware_dayoff` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`, `minus_leave`, `is_repeat`, `cancel_repeat_dates`, `is_overwork`, `is_BHXH`) VALUES
(661, 'vanphuc', 7, '2025-02-14', '2025-02-14', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc đột xuất, chứng giấy tờ xe.', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '24/02/2025 07:30', '1', '', '13/02/2025 17:07', NULL, NULL, NULL, 1, '2025-02-24 07:30', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(662, 'hien', 12, '2025-02-18', '2025-02-18', '00:00', '00:00', 'True', 'Việc cá nhân', 'Khám bệnh', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '14/02/2025 14:39', '1', '', '14/02/2025 14:39', NULL, NULL, NULL, 1, '2025-02-14 14:39', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(663, 'vutrang', 14, '2025-02-21', '2025-02-21', '13:00', '17:00', 'False', 'Việc cá nhân', 'bận việc cá nhân', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '17/02/2025 07:19', '1', '', '17/02/2025 07:04', NULL, NULL, NULL, 3, '2025-02-17 07:19', 'bi', '1', '0', NULL, '0', '0'),
(664, 'cong', 11, '2025-02-21', '2025-02-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân ', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '17/02/2025 08:02', '1', 'OK', '17/02/2025 07:49', NULL, NULL, NULL, 1, '2025-02-17 08:02', 'thanh', '1', '0', NULL, NULL, '0'),
(665, 'dat', 14, '2025-02-18', '2025-02-18', '07:30', '11:30', 'False', 'Việc đột xuất', 'việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/02/2025 15:02', '1', '', '17/02/2025 14:59', NULL, NULL, NULL, 1, '2025-02-17 15:02', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(666, 'huyhoang', 17, '2025-02-17', '2025-02-17', '07:30', '11:30', 'False', 'Khám bệnh', 'Khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/02/2025 17:55', '1', '', '17/02/2025 17:05', NULL, NULL, NULL, 1, '2025-02-17 17:55', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(667, 'tuyet2015', 8, '2025-02-18', '2025-02-18', ' 7:30', '11:30', 'False', 'Khác', 'Sửa đường dây mạng.', 'tranvinh.loc', 'minhthanhonly', 'Dinh Minh Thanh', 'Tran Vinh Loc', '24/02/2025 13:05', '1', '', '18/02/2025 10:32', 'minhthanhonly', '24/02/2025 12:14', 'Dinh Minh Thanh', 4, '2025-02-24 13:05', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(668, 'thoi', 17, '2025-02-21', '2025-02-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '18/02/2025 14:09', '1', '', '18/02/2025 14:08', NULL, NULL, NULL, 1, '2025-02-18 14:09', 'huyhoang', '0', '0', NULL, NULL, '0'),
(669, 'thanh', 11, '2025-02-24', '2025-02-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'nghỉ phép 1 ngày ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/02/2025 16:53', '1', '', '18/02/2025 16:52', NULL, NULL, NULL, 1, '2025-02-18 16:53', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(670, 'duyhoang', 17, '2025-02-21', '2025-02-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '19/02/2025 07:23', '1', '', '19/02/2025 07:21', NULL, NULL, NULL, 1, '2025-02-19 07:23', 'nguyen', '1', '0', NULL, NULL, '0'),
(671, 'ngocle', 17, '2025-02-19', '2025-02-19', '13:00', '17:00', 'False', 'Việc đột xuất', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '19/02/2025 10:46', '1', '', '19/02/2025 10:45', NULL, NULL, NULL, 1, '2025-02-19 10:46', 'nguyen', '1', '0', NULL, NULL, '0'),
(672, 'dat', 14, '2025-02-18', '2025-02-18', '13:00', '17:00', 'False', 'Việc đột xuất', 'việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/02/2025 14:47', '1', '', '19/02/2025 11:22', NULL, NULL, NULL, 1, '2025-02-19 14:47', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(673, 'vinh', 17, '2025-02-19', '2025-02-19', '07:30', '11:30', 'False', 'Bệnh', 'bị bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '19/02/2025 13:00', '1', '', '19/02/2025 13:00', NULL, NULL, NULL, 1, '2025-02-19 13:00', 'khang', '1', '0', NULL, NULL, '0'),
(674, 'tranvinh.loc', 1, '2025-02-28', '2025-03-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Về Quê', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/02/2025 15:42', '1', '', '19/02/2025 15:40', NULL, NULL, NULL, 1, '2025-02-19 15:42', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(675, 'lien', 9, '2025-02-24', '2025-02-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'về quê', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '19/02/2025 15:54', '1', '', '19/02/2025 15:54', NULL, NULL, NULL, 1, '2025-02-19 15:54', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(676, 'tham', 20, '2025-02-21', '2025-02-21', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'huy', 'huy', 'Tran Thanh Huy', 'Tran Thanh Huy', '20/02/2025 10:43', '1', 'đồng ý', '20/02/2025 10:38', NULL, NULL, NULL, 1, '2025-02-20 10:43', 'huy', '1', '0', NULL, NULL, '0'),
(677, 'van', 12, '2025-02-21', '2025-02-21', '00:00', '00:00', 'True', 'Việc đột xuất', 'em có việc gia đình đột xuất', 'hien', 'hien', 'Nguyen Thu Hien', 'Nguyen Thu Hien', '20/02/2025 16:24', '1', '', '20/02/2025 13:09', NULL, NULL, NULL, 1, '2025-02-20 16:24', 'hien', '1', '0', NULL, NULL, '0'),
(678, 'thinh_web', 7, '2025-03-14', '2025-03-14', '00:00', '00:00', 'True', 'Việc cá nhân', 'Đưa người thân khám bệnh', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '24/02/2025 07:30', '1', '', '21/02/2025 14:17', NULL, NULL, NULL, 1, '2025-02-24 07:30', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(679, 'tuyen', 8, '2025-02-24', '2025-02-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc gia đình', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '21/02/2025 15:43', '1', 'Đã xác nhận ', '21/02/2025 15:38', NULL, NULL, NULL, 1, '2025-02-21 15:43', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(680, 'tuyen', 8, '2025-02-25', '2025-02-25', '07:30', '11:30', 'False', 'Việc cá nhân', 'Bận việc gia đình', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '21/02/2025 15:44', '1', 'Đã xác nhận', '21/02/2025 15:39', NULL, NULL, NULL, 1, '2025-02-21 15:44', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(681, 'bi', 14, '2025-02-25', '2025-02-25', '07:30', '11:30', 'False', 'Việc đột xuất', 'Việc đột xuất', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '24/02/2025 08:39', '1', '', '24/02/2025 08:34', NULL, NULL, NULL, 1, '2025-02-24 08:39', 'dat', '1', '0', NULL, NULL, '0'),
(682, 'bich', 17, '2025-02-25', '2025-02-25', '07:30', '11:30', 'False', 'Chăm bệnh', 'đưa con đi khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '24/02/2025 09:20', '1', '', '24/02/2025 09:20', NULL, NULL, NULL, 1, '2025-02-24 09:20', 'khang', '1', '0', NULL, NULL, '0'),
(683, 'duya', 18, '2025-02-24', '2025-02-24', '13:00', '17:00', 'False', 'Về sớm', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '24/02/2025 09:40', '1', '', '24/02/2025 09:39', NULL, NULL, NULL, 1, '2025-02-24 09:40', 'luan', '1', '0', NULL, NULL, '0'),
(684, 'dai', 14, '2025-02-25', '2025-02-25', '00:00', '00:00', 'True', 'Việc cá nhân', 'Bận việc riêng', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '24/02/2025 15:32', '1', '', '24/02/2025 15:26', NULL, NULL, NULL, 1, '2025-02-24 15:32', 'bi', '1', '0', NULL, NULL, '0'),
(685, 'ngan', 13, '2025-02-24', '2025-02-24', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'tranvinh.loc', 'ngan', 'Nguyen Thanh Ngan', 'Tran Vinh Loc', '24/02/2025 16:53', '3', '', '24/02/2025 16:53', NULL, NULL, NULL, 1, '2025-02-24 16:53', 'ngan', '1', '0', NULL, NULL, '0'),
(686, 'ngan', 13, '2025-02-24', '2025-02-24', '00:00', '00:00', 'True', 'Việc đột xuất', 'Việc đột xuất', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '24/02/2025 17:03', '1', '', '24/02/2025 16:53', NULL, NULL, NULL, 1, '2025-02-24 17:03', 'van.tu', '1', '0', NULL, NULL, '0'),
(687, 'chautuan', 11, '2025-03-03', '2025-03-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'thanh', 'thanh', 'Doan Huu Thanh', 'Doan Huu Thanh', '25/02/2025 07:34', '1', 'OK', '25/02/2025 07:33', NULL, NULL, NULL, 1, '2025-02-25 07:34', 'thanh', '1', '0', NULL, NULL, '0'),
(688, 'nguyen', 17, '2025-02-26', '2025-02-26', '13:00', '17:00', 'False', 'Khám bệnh', 'Khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/02/2025 07:39', '1', '', '26/02/2025 07:24', NULL, NULL, NULL, 1, '2025-02-26 07:39', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(689, 'dat', 14, '2025-02-26', '2025-02-26', '13:00', '17:00', 'False', 'Việc đột xuất', 'việc đột xuất', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/02/2025 11:34', '1', '', '26/02/2025 11:27', NULL, NULL, NULL, 1, '2025-02-26 11:34', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(690, 'ngoc', 10, '2025-02-27', '2025-02-27', '13:00', '17:00', 'False', 'Bệnh', 'bị cảm sốt', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '27/02/2025 08:25', '1', 'Leader ok!', '27/02/2025 08:24', NULL, NULL, NULL, 1, '2025-02-27 08:25', 'nhan', '1', '0', NULL, NULL, '0'),
(691, 'minhthanhonly', 7, '2025-03-10', '2025-03-10', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthanhonly', NULL, NULL, 'Dinh Minh Thanh', '27/02/2025 09:46', '1', 'Duyệt tự động', '27/02/2025 09:46', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(692, 'khanh', 8, '2025-03-03', '2025-03-03', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '27/02/2025 13:37', '1', 'Đã xác nhận', '27/02/2025 13:35', NULL, NULL, NULL, 1, '2025-02-27 13:37', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(693, 'khanh', 8, '2025-03-28', '2025-03-28', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '27/02/2025 13:37', '1', 'Đã xác nhận.', '27/02/2025 13:36', NULL, NULL, NULL, 1, '2025-02-27 13:37', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(694, 'huyhoang', 17, '2025-02-14', '2025-02-14', '00:00', '00:00', 'True', 'Bệnh', 'bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '28/02/2025 07:51', '1', '', '28/02/2025 07:46', NULL, NULL, NULL, 1, '2025-02-28 07:51', 'minhthomonly', '0', '0', NULL, NULL, '1'),
(695, 'vutrang', 14, '2025-02-28', '2025-02-28', '07:30', '11:30', 'False', 'Mất điện', 'sáng cúp điện, nghỉ phép năm 7h30-11h30', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '28/02/2025 12:49', '1', '', '28/02/2025 12:49', NULL, NULL, NULL, 1, '2025-02-28 12:49', 'bi', '1', '0', NULL, NULL, '0'),
(696, 'vankhanh', 9, '2025-02-03', '2025-02-03', '00:00', '00:00', 'True', 'Khác', 'Bận việc riêng.', 'lien', 'minhthanhonly', 'Dinh Minh Thanh', 'Vu Thi Lien', '03/03/2025 08:05', '1', '', '03/03/2025 08:02', 'minhthanhonly', '03/03/2025 08:02', 'Dinh Minh Thanh', 3, '2025-03-03 08:05', 'minhthanhonly', '1', '0', NULL, '0', '0'),
(697, 'vankhanh', 9, '2025-03-03', '2025-03-03', '00:00', '00:00', 'True', 'Khác', 'Bận việc riêng', 'lien', 'vankhanh', 'Ha Van Khanh', 'Vu Thi Lien', '03/03/2025 08:04', '3', '', '03/03/2025 08:04', NULL, NULL, NULL, 1, '2025-03-03 08:04', 'vankhanh', '1', '0', NULL, NULL, '0'),
(698, 'nhan', 10, '2025-03-04', '2025-03-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân!', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/03/2025 07:18', '1', '', '03/03/2025 12:58', NULL, NULL, NULL, 1, '2025-03-04 07:18', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(699, 'bich', 17, '2025-03-07', '2025-03-07', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc cá nhân', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '03/03/2025 13:08', '1', '', '03/03/2025 13:06', NULL, NULL, NULL, 1, '2025-03-03 13:08', 'khang', '1', '0', NULL, NULL, '0'),
(700, 'bich', 17, '2025-03-11', '2025-03-11', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc cá nhân', 'khang', 'minhthanhonly', 'Dinh Minh Thanh', 'Pham Nguyen Khang', '12/03/2025 07:58', '2', 'Trùng lặp', '03/03/2025 13:06', NULL, NULL, NULL, 2, '2025-03-12 07:58', 'minhthanhonly', '1', '0', NULL, NULL, '0'),
(701, 'ngan', 13, '2025-03-03', '2025-03-03', '07:30', '11:30', 'False', 'Mất điện', 'Mất điện', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '03/03/2025 13:10', '1', '', '03/03/2025 13:09', NULL, NULL, NULL, 1, '2025-03-03 13:10', 'van.tu', '1', '0', NULL, NULL, '0'),
(702, 'duyhoang', 17, '2025-03-04', '2025-03-04', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '03/03/2025 13:37', '1', '', '03/03/2025 13:36', NULL, NULL, NULL, 1, '2025-03-03 13:37', 'nguyen', '1', '0', NULL, NULL, '0'),
(703, 'dinh', 8, '2025-03-04', '2025-03-04', '00:00', '00:00', 'True', 'Chăm bệnh', 'chăm mẹ nằm viện', 'tuyet2015', 'tuyet2015', 'Huynh Thi Anh Tuyet', 'Huynh Thi Anh Tuyet', '04/03/2025 09:03', '1', 'Đã xác nhận.\r\n', '04/03/2025 09:02', NULL, NULL, NULL, 1, '2025-03-04 09:03', 'tuyet2015', '1', '0', NULL, NULL, '0'),
(704, 'duya', 18, '2025-03-07', '2025-03-07', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép năm.', 'luan', 'luan', 'Vo Minh Luan', 'Vo Minh Luan', '04/03/2025 10:48', '1', '', '04/03/2025 10:48', NULL, NULL, NULL, 1, '2025-03-04 10:48', 'luan', '0', '0', NULL, NULL, '0'),
(705, 'bich', 17, '2025-03-04', '2025-03-04', '07:30', '11:30', 'False', 'Việc đột xuất', 'đưa bé đi khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '04/03/2025 13:03', '1', '', '04/03/2025 13:02', NULL, NULL, NULL, 1, '2025-03-04 13:03', 'khang', '1', '0', NULL, NULL, '0'),
(706, 'lien', 9, '2025-03-05', '2025-03-07', '00:00', '00:00', 'True', 'Tang người thân', 'bà mất', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/03/2025 08:07', '1', '', '04/03/2025 17:28', NULL, NULL, NULL, 1, '2025-03-05 08:07', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(707, 'nguyen', 17, '2025-03-05', '2025-03-05', '13:00', '17:00', 'False', 'Khám bệnh', 'Khám bệnh', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/03/2025 09:52', '1', '', '05/03/2025 07:25', NULL, NULL, NULL, 1, '2025-03-05 09:52', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(708, 'huyhoang', 17, '2025-03-06', '2025-03-06', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/03/2025 09:52', '1', '', '05/03/2025 07:44', NULL, NULL, NULL, 1, '2025-03-05 09:52', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(709, 'quyen', 17, '2025-03-07', '2025-03-07', '07:30', '11:30', 'False', 'Việc cá nhân', 'Có việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/03/2025 09:52', '1', '', '05/03/2025 07:44', NULL, NULL, NULL, 1, '2025-03-05 09:52', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(710, 'vutrang', 14, '2025-03-07', '2025-03-07', '07:30', '11:30', 'False', 'Mất điện', 'mất điện nên xin nghỉ buổi sáng', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '06/03/2025 13:36', '1', '', '06/03/2025 07:13', NULL, NULL, NULL, 1, '2025-03-06 13:36', 'bi', '1', '0', NULL, NULL, '0'),
(711, 'thanh', 11, '2025-03-07', '2025-03-07', '00:00', '00:00', 'True', 'Việc cá nhân', 'Nghỉ phép 1 ngày', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '06/03/2025 08:06', '1', '', '06/03/2025 08:05', NULL, NULL, NULL, 1, '2025-03-06 08:06', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(712, 'vankhanh', 9, '2025-03-07', '2025-03-07', '07:30', '11:30', 'False', 'Mất điện', 'mất điện', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Vu Thi Lien', '07/03/2025 13:33', '1', '', '07/03/2025 13:16', NULL, NULL, NULL, 2, '2025-03-07 13:33', 'tranvinh.loc', '1', '0', NULL, '0', '0'),
(713, 'ly', 14, '2025-03-10', '2025-03-10', '07:30', '11:30', 'False', 'Việc đột xuất', 'Việc đột xuất', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '10/03/2025 07:01', '1', '', '09/03/2025 16:25', NULL, NULL, NULL, 1, '2025-03-10 07:01', 'bi', '1', '0', NULL, NULL, '0'),
(714, 'hien', 12, '2025-03-20', '2025-03-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'bận việc cá nhân ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/03/2025 16:22', '1', '', '10/03/2025 16:21', NULL, NULL, NULL, 1, '2025-03-10 16:22', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(715, 'minhthomonly', 17, '2025-03-07', '2025-03-07', '00:00', '00:00', 'True', 'Giảm stress', 'nghỉ việc gia đình', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '11/03/2025 07:27', '1', 'Duyệt tự động', '11/03/2025 07:27', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(716, 'vantoan', 17, '2025-03-10', '2025-03-10', '00:00', '00:00', 'True', 'Khác', 'Cup điện ', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '11/03/2025 07:37', '1', 'Đã xác nhận', '11/03/2025 07:36', NULL, NULL, NULL, 1, '2025-03-11 07:37', 'nhu', '1', '0', NULL, NULL, '0'),
(717, 'khang', 17, '2025-03-14', '2025-03-14', '00:00', '00:00', 'True', 'Giảm stress', 'Về quê', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/03/2025 07:45', '1', '', '11/03/2025 07:44', NULL, NULL, NULL, 1, '2025-03-11 07:45', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(718, 'ha', 10, '2025-03-20', '2025-03-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'có việc riêng', 'nhan', 'nhan', 'Diep Thanh Nhan', 'Diep Thanh Nhan', '11/03/2025 09:38', '1', 'Leader ok!', '11/03/2025 09:37', NULL, NULL, NULL, 1, '2025-03-11 09:38', 'nhan', '1', '0', NULL, NULL, '0'),
(719, 'minhthomonly', 17, '2025-03-13', '2025-03-13', '13:00', '17:00', 'False', 'Việc cá nhân', 'việc gia đình', 'minhthomonly', NULL, NULL, 'Dinh Minh Thom', '11/03/2025 11:02', '1', 'Duyệt tự động', '11/03/2025 11:02', NULL, NULL, NULL, 0, '', '', '1', '0', NULL, NULL, '0'),
(720, 'bich', 17, '2025-03-10', '2025-03-11', '00:00', '00:00', 'True', 'Chăm bệnh', 'chăm bé bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '12/03/2025 07:26', '1', '', '12/03/2025 07:21', NULL, NULL, NULL, 1, '2025-03-12 07:26', 'khang', '0', '0', NULL, NULL, '1'),
(721, 'nhu', 17, '2025-03-13', '2025-03-13', '00:00', '00:00', 'True', 'Khác', 'Bận việc gia đình', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '12/03/2025 07:42', '1', '', '12/03/2025 07:30', NULL, NULL, NULL, 1, '2025-03-12 07:42', 'minhthomonly', '1', '0', NULL, NULL, '0'),
(722, 'tuyet2015', 8, '2025-03-17', '2025-03-18', '07:30', '17:00', 'False', 'Việc cá nhân', 'Về quê có việc gia đình', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/03/2025 17:02', '1', '', '12/03/2025 16:57', NULL, NULL, NULL, 1, '2025-03-12 17:02', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(723, 'quyen', 17, '2025-03-14', '2025-03-14', '13:00', '17:00', 'False', 'Việc cá nhân', 'Việc cá nhân', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/03/2025 07:43', '1', '', '12/03/2025 17:01', NULL, NULL, NULL, 1, '2025-03-13 07:43', 'minhthomonly', '0', '0', NULL, NULL, '0'),
(724, 'vantoan', 17, '2025-03-14', '2025-03-14', '13:00', '17:00', 'False', 'Việc cá nhân', 'Lấy bằng lái xe máy', 'nhu', 'nhu', 'Nguyen Thi Nhu', 'Nguyen Thi Nhu', '14/03/2025 11:28', '1', '', '14/03/2025 11:27', NULL, NULL, NULL, 1, '2025-03-14 11:28', 'nhu', '1', '0', NULL, NULL, '0'),
(725, 'minhthang', 14, '2025-03-17', '2025-03-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '14/03/2025 15:10', '1', '', '14/03/2025 14:12', NULL, NULL, NULL, 1, '2025-03-14 15:10', 'hoai', '1', '0', NULL, NULL, '0'),
(726, 'hoai', 14, '2025-03-18', '2025-03-18', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '14/03/2025 17:05', '1', '', '14/03/2025 16:46', NULL, NULL, NULL, 1, '2025-03-14 17:05', 'dat', '0', '0', NULL, NULL, '0'),
(727, 'bich', 17, '2025-03-18', '2025-03-18', '00:00', '00:00', 'True', 'Việc cá nhân', 'việc cá nhân', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '17/03/2025 07:18', '1', '', '17/03/2025 07:11', NULL, NULL, NULL, 1, '2025-03-17 07:18', 'khang', '1', '0', NULL, NULL, '0'),
(728, 'ngocle', 17, '2025-03-17', '2025-03-17', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'nguyen', 'nguyen', 'Lam Duy Nguyen', 'Lam Duy Nguyen', '17/03/2025 07:25', '1', '', '17/03/2025 07:24', NULL, NULL, NULL, 1, '2025-03-17 07:25', 'nguyen', '1', '0', NULL, NULL, '0'),
(729, 'vi', 14, '2025-03-17', '2025-03-17', '13:00', '17:00', 'False', 'Việc đột xuất', 'bận việc đột xuất', 'hoai', 'hoai', 'Nguyen Thi Hoai', 'Nguyen Thi Hoai', '17/03/2025 07:38', '1', '', '17/03/2025 07:37', NULL, NULL, NULL, 1, '2025-03-17 07:38', 'hoai', '1', '0', NULL, NULL, '0'),
(730, 'thoi', 17, '2025-03-17', '2025-03-17', '11:30', '17:00', 'False', 'Việc đột xuất', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '17/03/2025 09:44', '1', '', '17/03/2025 09:29', NULL, NULL, NULL, 1, '2025-03-17 09:44', 'huyhoang', '1', '0', NULL, NULL, '0'),
(731, 'thiet', 14, '2025-03-17', '2025-03-17', '13:00', '17:00', 'False', 'Việc đột xuất', 'việc đột xuất ', 'bi', 'bi', 'Nguyen Van Bi', 'Nguyen Van Bi', '17/03/2025 13:03', '1', '', '17/03/2025 13:03', NULL, NULL, NULL, 1, '2025-03-17 13:03', 'bi', '0', '0', NULL, NULL, '0'),
(732, 'vinh', 17, '2025-03-17', '2025-03-18', '00:00', '00:00', 'True', 'Việc đột xuất', 'gia đình có việc gấp', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '18/03/2025 07:40', '1', '', '18/03/2025 07:40', NULL, NULL, NULL, 1, '2025-03-18 07:40', 'khang', '1', '0', NULL, NULL, '0'),
(733, 'hien', 12, '2025-03-19', '2025-03-19', '11:30', '17:00', 'False', 'Việc cá nhân', 'bận việc cá nhân ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/03/2025 08:59', '1', '', '18/03/2025 08:58', NULL, NULL, NULL, 1, '2025-03-18 08:59', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(734, 'ngan', 13, '2025-03-20', '2025-03-21', '00:00', '00:00', 'True', 'Việc cá nhân', 'Việc cá nhân', 'van.tu', 'van.tu', 'Huynh Van Tu', 'Huynh Van Tu', '18/03/2025 13:48', '1', '', '18/03/2025 13:08', NULL, NULL, NULL, 1, '2025-03-18 13:48', 'van.tu', '1', '0', NULL, NULL, '0'),
(735, 'yen', 17, '2025-03-20', '2025-03-20', '07:30', '11:30', 'False', 'Việc cá nhân', 'việc cá nhân', 'huyhoang', 'huyhoang', 'Nguyen Huy Hoang', 'Nguyen Huy Hoang', '19/03/2025 17:10', '1', '', '19/03/2025 17:08', NULL, NULL, NULL, 1, '2025-03-19 17:10', 'huyhoang', '1', '0', NULL, NULL, '0'),
(736, 'van.tu', 13, '2025-03-19', '2025-03-19', '07:30', '11:30', 'False', 'Mất điện', 'MẤT ĐIỆN', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/03/2025 07:23', '1', '', '19/03/2025 17:18', NULL, NULL, NULL, 1, '2025-03-20 07:23', 'tranvinh.loc', '1', '0', NULL, NULL, '0'),
(737, 'vinh', 17, '2025-03-19', '2025-03-19', '00:00', '00:00', 'True', 'Chăm bệnh', 'đưa con khám bệnh', 'khang', 'khang', 'Pham Nguyen Khang', 'Pham Nguyen Khang', '20/03/2025 07:29', '1', '', '20/03/2025 07:28', NULL, NULL, NULL, 1, '2025-03-20 07:29', 'khang', '1', '0', NULL, NULL, '0');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_folder`
--

CREATE TABLE `groupware_folder` (
  `id` int(11) NOT NULL,
  `folder_type` mediumtext NOT NULL,
  `folder_id` int(11) NOT NULL,
  `folder_caption` mediumtext NOT NULL,
  `folder_name` mediumtext DEFAULT NULL,
  `folder_date` mediumtext DEFAULT NULL,
  `folder_order` int(11) DEFAULT NULL,
  `add_level` int(11) DEFAULT NULL,
  `add_group` mediumtext DEFAULT NULL,
  `add_user` mediumtext DEFAULT NULL,
  `public_level` int(11) DEFAULT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_folder`
--

INSERT INTO `groupware_folder` (`id`, `folder_type`, `folder_id`, `folder_caption`, `folder_name`, `folder_date`, `folder_order`, `add_level`, `add_group`, `add_user`, `public_level`, `public_group`, `public_user`, `edit_level`, `edit_group`, `edit_user`, `owner`, `editor`, `created`, `updated`) VALUES
(6, 'forum', 1, 'Webteam Thong Bao', 'Admin', '2018-08-22 07:56:16', 1, 0, '', '', 0, '', '', 1, '', '', 'admin', NULL, '2018-08-22 07:56:16', NULL),
(0, 'forum', 0, 'All', 'All', '2018-08-22 07:56:16', 1, 0, '', '', 0, '', '', 1, '', '', 'admin', NULL, '2018-08-22 07:56:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `groupware_forum`
--

CREATE TABLE `groupware_forum` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `forum_parent` int(11) NOT NULL,
  `forum_title` mediumtext DEFAULT NULL,
  `forum_name` mediumtext DEFAULT NULL,
  `forum_comment` mediumtext DEFAULT NULL,
  `forum_date` mediumtext DEFAULT NULL,
  `forum_file` mediumtext DEFAULT NULL,
  `forum_lastupdate` mediumtext DEFAULT NULL,
  `forum_node` int(11) DEFAULT NULL,
  `public_level` int(11) NOT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_forum`
--

INSERT INTO `groupware_forum` (`id`, `folder_id`, `forum_parent`, `forum_title`, `forum_name`, `forum_comment`, `forum_date`, `forum_file`, `forum_lastupdate`, `forum_node`, `public_level`, `public_group`, `public_user`, `edit_level`, `edit_group`, `edit_user`, `owner`, `editor`, `created`, `updated`) VALUES
(23, 1, 0, 'ThÃ´ng bÃ¡o nhom Web cáº­p nháº­t ná»™i quy Ä‘i trá»…, váº¯ng máº·t', 'Admin', 'ThÃ´ng bÃ¡o nhom Web:\r\nÄá»ƒ nháº§m háº¡n cháº¿ tÃ¬nh tráº¡ng Ä‘i trá»…, nÃ¢ng cao tÃ­nh Ä‘Ãºng giá» giáº¥c, nÃªn Ä‘Æ°a ra cÃ¡c quy Ä‘á»‹nh sau. Mong cÃ¡c báº¡n thá»±c hiá»‡n. Quy Ä‘á»‹nh Ã¡p dá»¥ng tá»« ngÃ y 1/12/2017.\r\n\r\n+ Äi trá»… quÃ¡ 5 phÃºt hoáº·c nghá»‰ Ä‘á»™t xuáº¥t vÃ¬ báº¥t ká»³ lÃ½ do gÃ¬ pháº£i gá»i Ä‘iá»‡n Ä‘áº¿n cty bÃ¡o (ko cháº¥p nháº­n hÃ¬nh thá»©c tin nháº¯n). Pháº£i bá»• sung giáº¥y phÃ©p sau Ä‘Ã³ liá»n. Náº¿u ko ná»™p hoáº·c ná»™p trá»… quÃ¡ 24 tiáº¿ng (tÃ­nh tá»« khi Ä‘i lÃ m láº¡i) sáº½ tÃ­nh lÃ  nghá»‰ ná»­a ngÃ y hoáº·c nhiá»u hÆ¡n tÃ¹y má»©c Ä‘á»™. TrÆ°á»ng há»£p báº¥t kháº£ khÃ¡ng ko thá»ƒ gá»i Ä‘iá»‡n bÃ¡o ngay cÃ³ thá»ƒ  chÃ¢m chÆ°á»›c nhÆ°ng pháº£i nÃªu rÃµ lÃ½ do trong Ä‘Æ¡n xin phÃ©p bá»• sung.\r\n-> SÄT liÃªn há»‡ Thanh: 0966448826, ÄT cty: 028.38245361\r\n\r\n+ Äi trá»… vÃ  nghá»‰ Ä‘á»™t xuáº¥t ko cÃ³ lÃ½ do chÃ­nh Ä‘Ã¡ng sáº½ ko Ä‘Æ°á»£c tÃ­nh vÃ o ngÃ y phÃ©p.\r\n\r\n+CÃ¡c trÆ°á»ng há»£p Ä‘i trá»… mÃ  báº¥m tháº» giá» Ä‘Ãºng 7h30, náº¿u phÃ¡t hiá»‡n Ä‘Æ°á»£c sáº½ ká»· luáº­t theo quy Ä‘á»‹nh.\r\n\r\n+ Khi cÃ³ káº¿ hoáº¡ch nghá»‰ phÃ©p nÄƒm nÃªn bÃ¡o sá»›m cho cáº¥p trÃªn vÃ  ná»™p Ä‘Æ¡n xin phÃ©p ngay sau Ä‘Ã³ (Ã­t nháº¥t trÆ°á»›c 1 tuáº§n). BÃ¡o dÆ°á»›i 1 tuáº§n  ko Ä‘Æ°á»£c tÃ­nh phÃ©p nÄƒm. Ko duyá»‡t cÃ¡c Ä‘Æ¡n xin nghá»‰ phÃ©p vá»›i cÃ¡c lÃ½ do chung chung nhÆ° \"báº­n viá»‡c riÃªng cÃ¡ nhÃ¢n\" hoáº·c bá» trá»‘ng lÃ½ do.\r\n\r\n+ Giáº¥y xin phÃ©p sáº½ ná»™p cho Thanh, cuá»‘i thÃ¡ng sáº½ tá»•ng há»£p ná»™p cho tá»•ng vá»¥. Ko ná»™p tá»«ng báº¡n ná»¯a.', '2018-08-22 08:00:28', '', '2018-08-22 08:00:28', 0, 0, '', '', 1, '', '', 'admin', NULL, '2018-08-22 08:00:28', NULL),
(24, 0, 0, ' ThÃ´ng bÃ¡o nghá»‰ lá»… Quá»‘c KhÃ¡nh', 'Admin', 'THÃ”NG BÃO:\r\nTá»•ng vá»¥ xin thÃ´ng bÃ¡o ngÃ y nghá»‰ lá»… Quá»‘c KhÃ¡nh nhÆ° sauï¼š\r\nâ–  NgÃ y 2/9 (Chá»§ nháº­t)\r\nâ–  NgÃ y 3/9 (Thá»© 2) nghá»‰ bÃ¹ 2/9\r\nCá»¥ thá»ƒ nghá»‰ tá»«ï¼š \r\n1/9 ï¼ˆthá»© 7ï¼‰ï½ž 3/9ï¼ˆthá»© 2ï¼‰\r\nChÃº Ã½ï¼š\r\nâ—‡ã€€TrÆ°á»›c ngÃ y nghá»‰ pháº£i thá»±c hiá»‡n liÃªn láº¡c khÃ¡ch hÃ ng, Ä‘iá»u chá»‰nh cÃ´ng viá»‡c cho ká»‹p tiáº¿n Ä‘á»™.\r\nâ—‡ã€€Trong ngÃ y nghá»‰ náº¿u pháº£i Ä‘i lÃ m vui lÃ²ng liÃªn láº¡c trÆ°á»›c vá»›i Bp.Tá»•ng vá»¥ (Ms. Trang) Ä‘á»ƒ lÃ m thá»§ tá»¥c cáº§n thiáº¿t.\r\nâ—‡ã€€Äá»ƒ Ä‘áº£m báº£o an toÃ n PCCC yÃªu cáº§u táº¯t cÃ¡c thiáº¿t bá»‹ Ä‘iá»‡n.\r\nChÃºc cÃ¡c báº¡n cÃ³ ká»³ nghá»‰ an toÃ n vÃ  vui váº»!', '2018-08-22 09:36:55', '', '2018-08-22 09:36:55', 0, 0, '', '', 1, '', '', 'admin', NULL, '2018-08-22 09:36:55', NULL),
(25, 0, 0, 'KhÃ¡m sá»©c khá»e Ä‘á»‹nh ká»³ 2018', 'Admin', 'THÃ”NG BÃO: \r\nV/v  KHÃM Sá»¨C KHá»ŽE Äá»ŠNH Ká»² NÄ‚M 2018\r\n\r\nThá»i gian khÃ¡m vÃ  xÃ©t nghiá»‡m: 7h00 â€“ 11h00 ngÃ y 25/08/2018\r\nÄá»‹a Ä‘iá»ƒm: Bá»‡nh viá»‡n Äa khoa Váº¡n Háº¡nh, 781/B1-B3-B5 LÃª Há»“ng Phong (ná»‘i dÃ i), P.12, Q.10.\r\n*LÆ°u Ã½*: \r\n- Phá»¥ ná»¯ cÃ³ thai hay nghi ngá» cÃ³ thai : khÃ´ng chá»¥p X Quang.\r\n- KhÃ´ng Äƒn sÃ¡ng trÆ°á»›c khi khÃ¡m\r\n- KhÃ´ng uá»‘ng cafe, rÆ°á»£u bia, cháº¥t kÃ­ch thÃ­ch trong 10 tiáº¿ng trÆ°á»›c khi khÃ¡m.\r\n\r\nC/c: cÃ¡c báº¡n cÃ²n Ä‘ang thá»­ viá»‡c thÃ¬ khÃ´ng khÃ¡m trong Ä‘á»£t nÃ y.\r\nTrÃ¢n trá»ng!', '2018-08-23 15:56:41', '', '2018-08-23 15:56:41', 0, 0, '', '', 1, '', '', 'admin', NULL, '2018-08-23 15:56:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `groupware_group`
--

CREATE TABLE `groupware_group` (
  `id` int(11) NOT NULL,
  `group_name` mediumtext NOT NULL,
  `group_order` int(11) DEFAULT NULL,
  `add_level` int(11) NOT NULL,
  `add_group` mediumtext DEFAULT NULL,
  `add_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_group`
--

INSERT INTO `groupware_group` (`id`, `group_name`, `group_order`, `add_level`, `add_group`, `add_user`, `edit_level`, `edit_group`, `edit_user`, `owner`, `editor`, `created`, `updated`) VALUES
(1, 'Chung', 0, 0, '', '', 0, '', '', 'admin', 'admin', '2011-07-13 16:59:48', '2021-03-10 03:39:02'),
(4, 'Đã Nghỉ', 100, 0, '', '', 0, '', '', 'admin', 'admin', '2011-07-14 09:43:47', '2024-05-14 08:45:47'),
(8, 'Thiet Bi A', 1, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:13:09', '2024-05-14 08:44:10'),
(7, 'Web', 12, 0, '', '', 0, '', '', 'admin', 'admin', '2019-02-13 08:37:59', '2021-03-10 04:17:51'),
(9, 'Thiet Bi B', 2, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:13:18', '2024-05-14 08:44:15'),
(10, 'Thiet Bi C', 4, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:13:25', '2024-05-14 08:44:28'),
(11, 'Thiet Bi D', 5, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:13:43', '2024-05-14 08:44:31'),
(12, 'Thiet Bi E', 6, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:13:53', '2024-05-14 08:44:35'),
(13, 'Thiet Bi F', 7, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:14:00', '2024-05-14 08:44:38'),
(14, '3D', 8, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:14:08', '2024-05-14 08:44:55'),
(15, 'Nang Luong', 9, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:14:17', '2024-05-14 08:45:05'),
(17, 'Kien Truc', 9, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:14:34', '2024-05-14 08:44:58'),
(18, 'Ket Cau', 11, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:14:59', '2024-05-14 08:45:26'),
(19, 'Tổng Vụ', 13, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:42:07', '2024-05-14 08:46:07'),
(20, 'Thiet Bi B1', 3, 0, '', '', 0, '', '', 'admin', 'admin', '2021-03-10 03:45:51', '2024-05-14 08:44:20');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_holiday`
--

CREATE TABLE `groupware_holiday` (
  `id` int(11) NOT NULL,
  `date` varchar(20) NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_holiday`
--

INSERT INTO `groupware_holiday` (`id`, `date`, `name`) VALUES
(1, '2024-09-03', 'Lễ quốc khánh'),
(2, '2024-09-02', 'Lễ quốc khánh'),
(3, '2025-01-01', 'Tết DL'),
(4, '2025-01-27', 'Tết Nguyên Đán'),
(5, '2025-01-28', 'Tết Nguyên Đán'),
(6, '2025-01-29', 'Tết Nguyên Đán'),
(7, '2025-01-30', 'Tết Nguyên Đán'),
(8, '2025-01-31', 'Tết Nguyên Đán'),
(9, '2025-04-07', 'Giỗ Tổ Hùng Vương'),
(10, '2025-04-30', 'Ngày Giải phóng miền Nam');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_log`
--

CREATE TABLE `groupware_log` (
  `id` int(11) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `time_count` varchar(10) DEFAULT NULL,
  `log_type` varchar(50) DEFAULT NULL,
  `title` text NOT NULL,
  `created_date` varchar(20) NOT NULL,
  `start_time` varchar(50) DEFAULT NULL,
  `end_time` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Triggers `groupware_log`
--
DELIMITER $$
CREATE TRIGGER `Delete log >1month` BEFORE DELETE ON `groupware_log` FOR EACH ROW DELETE FROM groupware_log
WHERE created_date < NOW() - INTERVAL 1 MONTH
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_message`
--

CREATE TABLE `groupware_message` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `message_type` mediumtext NOT NULL,
  `message_to` mediumtext NOT NULL,
  `message_from` mediumtext NOT NULL,
  `message_toname` mediumtext DEFAULT NULL,
  `message_fromname` mediumtext DEFAULT NULL,
  `message_title` mediumtext DEFAULT NULL,
  `message_comment` mediumtext DEFAULT NULL,
  `message_date` mediumtext DEFAULT NULL,
  `message_file` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_notification`
--

CREATE TABLE `groupware_notification` (
  `id` int(11) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_date` varchar(50) NOT NULL,
  `isRead` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_overtime`
--

CREATE TABLE `groupware_overtime` (
  `id` int(11) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `date_start` varchar(20) NOT NULL,
  `date_end` varchar(20) DEFAULT NULL,
  `time_start` varchar(10) DEFAULT NULL,
  `time_end` varchar(10) DEFAULT NULL,
  `allday` varchar(6) DEFAULT NULL,
  `offtype` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `confirm_userid` varchar(50) DEFAULT NULL,
  `confirm_real_userid` varchar(50) DEFAULT NULL,
  `confirm_real_name` varchar(50) DEFAULT NULL,
  `confirm_name` varchar(50) DEFAULT NULL,
  `confirm_date` varchar(20) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `comment` varchar(200) DEFAULT NULL,
  `created_time` varchar(20) NOT NULL,
  `req_edit_by` varchar(50) DEFAULT NULL,
  `req_edit_time` varchar(20) DEFAULT NULL,
  `req_edit_name` varchar(50) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 0,
  `update_date` varchar(20) NOT NULL,
  `update_by` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_overtime`
--

INSERT INTO `groupware_overtime` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`) VALUES
(3, 'minhthanhonly', 7, '2024-08-05', '2024-08-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: Hub01\r\nPJ Name: CailyHub', 'minhthanhonly', 'minhthanhonly', 'Dinh Minh Thanh', 'Dinh Minh Thanh', '30/08/2024 16:48', '3', 'Duyệt tự động', '05/08/2024 17:56', 'minhthanhonly', '05/08/2024 18:00', 'Dinh Minh Thanh', 12, '2024-08-30 16:48', 'minhthanhonly'),
(4, 'ly', 14, '2024-08-01', '2024-08-01', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.:20240726\r\nPJ Name:京葉ガス浦安ビル用地・賃貸ビル\r\nYêu cầu từ: leader', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '06/08/2024 09:38', '1', 'đã xác nhận - Đạt', '06/08/2024 09:30', 'dat', '06/08/2024 09:36', 'Tran Vinh Dat', 7, '2024-08-06 09:38', 'dat'),
(5, 'dai', 14, '2024-08-01', '2024-08-01', '17:00', '19:20', 'False', 'Được yêu cầu', 'PJ No.: 20240726\r\nPJ Name: 京葉ガス浦安ビル用地・賃貸ビル\r\nYêu cầu từ: Leader', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '06/08/2024 09:35', '1', 'đã xác nhận - Đạt', '06/08/2024 09:33', NULL, NULL, NULL, 1, '2024-08-06 09:35', 'dat'),
(6, 'duykhanh', 17, '2024-08-02', '2024-08-02', '17:00', '17:30', 'False', 'Được yêu cầu', 'PJ No.: 0278181-01 （B）福田　佐智子様W3F3J　特注　240000\r\nPJ Name: せたがや（世田谷）SETAGAYA\r\nYêu cầu từ: Dinh Minh Thom', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '09/08/2024 07:50', '1', '', '08/08/2024 13:49', NULL, NULL, NULL, 1, '2024-08-09 07:50', 'minhthomonly'),
(7, 'tuyet2015', 8, '2024-08-05', '2024-08-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: Huỳnh thị ánh tuyết\r\nYêu cầu từ: 春日井	0278604-01	児玉　幸男様	W3F4J	TAC共同	8月5日', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '15/08/2024 17:44', '1', '', '15/08/2024 15:39', NULL, NULL, NULL, 2, '2024-08-15 15:44', 'tranvinh.loc'),
(8, 'tam', 8, '2024-08-05', '2024-08-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 春日井 0278604-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết (giao bài kịp tiến độ)', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '16/08/2024 18:13', '1', '', '16/08/2024 16:06', NULL, NULL, NULL, 1, '2024-08-16 16:13', 'tranvinh.loc'),
(9, 'dinh', 8, '2024-08-05', '2024-08-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0278604-01\r\nPJ Name: 児玉　幸男様	W3F4J	TAC共同', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:05', '1', '', '20/08/2024 15:57', NULL, NULL, NULL, 1, '2024-08-20 16:05', 'tranvinh.loc'),
(10, 'khanh', 8, '2024-08-05', '2024-08-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 春日井	0278604-01\r\nPJ Name: 児玉　幸男様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:05', '1', '', '20/08/2024 16:04', NULL, NULL, NULL, 1, '2024-08-20 16:05', 'tranvinh.loc'),
(11, 'tuyen', 8, '2024-08-05', '2024-08-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 春日井	0278604-01\r\nPJ Name: 児玉　幸男様	W3F4J	TAC共同	\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:09', '1', '', '20/08/2024 16:09', NULL, NULL, NULL, 1, '2024-08-20 16:09', 'tranvinh.loc'),
(12, 'hien', 12, '2024-08-20', '2024-08-20', '17:00', '18:00', 'False', 'Tự nguyện', 'PJ No.: 48C0352\r\nPJ Name: 名古屋北支店　水野晴和様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:28', '1', '', '20/08/2024 16:25', NULL, NULL, NULL, 1, '2024-08-20 16:28', 'tranvinh.loc'),
(13, 'tuyet2015', 8, '2024-08-20', '2024-08-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 柏	0279600-01	小倉智恵子・小倉春吉様01棟	W3F6J	TAC集合	8月	20日\r\nPJ Name: 柏	0279600-01	小倉智恵子・小倉春吉様01棟	W3F6J	TAC集合	8月	20日\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:28', '1', '', '20/08/2024 16:26', NULL, NULL, NULL, 2, '2024-08-20 16:28', 'tranvinh.loc'),
(14, 'dinh', 8, '2024-08-20', '2024-08-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0279600-02\r\nPJ Name: 小倉智恵子・小倉春吉様02棟\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:28', '1', '', '20/08/2024 16:27', NULL, NULL, NULL, 1, '2024-08-20 16:28', 'tranvinh.loc'),
(15, 'tam', 8, '2024-08-20', '2024-08-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 柏0279600-02\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết giao bài kịp tiến độ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:39', '1', '', '20/08/2024 16:30', NULL, NULL, NULL, 1, '2024-08-20 16:39', 'tranvinh.loc'),
(16, 'tuyen', 8, '2024-08-20', '2024-08-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 柏　0279600-02\r\nPJ Name: 小倉智恵子・小倉春吉様02棟	W3F5J	TAC集合\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:39', '1', '', '20/08/2024 16:30', NULL, NULL, NULL, 1, '2024-08-20 16:39', 'tranvinh.loc'),
(17, 'khanh', 8, '2024-08-20', '2024-08-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 柏　0279600-02\r\nPJ Name: 小倉智恵子・小倉春吉様02棟\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/08/2024 18:39', '1', '', '20/08/2024 16:30', NULL, NULL, NULL, 1, '2024-08-20 16:39', 'tranvinh.loc'),
(18, 'hien', 12, '2024-08-21', '2024-08-21', '17:00', '19:00', 'False', 'Tự nguyện', 'PJ No.: 48D0178\r\nPJ Name: 東広島支店　株式会社中央地所様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/08/2024 18:30', '1', '', '21/08/2024 16:28', NULL, NULL, NULL, 2, '2024-08-21 16:30', 'tranvinh.loc'),
(19, 'hanhthach', 20, '2024-08-01', '2024-08-01', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: setagaza\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/08/2024 10:08', '1', '', '22/08/2024 07:47', NULL, NULL, NULL, 1, '2024-08-22 08:08', 'tranvinh.loc'),
(20, 'khang', 17, '2024-08-27', '2024-08-27', '17:00', '19:00', 'False', 'Tự nguyện', 'PJ No.: 0280586-01\r\nPJ Name: 有本　武司様　共同住宅新築工事\r\nHợp đồng gấp sáng mai giao', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '27/08/2024 17:53', '1', 'đã xác nhận', '27/08/2024 15:38', NULL, NULL, NULL, 1, '2024-08-27 17:53', 'minhthomonly'),
(21, 'hien', 12, '2024-08-05', '2024-08-05', '17:00', '18:30', 'False', 'Được yêu cầu', 'PJ No.: 48D0178\r\nPJ Name: 東広島支店　株式会社中央地所様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/09/2024 09:58', '1', '', '04/09/2024 07:57', NULL, NULL, NULL, 1, '2024-09-04 07:58', 'tranvinh.loc'),
(22, 'tuyet2015', 8, '2024-09-04', '2024-09-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 奈良	0278337-01	四ッ　健一様（ヨツツジ）\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/09/2024 18:27', '1', '', '04/09/2024 16:26', NULL, NULL, NULL, 1, '2024-09-04 16:27', 'tranvinh.loc'),
(23, 'tuyen', 8, '2024-09-04', '2024-09-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 四日市	0279728-01	\r\nPJ Name: 橋本　信賢様	W3F5J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/09/2024 18:27', '1', '', '04/09/2024 16:26', NULL, NULL, NULL, 1, '2024-09-04 16:27', 'tranvinh.loc'),
(24, 'khanh', 8, '2024-09-04', '2024-09-04', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0279728-01\r\nPJ Name: 橋本　信賢様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/09/2024 18:27', '1', '', '04/09/2024 16:27', NULL, NULL, NULL, 1, '2024-09-04 16:27', 'tranvinh.loc'),
(25, 'tam', 8, '2024-09-04', '2024-09-04', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 四日市0279728-01(giao bài kịp tiến độ)\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/09/2024 18:27', '1', '', '04/09/2024 16:27', NULL, NULL, NULL, 1, '2024-09-04 16:27', 'tranvinh.loc'),
(26, 'dinh', 8, '2024-09-04', '2024-09-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２７９７２８－０１\r\nPJ Name: 四日市支店＿橋本　信賢様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/09/2024 18:41', '1', '', '04/09/2024 16:40', NULL, NULL, NULL, 1, '2024-09-04 16:41', 'tranvinh.loc'),
(27, 'tuyet2015', 8, '2024-09-05', '2024-09-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 四日市	0279728-01	橋本　信賢様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/09/2024 18:19', '1', '', '05/09/2024 16:16', NULL, NULL, NULL, 1, '2024-09-05 16:19', 'tranvinh.loc'),
(28, 'dinh', 8, '2024-09-05', '2024-09-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: ０２７９７２８－０１\r\nPJ Name: 四日市支店＿橋本　信賢様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/09/2024 18:19', '1', '', '05/09/2024 16:17', NULL, NULL, NULL, 1, '2024-09-05 16:19', 'tranvinh.loc'),
(29, 'tuyen', 8, '2024-09-05', '2024-09-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 四日市	0279728-01\r\nPJ Name: 橋本　信賢様	W3F5J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/09/2024 18:37', '1', '', '05/09/2024 16:32', NULL, NULL, NULL, 1, '2024-09-05 16:37', 'tranvinh.loc'),
(30, 'khanh', 8, '2024-09-05', '2024-09-05', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0279728-01\r\nPJ Name: 橋本　信賢様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/09/2024 18:45', '1', '', '05/09/2024 16:45', NULL, NULL, NULL, 1, '2024-09-05 16:45', 'tranvinh.loc'),
(31, 'vi', 14, '2024-09-09', '2024-09-09', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240904\r\nPJ Name: 長坂不動産様中央区日本橋馬喰町１丁目\r\nYêu cầu từ: Leader -kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '09-09-2024 11:35', '1', 'chúc e tăng ca vui!', '09/09/2024 11:20', NULL, NULL, NULL, 1, '2024-09-09 11:35', 'dat'),
(32, 'long', 14, '2024-09-09', '2024-09-09', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240904\r\nPJ Name: 長坂不動産様中央区日本橋馬喰町１丁目\r\nYêu cầu từ: Leader - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '09-09-2024 11:36', '1', 'chúc e tăng ca vui!', '09/09/2024 11:25', NULL, NULL, NULL, 1, '2024-09-09 11:36', 'dat'),
(33, 'vi', 14, '2024-09-10', '2024-09-10', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240904\r\nPJ Name: 長坂不動産様中央区日本橋馬喰町１丁目\r\nYêu cầu từ: kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '10-09-2024 15:10', '1', '', '10/09/2024 15:07', NULL, NULL, NULL, 1, '2024-09-10 15:10', 'dat'),
(34, 'long', 14, '2024-09-10', '2024-09-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240904　\r\nPJ Name: 長坂不動産様中央区日本橋馬喰町１丁目\r\nYêu cầu từ: Leader - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '10-09-2024 16:00', '1', '', '10/09/2024 15:15', NULL, NULL, NULL, 1, '2024-09-10 16:00', 'dat'),
(35, 'minhthang', 14, '2024-09-11', '2024-09-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240904\r\nPJ Name: 長坂不動産様中央区日本橋馬喰町１丁目\r\nYêu cầu từ: leader - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '11-09-2024 16:37', '1', '', '11/09/2024 16:11', NULL, NULL, NULL, 1, '2024-09-11 16:37', 'dat'),
(36, 'hien', 12, '2024-09-18', '2024-09-18', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 49C0032\r\nPJ Name: 中村支店　株式会社桜町ビル様YTM\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/09/2024 17:53', '1', '', '18/09/2024 15:52', NULL, NULL, NULL, 1, '2024-09-18 15:53', 'tranvinh.loc'),
(37, 'hien', 12, '2024-09-19', '2024-09-19', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 49C0032 \r\nPJ Name: 中村支店　株式会社桜町ビル様YTM\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '19/09/2024 18:13', '1', '', '19/09/2024 16:11', NULL, NULL, NULL, 1, '2024-09-19 16:13', 'tranvinh.loc'),
(38, 'hien', 12, '2024-09-20', '2024-09-20', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 48I0361\r\nPJ Name: \"名取支店　有限会社サクラテック様A\n※東京オフィス\"\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/09/2024 18:47', '1', '', '20/09/2024 16:46', NULL, NULL, NULL, 1, '2024-09-20 16:47', 'tranvinh.loc'),
(39, 'vi', 14, '2024-09-24', '2024-09-24', '17:00', '19:30', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT 　12F6J　Kinshi 1 choumei\r\nYêu cầu từ: Leader- kịp dealine', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '24-09-2024 07:52', '1', '', '24/09/2024 07:30', NULL, NULL, NULL, 1, '2024-09-24 07:52', 'dat'),
(40, 'ly', 14, '2024-09-25', '2024-09-25', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: Leader yêu cầu- kịp thời gian giao bài', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '25-09-2024 13:19', '1', '', '25/09/2024 13:05', NULL, NULL, NULL, 1, '2024-09-25 13:19', 'dat'),
(41, 'vi', 14, '2024-09-25', '2024-09-25', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT 　12F6J　Kinshi 1 choumei\r\nYêu cầu từ: Leader- kịp deadline                                                                                                       ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '25-09-2024 13:19', '1', '', '25/09/2024 13:09', NULL, NULL, NULL, 1, '2024-09-25 13:19', 'dat'),
(42, 'minhthang', 14, '2024-09-25', '2024-09-25', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: LEADER - Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '25-09-2024 13:19', '1', '', '25/09/2024 13:18', NULL, NULL, NULL, 1, '2024-09-25 13:19', 'dat'),
(43, 'cong', 11, '2024-09-25', '2024-09-25', '17:00', '19:00', 'False', 'Tự nguyện', 'PJ No.: 名古屋 0280036-01\n奥村哲様     RC4F4J\r\nPJ Name: \r\nYêu cầu từ: làm kịp tiến độ', 'tranvinh.loc', 'cong', 'Nguyen Tien Cong', 'Tran Vinh Loc', '25/09/2024 15:02', '3', '', '25/09/2024 14:54', 'tranvinh.loc', '25/09/2024 17:00', 'Tran Vinh Loc', 2, '2024-09-25 15:01', 'cong'),
(44, 'cong', 11, '2024-09-25', '2024-09-25', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 名古屋 0280036-01 奥村哲様     RC4F4J\r\nPJ Name: Đoàn Hữu Thành\r\nYêu cầu từ: kịp tiến độ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '25/09/2024 17:07', '1', '', '25/09/2024 15:02', NULL, NULL, NULL, 1, '2024-09-25 15:07', 'tranvinh.loc'),
(45, 'hoai', 14, '2024-10-02', '2024-10-02', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240819\r\nPJ Name: 狛江市東和泉3丁目計画\r\nYêu cầu từ: Leader - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '02-10-2024 13:49', '1', '', '02/10/2024 13:17', NULL, NULL, NULL, 1, '2024-10-02 13:49', 'dat'),
(46, 'bi', 14, '2024-10-03', '2024-10-03', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240819\r\nPJ Name: （仮称）狛江市東和泉３丁目計画　\r\nYêu cầu từ: Leader- kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '03-10-2024 16:56', '1', '', '03/10/2024 14:29', NULL, NULL, NULL, 1, '2024-10-03 16:56', 'dat'),
(47, 'ngan', 13, '2024-10-07', '2024-10-07', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８００２２－０１\r\nPJ Name: （仮称）武蔵中原プロジェクト 新築工事\r\nYêu cầu từ: Huynh Van Tu', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 17:02', '1', '', '07/10/2024 15:01', NULL, NULL, NULL, 1, '2024-10-07 15:02', 'tranvinh.loc'),
(48, 'cong', 11, '2024-10-07', '2024-10-07', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 流通開発横浜支店  0280022-01  RC6F18J\r\nPJ Name: ĐOÀN HỮU THÀNH\r\nYêu cầu từ: kịp tiến độ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 17:07', '1', '', '07/10/2024 15:04', NULL, NULL, NULL, 1, '2024-10-07 15:07', 'tranvinh.loc'),
(49, 'tam', 8, '2024-10-07', '2024-10-07', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 高槻:0278445-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 18:29', '1', '', '07/10/2024 16:26', NULL, NULL, NULL, 1, '2024-10-07 16:29', 'tranvinh.loc'),
(50, 'tuyet2015', 8, '2024-10-07', '2024-10-07', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: làm và check 高槻	0278445-01	カオス株式会社様\r\n\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 18:29', '1', '', '07/10/2024 16:27', NULL, NULL, NULL, 1, '2024-10-07 16:29', 'tranvinh.loc'),
(51, 'khanh', 8, '2024-10-07', '2024-10-07', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0278445-01\r\nPJ Name: カオス株式会社様	W3F5J\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 18:29', '1', '', '07/10/2024 16:28', NULL, NULL, NULL, 1, '2024-10-07 16:29', 'tranvinh.loc'),
(52, 'dinh', 8, '2024-10-07', '2024-10-07', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: ０２７８４４５－０１\r\nPJ Name: カオス株式会社様　共同住宅新築工事\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 18:30', '1', '', '07/10/2024 16:30', NULL, NULL, NULL, 1, '2024-10-07 16:30', 'tranvinh.loc'),
(53, 'tuyen', 8, '2024-10-07', '2024-10-07', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.:  高槻 0278445-01 \r\nPJ Name: カオス株式会社様　W3F5J　TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/10/2024 18:47', '1', '', '07/10/2024 16:46', NULL, NULL, NULL, 1, '2024-10-07 16:47', 'tranvinh.loc'),
(54, 'hoai', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240702　\r\nPJ Name: I様築地6丁目計画\r\nYêu cầu từ: Leader - sửa checkback - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 12:49', NULL, NULL, NULL, 1, '2024-10-08 13:41', 'dat'),
(55, 'dai', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: Leader', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 13:01', NULL, NULL, NULL, 3, '2024-10-08 13:41', 'dat'),
(56, 'ly', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: Leader - Tăng ca kịp tiến độ ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 13:07', NULL, NULL, NULL, 1, '2024-10-08 13:41', 'dat'),
(57, 'thiet', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: leader- tăng ca kịp tiến độ ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 13:10', NULL, NULL, NULL, 1, '2024-10-08 13:41', 'dat'),
(58, 'bi', 14, '2024-10-08', '2024-10-08', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240819\r\nPJ Name: 狛江市東和泉3丁目計画\r\nYêu cầu từ: Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 13:30', NULL, NULL, NULL, 1, '2024-10-08 13:41', 'dat'),
(59, 'vi', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: Leader ,sửa checkback - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 13:32', NULL, NULL, NULL, 2, '2024-10-08 13:41', 'dat'),
(60, 'long', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: Leader - Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:41', '1', '', '08/10/2024 13:33', NULL, NULL, NULL, 1, '2024-10-08 13:41', 'dat'),
(61, 'minhthang', 14, '2024-10-08', '2024-10-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916\r\nPJ Name: 錦糸１丁目PROJECT\r\nYêu cầu từ: Leader yêu cầu, sửa checkback - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '08-10-2024 13:40', '1', '', '08/10/2024 13:37', NULL, NULL, NULL, 1, '2024-10-08 13:40', 'dat'),
(62, 'dinh', 8, '2024-10-10', '2024-10-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: ０２７９５１２ー０１\r\nPJ Name: 大東建託アセットソリューション株式会社様　\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/10/2024 18:34', '1', '', '10/10/2024 16:33', NULL, NULL, NULL, 1, '2024-10-10 16:34', 'tranvinh.loc'),
(63, 'tam', 8, '2024-10-10', '2024-10-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 流通開発横浜:0279512-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/10/2024 18:34', '1', '', '10/10/2024 16:34', NULL, NULL, NULL, 1, '2024-10-10 16:34', 'tranvinh.loc'),
(64, 'khanh', 8, '2024-10-10', '2024-10-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0279512-01\r\nPJ Name: 大東建託アセットソリューション株式会社様　（山梨県甲府市）', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/10/2024 18:35', '1', '', '10/10/2024 16:34', NULL, NULL, NULL, 1, '2024-10-10 16:35', 'tranvinh.loc'),
(65, 'tuyen', 8, '2024-10-10', '2024-10-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 流通開発横浜	0279512-01\r\nPJ Name: 大東建託アセットソリューション株式会社様（山梨県甲府市）W3F6J	TAC共同	\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/10/2024 18:35', '1', '', '10/10/2024 16:34', NULL, NULL, NULL, 1, '2024-10-10 16:35', 'tranvinh.loc'),
(66, 'tuyet2015', 8, '2024-10-10', '2024-10-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 流通開発横浜	0279512-01	大東建託アセットソリューション株式会社様　（山梨県甲府市）\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/10/2024 18:35', '1', '', '10/10/2024 16:35', NULL, NULL, NULL, 1, '2024-10-10 16:35', 'tranvinh.loc'),
(67, 'bi', 14, '2024-10-16', '2024-10-16', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240702\r\nPJ Name: 　I様築地6丁目計画　9F2JS　I Sama Tsukiji\r\nYêu cầu từ: kịp tiến độ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '16-10-2024 11:36', '1', '', '16/10/2024 11:25', NULL, NULL, NULL, 1, '2024-10-16 11:36', 'dat'),
(68, 'bi', 14, '2024-10-17', '2024-10-17', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20241007　\r\nPJ Name: 下小田中2丁目計画　4F9JT (Gym)　Shimo Odanaka (only P)\r\nYêu cầu từ: Kịp Tiến độ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '17-10-2024 12:59', '1', '', '17/10/2024 11:31', NULL, NULL, NULL, 1, '2024-10-17 12:59', 'dat'),
(69, 'thiet', 14, '2024-10-17', '2024-10-17', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240605\r\nPJ Name: H様山下町計画\r\nYêu cầu từ: leader- Tăng ca sửa bài kịp tiến độ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '17-10-2024 16:16', '1', '', '17/10/2024 13:06', NULL, NULL, NULL, 1, '2024-10-17 16:16', 'dat'),
(70, 'dai', 14, '2024-10-17', '2024-10-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20240605\r\nPJ Name: H様山下町計画\r\nYêu cầu từ: leader', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '17-10-2024 16:16', '1', '', '17/10/2024 16:13', NULL, NULL, NULL, 1, '2024-10-17 16:16', 'dat'),
(71, 'tuyet2015', 8, '2024-10-17', '2024-10-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大和	0279580-01	萩原　シヅエ様	W3F4J	TAC共同\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/10/2024 18:30', '1', '', '17/10/2024 16:29', NULL, NULL, NULL, 1, '2024-10-17 16:30', 'tranvinh.loc'),
(72, 'khanh', 8, '2024-10-17', '2024-10-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0279580-01\r\nPJ Name: 萩原　シヅエ様	W3F4J\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/10/2024 18:33', '1', '', '17/10/2024 16:32', NULL, NULL, NULL, 1, '2024-10-17 16:33', 'tranvinh.loc'),
(73, 'tuyen', 8, '2024-10-17', '2024-10-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大和	0279580-01	\r\nPJ Name: 萩原　シヅエ様	W3F4J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/10/2024 18:33', '1', '', '17/10/2024 16:32', NULL, NULL, NULL, 1, '2024-10-17 16:33', 'tranvinh.loc'),
(74, 'dinh', 8, '2024-10-17', '2024-10-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２７８４４５－０１,０２７９５８０－０１\r\nPJ Name: カオス株式会社様, 萩原　シヅエ様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/10/2024 18:36', '1', '', '17/10/2024 16:34', NULL, NULL, NULL, 1, '2024-10-17 16:36', 'tranvinh.loc'),
(75, 'vi', 14, '2024-10-22', '2024-10-22', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20241011\r\nPJ Name: 海老江１丁目プロジェクト\r\nYêu cầu từ: Leader- kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '22-10-2024 16:33', '1', '', '22/10/2024 15:59', NULL, NULL, NULL, 1, '2024-10-22 16:33', 'dat'),
(76, 'hien', 12, '2024-10-24', '2024-10-24', '17:00', '19:30', 'False', 'Được yêu cầu', 'PJ No.: 49C0034\r\nPJ Name: 名古屋北支店 柴山鉮子様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '24/10/2024 18:23', '1', '', '24/10/2024 16:21', NULL, NULL, NULL, 1, '2024-10-24 16:23', 'tranvinh.loc'),
(77, 'vi', 14, '2024-10-30', '2024-10-30', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20241021\r\nPJ Name: 文京区音羽計画　11F3JT\r\nYêu cầu từ: Leader- kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '30-10-2024 16:44', '1', '', '30/10/2024 13:11', NULL, NULL, NULL, 1, '2024-10-30 16:44', 'dat'),
(78, 'bi', 14, '2024-10-30', '2024-10-30', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20240916　\r\nPJ Name: 錦糸１丁目PROJECT 　12F6J\r\nYêu cầu từ: Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '30-10-2024 16:44', '1', '', '30/10/2024 16:03', NULL, NULL, NULL, 1, '2024-10-30 16:44', 'dat'),
(79, 'hien', 12, '2024-11-08', '2024-11-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 49C0032\r\nPJ Name: 中村支店　株式会社桜町ビル様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '08/11/2024 18:35', '1', '', '08/11/2024 16:35', NULL, NULL, NULL, 1, '2024-11-08 16:35', 'tranvinh.loc'),
(80, 'tuyet2015', 8, '2024-11-14', '2024-11-14', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 春日部	0280905-01	金子　雅紀様	W3F9J	TAC共同\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '14/11/2024 16:56', '1', '', '14/11/2024 14:55', NULL, NULL, NULL, 1, '2024-11-14 14:56', 'tranvinh.loc'),
(81, 'dinh', 8, '2024-11-14', '2024-11-14', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８０９０５－０１\r\nPJ Name: 金子　雅紀様　共同住宅新築工事\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '14/11/2024 17:54', '1', '', '14/11/2024 15:54', NULL, NULL, NULL, 1, '2024-11-14 15:54', 'tranvinh.loc'),
(82, 'khanh', 8, '2024-11-14', '2024-11-14', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0280905-01\r\nPJ Name: 春日部　金子　雅紀様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '14/11/2024 18:13', '1', '', '14/11/2024 16:13', NULL, NULL, NULL, 1, '2024-11-14 16:13', 'tranvinh.loc'),
(83, 'nguyen', 17, '2024-11-20', '2024-11-20', '17:00', '19:00', 'False', 'Tự nguyện', 'PJ No.: 技術開発部\r\nPJ Name: フォルターブⅢ\r\nLý do: Làm kịp tiến độ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/11/2024 07:37', '1', '', '20/11/2024 07:36', NULL, NULL, NULL, 1, '2024-11-20 07:37', 'minhthomonly'),
(84, 'ngocle', 17, '2024-11-20', '2024-11-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 技術開発部\r\nPJ Name: フォルターブⅢ\r\nYêu cầu từ: Lâm Duy Nguyên\r\nLý do: Làm kịp tiến độ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/11/2024 07:40', '1', '', '20/11/2024 07:44', NULL, NULL, NULL, 1, '2024-11-21 07:40', 'minhthomonly'),
(85, 'duyhoang', 17, '2024-11-20', '2024-11-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 技術開発部\r\nPJ Name: フォルターブⅢ\r\nYêu cầu từ: Lam Duy Nguyen', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/11/2024 07:40', '1', '', '20/11/2024 07:44', NULL, NULL, NULL, 1, '2024-11-21 07:40', 'minhthomonly'),
(86, 'hoai', 14, '2024-11-20', '2024-11-20', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 20241011\r\nPJ Name: 海老江１丁目プロジェクト\r\nYêu cầu từ: Leader - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '20/11/2024 17:03', '1', '', '20/11/2024 15:58', NULL, NULL, NULL, 1, '2024-11-20 17:03', 'dat'),
(87, 'dinh', 8, '2024-11-21', '2024-11-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２７９９８９－０１\r\nPJ Name: 澤井　由延様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/11/2024 17:13', '1', '', '21/11/2024 15:12', NULL, NULL, NULL, 1, '2024-11-21 15:13', 'tranvinh.loc'),
(88, 'tuyet2015', 8, '2024-11-21', '2024-11-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 京都	0279989-01	澤井　由延様	W3F4J	TAC共同\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/11/2024 17:14', '1', '', '21/11/2024 15:14', NULL, NULL, NULL, 1, '2024-11-21 15:14', 'tranvinh.loc'),
(89, 'khanh', 8, '2024-11-21', '2024-11-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0279989-01\r\nPJ Name: 澤井　由延様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/11/2024 18:12', '1', '', '21/11/2024 16:12', NULL, NULL, NULL, 1, '2024-11-21 16:12', 'tranvinh.loc'),
(90, 'tuyen', 8, '2024-11-27', '2024-11-27', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 流通開発千葉	0281111-01\r\nPJ Name: \"大東建託アセットソリューション株式会社様\r\nつくば市花畑ＰＪ\"	W3F6J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/11/2024 18:04', '1', '', '27/11/2024 16:03', NULL, NULL, NULL, 1, '2024-11-27 16:04', 'tranvinh.loc'),
(91, 'tuyet2015', 8, '2024-11-27', '2024-11-27', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 流通開発千葉	0281111-01	\"大東建託アセットソリューション株式会社様\nつくば市花畑ＰＪ\"\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/11/2024 18:04', '1', '', '27/11/2024 16:03', NULL, NULL, NULL, 1, '2024-11-27 16:04', 'tranvinh.loc'),
(92, 'dinh', 8, '2024-11-27', '2024-11-27', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８１１１１－０１\r\nPJ Name: つくば市花畑ＰＪ\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/11/2024 18:04', '1', '', '27/11/2024 16:03', NULL, NULL, NULL, 1, '2024-11-27 16:04', 'tranvinh.loc'),
(93, 'khanh', 8, '2024-11-27', '2024-11-27', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281111-01\r\nPJ Name: 大東建託アセットソリューション株式会社様\nつくば市花畑ＰＪ\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '27/11/2024 18:20', '1', '', '27/11/2024 16:20', NULL, NULL, NULL, 1, '2024-11-27 16:20', 'tranvinh.loc'),
(94, 'dinh', 8, '2024-11-28', '2024-11-28', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８０８９９－０１\r\nPJ Name: 森田　照子様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '28/11/2024 18:19', '1', '', '28/11/2024 16:01', NULL, NULL, NULL, 1, '2024-11-28 16:19', 'tranvinh.loc'),
(95, 'tuyet2015', 8, '2024-11-28', '2024-11-28', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 京都東	0280899-01	森田　照子様	W3F5J	TAC共同	11月	\"29日\nAM\"\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '28/11/2024 18:19', '1', '', '28/11/2024 16:18', NULL, NULL, NULL, 1, '2024-11-28 16:19', 'tranvinh.loc'),
(96, 'tuyen', 8, '2024-11-28', '2024-11-28', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 京都東	0280899-01	\r\nPJ Name: 森田　照子様	W3F5J	TAC共同', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '28/11/2024 18:27', '1', '', '28/11/2024 16:23', NULL, NULL, NULL, 1, '2024-11-28 16:27', 'tranvinh.loc'),
(97, 'duyhoang', 17, '2024-11-28', '2024-11-28', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 技術開発部\r\nPJ Name: フォルターブⅢ\r\nYêu cầu từ: Lam Duy Nguyen', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '29/11/2024 09:21', '1', '', '29/11/2024 07:54', NULL, NULL, NULL, 1, '2024-11-29 09:21', 'minhthomonly'),
(98, 'vantoan', 17, '2024-11-28', '2024-11-28', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 技術開発部\r\nPJ Name: フォルターブⅢ\r\nYêu cầu từ: Lam Duy Nguyen', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '29/11/2024 09:21', '1', '', '29/11/2024 08:13', NULL, NULL, NULL, 1, '2024-11-29 09:21', 'minhthomonly'),
(99, 'khang', 17, '2024-12-03', '2024-12-03', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0280715-01\r\nPJ Name: 江東支店　寺島　和重様\r\nLý do: Để giao kịp những bản vẽ cho kết cấu ngày 5/12', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/12/2024 17:08', '1', 'chỉnh lại loại hình: được chỉ thị', '03/12/2024 15:32', 'minhthomonly', '10/12/2024 15:17', 'Dinh Minh Thom', 4, '2024-12-10 17:08', 'minhthomonly'),
(100, 'khang', 17, '2024-12-04', '2024-12-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280715-01\r\nPJ Name: 江東　寺島　和重　様\r\nLý do: tăng ca để hoàn thành bản vẽ giao kết cấu.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/12/2024 17:08', '1', 'chỉnh lại loại hình: được chỉ thị', '04/12/2024 15:42', 'minhthomonly', '10/12/2024 15:17', 'Dinh Minh Thom', 4, '2024-12-10 17:08', 'minhthomonly'),
(101, 'khang', 17, '2024-12-05', '2024-12-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 3513 Hợp đồng RC 3F\r\nPJ Name: 京都	株式会社　上村様\r\nLý do: Làm hợp đồng RC', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/12/2024 17:08', '1', 'chỉnh lại loại hình: được chỉ thị', '05/12/2024 16:20', 'minhthomonly', '10/12/2024 15:17', 'Dinh Minh Thom', 5, '2024-12-10 17:08', 'minhthomonly'),
(102, 'cong', 11, '2024-12-10', '2024-12-10', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.:  川崎東支店  0274101-01\r\nPJ Name: ĐOÀN HỮU THÀNH\r\nYêu cầu từ: làm kịp tiến độ ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/12/2024 16:56', '1', '', '10/12/2024 14:55', NULL, NULL, NULL, 1, '2024-12-10 14:56', 'tranvinh.loc'),
(103, 'khang', 17, '2024-12-10', '2024-12-10', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282181-01\r\nPJ Name: 京都	株式会社　上村様\r\nLý do: Tăng ca để kịp giao hợp đồng trong sáng mai.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/12/2024 17:08', '1', 'chỉnh lại loại hình: được chỉ thị', '10/12/2024 14:58', 'minhthomonly', '10/12/2024 15:17', 'Dinh Minh Thom', 4, '2024-12-10 17:08', 'minhthomonly'),
(104, 'khang', 17, '2024-12-12', '2024-12-12', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0282040-01\r\nPJ Name: 川崎　鈴木　欽二　様\"\r\nLý do: Sửa bản vẽ sớm gửi thiết bị', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '12/12/2024 14:57', '1', '', '12/12/2024 14:48', NULL, NULL, NULL, 1, '2024-12-12 14:57', 'minhthomonly'),
(105, 'tuyet2015', 8, '2024-12-12', '2024-12-12', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 苫小牧千歳	0281190-01	村上　勝様01棟\r\n苫小牧千歳	0281190-02	村上　勝様02棟\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/12/2024 18:17', '1', '', '12/12/2024 16:16', NULL, NULL, NULL, 1, '2024-12-12 16:17', 'tranvinh.loc'),
(106, 'dinh', 8, '2024-12-12', '2024-12-12', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８１１９０－０１・０２８１１９０－０２\r\nPJ Name: 村上　勝様集合住宅新築工事\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/12/2024 18:18', '1', '', '12/12/2024 16:18', NULL, NULL, NULL, 1, '2024-12-12 16:18', 'tranvinh.loc'),
(107, 'khanh', 8, '2024-12-12', '2024-12-12', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281190-01 + 0281190-02\r\nPJ Name: 村上　勝様01棟 + 村上　勝様02棟\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '12/12/2024 18:19', '1', '', '12/12/2024 16:18', NULL, NULL, NULL, 1, '2024-12-12 16:19', 'tranvinh.loc'),
(108, 'khang', 17, '2024-12-16', '2024-12-16', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0278655-01\r\nPJ Name: 流通開発千葉  山田　武代様\r\nLý do: Vẽ bản chung và kentou cho tantou kiểm tra xâm lấn', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '16/12/2024 15:55', '1', '', '16/12/2024 15:51', NULL, NULL, NULL, 1, '2024-12-16 15:55', 'minhthomonly'),
(109, 'khang', 17, '2024-12-18', '2024-12-18', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282127-01\r\nPJ Name: 小山　塩田幸男様　障がい者GH\r\nLý do: Tăng ca để kịp giao bản vẽ cho kết cấu.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/12/2024 14:52', '1', '', '18/12/2024 14:47', NULL, NULL, NULL, 1, '2024-12-18 14:52', 'minhthomonly'),
(110, 'duykhanh', 17, '2024-12-18', '2024-12-18', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0000000-01\r\nPJ Name: 豊橋　伊東　興一 様WRC4F4JS　特注契約\r\nYêu cầu từ: Nguyễn Thị Thu Quyên', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '18/12/2024 16:54', '1', '', '18/12/2024 16:50', NULL, NULL, NULL, 1, '2024-12-18 16:54', 'minhthomonly'),
(111, 'khang', 17, '2024-12-20', '2024-12-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 05112014-01\r\nPJ Name: 練馬  加藤　直義　様\r\nLý do: Tăng ca làm hợp đồng giao gấp', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/12/2024 15:22', '1', '', '20/12/2024 15:21', NULL, NULL, NULL, 1, '2024-12-20 15:22', 'minhthomonly'),
(112, 'long', 14, '2024-12-20', '2024-12-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20241212\r\nPJ Name:千葉興銀船橋支店建替え計画\r\nYêu cầu từ: Leader - Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '20/12/2024 17:11', '1', '', '20/12/2024 16:20', NULL, NULL, NULL, 1, '2024-12-20 17:11', 'dat'),
(113, 'khang', 17, '2024-12-23', '2024-12-23', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282582-01\r\nPJ Name: 所沢	萩谷　和彦　様\r\nLý do : Giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/12/2024 15:12', '1', '', '23/12/2024 14:40', NULL, NULL, NULL, 1, '2024-12-23 15:12', 'minhthomonly'),
(114, 'bi', 14, '2024-12-24', '2024-12-24', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20241021\r\nPJ Name: 文京区音羽計画\r\nYêu cầu từ: Tăng ca kịp tiến độ.', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '24/12/2024 15:44', '1', '', '24/12/2024 15:16', NULL, NULL, NULL, 1, '2024-12-24 15:44', 'dat'),
(115, 'khang', 17, '2024-12-24', '2024-12-24', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281762-01\r\nPJ Name: 大阪りんくう	三浦　剛　様\r\nLý do: Giao kết cấu đúng hạn', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '24/12/2024 16:30', '1', '', '24/12/2024 16:11', NULL, NULL, NULL, 1, '2024-12-24 16:30', 'minhthomonly'),
(116, 'bi', 14, '2024-12-25', '2024-12-25', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20241021\r\nPJ Name: 文京区音羽計画\r\nYêu cầu từ: Kịp tiến độ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '25/12/2024 17:12', '1', '', '25/12/2024 16:11', NULL, NULL, NULL, 1, '2024-12-25 17:12', 'dat'),
(117, 'duykhanh', 17, '2024-12-26', '2024-12-26', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No: 0280698-01\r\nPJ Name: 京都　堅田　和弘様W3F4J　特注　\r\nYêu cầu từ: Nguyễn Thị Thu Quyên', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/12/2024 17:17', '1', '', '26/12/2024 15:31', NULL, NULL, NULL, 1, '2024-12-26 17:17', 'minhthomonly'),
(118, 'duykhanh', 17, '2025-01-02', '2025-01-02', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0280698-01\r\nPJ Name: 京都　堅田　和弘様W3F4J　特注\r\nYêu cầu từ: Nguyễn Thị Thu Quyên', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '03/01/2025 07:57', '1', '', '03/01/2025 07:24', NULL, NULL, NULL, 1, '2025-01-03 07:57', 'minhthomonly'),
(119, 'khang', 17, '2025-01-08', '2025-01-08', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282558-01\r\nPJ Name: 練馬	加藤　直義　様\r\nLý do: hợp đồng giao gấp', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '09/01/2025 09:24', '1', '', '08/01/2025 14:05', NULL, NULL, NULL, 1, '2025-01-09 09:24', 'minhthomonly'),
(120, 'khang', 17, '2025-01-09', '2025-01-09', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282689-01\r\nPJ Name: 墨田	株式会社 加藤製作所様\r\nLý do : giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '09/01/2025 09:24', '1', '', '08/01/2025 14:06', NULL, NULL, NULL, 1, '2025-01-09 09:24', 'minhthomonly'),
(121, 'khang', 17, '2025-01-10', '2025-01-10', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282689-01\r\nPJ Name: 墨田	株式会社 加藤製作所様\r\nLý do: giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '09/01/2025 09:24', '1', '', '08/01/2025 14:08', NULL, NULL, NULL, 1, '2025-01-09 09:24', 'minhthomonly'),
(122, 'khang', 17, '2025-01-13', '2025-01-13', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282689-01\r\nPJ Name: 墨田	株式会社 加藤製作所様\r\nLý do: Giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/01/2025 15:38', '1', '', '13/01/2025 15:31', NULL, NULL, NULL, 1, '2025-01-13 15:38', 'minhthomonly'),
(123, 'khang', 17, '2025-01-14', '2025-01-14', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282558-01\r\nPJ Name: 練馬	加藤　直義　様\r\nLý do: Giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/01/2025 15:38', '1', '', '13/01/2025 15:32', NULL, NULL, NULL, 1, '2025-01-13 15:38', 'minhthomonly'),
(124, 'tam', 8, '2025-01-13', '2025-01-13', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 奈良 0278488-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'minhthanhonly', 'Dinh Minh Thanh', 'Tran Vinh Loc', '14/01/2025 08:12', '1', '', '13/01/2025 16:05', NULL, NULL, NULL, 2, '2025-01-14 08:13', 'minhthanhonly'),
(125, 'tuyet2015', 8, '2025-01-13', '2025-01-13', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 上尾	0281748-01	黒澤　三津子様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/01/2025 18:21', '1', '', '13/01/2025 16:20', NULL, NULL, NULL, 1, '2025-01-13 16:21', 'tranvinh.loc'),
(126, 'khanh', 8, '2025-01-13', '2025-01-13', '17:00', '18:30', 'False', 'Được yêu cầu', 'PJ No.: 0281748-01\r\nPJ Name: 黒澤　三津子様	W2F4J	TAC集合', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/01/2025 18:53', '1', '', '13/01/2025 16:50', NULL, NULL, NULL, 1, '2025-01-13 16:53', 'tranvinh.loc'),
(127, 'tuyen', 8, '2025-01-13', '2025-01-13', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 上尾	0281748-01\r\nPJ Name: 黒澤　三津子様	W2F4J	TAC集合\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/01/2025 18:53', '1', '', '13/01/2025 16:51', NULL, NULL, NULL, 1, '2025-01-13 16:53', 'tranvinh.loc'),
(128, 'dinh', 8, '2025-01-13', '2025-01-13', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0278488-01\r\nPJ Name: 山谷　裕紀様\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/01/2025 18:53', '1', '', '13/01/2025 16:51', NULL, NULL, NULL, 1, '2025-01-13 16:53', 'tranvinh.loc'),
(129, 'khang', 17, '2025-01-15', '2025-01-15', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0278655-01\r\nPJ Name: 流通開発千葉	山田　武代様\r\nLý do: Tăng ca làm bản vẽ giao kết cấu', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '15/01/2025 15:46', '1', '', '15/01/2025 11:24', NULL, NULL, NULL, 1, '2025-01-15 15:46', 'minhthomonly'),
(130, 'khang', 17, '2025-01-17', '2025-01-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280716-01\r\nPJ Name: 横須賀	大谷　剛正　様\r\nLý do: Khách hàng thay đổi phương án', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/01/2025 15:14', '1', '', '17/01/2025 10:22', NULL, NULL, NULL, 1, '2025-01-17 15:14', 'minhthomonly'),
(131, 'khang', 17, '2025-01-20', '2025-01-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281707-01\r\nPJ Name: 江戸川	一般社団法人　小林　章　様\r\nLý do: Giao bản vẽ kết cấu trước Tết ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '20/01/2025 15:47', '1', '', '20/01/2025 15:47', NULL, NULL, NULL, 1, '2025-01-20 15:47', 'minhthomonly'),
(132, 'tuyet2015', 8, '2025-01-20', '2025-01-20', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 守谷	0280610-01	村越　誠一様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/01/2025 18:53', '1', '', '20/01/2025 16:52', NULL, NULL, NULL, 1, '2025-01-20 16:53', 'tranvinh.loc'),
(133, 'tam', 8, '2025-01-20', '2025-01-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 守谷 0280610-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/01/2025 18:54', '1', '', '20/01/2025 16:53', NULL, NULL, NULL, 1, '2025-01-20 16:54', 'tranvinh.loc'),
(134, 'tuyen', 8, '2025-01-20', '2025-01-20', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大阪りんくう　0281762-01	\r\nPJ Name: 三浦　剛　W2F5J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/01/2025 18:55', '1', '', '20/01/2025 16:54', NULL, NULL, NULL, 1, '2025-01-20 16:55', 'tranvinh.loc'),
(135, 'dinh', 8, '2025-01-20', '2025-01-20', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0280610-01\r\nPJ Name: 村越　誠一様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '20/01/2025 18:59', '1', '', '20/01/2025 16:58', NULL, NULL, NULL, 1, '2025-01-20 16:59', 'tranvinh.loc'),
(136, 'khanh', 8, '2025-01-20', '2025-01-20', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0280610-01\r\nPJ Name: 村越　誠一様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/01/2025 09:11', '1', '', '20/01/2025 17:05', NULL, NULL, NULL, 1, '2025-01-21 07:11', 'tranvinh.loc'),
(137, 'khang', 17, '2025-01-21', '2025-01-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281707-01\r\nPJ Name: 江戸川	一般社団法人　小林　章　様\r\nLý do: Giao bản vẽ cho kết cấu trước Tết', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/01/2025 17:06', '1', '', '21/01/2025 14:37', NULL, NULL, NULL, 1, '2025-01-21 17:06', 'minhthomonly'),
(138, 'tam', 8, '2025-01-21', '2025-01-21', '17:00', '18:30', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 守谷 0280610-01,名古屋西 0281928-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/01/2025 18:03', '1', '', '21/01/2025 16:03', NULL, NULL, NULL, 1, '2025-01-21 16:03', 'tranvinh.loc'),
(139, 'khanh', 8, '2025-01-21', '2025-01-21', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0282424-01\r\nPJ Name: 松戸支店＿東風匠様', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/01/2025 18:05', '1', '', '21/01/2025 16:04', NULL, NULL, NULL, 1, '2025-01-21 16:05', 'tranvinh.loc'),
(140, 'tuyen', 8, '2025-01-21', '2025-01-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 名古屋西	0281928-01\r\nPJ Name: 吉田　哲郎様01棟　W2F1J　TAC戸建て\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/01/2025 18:08', '1', '', '21/01/2025 16:07', NULL, NULL, NULL, 1, '2025-01-21 16:08', 'tranvinh.loc'),
(141, 'tuyet2015', 8, '2025-01-21', '2025-01-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 鳥居		松戸支店＿東風匠様	W3F6J\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/01/2025 18:09', '1', '', '21/01/2025 16:08', NULL, NULL, NULL, 1, '2025-01-21 16:09', 'tranvinh.loc'),
(142, 'dinh', 8, '2025-01-21', '2025-01-21', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0282424-01\r\nPJ Name: 村越　誠一様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/01/2025 18:19', '1', '', '21/01/2025 16:19', NULL, NULL, NULL, 1, '2025-01-21 16:19', 'tranvinh.loc'),
(143, 'khang', 17, '2025-01-22', '2025-01-22', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0278655-01\r\nPJ Name: 流通開発千葉	山田　武代様\r\nLý do: Sửa giao bản vẽ cho kết cấu trước Tết', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '22/01/2025 15:29', '1', '', '22/01/2025 15:15', NULL, NULL, NULL, 1, '2025-01-22 15:29', 'minhthomonly'),
(144, 'khanh', 8, '2025-01-22', '2025-01-22', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281928-02\r\nPJ Name: 吉田　哲郎様02棟\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 18:10', '1', '', '22/01/2025 16:09', NULL, NULL, NULL, 1, '2025-01-22 16:10', 'tranvinh.loc'),
(145, 'tuyet2015', 8, '2025-01-22', '2025-01-22', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 鳥居		松戸支店＿東風匠様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 18:11', '1', '', '22/01/2025 16:10', NULL, NULL, NULL, 1, '2025-01-22 16:11', 'tranvinh.loc'),
(146, 'tam', 8, '2025-01-22', '2025-01-22', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 名古屋西 0281928-02\r\nYêu cầu từ: Huynh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 18:10', '1', '', '22/01/2025 16:10', NULL, NULL, NULL, 1, '2025-01-22 16:10', 'tranvinh.loc'),
(147, 'dinh', 8, '2025-01-22', '2025-01-22', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0281314-01\r\nPJ Name: 笹川　隆邦様\r\n', 'tranvinh.loc', 'dinh', 'Tran Huu Dinh', 'Tran Vinh Loc', '22/01/2025 16:10', '3', '', '22/01/2025 16:10', NULL, NULL, NULL, 1, '2025-01-22 16:10', 'dinh'),
(148, 'dinh', 8, '2025-01-22', '2025-01-22', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281314-01\r\nPJ Name: 笹川　隆邦様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 18:11', '1', '', '22/01/2025 16:11', NULL, NULL, NULL, 1, '2025-01-22 16:11', 'tranvinh.loc'),
(149, 'tuyen', 8, '2025-01-22', '2025-01-22', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 名古屋西	0281928-02	\r\nPJ Name: 吉田　哲郎様02棟　W2F1J　TAC戸建て\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '22/01/2025 18:14', '1', '', '22/01/2025 16:14', NULL, NULL, NULL, 1, '2025-01-22 16:14', 'tranvinh.loc'),
(150, 'khang', 17, '2025-01-23', '2025-01-23', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0278655-01\r\nPJ Name: 流通開発千葉	山田　武代様\r\nLý do: Sửa bản vẽ giao kết cấu trước Tết.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '23/01/2025 15:26', '1', '', '23/01/2025 15:24', NULL, NULL, NULL, 1, '2025-01-23 15:26', 'minhthomonly'),
(151, 'khang', 17, '2025-02-04', '2025-02-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281707-01\r\nPJ Name: 江戸川	一般社団法人　小林　章　様\r\nLý do: Kịp giao tổng vào cuối tuần', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '04/02/2025 15:21', '1', '', '04/02/2025 10:23', NULL, NULL, NULL, 1, '2025-02-04 15:21', 'minhthomonly');
INSERT INTO `groupware_overtime` (`id`, `userid`, `group_id`, `date_start`, `date_end`, `time_start`, `time_end`, `allday`, `offtype`, `reason`, `confirm_userid`, `confirm_real_userid`, `confirm_real_name`, `confirm_name`, `confirm_date`, `status`, `comment`, `created_time`, `req_edit_by`, `req_edit_time`, `req_edit_name`, `version`, `update_date`, `update_by`) VALUES
(152, 'tuyet2015', 8, '2025-02-04', '2025-02-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 上尾	0281648-01	川田　進様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/02/2025 17:08', '1', '', '04/02/2025 15:07', NULL, NULL, NULL, 1, '2025-02-04 15:08', 'tranvinh.loc'),
(153, 'khanh', 8, '2025-02-04', '2025-02-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282614-01\r\nPJ Name: 新潟　能勢　美砂江様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/02/2025 17:11', '1', '', '04/02/2025 15:10', NULL, NULL, NULL, 1, '2025-02-04 15:11', 'tranvinh.loc'),
(154, 'tuyen', 8, '2025-02-04', '2025-02-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 新潟	0282614-01	\r\nPJ Name: 能勢　美砂江様	W2F1J	新規修正　2月　5日\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/02/2025 17:14', '1', '', '04/02/2025 15:13', NULL, NULL, NULL, 1, '2025-02-04 15:14', 'tranvinh.loc'),
(155, 'tam', 8, '2025-02-04', '2025-02-04', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 新潟 0282614-01, 大和 0281981-01\r\nYêu cầu từ: Huynh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/02/2025 17:14', '1', '', '04/02/2025 15:13', NULL, NULL, NULL, 1, '2025-02-04 15:14', 'tranvinh.loc'),
(156, 'khang', 17, '2025-02-05', '2025-02-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280036-01\r\nPJ Name: 名古屋 奥村　哲　様\r\nLý do: Giao bản vẽ xin phép', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '05/02/2025 15:38', '1', '', '05/02/2025 14:54', NULL, NULL, NULL, 1, '2025-02-05 15:38', 'minhthomonly'),
(157, 'tuyet2015', 8, '2025-02-05', '2025-02-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 京都	0279989-01	澤井　由延様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 18:10', '1', '', '05/02/2025 16:09', NULL, NULL, NULL, 1, '2025-02-05 16:10', 'tranvinh.loc'),
(158, 'khanh', 8, '2025-02-05', '2025-02-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281981-01\r\nPJ Name: 大塚　しげ子様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 18:10', '1', '', '05/02/2025 16:09', NULL, NULL, NULL, 1, '2025-02-05 16:10', 'tranvinh.loc'),
(159, 'dinh', 8, '2025-02-05', '2025-02-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0279989-01・ 0279892-01\r\nPJ Name: 澤井　由延様・伊藤ヤヨイ様（旧：谷さゆり）\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 18:10', '1', '', '05/02/2025 16:10', NULL, NULL, NULL, 1, '2025-02-05 16:10', 'tranvinh.loc'),
(160, 'tam', 8, '2025-02-05', '2025-02-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 京都 0279989-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 18:12', '1', '', '05/02/2025 16:11', NULL, NULL, NULL, 1, '2025-02-05 16:12', 'tranvinh.loc'),
(161, 'tuyen', 8, '2025-02-05', '2025-02-05', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 京都	0279989-01	\r\nPJ Name: 澤井　由延様	W3F4J	\r\nPJ No.: 豊川	 0279892-01	\r\nPJ Name: 伊藤ヤヨイ様（旧：谷さゆり）	W2F3J	', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '05/02/2025 18:15', '1', '', '05/02/2025 16:15', NULL, NULL, NULL, 1, '2025-02-05 16:15', 'tranvinh.loc'),
(162, 'khang', 17, '2025-02-07', '2025-02-07', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282627-01\r\nPJ Name: 足立  寳谷 徹朗様\r\nLý do: Giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/02/2025 10:03', '1', '', '07/02/2025 14:27', NULL, NULL, NULL, 1, '2025-02-10 10:03', 'minhthomonly'),
(163, 'khang', 17, '2025-02-10', '2025-02-10', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282627-01\r\nPJ Name: 足立  寳谷 徹朗様\r\nYêu cầu từ: Giao gấp hợp đồng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '10/02/2025 10:03', '1', '', '07/02/2025 14:28', NULL, NULL, NULL, 1, '2025-02-10 10:03', 'minhthomonly'),
(164, 'tuyet2015', 8, '2025-02-10', '2025-02-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 大和	0281981-01	大塚　しげ子様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/02/2025 17:42', '1', '', '10/02/2025 15:41', NULL, NULL, NULL, 1, '2025-02-10 15:42', 'tranvinh.loc'),
(165, 'tam', 8, '2025-02-10', '2025-02-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 大和 0281981-01\r\nYêu cầu từ: Huynh Thi Anh Tuyet', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/02/2025 17:43', '1', '', '10/02/2025 15:42', NULL, NULL, NULL, 1, '2025-02-10 15:43', 'tranvinh.loc'),
(166, 'dinh', 8, '2025-02-10', '2025-02-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８１９８１－０１\r\nPJ Name: 大塚　しげ子様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/02/2025 17:43', '1', '', '10/02/2025 15:43', NULL, NULL, NULL, 1, '2025-02-10 15:43', 'tranvinh.loc'),
(167, 'tuyen', 8, '2025-02-10', '2025-02-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 大和	0281981-01	\r\nPJ Name: 大塚　しげ子様	W3F3J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/02/2025 17:44', '1', '', '10/02/2025 15:43', NULL, NULL, NULL, 1, '2025-02-10 15:44', 'tranvinh.loc'),
(168, 'khanh', 8, '2025-02-10', '2025-02-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 0281981-01\r\nPJ Name: 大塚　しげ子様', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '10/02/2025 17:54', '1', '', '10/02/2025 15:54', NULL, NULL, NULL, 1, '2025-02-10 15:54', 'tranvinh.loc'),
(169, 'khang', 17, '2025-02-11', '2025-02-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282627-01\r\nPJ Name: 足立  寳谷　徹朗様\r\nLý do: Giao hợp đồng vào cuối tuần', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/02/2025 15:14', '1', '', '11/02/2025 15:12', NULL, NULL, NULL, 1, '2025-02-11 15:14', 'minhthomonly'),
(170, 'khang', 17, '2025-02-12', '2025-02-12', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282627-01\r\nPJ Name: 足立  寳谷　徹朗様\r\nYêu cầu từ: Giao hợp đồng cuối tuần', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/02/2025 15:14', '1', '', '11/02/2025 15:13', NULL, NULL, NULL, 1, '2025-02-11 15:14', 'minhthomonly'),
(171, 'tuyet2015', 8, '2025-02-11', '2025-02-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 江戸川	0281707-01	一般社団法人小林章様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/02/2025 17:57', '1', '', '11/02/2025 15:57', NULL, NULL, NULL, 1, '2025-02-11 15:57', 'tranvinh.loc'),
(172, 'dinh', 8, '2025-02-11', '2025-02-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８１７０７－０１\r\nPJ Name: 一般社団法人　小林　章様　', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/02/2025 17:57', '1', '', '11/02/2025 15:57', NULL, NULL, NULL, 1, '2025-02-11 15:57', 'tranvinh.loc'),
(173, 'tam', 8, '2025-02-11', '2025-02-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 江戸川 0281707-01\r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/02/2025 17:59', '1', '', '11/02/2025 15:59', NULL, NULL, NULL, 1, '2025-02-11 15:59', 'tranvinh.loc'),
(174, 'khanh', 8, '2025-02-11', '2025-02-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0281707-01\r\nPJ Name: 一般社団法人小林章様\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/02/2025 18:00', '1', '', '11/02/2025 15:59', NULL, NULL, NULL, 1, '2025-02-11 16:00', 'tranvinh.loc'),
(175, 'tuyen', 8, '2025-02-11', '2025-02-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 江戸川	0281707-01	\r\nPJ Name: 一般社団法人小林章様	W3F2J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '11/02/2025 18:00', '1', '', '11/02/2025 15:59', NULL, NULL, NULL, 1, '2025-02-11 16:00', 'tranvinh.loc'),
(176, 'khang', 17, '2025-02-13', '2025-02-13', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280036-01\r\nPJ Name: 名古屋  奥村　哲　様\r\nLý do : Giao bản vẽ sửa xin phép', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '13/02/2025 16:26', '1', '', '13/02/2025 16:03', NULL, NULL, NULL, 1, '2025-02-13 16:26', 'minhthomonly'),
(177, 'tuyet2015', 8, '2025-02-17', '2025-02-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大阪りんくう	 0281214-01	山出 啓子様\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/02/2025 18:07', '1', '', '17/02/2025 16:07', NULL, NULL, NULL, 1, '2025-02-17 16:07', 'tranvinh.loc'),
(178, 'khanh', 8, '2025-02-17', '2025-02-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.:  0281214-01\r\nPJ Name: 山出 啓子様', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/02/2025 18:07', '1', '', '17/02/2025 16:07', NULL, NULL, NULL, 1, '2025-02-17 16:07', 'tranvinh.loc'),
(179, 'dinh', 8, '2025-02-17', '2025-02-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: ０２８１２１４－０１\r\nPJ Name: 山出　啓子様　共同住宅新築工事 \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/02/2025 18:08', '1', '', '17/02/2025 16:08', NULL, NULL, NULL, 1, '2025-02-17 16:08', 'tranvinh.loc'),
(180, 'tuyen', 8, '2025-02-17', '2025-02-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大阪りんくう　　0281214-01	\r\nPJ Name: 山出 啓子様	W3F3J	TAC共同\r\n', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/02/2025 18:09', '1', '', '17/02/2025 16:08', NULL, NULL, NULL, 1, '2025-02-17 16:09', 'tranvinh.loc'),
(181, 'tam', 8, '2025-02-17', '2025-02-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大阪りんくう 0281214-01\r\nPJ Name: \r\nYêu cầu từ: Huỳnh Thị Ánh Tuyết', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '17/02/2025 18:09', '1', '', '17/02/2025 16:09', NULL, NULL, NULL, 1, '2025-02-17 16:09', 'tranvinh.loc'),
(182, 'khang', 17, '2025-02-17', '2025-02-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280430-01\r\nPJ Name: 船橋  宮本　真一　様\r\nLý do: Giao bản vẽ cho kết cấu đúng hạn.', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/02/2025 17:55', '1', '', '17/02/2025 16:49', NULL, NULL, NULL, 1, '2025-02-17 17:55', 'minhthomonly'),
(183, 'hanhthach', 20, '2025-02-17', '2025-02-17', '17:00', '19:22', 'False', 'Tự nguyện', 'PJ No.: \r\nPJ Name: 江東	0280556-01\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/02/2025 09:32', '1', '', '18/02/2025 07:29', NULL, NULL, NULL, 1, '2025-02-18 07:32', 'tranvinh.loc'),
(184, 'duykhanh', 17, '2025-02-19', '2025-02-19', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282041-01\r\nPJ Name: 柏　山口　愼一様\r\nYêu cầu từ: Nguyễn Thị Thu Quyên', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '19/02/2025 14:47', '1', '', '19/02/2025 14:42', NULL, NULL, NULL, 1, '2025-02-19 14:47', 'minhthomonly'),
(185, 'khang', 17, '2025-02-21', '2025-02-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0282582-01\r\nPJ Name: 所沢  萩谷　和彦　様\r\nLý do: Gửi bản vẽ cho kết cấu đúng hạn', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/02/2025 15:05', '1', '', '21/02/2025 14:35', NULL, NULL, NULL, 1, '2025-02-21 15:05', 'minhthomonly'),
(186, 'khang', 17, '2025-02-24', '2025-02-24', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0283262-01\r\nPJ Name: 枚方南  大村　義文様\r\nLý do: Tổng hợp giao hợp đồng sớm', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '21/02/2025 15:05', '1', '', '21/02/2025 14:37', NULL, NULL, NULL, 1, '2025-02-21 15:05', 'minhthomonly'),
(187, 'hanhthach', 20, '2025-02-21', '2025-02-21', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 和歌山	0281943-01\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '21/02/2025 17:24', '1', '', '21/02/2025 15:24', NULL, NULL, NULL, 1, '2025-02-21 15:24', 'tranvinh.loc'),
(188, 'long', 14, '2025-02-26', '2025-02-26', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20250217\r\nPJ Name: 渋谷区渋谷３丁目計画\r\nYêu cầu từ: Leader - Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '26/02/2025 07:59', '1', '', '26/02/2025 07:49', NULL, NULL, NULL, 1, '2025-02-26 07:59', 'dat'),
(189, 'ly', 14, '2025-02-26', '2025-02-26', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250219\r\nPJ Name: YUWA設計　ＭＫＧ亀戸マンション\r\nYêu cầu từ: yêu cầu từ leader- kịp tiến độ', 'dat', 'minhthomonly', 'Dinh Minh Thom', 'Tran Vinh Dat', '26/02/2025 16:35', '1', '', '26/02/2025 14:26', NULL, NULL, NULL, 1, '2025-02-26 16:35', 'minhthomonly'),
(190, 'khang', 17, '2025-02-26', '2025-02-26', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280715-01\r\nPJ Name: 江東  寺島　和重　様\r\nLý do: Thay đổi nhà chủ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/02/2025 16:35', '1', '', '26/02/2025 14:53', NULL, NULL, NULL, 1, '2025-02-26 16:35', 'minhthomonly'),
(191, 'khang', 17, '2025-02-27', '2025-02-27', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280715-01\r\nPJ Name: 江東  寺島　和重　様\r\nLý do: Thay đổi nhà chủ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/02/2025 16:35', '1', '', '26/02/2025 14:53', NULL, NULL, NULL, 1, '2025-02-26 16:35', 'minhthomonly'),
(192, 'khang', 17, '2025-02-28', '2025-02-28', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0280715-01\r\nPJ Name: 江東  寺島　和重　様\r\nLý do: Thay đổi nhà chủ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '26/02/2025 16:35', '1', '', '26/02/2025 14:54', NULL, NULL, NULL, 1, '2025-02-26 16:35', 'minhthomonly'),
(193, 'vi', 14, '2025-02-26', '2025-02-26', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250219\r\nPJ Name: YUWA設計　ＭＫＧ亀戸マンション　10F1JT　MKG Kameido\r\nYêu cầu từ: leader- kịp deadline', 'dat', 'minhthomonly', 'Dinh Minh Thom', 'Tran Vinh Dat', '26/02/2025 16:35', '1', '', '26/02/2025 15:45', NULL, NULL, NULL, 1, '2025-02-26 16:35', 'minhthomonly'),
(194, 'hanhthach', 20, '2025-02-26', '2025-02-26', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 和歌山	0281943-01\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '26/02/2025 18:19', '1', '', '26/02/2025 16:18', NULL, NULL, NULL, 1, '2025-02-26 16:19', 'tranvinh.loc'),
(195, 'bi', 14, '2025-02-28', '2025-02-28', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250217\r\nPJ Name: 　渋谷区渋谷３丁目計画　13F4JTS　\r\nYêu cầu từ: Kịp tiến độ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '28/02/2025 17:01', '1', '', '28/02/2025 17:00', NULL, NULL, NULL, 1, '2025-02-28 17:01', 'dat'),
(196, 'thiet', 14, '2025-02-28', '2025-02-28', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250217\r\nPJ Name: 渋谷区渋谷３丁目計画　13F4JTS\r\nYêu cầu từ: kịp tiến độ ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '03/03/2025 09:48', '1', '', '28/02/2025 17:06', NULL, NULL, NULL, 1, '2025-03-03 09:48', 'dat'),
(197, 'hanhthach', 20, '2025-03-04', '2025-03-04', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 流通開発神戸\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/03/2025 18:22', '1', '', '04/03/2025 16:19', NULL, NULL, NULL, 1, '2025-03-04 16:22', 'tranvinh.loc'),
(198, 'lien', 9, '2025-03-04', '2025-03-04', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 江東	0280556-01	室　英延様	W3F5J\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/03/2025 18:23', '1', '', '04/03/2025 16:22', NULL, NULL, NULL, 1, '2025-03-04 16:23', 'tranvinh.loc'),
(199, 'ducanh', 9, '2025-03-04', '2025-03-04', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 江東支店\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/03/2025 18:25', '1', '', '04/03/2025 16:24', NULL, NULL, NULL, 1, '2025-03-04 16:25', 'tranvinh.loc'),
(200, 'vankhanh', 9, '2025-03-04', '2025-03-04', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 流通開発神戸	0282673-01\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '04/03/2025 18:37', '1', '', '04/03/2025 16:34', 'tranvinh.loc', '04/03/2025 18:35', 'Tran Vinh Loc', 3, '2025-03-04 16:37', 'tranvinh.loc'),
(201, 'vi', 14, '2025-03-07', '2025-03-07', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 関目３丁目プロジェクト　10F4JT　Sekime 3 Project (OSAKA)\r\nYêu cầu từ: leader-kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '07/03/2025 16:28', '1', '', '07/03/2025 16:26', NULL, NULL, NULL, 1, '2025-03-07 16:28', 'dat'),
(202, 'long', 14, '2025-03-07', '2025-03-07', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 関目３丁目プロジェクト\r\nYêu cầu từ: Leader - Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '07/03/2025 16:28', '1', '', '07/03/2025 16:27', NULL, NULL, NULL, 1, '2025-03-07 16:28', 'dat'),
(203, 'dat', 14, '2025-03-07', '2025-03-07', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 福栄商事株式会社\r\nYêu cầu từ: cho kịp tiến độ công việc', 'dat', NULL, NULL, 'Tran Vinh Dat', '07/03/2025 16:30', '1', 'Duyệt tự động', '07/03/2025 16:30', NULL, NULL, NULL, 0, '', ''),
(204, 'bi', 14, '2025-03-07', '2025-03-07', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20241021\r\nPJ Name: 文京区音羽計画　\r\nYêu cầu từ: Kịp tiến độ', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '07/03/2025 17:08', '1', '', '07/03/2025 16:40', NULL, NULL, NULL, 1, '2025-03-07 17:08', 'dat'),
(205, 'hoai', 14, '2025-03-07', '2025-03-07', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 関目３丁目プロジェクト\r\nYêu cầu từ: Leader - Kịp dealine', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '07/03/2025 17:08', '1', '', '07/03/2025 16:46', NULL, NULL, NULL, 1, '2025-03-07 17:08', 'dat'),
(206, 'huy', 20, '2025-03-07', '2025-03-07', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 0281591-01\r\nPJ Name: \"大東建託アセットソリューションズ様\n（川崎市宮前区）\"\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/03/2025 18:55', '1', '', '07/03/2025 16:54', NULL, NULL, NULL, 1, '2025-03-07 16:55', 'tranvinh.loc'),
(207, 'vankhanh', 9, '2025-03-07', '2025-03-07', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: 川崎市宮前区  0281591-01\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '07/03/2025 19:06', '1', '', '07/03/2025 17:05', NULL, NULL, NULL, 1, '2025-03-07 17:06', 'tranvinh.loc'),
(208, 'vi', 14, '2025-03-10', '2025-03-10', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 福栄商事株式会社様文京区湯島２丁目計画\r\nYêu cầu từ: leader-kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '10/03/2025 16:01', '1', '', '10/03/2025 15:59', NULL, NULL, NULL, 1, '2025-03-10 16:01', 'dat'),
(209, 'vi', 14, '2025-03-11', '2025-03-11', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 福栄商事株式会社様文京区湯島２丁目計画\r\nYêu cầu từ: leader-kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '10/03/2025 16:01', '1', '', '10/03/2025 16:00', NULL, NULL, NULL, 1, '2025-03-10 16:01', 'dat'),
(210, 'khang', 17, '2025-03-11', '2025-03-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0250927-01\r\nPJ Name: 相模原	  井上　照子様\r\nLý do : Sửa kiện giao khách hàng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/03/2025 15:35', '1', '', '11/03/2025 15:19', NULL, NULL, NULL, 1, '2025-03-11 15:35', 'minhthomonly'),
(211, 'khang', 17, '2025-03-12', '2025-03-12', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0250927-01\r\nPJ Name: 相模原	  井上　照子様\r\nLý do : Sửa kiện giao khách hàng', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '11/03/2025 15:35', '1', '', '11/03/2025 15:19', NULL, NULL, NULL, 1, '2025-03-11 15:35', 'minhthomonly'),
(212, 'hoai', 14, '2025-03-11', '2025-03-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 福栄商事株式会社様文京区湯島２丁目計画\r\nYêu cầu từ: Leader - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '11/03/2025 16:45', '1', '', '11/03/2025 16:44', NULL, NULL, NULL, 1, '2025-03-11 16:45', 'dat'),
(213, 'bi', 14, '2025-03-11', '2025-03-11', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20250217\r\nPJ Name: 渋谷区渋谷３丁目計画\r\nYêu cầu từ: Kip deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '11/03/2025 16:45', '1', '', '11/03/2025 16:45', NULL, NULL, NULL, 1, '2025-03-11 16:45', 'dat'),
(214, 'long', 14, '2025-03-12', '2025-03-12', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20250303\r\nPJ Name: 福栄商事株式会社様文京区湯島２丁目計画\r\nYêu cầu từ: Leader - Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '12/03/2025 10:08', '1', '', '12/03/2025 10:07', NULL, NULL, NULL, 1, '2025-03-12 10:08', 'dat'),
(215, 'hoai', 14, '2025-03-12', '2025-03-12', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 20241118\r\nPJ Name: 東町4丁目計画\r\nYêu cầu từ: Leader - sửa checkback - kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '12/03/2025 16:34', '1', '', '12/03/2025 15:42', NULL, NULL, NULL, 1, '2025-03-12 16:34', 'dat'),
(216, 'bi', 14, '2025-03-12', '2025-03-12', '17:00', '20:00', 'False', 'Được yêu cầu', 'PJ No.: 20250217\r\nPJ Name: 渋谷区渋谷３丁目計画\r\nYêu cầu từ: Kịp deadline', 'dat', 'dat', 'Tran Vinh Dat', 'Tran Vinh Dat', '12/03/2025 16:34', '1', '', '12/03/2025 15:56', NULL, NULL, NULL, 1, '2025-03-12 16:34', 'dat'),
(217, 'cong', 11, '2025-03-13', '2025-03-13', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: （仮称）武蔵中原プロジェクト 新築工事\r\nPJ Name: 流通開発横浜支店\r\nYêu cầu từ: Đoàn Hữu Thành', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '13/03/2025 18:14', '1', '', '13/03/2025 16:12', NULL, NULL, NULL, 1, '2025-03-13 16:14', 'tranvinh.loc'),
(218, 'tuyet2015', 8, '2025-03-14', '2025-03-14', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 大和	0282266-01	中村　英夫様	W3F4J	ＴＡＣ共同\r\nPJ Name: \r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '14/03/2025 18:53', '1', '', '14/03/2025 16:52', NULL, NULL, NULL, 1, '2025-03-14 16:53', 'tranvinh.loc'),
(219, 'khang', 17, '2025-03-17', '2025-03-17', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0250927-01\r\nPJ Name: 相模原  井上　照子様\r\nLý do: Giao kịp tiến độ', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/03/2025 16:04', '1', '', '17/03/2025 16:01', NULL, NULL, NULL, 1, '2025-03-17 16:04', 'minhthomonly'),
(220, 'khang', 17, '2025-03-18', '2025-03-18', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 0283560-01\r\nPJ Name: 京都西  鶴山　昌仁様\r\nLý do: Giao hợp đồng đúng hạn', 'minhthomonly', 'minhthomonly', 'Dinh Minh Thom', 'Dinh Minh Thom', '17/03/2025 16:04', '1', '', '17/03/2025 16:03', NULL, NULL, NULL, 1, '2025-03-17 16:04', 'minhthomonly'),
(221, 'hanhthach', 20, '2025-03-18', '2025-03-18', '17:00', '18:00', 'False', 'Được yêu cầu', 'PJ No.: \r\nPJ Name: 大和	0281859-01\r\nYêu cầu từ: ', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/03/2025 17:33', '1', '', '18/03/2025 15:22', NULL, NULL, NULL, 1, '2025-03-18 15:33', 'tranvinh.loc'),
(222, 'ha', 10, '2025-03-18', '2025-03-18', '17:00', '19:00', 'False', 'Được yêu cầu', 'PJ No.: 274706\r\nPJ Name: 横浜	0276194-01	株式会社 京や不動産様	RC10F5J\r\nYêu cầu từ: Leader nhóm C', 'tranvinh.loc', 'tranvinh.loc', 'Tran Vinh Loc', 'Tran Vinh Loc', '18/03/2025 17:28', '1', '', '18/03/2025 15:28', NULL, NULL, NULL, 1, '2025-03-18 15:28', 'tranvinh.loc');

-- --------------------------------------------------------

--
-- Table structure for table `groupware_project`
--

CREATE TABLE `groupware_project` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `project_parent` int(11) NOT NULL,
  `project_title` mediumtext DEFAULT NULL,
  `project_begin` mediumtext DEFAULT NULL,
  `project_end` mediumtext DEFAULT NULL,
  `project_name` mediumtext DEFAULT NULL,
  `project_progress` int(11) DEFAULT NULL,
  `project_comment` mediumtext DEFAULT NULL,
  `project_date` mediumtext DEFAULT NULL,
  `project_file` mediumtext DEFAULT NULL,
  `public_level` int(11) NOT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_schedule`
--

CREATE TABLE `groupware_schedule` (
  `id` int(11) NOT NULL,
  `schedule_type` int(11) NOT NULL,
  `schedule_title` mediumtext DEFAULT NULL,
  `schedule_name` mediumtext DEFAULT NULL,
  `schedule_comment` mediumtext DEFAULT NULL,
  `schedule_year` int(11) DEFAULT NULL,
  `schedule_month` int(11) DEFAULT NULL,
  `schedule_day` int(11) DEFAULT NULL,
  `schedule_date` mediumtext DEFAULT NULL,
  `schedule_time` mediumtext DEFAULT NULL,
  `schedule_endtime` mediumtext DEFAULT NULL,
  `schedule_allday` mediumtext DEFAULT NULL,
  `schedule_repeat` mediumtext DEFAULT NULL,
  `schedule_everyweek` mediumtext DEFAULT NULL,
  `schedule_everymonth` mediumtext DEFAULT NULL,
  `schedule_begin` mediumtext DEFAULT NULL,
  `schedule_end` mediumtext DEFAULT NULL,
  `schedule_facility` int(11) DEFAULT NULL,
  `schedule_level` int(11) NOT NULL,
  `schedule_group` mediumtext DEFAULT NULL,
  `schedule_user` mediumtext DEFAULT NULL,
  `public_level` int(11) NOT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_schedule`
--

INSERT INTO `groupware_schedule` (`id`, `schedule_type`, `schedule_title`, `schedule_name`, `schedule_comment`, `schedule_year`, `schedule_month`, `schedule_day`, `schedule_date`, `schedule_time`, `schedule_endtime`, `schedule_allday`, `schedule_repeat`, `schedule_everyweek`, `schedule_everymonth`, `schedule_begin`, `schedule_end`, `schedule_facility`, `schedule_level`, `schedule_group`, `schedule_user`, `public_level`, `public_group`, `public_user`, `edit_level`, `edit_group`, `edit_user`, `owner`, `editor`, `created`, `updated`) VALUES
(32, 0, 'lich buoi sang', 'Trần Vĩnh Lộc', 'Tinh Toan ', 2011, 10, 10, '2011-10-10', '09:30', '13:30', '', '', '', '', '', '', 0, 1, '', '', 0, '', '', 0, '', '', 'tranvinh.loc', NULL, '2011-10-10 09:46:44', NULL),
(30, 0, 'Test', 'admin', 'Edit caily group website', 2011, 9, 28, '2011-09-28', '08:00', '12:50', '', '', '', '', '', '', 0, 1, '', '', 0, '', '', 0, '', '', 'admin', 'admin', '2011-09-28 11:57:16', '2011-09-28 13:30:12'),
(33, 0, '健康診断', 'Kumamoto', '', 2017, 11, 29, '2017-11-29', '07:30', '12:30', '', '', '', '', '', '', 0, 1, '', '', 0, '', '', 0, '', '', 'kumamoto', NULL, '2017-11-29 09:35:33', NULL),
(34, 0, '健康診断', 'Kumamoto', '', 2017, 11, 29, '2017-11-29', '07:30', '12:30', '', '', '', '', '', '', 0, 1, '', '', 0, '', '', 1, '', '', 'kumamoto', NULL, '2017-11-29 09:35:58', NULL),
(35, 0, '【Lant】Kumamoto・Huyen', 'Kumamoto', 'Lant事務所で仕事します。', 2017, 12, 1, '2017-12-01', '11:20', '12:20', '', '', '', '', '', '', 0, 1, '', '', 2, '[1]', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:23:12', NULL),
(36, 0, '【HDTC】Kumamoto・Huyen', 'Kumamoto', '【HDTC】Kumamoto・Huyen', 2017, 12, 1, '2017-12-01', '11:20', '12:20', '', '', '', '', '', '', 0, 1, '', '', 2, '[1]', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:23:47', NULL),
(37, 0, '【HDTC】Kumamoto・Huyen', 'Kumamoto', '【HDTC】Kumamoto・Huyen', 2017, 12, 1, '2017-12-01', '07:30', '17:00', '', '', '', '', '', '', 0, 1, '', '', 2, '[1]', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:24:01', NULL),
(38, 0, '【HDTC】Kumamoto・Huyen', 'Kumamoto', '【HDTC】Kumamoto・Huyen', 2017, 12, 1, '2017-12-01', '07:30', '17:00', '', '', '', '', '', '', 0, 1, '', '', 2, '[1]', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:24:11', NULL),
(39, 0, '【HDTC】Kumamoto・Huyen', 'Kumamoto', '【HDTC】Kumamoto・Huyen', 2017, 12, 1, '2017-12-01', '07:30', '17:00', '', '', '', '', '', '', 0, 1, '', '', 2, '[1]', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:24:13', NULL),
(40, 0, '【HDTC】Kumamoto・Huyen', 'Kumamoto', '【HDTC】Kumamoto・Huyen', 2017, 12, 1, '2017-12-01', '07:30', '17:00', '', '', '', '', '', '', 0, 1, '', '', 0, '', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:24:24', NULL),
(41, 0, '【HDTC】Kumamoto・Huyen', 'Kumamoto', '【HDTC】Kumamoto・Huyen', 2017, 12, 1, '2017-12-01', '07:30', '17:00', '', '', '', '', '', '', 0, 1, '', '', 2, '[1]', '', 0, '', '', 'kumamoto', NULL, '2017-12-01 13:24:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `groupware_storage`
--

CREATE TABLE `groupware_storage` (
  `id` int(11) NOT NULL,
  `storage_type` mediumtext NOT NULL,
  `storage_folder` int(11) NOT NULL,
  `storage_title` mediumtext DEFAULT NULL,
  `storage_name` mediumtext DEFAULT NULL,
  `storage_comment` mediumtext DEFAULT NULL,
  `storage_date` mediumtext DEFAULT NULL,
  `storage_file` mediumtext DEFAULT NULL,
  `storage_size` mediumtext DEFAULT NULL,
  `add_level` int(11) DEFAULT NULL,
  `add_group` mediumtext DEFAULT NULL,
  `add_user` mediumtext DEFAULT NULL,
  `public_level` int(11) NOT NULL,
  `public_group` mediumtext DEFAULT NULL,
  `public_user` mediumtext DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_timecard`
--

CREATE TABLE `groupware_timecard` (
  `id` int(11) NOT NULL,
  `timecard_open` mediumtext DEFAULT NULL,
  `timecard_close` mediumtext DEFAULT NULL,
  `timecard_originalopen` mediumtext DEFAULT NULL,
  `timecard_originalclose` mediumtext DEFAULT NULL,
  `timecard_interval` mediumtext DEFAULT NULL,
  `timecard_originalinterval` mediumtext DEFAULT NULL,
  `timecard_time` mediumtext DEFAULT NULL,
  `timecard_timeover` mediumtext DEFAULT NULL,
  `timecard_timeinterval` mediumtext DEFAULT NULL,
  `timecard_comment` mediumtext DEFAULT NULL,
  `update_time` varchar(50) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 0,
  `leader_comment` text DEFAULT NULL,
  `leader_comment_id` varchar(50) DEFAULT NULL,
  `leader_comment_time` varchar(50) DEFAULT NULL,
  `tv_comment` text DEFAULT NULL,
  `tv_comment_id` varchar(50) DEFAULT NULL,
  `tv_comment_time` varchar(20) DEFAULT NULL,
  `timecard_minus` varchar(10) DEFAULT NULL,
  `timecard_overtime_real` varchar(10) DEFAULT NULL,
  `timecard_year` int(11) DEFAULT NULL,
  `timecard_month` int(11) DEFAULT NULL,
  `timecard_day` int(11) DEFAULT NULL,
  `timecard_date` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL,
  `timecard_temp` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_timecard`
--

INSERT INTO `groupware_timecard` (`id`, `timecard_open`, `timecard_close`, `timecard_originalopen`, `timecard_originalclose`, `timecard_interval`, `timecard_originalinterval`, `timecard_time`, `timecard_timeover`, `timecard_timeinterval`, `timecard_comment`, `update_time`, `version`, `leader_comment`, `leader_comment_id`, `leader_comment_time`, `tv_comment`, `tv_comment_id`, `tv_comment_time`, `timecard_minus`, `timecard_overtime_real`, `timecard_year`, `timecard_month`, `timecard_day`, `timecard_date`, `owner`, `editor`, `created`, `updated`, `timecard_temp`) VALUES
(153261, '7:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2025, 4, 4, '2025-04-04', 'minhthanhonly', NULL, '', NULL, NULL),
(153263, '7:30', '17:00', NULL, NULL, '1:00', NULL, '8:00', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2025, 3, 15, '2025-03-15', 'minhthanhonly', NULL, '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `groupware_todo`
--

CREATE TABLE `groupware_todo` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `todo_parent` int(11) DEFAULT NULL,
  `todo_title` mediumtext DEFAULT NULL,
  `todo_name` mediumtext DEFAULT NULL,
  `todo_term` mediumtext DEFAULT NULL,
  `todo_noterm` mediumtext DEFAULT NULL,
  `todo_priority` int(11) DEFAULT NULL,
  `todo_comment` mediumtext DEFAULT NULL,
  `todo_complete` int(11) DEFAULT NULL,
  `todo_completedate` mediumtext DEFAULT NULL,
  `todo_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `groupware_user`
--

CREATE TABLE `groupware_user` (
  `id` int(11) NOT NULL,
  `userid` mediumtext NOT NULL,
  `password` mediumtext NOT NULL,
  `password_default` mediumtext NOT NULL,
  `realname` mediumtext NOT NULL,
  `authority` mediumtext NOT NULL,
  `user_group` int(11) DEFAULT NULL,
  `user_groupname` mediumtext DEFAULT NULL,
  `user_email` mediumtext DEFAULT NULL,
  `user_skype` mediumtext DEFAULT NULL,
  `user_ruby` mediumtext DEFAULT NULL,
  `user_postcode` mediumtext DEFAULT NULL,
  `user_address` mediumtext DEFAULT NULL,
  `user_addressruby` mediumtext DEFAULT NULL,
  `user_phone` mediumtext DEFAULT NULL,
  `user_mobile` mediumtext DEFAULT NULL,
  `user_order` int(11) DEFAULT NULL,
  `edit_level` int(11) DEFAULT NULL,
  `edit_group` mediumtext DEFAULT NULL,
  `edit_user` mediumtext DEFAULT NULL,
  `owner` mediumtext NOT NULL,
  `editor` mediumtext DEFAULT NULL,
  `created` mediumtext NOT NULL,
  `updated` mediumtext DEFAULT NULL,
  `pc_name` varchar(50) NOT NULL,
  `last_active` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `idle_time` varchar(50) NOT NULL,
  `pc_hashs` text NOT NULL,
  `member_type` varchar(50) DEFAULT 'timecard	',
  `remember_token` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groupware_user`
--

INSERT INTO `groupware_user` (`id`, `userid`, `password`, `password_default`, `realname`, `authority`, `user_group`, `user_groupname`, `user_email`, `user_skype`, `user_ruby`, `user_postcode`, `user_address`, `user_addressruby`, `user_phone`, `user_mobile`, `user_order`, `edit_level`, `edit_group`, `edit_user`, `owner`, `editor`, `created`, `updated`, `pc_name`, `last_active`, `status`, `idle_time`, `pc_hashs`, `member_type`, `remember_token`) VALUES
(1, 'admin', '3c6b2cbde180f07b64004617bf4eee44', '21232f297a57a5a743894a0e4a801fc3', 'Admin', 'administrator', 1, 'Chung', '', '', '', '', '', '', '', '', 0, 0, '', '', 'admin', 'admin', '2011-07-13 16:59:48', '2021-03-10 12:23:32', 'NOONE', '2024-06-27 17:24:46', 'online', '', '', 'timecard', '2804e937e57ddee327755ca623f65694'),
(12, 'tranvinh.loc', '06367194b29bc307b35763e6e85dbfaa', '06367194b29bc307b35763e6e85dbfaa', 'Tran Vinh Loc', 'editor', 1, 'Chung', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', '', 'minhthanhonly', '', '2024-06-27 11:13:20', 'TB_007', '2024-07-12 17:00:22', 'offline', '2024-07-12 11:59:15', 'MT7017003604892-BFEBFBFF00000F65-TB_007-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(13, 'minhthomonly', '7ee4e16825da9eb26585986429271d91', '89a64ecaffb30bcade81eb8ffefe75ed', 'Dinh Minh Thom', 'manager', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', '', 'minhthanhonly', '', '2024-06-19 17:09:30', 'KT-001', '2024-07-15 07:51:28', 'offline', '2024-07-12 11:38:18', '', 'timecard', NULL),
(15, 'minhthanhonly', '3c6b2cbde180f07b64004617bf4eee44', '89a64ecaffb30bcade81eb8ffefe75ed', 'Dinh Minh Thanh', 'manager', 7, 'Web', 'thanhonly@caily.com.vn', 'minhthanhonly', NULL, NULL, 'MDC', NULL, NULL, '0966448826', 0, 0, '', '', '', 'minhthanhonly', '', '2024-09-26 15:45:46', 'NOONE', '2024-07-12 13:13:40', 'offline', '2024-06-28 16:55:30', '', 'timecard20240925092540', '90364bbff89a96606b0bc6c66b24aa1f'),
(16, 'dat', 'e8c18bde56087ea04566b7f5a7e99e7f', 'e8c18bde56087ea04566b7f5a7e99e7f', 'Tran Vinh Dat', 'editor', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2012-11-06 10:49:25', '2021-03-10 09:17:39', 'PC', '2024-07-15 07:37:34', 'offline', '2024-07-15 06:57:40', 'sb45nrcx001mnzmb-178bfbff00a70f52-dat-zephyrus-microsoft windows nt 6.2.9200.0,BSS-0123456789-BFEBFBFF000806EA-DESKTOP-U3UH4CR-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(19, 'ngocthuy', 'c63a090ecb5aed04036c5864e8c31652', 'c63a090ecb5aed04036c5864e8c31652', 'Luong Ngoc Thuy', 'manager', 19, 'Tổng Vụ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2013-03-11 10:02:25', '2021-03-10 06:18:20', 'THUY_TV', '2024-07-02 07:39:50', 'offline', '2024-07-01 13:27:17', '', 'timecard', NULL),
(22, 'lien', '26dd2ba83929792128d28b0e464e8350', '26dd2ba83929792128d28b0e464e8350', 'Vu Thi Lien', 'editor', 9, 'Thiet Bi B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2013-06-10 10:31:04', '2021-03-10 09:16:31', 'TB_006', '2024-07-15 06:21:23', 'offline', '2024-07-12 11:44:34', '..CN70163161007N.                  -BFEBFBFF000206A7-TB_006-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(23, 'nguyen', 'a28d88a19f793941ac767bf447372b0d', 'a28d88a19f793941ac767bf447372b0d', 'Lam Duy Nguyen', 'editor', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2013-06-10 10:38:13', '2021-03-10 09:18:03', 'ADMINISTRATOR', '2024-07-15 08:27:34', 'offline', '2024-07-12 14:31:12', 'default string-bfebfbff00090675-administrator-microsoft windows nt 6.2.9200.0,Unknown-Unknown-ADMINISTRATOR-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(27, 'nhan', '80e16a3287ec72da2094cb20eb238ddd', '80e16a3287ec72da2094cb20eb238ddd', 'Diep Thanh Nhan', 'editor', 10, 'Thiet Bi C', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2013-12-16 09:28:39', '2021-03-10 09:16:55', 'DESKTOP-RM1MUTT', '2024-07-15 13:20:17', 'offline', '2024-07-15 13:10:18', 'Default string-BFEBFBFF00090675-DESKTOP-RM1MUTT-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(28, 'khang', 'f94fdb47713f8a00c740d38ed26ecc1a', 'f94fdb47713f8a00c740d38ed26ecc1a', 'Pham Nguyen Khang', 'editor', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'minhthanhonly', '2013-12-16 09:29:02', '2024-06-03 07:57:26', '', '2024-07-10 07:30:48', 'offline', '2024-07-09 10:00:20', 'M80-3C009402063-BFEBFBFF000306C3-KT-KHANG-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(151, 'hoaibao', '1626c8f1380c69b4dddf527d192e3def', '1626c8f1380c69b4dddf527d192e3def', 'Nguyen Hoai Bao', 'member', 13, 'Thiet Bi F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2020-11-10 09:39:34', '2021-03-10 03:37:25', 'TB-003', '2024-07-12 15:36:28', 'offline', '2024-07-12 15:25:04', '150239751600235-bfebfbff000306c3-tb-003-microsoft windows nt 6.1.7601 service pack 1,07B8911_KB1E514815-178BFBFF00A20F12-DESKTOP-OJSP0M4-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(36, 'trang', '81e139aaf0f4a29cbc9beb0ded08a969', '81e139aaf0f4a29cbc9beb0ded08a969', 'Huynh Thi Khanh Trang', 'manager', 19, 'Tổng Vụ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2014-10-02 18:49:12', '2021-03-10 06:18:27', '', '', '', '', '', 'timecard', NULL),
(38, 'tranmanhtu', '008f96839bd76b376b81f338964e1ed7', '008f96839bd76b376b81f338964e1ed7', 'Tran Manh Tu', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'minhthanhonly', '2014-10-22 18:35:24', '2024-06-03 07:56:08', '', '', '', '', '', 'timecard', NULL),
(152, 'quocthinh_web', '59be41051816e8bab8f130b172093229', '59be41051816e8bab8f130b172093229', 'Huynh Quoc Thinh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'minhthanhonly', '2021-03-08 02:16:56', '2025-01-08 07:35:28', 'DESKTOP-BCULK3U', '2024-07-10 16:35:45', 'online', '2024-07-10 16:12:56', 'To be filled by O.E.M.-BFEBFBFF000306A9-DESKTOP-BCULK3U-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(41, 'hien', '8382303daaf3d40010e197c3681d2c5e', '8382303daaf3d40010e197c3681d2c5e', 'Nguyen Thu Hien', 'editor', 12, 'Thiet Bi E', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2014-11-12 09:16:56', '2021-03-10 09:17:16', 'TB_017', '2024-07-15 11:33:15', 'offline', '2024-07-15 08:05:15', 'default string-178bfbff00810f10-tb_017-microsoft windows nt 6.2.9200.0,Unknown-Unknown-TB_017-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(42, 'linh', 'd1fe9a7a480e8edbc7e29c1518847bd9', 'd1fe9a7a480e8edbc7e29c1518847bd9', 'Pham Thi My Linh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2014-11-12 09:17:36', '2021-03-10 03:43:45', '', '', '', '', '', 'timecard', NULL),
(46, 'hieu', 'd8026eca77d4b477017d7050401b5d31', 'd8026eca77d4b477017d7050401b5d31', 'Huynh Thien Hieu', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2014-12-02 09:46:40', '2021-03-10 03:44:16', '', '', '', '', '', 'timecard', NULL),
(48, 'hau', 'b30bffd26c5543a5ecad7cf3ee756a22', 'b30bffd26c5543a5ecad7cf3ee756a22', 'Le Trung Hau', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-04-06 10:29:40', '2021-03-10 03:44:43', '', '', '', '', '', 'timecard', NULL),
(49, 'hoang', '608c250e4999761bbe535f50fa001bab', '608c250e4999761bbe535f50fa001bab', 'Le Quoc Hoang', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-04-22 18:55:10', '2021-03-10 04:05:45', '', '', '', '', '', 'timecard', NULL),
(50, 'bien', 'af7da10ac208ff99b78a43107ca7fbff', 'af7da10ac208ff99b78a43107ca7fbff', 'Huynh Trong Bien', 'editor', 15, 'Nang Luong', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-04-22 18:55:45', '2021-03-10 09:18:30', 'DESKTOP-H0NH7L4', '2024-07-12 15:39:18', 'idle', '2024-07-12 10:26:55', 'Default string-178BFBFF00810F10-DESKTOP-H0NH7L4-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(51, 'vu', 'f596e1c82baa4f46e6c994f7268cb913', 'f596e1c82baa4f46e6c994f7268cb913', 'Le Trong Vu', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-05-22 16:53:48', '2021-03-10 03:47:32', '', '', '', '', '', 'timecard', NULL),
(52, 'van.tu', 'e95286a714de7943b5cb0d95a47aa4fd', 'e95286a714de7943b5cb0d95a47aa4fd', 'Huynh Van Tu', 'editor', 13, 'Thiet Bi F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-05-22 16:55:04', '2021-03-10 09:17:30', 'VANTU-PC', '2024-07-12 15:28:35', 'offline', '2024-07-11 16:25:38', '170397795802397-bfebfbff000306c3-vantu-pc-microsoft windows nt 6.1.7601 service pack 1,BSN12345678901234567-BFEBFBFF00040651-TAHUYNH-ASUS-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(53, 'trinh', '0e2a0dc1d316ae8fb1e4deb0f2493126', '0e2a0dc1d316ae8fb1e4deb0f2493126', 'Mai Van Trinh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-05-22 16:55:47', '2021-03-10 03:48:10', '', '', '', '', '', 'timecard', NULL),
(55, 'huyenhanh153', '3b7f3c33fb7148217ba90d3be3a008c6', '3b7f3c33fb7148217ba90d3be3a008c6', 'Huyen Hanh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-07-01 10:19:04', '2021-03-10 03:48:57', '', '', '', '', '', 'timecard', NULL),
(56, 'trongtien63', '90386ffd3d247465d6ef85ac3895af4e', '90386ffd3d247465d6ef85ac3895af4e', 'Nguyen Trong Tien', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-07-01 10:19:53', '2021-03-10 03:49:05', '', '', '', '', '', 'timecard', NULL),
(57, 'truong', '1e4bc905be77559e48dad09766478954', '1e4bc905be77559e48dad09766478954', 'Hoang Van Truong', 'member', 15, 'Nang Luong', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-07-28 12:11:15', '2021-03-10 03:50:00', 'HTRUONG-PC', '2024-07-12 17:05:09', 'offline', '2024-07-12 14:22:04', '140933015604604-BFEBFBFF000306A9-HTRUONG-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(59, 'sam', '773fff5f655fedf7f6cefa53e8a04c7e', '773fff5f655fedf7f6cefa53e8a04c7e', 'Ngo Ngoc Sam', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-08-17 11:30:13', '2021-03-10 03:50:23', '', '', '', '', '', 'timecard', NULL),
(61, 'huy', 'd976d18052f1786159af77e935d6d13e', 'd976d18052f1786159af77e935d6d13e', 'Tran Thanh Huy', 'editor', 20, 'Thiet Bi B1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-09-15 17:49:34', '2021-03-10 09:16:44', 'TB_022-PC', '2024-07-15 07:52:45', 'offline', '2024-07-12 14:45:05', '140830664303944-BFEBFBFF000306A9-TB_022-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(125, 'chautuan', 'ab5dd1786fc1afdb96bd342fc916e562', 'ab5dd1786fc1afdb96bd342fc916e562', 'Le Chau Tuan', 'member', 11, 'Thiet Bi D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-09-04 13:28:48', '2021-03-10 03:37:49', 'TB_014-PC', '2024-07-12 15:34:44', 'offline', '2024-07-11 10:41:21', '130915509901888-BFEBFBFF000306A9-TB_014-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(63, 'sang', '4546447fb6f2f8781055cefd0ea95f04', '4546447fb6f2f8781055cefd0ea95f04', 'Nguyen Hoang Sang', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-09-15 17:57:07', '2021-03-10 03:50:50', '', '', '', '', '', 'timecard', NULL),
(64, 'thanh', '2d0c74a7121db74db6970f2e5f322a8e', '2d0c74a7121db74db6970f2e5f322a8e', 'Doan Huu Thanh', 'editor', 11, 'Thiet Bi D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-09-15 17:58:41', '2021-03-10 09:17:08', 'THANH-THIETBI', '2024-07-12 17:05:24', 'offline', '2024-07-12 16:54:19', '141236838813610-BFEBFBFF000306A9-THANH-THIETBI-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(65, 'vantuan', '490dac6ef83fa107fd38c97ba83b10e1', '490dac6ef83fa107fd38c97ba83b10e1', 'Ngo Van Tuan', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2015-09-21 17:10:02', '2022-08-31 02:31:22', '', '', '', '', '', 'timecard', NULL),
(67, 'tuyet2015', '14eccd7e21c16e7f2c65d9934680a12d', '14eccd7e21c16e7f2c65d9934680a12d', 'Huynh Thi Anh Tuyet', 'editor', 8, 'Thiet Bi A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'admin', '2015-10-20 15:36:14', '2021-03-10 09:16:19', 'TB_005-PC', '2024-07-12 17:13:04', 'offline', '2024-07-11 16:06:53', 'PF10TKXS-BFEBFBFF000806EA-NGOVANTHAN-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(68, 'phong2015', 'f38fc56e84c9366469d7e07fb6f4ac52', 'f38fc56e84c9366469d7e07fb6f4ac52', 'Tran The Phong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2015-10-20 15:48:36', '2021-03-10 03:51:26', '', '', '', '', '', 'timecard', NULL),
(69, 'khactrinh', '0e2a0dc1d316ae8fb1e4deb0f2493126', '0e2a0dc1d316ae8fb1e4deb0f2493126', 'La Khac Trinh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2015-11-30 18:56:58', '2021-03-10 03:51:59', '', '', '', '', '', 'timecard', NULL),
(70, 'tuyen', 'f60223501987998182f84615ec30335d', 'f60223501987998182f84615ec30335d', 'Huynh Thi Thanh Tuyen', 'member', 8, 'Thiet Bi A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '', '', 'ngocthuy', 'trang', '2015-11-30 18:58:15', '2023-04-28 01:37:50', 'DESKTOP-T3K8M5P', '2024-07-15 07:04:24', 'offline', '2024-07-12 11:30:37', '07C8911_L21E591072-BFEBFBFF000A0653-DESKTOP-T3K8M5P-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(71, 'hanhthach', '3b7f3c33fb7148217ba90d3be3a008c6', '3b7f3c33fb7148217ba90d3be3a008c6', 'Thach Thi Tuyet Hanh', 'member', 20, 'Thiet Bi B1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 0, '', '', 'ngocthuy', 'trang', '2015-11-30 18:59:08', '2021-04-28 03:09:50', 'TB_023', '2024-07-15 11:25:48', 'online', '2024-07-15 07:48:15', '140830664306464-BFEBFBFF000306A9-TB_023-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(72, 'duya', '36e6a8ef09390496faf70f56dae64933', '36e6a8ef09390496faf70f56dae64933', 'Do Van Duya', 'member', 18, 'Ket Cau', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-02-29 12:12:26', '2021-03-10 03:52:39', 'ADMINISTRATOR', '2024-07-12 15:38:45', 'idle', '2024-07-12 11:37:40', '32fcff85d806637b93558e01e1dafed82df31a32e4459d9630d60650daac5123,Default string-BFEBFBFF00090672-DESKTOP-FOPMKH7-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(73, 'phuong', 'd3ac38ec42ad979334510459e7d5ee97', 'd3ac38ec42ad979334510459e7d5ee97', 'Tran Thanh Phuong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-03-16 15:03:00', '2021-03-10 04:02:55', '', '', '', '', '', 'timecard', NULL),
(74, 'khanh', 'ea111eebc0a74cf5162e8f704d4651c0', 'ea111eebc0a74cf5162e8f704d4651c0', 'Diep Hoang Khanh', 'member', 8, 'Thiet Bi A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-03-22 17:16:36', '2021-03-10 03:52:53', 'ADMIN', '2024-07-12 17:02:43', 'offline', '2024-07-12 12:17:52', '160163370305739-bfebfbff000306c3-desktop-uh2o024-microsoft windows nt 6.2.9200.0,07D1711_L61E718094-BFEBFBFF000A0671-DESKTOP-I5JOSG2-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(75, 'dinh', 'b4708c5555599f522af9269b6ccd00de', 'b4708c5555599f522af9269b6ccd00de', 'Tran Huu Dinh', 'member', 8, 'Thiet Bi A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '', '', 'ngocthuy', 'trang', '2016-03-22 17:18:26', '2023-04-28 01:42:58', 'DINHTRAN-PC', '2024-07-12 16:57:21', 'offline', '2024-07-12 10:23:48', 'ma41nbcv00ayujmb-bfebfbff000806c1-desktop-svu5gvo-microsoft windows nt 6.2.9200.0,M80-E3009802164-BFEBFBFF000A0653-DESKTOP-PB2JF3N-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(76, 'vankhanh', '8b1192dc710d105f9291ab267a24dda3', '8b1192dc710d105f9291ab267a24dda3', 'Ha Van Khanh', 'member', 9, 'Thiet Bi B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-04-07 09:34:50', '2021-03-10 03:53:04', 'DESKTOP-GQK8GQ6', '2024-07-12 16:46:22', 'offline', '2024-07-11 11:41:47', '180119618501134-BFEBFBFF000906E9-DESKTOP-GQK8GQ6-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(77, 'vi', 'de102aa63d98bb533408fd5c3ff8ee04', 'de102aa63d98bb533408fd5c3ff8ee04', 'Huynh Tuong Vi', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2016-04-20 09:47:22', '2024-01-22 01:11:43', '3D_003', '2024-07-15 07:42:18', 'offline', '2024-07-12 12:00:44', 'Default string-BFEBFBFF000506E3-3D_003-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(78, 'thang', '8a5a411e4eeccb33e161a09aa919f9a7', '8a5a411e4eeccb33e161a09aa919f9a7', 'Nguyen Xuan Thang', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-04-20 09:49:16', '2021-03-10 04:03:11', '', '', '', '', '', 'timecard', NULL),
(79, 'thuy', 'ef21eb0fb7dbf91134d141f0131b9b3a', 'ef21eb0fb7dbf91134d141f0131b9b3a', 'Nguyen Thi Thu Thuy', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2016-05-05 18:58:06', '2024-06-03 07:56:18', '', '', '', '', '', 'timecard', NULL),
(80, 'dai', 'd07d95266a5bf0e6ab0e3bc43de8f9c2', 'd07d95266a5bf0e6ab0e3bc43de8f9c2', 'Nguyen Minh Dai', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-05-11 15:43:38', '2021-03-10 04:09:46', 'DESKTOP-RM1MUTT', '2024-07-15 07:20:41', 'online', '2024-07-12 12:27:25', 'default string-bfebfbff00090675-caily-dai-microsoft windows nt 6.2.9200.0,Default string-BFEBFBFF00090675-CAILY-MINHDAI-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(81, 'ngan', '49773502ee6e9b35f53be629ef4b30a0', '49773502ee6e9b35f53be629ef4b30a0', 'Nguyen Thanh Ngan', 'member', 13, 'Thiet Bi F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-05-11 15:44:25', '2021-03-10 03:54:07', 'TB-010', '2024-07-15 12:54:39', 'offline', '2024-07-12 11:25:56', 'Default string-BFEBFBFF00090675-TB-010-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(82, 'diep', '0ec047038d2d80733b9630b52189d93e', '0ec047038d2d80733b9630b52189d93e', 'Tran Thi Diep', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-05-11 15:44:58', '2021-03-10 03:55:38', '', '', '', '', '', 'timecard', NULL),
(83, 'duchoa', 'a4a313134f88cfe21cae73a2c503131c', 'a4a313134f88cfe21cae73a2c503131c', 'Bui Duc Hoa', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-05-11 15:45:39', '2021-03-10 03:54:55', '', '', '', '', '', 'timecard', NULL),
(85, 'lengoc', 'ba306cef0766f36d5f315564de3495ed', 'ba306cef0766f36d5f315564de3495ed', 'Le Thi Ngoc', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-05-23 17:48:28', '2021-03-10 03:55:14', '', '', '', '', '', 'timecard', NULL),
(86, 'minhtri', '3cafaea245f56d26336612e3353f9e92', '3cafaea245f56d26336612e3353f9e92', 'Nguyen Minh Tri', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-06-29 16:06:23', '2021-03-10 04:03:22', '', '', '', '', '', 'timecard', NULL),
(87, 'quoc', 'f70e1fd0a06d59485221ffda7125e382', 'f70e1fd0a06d59485221ffda7125e382', 'Tran The Quoc', 'member', 15, 'Nang Luong', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-07-11 18:36:25', '2021-03-10 03:55:08', 'DESKTOP-STSQGVQ', '2024-07-12 16:58:19', 'offline', '2024-07-12 11:32:33', '160469703707690-bfebfbff000306c3-desktop-stsqgvq-microsoft windows nt 6.2.9200.0,Unknown-Unknown-DESKTOP-STSQGVQ-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(88, 'ha', 'edd2a7cfadb42c4222b0ac41fc9b81f9', 'edd2a7cfadb42c4222b0ac41fc9b81f9', 'Ho Thi Ha', 'member', 10, 'Thiet Bi C', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-07-11 18:37:25', '2021-03-10 03:58:44', 'P0WFA3PBKA3CWKF', '2024-07-15 13:51:03', 'online', '2024-07-11 13:37:49', 'BTWW1330019X-BFEBFBFF000206A7-P0WFA3PBKA3CWKF-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(89, 'diemai', '447dbea4d86a8ecf964d7db81431558a', '447dbea4d86a8ecf964d7db81431558a', 'Dang Thi Diem Ai', 'member', 7, 'Web', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2016-08-25 10:11:52', NULL, '', '', '', '', 'default string-bfebfbff000506e3-web0000-microsoft windows nt 6.2.9200.0,140525603208334-BFEBFBFF000306A9-DESKTOP-MQ7SU43-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(90, 'thoi', '046decd3f81a091eae4fac8028f03b39', '046decd3f81a091eae4fac8028f03b39', 'Dang Dinh Thoi', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-09-08 10:13:57', '2021-03-10 03:55:26', 'KT_012', '2024-07-12 16:59:10', 'offline', '2024-07-12 11:35:17', 'Default string-BFEBFBFF000906E9-KT_012-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(91, 'hoai', '5383eaf6804e7e7d666c93cbc9fb4d99', '5383eaf6804e7e7d666c93cbc9fb4d99', 'Nguyen Thi Hoai', 'editor', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2016-09-21 08:33:12', '2024-06-27 15:11:41', 'DESKTOP-RM1MUTT', '2024-07-15 07:23:19', 'online', '2024-07-12 11:36:16', 'Default string-BFEBFBFF00090675-CAILY-HOAI-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(92, 'tin', '1533acd45cdb4fec6b17b18233223c41', '1533acd45cdb4fec6b17b18233223c41', 'Phan Thanh Tin', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-09-27 13:25:01', '2021-03-10 04:03:44', '', '', '', '', '', 'timecard', NULL),
(93, 'vietpham', 'd6a7041e009d25339b6cac81bdf0022d', 'd6a7041e009d25339b6cac81bdf0022d', 'Pham Van Viet', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2016-11-21 18:41:13', '2021-03-10 03:56:19', '', '', '', '', '', 'timecard', NULL),
(94, 'lan', 'bcdfde7e23da8521cc91a031f5d1a0de', 'bcdfde7e23da8521cc91a031f5d1a0de', 'Tran Thi Thuy Lan', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-03-20 15:53:08', '2021-03-10 04:04:03', '', '', '', '', '', 'timecard', NULL),
(95, 'baotram', '759e89f2142c133c7d729d6d0f3ee63a', '759e89f2142c133c7d729d6d0f3ee63a', 'Tran Nguyen Bao Tram', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-04-24 16:50:16', '2021-03-10 03:56:38', '', '', '', '', '', 'timecard', NULL),
(96, 'quyen', 'd5b9d8be2b40195861ff6e71231ee76b', 'd5b9d8be2b40195861ff6e71231ee76b', 'Nguyen Thi Thu Quyen', 'editor', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-06-01 17:06:53', '2021-03-10 09:18:15', 'KT_002', '2024-07-12 15:40:12', 'offline', '2024-07-12 12:01:01', '170397795801589-BFEBFBFF000306C3-KT_002-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(97, 'yen', '16a28daef8d3c0b3026dc5bd94e9c39b', '16a28daef8d3c0b3026dc5bd94e9c39b', 'Bui Thi Hong Yen', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-06-20 16:02:13', '2021-03-10 03:57:37', 'KT_YEN-PC', '2024-07-15 07:42:32', 'offline', '2024-07-12 11:36:27', '170397795802594-BFEBFBFF000306C3-KT_YEN-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(98, 'ngoclong', '959a507b79b871d7b2967ef9b7513433', '959a507b79b871d7b2967ef9b7513433', 'Van Ngoc Long', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-07-06 09:48:59', '2021-03-10 03:57:44', '', '', '', '', '', 'timecard', NULL),
(99, 'huyhoang', '53772b1ff6f3d97ecb0110469de2fbb2', '53772b1ff6f3d97ecb0110469de2fbb2', 'Nguyen Huy Hoang', 'editor', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2017-07-21 12:04:04', '2024-06-27 15:18:55', 'KT_006', '2024-07-12 15:30:12', 'offline', '2024-07-12 11:37:07', '170397795801539-bfebfbff000306c3-kt_006-microsoft windows nt 6.1.7601 service pack 1,Default string-BFEBFBFF000506E3-DESKTOP-BAN2B3B-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(100, 'nhu', '88d7817c64a11806c298774ed553bfcf', '88d7817c64a11806c298774ed553bfcf', 'Nguyen Thi Nhu', 'editor', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2017-07-21 12:06:03', '2024-06-27 15:18:34', 'KIENTRUC-PC', '2024-07-14 13:56:03', 'offline', '2024-07-11 17:22:12', 'unknown-unknown-kientruc-pc-microsoft windows nt 6.1.7601 service pack 1,E80-45010700161-BFEBFBFF000306C3-KIENTRUC-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(101, 'bao', '453f3ee58594dfd0725afea6bcebbbd4', '453f3ee58594dfd0725afea6bcebbbd4', 'Luu Chi Bao', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-09-15 15:48:33', '2021-03-10 03:59:01', '', '', '', '', '', 'timecard', NULL),
(102, 'bi', '81f080dcd20de79cf25b40d2466a1091', '81f080dcd20de79cf25b40d2466a1091', 'Nguyen Van Bi', 'editor', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2017-09-15 15:49:32', '2024-06-27 15:11:56', 'Z-VROOTVN', '2024-07-15 07:25:00', 'online', '2024-07-12 14:23:59', '200670022004997-bfebfbff000a0653-z-vrootvn-microsoft windows nt 6.2.9200.0,Default string-BFEBFBFF00090675-Z-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(103, 'khanhlinh', 'd0bfd1e87014f48d9f83481cbe7577f7', 'd0bfd1e87014f48d9f83481cbe7577f7', 'Nguyen Ngoc Khanh Linh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-09-15 15:51:00', '2021-03-10 04:04:17', '', '', '', '', '', 'timecard', NULL),
(104, 'vutrang', '82a4cd157b5c0f7d73147920bec29892', '82a4cd157b5c0f7d73147920bec29892', 'Tran Vu Trang', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-09-15 15:52:03', '2021-03-10 04:01:13', 'DESKTOP-VSI6RLH', '2024-07-15 07:39:57', 'offline', '2024-07-12 14:11:14', 'Default string-BFEBFBFF000906E9-DESKTOP-VSI6RLH-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(105, 'minh', '2a8ed92793ec2c5a84a7e0649b14bb4d', '2a8ed92793ec2c5a84a7e0649b14bb4d', 'Tran Nhat Minh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2017-09-25 16:35:17', '2022-08-31 02:31:40', '', '', '', '', '', 'timecard', NULL),
(106, 'giap', 'f0421c8562220dccee524c9c63aa3c2b', 'f0421c8562220dccee524c9c63aa3c2b', 'Phan Ba Giap', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-09-25 16:36:13', '2021-03-10 04:04:30', '', '', '', '', '', 'timecard', NULL),
(107, 'vien', 'a6629333b689afa813df644cb03a12c7', 'a6629333b689afa813df644cb03a12c7', 'Ngo Quang Vien', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-09-25 16:37:02', '2021-03-10 04:02:05', '', '', '', '', '', 'timecard', NULL),
(108, 'nguyenkhanh', 'fcceab2c1c518b100179d6ac3f0e3d73', 'fcceab2c1c518b100179d6ac3f0e3d73', 'Nguyen Van Khanh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-11-01 10:45:36', '2021-03-10 04:04:43', '', '', '', '', '', 'timecard', NULL),
(109, 'phihai', 'd82e25eaa5e0c3c12f05baa1fe4d991a', 'd82e25eaa5e0c3c12f05baa1fe4d991a', 'Nguyen Au Phi Hai', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2017-11-15 18:21:22', '2021-03-10 04:02:20', '', '', '', '', '', 'timecard', NULL),
(112, 'tantrung', '28253073f99de86c236c1ea39de6bdbe', '28253073f99de86c236c1ea39de6bdbe', 'Nguyen Tan Trung', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-02-12 10:32:01', '2021-03-10 04:04:59', '', '', '', '', '', 'timecard', NULL),
(113, 'nhat', '7a1cec33d27624b27d781456af50e187', '7a1cec33d27624b27d781456af50e187', 'Bui Uy Lam Thanh Nhat', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2018-04-16 16:22:41', NULL, '', '', '', '', '', 'timecard', NULL),
(114, 'tham', '6526bf6247c9fa28d3d5848a351924cd', '6526bf6247c9fa28d3d5848a351924cd', 'Tran Thi Anh Tham', 'member', 20, 'Thiet Bi B1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-04-16 16:24:17', '2021-03-10 03:46:06', 'TB_024', '2024-07-15 07:16:50', 'offline', '2024-07-12 16:32:06', '150239751601731-bfebfbff000306c3-tb_024-microsoft windows nt 6.2.9200.0,Default string-BFEBFBFF000506E3-WEB0000-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(115, 'cong', '90ee4e239f0aa8ac5a0ee1dd45349402', '90ee4e239f0aa8ac5a0ee1dd45349402', 'Nguyen Tien Cong', 'member', 11, 'Thiet Bi D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-05-29 14:34:37', '2021-03-10 04:13:47', 'KT_015-PC', '2024-07-12 17:00:02', 'offline', '2024-07-12 12:30:18', '140729808208256-BFEBFBFF000306A9-KT_015-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(116, 'loan', '5fc4b12723ae8f98b59b474ab4df8bfa', '5fc4b12723ae8f98b59b474ab4df8bfa', 'Bui Thi Loan', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2018-05-29 14:35:18', NULL, '', '', '', '', '', 'timecard', NULL),
(117, 'an', '2f3ffea6241c406f9bd9cfc056601e10', '2f3ffea6241c406f9bd9cfc056601e10', 'Tran Quoc An', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2018-06-11 14:07:16', NULL, '', '', '', '', '', 'timecard', NULL),
(118, 'hung', 'e5587b046e7694953d5379ddbdbcb9e2', 'e5587b046e7694953d5379ddbdbcb9e2', 'Bui Ngoc Hung', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-07-23 14:04:08', '2021-03-10 04:05:19', '', '', '', '', '', 'timecard', NULL),
(119, 'lethuy', 'c8a5ff26015e8fcebc5b4da684af6269', 'c8a5ff26015e8fcebc5b4da684af6269', 'Nguyen Thi Le Thuy', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2018-08-13 16:50:55', '2025-01-08 07:35:20', 'DESKTOP-UT9K4GM', '2024-07-10 16:47:03', 'offline', '2024-07-10 14:03:30', '140729403501096-BFEBFBFF000306A9-DESKTOP-UT9K4GM-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(120, 'bich', '10ca59bf8ae9c06c0645409a849f1a04', '10ca59bf8ae9c06c0645409a849f1a04', 'Nguyen Thi Ngoc Bich', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-08-13 16:51:46', '2021-03-10 04:02:38', 'KT_BICH-PC', '2024-07-12 11:56:46', 'idle', '2024-07-12 11:28:14', '141236244002989-bfebfbff000306c3-kt_bich-pc-microsoft windows nt 6.1.7601 service pack 1,/BVGX162/CN1296359I077B/-BFEBFBFF00040651-DESKTOP-TP038D8-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(121, 'hongphong', '031e0532e27303d3b537cfa189290de6', '031e0532e27303d3b537cfa189290de6', 'Nguyen Hong Phong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-08-13 16:52:18', '2021-03-10 04:05:32', '', '', '', '', '', 'timecard', NULL),
(122, 'quang', 'faa96bbd669f63b80ef1e842b8d4c82a', 'faa96bbd669f63b80ef1e842b8d4c82a', 'Le Viet Quang', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-08-13 16:52:44', '2021-03-10 04:06:20', '', '', '', '', '', 'timecard', NULL),
(123, 'thuylinh', '656e409aa3b7596c1614b7c02ee2e5fd', '656e409aa3b7596c1614b7c02ee2e5fd', 'Nguyen Thi Thuy Linh', 'member', 18, 'Ket Cau', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2018-08-13 16:53:17', '2021-03-10 06:03:57', 'DESKTOP-FKE21FH', '2024-07-12 15:07:03', 'offline', '2024-07-12 12:13:14', '180322468701277-BFEBFBFF000906E9-DESKTOP-FKE21FH-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(124, 'vungoc', '5ad75d26a17cb23a3589ba4374d2d17c', '5ad75d26a17cb23a3589ba4374d2d17c', 'Pham Vu Ngoc', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2018-08-20 10:37:13', NULL, '', '', '', '', '', 'timecard', NULL),
(126, 'duykhanh', '36b426669ef1860d5469d2dcecd75e8c', '36b426669ef1860d5469d2dcecd75e8c', 'Bui Duy Khanh', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2018-11-28 09:32:02', '2021-03-10 04:01:33', 'DESKTOP-8FCH30P', '2024-07-12 15:41:20', 'offline', '2024-07-12 11:34:52', 'default string-bfebfbff000906e9-desktop-8fch30p-microsoft windows nt 6.2.9200.0,Unknown-Unknown-DESKTOP-8FCH30P-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(127, 'tai', 'a04a404a01e5ce3f5d81bd125068174e', 'a04a404a01e5ce3f5d81bd125068174e', 'Dang Trung Ut Tai', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2018-11-28 09:33:07', NULL, '', '', '', '', '', 'timecard', NULL),
(129, 'caily_cuong', 'ae3c21485e4a8fc2658f0f6ee4974a90', 'ae3c21485e4a8fc2658f0f6ee4974a90', 'Vo Thanh Cuong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'minhthanhonly', '2019-02-13 08:55:58', '2021-03-10 06:06:26', '', '', '', '', '', 'timecard', NULL),
(130, 'toan', 'f5425e25f42c2ec362075cae7b48c9f9', 'f5425e25f42c2ec362075cae7b48c9f9', 'Huynh Phuoc Toan', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-02-18 10:11:21', '2021-03-10 04:06:33', '', '', '', '', '', 'timecard', NULL),
(131, 'trung', 'e50f9e83975258499e31def494cdafc4', 'e50f9e83975258499e31def494cdafc4', 'Nguyen Thanh Trung', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2019-03-11 08:53:20', NULL, '', '', '', '', '', 'timecard', NULL),
(132, 'anh', '6fc7f971116578014250be5ab24027f7', '6fc7f971116578014250be5ab24027f7', 'Vu Tuan Anh', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2019-03-11 08:54:01', '2024-01-22 01:04:57', '', '', '', '', '', 'timecard', NULL),
(133, 'chau', 'bc0bf09c96c339c2e33565ab338c3e1c', 'bc0bf09c96c339c2e33565ab338c3e1c', 'Tran Pham Hanh Chau', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-03-14 02:28:10', '2021-03-10 04:00:44', '', '', '', '', '', 'timecard', NULL),
(134, 'huuhung', '29cd646b9958be8f6d54cb61c5a9cbe9', '29cd646b9958be8f6d54cb61c5a9cbe9', 'Lai Huu Hung', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2019-04-08 10:04:05', '2024-01-22 01:00:58', '', '', '', '', '', 'timecard', NULL),
(135, 'vanphuc', 'f3055096d65e4f0fd12eda23fffdbf95', 'f3055096d65e4f0fd12eda23fffdbf95', 'Nguyen Van Phuc', 'member', 7, 'Web', 'phuc_web@caily.com.vn', 'nvphuc.caily', NULL, NULL, '19 Quáº£n Trá»ng Linh, P.7, Q.8, TP.HCM', NULL, NULL, '0908700917', 0, 0, '', '', 'admin', 'vanphuc', '2019-04-25 06:02:13', '2021-11-12 00:21:19', 'DESKTOP-Q514OB3', '2024-07-10 17:00:26', 'offline', '2024-07-08 12:41:35', 'm80-f3005800328-bfebfbff00090675-desktop-v7ilo0v-microsoft windows nt 6.2.9200.0,M80-A4014500136-BFEBFBFF000906E9-DESKTOP-MC1MMSC-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(136, 'thinh_web', '845053c493246e9e4a5a7c81e25acee3', '845053c493246e9e4a5a7c81e25acee3', 'Tran Nguyen Thinh', 'member', 7, 'Web', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', NULL, '2019-05-02 00:58:43', NULL, 'DESKTOP-MNK0VVN', '2024-07-10 16:49:34', 'offline', '2024-07-10 15:53:44', 'default string-bfebfbff000906ea-desktop-mnk0vvn-microsoft windows nt 6.2.9200.0,PGXWK0BCYB20NL-BFEBFBFF00050654-DESKTOP-3S9FJD2-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(137, 'trong', '49ab66b47abcf40ab3d4c48aa7ee01fa', '49ab66b47abcf40ab3d4c48aa7ee01fa', 'Vu Ngoc Trong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', NULL, '2019-07-09 08:40:02', NULL, '', '', '', '', '', 'timecard', NULL),
(138, 'toi', '5ebc104999632213d3e112b2e96b4368', '5ebc104999632213d3e112b2e96b4368', 'Nguyen Quang Toi', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-07-17 01:25:54', '2024-05-14 08:48:14', '', '', '', '', '', 'timecard', NULL),
(139, 'minhphuong', 'b0436c78e63ab7af7c2701cf113ccef7', 'b0436c78e63ab7af7c2701cf113ccef7', 'Nguyen Minh Phuong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-08-01 07:15:21', '2021-03-10 04:07:05', '', '', '', '', '', 'timecard', NULL),
(140, 'ngocle', '978a29ba452072f7c01299a128d71d62', '978a29ba452072f7c01299a128d71d62', 'Ho Ngoc Le', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-08-12 08:38:50', '2021-03-10 04:11:43', 'KT_010', '2024-07-12 15:47:56', 'offline', '2024-06-28 15:36:47', 'Default string-BFEBFBFF000906E9-KT_010-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(141, 'van', '5ea8477fa928992f5af9d8c4b110c46c', '5ea8477fa928992f5af9d8c4b110c46c', 'Le Ngoc Y Van', 'member', 12, 'Thiet Bi E', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-08-14 02:03:12', '2021-03-10 04:13:25', 'TB_MSSVAN', '2024-07-12 16:16:45', 'offline', '2024-07-11 15:32:36', '200670885802142-BFEBFBFF000306C3-TB_MSSVAN-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(142, 'duyhoang', '2bab2975adc9d9aa42bb0375335472c8', '2bab2975adc9d9aa42bb0375335472c8', 'Vu Duy Hoang', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-08-19 06:30:55', '2021-03-10 04:12:16', 'DUYHOANG', '2024-07-11 17:06:41', 'offline', '2024-07-09 11:52:50', '230926166704614-bfebfbff00090675-duyhoang-microsoft windows nt 6.2.9200.0,Unknown-Unknown-DUYHOANG-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(144, 'tam', '7c77992fa0db66e072f5aa17c4592daa', '7c77992fa0db66e072f5aa17c4592daa', 'Vu Thi Thanh Tam', 'member', 8, 'Thiet Bi A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2019-10-14 09:21:23', '2021-03-10 04:14:35', 'TB_001-PC', '2024-07-14 12:51:57', 'offline', '2024-07-11 12:07:27', '161086335828765-BFEBFBFF000306C3-TB_TAM-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(145, 'ducanh', '43f052cdef8ad7c013aceaf6b3367dbb', '43f052cdef8ad7c013aceaf6b3367dbb', 'Le Thi Duc Anh', 'member', 9, 'Thiet Bi B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2020-03-02 07:06:12', '2021-03-10 04:14:15', 'TB_013', '2024-07-12 16:35:32', 'offline', '2024-07-12 11:45:06', '130915509902254-BFEBFBFF000306A9-TB_013-Microsoft Windows NT 6.1.7600.0', 'timecard', NULL),
(147, 'minhthang', '9890f9f9a08aa7fa9bade7fee8ed98aa', '9890f9f9a08aa7fa9bade7fee8ed98aa', 'Nguyen Minh Thang', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2020-06-15 01:52:13', '2021-03-10 04:07:25', '3D-005-THANG', '2024-07-15 07:43:24', 'offline', '2024-07-12 12:32:53', 'yx06eb0f-178bfbff00a40f41-minh-thang-pc-microsoft windows nt 6.2.9200.0,Default string-BFEBFBFF000906E9-3D-005-THANG-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(148, 'ngoctuyen', 'ba2f891c49459464112ed4d4cfcccfc6', 'ba2f891c49459464112ed4d4cfcccfc6', 'Phan Ngoc Tuyen', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'minhthanhonly', '2020-07-08 10:00:06', '2021-12-06 06:43:55', '', '', '', '', '', 'timecard', NULL),
(149, 'thiet', '39163740c20a3cb311102e1b96841f98', '39163740c20a3cb311102e1b96841f98', 'Le Nguyen Quang Thiet', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'ngocthuy', 'admin', '2020-08-03 07:54:19', '2021-03-10 04:00:00', '3D_004', '2024-07-15 17:02:07', 'offline', '2024-07-15 11:31:42', 'Default string-BFEBFBFF000506E3-3D_004-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(150, 'tu_web', 'edb0ad4a4d593674896f341906ad4576', 'edb0ad4a4d593674896f341906ad4576', 'Phan Ho Tu', 'member', 7, 'Web', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'admin', 'minhthanhonly', '2020-09-24 07:11:40', '2021-03-10 08:58:12', 'DESKTOP-LPQTGUL', '2024-07-10 16:30:44', 'offline', '2024-07-10 12:19:21', '191263816604870-bfebfbff000906ed-desktop-lpqtgul-microsoft windows nt 6.2.9200.0,BSN12345678901234567-BFEBFBFF00040651-DESKTOP-CHGEQCS-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(153, 'hieu_web', '82c1cf84baa61c5f5258c351c4d176c0', 'e69b1183ede30621ab4916bdd9c764c8', 'Tran Trung Hieu', 'member', 4, 'Đã Nghỉ', 'hieu_web@caily.com.vn', '', NULL, NULL, '', NULL, NULL, '', 0, 0, '', '', 'admin', 'admin', '2021-04-28 04:09:31', '2024-05-14 08:50:21', '', '', '', '', '', 'timecard', NULL),
(155, 'Kajita', 'a964e43cb0a41d8545fe03b6bd25b0a9', 'a964e43cb0a41d8545fe03b6bd25b0a9', 'Kajita Atsuo', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '', '', 'trang', 'minhthanhonly', '2021-12-07 07:45:56', '2024-06-03 07:56:46', '', '', '', '', '', 'timecard', NULL),
(156, 'Okada', 'fc56ff610f8792ba327d92094bd2dd90', 'fc56ff610f8792ba327d92094bd2dd90', 'Okada Yohei', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', 'minhthanhonly', '2021-12-07 07:47:24', '2023-06-30 02:33:12', '', '', '', '', '', 'timecard', NULL),
(158, 'vinh', 'cd8987cd715dc02a9528999f09302048', 'cd8987cd715dc02a9528999f09302048', 'Dang Quang Vinh', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', 'admin', '2022-06-20 07:46:41', '2024-06-19 07:38:25', 'KT_008', '2024-07-12 16:19:00', 'offline', '2024-07-12 07:10:32', 'Default string-BFEBFBFF000906E9-KT_008-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(159, 'hoangphong', 'd9beb22e47e20a648af4d05c80b90651', 'd9beb22e47e20a648af4d05c80b90651', 'Hoang Thi Phong', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', 'minhthanhonly', '2022-07-06 06:30:16', '2024-01-22 01:11:02', '', '', '', '', '', 'timecard', NULL),
(160, 'long', 'f4296364bcb869f17792a6d44ac44986', 'f4296364bcb869f17792a6d44ac44986', 'Nguyen Thanh Long', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', NULL, '2022-11-08 01:41:26', NULL, '3D_LONG', '2024-07-15 17:01:44', 'offline', '2024-07-15 11:30:41', 'Default string-BFEBFBFF000506E3-3D_LONG-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(161, 'ly', '9186c292bf64e7482823c1104527811f', '9186c292bf64e7482823c1104527811f', 'Nguyen Thi Ly', 'member', 14, '3D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', NULL, '2022-11-08 01:42:46', NULL, '3D_006', '2024-07-15 07:41:18', 'offline', '2024-07-12 11:31:52', 'Default string-BFEBFBFF000506E3-3D_006-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(162, 'ngoc', 'd6a1f3eab060a4252bfeb41eb64541e1', 'd6a1f3eab060a4252bfeb41eb64541e1', 'Nguyen Tieu Ngoc', 'member', 10, 'Thiet Bi C', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', NULL, '2022-11-08 01:49:13', NULL, 'TB_20-PC', '2024-07-15 09:34:21', 'offline', '2024-07-12 11:46:56', '160163973006570-BFEBFBFF000306C3-TB_20-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(163, 'trieu', 'f4cf00addee6db26953b3ca615ef9720', 'f4cf00addee6db26953b3ca615ef9720', 'Phan Tan Trieu', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', NULL, '2022-11-14 06:47:32', NULL, 'KT_013', '2024-07-15 12:15:54', 'offline', '2024-07-15 11:49:35', '140830664304471-BFEBFBFF000306A9-KT_013-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(164, 'vantoan', 'a2ecbfc9b01d656709df716305e836b0', 'a2ecbfc9b01d656709df716305e836b0', 'Doan Van Toan', 'member', 17, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', 'admin', '2023-02-14 01:33:01', '2024-05-14 08:52:46', 'CAILY-PC', '2024-07-12 15:53:38', 'offline', '2024-07-12 11:36:18', 'Default string-BFEBFBFF000906E9-CAILY-PC-Microsoft Windows NT 6.1.7601 Service Pack 1', 'timecard', NULL),
(165, 'baohoa', 'ec374a83b52782d8094d9e535d6950aa', 'ec374a83b52782d8094d9e535d6950aa', 'Le Vu Bao Hoa', 'member', 4, 'Kien Truc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '', '', 'trang', 'trang', '2023-03-01 03:34:29', '2023-03-01 03:37:23', '', '', '', '', '', 'timecard', NULL),
(166, 'thienquan', 'b6f208f9cf72c85639b7f54c5a4d852f', 'b6f208f9cf72c85639b7f54c5a4d852f', 'Pham Thien Quan', 'member', 12, 'Thiet Bi E', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', 'admin', '2023-03-06 03:29:12', '2024-05-14 08:49:14', 'TB_016', '2024-07-15 07:23:18', 'online', '2024-07-12 11:26:03', '150239751605660-BFEBFBFF000306C3-TB_016-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(167, 'luan', '7efa518d9ea3a3c8f0037bdc7d485df6', '7efa518d9ea3a3c8f0037bdc7d485df6', 'Vo Minh Luan', 'editor', 18, 'Ket Cau', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'trang', 'minhthanhonly', '2023-06-19 01:06:58', '2024-07-31 15:25:52', 'DESKTOP-QONTSLF', '2024-07-12 16:51:25', 'offline', '2024-07-12 15:38:22', 'default string-bfebfbff00090672-desktop-qontslf-microsoft windows nt 6.2.9200.0,C02708300GUHMHCA3-BFEBFBFF000406E3-VOLUAN-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(168, 'tra_web', 'a79ff6efc6b6cb057c3e4c1dc4c05154', 'a79ff6efc6b6cb057c3e4c1dc4c05154', 'Tran Thi My Tra', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'minhthanhonly', 'minhthanhonly', '2023-06-30 02:28:27', '2025-01-08 07:35:58', 'DESKTOP-PUP726E', '2024-07-10 16:44:03', 'offline', '2024-07-10 15:27:57', '150853046601743-bfebfbff000306c3-desktop-4v1evep-microsoft windows nt 6.2.9200.0,                    -BFEBFBFF000506E3-DESKTOP-PUP726E-Microsoft Windows NT 6.2.9200.0', 'timecard', NULL),
(169, 'takada_kc', 'bf17bf4723c0defa1cffc090a62209c4', 'bf17bf4723c0defa1cffc090a62209c4', 'Takada', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'minhthanhonly', 'minhthanhonly', '2024-05-27 07:29:27', '2024-07-08 15:50:55', '', '', '', '', '', 'timecard', NULL),
(170, 'tester', '3c6b2cbde180f07b64004617bf4eee44', '3c6b2cbde180f07b64004617bf4eee44', 'tester', 'member', 4, 'Đã Nghỉ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, '', '', 'minhthanhonly', 'minhthanhonly', '2024-07-26 16:13:41', '2024-10-01 08:28:48', '', '', '', '', '', 'timecard', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achieve`
--
ALTER TABLE `achieve`
  ADD PRIMARY KEY (`achieve_id`);

--
-- Indexes for table `groupware_addressbook`
--
ALTER TABLE `groupware_addressbook`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_addressbook_folder_id` (`folder_id`),
  ADD KEY `groupware_index_addressbook_type` (`addressbook_type`);

--
-- Indexes for table `groupware_bookmark`
--
ALTER TABLE `groupware_bookmark`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_bookmark_folder_id` (`folder_id`);

--
-- Indexes for table `groupware_config`
--
ALTER TABLE `groupware_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_config_type` (`config_type`(250));

--
-- Indexes for table `groupware_dayoff`
--
ALTER TABLE `groupware_dayoff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_folder`
--
ALTER TABLE `groupware_folder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_folder_type` (`folder_type`(250)),
  ADD KEY `groupware_index_folder_id` (`folder_id`),
  ADD KEY `groupware_index_folder_owner` (`owner`(250));

--
-- Indexes for table `groupware_forum`
--
ALTER TABLE `groupware_forum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_forum_folder_id` (`folder_id`),
  ADD KEY `groupware_index_forum_parent` (`forum_parent`);

--
-- Indexes for table `groupware_group`
--
ALTER TABLE `groupware_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_holiday`
--
ALTER TABLE `groupware_holiday`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_log`
--
ALTER TABLE `groupware_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_message`
--
ALTER TABLE `groupware_message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_message_folder_id` (`folder_id`),
  ADD KEY `groupware_index_message_type` (`message_type`(250)),
  ADD KEY `groupware_index_message_owner` (`owner`(250));

--
-- Indexes for table `groupware_notification`
--
ALTER TABLE `groupware_notification`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_overtime`
--
ALTER TABLE `groupware_overtime`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_project`
--
ALTER TABLE `groupware_project`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_project_folder_id` (`folder_id`),
  ADD KEY `groupware_index_project_parent` (`project_parent`);

--
-- Indexes for table `groupware_schedule`
--
ALTER TABLE `groupware_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_schedule_type` (`schedule_type`),
  ADD KEY `groupware_index_schedule_date` (`schedule_date`(250)),
  ADD KEY `groupware_index_schedule_repeat` (`schedule_repeat`(250)),
  ADD KEY `groupware_index_schedule_begin` (`schedule_begin`(250)),
  ADD KEY `groupware_index_schedule_end` (`schedule_end`(250)),
  ADD KEY `groupware_index_schedule_level` (`schedule_level`),
  ADD KEY `groupware_index_schedule_owner` (`owner`(250));

--
-- Indexes for table `groupware_storage`
--
ALTER TABLE `groupware_storage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_storage_type` (`storage_type`(250)),
  ADD KEY `groupware_index_storage_folder` (`storage_folder`);

--
-- Indexes for table `groupware_timecard`
--
ALTER TABLE `groupware_timecard`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groupware_todo`
--
ALTER TABLE `groupware_todo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `groupware_index_todo_folder_id` (`folder_id`),
  ADD KEY `groupware_index_todo_owner` (`owner`(250));

--
-- Indexes for table `groupware_user`
--
ALTER TABLE `groupware_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `groupware_index_userid` (`userid`(255)) USING HASH,
  ADD KEY `groupware_index_user_group` (`user_group`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achieve`
--
ALTER TABLE `achieve`
  MODIFY `achieve_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_addressbook`
--
ALTER TABLE `groupware_addressbook`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `groupware_bookmark`
--
ALTER TABLE `groupware_bookmark`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_config`
--
ALTER TABLE `groupware_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `groupware_dayoff`
--
ALTER TABLE `groupware_dayoff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=738;

--
-- AUTO_INCREMENT for table `groupware_folder`
--
ALTER TABLE `groupware_folder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `groupware_forum`
--
ALTER TABLE `groupware_forum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `groupware_group`
--
ALTER TABLE `groupware_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `groupware_holiday`
--
ALTER TABLE `groupware_holiday`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `groupware_log`
--
ALTER TABLE `groupware_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48454;

--
-- AUTO_INCREMENT for table `groupware_message`
--
ALTER TABLE `groupware_message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_notification`
--
ALTER TABLE `groupware_notification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10098;

--
-- AUTO_INCREMENT for table `groupware_overtime`
--
ALTER TABLE `groupware_overtime`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=223;

--
-- AUTO_INCREMENT for table `groupware_project`
--
ALTER TABLE `groupware_project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `groupware_schedule`
--
ALTER TABLE `groupware_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `groupware_storage`
--
ALTER TABLE `groupware_storage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `groupware_timecard`
--
ALTER TABLE `groupware_timecard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153264;

--
-- AUTO_INCREMENT for table `groupware_todo`
--
ALTER TABLE `groupware_todo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupware_user`
--
ALTER TABLE `groupware_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
