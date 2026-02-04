-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 24, 2026 at 08:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrms_paluan`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT './img/admin1.png',
  `user_role` varchar(50) DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `am_time_in` time DEFAULT NULL,
  `am_time_out` time DEFAULT NULL,
  `pm_time_in` time DEFAULT NULL,
  `pm_time_out` time DEFAULT NULL,
  `ot_hours` decimal(5,2) DEFAULT 0.00,
  `under_time` decimal(5,2) DEFAULT 0.00,
  `total_hours` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `date`, `employee_id`, `employee_name`, `department`, `am_time_in`, `am_time_out`, `pm_time_in`, `pm_time_out`, `ot_hours`, `under_time`, `total_hours`, `created_at`, `updated_at`) VALUES
(1, '2024-01-15', 'EMP001', 'John Doe', 'Office of the Municipal Mayor', '08:00:00', '12:00:00', '13:00:00', '17:00:00', 0.00, 0.00, 8.00, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(2, '2024-01-15', 'EMP002', 'Jane Smith', 'Human Resource Management Division', '08:15:00', '12:05:00', '13:10:00', '17:30:00', 0.50, 0.25, 8.33, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(3, '2024-01-16', 'EMP001', 'John Doe', 'Office of the Municipal Mayor', '08:05:00', '12:00:00', '13:00:00', '17:00:00', 0.00, 0.08, 7.92, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(4, '2024-01-16', 'EMP002', 'Jane Smith', 'Human Resource Management Division', NULL, NULL, '13:00:00', '17:00:00', 0.00, 4.00, 4.00, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(5, '2024-01-17', 'EMP001', 'John Doe', 'Office of the Municipal Mayor', '07:45:00', '12:00:00', '13:00:00', '17:30:00', 0.75, 0.00, 8.75, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(6, '2024-01-17', 'EMP002', 'Jane Smith', 'Human Resource Management Division', '08:00:00', '12:00:00', '13:00:00', '17:00:00', 0.00, 0.00, 8.00, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(7, '2024-01-18', 'EMP003', 'Michael Johnson', 'Business Permit and Licensing Division', '08:10:00', '12:00:00', '13:05:00', '17:00:00', 0.00, 0.25, 7.75, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(8, '2024-01-18', 'EMP004', 'Sarah Williams', 'Sangguniang Bayan Office', '08:00:00', '12:00:00', '13:00:00', '17:15:00', 0.25, 0.00, 8.25, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(9, '2024-01-19', 'EMP005', 'Robert Brown', 'Office of the Municipal Accountant', '07:30:00', '12:00:00', '13:00:00', '18:00:00', 1.50, 0.00, 9.50, '2026-01-23 20:57:39', '2026-01-23 20:57:39'),
(10, '2024-01-19', 'EMP006', 'Lisa Davis', 'Office of the Assessor', '08:00:00', '11:30:00', '13:00:00', '16:30:00', 0.00, 1.00, 7.00, '2026-01-23 20:57:39', '2026-01-23 20:57:39');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'update', 'Updated user profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-23 21:35:03'),
(2, 1, 'update', 'Updated email settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-24 13:29:13'),
(3, 1, 'update', 'Updated email settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-24 15:00:51');

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` int(11) NOT NULL,
  `backup_type` enum('full','database','files') NOT NULL,
  `backup_notes` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('completed','failed','in_progress') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractofservice`
--

CREATE TABLE `contractofservice` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `office_assignment` varchar(255) NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `wages` decimal(10,2) NOT NULL,
  `contribution` varchar(255) DEFAULT NULL,
  `profile_image_path` varchar(500) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `date_of_birth` date NOT NULL,
  `marital_status` varchar(50) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `street_address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state_region` varchar(100) NOT NULL,
  `zip_code` varchar(10) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `joining_date` date NOT NULL,
  `eligibility` varchar(50) NOT NULL,
  `doc_id_path` varchar(500) DEFAULT NULL,
  `doc_resume_path` varchar(500) DEFAULT NULL,
  `doc_service_path` varchar(500) DEFAULT NULL,
  `doc_appointment_path` varchar(500) DEFAULT NULL,
  `doc_transcript_path` varchar(500) DEFAULT NULL,
  `doc_eligibility_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractofservice`
--

INSERT INTO `contractofservice` (`id`, `employee_id`, `full_name`, `designation`, `office_assignment`, `period_from`, `period_to`, `wages`, `contribution`, `profile_image_path`, `first_name`, `last_name`, `mobile_number`, `email_address`, `date_of_birth`, `marital_status`, `gender`, `nationality`, `street_address`, `city`, `state_region`, `zip_code`, `password_hash`, `joining_date`, `eligibility`, `doc_id_path`, `doc_resume_path`, `doc_service_path`, `doc_appointment_path`, `doc_transcript_path`, `doc_eligibility_path`, `created_at`, `updated_at`) VALUES
(1, 'HRMD-2024-0001', 'Maria Santos Cruz', 'HR Assistant', 'Human Resource Management Division', '2024-01-15', '2024-12-31', 25000.00, 'SSS, PhilHealth, Pag-IBIG', NULL, 'Maria', 'Cruz', '09171234567', 'maria.cruz@paluan.gov.ph', '1990-05-15', 'Single', 'Female', 'Filipino', '123 Poblacion Street', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-15', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(2, 'MHO-2024-0001', 'Juan Dela Cruz Reyes', 'Medical Clerk', 'Municipal Health Office', '2024-02-01', '2024-12-31', 22000.00, 'SSS, PhilHealth', NULL, 'Juan', 'Reyes', '09182345678', 'juan.reyes@paluan.gov.ph', '1988-08-20', 'Married', 'Male', 'Filipino', '456 Health Center Road', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-02-01', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(3, 'MEO-2024-0001', 'Pedro Santos Mendoza', 'Engineering Aide', 'Municipal Engineering Office', '2024-03-01', '2024-12-31', 28000.00, 'SSS, PhilHealth, Pag-IBIG', NULL, 'Pedro', 'Mendoza', '09193456789', 'pedro.mendoza@paluan.gov.ph', '1992-11-30', 'Single', 'Male', 'Filipino', '789 Engineer\'s Lane', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-03-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(4, 'MPDO-2024-0001', 'Ana Marie Garcia Lopez', 'Planning Assistant', 'Municipal Planning and Development Office', '2024-01-01', '2026-12-31', 30000.00, 'SSS, PhilHealth, Pag-IBIG, GSIS', 'uploads/contractual_documents/profile_1769204437_6973ead596a96.png', 'Ana', 'Lopez', '09204567890', 'ana.lopez@paluan.gov.ph', '1985-03-25', 'Married', 'Female', 'Filipino', '321 Planning Avenue', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-24 19:12:49'),
(5, 'MSWDO-2024-0001', 'Roberto Tan Lim', 'Social Welfare Assistant', 'Municipal Social Welfare and Development Office', '2024-02-15', '2024-12-31', 23000.00, 'SSS, PhilHealth', NULL, 'Roberto', 'Lim', '09215678901', 'roberto.lim@paluan.gov.ph', '1991-07-12', 'Single', 'Male', 'Filipino', '654 Welfare Street', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-02-15', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(6, 'OMM-2024-0001', 'Catherine Sy Wong', 'Executive Assistant', 'Office of the Municipal Mayor', '2024-01-10', '2024-12-31', 35000.00, 'SSS, PhilHealth, Pag-IBIG, GSIS', NULL, 'Catherine', 'Wong', '09226789012', 'catherine.wong@paluan.gov.ph', '1987-12-05', 'Married', 'Female', 'Filipino', '987 Mayor\'s Drive', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-10', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(7, 'MBO-2024-0001', 'Michael John Torres', 'Budget Analyst', 'Municipal Budget Office', '2024-02-01', '2024-12-31', 32000.00, 'SSS, PhilHealth, Pag-IBIG, GSIS', NULL, 'Michael', 'Torres', '09237890123', 'michael.torres@paluan.gov.ph', '1989-09-18', 'Single', 'Male', 'Filipino', '147 Budget Road', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-02-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(8, 'MGSO-2024-0001', 'Susan Lee Chua', 'Administrative Assistant', 'Municipal General Services Office', '2024-03-15', '2024-12-31', 21000.00, 'SSS, PhilHealth', NULL, 'Susan', 'Chua', '09248901234', 'susan.chua@paluan.gov.ph', '1993-04-22', 'Single', 'Female', 'Filipino', '258 Service Avenue', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-03-15', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(9, 'MTO-2024-0001', 'James Patrick Villanueva', 'Cashier', 'Municipal Treasurer\'s Office', '2024-01-20', '2024-12-31', 27000.00, 'SSS, PhilHealth, Pag-IBIG, GSIS', NULL, 'James', 'Villanueva', '09259012345', 'james.villanueva@paluan.gov.ph', '1990-10-08', 'Married', 'Male', 'Filipino', '369 Treasury Lane', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-20', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(10, 'MENRO-2024-0001', 'Liza Marie Ramos', 'Environmental Assistant', 'Municipal Environment and Natural Resources Office', '2024-02-28', '2024-12-31', 24000.00, 'SSS, PhilHealth', NULL, 'Liza', 'Ramos', '09260123456', 'liza.ramos@paluan.gov.ph', '1994-06-14', 'Single', 'Female', 'Filipino', '741 Environment Road', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-02-28', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(11, 'HRMD-2023-0001', 'Ricardo Fernandez', 'HR Clerk', 'Human Resource Management Division', '2023-06-01', '2023-12-31', 20000.00, 'SSS, PhilHealth', NULL, 'Ricardo', 'Fernandez', '09170000001', 'ricardo.fernandez@paluan.gov.ph', '1995-02-28', 'Single', 'Male', 'Filipino', '852 Old Street', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2023-06-01', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(12, 'MHO-2023-0001', 'Angelita Santos', 'Nursing Aide', 'Municipal Health Office', '2023-07-15', '2029-12-31', 19000.00, 'SSS, PhilHealth', NULL, 'Angelita', 'Santos', '09181111111', 'angelita.santos@paluan.gov.ph', '1996-09-09', 'Single', 'Female', 'Filipino', '963 Health Street', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2023-07-15', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 20:38:29'),
(13, 'BPLD-2024-0001', 'Carlos Miguel Tan', 'Permit Processor', 'Business Permit and Licensing Division', '2024-01-05', '2024-12-31', 26000.00, 'SSS, PhilHealth, Pag-IBIG', NULL, 'Carlos', 'Tan', '09202222222', 'carlos.tan@paluan.gov.ph', '1986-12-01', 'Married', 'Male', 'Filipino', '159 Business Avenue', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-05', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(14, 'SBO-2024-0001', 'Margarita Hernandez', 'Legislative Staff', 'Sangguniang Bayan Office', '2024-02-10', '2024-12-31', 29000.00, 'SSS, PhilHealth, Pag-IBIG, GSIS', NULL, 'Margarita', 'Hernandez', '09213333333', 'margarita.hernandez@paluan.gov.ph', '1984-04-17', 'Married', 'Female', 'Filipino', '753 Council Road', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-02-10', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38'),
(15, 'OMA-2024-0001', 'Benjamin Cruz', 'Accounting Clerk', 'Office of the Municipal Accountant', '2024-01-25', '2024-12-31', 31000.00, 'SSS, PhilHealth, Pag-IBIG, GSIS', NULL, 'Benjamin', 'Cruz', '09224444444', 'benjamin.cruz@paluan.gov.ph', '1983-08-22', 'Married', 'Male', 'Filipino', '951 Accountant\'s Lane', 'Paluan', 'Occidental Mindoro', '5104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-25', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 19:38:38', '2026-01-23 19:38:38');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `dept_code` varchar(20) NOT NULL,
  `dept_head` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `dept_name`, `dept_code`, `dept_head`, `updated_at`, `created_at`) VALUES
(1, 'Human Resources', 'HR', NULL, '2026-01-23 21:34:01', '2026-01-23 21:34:01'),
(2, 'Finance', 'FIN', NULL, '2026-01-23 21:34:01', '2026-01-23 21:34:01'),
(3, 'Information Technology', 'IT', NULL, '2026-01-23 21:34:01', '2026-01-23 21:34:01'),
(4, 'Administration', 'ADMIN', NULL, '2026-01-23 21:34:01', '2026-01-23 21:34:01'),
(5, 'Operations', 'OPS', NULL, '2026-01-23 21:34:01', '2026-01-23 21:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `email_settings`
--

CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(100) DEFAULT 'smtp.gmail.com',
  `smtp_port` int(11) DEFAULT 587,
  `smtp_username` varchar(100) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_encryption` varchar(10) DEFAULT 'tls',
  `from_email` varchar(100) DEFAULT 'hrmo@paluan.gov.ph',
  `from_name` varchar(100) DEFAULT 'HRMO Paluan',
  `enable_email_notifications` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_settings`
--

INSERT INTO `email_settings` (`id`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `from_email`, `from_name`, `enable_email_notifications`, `updated_at`, `created_at`) VALUES
(1, 'smtp.gmail.com', 587, 'punzalanmarkjhon8@gmail.com', 'Coddex', '', 'hrmo@paluan.gov.ph', '', 0, '2026-01-24 15:00:51', '2026-01-23 21:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `job_order`
--

CREATE TABLE `job_order` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `office` varchar(100) DEFAULT NULL,
  `rate_per_day` decimal(10,2) DEFAULT NULL,
  `sss_contribution` varchar(100) DEFAULT NULL,
  `ctc_number` varchar(50) DEFAULT NULL,
  `ctc_date` date DEFAULT NULL,
  `place_of_issue` varchar(100) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `email_address` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `marital_status` varchar(20) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state_region` varchar(50) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `eligibility` varchar(50) DEFAULT NULL,
  `doc_id_path` varchar(255) DEFAULT NULL,
  `doc_resume_path` varchar(255) DEFAULT NULL,
  `doc_service_path` varchar(255) DEFAULT NULL,
  `doc_appointment_path` varchar(255) DEFAULT NULL,
  `doc_transcript_path` varchar(255) DEFAULT NULL,
  `doc_eligibility_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order`
--

INSERT INTO `job_order` (`id`, `employee_id`, `employee_name`, `occupation`, `office`, `rate_per_day`, `sss_contribution`, `ctc_number`, `ctc_date`, `place_of_issue`, `profile_image_path`, `first_name`, `last_name`, `mobile_number`, `email_address`, `date_of_birth`, `marital_status`, `gender`, `nationality`, `street_address`, `city`, `state_region`, `zip_code`, `password_hash`, `joining_date`, `eligibility`, `doc_id_path`, `doc_resume_path`, `doc_service_path`, `doc_appointment_path`, `doc_transcript_path`, `doc_eligibility_path`, `created_at`, `updated_at`) VALUES
(1, 'JO-2024-001', 'Juan Dela Cruz', 'Administrative Assistant', 'Office of the Municipal Mayor', 750.00, 'SSS-123456789', 'CTC-2024-001', '2024-01-15', 'Paluan, Occidental Mindoro', NULL, 'Juan', 'Dela Cruz', '09171234567', 'juan.delacruz@example.com', '1990-05-15', 'Married', 'Male', 'Filipino', '123 Rizal Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-01-02', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(2, 'JO-2024-002', 'Maria Santos', 'Clerk', 'Municipal Treasurer\'s Office', 680.00, 'SSS-987654321', 'CTC-2024-002', '2024-02-20', 'Paluan, Occidental Mindoro', NULL, 'Maria', 'Santos', '09172345678', 'maria.santos@example.com', '1992-08-22', 'Single', 'Female', 'Filipino', '456 Bonifacio Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-02-01', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(3, 'JO-2024-003', 'Pedro Reyes', 'Utility Worker', 'Municipal General Services Office', 550.00, 'SSS-456789123', 'CTC-2024-003', '2024-03-10', 'Paluan, Occidental Mindoro', NULL, 'Pedro', 'Reyes', '09173456789', 'pedro.reyes@example.com', '1988-11-30', 'Married', 'Male', 'Filipino', '789 Mabini Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-03-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(4, 'JO-2024-004', 'Ana Martinez', 'Data Encoderr', 'Municipal Planning and Development Office', 720.00, 'SSS-789123456', 'CTC-2024-004', '2024-04-05', 'Paluan, Occidental Mindoro', NULL, 'Ana', 'Martinez', '09174567890', 'ana.martinez@example.com', '1995-03-18', 'Single', 'Female', 'Filipino', '321 Aguinaldo Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-04-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-24 19:15:27'),
(5, 'JO-2024-005', 'Luis Garcia', 'Driver', 'Office of the Municipal Mayor', 600.00, 'SSS-321654987', 'CTC-2024-005', '2024-05-12', 'Paluan, Occidental Mindoro', NULL, 'Luis', 'Garcia', '09175678901', 'luis.garcia@example.com', '1991-07-25', 'Married', 'Male', 'Filipino', '654 Quezon Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-05-01', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(6, 'JO-2024-006', 'Sofia Lopez', 'Nurse', 'Municipal Health Office', 850.00, 'SSS-654987321', 'CTC-2024-006', '2024-06-18', 'Paluan, Occidental Mindoro', NULL, 'Sofia', 'Lopez', '09176789012', 'sofia.lopez@example.com', '1993-12-05', 'Single', 'Female', 'Filipino', '987 Osmeña Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-06-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(7, 'JO-2024-007', 'Carlos Ramos', 'Agricultural Technician', 'Office of the Municipal Agriculturist', 700.00, 'SSS-159357486', 'CTC-2024-007', '2024-07-22', 'Paluan, Occidental Mindoro', NULL, 'Carlos', 'Ramos', '09177890123', 'carlos.ramos@example.com', '1989-09-14', 'Married', 'Male', 'Filipino', '147 Roxas Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-07-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(8, 'JO-2024-008', 'Elena Torres', 'Social Worker', 'Municipal Social Welfare and Development Office', 780.00, 'SSS-753159486', 'CTC-2024-008', '2024-08-14', 'Paluan, Occidental Mindoro', NULL, 'Elena', 'Torres', '09178901234', 'elena.torres@example.com', '1994-04-28', 'Single', 'Female', 'Filipino', '258 Laurel Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-08-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(9, 'JO-2024-009', 'Antonio Cruz', 'Engineer Aide', 'Municipal Engineering Office', 800.00, 'SSS-852741963', 'CTC-2024-009', '2024-09-30', 'Paluan, Occidental Mindoro', NULL, 'Antonio', 'Cruz', '09179012345', 'antonio.cruz@example.com', '1990-10-10', 'Married', 'Male', 'Filipino', '369 Marcos Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-09-01', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(10, 'JO-2024-010', 'Carmen Reyes', 'Bookkeeper', 'Municipal Budget Office', 820.00, 'SSS-963852741', 'CTC-2024-010', '2024-10-25', 'Paluan, Occidental Mindoro', NULL, 'Carmen', 'Reyes', '09170123456', 'carmen.reyes@example.com', '1996-01-20', 'Single', 'Female', 'Filipino', '741 Macapagal Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-10-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(11, 'JO-2024-011', 'Roberto Mendoza', 'Security Guard', 'Municipal General Services Office', 580.00, 'SSS-147258369', 'CTC-2024-011', '2024-11-08', 'Paluan, Occidental Mindoro', NULL, 'Roberto', 'Mendoza', '09171234567', 'roberto.mendoza@example.com', '1987-06-12', 'Married', 'Male', 'Filipino', '852 Quirino Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-11-01', 'Not Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26'),
(12, 'JO-2024-012', 'Teresa Fernandez', 'Midwife', 'Municipal Health Office', 830.00, 'SSS-369258147', 'CTC-2024-012', '2024-12-03', 'Paluan, Occidental Mindoro', NULL, 'Teresa', 'Fernandez', '09172345678', 'teresa.fernandez@example.com', '1992-11-08', 'Married', 'Female', 'Filipino', '963 Garcia Street', 'Paluan', 'Occidental Mindoro', '5107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2024-12-01', 'Eligible', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 21:10:26', '2026-01-23 21:10:26');

-- --------------------------------------------------------

--
-- Table structure for table `permanent`
--

CREATE TABLE `permanent` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `office` varchar(100) NOT NULL,
  `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_accrued` decimal(12,2) NOT NULL DEFAULT 0.00,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `nationality` varchar(50) NOT NULL DEFAULT 'Filipino',
  `street_address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state_region` varchar(50) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `joining_date` date NOT NULL,
  `eligibility` enum('Eligible','Not Eligible') NOT NULL DEFAULT 'Eligible',
  `password_hash` varchar(255) NOT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `doc_id_path` varchar(255) DEFAULT NULL,
  `doc_resume_path` varchar(255) DEFAULT NULL,
  `doc_service_path` varchar(255) DEFAULT NULL,
  `doc_appointment_path` varchar(255) DEFAULT NULL,
  `doc_transcript_path` varchar(255) DEFAULT NULL,
  `doc_eligibility_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permanent`
--

INSERT INTO `permanent` (`id`, `employee_id`, `full_name`, `position`, `office`, `monthly_salary`, `amount_accrued`, `first_name`, `last_name`, `mobile_number`, `email_address`, `date_of_birth`, `marital_status`, `gender`, `nationality`, `street_address`, `city`, `state_region`, `zip_code`, `joining_date`, `eligibility`, `password_hash`, `profile_image_path`, `doc_id_path`, `doc_resume_path`, `doc_service_path`, `doc_appointment_path`, `doc_transcript_path`, `doc_eligibility_path`, `created_at`, `updated_at`) VALUES
(2, 'P-2024-01-002', 'Maria Santos', 'Human Resource Officer III', 'Human Resource Management Division', 32000.00, 384000.00, 'Maria', 'Santos', '09172345678', 'maria.santos@paluan.gov.ph', '1990-03-22', 'Single', 'Female', 'Filipino', '456 Barangay Magsaysay', 'Paluan', 'Occidental Mindoro', '5107', '2021-03-01', 'Eligible', '$2y$10$YourHashedPasswordHere', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 18:55:27', '2026-01-23 18:55:27'),
(3, 'P-2024-01-003', 'Roberto Garcia', 'Budget Officer III', 'Municipal Budget Office', 38000.00, 456000.00, 'Roberto', 'Garcia', '09173456789', 'roberto.garcia@paluan.gov.ph', '1982-11-30', 'Married', 'Male', 'Filipino', '789 Sitio Pag-asa', 'Paluan', 'Occidental Mindoro', '5107', '2019-07-10', 'Eligible', '$2y$10$YourHashedPasswordHere', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 18:55:27', '2026-01-23 18:55:27'),
(4, 'P-2024-01-004', 'Anna Reyes', 'Municipal Accountant II', 'Office of the Municipal Accountant', 40000.00, 480000.00, 'Anna', 'Reyes', '09174567890', 'anna.reyes@paluan.gov.ph', '1988-09-05', 'Single', 'Female', 'Filipino', '321 Barangay Bagong Buhay', 'Paluan', 'Occidental Mindoro', '5107', '2022-02-20', 'Not Eligible', '$2y$10$YourHashedPasswordHere', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 18:55:27', '2026-01-23 18:55:27'),
(5, 'P-2024-01-005', 'Carlos Mendoza', 'Municipal Engineer III', 'Municipal Engineering Office', 42000.00, 504000.00, 'Carlos', 'Mendoza', '09175678901', 'carlos.mendoza@paluan.gov.ph', '1979-12-18', 'Married', 'Male', 'Filipino', '654 Purok Masagana', 'Paluan', 'Occidental Mindoro', '5107', '2018-11-05', 'Eligible', '$2y$10$YourHashedPasswordHere', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 18:55:27', '2026-01-23 18:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `system_name` varchar(100) DEFAULT 'HRMS Paluan',
  `timezone` varchar(50) DEFAULT 'Asia/Manila',
  `date_format` varchar(20) DEFAULT 'Y-m-d',
  `time_format` varchar(20) DEFAULT 'H:i:s',
  `pagination_limit` int(11) DEFAULT 25,
  `session_timeout` int(11) DEFAULT 30,
  `enable_registration` tinyint(1) DEFAULT 1,
  `enable_remember_me` tinyint(1) DEFAULT 1,
  `enable_debug` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `system_name`, `timezone`, `date_format`, `time_format`, `pagination_limit`, `session_timeout`, `enable_registration`, `enable_remember_me`, `enable_debug`, `updated_at`, `created_at`) VALUES
(1, 'HRMS Paluan', 'Asia/Manila', 'Y-m-d', 'H:i:s', 25, 30, 1, 1, 0, '2026-01-23 21:34:01', '2026-01-23 21:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `position`, `department`, `is_admin`, `is_active`, `otp_code`, `otp_expires_at`, `login_attempts`, `locked_until`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'punzalanmarkjhon8@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'System Administrator', 'HR Department', 1, 1, NULL, NULL, 0, NULL, '2026-01-24 12:26:27', '2026-01-24 18:49:33', '2026-01-25 02:49:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_employee_name` (`employee_name`),
  ADD KEY `idx_department` (`department`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `contractofservice`
--
ALTER TABLE `contractofservice`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email_address` (`email_address`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_email` (`email_address`),
  ADD KEY `idx_office` (`office_assignment`),
  ADD KEY `idx_full_name` (`full_name`),
  ADD KEY `idx_period_to` (`period_to`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dept_code` (`dept_code`);

--
-- Indexes for table `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `job_order`
--
ALTER TABLE `job_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `permanent`
--
ALTER TABLE `permanent`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_full_name` (`full_name`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_office` (`office`),
  ADD KEY `idx_email` (`email_address`),
  ADD KEY `idx_joining_date` (`joining_date`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractofservice`
--
ALTER TABLE `contractofservice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_settings`
--
ALTER TABLE `email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `job_order`
--
ALTER TABLE `job_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `permanent`
--
ALTER TABLE `permanent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
