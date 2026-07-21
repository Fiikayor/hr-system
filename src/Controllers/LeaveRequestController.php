<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Helpers\AuditLogger;
use App\Helpers\Mailer;

class LeaveRequestController
{
    /** Employee submits a leave request — always starts as 'pending' */
    public function store(): void
    {
        $claims = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $required = ['leave_type_id', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error('VALIDATION_ERROR', "Field '{$field}' is required", 422);
            }
        }

        if (strtotime($body['end_date']) < strtotime($body['start_date'])) {
            Response::error('VALIDATION_ERROR', 'end_date cannot be before start_date', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status)
             VALUES (:employee_id, :leave_type_id, :start_date, :end_date, :reason, "pending")'
        );
        $stmt->execute([
            'employee_id' => $claims['sub'],
            'leave_type_id' => $body['leave_type_id'],
            'start_date' => $body['start_date'],
            'end_date' => $body['end_date'],
            'reason' => $body['reason'] ?? null,
        ]);

        Response::success(['id' => (int) $db->lastInsertId(), 'status' => 'pending'], 201);
    }

    /** Admin/manager view of requests needing attention */
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $db = Database::getConnection();

        $sql = 'SELECT lr.id, lr.employee_id, CONCAT(e.first_name, " ", e.last_name) AS employee_name,
                       lt.name AS leave_type, lr.start_date, lr.end_date, lr.reason, lr.status
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                JOIN leave_types lt ON lt.id = lr.leave_type_id';
        $params = [];

        if ($claims['role'] === 'manager') {
            $sql .= ' WHERE e.department_id = (SELECT department_id FROM employees WHERE id = :manager_id)';
            $params['manager_id'] = $claims['sub'];
        }

        $sql .= ' ORDER BY lr.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll());
    }

    /** Employee's own leave request history */
    public function me(): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT lr.id, lt.name AS leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.reviewed_at
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :employee_id
             ORDER BY lr.created_at DESC'
        );
        $stmt->execute(['employee_id' => $claims['sub']]);

        Response::success($stmt->fetchAll());
    }

    /** Manager/admin approves or rejects */
    public function review(int $id): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $body['status'] ?? null;

        if (!in_array($status, ['approved', 'rejected'], true)) {
            Response::error('VALIDATION_ERROR', "status must be 'approved' or 'rejected'", 422);
        }

        $db = Database::getConnection();

        // Confirm the request exists and is still pending
        $stmt = $db->prepare('SELECT id, status, employee_id FROM leave_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        if (!$request) {
            Response::error('NOT_FOUND', 'Leave request not found', 404);
        }

        if ($request['status'] !== 'pending') {
            Response::error('ALREADY_REVIEWED', 'This request has already been reviewed', 409);
        }

        $stmt = $db->prepare(
            'UPDATE leave_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'reviewed_by' => $claims['sub'],
            'id' => $id,
        ]);

        AuditLogger::log($claims['sub'], 'leave_request.review', 'leave_request', $id, ['status' => $status]);

        $stmt = $db->prepare('SELECT first_name, last_name, email FROM employees WHERE id = :id');
        $stmt->execute(['id' => $request['employee_id']]);
        $employee = $stmt->fetch();

        if ($employee) {
            $statusLabel = $status === 'approved' ? 'Approved' : 'Rejected';
            Mailer::send(
                $employee['email'],
                $employee['first_name'] . ' ' . $employee['last_name'],
                "Your leave request has been {$statusLabel}",
                "<p>Hi {$employee['first_name']},</p><p>Your leave request has been <strong>{$statusLabel}</strong>.</p>"
            );
        }

        Response::success(['id' => $id, 'status' => $status]);
    }
}
