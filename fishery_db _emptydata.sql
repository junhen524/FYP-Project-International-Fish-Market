-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2026 at 06:22 PM
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
-- Database: `fishery_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `export_cart`
--

CREATE TABLE `export_cart` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `restaurant_id` bigint(20) DEFAULT NULL,
  `product_slug` varchar(100) NOT NULL,
  `tier_label` varchar(20) DEFAULT '',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_driver`
--

CREATE TABLE `export_driver` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `port_id` bigint(20) DEFAULT NULL,
  `license_no` varchar(100) DEFAULT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL,
  `identification_no` varchar(100) DEFAULT NULL,
  `vessel_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_orders`
--

CREATE TABLE `export_orders` (
  `id` bigint(20) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `restaurant_id` bigint(20) DEFAULT NULL,
  `wallet_id` bigint(20) DEFAULT NULL,
  `stage` varchar(20) NOT NULL DEFAULT 'processing',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `shipping_terms` varchar(100) DEFAULT NULL,
  `destination_port` varchar(255) DEFAULT NULL,
  `destination_country` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `delivery_qr_code` varchar(64) DEFAULT NULL,
  `delivery_pin` varchar(6) DEFAULT NULL,
  `delivery_qr_used` tinyint(1) DEFAULT 0,
  `rejected_reason` text DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items`)),
  `ordered_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_recipes`
--

CREATE TABLE `export_recipes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT '',
  `description` text DEFAULT NULL,
  `level` varchar(50) DEFAULT 'Intermediate',
  `time_minutes` int(11) DEFAULT 30,
  `image_url` varchar(500) DEFAULT '',
  `is_active` tinyint(1) DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `restaurant_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_recipe_favorites`
--

CREATE TABLE `export_recipe_favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `restaurant_id` int(11) DEFAULT NULL,
  `recipe_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_recipe_items`
--

CREATE TABLE `export_recipe_items` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `type` enum('ingredient','step') NOT NULL,
  `content` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `product_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_restaurant_user`
--

CREATE TABLE `export_restaurant_user` (
  `id` bigint(20) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `email` varchar(254) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `reg_no` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `discount_percent` decimal(5,1) NOT NULL DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_shipment`
--

CREATE TABLE `export_shipment` (
  `id` bigint(20) NOT NULL,
  `shipment_number` varchar(20) DEFAULT '',
  `order_id` bigint(20) DEFAULT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `destination_port` varchar(255) DEFAULT NULL,
  `destination_country` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `driver_id` bigint(20) DEFAULT NULL,
  `packed_at` datetime DEFAULT NULL,
  `loaded_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_user`
--

CREATE TABLE `export_user` (
  `id` bigint(20) NOT NULL,
  `username` varchar(150) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_wallets`
--

CREATE TABLE `export_wallets` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `restaurant_id` bigint(20) DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `saved_cards` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`saved_cards`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_wallet_txn`
--

CREATE TABLE `export_wallet_txn` (
  `id` bigint(20) NOT NULL,
  `wallet_id` bigint(20) DEFAULT NULL,
  `transaction_type` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_before` decimal(12,2) DEFAULT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fishery_zone`
--

CREATE TABLE `fishery_zone` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `center_lat` decimal(10,6) DEFAULT NULL,
  `center_lng` decimal(10,6) DEFAULT NULL,
  `radius_km` decimal(10,2) DEFAULT NULL,
  `fish_density_base` decimal(5,2) DEFAULT NULL,
  `primary_species` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fishing_trips`
--

CREATE TABLE `fishing_trips` (
  `id` int(11) NOT NULL,
  `vessel_id` bigint(20) NOT NULL,
  `zone_id` bigint(20) NOT NULL,
  `status` enum('planning','outgoing','fishing','returning','completed','cancelled') NOT NULL DEFAULT 'planning',
  `started_at` datetime DEFAULT NULL,
  `fishing_start` datetime DEFAULT NULL,
  `fishing_end` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `port_id` bigint(20) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reserved_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `batch_no` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'available',
  `received_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_cart`
--

CREATE TABLE `market_cart` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `tier_id` varchar(50) NOT NULL DEFAULT '',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_drivers`
--

CREATE TABLE `market_drivers` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `port_id` bigint(20) DEFAULT NULL,
  `license_no` varchar(100) DEFAULT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL,
  `identification_no` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_order`
--

CREATE TABLE `market_order` (
  `id` bigint(20) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `wallet_id` bigint(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT '',
  `notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_shipments`
--

CREATE TABLE `market_shipments` (
  `id` bigint(20) NOT NULL,
  `shipment_number` varchar(20) DEFAULT '',
  `order_id` bigint(20) NOT NULL,
  `order_number` varchar(20) DEFAULT '',
  `port_id` bigint(20) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `packed_at` datetime DEFAULT NULL,
  `loaded_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_user`
--

CREATE TABLE `market_user` (
  `id` bigint(20) NOT NULL,
  `username` varchar(150) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'customer',
  `phone` varchar(50) DEFAULT NULL,
  `address_line1` text DEFAULT NULL,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `balance` decimal(12,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'MYR',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_wallet_txn`
--

CREATE TABLE `market_wallet_txn` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `wallet_id` bigint(20) DEFAULT NULL,
  `transaction_type` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_before` decimal(12,2) DEFAULT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `port`
--

CREATE TABLE `port` (
  `id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `ports`
-- (See below for the actual view)
--
CREATE TABLE `ports` (
`id` bigint(20)
,`name` varchar(100)
,`code` varchar(10)
,`location` varchar(255)
,`capacity` int(11)
,`is_active` tinyint(1)
,`created_at` datetime
,`updated_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `port_user`
--

CREATE TABLE `port_user` (
  `id` bigint(20) NOT NULL,
  `username` varchar(150) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'viewer',
  `port_id` bigint(20) DEFAULT NULL,
  `port_name` varchar(255) DEFAULT NULL,
  `port_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `freshness` varchar(20) NOT NULL DEFAULT 'fresh',
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `domestic_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `export_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `origin` varchar(255) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `image_data` longblob DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `tier_3kg_price` decimal(10,2) DEFAULT NULL,
  `tier_3kg_export_price` decimal(12,2) DEFAULT 0.00,
  `tier_3kg_stock` int(11) NOT NULL DEFAULT 10,
  `tier_6kg_price` decimal(10,2) DEFAULT NULL,
  `tier_6kg_export_price` decimal(12,2) DEFAULT 0.00,
  `tier_6kg_stock` int(11) NOT NULL DEFAULT 10,
  `tier_10kg_price` decimal(10,2) DEFAULT NULL,
  `tier_10kg_export_price` decimal(12,2) DEFAULT 0.00,
  `tier_10kg_stock` int(11) NOT NULL DEFAULT 10,
  `received_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_move`
--

CREATE TABLE `stock_move` (
  `id` bigint(20) NOT NULL,
  `inventory_id` bigint(20) DEFAULT NULL,
  `product_id` bigint(20) NOT NULL,
  `port_id` bigint(20) DEFAULT NULL,
  `movement_type` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `balance_before` decimal(10,2) DEFAULT NULL,
  `balance_after` decimal(10,2) DEFAULT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_type` varchar(50) DEFAULT 'system',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_catches`
--

CREATE TABLE `trip_catches` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `tier_id` int(11) DEFAULT NULL,
  `pieces_caught` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of sets/pieces caught',
  `estimated_weight_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quality_grade` varchar(10) DEFAULT NULL,
  `species` varchar(255) DEFAULT NULL,
  `catch_location` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'landed',
  `reject_reason` varchar(100) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `unloaded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=unloaded to warehouse',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vessels`
--

CREATE TABLE `vessels` (
  `id` bigint(20) NOT NULL,
  `port_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `registration_no` varchar(100) DEFAULT NULL,
  `vessel_type` varchar(50) DEFAULT NULL,
  `captain_name` varchar(255) DEFAULT NULL,
  `capacity_tonnes` decimal(10,2) DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'docked',
  `last_docked_at` datetime DEFAULT NULL,
  `current_trip_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vessel_track`
--

CREATE TABLE `vessel_track` (
  `id` int(11) NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `latitude` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `longitude` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `speed_kn` decimal(6,2) DEFAULT 0.00,
  `heading_deg` decimal(6,2) DEFAULT 0.00,
  `depth_m` decimal(8,2) DEFAULT 0.00,
  `fish_density` decimal(6,4) DEFAULT 0.0000,
  `zone_id` int(11) DEFAULT NULL,
  `is_fishing` tinyint(1) NOT NULL DEFAULT 0,
  `catch_kg_estimate` decimal(10,2) DEFAULT 0.00,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `ports`
--
DROP TABLE IF EXISTS `ports`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `ports`  AS SELECT `port`.`id` AS `id`, `port`.`name` AS `name`, `port`.`code` AS `code`, `port`.`location` AS `location`, `port`.`capacity` AS `capacity`, `port`.`is_active` AS `is_active`, `port`.`created_at` AS `created_at`, `port`.`updated_at` AS `updated_at` FROM `port` WHERE `port`.`is_active` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `export_cart`
--
ALTER TABLE `export_cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_product` (`user_id`,`product_slug`,`tier_label`),
  ADD UNIQUE KEY `uk_restaurant_product` (`restaurant_id`,`product_slug`,`tier_label`);

--
-- Indexes for table `export_driver`
--
ALTER TABLE `export_driver`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `export_orders`
--
ALTER TABLE `export_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_stage` (`stage`);

--
-- Indexes for table `export_recipes`
--
ALTER TABLE `export_recipes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `export_recipe_favorites`
--
ALTER TABLE `export_recipe_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_favorite` (`user_id`,`restaurant_id`,`recipe_id`),
  ADD KEY `recipe_id` (`recipe_id`);

--
-- Indexes for table `export_recipe_items`
--
ALTER TABLE `export_recipe_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `export_restaurant_user`
--
ALTER TABLE `export_restaurant_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `export_shipment`
--
ALTER TABLE `export_shipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `export_user`
--
ALTER TABLE `export_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `export_wallets`
--
ALTER TABLE `export_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_restaurant_wallet` (`restaurant_id`),
  ADD KEY `fk_wallet_user` (`user_id`);

--
-- Indexes for table `export_wallet_txn`
--
ALTER TABLE `export_wallet_txn`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_id` (`wallet_id`);

--
-- Indexes for table `fishery_zone`
--
ALTER TABLE `fishery_zone`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fishing_trips`
--
ALTER TABLE `fishing_trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vessel` (`vessel_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_warehouse` (`port_id`);

--
-- Indexes for table `market_cart`
--
ALTER TABLE `market_cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`slug`,`tier_id`);

--
-- Indexes for table `market_drivers`
--
ALTER TABLE `market_drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `market_order`
--
ALTER TABLE `market_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `market_shipments`
--
ALTER TABLE `market_shipments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `market_user`
--
ALTER TABLE `market_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `market_wallet_txn`
--
ALTER TABLE `market_wallet_txn`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_id` (`wallet_id`),
  ADD KEY `idx_wallet_txn_user` (`user_id`);

--
-- Indexes for table `port`
--
ALTER TABLE `port`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `port_user`
--
ALTER TABLE `port_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `stock_move`
--
ALTER TABLE `stock_move`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_warehouse` (`port_id`);

--
-- Indexes for table `trip_catches`
--
ALTER TABLE `trip_catches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trip` (`trip_id`),
  ADD KEY `idx_unloaded` (`unloaded`);

--
-- Indexes for table `vessels`
--
ALTER TABLE `vessels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vessel_track`
--
ALTER TABLE `vessel_track`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vessel_id` (`vessel_id`),
  ADD KEY `idx_recorded_at` (`recorded_at`),
  ADD KEY `idx_vessel_recorded` (`vessel_id`,`recorded_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `export_cart`
--
ALTER TABLE `export_cart`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_driver`
--
ALTER TABLE `export_driver`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_orders`
--
ALTER TABLE `export_orders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_recipes`
--
ALTER TABLE `export_recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_recipe_favorites`
--
ALTER TABLE `export_recipe_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_recipe_items`
--
ALTER TABLE `export_recipe_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_restaurant_user`
--
ALTER TABLE `export_restaurant_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_shipment`
--
ALTER TABLE `export_shipment`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_user`
--
ALTER TABLE `export_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_wallets`
--
ALTER TABLE `export_wallets`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_wallet_txn`
--
ALTER TABLE `export_wallet_txn`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fishery_zone`
--
ALTER TABLE `fishery_zone`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fishing_trips`
--
ALTER TABLE `fishing_trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_cart`
--
ALTER TABLE `market_cart`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_drivers`
--
ALTER TABLE `market_drivers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_order`
--
ALTER TABLE `market_order`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_shipments`
--
ALTER TABLE `market_shipments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_user`
--
ALTER TABLE `market_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_wallet_txn`
--
ALTER TABLE `market_wallet_txn`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `port`
--
ALTER TABLE `port`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `port_user`
--
ALTER TABLE `port_user`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_move`
--
ALTER TABLE `stock_move`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_catches`
--
ALTER TABLE `trip_catches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vessels`
--
ALTER TABLE `vessels`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vessel_track`
--
ALTER TABLE `vessel_track`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `export_cart`
--
ALTER TABLE `export_cart`
  ADD CONSTRAINT `export_cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `export_user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `export_cart_ibfk_2` FOREIGN KEY (`restaurant_id`) REFERENCES `export_restaurant_user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `export_recipe_favorites`
--
ALTER TABLE `export_recipe_favorites`
  ADD CONSTRAINT `export_recipe_favorites_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `export_recipes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `export_recipe_items`
--
ALTER TABLE `export_recipe_items`
  ADD CONSTRAINT `export_recipe_items_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `export_recipes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `export_recipe_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `export_wallets`
--
ALTER TABLE `export_wallets`
  ADD CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `export_user` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `market_cart`
--
ALTER TABLE `market_cart`
  ADD CONSTRAINT `fk_market_cart_user` FOREIGN KEY (`user_id`) REFERENCES `market_user` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
