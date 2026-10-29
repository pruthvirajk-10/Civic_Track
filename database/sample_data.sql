-- ========================================================
-- CivicTrack — Smart City Civic Complaint Management System
-- Optional Sample Demonstration Data: sample_data.sql
-- FOR DEMONSTRATION & PROJECT EVALUATION ONLY
-- ========================================================

USE `civictrack_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Sample Department Officers & Test Citizens
-- --------------------------------------------------------
DELETE FROM `users` WHERE `role` IN ('officer', 'citizen');

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password`, `role`, `dept_id`, `status`, `address`, `ward`) VALUES
-- Department Officers
(2, 'Rajesh Kumar (Roads Officer)', 'officer.roads@civictrack.gov', '+91 9820123451', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'officer', 1, 'active', 'Public Works Depot, Sector 4', 'North Zone - Ward 04'),
(3, 'Sunita Sharma (Sanitation Officer)', 'officer.sanitation@civictrack.gov', '+91 9820123452', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'officer', 2, 'active', 'SWM Divisional Office, Central Zone', 'Central Zone - Ward 07'),
(4, 'Vikram Patil (Electrical Officer)', 'officer.power@civictrack.gov', '+91 9820123453', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'officer', 3, 'active', 'Smart Streetlight Substation', 'East Zone - Ward 12'),
(5, 'Amit Deshmukh (Water Officer)', 'officer.water@civictrack.gov', '+91 9820123454', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'officer', 4, 'active', 'Water Distribution Control Center', 'South Zone - Ward 09'),
(6, 'Pooja Iyer (Drainage Officer)', 'officer.drainage@civictrack.gov', '+91 9820123455', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'officer', 5, 'active', 'Stormwater & Sewage Command Cell', 'West Zone - Ward 15'),

-- Test Citizens
(7, 'Aarav Mehta', 'aarav.mehta@example.com', '+91 9876543210', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'citizen', NULL, 'active', 'Flat 402, Green Meadows, FC Road', 'Ward 01 - Shivajinagar / Ghole Road'),
(8, 'Priya Nair', 'priya.nair@example.com', '+91 9876543211', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'citizen', NULL, 'active', 'House No 12, Gulmohar Enclave, Paud Road, Kothrud', 'Ward 02 - Kothrud / Bavdhan'),
(9, 'Rohan Verma', 'rohan.verma@example.com', '+91 9876543212', '$2y$10$vQ3E5YgQz5jE2jK/1QyU5O9v.rN7XmR8f6sI.p7z4tA2x0hZ8s8hO', 'citizen', NULL, 'active', 'B-14, Shanti Heights, Baner Road', 'Ward 03 - Aundh / Baner');

-- --------------------------------------------------------
-- Sample Complaints
-- --------------------------------------------------------
DELETE FROM `complaints`;
INSERT INTO `complaints` (`complaint_id`, `reference_number`, `citizen_id`, `category_id`, `dept_id`, `title`, `description`, `priority`, `image_url`, `latitude`, `longitude`, `address`, `ward`, `status`, `assigned_officer_id`, `sla_deadline`, `created_at`, `resolved_at`) VALUES
(1, 'CT-2026-10001', 7, 1, 1, 'Deep hazardous pothole near Metro Station Pillar 42', 'A large crater roughly 2 feet wide has formed on the main carriageway. Multiple two-wheelers have skidded here during rains. Immediate asphalt patching needed.', 'High', 'uploads/complaints/sample_pothole.jpg', 18.53140000, 73.84460000, 'Near Shivajinagar Metro Station, FC Road', 'Ward 01 - Shivajinagar / Ghole Road', 'Resolved', 2, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),

(2, 'CT-2026-10002', 8, 3, 2, 'Garbage dump overflowing outside community park', 'Commercial waste and domestic trash has been dumped in the open for 3 days. Severe stench and stray dogs gathering around. Needs urgent mechanical loader pickup.', 'High', 'uploads/complaints/sample_garbage.jpg', 18.50740000, 73.80770000, 'Near Joggers Park, Paud Road, Kothrud', 'Ward 02 - Kothrud / Bavdhan', 'In Progress', 3, DATE_ADD(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),

(3, 'CT-2026-10003', 9, 2, 3, 'Dark street stretch: 4 consecutive streetlights not functioning', 'Street lamps from pole #18 to #21 are completely dead since Friday night. Area has become pitch black and unsafe for women and pedestrians.', 'Medium', 'uploads/complaints/sample_streetlight.jpg', 18.55900000, 73.78680000, 'Cross Road No 3, Baner High Street, Baner', 'Ward 03 - Aundh / Baner', 'Assigned', 4, DATE_ADD(NOW(), INTERVAL 18 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR), NULL),

(4, 'CT-2026-10004', 7, 5, 4, 'Major potable water pipeline burst gushing drinking water', 'High-pressure clean water is gushing out on the sidewalk and flooding the adjacent residential driveway. Thousands of liters getting wasted.', 'Urgent', 'uploads/complaints/sample_water.jpg', 18.56790000, 73.91430000, 'Opposite Phoenix Marketcity, Viman Nagar', 'Ward 05 - Viman Nagar / Nagar Road', 'Under Review', 5, DATE_ADD(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL),

(5, 'CT-2026-10005', 8, 4, 5, 'Blocked sewer manhole overflowing foul black sewage', 'Drain is completely clogged with plastic and silt. Dirty water overflowing onto the main market entrance. Health hazard.', 'High', 'uploads/complaints/sample_drainage.jpg', 18.50890000, 73.92600000, 'Near Destination Centre, Magarpatta City, Hadapsar', 'Ward 06 - Hadapsar / Magarpatta', 'Submitted', NULL, DATE_ADD(NOW(), INTERVAL 30 HOUR), NOW(), NULL),

(6, 'CT-2026-10006', 9, 6, 6, 'Damaged concrete pedestrian footpath tiles', 'Paver blocks have caved in creating tripping hazards for elderly walkers.', 'Low', NULL, 18.50180000, 73.85360000, 'Near Sarasbaug, Swargate', 'Ward 08 - Swargate / Bibwewadi', 'In Progress', NULL, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 3 DAY), NULL);

-- --------------------------------------------------------
-- Sample Complaint Logs
-- --------------------------------------------------------
DELETE FROM `complaint_logs`;
INSERT INTO `complaint_logs` (`complaint_id`, `user_id`, `action`, `previous_status`, `new_status`, `remarks`, `proof_image`, `created_at`) VALUES
(1, 7, 'Complaint Lodged', NULL, 'Submitted', 'Complaint submitted with photo attachment and geolocation.', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 1, 'Auto Routed', 'Submitted', 'Under Review', 'Automatically routed to Roads and Public Works department based on category.', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 2, 'Assigned to Inspection Team', 'Under Review', 'Assigned', 'Assigned to Ward 04 Quick Response Patching Unit.', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 2, 'Work Started', 'Assigned', 'In Progress', 'Hot-mix asphalt dispatch requested. Work commenced at 10:00 AM.', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'Issue Resolved', 'In Progress', 'Resolved', 'Pothole filled and leveled with bituminous cold/hot mix. Road surface restored.', 'uploads/resolutions/resolved_pothole.jpg', DATE_SUB(NOW(), INTERVAL 1 DAY)),

(2, 8, 'Complaint Lodged', NULL, 'Submitted', 'Citizen reported open garbage pile.', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 3, 'Reviewed & Dispatched', 'Submitted', 'In Progress', 'Compactor vehicle truck #MH-04-AX-9912 dispatched for clearing.', NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR)),

(3, 9, 'Complaint Lodged', NULL, 'Submitted', 'Citizen reported non-functional streetlights.', NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(3, 4, 'Work Order Issued', 'Submitted', 'Assigned', 'Electrician team scheduled for evening feeder cable check.', NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR)),

(4, 7, 'Complaint Lodged', NULL, 'Submitted', 'Urgent water burst reported.', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(4, 1, 'Priority Escalation', 'Submitted', 'Under Review', 'Escalated to Chief Hydraulic Engineer.', NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- --------------------------------------------------------
-- Sample Feedback
-- --------------------------------------------------------
DELETE FROM `feedback`;
INSERT INTO `feedback` (`complaint_id`, `citizen_id`, `rating`, `comments`, `created_at`) VALUES
(1, 7, 5, 'Outstanding response time! The pothole was repaired within 48 hours and the road is smooth again. Thank you municipal team!', DATE_SUB(NOW(), INTERVAL 20 HOUR));

-- --------------------------------------------------------
-- Sample Notifications
-- --------------------------------------------------------
DELETE FROM `notifications`;
INSERT INTO `notifications` (`user_id`, `complaint_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(7, 1, 'Complaint Resolved', 'Your complaint CT-2026-10001 (Deep hazardous pothole) has been resolved by Roads and Public Works department.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(8, 2, 'Work In Progress', 'Sanitation truck dispatched for complaint CT-2026-10002. Expected clearing within 12 hours.', 0, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(9, 3, 'Complaint Assigned', 'Complaint CT-2026-10003 assigned to Electrical Substation team.', 0, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(7, 4, 'Complaint Under Review', 'Water Supply engineers reviewing emergency complaint CT-2026-10004.', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 1, 'New Assignment', 'New road repair complaint CT-2026-10001 in Ward 04 allocated to your queue.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

SET FOREIGN_KEY_CHECKS = 1;
