# Tumaini Connect

Enterprise Church Outreach, Reporting, and Follow-up Management System for Tanzania.

## Stack
- PHP (procedural)
- MySQL (mysqli)
- Bootstrap 5
- Chart.js
- DataTables

## Setup (XAMPP)
1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin and import: `database/tumaini_connect_db.sql`.
3. Ensure project path is:
   - `C:/xampp/htdocs/Tumaini-Connect/tumaini-connect`
4. Open in browser:
   - `http://localhost/Tumaini-Connect/tumaini-connect/`

## Default Admin Login
- Email: `admin@tumaini.or.tz`
- Password: `Admin@123`

## Core Modules
- Authentication + RBAC
- Union management
- Conference/Field management
- Station management
- Public interest form
- Follow-up assignment and updates
- Role-based dashboard and analytics
- CSV report export

## Roles
- tanzania_admin: full visibility
- union_admin: union scope
- conference_admin: conference scope
- coordinator: assigned stations scope
- followup: assigned follow-up scope

## Notes
- CSRF protection is enabled on POST forms.
- Passwords are hashed with PHP `password_hash()`.
- Public form is available at:
  - `/modules/interests/public_form.php`
