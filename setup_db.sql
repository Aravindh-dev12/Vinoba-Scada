
CREATE DATABASE IF NOT EXISTS `vinoba-velliyanai-scada`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `vinoba-velliyanai-scada`;

DROP TABLE IF EXISTS `plants`;
CREATE TABLE `plants` (
    `id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `capacity` DECIMAL(5,2) NOT NULL DEFAULT 2.0,
    `location` VARCHAR(100) NOT NULL DEFAULT 'Karur',
    `theme` VARCHAR(20) DEFAULT 'blue',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plants` (`id`, `name`, `capacity`, `location`, `theme`) VALUES
('vinoba-velliyanai', 'Vinoba Velliyanai', 2.0, 'Veliyanai, Karur', 'violet'),
('makkalpower', 'Makkal Power', 2.0, 'Veliyanai, Karur', 'blue'),
('anushyam', 'Anushyam Plant', 2.0, 'Veliyanai, Karur', 'emerald');


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user',
    `plant_id` VARCHAR(50) DEFAULT '',
    `auth_token` VARCHAR(128) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`email`, `password`, `role`, `plant_id`) VALUES
('admin@scada.com', '$2y$10$yp5n8uCZkpcJLTsUmGHBKutfKB83.HuJk8H2TSaHj2GFYUs1xZA.y', 'admin', ''),
('veliyani@scada.com', '$2y$10$bwcSRcv4gcyKzI8BiuCuA.EN0GSfDptUp06n/bxa467Rui.auCnJW', 'user', 'vinoba-velliyanai'),
('makkal@scada.com', '$2y$10$btjZmVWpBvtyHqCPXud0gOAr664/RZ7s4.9Fjh2ymZ3j6xSlO4yca', 'user', 'makkalpower'),
('anushyam@scada.com', '$2y$10$Jk8H2TSaHj2GFYUs1xZA.yp5n8uCZkpcJLTsUmGHBKutfKB83.Hu', 'user', 'anushyam');

DROP TABLE IF EXISTS `vcb_readings`;
CREATE TABLE `vcb_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `active_power_total` DECIMAL(10,2) DEFAULT 0 COMMENT 'kW - 3 Phase Active Power',
    `active_power_r` DECIMAL(10,2) DEFAULT 0 COMMENT 'kW',
    `active_power_y` DECIMAL(10,2) DEFAULT 0 COMMENT 'kW',
    `active_power_b` DECIMAL(10,2) DEFAULT 0 COMMENT 'kW',
    `frequency` DECIMAL(6,2) DEFAULT 0 COMMENT 'Hz',
    `voltage_rn` DECIMAL(8,1) DEFAULT 0 COMMENT 'V - R Phase-N',
    `voltage_yn` DECIMAL(8,1) DEFAULT 0 COMMENT 'V - Y Phase-N',
    `voltage_bn` DECIMAL(8,1) DEFAULT 0 COMMENT 'V - B Phase-N',
    `voltage_ry` DECIMAL(8,1) DEFAULT 0 COMMENT 'V - V12 (RY)',
    `voltage_yb` DECIMAL(8,1) DEFAULT 0 COMMENT 'V - V23 (YB)',
    `voltage_br` DECIMAL(8,1) DEFAULT 0 COMMENT 'V - V31 (BR)',
    `current_r` DECIMAL(6,2) DEFAULT 0 COMMENT 'A - L1 (R)',
    `current_y` DECIMAL(6,2) DEFAULT 0 COMMENT 'A - L2 (Y)',
    `current_b` DECIMAL(6,2) DEFAULT 0 COMMENT 'A - L3 (B)',
    `pf_q1` DECIMAL(5,3) DEFAULT 0,
    `pf_q2` DECIMAL(5,3) DEFAULT 0,
    `pf_q3` DECIMAL(5,3) DEFAULT 0,
    `voltage_thd_r` DECIMAL(6,2) DEFAULT 0 COMMENT '%',
    `voltage_thd_y` DECIMAL(6,2) DEFAULT 0 COMMENT '%',
    `voltage_thd_b` DECIMAL(6,2) DEFAULT 0 COMMENT '%',
    `active_total_export` DECIMAL(12,2) DEFAULT 0 COMMENT 'kWh',
    `active_total_import` DECIMAL(12,2) DEFAULT 0 COMMENT 'kWh',
    `reactive_import_q1q2` DECIMAL(12,2) DEFAULT 0 COMMENT 'kVAR',
    `reactive_export_q3q4` DECIMAL(12,2) DEFAULT 0 COMMENT 'kVAR',
    `today_energy` DECIMAL(12,2) DEFAULT 0 COMMENT 'kWh - vcb-today',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_vcb_plant` (`plant_id`),
    INDEX `idx_vcb_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `inverter_readings`;
CREATE TABLE `inverter_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `device_name` VARCHAR(100) NOT NULL COMMENT 'e.g. Inverter1, Inverter2',
    `ac_active_power` DECIMAL(10,2) DEFAULT 0 COMMENT 'kW',
    `ac_reactive_power` DECIMAL(10,2) DEFAULT 0 COMMENT 'kVAR',
    `power_factor` DECIMAL(5,3) DEFAULT 0,
    `ac_voltage_ab` DECIMAL(8,1) DEFAULT 0 COMMENT 'V',
    `ac_voltage_bc` DECIMAL(8,1) DEFAULT 0 COMMENT 'V',
    `ac_voltage_ca` DECIMAL(8,1) DEFAULT 0 COMMENT 'V',
    `ac_frequency` DECIMAL(6,2) DEFAULT 0 COMMENT 'Hz',
    `phase_current_a` DECIMAL(6,2) DEFAULT 0 COMMENT 'A',
    `phase_current_b` DECIMAL(6,2) DEFAULT 0 COMMENT 'A',
    `phase_current_c` DECIMAL(6,2) DEFAULT 0 COMMENT 'A',
    `inverter_efficiency` DECIMAL(5,1) DEFAULT 0 COMMENT '%',
    `internal_temp` DECIMAL(5,1) DEFAULT 0 COMMENT 'degC',
    `daily_generation` DECIMAL(12,2) DEFAULT 0 COMMENT 'kWh',
    `total_generation` DECIMAL(15,2) DEFAULT 0 COMMENT 'kWh',
    `daily_co2_reduction` DECIMAL(10,2) DEFAULT 0 COMMENT 'kg',
    `total_co2_reduction` DECIMAL(12,2) DEFAULT 0 COMMENT 'kg',
    `daily_working_hours` DECIMAL(5,1) DEFAULT 0 COMMENT 'hrs',
    `total_working_hours` DECIMAL(10,1) DEFAULT 0 COMMENT 'hrs',
    `active_strings` INT DEFAULT 0,
    `total_strings` INT DEFAULT 0,
    `status` VARCHAR(20) DEFAULT 'unknown' COMMENT 'online/offline/warning',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inv_plant` (`plant_id`),
    INDEX `idx_inv_device` (`plant_id`, `device_name`),
    INDEX `idx_inv_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `inverter_strings`;
CREATE TABLE `inverter_strings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `inverter_name` VARCHAR(100) NOT NULL,
    `string_number` INT NOT NULL,
    `current` DECIMAL(6,2) DEFAULT 0 COMMENT 'A',
    `voltage` DECIMAL(6,1) DEFAULT 0 COMMENT 'V',
    `is_active` TINYINT(1) DEFAULT 0 COMMENT '1 if current > 0.5A',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_str_plant_inv` (`plant_id`, `inverter_name`),
    INDEX `idx_str_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transformer_readings`;
CREATE TABLE `transformer_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `device_name` VARCHAR(100) NOT NULL COMMENT 'Transformer-oil or Transformer-winding',
    `oil_temp` DECIMAL(5,1) DEFAULT NULL COMMENT 'degC',
    `winding_temp` DECIMAL(5,1) DEFAULT NULL COMMENT 'degC',
    `status` VARCHAR(20) DEFAULT 'normal' COMMENT 'normal/warning',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_trafo_plant` (`plant_id`),
    INDEX `idx_trafo_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `weather_readings`;
CREATE TABLE `weather_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `radiation` DECIMAL(8,2) DEFAULT 0 COMMENT 'W/m2 - raw data',
    `panel_temp` DECIMAL(5,1) DEFAULT 0 COMMENT 'degC - pannel temperature',
    `wind_speed` DECIMAL(5,2) DEFAULT 0 COMMENT 'm/s - windspeed',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wx_plant` (`plant_id`),
    INDEX `idx_wx_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `telemetry_history`;
CREATE TABLE `telemetry_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `metric_type` VARCHAR(50) NOT NULL COMMENT 'vcb_power, inverter_power, oil_temp, winding_temp, radiation',
    `metric_value` DECIMAL(12,2) DEFAULT 0,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hist_plant_type` (`plant_id`, `metric_type`),
    INDEX `idx_hist_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Login credentials 
-- admin@scada.com        / admin         
-- veliyani@scada.com     / admin        
-- makkal@scada.com       / admin         
-- anushyam@scada.com     / admin         
