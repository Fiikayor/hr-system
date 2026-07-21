<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Helpers\AuditLogger;

class EmployeeController
{
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $db = Database::getConnection();

        $sql = 'SELECT e.id, e.employee_code, e.first_name, e.last_name, e.email, e.job_title,
                       e.status, r.name AS role, d.name AS department
                FROM employees e
                JOIN roles r ON r.id = e.role_id
                LEFT JOIN departments d ON d.id = e.department_id';
        $params = [];

        // Managers only see their own department
        if ($claims['role'] === 'manager') {
            $sql .= ' WHERE e.department_id = (SELECT department_id FROM employees WHERE id = :manager_id)';
            $params['manager_id'] = $claims['sub'];
        }

        $sql .= ' ORDER BY e.id DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll());
    }

    public function show(int $id): void
    {
        $claims = AuthMiddleware::authenticate();

        // Employees can only view themselves; admin/manager can view others
        if ($claims['role'] === 'employee' && (int) $claims['sub'] !== $id) {
            Response::error('FORBIDDEN', 'You can only view your own profile', 403);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT e.id, e.employee_code, e.first_name, e.last_name, e.email, e.job_title,
                    e.hire_date, e.salary, e.status, r.name AS role, d.name AS department
             FROM employees e
             JOIN roles r ON r.id = e.role_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $employee = $stmt->fetch();

        if (!$employee) {
            Response::error('NOT_FOUND', 'Employee not found', 404);
        }

        // Salary is sensitive — strip it out unless admin or the employee themself
        if (!in_array($claims['role'], ['admin'], true) && (int) $claims['sub'] !== $id) {
            unset($employee['salary']);
        }

        Response::success($employee);
    }

    public function update(int $id): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // Managers can only update a limited set of fields
        $allowedFields = $claims['role'] === 'admin'
            ? ['first_name', 'last_name', 'phone', 'job_title', 'department_id', 'salary', 'status', 'role_id']
            : ['phone', 'job_title'];

        $updates = array_intersect_key($body, array_flip($allowedFields));

        if (empty($updates)) {
            Response::error('VALIDATION_ERROR', 'No valid fields provided to update', 422);
        }

        $setClause = implode(', ', array_map(fn($field) => "{$field} = :{$field}", array_keys($updates)));
        $updates['id'] = $id;

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE employees SET {$setClause} WHERE id = :id");
        $stmt->execute($updates);
        AuditLogger::log($claims['sub'], 'employee.update', 'employee', $id, $updates);

        Response::success(['updated' => true]);
    }

    public function destroy(int $id): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $db = Database::getConnection();
        // Soft delete — never hard-delete employee records (payroll/audit history depends on them)
        $stmt = $db->prepare('UPDATE employees SET status = "terminated" WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success(['terminated' => true]);
    }
}
