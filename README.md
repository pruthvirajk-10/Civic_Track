# CivicTrack — Smart City Civic Complaint Management & Resolution Tracking System

**Internship Project Assignment — Team 4**  
*A modern, enterprise-grade, secure, and responsive municipal grievance redressal web application.*

---

## 📌 Project Overview

**CivicTrack** is a web-based civic technology platform designed for Metropolitan Municipal Corporations. It empowers citizens to report civic hazards—such as potholes, overflowing garbage dumps, damaged streetlights, water pipeline bursts, and blocked drainage lines—while providing local government authorities with automated department routing, committed Service Level Agreement (SLA) deadlines, audit logging, and executive data analytics.

### 🌟 Key Capabilities
- **Citizen Portal**:
  - Secure registration & authentication with bcrypt password hashing.
  - Multi-section grievance submission with photographic evidence and Leaflet interactive map / GPS geolocation pinpointing.
  - Automatic department routing based on category mapping.
  - Live complaint tracking with a visual timeline (`Submitted` ➔ `Under Review` ➔ `Assigned` ➔ `In Progress` ➔ `Resolved`).
  - Citizen resolution satisfaction rating (1–5 stars) and formal grievance reopening if repairs are unsatisfactory.
  - In-app notification system with read tracking.
- **Department Officer Portal**:
  - Filtered department queue restricted to officer's assigned civic division.
  - Urgency indicators and SLA breach / overdue warning alerts.
  - Status updates with field progress remarks and after-repair resolution photograph uploads.
  - Complete auditable activity history logged in `complaint_logs`.
- **Administrator Command Center**:
  - Executive KPI metrics computed from live MySQL queries.
  - Interactive Chart.js charts: Category Distribution (Doughnut), Status Lifecycle (Bar), 7-Day Trend (Line), Department Clearance (Horizontal Bar).
  - Full CRUD management of municipal departments and category SLA configurations.
  - User account administration and department officer account creation.
  - Administrative override (reassign departments, assign specific officers, adjust priorities).
  - Date-range and multi-criteria analytical reports with one-click secure CSV export.
- **Public Grievance Tracker**:
  - Anyone can look up any ticket reference number (e.g. `CT-2026-10001`) to inspect real-time progress and before/after repair photos without logging in.

---

## 💻 Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Backend** | PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3 (Modular, Object-safe PDO Prepared Statements) |
| **Database** | MySQL / MariaDB (InnoDB engine, Foreign Keys, UTF8mb4 Charset) |
| **Frontend** | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3.3 |
| **Icons** | Bootstrap Icons 1.11.3 |
| **Interactive Maps** | Leaflet.js with OpenStreetMap tiles |
| **Charts & Analytics** | Chart.js 4.4.2 |
| **Local Server Environment**| XAMPP / WAMP / LAMP (Apache + MySQL) or PHP Built-in Server |

---

## 📂 Project Directory Structure

```text
d:/Antigravity/
├── config/
│   ├── app.php                   # Timezone, base URL resolver, upload limits, session security
│   └── database.php              # PDO database credentials & singleton connection
├── includes/
│   ├── auth.php                  # Authentication, role verification (requireRole), session helpers
│   ├── csrf.php                  # CSRF token generation & validation
│   ├── functions.php             # XSS escaping, safe file upload handler, SLA math, badges, logging
│   ├── header.php                # Shared HTML header (Bootstrap, Leaflet, Google Fonts)
│   ├── footer.php                # Shared HTML footer & scripts
│   ├── navbar.php                # Public responsive navigation
│   └── sidebar.php               # Dynamic role-aware dashboard sidebar
├── database/
│   ├── schema.sql                # Complete InnoDB MySQL database schema
│   └── seed.sql                  # Demonstration seed data with sample complaints & logs
├── assets/
│   ├── css/
│   │   ├── style.css             # Main styling, Smart City identity, landing page, hero
│   │   └── dashboard.css         # Dashboard UI, KPI cards, timeline, tables, mobile navigation
│   └── js/
│       ├── main.js               # Tooltips, auto-dismissing alerts, sidebar mobile toggle
│       ├── complaints.js         # Leaflet map picker, GPS detection, photo previews, star rating
│       └── dashboard.js          # Chart.js initializers & AJAX notification reader
├── citizen/
│   ├── dashboard.php             # Citizen KPI metrics and recent complaints
│   ├── lodge-complaint.php       # Grievance lodging form with GPS map pin & photo upload
│   ├── my-complaints.php         # Searchable, filterable list of citizen's complaints
│   ├── complaint-details.php     # Detailed timeline, officer notes, feedback form, reopen modal
│   ├── notifications.php         # In-app notifications center with mark-all-read
│   └── profile.php               # Citizen profile update & password change
├── officer/
│   ├── dashboard.php             # Department officer desk with SLA overdue warnings
│   ├── assigned-complaints.php   # Assigned complaint queue with filters
│   ├── complaint-details.php     # Status updates, resolution proof upload, progress remarks
│   └── profile.php               # Officer profile & password change
├── admin/
│   ├── dashboard.php             # Executive command center with live charts & KPIs
│   ├── complaints.php            # Citywide complaints management with multi-filters
│   ├── complaint-details.php     # Executive override, department reassignment, officer assignment
│   ├── departments.php           # Department CRUD operations
│   ├── categories.php            # Category & SLA hours configuration
│   ├── users.php                 # User account management & officer creation
│   ├── reports.php               # Analytical reports & ward hotspot breakdowns
│   ├── export-csv.php            # Secure CSV report generator
│   └── settings.php              # Municipal branding & GIS settings
├── api/
│   ├── get_analytics.php         # Live stats JSON endpoint
│   ├── get_categories.php        # Categories and SLA hours JSON endpoint
│   ├── mark_notification_read.php# AJAX endpoint for notification read status
│   └── search_complaints.php     # Quick autocomplete search endpoint
├── uploads/
│   ├── complaints/               # Uploaded citizen evidence photographs
│   └── resolutions/              # Uploaded official resolution proof photographs
├── index.php                     # Public landing page with live database statistics
├── track.php                     # Public complaint status tracker (search by Reference #)
├── login.php                     # Unified role-based login with 1-click demo shortcuts
├── register.php                  # Citizen registration with instant session login
├── logout.php                    # Secure session destruction
├── setup.php                     # 1-Click Web Database Installer & Seeder
└── README.md                     # Comprehensive documentation
```

---

## 🚀 Quick Setup & Installation Guide (XAMPP)

### Step 1: Prerequisites
- Install **XAMPP** (or WAMP / LAMP) with **PHP 7.4 or higher** and **MySQL / MariaDB**.
- Launch **XAMPP Control Panel** and start both **Apache** and **MySQL**.

### Step 2: Place Project in Web Root
1. Copy the project folder into your XAMPP web root directory:
   ```text
   C:\xampp\htdocs\civictrack\
   ```
   *(Alternatively, if running directly in `d:\Antigravity`, you can run PHP's built-in server as shown in Step 4 below).*

### Step 3: Initialize Database (1-Click Automated Method)
CivicTrack includes an automated installer that creates the database, all tables, foreign keys, and seeds demonstration records.

1. Open your web browser and navigate to:
   ```text
   http://localhost/civictrack/setup.php
   ```
2. Click **"Click Here to Initialize Database Now"**.
3. The setup script will:
   - Create database `civictrack_db`.
   - Execute `database/schema.sql`.
   - Seed sample departments, categories, and test grievances from `database/seed.sql`.
   - Hash all test passwords using native PHP `password_hash()`.
   - Verify upload directories.

*(Manual phpMyAdmin alternative: Open `http://localhost/phpmyadmin/`, create a database named `civictrack_db`, and import `database/schema.sql` followed by `database/seed.sql`).*

### Step 4: Running via PHP Built-in Server (Alternative to Apache)
If you prefer not to use XAMPP's Apache web server, open PowerShell or Terminal in the project root:
```powershell
cd d:\Antigravity
php -S localhost:8000
```
Then access: `http://localhost:8000/setup.php` and `http://localhost:8000/index.php`.

---

## 🔑 Pre-Configured Test Credentials

The database seeder comes pre-loaded with demonstration accounts for each role:

| Role | Email | Password | Description |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@civictrack.gov` | `Admin@123` | Municipal Chief Admin (Full system control) |
| **Roads Officer** | `officer.roads@civictrack.gov` | `Officer@123` | Assigned to *Roads and Public Works* |
| **Sanitation Officer** | `officer.sanitation@civictrack.gov` | `Officer@123` | Assigned to *Sanitation and Waste Management* |
| **Electrical Officer** | `officer.power@civictrack.gov` | `Officer@123` | Assigned to *Electricity and Streetlights* |
| **Citizen (Sample 1)** | `aarav.mehta@example.com` | `Citizen@123` | Registered Citizen (Ward 04 - Bandra West) |
| **Citizen (Sample 2)** | `priya.nair@example.com` | `Citizen@123` | Registered Citizen (Ward 07 - Powai) |

> 💡 **Tip:** On the [login.php](login.php) page, you can use the **"1-Click Demo Login Shortcuts"** to autofill Admin, Officer, or Citizen credentials instantly!

---

## 🧪 Testing Complete Workflows

### Workflow A — Citizen Lifecycle:
1. Navigate to `http://localhost/civictrack/index.php`.
2. Click **"Register"** and create a new account, or log in with `aarav.mehta@example.com`.
3. Click **"Lodge New Grievance"**.
4. Enter title (e.g. *"Large road crater on 5th cross"*), choose category **Roads and Potholes**, and pick priority.
5. Use the Leaflet map to drop a pin or click **"Detect My GPS Location"**.
6. Attach a photo and submit. Note the generated Reference Number (e.g. `CT-2026-XXXXX`).
7. Inspect the ticket in **"My Complaints"** and review the progress timeline.

### Workflow B — Department Officer Lifecycle:
1. Log in with `officer.roads@civictrack.gov` (Password: `Officer@123`).
2. You are directed to the **Officer Desk**. Notice the queue and SLA indicators.
3. Open the newly submitted complaint or sample ticket `CT-2026-10001`.
4. In the action panel, select new status (e.g. `In Progress` or `Resolved`).
5. Enter field progress remarks (e.g. *"Hot-mix patching crew dispatched at 10:00 AM"*).
6. Attach an after-repair proof photograph and click **"Save Status & Notify Citizen"**.
7. The status transitions, an audit log entry is permanently saved, and an in-app notification is sent to the citizen.

### Workflow C — Citizen Feedback & Reopening:
1. Log back in as the citizen owner of the resolved complaint.
2. Open the complaint details page.
3. Notice the resolution proof photograph uploaded by the officer.
4. Fill in the **Star Rating (1 to 5 stars)** and leave a comment.
5. If the work was unsatisfactory, click **"Not Resolved? Reopen"** to return the ticket to the department queue with an escalation notice.

### Workflow D — Administrator Governance:
1. Log in with `admin@civictrack.gov` (Password: `Admin@123`).
2. View the **Executive Command Console** with real-time KPI cards and Chart.js charts.
3. Navigate to **"All Complaints"** to filter by department, ward, date, and overdue status.
4. Navigate to **"Departments"** to add a new department or toggle active status.
5. Navigate to **"SLA & Categories"** to adjust SLA hours (e.g., reduce streetlight SLA from 24h to 12h).
6. Navigate to **"User Accounts"** to create a new Department Officer account.
7. Navigate to **"Reports"** to view ward hotspots and click **"Export Filtered CSV"**.

---

## 🛡️ Security Implementation

- **SQL Injection Prevention**: 100% of database interactions use **PDO prepared statements** with bound parameters.
- **Cross-Site Request Forgery (CSRF)**: All state-changing forms (complaint submission, status updates, user management, feedback, settings) require a cryptographically secure token verified via `verifyCsrfToken()`.
- **Cross-Site Scripting (XSS)**: Output rendering is sanitized using the helper function `e()` (`htmlspecialchars` with `ENT_QUOTES`).
- **Secure Authentication**: Passwords hashed with `password_hash($pass, PASSWORD_BCRYPT)` and verified using `password_verify()`.
- **Session Security**: Session IDs are regenerated upon login (`session_regenerate_id(true)`) to mitigate session fixation; `cookie_httponly` and `use_only_cookies` are enforced.
- **Insecure Direct Object Reference (IDOR) Protection**: Citizens can strictly view and rate complaints matching their `citizen_id`; officers are restricted strictly to complaints belonging to their assigned `dept_id`.
- **Safe File Uploads**: Uploaded images are validated for size (<= 5MB), MIME type (`image/jpeg`, `image/png`, `image/webp`), and given randomized alphanumeric filenames to prevent executable uploads or directory traversal.

---

## 🗄️ Database Architecture

The MySQL database schema (`database/schema.sql`) contains 8 relational tables:

1. `users` — Citizens, Officers, and Admins (`role`, `dept_id`, `status`, password hash).
2. `departments` — Municipal divisions (`dept_name`, `dept_code`, `dept_head_email`, `is_active`).
3. `complaint_categories` — Issue types mapped to departments with committed `sla_hours` and `default_priority`.
4. `complaints` — Grievance records (`reference_number`, `citizen_id`, `category_id`, `dept_id`, `status`, `priority`, `latitude`, `longitude`, `sla_deadline`, `resolved_at`).
5. `complaint_logs` — Immutable audit trail of every status transition, acting user, notes, and proof photos.
6. `feedback` — Citizen satisfaction ratings (1–5 stars) and comments.
7. `notifications` — In-app citizen and officer notification alerts.
8. `system_settings` — Municipal authority branding, contact helplines, and GIS coordinates.

---

## ❓ Troubleshooting

1. **"Unknown database civictrack_db"**:
   - Run `http://localhost/civictrack/setup.php` in your browser to create the database with 1 click.
2. **"MySQL Connection Failed"**:
   - Check that MySQL is running in your XAMPP Control Panel.
   - Verify credentials in [config/database.php](config/database.php) (Default: Host `localhost`, User `root`, Password empty `""`).
3. **Uploads Failing**:
   - Ensure the folders `uploads/complaints/` and `uploads/resolutions/` exist and have write permissions (created automatically by `setup.php`).
   - Ensure your PHP `upload_max_filesize` in `php.ini` is at least 5M (standard XAMPP default is 40M).

---

## 👨‍💻 Project Submission Details

- **Project Title**: Smart City Civic Complaint Management and Resolution Tracking System
- **Assigned Team**: **Team 4**
- **Domain**: Smart City Governance, Civic Technology & Municipal Grievance Redressal
- **Developed by**: Team 4 Interns
