<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

class AttendanceController
{
    public function checkIn(): void
    {
        $claims = AuthMiddleware::authenticate();
        $employeeId = $claims['sub'];

        $db = Database::getConnection();

        // Prevent double check-in: block if there's already an open record (check_out IS NULL) for today
        $stmt = $db->prepare(
            'SELECT id FROM attendance
             WHERE employee_id = :employee_id AND check_out IS NULL
             AND DATE(check_in) = CURDATE()'
        );
        $stmt->execute(['employee_id' => $employeeId]);

        if ($stmt->fetch()) {
            Response::error('ALREADY_CHECKED_IN', 'You already have an open check-in for today', 409);
        }

        $now = date('Y-m-d H:i:s');
        $status = (date('H:i:s') > '09:15:00') ? 'late' : 'present';

        $stmt = $db->prepare(
            'INSERT INTO attendance (employee_id, check_in, status) VALUES (:employee_id, :check_in, :status)'
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'check_in' => $now,
            'status' => $status,
        ]);

        Response::success([
            'id' => (int) $db->lastInsertId(),
            'check_in' => $now,
            'status' => $status,
        ], 201);
    }

    public function checkOut(): void
    {
        $claims = AuthMiddleware::authenticate();
        $employeeId = $claims['sub'];

        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT id FROM attendance
             WHERE employee_id = :employee_id AND check_out IS NULL
             ORDER BY check_in DESC LIMIT 1'
        );
        $stmt->execute(['employee_id' => $employeeId]);
        $record = $stmt->fetch();

        if (!$record) {
            Response::error('NOT_CHECKED_IN', 'No open check-in found to close', 400);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE attendance SET check_out = :check_out WHERE id = :id');
        $stmt->execute(['check_out' => $now, 'id' => $record['id']]);

        Response::success(['id' => $record['id'], 'check_out' => $now]);
    }

    /** Admin/manager view — filterable by employee and date via query params */
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $db = Database::getConnection();

        $sql = 'SELECT a.id, a.employee_id, CONCAT(e.first_name, " ", e.last_name) AS employee_name,
                       a.check_in, a.check_out, a.status
                FROM attendance a
                JOIN employees e ON e.id = a.employee_id';
        $params = [];
        $conditions = [];

        if ($claims['role'] === 'manager') {
            $conditions[] = 'e.department_id = (SELECT department_id FROM employees WHERE id = :manager_id)';
            $params['manager_id'] = $claims['sub'];
        }

        if (!empty($_GET['employee_id'])) {
            $conditions[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $_GET['employee_id'];
        }

        if (!empty($_GET['date'])) {
            $conditions[] = 'DATE(a.check_in) = :date';
            $params['date'] = $_GET['date'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY a.check_in DESC LIMIT 200';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll());
    }

    /** Own history — any authenticated employee */
    public function me(): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, check_in, check_out, status
             FROM attendance
             WHERE employee_id = :employee_id
             ORDER BY check_in DESC LIMIT 100'
        );
        $stmt->execute(['employee_id' => $claims['sub']]);

        Response::success($stmt->fetchAll());
    }
}
