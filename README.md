# Organic Matter Detection in Tilapia
### Web-Based Simulation Monitoring System

 Simulation-based · Academic 

---

## 🎯 Project Purpose

The main objective of this system is to monitor the organic matter level in tilapia pond water using a web-based simulation system developed in PHP.

Organic matter such as fish waste, excess feeds, and decaying plants can accumulate in the pond. This can cause low dissolved oxygen, poor water quality, and may lead to fish stress or death.

This system helps users detect high organic levels early, allowing them to take preventive actions to maintain a healthy pond environment. 🐟💧

> **Note:** This project is a simulation-based monitoring system. The data shown in the dashboard is system-generated and not from actual physical sensors. It is intended for academic demonstration and learning purposes only.

---

## 🛠️ System Features

### 📊 Simulated Real-Time Monitoring
Displays continuously generated water condition data including organic matter level (%), water temperature (°C), and pH level. Values auto-update every 5 seconds using JavaScript simulation that gradually drifts readings to mimic real sensor behavior.

### ⚠️ Alert Notification System
Provides automatic warning messages when organic matter reaches critical or warning thresholds. Alerts are color-coded — green for safe, amber for warning, red for critical. Admin and Manager can acknowledge, resolve, or escalate alerts to the next user level.

### 📈 Data Visualization Dashboard
Shows monitoring results through a Chart.js line chart with daily, weekly, and monthly period views. Includes an IOT Panel section with per-pond mini charts showing the last 10 readings history per pond.

### 🗺️ Polygon Map Visualization
Each tilapia pond is represented as a real boundary polygon on an interactive Leaflet.js dark map. Polygons change color based on current pond status and show popup cards with live organic level, temperature, pH, assigned staff, and location. The map auto-zooms to fit all ponds on load.

### 💾 Data Recording and Storage
Stores readings in a MySQL database for monitoring history and analysis. Falls back to simulated random values if no database readings exist yet, making the system fully usable for demonstration even without connected IoT hardware.

### 🖥️ Multi-Role Web Interface
Three separate dashboards for Admin, Manager, and Staff — each with role-appropriate features and access levels. All dashboards are fully responsive across desktop, tablet, and mobile devices.

### 📱 Mobile-Optimized Design
Bottom navigation bar with touch-optimized tap targets for comfortable phone use. Swipe right from the left edge to open the sidebar. Logout button is always visible in the top navigation bar on mobile — no need to open the sidebar to log out.

### 📋 Report Generation
Generates daily, weekly, and monthly summary reports showing average organic level, temperature, pH, pond status breakdown, active staff count, and incident totals. Supports PDF, Excel, and CSV export (simulation).

### 👥 User Management (Admin)
Full CRUD for Admin, Manager, and Staff accounts. Supports bulk activate, deactivate, and delete actions. Includes live search with text highlight, filter by role and status. Mobile shows card view; desktop shows a data table.

### 📤 CSV Data Export
Client-side CSV export for users, pond data, and alerts. No server request needed — data is generated directly from the current session values and downloaded instantly.

### 🔧 System Settings
Notification preferences (critical/warning/info toggles), IoT refresh rate slider, session timeout control, animated background toggle, and a system info panel showing PHP version, memory usage, server time, and database counts.

### 🏃 Activities Log
A unified activity feed combining staff logins, new sensor readings, and alert events in chronological order — visible to Admin and Manager for audit purposes.

---

## ⚙️ Technologies Used

| Technology | Purpose |
|---|---|
| 🐘 **PHP 8+** | Server-side logic, session handling, database queries, AJAX handlers |
| 🗄️ **MySQL** | Stores users, pond data, sensor readings, and notifications |
| 🌐 **HTML** | Dashboard structure and layout |
| 🎨 **CSS** | Responsive design, animations, dark cyber IoT theme with CSS variables |
| 📜 **JavaScript** | IoT simulation, live clock, chart updates, map interaction, swipe gestures |
| 🗺️ **Leaflet.js** | Interactive polygon pond map with CartoDB dark tiles |
| 📊 **Chart.js** | Line charts for organic, temperature, and pH trends |
| 🔠 **Font Awesome 6** | Icons throughout the interface |
| 🔤 **Google Fonts** | Syne (headings) + Space Mono (data values and timestamps) |

---

## 👤 User Roles

| Role | Access |
|---|---|
| **Admin** | Full access — manage users, all ponds, IOT panel, reports, settings, CSV export |
| **Manager** | View all ponds, monitor staff, respond to alerts, notify admin, generate reports |
| **Staff** | View assigned pond only, log maintenance, submit readings |

---

## 🏊 Pond Locations

All ponds are located in **Manolo Fortich, Bukidnon, Philippines** (approx. 8.3694°N, 124.8652°E).

| Pond | Full Name | Assigned Staff |
|---|---|---|
| A-1 | Tilapia Pond A-1 | Pedro Reyes |
| B-2 | Tilapia Pond B-2 | Ana Lopez |
| C-1 | Tilapia Pond C-1 | Roberto Gomez |

---

## ⚠️ Alert Threshold

| Level | Color | Trigger Condition |
|---|---|---|
| 🔴 **Critical** ..| Red | Organic > 80% OR Temperature > 32°C OR pH > 8.5 |
| 🟡 **Warning** | Amber | Organic > 60% OR Temperature > 30°C OR pH > 7.8 |
| 🟢 **Safe** | Green | All values below warning thresholds |

---

## 📱 Responsive Breakpoints

| Screen Size | Layout |
|---|---|
| ≥ 1400px | Full sidebar, 3–4 column grids |
| ≤ 1100px | Narrower sidebar, 2-column grids |
| ≤ 768px | Hidden sidebar + hamburger, bottom navigation bar, single column |
| ≤ 480px | Compact KPI cards, simplified layout |

---

## ⚙️ Setup


| Role | Email | Password |
|---|---|---|
| Admin | admin@aqua.com | admin123 |
| Manager | manager@aqua.com | manager123 |
| Staff | staff@aqua.com | staff123 |

> ⚠️ Change all default passwords immediately after first login.

⚠️ Challenges and Issues Encountered During Development

During the development of the system, the team faced several technical and resource-related challenges:

1. Login and Account Creation Bugs
We experienced difficulties debugging issues in the login and account creation modules. At times, the system would unexpectedly crash or behave inconsistently during authentication processes.
2. File Corruption
Some project files became corrupted without warning, causing delays and requiring rework to restore lost code and functionality.
3. XAMPP MySQL Not Running
There were instances where MySQL in XAMPP failed to start, preventing database access and interrupting system testing and development.
4. Limited Access to Devices
The team had a shortage of laptops and desktop computers. We often had to share a single device among multiple members, which slowed down progress and made collaboration more difficult.
5. Design Rendering Issues
While working on the user interface, there were times when changes in the design did not appear or reflect properly, making it hard to verify updates.
6. Internet Connectivity Issues
Unstable or slow internet connection affected downloading libraries, accessing documentation, and testing certain features.
7. Version Control Conflicts
Without proper version control (e.g., Git), merging code from different members sometimes caused conflicts or overwrote others’ work.
8. Inconsistent Data Simulation Behavior
The simulated data occasionally produced unrealistic values, making testing less reliable.
9. Browser Compatibility Issues
Some features worked differently across browsers, causing layout or functionality inconsistencies.
10. Session Handling Problems
Users were sometimes logged out unexpectedly or sessions expired too quickly due to misconfigured session settings.
11. Database Connection Errors
Incorrect configurations or sudden disconnections occasionally caused failures in fetching or storing data.
12. Performance Slowdowns
As more features were added, the system sometimes became slower, especially when loading charts or map data.
13. Lack of Testing Tools and Debugging Experience
Limited experience with debugging tools made it harder to quickly identify and fix errors.
14. Time Constraints
Tight academic deadlines added pressure, limiting the time available for thorough testing and refinement.


## 📁 Project Structure.

---
ORGANIC-MATTER-DETECTION-IN-TILAPIA/
│
├─ admin/
│  └─ admin_dashboard.php
│
├─ api/
│  └─ live_data.php
│
├─ auth/
│  ├─ create_hashes.php
│  ├─ login.php
│  └─ logout.php
│
├─ config/
│  ├─ config.php
│  └─ db_connect.php
│
├─ database/
│  └─ organic.sql
│
├─ manager/
│  ├─ manager_dashboard.php
│  └─ simulate_reading.php
│
├─ staff/
│  └─ staff_dashboard.php
│
├─ index.php
└─ README.md
```

---
