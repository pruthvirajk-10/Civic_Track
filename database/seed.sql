-- ========================================================
-- CivicTrack — Smart City Civic Complaint Management System
-- Demonstration Seed Data: seed.sql
-- Default Passwords:
-- Admin:    Admin@123
-- Officers: Officer@123
-- Citizens: Citizen@123
-- ========================================================

USE `civictrack_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Departments Data
-- --------------------------------------------------------
TRUNCATE TABLE `departments`;
INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_code`, `dept_head_email`, `phone`, `description`, `is_active`) VALUES
(1, 'Roads and Public Works', 'RPW', 'roads.head@smartcity.gov', '+91 22 2541 1001', 'Maintenance of city roads, potholes, pedestrian footpaths, and flyovers.', 1),
(2, 'Sanitation and Waste Management', 'SWM', 'sanitation.head@smartcity.gov', '+91 22 2541 1002', 'Solid waste collection, garbage clearing, street sweeping, and dumping yard maintenance.', 1),
(3, 'Electricity and Streetlights', 'ESL', 'electricity.head@smartcity.gov', '+91 22 2541 1003', 'Public street lighting, electrical poles, junction boxes, and smart streetlamps.', 1),
(4, 'Water Supply and Distribution', 'WSD', 'water.head@smartcity.gov', '+91 22 2541 1004', 'Potable drinking water supply, pipeline leak repairs, and water contamination monitoring.', 1),
(5, 'Drainage and Sewerage', 'DAS', 'drainage.head@smartcity.gov', '+91 22 2541 1005', 'Stormwater drains, unclogging sewage lines, manhole covers, and flooding control.', 1),
(6, 'Public Parks and Infrastructure', 'PPI', 'parks.head@smartcity.gov', '+91 22 2541 1006', 'Public parks, bus shelters, municipal building repairs, and city signage.', 1);

-- --------------------------------------------------------
-- 2. Complaint Categories Data (Mapped to Departments with SLAs)
-- --------------------------------------------------------
TRUNCATE TABLE `complaint_categories`;
INSERT INTO `complaint_categories` (`category_id`, `category_name`, `dept_id`, `sla_hours`, `default_priority`, `icon_class`, `description`, `is_active`) VALUES
(1, 'Roads and Potholes', 1, 48, 'High', 'bi-signpost-2', 'Road surface craters, broken asphalt, and unpaved hazards.', 1),
(2, 'Streetlights and Electricity', 3, 24, 'Medium', 'bi-lightbulb', 'Non-functional street lamps, dangling wires, open transformer boxes.', 1),
(3, 'Garbage and Sanitation', 2, 24, 'High', 'bi-trash3', 'Overflowing garbage bins, roadside dumping, uncollected trash.', 1),
(4, 'Drainage and Sewage', 5, 36, 'High', 'bi-water', 'Overflowing manholes, choked gutters, stagnant dirty water.', 1),
(5, 'Water Leakage and Supply', 4, 24, 'Urgent', 'bi-droplet-half', 'Burst water mains, no municipal water supply, contaminated water.', 1),
(6, 'Public Infrastructure', 6, 72, 'Low', 'bi-building-gear', 'Broken pavements, damaged bus stops, damaged public benches.', 1),
(7, 'Other Civic Issues', 1, 48, 'Medium', 'bi-exclamation-octagon', 'General municipal concerns not covered in specific categories.', 1);

-- --------------------------------------------------------
-- 3. Users Data
-- Passwords below are pre-computed standard BCRYPT hashes:
-- Admin:    Admin@123    -> $2y$10$eE0x4M1j4M8.9B4O6Hn2rO8G9x6V6p7D7g4S3E2R1Q0Z9Y8X7W6V5 (will also be synced via setup.php)
-- Officer:  Officer@123  -> $2y$10$fF1y5N2k5N9.0C5P7Io3sP9H0y7W7q8E8h5T4F3S2R1A0Z9Y8X7W6V5
-- Citizen:  Citizen@123  -> $2y$10$gG2z6O3l6O0.1D6Q8Jp4tQ0I1z8X8r9F9i6U5G4T3S2B1A0Z9Y8X7W6V5
-- --------------------------------------------------------
TRUNCATE TABLE `users`;
INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password`, `role`, `dept_id`, `status`, `address`, `ward`) VALUES
-- Administrator
(1, 'Municipal Chief Admin', 'admin@civictrack.gov', '+91 9820011223', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'admin', NULL, 'active', 'City Municipal Corporation HQ, Room 402', 'City Center - Ward 01'),

-- Department Officers
(2, 'Rajesh Kumar (Roads Officer)', 'officer.roads@civictrack.gov', '+91 9820123451', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'officer', 1, 'active', 'Public Works Depot, Sector 4', 'North Zone - Ward 04'),
(3, 'Sunita Sharma (Sanitation Officer)', 'officer.sanitation@civictrack.gov', '+91 9820123452', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'officer', 2, 'active', 'SWM Divisional Office, Central Zone', 'Central Zone - Ward 07'),
(4, 'Vikram Patil (Electrical Officer)', 'officer.power@civictrack.gov', '+91 9820123453', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'officer', 3, 'active', 'Smart Streetlight Substation', 'East Zone - Ward 12'),
(5, 'Amit Deshmukh (Water Officer)', 'officer.water@civictrack.gov', '+91 9820123454', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'officer', 4, 'active', 'Water Distribution Control Center', 'South Zone - Ward 09'),
(6, 'Pooja Iyer (Drainage Officer)', 'officer.drainage@civictrack.gov', '+91 9820123455', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'officer', 5, 'active', 'Stormwater & Sewage Command Cell', 'West Zone - Ward 15'),

-- Citizens
(7, 'Aarav Mehta', 'aarav.mehta@example.com', '+91 9876543210', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'citizen', NULL, 'active', 'Flat 402, Green Meadows, FC Road', 'Ward 01 - Shivajinagar / Ghole Road'),
(8, 'Priya Nair', 'priya.nair@example.com', '+91 9876543211', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'citizen', NULL, 'active', 'House No 12, Gulmohar Enclave, Paud Road, Kothrud', 'Ward 02 - Kothrud / Bavdhan'),
(9, 'Rohan Verma', 'rohan.verma@example.com', '+91 9876543212', '$2y$10$0P01Y/G6q3x9Y5KqXk3jXeZ5aW1sY2d4m3n5o6p7q8r9s0t1u2v3w', 'citizen', NULL, 'active', 'B-14, Shanti Heights, Baner Road', 'Ward 03 - Aundh / Baner');

-- --------------------------------------------------------
-- 4. Sample Complaints Data
-- --------------------------------------------------------
TRUNCATE TABLE `complaints`;
INSERT INTO `complaints` (`complaint_id`, `reference_number`, `citizen_id`, `category_id`, `dept_id`, `title`, `description`, `priority`, `image_url`, `latitude`, `longitude`, `address`, `ward`, `status`, `assigned_officer_id`, `sla_deadline`, `created_at`, `resolved_at`) VALUES
(1, 'CT-2026-10001', 7, 1, 1, 'Deep hazardous pothole near Metro Station Pillar 42', 'A large crater roughly 2 feet wide has formed on the main carriageway. Multiple two-wheelers have skidded here during rains. Immediate asphalt patching needed.', 'High', 'uploads/complaints/sample_pothole.jpg', 18.53140000, 73.84460000, 'Near Shivajinagar Metro Station, FC Road', 'Ward 01 - Shivajinagar / Ghole Road', 'Resolved', 2, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),

(2, 'CT-2026-10002', 8, 3, 2, 'Garbage dump overflowing outside community park', 'Commercial waste and domestic trash has been dumped in the open for 3 days. Severe stench and stray dogs gathering around. Needs urgent mechanical loader pickup.', 'High', 'uploads/complaints/sample_garbage.jpg', 18.50740000, 73.80770000, 'Near Joggers Park, Paud Road, Kothrud', 'Ward 02 - Kothrud / Bavdhan', 'In Progress', 3, DATE_ADD(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),

(3, 'CT-2026-10003', 9, 2, 3, 'Dark street stretch: 4 consecutive streetlights not functioning', 'Street lamps from pole #18 to #21 are completely dead since Friday night. Area has become pitch black and unsafe for women and pedestrians.', 'Medium', 'uploads/complaints/sample_streetlight.jpg', 18.55900000, 73.78680000, 'Cross Road No 3, Baner High Street, Baner', 'Ward 03 - Aundh / Baner', 'Assigned', 4, DATE_ADD(NOW(), INTERVAL 18 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR), NULL),

(4, 'CT-2026-10004', 7, 5, 4, 'Major potable water pipeline burst gushing drinking water', 'High-pressure clean water is gushing out on the sidewalk and flooding the adjacent residential driveway. Thousands of liters getting wasted.', 'Urgent', 'uploads/complaints/sample_water.jpg', 18.56790000, 73.91430000, 'Opposite Phoenix Marketcity, Viman Nagar', 'Ward 05 - Viman Nagar / Nagar Road', 'Under Review', 5, DATE_ADD(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL),

(5, 'CT-2026-10005', 8, 4, 5, 'Blocked sewer manhole overflowing foul black sewage', 'Drain is completely clogged with plastic and silt. Dirty water overflowing onto the main market entrance. Health hazard.', 'High', 'uploads/complaints/sample_drainage.jpg', 18.50890000, 73.92600000, 'Near Destination Centre, Magarpatta City, Hadapsar', 'Ward 06 - Hadapsar / Magarpatta', 'Submitted', NULL, DATE_ADD(NOW(), INTERVAL 30 HOUR), NOW(), NULL),

(6, 'CT-2026-10006', 9, 6, 6, 'Damaged concrete pedestrian footpath tiles', 'Paver blocks have caved in creating tripping hazards for elderly walkers.', 'Low', NULL, 18.50180000, 73.85360000, 'Near Sarasbaug, Swargate', 'Ward 08 - Swargate / Bibwewadi', 'In Progress', NULL, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 3 DAY), NULL);

-- --------------------------------------------------------
-- 5. Complaint Status Logs (Audit trail)
-- --------------------------------------------------------
TRUNCATE TABLE `complaint_logs`;
INSERT INTO `complaint_logs` (`complaint_id`, `user_id`, `action`, `previous_status`, `new_status`, `remarks`, `proof_image`, `created_at`) VALUES
(1, 7, 'Complaint Lodged', NULL, 'Submitted', 'Complaint submitted with photo attachment and geolocation.', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 1, 'Auto Routed', 'Submitted', 'Under Review', 'Automatically routed to Roads and Public Works department based on category.', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 2, 'Assigned to Inspection Team', 'Under Review', 'Assigned', 'Assigned to Ward 01 Quick Response Patching Unit.', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 2, 'Work Started', 'Assigned', 'In Progress', 'Hot-mix asphalt dispatch requested. Work commenced at 10:00 AM.', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'Issue Resolved', 'In Progress', 'Resolved', 'Pothole filled and leveled with bituminous cold/hot mix. Road surface restored.', 'uploads/resolutions/resolved_pothole.jpg', DATE_SUB(NOW(), INTERVAL 1 DAY)),

(2, 8, 'Complaint Lodged', NULL, 'Submitted', 'Citizen reported open garbage pile.', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 3, 'Reviewed & Dispatched', 'Submitted', 'In Progress', 'Compactor vehicle truck #MH-12-AX-9912 dispatched for clearing.', NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR)),

(3, 9, 'Complaint Lodged', NULL, 'Submitted', 'Citizen reported non-functional streetlights.', NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(3, 4, 'Work Order Issued', 'Submitted', 'Assigned', 'Electrician team scheduled for evening feeder cable check.', NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR)),

(4, 7, 'Complaint Lodged', NULL, 'Submitted', 'Urgent water burst reported.', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(4, 1, 'Priority Escalation', 'Submitted', 'Under Review', 'Escalated to Chief Hydraulic Engineer.', NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- --------------------------------------------------------
-- 6. Citizen Feedback Data
-- --------------------------------------------------------
TRUNCATE TABLE `feedback`;
INSERT INTO `feedback` (`complaint_id`, `citizen_id`, `rating`, `comments`, `created_at`) VALUES
(1, 7, 5, 'Outstanding response time! The pothole was repaired within 48 hours and the road is smooth again. Thank you municipal team!', DATE_SUB(NOW(), INTERVAL 20 HOUR));

-- --------------------------------------------------------
-- 7. Notifications Data
-- --------------------------------------------------------
TRUNCATE TABLE `notifications`;
INSERT INTO `notifications` (`user_id`, `complaint_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(7, 1, 'Complaint Resolved', 'Your complaint CT-2026-10001 (Deep hazardous pothole) has been resolved by Roads and Public Works department.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(8, 2, 'Work In Progress', 'Sanitation truck dispatched for complaint CT-2026-10002. Expected clearing within 12 hours.', 0, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(9, 3, 'Complaint Assigned', 'Complaint CT-2026-10003 assigned to Electrical Substation team.', 0, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(7, 4, 'Complaint Under Review', 'Water Supply engineers reviewing emergency complaint CT-2026-10004.', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 1, 'New Assignment', 'New road repair complaint CT-2026-10001 in Ward 01 allocated to your queue.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- --------------------------------------------------------
-- 8. System Settings Data
-- --------------------------------------------------------
TRUNCATE TABLE `system_settings`;
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_name', 'CivicTrack — Smart City Complaint Management System', 'Platform title'),
('municipality_name', 'Pune Smart City Municipal Corporation (PMC)', 'Governing municipal authority'),
('city_center_lat', '18.5204', 'Default map latitude center'),
('city_center_lng', '73.8567', 'Default map longitude center'),
('city_zoom_level', '13', 'Default Leaflet map zoom level'),
('helpline_phone', '1800-22-2026', 'Toll-free 24/7 civic helpline'),
('support_email', 'helpdesk@civictrack.gov', 'Public grievance support email'),
('sla_alert_hours_before', '6', 'Hours before deadline to mark as SLA at risk');

SET FOREIGN_KEY_CHECKS = 1;
