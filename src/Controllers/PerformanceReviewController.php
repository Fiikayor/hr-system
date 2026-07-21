<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

class PerformanceReviewController
{
    public function store(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $required = ['employee_id', 'review_period', 'rating'];
        foreach ($required as $field) {
            if (empty($body[$field]) && $body[$field] !== 0) {
                Response::error('VALIDATION_ERROR', "Field '{$field}' is required", 422);
            }
        }

        if ($body['rating'] < 0 || $body['rating'] > 5) {
            Response::error('VALIDATION_ERROR', 'rating must be between 0 and 5', 422);
        }

        $db = Database::getConnection();

        // Managers can only review employees in their own department
        if ($claims['role'] === 'manager') {
            $stmt = $db->prepare(
                'SELECT department_id FROM employees WHERE id = :manager_id'
            );
            $stmt->execute(['manager_id' => $claims['sub']]);
            $managerDept = $stmt->fetch()['department_id'];

            $stmt = $db->prepare('SELECT department_id FROM employees WHERE id = :employee_id');
            $stmt->execute(['employee_id' => $body['employee_id']]);
            $target = $stmt->fetch();

            if (!$target || $target['department_id'] !== $managerDept) {
                Response::error('FORBIDDEN', 'You can only review employees in your own department', 403);
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO performance_reviews (employee_id, reviewer_id, review_period, rating, strengths, improvements)
             VALUES (:employee_id, :reviewer_id, :review_period, :rating, :strengths, :improvements)'
        );
        $stmt->execute([
            'employee_id' => $body['employee_id'],
            'reviewer_id' => $claims['sub'],
            'review_period' => $body['review_period'],
            'rating' => $body['rating'],
            'strengths' => $body['strengths'] ?? null,
            'improvements' => $body['improvements'] ?? null,
        ]);

        Response::success(['id' => (int) $db->lastInsertId()], 201);
    }

    /** Admin sees all; manager sees their department's reviews */
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $db = Database::getConnection();

        $sql = 'SELECT pr.id, pr.employee_id, CONCAT(e.first_name, " ", e.last_name) AS employee_name,
                       CONCAT(r.first_name, " ", r.last_name) AS reviewer_name,
                       pr.review_period, pr.rating, pr.strengths, pr.improvements, pr.created_at
                FROM performance_reviews pr
                JOIN employees e ON e.id = pr.employee_id
                JOIN employees r ON r.id = pr.reviewer_id';
        $params = [];

        if ($claims['role'] === 'manager') {
            $sql .= ' WHERE e.department_id = (SELECT department_id FROM employees WHERE id = :manager_id)';
            $params['manager_id'] = $claims['sub'];
        }

        $sql .= ' ORDER BY pr.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll());
    }

    /** Employee's own reviews */
    public function me(): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT pr.id, CONCAT(r.first_name, " ", r.last_name) AS reviewer_name,
                    pr.review_period, pr.rating, pr.strengths, pr.improvements, pr.created_at
             FROM performance_reviews pr
             JOIN employees r ON r.id = pr.reviewer_id
             WHERE pr.employee_id = :employee_id
             ORDER BY pr.created_at DESC'
        );
        $stmt->execute(['employee_id' => $claims['sub']]);

        Response::success($stmt->fetchAll());
    }
}
