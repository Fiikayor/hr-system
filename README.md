# HR Management System

A full-stack Enterprise HR Management platform built as a learning project to understand REST API design, JWT authentication, role-based access control, and building a frontend against a self-built API.

Built with a deliberately minimal stack — plain PHP (no framework) on the backend, vanilla JavaScript on the frontend — to focus on fundamentals rather than framework abstractions.

## Features

- **Authentication** — JWT-based login/register, role-aware
- **Role-based access control** — Admin, Manager, Employee, each with different visibility and permissions
- **Employee management** — full CRUD, soft-delete (deactivation, not hard delete)
- **Department management** — full CRUD with manager assignment
- **Attendance** — check-in/check-out, automatic late detection, personal and org-wide views
- **Leave requests** — submission → approval workflow, with a guard against double-review
- **Performance reviews** — manager/admin can review employees in their scope
- **Announcements** — company-wide or department-scoped
- **Payroll** — generation with auto-filled or overridden base salary, bonuses/deductions math
- **PDF payslips** — generated on demand via DomPDF
- **Email notifications** — leave approval/rejection emails via PHPMailer (SMTP, tested with Mailtrap)
- **Audit logs** — tracks key actions (employee updates, leave reviews, payroll generation) with actor, timestamp, and details

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ (no framework), PDO/MySQL |
| Auth | JWT (`firebase/php-jwt`) |
| PDF generation | DomPDF |
| Email | PHPMailer over SMTP |
| Database | MySQL |
| Frontend | Vanilla HTML/CSS/JS, Tailwind CSS (CDN) |
| Local environment | XAMPP |

No frontend framework — pages talk to the REST API via `fetch()`, the same way a React/Vue app would, without the build tooling.

## Project structure

\```
hr-system/
├── config/
│   └── database.php          # PDO connection
├── database/
│   ├── schema.sql             # Full schema + seed data
│   └── endpoint-map.md        # Full REST endpoint reference
├── public/
│   ├── index.php              # API front controller / router
│   ├── login.html
│   ├── employee-dashboard.html
│   ├── manager-dashboard.html
│   ├── admin-dashboard.html
│   ├── js/
│   │   ├── api.js             # Shared fetch wrapper + auth/token handling
│   │   ├── tailwind-config.js # Shared design tokens
│   │   ├── login.js
│   │   ├── employee-dashboard.js
│   │   ├── manager-dashboard.js
│   │   └── admin-dashboard.js
│   └── .htaccess
├── src/
│   ├── Controllers/            # One controller per resource
│   ├── Middleware/
│   │   └── AuthMiddleware.php  # JWT verification + role authorization
│   └── Helpers/
│       ├── Response.php        # Standard {success, data} / {success, error} shape
│       ├── JwtHelper.php
│       ├── AuditLogger.php
│       └── Mailer.php
├── composer.json
├── .env.example
└── .gitignore
\```

## Setup

### 1. Place the project

Clone or copy this repo into your XAMPP `htdocs` folder, e.g. `htdocs/hr-system`.

### 2. Install dependencies

From inside the project folder:

\```
composer install
\```

Pulls in `firebase/php-jwt`, `vlucas/phpdotenv`, `dompdf/dompdf`, and `phpmailer/phpmailer`.

### 3. Configure environment

\```
cp .env.example .env
\```

Fill in `.env`:

\```
DB_HOST=127.0.0.1
DB_NAME=hr_system
DB_USER=root
DB_PASS=

JWT_SECRET=replace_with_a_long_random_string
JWT_EXPIRY_SECONDS=86400

MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM=noreply@hr-system.local
MAIL_FROM_NAME="HR System"
\```

**Important:** any `.env` value containing a space must be wrapped in double quotes (e.g. `MAIL_FROM_NAME="HR System"`), or the app will fail to start with a dotenv parse error.

For local email testing, [Mailtrap](https://mailtrap.io)'s free Email Testing sandbox works well — it gives you SMTP credentials and a fake inbox so no real emails get sent during development.

### 4. Create the database

Start Apache + MySQL in XAMPP, then import `database/schema.sql` via phpMyAdmin (Import tab) or:

\```
mysql -u root -p < database/schema.sql
\```

This creates the database, all tables, and seeds one admin account.

### 5. Verify it's running

Visit `http://localhost/hr-system/public/api/health` — you should see:

\```json
{"success":true,"data":{"status":"ok","time":"..."}}
\```

### 6. Log in

Open `http://localhost/hr-system/public/login.html` in your browser.

**Default admin credentials:**
\```
Email:    admin@hr-system.local
Password: Admin123!
\```

Change this password after first login (via the Employees tab in the admin dashboard) — it's a shared default from the seed data.

## API overview

Base URL: `http://localhost/hr-system/public/api`

All endpoints except `/health`, `/auth/login`, and `/auth/register` require an `Authorization: Bearer <token>` header, obtained from `/auth/login`.

Every response follows one of two shapes:

\```json
// success
{ "success": true, "data": { ... } }

// error
{ "success": false, "error": { "code": "...", "message": "..." } }
\```

| Resource | Endpoints |
|---|---|
| Auth | `POST /auth/register`, `POST /auth/login`, `GET /auth/me` |
| Employees | `GET /employees`, `GET /employees/{id}`, `PUT /employees/{id}`, `DELETE /employees/{id}` |
| Departments | `GET /departments`, `GET /departments/{id}`, `POST /departments`, `PUT /departments/{id}`, `DELETE /departments/{id}` |
| Attendance | `POST /attendance/check-in`, `POST /attendance/check-out`, `GET /attendance/me`, `GET /attendance` (admin/manager) |
| Leave requests | `POST /leave-requests`, `GET /leave-requests/me`, `GET /leave-requests` (admin/manager), `PUT /leave-requests/{id}/review` |
| Announcements | `GET /announcements`, `POST /announcements`, `DELETE /announcements/{id}` |
| Performance reviews | `POST /reviews`, `GET /reviews/me`, `GET /reviews` (admin/manager) |
| Payroll | `POST /payroll`, `GET /payroll/me`, `GET /payroll` (admin), `GET /payroll/{id}/payslip` (PDF) |
| Audit logs | `GET /audit-logs` (admin only) |

Full endpoint reference with role requirements: [`database/endpoint-map.md`](database/endpoint-map.md).

A complete Postman collection covering every endpoint (with environment variables and an auto-token-chaining login script) was used throughout development and testing.

## Roles

| Role | Can do |
|---|---|
| **Admin** | Everything — full employee/department management, org-wide attendance and leave visibility, payroll generation, audit log access |
| **Manager** | Manages their own department — team attendance/leave visibility, approve/reject leave, write reviews, post department announcements — plus their own personal attendance/leave like any employee |
| **Employee** | Own attendance, own leave requests, own payslips, own reviews, view announcements |

## Known limitations

Being upfront about the rough edges rather than hiding them:

- **Roles are hardcoded on the frontend** (`admin: 1, manager: 2, employee: 3`) since there's no `GET /roles` endpoint. Fine while roles are fixed by seed data; would need a proper endpoint if roles ever become dynamic.
- **Leave types are hardcoded** in the frontend dropdown (Annual/Sick/Unpaid) for the same reason — no `GET /leave-types` endpoint exists yet.
- **`/auth/register` is currently open** (not restricted to admins) to make initial test-account creation easier during development. In a real deployment this should be gated behind `AuthMiddleware::authorize($claims, ['admin'])`.
- **No refresh tokens** — JWTs expire after `JWT_EXPIRY_SECONDS` (default 24h) and the user must log in again; there's no silent refresh.
- **Frontend pages are not server-gated** — an unauthenticated user's browser can technically download the HTML/JS of a dashboard page, it just won't successfully load any data since every API call requires a valid token. Fine for an internal tool behind XAMPP; would need server-side session/auth checks for a public-facing deployment.

## License

Personal/educational project.