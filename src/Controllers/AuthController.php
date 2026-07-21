<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Helpers\JwtHelper;
use App\Middleware\AuthMiddleware;

class AuthController
{
    public function login(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            Response::error('VALIDATION_ERROR', 'Email and password are required', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT e.id, e.first_name, e.last_name, e.email, e.password_hash, r.name AS role
             FROM employees e
             JOIN roles r ON r.id = e.role_id
             WHERE e.email = :email AND e.status = "active"
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('INVALID_CREDENTIALS', 'Email or password is incorrect', 401);
        }

        $token = JwtHelper::generate([
            'sub' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
        ]);

        Response::success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

    /**
     * In production this should be admin-only (protect with AuthMiddleware + role check).
     * Left open here so you can create your first few test users while building.
     */
    public function register(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $required = ['first_name', 'last_name', 'email', 'password', 'role_id'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error('VALIDATION_ERROR', "Field '{$field}' is required", 422);
            }
        }

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('VALIDATION_ERROR', 'Invalid email format', 422);
        }

        if (strlen($body['password']) < 8) {
            Response::error('VALIDATION_ERROR', 'Password must be at least 8 characters', 422);
        }

        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id FROM employees WHERE email = :email');
        $stmt->execute(['email' => $body['email']]);
        if ($stmt->fetch()) {
            Response::error('EMAIL_EXISTS', 'An employee with this email already exists', 409);
        }

        $employeeCode = 'EMP-' . str_pad((string) (self::nextEmployeeId($db)), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare(
            'INSERT INTO employees (employee_code, first_name, last_name, email, password_hash, role_id, department_id, job_title, hire_date, status)
             VALUES (:code, :first_name, :last_name, :email, :password_hash, :role_id, :department_id, :job_title, :hire_date, "active")'
        );
        $stmt->execute([
            'code' => $employeeCode,
            'first_name' => $body['first_name'],
            'last_name' => $body['last_name'],
            'email' => $body['email'],
            'password_hash' => password_hash($body['password'], PASSWORD_BCRYPT),
            'role_id' => $body['role_id'],
            'department_id' => $body['department_id'] ?? null,
            'job_title' => $body['job_title'] ?? null,
            'hire_date' => $body['hire_date'] ?? date('Y-m-d'),
        ]);

        Response::success(['id' => (int) $db->lastInsertId(), 'employee_code' => $employeeCode], 201);
    }

    public function me(): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT e.id, e.employee_code, e.first_name, e.last_name, e.email, e.job_title,
                    e.hire_date, e.status, r.name AS role, d.name AS department
             FROM employees e
             JOIN roles r ON r.id = e.role_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.id = :id'
        );
        $stmt->execute(['id' => $claims['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('NOT_FOUND', 'User not found', 404);
        }

        Response::success($user);
    }

    private static function nextEmployeeId($db): int
    {
        $result = $db->query('SELECT MAX(id) AS max_id FROM employees')->fetch();
        return ((int) ($result['max_id'] ?? 0)) + 1;
    }
}
