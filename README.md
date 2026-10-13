# GoCashâ„¢ - Microfinance & Automated Loan Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Supabase](https://img.shields.io/badge/Supabase-PostgreSQL-3ECF8E?style=for-the-badge&logo=supabase&logoColor=white)](https://supabase.com/)
[![TailwindCSS](https://img.shields.io/badge/Tailwind-CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![REST API](https://img.shields.io/badge/REST-API%20Engine-00F2FE?style=for-the-badge&logo=fastapi&logoColor=black)](/)
[![Live Demo](https://img.shields.io/badge/Live-Interactive%20Portal-10B981?style=for-the-badge&logo=google-chrome&logoColor=white)](https://09072802649.github.io/tyron-portfolio/projects/loan-management-system/index.html)

> **GoCashâ„¢** is an enterprise-grade microfinance and lending management platform designed to automate loan underwriting, daily/weekly installment amortization calculation, proof-of-payment receipt verification, and field collector cash remittance operations.

---

## ðŸŒŸ Key Features & Role Architecture

GoCash provides role-tailored dashboards and services for every participant in the microfinance lifecycle:

### ðŸ‘‘ System Administrator Control Hub
- **Portfolio Health & Metrics**: Real-time gross disbursed capital, principal recoveries, collected interest margin, and delinquency/default rate telemetry.
- **Underwriting & Approval Pipeline**: Single-click approval, interest rate tuning (flat vs. diminishing), and tenure configuration.
- **Receipt Verification Queue**: Dual-pane manual auditor workspace with high-resolution image zoom for borrower payment receipts.
- **Audit Logging**: Full audit trail for transaction reconciliation and system events.

### ðŸ›µ Field Collector Mobile & Desktop Portal
- **Daily Route Collection Sheets**: Sorted borrower rosters highlighting overdue accounts, expected daily collections, and contact information.
- **Instant Cash Payment Posting**: In-field payment logging with automated installment receipt numbering.
- **Collector Remittance Reconciliation**: Real-time counter of total cash on hand requiring end-of-day supervisor remittance.

### ðŸ‘¤ Borrower Self-Service Account Portal
- **Active Loan Dashboard**: Remaining balance, next due date, completed installments, and principal-to-interest breakdown.
- **Automated Installment Calendar**: Full amortization schedule with dynamic status tracking (Paid, Due, Overdue).
- **Proof-of-Payment Submission**: Digital slip uploader allowing borrowers to submit GCash/bank transfer screenshots directly to the verification queue.

### âš¡ Interactive Live REST API Sandbox
- Built-in sandbox allowing developers to execute test requests against all GoCash backend endpoints with real JSON payloads, response time benchmarks, and HTTP status codes.

---

## ðŸ—ï¸ System Architecture

`
                  â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                  â”‚       GoCashâ„¢ Web & Mobile Portals       â”‚
                  â”‚   Admin  â€¢  Collector  â€¢  Borrower App   â”‚
                  â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                                        â”‚
                    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                    â”‚                                       â”‚
                    â–¼                                       â–¼
    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
    â”‚     PHP 8.x REST API Engine    â”‚       â”‚     Supabase Cloud Service    â”‚
    â”‚  Endpoints & Amortization Coreâ”‚       â”‚ Real-Time Storage & PG Engine â”‚
    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜       â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                    â”‚                                       â”‚
                    â–¼                                       â–¼
    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
    â”‚       MySQL Relational DB     â”‚       â”‚    PostgreSQL / Supabase      â”‚
    â”‚     loan_monitoring_db.sql    â”‚       â”‚      supabase_schema.sql      â”‚
    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜       â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
`

---

## ðŸ“¡ REST API Endpoints Reference

| Method | Endpoint | Description | Role / Auth |
| :--- | :--- | :--- | :--- |
| POST | /login.php | Authenticates users (Admin, Collector, Borrower) and returns session | Public |
| POST | /register.php | Registers new borrower profile | Public |
| GET | /get_loans.php | Fetches active loans filtered by status (pending, ctive, completed) | Admin / Collector |
| POST | /create_loan.php | Generates loan agreement, calculates interest, and builds amortization schedule | Admin / Collector |
| GET | /get_installments.php | Returns installment breakdown for a specific loan_id | All Roles |
| POST | /record_payment.php | Logs a verified payment against specific installments and settles balances | Admin / Collector |
| POST | /submit_payment.php | Submits payment reference and image proof to the verification queue | Borrower |
| GET | /collector_daily_transactions_helper.php | Retrieves collector's daily collection ledger and route summaries | Collector / Admin |
| GET | /get_borrower_summary.php | Retrieves consolidated borrower financial overview | Admin / Collector |

---

## ðŸ—„ï¸ Database Schemas & DDL

This repository includes full schema definitions for both MySQL and Supabase PostgreSQL:

1. **MySQL Enterprise Schema**: [loan_monitoring_db.sql](./loan_monitoring_db.sql) & [schema.sql](./schema.sql)
   - Tables: users, orrowers, loans, installments, payments, emittances, 
otifications, udit_logs.
2. **Supabase PostgreSQL Schema**: [supabase_schema.sql](./supabase_schema.sql)
   - Includes Row Level Security (RLS) policies, storage bucket definitions for payment proofs, and initial seed dataset.

---

## ðŸš€ Getting Started

### Option 1: Live Interactive Demo (Zero Setup)
Open the deployed live portal directly:
ðŸ‘‰ **[GoCashâ„¢ Live Interactive Platform](https://09072802649.github.io/tyron-portfolio/projects/loan-management-system/index.html)**

### Option 2: Local PHP & MySQL Setup (XAMPP / Laragon / LAMP)
1. Clone this repository:
   `ash
   git clone https://github.com/09072802649/loan_api.git
   `
2. Move the project folder into your web server root (e.g., htdocs/loan_api in XAMPP or www/loan_api in Laragon).
3. Import the database schema:
   - Create a MySQL database named u123456_loan_monitoring.
   - Import [loan_monitoring_db.sql](./loan_monitoring_db.sql) into MySQL via phpMyAdmin or CLI:
     `ash
     mysql -u root -p u123456_loan_monitoring < loan_monitoring_db.sql
     `
4. Configure database credentials in [db_connect.php](./db_connect.php):
   `php
   <?php
    = new mysqli("localhost", "your_username", "your_password", "u123456_loan_monitoring");
   `
5. Open your browser and navigate to:
   `
   http://localhost/loan_api/index.html
   `

---

## ðŸ‘¤ Author

**Tyron John L. Dela Cruz**
- Portfolio: [09072802649.github.io/tyron-portfolio](https://09072802649.github.io/tyron-portfolio/)
- GitHub: [@09072802649](https://github.com/09072802649)
- Email: [tyronjohndelacruz@gmail.com](mailto:tyronjohndelacruz@gmail.com)

---

## ðŸ“„ License
This project is licensed under the MIT License - feel free to use and adapt for academic and enterprise lending systems.
