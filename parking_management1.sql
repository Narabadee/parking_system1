-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2025 at 09:11 AM
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
-- Database: `parking_management1`
--

-- --------------------------------------------------------

--
-- Table structure for table `auth_users`
--

CREATE TABLE `auth_users` (
  `auth_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_users`
--

INSERT INTO `auth_users` (`auth_id`, `user_id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 1, 'admin', '$2y$10$3Zt7hXJQLH5.t2QAw1ycTuF4G9ULZeKQEEUgJZJH1MKDCjTRDpBOK', 'admin', '2025-03-29 05:37:15'),
(2, 1, 'user1', '$2y$10$3Zt7hXJQLH5.t2QAw1ycTuF4G9ULZeKQEEUgJZJH1MKDCjTRDpBOK', 'user', '2025-03-29 05:37:15'),
(3, 2, 'narabadee', '$2y$10$B.3sHRomN7DeyL9ndMEGke0Ob7p.3O0WIUnKfGxjgDwOvDwn.hLoq', 'admin', '2025-03-29 05:38:01'),
(4, 3, 'usertest', '$2y$10$a54l/ol9erYZBahjxZ.KuO/E2rUNVKeNXH8MdObHYm28SvjOyP6RS', 'user', '2025-03-29 05:58:41');

-- --------------------------------------------------------

--
-- Table structure for table `parking_records`
--

CREATE TABLE `parking_records` (
  `record_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `spot_id` int(11) NOT NULL,
  `vehicle_number` varchar(20) NOT NULL,
  `entry_time` datetime NOT NULL,
  `exit_time` datetime DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reservation_time` datetime DEFAULT current_timestamp(),
  `confirmation_status` enum('pending','confirmed','cancelled','expired') DEFAULT 'pending',
  `vehicle_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parking_spots`
--

CREATE TABLE `parking_spots` (
  `spot_id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `spot_number` varchar(10) NOT NULL,
  `status` enum('free','occupied') NOT NULL DEFAULT 'free',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_spots`
--

INSERT INTO `parking_spots` (`spot_id`, `zone_id`, `spot_number`, `status`, `created_at`) VALUES
(1, 1, 'A1', 'free', '2025-03-29 05:32:07'),
(2, 1, 'A2', 'free', '2025-03-29 05:32:07'),
(3, 1, 'A3', 'free', '2025-03-29 05:32:07'),
(4, 1, 'A4', 'free', '2025-03-29 05:32:07'),
(5, 1, 'A5', 'free', '2025-03-29 05:32:07'),
(6, 2, 'B1', 'free', '2025-03-29 05:32:07'),
(7, 2, 'B2', 'free', '2025-03-29 05:32:07'),
(8, 2, 'B3', 'free', '2025-03-29 05:32:07'),
(9, 2, 'B4', 'free', '2025-03-29 05:32:07'),
(10, 2, 'B5', 'free', '2025-03-29 05:32:07'),
(11, 3, 'C1', 'free', '2025-03-29 05:32:07'),
(12, 3, 'C2', 'free', '2025-03-29 05:32:07'),
(13, 3, 'C3', 'free', '2025-03-29 05:32:07'),
(14, 3, 'C4', 'free', '2025-03-29 05:32:07'),
(15, 3, 'C5', 'free', '2025-03-29 05:32:07'),
(16, 4, 'D1', 'free', '2025-03-29 05:32:07'),
(17, 4, 'D2', 'free', '2025-03-29 05:32:07'),
(18, 4, 'D3', 'free', '2025-03-29 05:32:07'),
(19, 4, 'D4', 'free', '2025-03-29 05:32:07'),
(20, 4, 'D5', 'free', '2025-03-29 05:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `parking_zones`
--

CREATE TABLE `parking_zones` (
  `zone_id` int(11) NOT NULL,
  `zone_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_zones`
--

INSERT INTO `parking_zones` (`zone_id`, `zone_name`, `description`, `created_at`) VALUES
(1, 'Zone A', 'Main entrance parking zone', '2025-03-29 05:32:07'),
(2, 'Zone B', 'Side entrance parking zone', '2025-03-29 05:32:07'),
(3, 'Zone C', 'Back entrance parking zone', '2025-03-29 05:32:07'),
(4, 'Zone D', 'VIP parking zone', '2025-03-29 05:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `duration_months` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`plan_id`, `plan_name`, `duration_months`, `price`, `description`, `created_at`) VALUES
(1, 'Monthly', 1, 500.00, 'Monthly subscription with discounted parking rates', '2025-03-29 05:32:07'),
(2, 'Yearly', 12, 5000.00, 'Yearly subscription with discounted parking rates', '2025-03-29 05:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL DEFAULT 1,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `tel` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `type_id`, `first_name`, `last_name`, `tel`, `created_at`, `updated_at`) VALUES
(1, 1, 'Admin', 'User', '0123456789', '2025-03-29 05:32:07', '2025-03-29 05:32:07'),
(2, 1, 'Narabadee', 'Yapolha', '0649102354', '2025-03-29 05:38:01', '2025-03-29 05:38:01'),
(3, 2, 'สมชาย', 'asd', '092857463', '2025-03-29 05:58:40', '2025-04-18 09:47:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_subscriptions`
--

CREATE TABLE `user_subscriptions` (
  `subscription_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_types`
--

CREATE TABLE `user_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_types`
--

INSERT INTO `user_types` (`type_id`, `type_name`, `description`, `created_at`) VALUES
(1, 'normal', 'Regular user with standard parking rates', '2025-03-29 05:32:07'),
(2, 'sub', 'Subscription user with discounted parking rates', '2025-03-29 05:32:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_users`
--
ALTER TABLE `auth_users`
  ADD PRIMARY KEY (`auth_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parking_records`
--
ALTER TABLE `parking_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `spot_id` (`spot_id`);

--
-- Indexes for table `parking_spots`
--
ALTER TABLE `parking_spots`
  ADD PRIMARY KEY (`spot_id`),
  ADD UNIQUE KEY `zone_spot_unique` (`zone_id`,`spot_number`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Indexes for table `parking_zones`
--
ALTER TABLE `parking_zones`
  ADD PRIMARY KEY (`zone_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `subscription_id` (`subscription_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`plan_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `type_id` (`type_id`);

--
-- Indexes for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `user_types`
--
ALTER TABLE `user_types`
  ADD PRIMARY KEY (`type_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auth_users`
--
ALTER TABLE `auth_users`
  MODIFY `auth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `parking_records`
--
ALTER TABLE `parking_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `parking_spots`
--
ALTER TABLE `parking_spots`
  MODIFY `spot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `parking_zones`
--
ALTER TABLE `parking_zones`
  MODIFY `zone_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_types`
--
ALTER TABLE `user_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_users`
--
ALTER TABLE `auth_users`
  ADD CONSTRAINT `auth_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `parking_records`
--
ALTER TABLE `parking_records`
  ADD CONSTRAINT `parking_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `parking_records_ibfk_2` FOREIGN KEY (`spot_id`) REFERENCES `parking_spots` (`spot_id`);

--
-- Constraints for table `parking_spots`
--
ALTER TABLE `parking_spots`
  ADD CONSTRAINT `parking_spots_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `parking_zones` (`zone_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `parking_records` (`record_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `user_subscriptions` (`subscription_id`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `user_types` (`type_id`);

--
-- Constraints for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `user_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`plan_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
