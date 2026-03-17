-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 17, 2026 at 03:42 PM
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
-- Database: `ccs_sitin`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `message`, `created_at`) VALUES
(3, 'Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!', '2026-03-17 00:58:15'),
(12, 'TIDERT', '2026-03-17 14:33:57');

-- --------------------------------------------------------

--
-- Table structure for table `sitin_records`
--

CREATE TABLE `sitin_records` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `lab` varchar(100) NOT NULL,
  `remaining_sessions` int(11) NOT NULL DEFAULT 30,
  `time_in` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_out` timestamp NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitin_records`
--

INSERT INTO `sitin_records` (`id`, `id_number`, `purpose`, `lab`, `remaining_sessions`, `time_in`, `time_out`, `status`) VALUES
(6, '21539101', 'JAVA', '302', 30, '2026-03-17 03:25:25', NULL, 'Active'),
(7, '21539102', 'C', '300', 30, '2026-03-17 03:29:12', NULL, 'Active'),
(8, '21539103', 'C', '524', 30, '2026-03-17 14:32:40', NULL, 'Active'),
(9, '21539103', 'C', '524', 30, '2026-03-17 14:32:58', NULL, 'Active'),
(10, '21539103', 'Java', '302', 30, '2026-03-17 14:33:22', NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'student',
  `photo` varchar(255) DEFAULT NULL,
  `sessions_remaining` int(11) DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `id_number`, `last_name`, `first_name`, `middle_name`, `course`, `email`, `password`, `address`, `created_at`, `status`, `role`, `photo`, `sessions_remaining`) VALUES
(11, '21539101', 'Silva', 'Michael', 'Jackson', 'BS Elementary Education', 'michaeljohnsilva6@gmail.com', '$2y$10$h0K3VG.LFuvyS0/b8KIEquzJ5y.ZehkOM.4eBsl8P1Xp.Vwrl63m6', 'Cebu City', '2026-03-14 15:13:38', 'active', 'student', 'uploads/1773526779_Screenshot 2026-03-15 053918.png', 27),
(22, '21539101', 'Silva', 'Michael', 'John', 'BS Elementary Education', 'michaeljohnsilva55@gmail.com', '$2y$10$twkODgrqmOjsCOew4CE7/ecLSGMuTm5yEeVHCwHW.xYKx5zwCMyhi', 'Cebu City', '2026-03-14 15:45:44', 'active', 'student', 'uploads/profile_22.png', 27),
(27, '00000001', 'Admin', 'System', '', 'Administrator', 'admin@ccs.com', '$2y$10$8ltlTYfSn4BmyWVOOTfXUuQ8Smt5T.vx/sOnRWDq.7pTTDxR4PEGG', 'System', '2026-03-14 16:00:37', 'active', 'admin', NULL, 30),
(28, '21539102', 'Woo', 'Michael John', 'Silva', 'BS Hotel & Restaurant Management', 'mjsilva@gmail.com', '$2y$10$604VTM75APNSCa89PKlrb.solH44xLa0NYYJPznPT5Za17nmh5G5W', 'Cebu City', '2026-03-17 03:28:22', '', 'student', NULL, 29),
(29, '21539103', 'Silva', 'MJ', 'Santillan', 'BS Information Technology', 'mjsilva1@gmail.com', '$2y$10$W/gbLXoxHGlE98J2S6hlYuboK1/f3m23WVgmNj3Oe5aDJRP2PnRmu', 'Cebu City', '2026-03-17 14:14:39', '', 'student', NULL, 27);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sitin_records`
--
ALTER TABLE `sitin_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `sitin_records`
--
ALTER TABLE `sitin_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
