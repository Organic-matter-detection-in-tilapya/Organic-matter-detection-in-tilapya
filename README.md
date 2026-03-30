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
| 🔴 **Critical** | Red | Organic > 80% OR Temperature > 32°C OR pH > 8.5 |
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



## 📁 Project Structure

```
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
