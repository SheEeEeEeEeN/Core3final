-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 18, 2025 at 03:06 PM
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
-- Database: `core3`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `email`, `password`, `profile_image`, `role`, `created_at`) VALUES
(19, 'admin', 'admin@gmail.com', '$2y$10$bPUzSA4onQcreQ0hyWvKX.yu6kcG2uSbpNPyFVSldUkfuo4.W.cJ2', NULL, 'admin', '2025-09-13 09:38:47'),
(20, 'user1', 'user@gmail.com', '$2y$10$Gnwx9578IK.ZsBFFKxG.R.Cp5lGyIe3l0HGdQBgDilZsEznNX.HhW', 'upload/1758179937_Screenshot 2025-08-28 222310.png', 'user', '2025-09-13 09:39:40'),
(21, 'roy', 'royzxcasd@gmail.com', '$2y$10$HnK30G7exekbMK2L5hmCpOiE9rUrbuohh5rmh0wcJhHzT9OkPSj.G', 'upload/1758010493_Screenshot 2025-07-25 211601.png', 'user', '2025-09-16 01:27:29'),
(22, 'valle', 'valle.roy458@gmail.com', '$2y$10$5pJ488y0WMtxgKF5rAZIX.olfESh036blKYf8/oDexAvt0vtYfdlS', NULL, 'user', '2025-09-16 09:02:33');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity`
--

CREATE TABLE `admin_activity` (
  `id` int(11) NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `module` varchar(100) NOT NULL,
  `activity` text NOT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity`
--

INSERT INTO `admin_activity` (`id`, `date`, `module`, `activity`, `status`) VALUES
(3, '2025-09-01 19:02:00', 'CRM', 'Added new customer: nemon (nemon.corp)', 'Success'),
(5, '2025-09-01 19:09:26', 'CRM', 'Deleted customer: bhrruuu4 (PIHAN4)', 'Success'),
(6, '2025-09-01 19:09:47', 'CRM', 'Updated customer: TJK (DJK)', 'Success'),
(7, '2025-09-01 19:22:26', 'CRM', 'Added new customer: neee (neee.corp)', 'Success'),
(8, '2025-09-01 19:58:12', 'CRM', 'Updated customer: neee (neee.corp)', 'Success'),
(9, '2025-09-01 19:58:27', 'CRM', 'Deleted customer: dsad (PKG)', 'Success'),
(10, '2025-09-01 19:59:02', 'CRM', 'Added new customer: santa (santa)', 'Success'),
(11, '2025-09-01 20:01:53', 'CRM', 'Updated customer: Pota (Pota.corp)', 'Success'),
(12, '2025-09-01 20:01:55', 'CRM', 'Updated customer: Pota (Pota.corp)', 'Success'),
(13, '2025-09-01 20:03:42', 'CRM', 'Updated customer: tryt (try.corp342)', 'Success'),
(14, '2025-09-01 20:18:21', 'CRM', 'Updated customer ID 89 → nemon (nemon.corp)', 'Success'),
(15, '2025-09-01 20:18:23', 'CRM', 'Updated customer ID 92 → santa (santa)', 'Success'),
(16, '2025-09-01 23:48:10', 'CRM', 'Deleted customer: 123 (123)', 'Success'),
(17, '2025-09-02 00:10:39', 'CRM', 'Added new customer: tr (tr)', 'Success'),
(18, '2025-09-02 00:10:53', 'CRM', 'Updated customer ID 3: tr (tr)', 'Success'),
(19, '2025-09-02 00:16:23', 'CSM', 'Added new contract: tr - tr', 'Success'),
(20, '2025-09-02 00:17:24', 'CSM', 'Added new contract: tru - tru', 'Success'),
(21, '2025-09-02 00:20:30', 'CSM', 'Added new contract: 786876876 - gygyh', 'Success'),
(22, '2025-09-02 00:40:48', 'E-Documentation', 'Uploaded document: try', 'Pending Review'),
(23, '2025-09-02 00:41:35', 'E-Documentation', 'Edited document: try', 'Pending Review'),
(24, '2025-09-02 00:41:46', 'E-Documentation', 'Deleted document: try', 'Deleted'),
(25, '2025-09-02 03:38:55', 'CRM', 'Updated customer: tr (tr)', 'Success'),
(26, '2025-09-02 03:39:22', 'CRM', 'Added new customer: try (try)', 'Success'),
(27, '2025-09-02 03:39:41', 'CRM', 'Deleted customer: tr (tr)', 'Success'),
(28, '2025-09-02 03:40:56', 'CSM', 'Added new contract: 22052118321 - ulet', 'Success'),
(29, '2025-09-02 03:41:46', 'E-Documentation', 'Uploaded document: 123wqe', 'Pending Review'),
(30, '2025-09-02 03:42:01', 'E-Documentation', 'Edited document: 123wqe', 'Expired'),
(31, '2025-09-02 03:42:55', 'E-Documentation', 'Deleted document: qwee', 'Deleted'),
(32, '2025-09-02 03:44:12', 'E-Documentation', 'Deleted document: w1231', 'Deleted'),
(33, '2025-09-02 16:17:47', 'CRM', 'Updated customer: try (try)', 'Success'),
(34, '2025-09-02 16:31:38', 'CRM', 'Deleted customer: try (try)', 'Success'),
(35, '2025-09-02 17:40:07', 'CSM', 'Added new contract: 873485783478 - Roy', 'Success'),
(36, '2025-09-02 18:01:57', 'CSM', 'Added new contract: 878346573 - All G', 'Success'),
(37, '2025-09-02 18:03:03', 'CRM', 'Added new customer: ALLG (ALLG)', 'Success'),
(38, '2025-09-03 21:52:22', 'CSM', 'Added new contract: 645624324 - Oppss', 'Success'),
(39, '2025-09-03 22:51:50', 'CRM', 'Added new customer: testing (testing.corp)', 'Success'),
(40, '2025-09-03 22:52:00', 'CRM', 'Updated customer: ALLG (ALLG)', 'Success'),
(41, '2025-09-03 22:52:25', 'E-Documentation', 'Edited document: 123wqe', 'Expired'),
(42, '2025-09-03 22:52:35', 'CRM', 'Updated customer: testing (testing.corp)', 'Success'),
(43, '2025-09-03 22:52:43', 'E-Documentation', 'Edited document: 123wqe', 'Expired'),
(44, '2025-09-03 22:53:43', 'E-Documentation', 'Edited document: 123wqe', 'Expired'),
(45, '2025-09-03 22:54:10', 'E-Documentation', 'Deleted document: sadasd', 'Deleted'),
(46, '2025-09-04 00:18:51', 'CRM', 'Updated customer: testing (testing.corp)', 'Success'),
(47, '2025-09-04 21:26:24', 'CSM', 'Added new contract: 4353523123 - Try ule', 'Success'),
(48, '2025-09-04 21:37:56', 'CSM', 'Added new contract:  - ', 'Success'),
(49, '2025-09-04 21:38:25', 'CSM', 'Added new contract:  - ', 'Success'),
(50, '2025-09-04 21:44:23', 'CSM', 'Added new contract: 123 - 123123', 'Success'),
(51, '2025-09-04 21:51:38', 'CSM', 'Added new contract: 0980934758 - Try nga', 'Success'),
(52, '2025-09-04 22:24:24', 'CSM', 'Added new contract: 467456767 - haaa', 'Success'),
(53, '2025-09-04 22:37:23', 'E-Documentation', 'Uploaded document: qweee', 'Pending Review'),
(54, '2025-09-04 22:37:49', 'E-Documentation', 'Edited document: qweee', 'Compliant'),
(55, '2025-09-04 22:42:53', 'CRM', 'Updated customer: testing (testing.corp)', 'Success'),
(56, '2025-09-04 22:43:26', 'CRM', 'Added new customer: werqwe (123123qwe)', 'Success'),
(57, '2025-09-04 22:43:44', 'CRM', 'Deleted customer: werqwe (123123qwe)', 'Success'),
(58, '2025-09-07 17:33:07', 'CRM', 'Updated customer: testing (testing.corp)', 'Success'),
(59, '2025-09-07 17:33:11', 'CRM', 'Updated customer: testing (testing.corp)', 'Success'),
(60, '2025-09-07 17:33:13', 'CRM', 'Updated customer: ALLG (ALLG)', 'Success'),
(61, '2025-09-07 17:33:16', 'CRM', 'Updated customer: ALLG (ALLG)', 'Success'),
(62, '2025-09-07 17:34:06', 'CRM', 'Updated customer: ALLG (ALLG)', 'Success'),
(63, '2025-09-07 17:34:37', 'CRM', 'Deleted customer: testing (testing.corp)', 'Success'),
(64, '2025-09-07 18:12:06', 'CRM', 'Updated customer: ALLG (ALLG)', 'Success'),
(65, '2025-09-07 19:41:26', 'CSM', 'Added new contract: 123123 - 31231w', 'Success'),
(66, '2025-09-07 19:42:53', 'E-Documentation', 'Edited document: qweee', 'Compliant'),
(67, '2025-09-07 19:51:48', 'E-Documentation', 'Deleted document: 123wqe', 'Deleted'),
(68, '2025-09-07 19:55:43', 'E-Documentation', 'Edited document: qweee', 'Compliant'),
(69, '2025-09-07 19:55:55', 'E-Documentation', 'Uploaded document: 123qwe', 'Pending Review'),
(70, '2025-09-08 00:35:38', 'CRM', 'Updated customer: ALLG (ALLG)', 'Success'),
(71, '2025-09-08 23:39:24', 'Accounts', 'Updated account: 123333 (user)', 'Success'),
(72, '2025-09-08 23:39:51', 'Accounts', 'Deleted account: 123333 (user)', 'Success'),
(73, '2025-09-08 23:48:03', 'Accounts', 'Updated account: 097 (user)', 'Success'),
(74, '2025-09-09 00:00:24', 'CRM', 'Updated account: 097 (admin)', 'Success'),
(75, '2025-09-09 00:23:08', 'CRM', 'Deleted account: 00000 (admin)', 'Success'),
(76, '2025-09-10 23:16:54', 'CRM', 'Updated account: 097 (admin)', 'Success'),
(77, '2025-09-13 08:43:58', 'CSM', 'Added new contract: CSM-20250913-084330-6551 - user1', 'Success'),
(78, '2025-09-13 14:37:39', 'CRM', 'Deleted user: qwe', 'Success'),
(79, '2025-09-13 14:41:49', 'CRM', 'Deleted user: Sheen', 'Success'),
(80, '2025-09-13 14:42:44', 'CRM', 'Deleted user: user2', 'Success'),
(81, '2025-09-13 17:37:56', 'CRM', 'Deleted user: user1', 'Success'),
(82, '2025-09-13 17:37:59', 'CRM', 'Deleted user: 097', 'Success'),
(83, '2025-09-13 17:38:05', 'CRM', 'Deleted user: erer', 'Success'),
(84, '2025-09-13 17:38:08', 'CRM', 'Deleted user: 123', 'Success'),
(85, '2025-09-13 17:49:04', 'CSM', 'Added new contract: CSM-20250913-174821-7670 - user1', 'Success'),
(86, '2025-09-14 17:51:09', 'E-Documentation', 'Uploaded document: asd', 'Pending Review'),
(87, '2025-09-14 17:51:42', 'CRM', 'Updated user: user1', 'Success'),
(88, '2025-09-14 17:52:02', 'CRM', 'Updated user: user1', 'Success'),
(89, '2025-09-16 16:04:07', 'CRM', 'Updated user: user1', 'Success'),
(90, '2025-09-16 16:19:57', 'CRM', 'Updated user: user1', 'Success'),
(91, '2025-09-16 16:23:33', 'E-Documentation', 'Edited document: <script>   alert(\"di sya secure\");   window.location.href = \"https://www.youtube.com/watch?v=FPcsJTxnaBQ\"; </script>', 'Pending Review');

-- --------------------------------------------------------

--
-- Table structure for table `bi_costs`
--

CREATE TABLE `bi_costs` (
  `id` int(11) NOT NULL,
  `shipment_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `category` enum('Fuel','Labor','Carrier','Tolls','Other') NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bi_costs`
--

INSERT INTO `bi_costs` (`id`, `shipment_id`, `date`, `category`, `amount`) VALUES
(1, NULL, '2025-09-01', 'Fuel', 1000.00),
(2, NULL, '2025-09-01', 'Labor', 500.00),
(3, NULL, '2025-09-02', 'Carrier', 700.00),
(4, NULL, '2025-09-03', 'Tolls', 200.00),
(5, NULL, '2025-09-05', 'Other', 150.00);

-- --------------------------------------------------------

--
-- Table structure for table `bi_shipments`
--

CREATE TABLE `bi_shipments` (
  `id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('on-time','in-transit','delayed','delivered') NOT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `transit_time` decimal(4,2) DEFAULT NULL,
  `delay_reason` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bi_shipments`
--

INSERT INTO `bi_shipments` (`id`, `created_at`, `status`, `origin`, `destination`, `transit_time`, `delay_reason`) VALUES
(1, '2025-09-18 10:21:14', 'on-time', 'Manila', 'Cebu', 3.50, NULL),
(2, '2025-09-18 10:21:14', 'delayed', 'Manila', 'Davao', 4.20, 'Weather'),
(3, '2025-09-18 10:21:14', 'in-transit', 'Cebu', 'Davao', NULL, NULL),
(4, '2025-09-18 10:21:14', 'on-time', 'Manila', 'HK', 2.80, NULL),
(5, '2025-09-18 10:21:14', 'delayed', 'Manila', 'Cebu', 3.90, 'Traffic');

-- --------------------------------------------------------

--
-- Table structure for table `crm`
--

CREATE TABLE `crm` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `company` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('Active','Prospect','Inactive') DEFAULT 'Prospect',
  `last_contract` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm`
--

INSERT INTO `crm` (`id`, `customer_name`, `company`, `email`, `phone`, `status`, `last_contract`) VALUES
(5, 'ALLG', 'ALLG', 'ALLG@gmail.com', '909878747', 'Active', '2025-09-02 10:03:03');

-- --------------------------------------------------------

--
-- Table structure for table `csm`
--

CREATE TABLE `csm` (
  `id` int(11) NOT NULL,
  `contract_id` varchar(50) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Expired','Pending') NOT NULL,
  `sla_compliance` enum('Compliant','Non-Compliant') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `csm`
--

INSERT INTO `csm` (`id`, `contract_id`, `client_name`, `start_date`, `end_date`, `status`, `sla_compliance`) VALUES
(55, '873485783478', 'Roy', '2025-09-02', '2025-09-10', 'Active', 'Compliant'),
(56, '878346573', 'All G', '2025-09-02', '2025-09-23', 'Expired', 'Compliant'),
(57, '645624324', 'Oppss', '2025-09-03', '2025-09-11', 'Active', 'Compliant'),
(58, '4353523123', 'Try ule', '2025-09-04', '2025-09-06', 'Active', 'Compliant'),
(61, '123', '123123', '2025-09-05', '2025-09-09', 'Active', 'Compliant'),
(62, '0980934758', 'Try nga', '2025-09-04', '2025-09-17', 'Expired', 'Compliant'),
(63, '467456767', 'haaa', '2025-09-05', '2025-09-08', 'Active', 'Non-Compliant'),
(64, '123123', '31231w', '2025-09-05', '2025-09-10', 'Active', 'Compliant'),
(65, 'CSM-20250913-084330-6551', 'user1', '2025-09-20', '2025-09-20', 'Active', 'Compliant'),
(66, 'CSM-20250913-174821-7670', 'user1', '2025-05-12', '2027-02-12', 'Active', 'Compliant');

-- --------------------------------------------------------

--
-- Table structure for table `e_doc`
--

CREATE TABLE `e_doc` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_on` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Pending Review'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `e_doc`
--

INSERT INTO `e_doc` (`id`, `title`, `doc_type`, `filename`, `uploaded_on`, `status`) VALUES
(12, 'qweee', 'Bill of Lading', 'index.php', '2025-09-04 14:37:23', 'Compliant'),
(13, '123qwe', 'Bill of Lading', 'freight_db.sql', '2025-09-07 11:55:55', 'Pending Review');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read') DEFAULT 'unread',
  `reply` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `account_id`, `comment`, `created_at`, `status`, `reply`) VALUES
(18, 20, 'hello', '2025-09-14 09:55:56', 'read', NULL),
(19, 20, 'hello', '2025-09-14 09:56:12', 'unread', NULL),
(20, 20, 'test', '2025-09-16 10:31:49', 'unread', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `replies`
--

CREATE TABLE `replies` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `reply_message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `replies`
--

INSERT INTO `replies` (`id`, `feedback_id`, `admin_id`, `reply_message`, `created_at`) VALUES
(11, 18, 19, 'sjdfhlsdkf', '2025-09-14 09:56:08');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `receiver_name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `weight` decimal(10,2) NOT NULL,
  `package_description` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `user_id`, `sender_name`, `receiver_name`, `address`, `weight`, `package_description`, `status`, `created_at`) VALUES
(1, 5, 'Ako', 'Ikaw', 'Italy', 40.00, 'Ayos', 'Pending', '2025-09-04 11:44:26'),
(2, 5, 'ro', 'ol', 'china', 123.00, 'ok', 'Pending', '2025-09-04 15:17:38'),
(3, 18, 'roy', 'valle', 'manila', 2.00, 'bulak', 'Pending', '2025-09-12 15:40:13'),
(4, 18, 'mcdo', 'zxc', 'lakers', 4.00, 'bato', 'Pending', '2025-09-12 15:42:13'),
(5, 18, '<script>window.location.href=\"https://chatgpt.com/?model=auto\"</script>', '<script>window.location.href=\"https://chatgpt.com/?model=auto\"</script>', '<script>window.location.href=\"https://chatgpt.com/?model=auto\"</script>', 0.00, '<script>window.location.href=\"https://chatgpt.com/?model=auto\"</script>', 'Pending', '2025-09-12 15:50:26'),
(6, 18, 'z', 'd', 'tokyo', 4.00, 'sfs', 'Pending', '2025-09-12 15:53:23'),
(7, 17, 'roy valle', 'mae', 'mekus', 5.00, 'sibuyas', 'Pending', '2025-09-13 04:17:36'),
(8, 20, 'roy valle', 'mae', 'manila', 2.00, 'kamote', 'Pending', '2025-09-13 10:21:04'),
(9, 22, 'valle', 'roy', 'manila', 5.00, 'kamote', 'Pending', '2025-09-16 09:32:25'),
(10, 22, 'roy valle', 'mae', 'caloocan', 5.00, 'sgdfg', 'Pending', '2025-09-16 10:16:34'),
(11, 20, 'test', 'test', 'test', 0.00, 'test', 'Pending', '2025-09-16 10:31:24'),
(12, 0, 'Alice', 'Bob', '123 Main Street', 25.00, 'Electronics', 'Pending', '2025-09-16 15:48:47'),
(13, 0, 'Alice', 'Bob', '123 Main Street', 25.00, 'Electronics', 'Pending', '2025-09-16 16:01:19'),
(14, 20, 'roy valle', 'mae', '', 1.00, 'bigas', 'Pending', '2025-09-16 16:02:41'),
(15, 20, 'moy', 'roy', 'phs 7c blk 58 lot 11 pkg 7 bagong silang caloocan city', 5.00, 'sibuyas', 'Pending', '2025-09-16 16:22:46'),
(16, 20, 'dfk;', 'sk;fdlk\'', 'adlfks\'dfk', 3.00, 'k\'rwer', 'Pending', '2025-09-17 01:48:12'),
(17, 20, 'api', 'api', 'api', 4.00, 'api', 'Pending', '2025-09-17 01:52:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_data`
--

CREATE TABLE `user_data` (
  `data_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bi_costs`
--
ALTER TABLE `bi_costs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shipment_id` (`shipment_id`);

--
-- Indexes for table `bi_shipments`
--
ALTER TABLE `bi_shipments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm`
--
ALTER TABLE `crm`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `csm`
--
ALTER TABLE `csm`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `e_doc`
--
ALTER TABLE `e_doc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `replies`
--
ALTER TABLE `replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_id` (`feedback_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_data`
--
ALTER TABLE `user_data`
  ADD PRIMARY KEY (`data_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `admin_activity`
--
ALTER TABLE `admin_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `bi_costs`
--
ALTER TABLE `bi_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bi_shipments`
--
ALTER TABLE `bi_shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `crm`
--
ALTER TABLE `crm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `csm`
--
ALTER TABLE `csm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `e_doc`
--
ALTER TABLE `e_doc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `replies`
--
ALTER TABLE `replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_data`
--
ALTER TABLE `user_data`
  MODIFY `data_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bi_costs`
--
ALTER TABLE `bi_costs`
  ADD CONSTRAINT `bi_costs_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `replies`
--
ALTER TABLE `replies`
  ADD CONSTRAINT `replies_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `replies_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_data`
--
ALTER TABLE `user_data`
  ADD CONSTRAINT `user_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
