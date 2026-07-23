# HR System — API Endpoint Map

Base URL (local): `http://localhost:8002/hr-system/public/api`

Auth: `Bearer {{token}}` header, except where marked Public.
Roles: admin (A), manager (M), employee (E)

## Phase 1 — Foundation (BUILD FIRST)

| Method | Endpoint                  | Access      | Notes                              |
|--------|----------------------------|-------------|--------------------------------------|
| GET    | /health                    | Public      | Sanity check                        |
| POST   | /auth/register              | Public      | Admin-only in practice (seeded first admin creates others) |
| POST   | /auth/login                | Public      | Returns JWT                         |
| GET    | /auth/me                   | A,M,E       | Current user from token             |
| GET    | /employees                 | A,M         | M sees own department only          |
| GET    | /employees/{id}            | A,M,E       | E can view only self                |
| POST   | /employees                 | A           | Create employee                     |
| PUT    | /employees/{id}            | A,M         | M limited fields                    |
| DELETE | /employees/{id}            | A           | Soft delete (status = terminated)   |
| GET    | /departments                | A,M,E       |                                      |
| GET    | /departments/{id}          | A,M,E       |                                      |
| POST   | /departments                | A           |                                      |
| PUT    | /departments/{id}          | A           |                                      |
| DELETE | /departments/{id}          | A           |                                      |

## Phase 2 — Attendance, Leave, Announcements

| Method | Endpoint                     | Access | Notes                          |
|--------|-------------------------------|--------|----------------------------------|
| POST   | /attendance/check-in          | A,M,E  | Self only                       |
| POST   | /attendance/check-out         | A,M,E  | Self only                       |
| GET    | /attendance                   | A,M    | Filter by employee/date         |
| GET    | /attendance/me                | A,M,E  | Own history                     |
| POST   | /leave-requests                | A,M,E  | Self only                       |
| GET    | /leave-requests                | A,M    | M sees own department           |
| GET    | /leave-requests/me            | A,M,E  |                                  |
| PUT    | /leave-requests/{id}/review    | A,M    | Approve/reject                  |
| GET    | /announcements                | A,M,E  |                                  |
| POST   | /announcements                | A,M    |                                  |
| DELETE | /announcements/{id}            | A,M    | Own posts only for M            |

## Phase 3 — Payroll, Reviews, Audit, PDF, Email

| Method | Endpoint                       | Access | Notes                        |
|--------|----------------------------------|--------|--------------------------------|
| GET    | /payroll                          | A      |                                |
| GET    | /payroll/me                      | A,M,E  |                                |
| POST   | /payroll                          | A      | Generate for period            |
| GET    | /payroll/{id}/payslip            | A,M,E  | Returns generated PDF          |
| GET    | /reviews                          | A,M    |                                |
| POST   | /reviews                          | A,M    |                                |
| GET    | /reviews/me                      | A,M,E  |                                |
| GET    | /audit-logs                       | A      |                                |

---

**Response shape convention** (keep this consistent everywhere — it makes Postman tests trivial to write once and reuse):

```json
// success
{ "success": true, "data": { ... } }

// error
{ "success": false, "error": { "code": "VALIDATION_ERROR", "message": "..." } }
```
