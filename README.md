# HR Management API — Phase 1 Setup

## 1. Place the project
Copy the whole `hr-api` folder into your XAMPP `htdocs` directory, e.g.:
`C:\xampp\htdocs\hr-api` or `/Applications/XAMPP/htdocs/hr-api`

## 2. Install dependencies
You need Composer installed (composer.org). From inside the `hr-api` folder:
```
composer install
```
This pulls in `firebase/php-jwt` (JWT handling) and `vlucas/phpdotenv` (.env loading) — the only two third-party pieces in this whole project. Everything else is plain PHP.

## 3. Configure environment
```
cp .env.example .env
```
Edit `.env`:
- `DB_PASS` — leave blank if using default XAMPP MySQL (root, no password)
- `JWT_SECRET` — replace with any long random string (this signs your tokens — treat it like a password)

## 4. Create the database
Start Apache + MySQL in the XAMPP control panel, then either:
- Open phpMyAdmin → Import → select `database/schema.sql`, or
- Terminal: `mysql -u root -p < database/schema.sql`

## 5. Test it's alive
Visit: `http://localhost/hr-api/public/api/health`
You should get:
```json
{"success":true,"data":{"status":"ok","time":"..."}}
```

If you get a 404, double check the `$basePath` variable in `public/index.php` matches where you placed the folder in `htdocs`.

## 6. Log in as the seeded admin
```
POST http://localhost/hr-api/public/api/auth/login
Body: { "email": "admin@hr-system.local", "password": "Admin123!" }
```
This returns a JWT. Change this password via a real `/employees/{id}` update once you've built that flow out — for now it's fine for testing.

## What's built (Phase 1)
- `POST /auth/register`, `POST /auth/login`, `GET /auth/me`
- Full `employees` CRUD with role-based visibility (admin sees all, manager sees own department, employee sees only self)
- Full `departments` CRUD (admin-only writes)
- JWT auth middleware + role authorization on every protected route

## Next up (once this is tested in Postman)
- Phase 2: attendance, leave requests, announcements
- Phase 3: payroll, reviews, audit logs, PDF payslips, email notifications
