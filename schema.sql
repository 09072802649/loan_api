-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 08:14 PM
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
-- Database: `loan_monitoring_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `loan_account_number` varchar(20) DEFAULT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `remaining_balance` decimal(10,2) DEFAULT 0.00,
  `interest_rate` decimal(10,2) DEFAULT NULL,
  `loan_term` int(11) DEFAULT 1,
  `payment_frequency` enum('daily','weekly','monthly') DEFAULT 'monthly',
  `total_amount` decimal(12,2) DEFAULT NULL,
  `installment_amount` decimal(10,2) DEFAULT NULL,
  `number_of_payments` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','ongoing','paid','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `borrower_name` varchar(255) DEFAULT NULL,
  `borrower_contact` varchar(50) DEFAULT NULL,
  `borrower_address` text DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `borrower_id`, `collector_id`, `loan_account_number`, `principal_amount`, `total_paid`, `remaining_balance`, `interest_rate`, `loan_term`, `payment_frequency`, `total_amount`, `installment_amount`, `number_of_payments`, `start_date`, `status`, `created_at`, `borrower_name`, `borrower_contact`, `borrower_address`, `balance`, `due_date`) VALUES
(2, 10, 6, 'GC-2026-000002', 2000.00, 2400.00, 0.00, 20.00, 2, 'daily', 0.00, 0.00, 52, '2026-03-15', 'completed', '2026-03-15 07:28:20', 'chase', '0612346494', 'gahahajaja', 0.00, NULL),
(3, 11, 6, 'GC-2026-000003', 1500.00, 800.00, 700.00, 20.00, 2, 'daily', 0.00, 0.00, 52, '2026-03-17', 'ongoing', '2026-03-15 08:00:38', 'Chasie', '09213425299', '#14  lombus st.', 700.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_installments`
--

CREATE TABLE `loan_installments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','paid','overdue') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_installments`
--

INSERT INTO `loan_installments` (`id`, `loan_id`, `installment_number`, `due_date`, `amount`, `status`, `paid_at`, `created_at`) VALUES
(1, 2, 1, '2026-03-16', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(2, 2, 2, '2026-03-17', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(3, 2, 3, '2026-03-18', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(4, 2, 4, '2026-03-19', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(5, 2, 5, '2026-03-20', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(6, 2, 6, '2026-03-21', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(7, 2, 7, '2026-03-23', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(8, 2, 8, '2026-03-24', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(9, 2, 9, '2026-03-25', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(10, 2, 10, '2026-03-26', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(11, 2, 11, '2026-03-27', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(12, 2, 12, '2026-03-28', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(13, 2, 13, '2026-03-30', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(14, 2, 14, '2026-03-31', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(15, 2, 15, '2026-04-01', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(16, 2, 16, '2026-04-02', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(17, 2, 17, '2026-04-03', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(18, 2, 18, '2026-04-04', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(19, 2, 19, '2026-04-06', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(20, 2, 20, '2026-04-07', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(21, 2, 21, '2026-04-08', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(22, 2, 22, '2026-04-09', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(23, 2, 23, '2026-04-10', 0.00, 'pending', NULL, '2026-03-15 07:33:15'),
(24, 2, 24, '2026-04-11', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(25, 2, 25, '2026-04-13', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(26, 2, 26, '2026-04-14', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(27, 2, 27, '2026-04-15', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(28, 2, 28, '2026-04-16', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(29, 2, 29, '2026-04-17', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(30, 2, 30, '2026-04-18', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(31, 2, 31, '2026-04-20', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(32, 2, 32, '2026-04-21', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(33, 2, 33, '2026-04-22', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(34, 2, 34, '2026-04-23', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(35, 2, 35, '2026-04-24', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(36, 2, 36, '2026-04-25', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(37, 2, 37, '2026-04-27', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(38, 2, 38, '2026-04-28', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(39, 2, 39, '2026-04-29', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(40, 2, 40, '2026-04-30', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(41, 2, 41, '2026-05-01', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(42, 2, 42, '2026-05-02', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(43, 2, 43, '2026-05-04', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(44, 2, 44, '2026-05-05', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(45, 2, 45, '2026-05-06', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(46, 2, 46, '2026-05-07', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(47, 2, 47, '2026-05-08', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(48, 2, 48, '2026-05-09', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(49, 2, 49, '2026-05-11', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(50, 2, 50, '2026-05-12', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(51, 2, 51, '2026-05-13', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(52, 2, 52, '2026-05-14', 0.00, 'pending', NULL, '2026-03-15 07:33:16'),
(53, 3, 1, '2026-03-17', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(54, 3, 2, '2026-03-18', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(55, 3, 3, '2026-03-19', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(56, 3, 4, '2026-03-20', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(57, 3, 5, '2026-03-21', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(58, 3, 6, '2026-03-23', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(59, 3, 7, '2026-03-24', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(60, 3, 8, '2026-03-25', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(61, 3, 9, '2026-03-26', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(62, 3, 10, '2026-03-27', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(63, 3, 11, '2026-03-28', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(64, 3, 12, '2026-03-30', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(65, 3, 13, '2026-03-31', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(66, 3, 14, '2026-04-01', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(67, 3, 15, '2026-04-02', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(68, 3, 16, '2026-04-03', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(69, 3, 17, '2026-04-04', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(70, 3, 18, '2026-04-06', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(71, 3, 19, '2026-04-07', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(72, 3, 20, '2026-04-08', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(73, 3, 21, '2026-04-09', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(74, 3, 22, '2026-04-10', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(75, 3, 23, '2026-04-11', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(76, 3, 24, '2026-04-13', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(77, 3, 25, '2026-04-14', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(78, 3, 26, '2026-04-15', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(79, 3, 27, '2026-04-16', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(80, 3, 28, '2026-04-17', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(81, 3, 29, '2026-04-18', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(82, 3, 30, '2026-04-20', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(83, 3, 31, '2026-04-21', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(84, 3, 32, '2026-04-22', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(85, 3, 33, '2026-04-23', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(86, 3, 34, '2026-04-24', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(87, 3, 35, '2026-04-25', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(88, 3, 36, '2026-04-27', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(89, 3, 37, '2026-04-28', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(90, 3, 38, '2026-04-29', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(91, 3, 39, '2026-04-30', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(92, 3, 40, '2026-05-01', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(93, 3, 41, '2026-05-02', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(94, 3, 42, '2026-05-04', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(95, 3, 43, '2026-05-05', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(96, 3, 44, '2026-05-06', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(97, 3, 45, '2026-05-07', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(98, 3, 46, '2026-05-08', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(99, 3, 47, '2026-05-09', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(100, 3, 48, '2026-05-11', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(101, 3, 49, '2026-05-12', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(102, 3, 50, '2026-05-13', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(103, 3, 51, '2026-05-14', 0.00, 'pending', NULL, '2026-03-15 08:01:30'),
(104, 3, 52, '2026-05-15', 0.00, 'pending', NULL, '2026-03-15 08:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `loan_requests`
--

CREATE TABLE `loan_requests` (
  `id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(10,2) DEFAULT NULL,
  `loan_term` int(11) DEFAULT NULL,
  `payment_frequency` enum('daily','weekly','monthly') DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `installment_amount` decimal(10,2) DEFAULT NULL,
  `number_of_payments` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_requests`
--

INSERT INTO `loan_requests` (`id`, `borrower_id`, `collector_id`, `principal_amount`, `interest_rate`, `loan_term`, `payment_frequency`, `total_amount`, `installment_amount`, `number_of_payments`, `status`, `created_at`, `updated_at`) VALUES
(1, 10, 6, 2000.00, 20.00, 2, 'daily', 2400.00, 46.15, 52, 'pending', '2026-03-16 18:30:45', '2026-03-16 18:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'unread',
  `title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`, `type`, `related_id`, `status`, `title`) VALUES
(1, NULL, 'chase submitted a new loan request for PHP 2,000.00.', 0, '2026-03-15 07:28:20', 'loan_request', 2, 'unread', 'New loan request submitted'),
(2, NULL, 'chase\'s loan request was rejected.', 0, '2026-03-15 07:28:32', 'loan_rejected', 1, 'unread', 'Loan rejected'),
(3, NULL, 'chase has been approved and the borrower account was created.', 0, '2026-03-15 07:33:16', 'loan_approved', 2, 'unread', 'Loan approved'),
(4, NULL, 'Chasie submitted a new loan request for PHP 1,500.00.', 0, '2026-03-15 08:00:38', 'loan_request', 3, 'unread', 'New loan request submitted'),
(5, NULL, 'Chasie has been approved and the borrower account was created.', 0, '2026-03-15 08:01:30', 'loan_approved', 3, 'unread', 'Loan approved'),
(6, NULL, 'chase has an overdue installment #1 due on Mar 16, 2026.', 0, '2026-03-16 17:46:52', 'overdue_payment', 1, 'unread', 'Overdue payment alert'),
(7, 5, 'Payment of ₱2,100.00 received from borrower ID 10', 0, '2026-03-16 18:10:40', 'payment_recorded', 9, 'unread', 'New Payment Recorded'),
(8, NULL, 'chase submitted a reloan request for PHP 2,000.00.', 0, '2026-03-16 18:30:45', 'reloan_request', 1, 'unread', 'New reloan request submitted'),
(9, 5, 'Payment of ₱800.00 received from borrower ID 11', 0, '2026-03-16 18:53:33', 'payment_recorded', 10, 'unread', 'New Payment Recorded');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `remitted` tinyint(1) DEFAULT 0,
  `remittance_id` int(11) DEFAULT NULL,
  `borrower_id` int(11) DEFAULT NULL,
  `collector_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `loan_id`, `installment_id`, `amount`, `payment_date`, `proof_image`, `status`, `remitted`, `remittance_id`, `borrower_id`, `collector_id`) VALUES
(5, 2, NULL, 300.00, '2026-03-17 01:50:36', '1773683436_proof_1773683434957.jpg', 'approved', 0, NULL, 10, 6),
(9, 2, NULL, 2100.00, '2026-03-17 02:10:40', '1773684640_proof_1773684640657.jpg', 'approved', 0, NULL, 10, 6),
(10, 3, NULL, 800.00, '2026-03-17 02:53:33', '1773687213_proof_1773687213730.jpg', 'approved', 0, NULL, 11, 6);

-- --------------------------------------------------------

--
-- Table structure for table `remittances`
--

CREATE TABLE `remittances` (
  `id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `remittance_date` datetime DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `transaction_count` int(11) DEFAULT NULL,
  `status` enum('pending','received') DEFAULT 'pending',
  `received_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `username` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','collector','borrower') DEFAULT 'borrower',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `collector_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `username`, `password`, `role`, `status`, `created_at`, `last_login`, `phone`, `address`, `collector_id`) VALUES
(5, 'System Admin', 'admin', '$2y$10$1ajKAStQzOnayCIiNs6y5uSDWqYYAXJpvK6ySjPFZKtVjYcZHNe5e', 'admin', 'active', '2026-03-15 07:11:27', '2026-03-17 01:46:52', NULL, NULL, NULL),
(6, 'satu', 'satu', '$2y$10$j3CnPNikEmEgyVTAbUdAQuzcXRq6fpxHejmFl7CRSsrShjnkoptRS', 'collector', 'active', '2026-03-15 07:24:56', NULL, '09132154564', 'kajskjhjdkhasjkd', NULL),
(10, 'chase', 'chase', '$2y$10$rS76vY/xd8zGkfNqEsJ9YOvM8q9kQVW6cyk/yDGlVQdg0AKwsWU9C', 'borrower', 'active', '2026-03-15 07:33:15', NULL, '0612346494', 'gahahajaja', 6),
(11, 'Chasie', 'chasie', '$2y$10$gk.gOGF.rcljW37gL6C/zeFpKObm8HgcplglMazSW/1lJyEPNcB/y', 'borrower', 'active', '2026-03-15 08:01:30', NULL, '09213425299', '#14  lombus st.', 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_loans_borrower` (`borrower_id`);

--
-- Indexes for table `loan_installments`
--
ALTER TABLE `loan_installments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loan_requests`
--
ALTER TABLE `loan_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `remittances`
--
ALTER TABLE `remittances`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `loan_installments`
--
ALTER TABLE `loan_installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `loan_requests`
--
ALTER TABLE `loan_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `remittances`
--
ALTER TABLE `remittances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `fk_loans_borrower` FOREIGN KEY (`borrower_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
