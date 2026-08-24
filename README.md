# 🛡 Security Incident Ticketing System

A web-based Security Incident Ticketing System developed using PHP, MySQL, HTML, CSS and JavaScript.

The system helps security teams to report, classify, assign, investigate, track and resolve security incidents through a centralized ticketing platform.

---

## 📌 Project Overview

Security incidents such as unauthorized access, suspicious login attempts, malware detection, phishing, data leakage and other security events need to be properly recorded and tracked.

This project provides a centralized platform where:

- Administrators can manage users and incidents.
- Security analysts can investigate assigned incidents.
- Incidents can be classified according to category and severity.
- Incidents can be assigned to security analysts.
- Incident status can be updated throughout the investigation.
- Complete activity history can be maintained.
- Reports can be generated using multiple filters.
- Incident reports can be exported as CSV.

---

# 🎯 Key Objectives

The main objectives of this project are:

1. Centralize security incident management.
2. Create unique incident ticket numbers.
3. Classify incidents by category.
4. Assign incidents to security analysts.
5. Track incident status.
6. Maintain incident activity history.
7. Provide role-based access control.
8. Generate incident reports.
9. Export reports in CSV format.
10. Improve incident response workflow.

---

# 🚀 Features

## 🔐 Authentication

- Secure login system
- Session-based authentication
- Logout functionality
- Password hashing
- Role-based authorization

---

## 👨‍💼 Admin Module

Administrators can:

- View dashboard
- View total users
- View incident statistics
- Manage users
- View all incidents
- Create incidents
- Edit incidents
- Assign incidents
- Update incident status
- Generate reports
- Export reports

---

## 🕵️ Analyst Module

Security analysts can:

- Access analyst dashboard
- View assigned incidents
- View incident details
- Review activity history
- Update incident status
- Add investigation comments
- Track incident progress

Analysts cannot access administrator-only functions.

---

# 🎫 Incident Management

Each incident receives a unique ticket number.

Example:

```text
INC-2026-0001
INC-2026-0002
INC-2026-0003

Incident information includes:

Ticket Number
Title
Description
Category
Severity
Status
Reporter
Assigned Analyst
Created Date
Updated Date
🏷 Incident Categories

Example categories include:

Unauthorized Access
Malware
Phishing
Data Breach
Suspicious Activity
Denial of Service
Account Compromise
Policy Violation
🚨 Severity Levels

The system supports four severity levels:

Severity	Description
Low	Minor security event
Medium	Security event requiring investigation
High	Serious security incident
Critical	Severe incident requiring immediate response
🔄 Incident Status

Incidents move through different stages:
Open
  ↓
In Progress
  ↓
Resolved
  ↓
Closed

👥 User Roles

The system currently supports:
| Role    | Access                   |
| ------- | ------------------------ |
| Admin   | Full system access       |
| Analyst | Assigned incident access |

Role-based access control prevents unauthorized users from accessing restricted modules.

📊 Reports

The reporting module supports filtering by:

Date From
Date To
Status
Severity
Category
Analyst

Report statistics include:

Total Incidents
Open Incidents
In Progress
Resolved
Closed
Critical / High Incidents
📥 CSV Export

Filtered incident reports can be exported to CSV.

Example filename:
security-incidents-report-2026-08-23-23-50-00.csv

CSV contains:

Ticket Number
Incident Title
Description
Category
Reporter
Assigned Analyst
Severity
Status
Created At
Updated At
🧾 Audit Trail

The system maintains an activity history for incidents.

Example:

Incident Created
       ↓
Analyst Assigned
       ↓
Status Changed
       ↓
Investigation Started
       ↓
Incident Resolved
       ↓
Incident Closed

Each activity can contain:
User
User Role
Action
Previous Value
New Value
Comment
Timestamp

📁 Project Structure

security-incident-ticketing/
│
├── config/
│   ├── database.php
│   └── security.php
│
├── auth/
│   ├── login.php
│   ├── authenticate.php
│   └── logout.php
│
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   └── incidents.php
│
├── incidents/
│   ├── create.php
│   ├── view.php
│   ├── edit.php
│   ├── assign.php
│   └── update-status.php
│
├── analyst/
│   ├── dashboard.php
│   └── assigned-incidents.php
│
├── reports/
│   ├── incidents-report.php
│   └── export.php
│
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
│
├── index.php
└── README.md

🛠 Technologies Used
Frontend
HTML5
CSS3
JavaScript
Backend
PHP
PDO
Database
MySQL
Server
Apache
XAMPP
🔒 Security Features

The application implements several security controls:

Password Hashing

Passwords are stored using PHP password hashing functions.

password_hash()

Passwords are verified using:

password_verify()

Prepared Statements

Database queries use PDO prepared statements to reduce SQL injection risks.

Example:

$stmt = $pdo->prepare(
    "SELECT * FROM users WHERE email = ?"
);

$stmt->execute([$email]);

Output Escaping

User-controlled output is escaped before being displayed.

Example:

htmlspecialchars()

Role-Based Access Control

Restricted pages verify the authenticated user's role.

Example:

requireRole('admin');

and:

requireRole('analyst');

Session Authentication

Protected pages require a valid authenticated session.

Example:

requireLogin();


💻 Installation
Step 1 — Install XAMPP

Install XAMPP with:

Apache
MySQL
PHP
phpMyAdmin
Step 2 — Project Location

Copy the project into:

C:\xampp\htdocs\

The final location should be:

C:\xampp\htdocs\security-incident-ticketing\
Step 3 — Start XAMPP

Start:

Apache  → Running
MySQL   → Running
Step 4 — Create Database

Open phpMyAdmin:

http://localhost/phpmyadmin/

Create the project database.

Example:

security_incident_ticketing

Import the project database SQL file if available.

Step 5 — Configure Database

Open:

config/database.php

Configure:

$host = 'localhost';
$db   = 'security_incident_ticketing';
$user = 'root';
$pass = '';

Change the username/password according to your MySQL configuration.

Step 6 — Test Database

Open:

http://localhost/security-incident-ticketing/

The application should successfully connect to MySQL.

🔑 Login
Administrator
Email:
admin@security.local

Use the administrator password configured in your database.

Security Analyst
Email:
analyst@security.local

Use the analyst password configured in your database.

For production deployment, never publish real passwords in this README.

🔄 Application Workflow

The basic workflow is:

User Login
    ↓
Authentication
    ↓
Role Verification
    ↓
Dashboard
    ↓
Create Incident
    ↓
Classify Incident
    ↓
Set Severity
    ↓
Assign Analyst
    ↓
Investigation
    ↓
Update Status
    ↓
Resolve Incident
    ↓
Close Incident
    ↓
Generate Report
    ↓
Export CSV
🧪 Testing

The following functions should be tested:

Authentication
Valid login
Invalid login
Logout
Unauthorized access
Admin
Dashboard
User management
Incident management
Assignment
Status updates
Reports
CSV export
Analyst
Analyst dashboard
Assigned incidents
Incident details
Status update
Access restriction
Incident Management
Create incident
Edit incident
View incident
Assign analyst
Update status
Activity history
Reports
Date filter
Status filter
Severity filter
Category filter
Analyst filter
CSV export
🔐 Role-Based Access Example
                    SYSTEM
                       │
              Authentication
                       │
                Role Verification
                       │
             ┌─────────┴─────────┐
             │                   │
           ADMIN              ANALYST
             │                   │
       ┌─────┼─────┐             │
       │     │     │             │
     Users Incidents Reports   Assigned
       │     │     │           Incidents
       │     │     │             │
       └─────┴─────┘             │
             │                   │
             └─────────┬─────────┘
                       │
                 Incident System
📌 Current Project Status
Module	Status
Database Connection	✅ Complete
Authentication	✅ Complete
Admin Dashboard	✅ Complete
User Management	✅ Complete
Incident Creation	✅ Complete
Incident View	✅ Complete
Incident Editing	✅ Complete
Incident Assignment	✅ Complete
Status Management	✅ Complete
Analyst Dashboard	✅ Complete
Assigned Incidents	✅ Complete
Incident Reports	✅ Complete
CSV Export	✅ Complete
Activity History	✅ Complete
RBAC	✅ Complete
🔮 Future Enhancements

Possible future improvements:

Email notifications
SMS notifications
File attachments
Evidence management
Incident comments
Advanced analytics
Charts and dashboards
PDF report generation
Two-factor authentication
Password reset
Security event integration
SIEM integration
MITRE ATT&CK mapping
IOC management
SLA tracking
Incident escalation
API integration
Automated alerting
👨‍💻 Project Type
Capstone Project
Project Name
Security Incident Ticketing System
Primary Purpose
Report → Classify → Assign → Investigate → Track → Resolve → Report
📜 License

This project is developed for educational and academic purposes.

👨‍💻 Author:Abhishek Anand

Security Incident Ticketing System

Developed as a cybersecurity capstone project.


