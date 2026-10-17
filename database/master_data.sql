-- ========================================================
-- CivicTrack — Smart City Civic Complaint Management System
-- Foundation Master Data: master_data.sql
-- Contains only essential municipal structure:
-- 1. Departments
-- 2. Complaint Categories with SLA hours
-- 3. System Settings
-- 4. Initial System Administrator Account
-- ZERO fake complaints or user records.
-- ========================================================

USE `civictrack_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Municipal Departments (Master Records)
-- --------------------------------------------------------
DELETE FROM `departments`;
INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_code`, `dept_head_email`, `phone`, `description`, `is_active`) VALUES
(1, 'Roads and Public Works', 'RPW', 'roads.head@smartcity.gov', '+91 22 2541 1001', 'Maintenance of city roads, potholes, pedestrian footpaths, and flyovers.', 1),
(2, 'Sanitation and Waste Management', 'SWM', 'sanitation.head@smartcity.gov', '+91 22 2541 1002', 'Solid waste collection, garbage clearing, street sweeping, and dumping yard maintenance.', 1),
(3, 'Electricity and Streetlights', 'ESL', 'electricity.head@smartcity.gov', '+91 22 2541 1003', 'Public street lighting, electrical poles, junction boxes, and smart streetlamps.', 1),
(4, 'Water Supply and Distribution', 'WSD', 'water.head@smartcity.gov', '+91 22 2541 1004', 'Potable drinking water supply, pipeline leak repairs, and water contamination monitoring.', 1),
(5, 'Drainage and Sewerage', 'DAS', 'drainage.head@smartcity.gov', '+91 22 2541 1005', 'Stormwater drains, unclogging sewage lines, manhole covers, and flooding control.', 1),
(6, 'Public Parks and Infrastructure', 'PPI', 'parks.head@smartcity.gov', '+91 22 2541 1006', 'Public parks, bus shelters, municipal building repairs, and city signage.', 1);

-- --------------------------------------------------------
-- 2. Complaint Categories Mapped to Departments with SLAs
-- --------------------------------------------------------
DELETE FROM `complaint_categories`;
INSERT INTO `complaint_categories` (`category_id`, `category_name`, `dept_id`, `sla_hours`, `default_priority`, `icon_class`, `description`, `is_active`) VALUES
(1, 'Roads and Potholes', 1, 48, 'High', 'bi-signpost-2', 'Road surface craters, broken asphalt, and unpaved hazards.', 1),
(2, 'Streetlights and Electricity', 3, 24, 'Medium', 'bi-lightbulb', 'Non-functional street lamps, dangling wires, open transformer boxes.', 1),
(3, 'Garbage and Sanitation', 2, 24, 'High', 'bi-trash3', 'Overflowing garbage bins, roadside dumping, uncollected trash.', 1),
(4, 'Drainage and Sewage', 5, 36, 'High', 'bi-water', 'Overflowing manholes, choked gutters, stagnant dirty water.', 1),
(5, 'Water Leakage and Supply', 4, 24, 'Urgent', 'bi-droplet-half', 'Burst water mains, no municipal water supply, contaminated water.', 1),
(6, 'Public Infrastructure', 6, 72, 'Low', 'bi-building-gear', 'Broken pavements, damaged bus stops, damaged public benches.', 1),
(7, 'Other Civic Issues', 1, 48, 'Medium', 'bi-exclamation-octagon', 'General municipal concerns not covered in specific categories.', 1);

-- --------------------------------------------------------
-- 3. Initial Administrator Account
-- Password will be hashed dynamically by setup.php: Admin@123
-- --------------------------------------------------------
DELETE FROM `users` WHERE `role` = 'admin';
INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password`, `role`, `dept_id`, `status`, `address`, `ward`) VALUES
(1, 'Municipal Chief Admin', 'admin@civictrack.gov', '+91 9820011223', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'admin', NULL, 'active', 'City Municipal Corporation HQ, Room 402', 'City Center - Ward 01');

-- --------------------------------------------------------
-- 4. System Settings
-- --------------------------------------------------------
DELETE FROM `system_settings`;
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_name', 'CivicTrack — Smart City Complaint Management System', 'Platform title'),
('municipality_name', 'Metropolitan Smart City Municipal Corporation', 'Governing municipal authority'),
('city_center_lat', '19.0760', 'Default map latitude center'),
('city_center_lng', '72.8777', 'Default map longitude center'),
('city_zoom_level', '12', 'Default Leaflet map zoom level'),
('helpline_phone', '1800-22-2026', 'Toll-free 24/7 civic helpline'),
('support_email', 'helpdesk@civictrack.gov', 'Public grievance support email'),
('sla_alert_hours_before', '6', 'Hours before deadline to mark as SLA at risk');

SET FOREIGN_KEY_CHECKS = 1;
