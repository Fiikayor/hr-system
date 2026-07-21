<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

class AnnouncementController
{
    /** Everyone sees company-wide announcements + ones for their own department */
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT a.id, a.title, a.body, a.department_id,
                    CONCAT(e.first_name, " ", e.last_name) AS posted_by_name,
                    a.created_at
             FROM announcements a
             JOIN employees e ON e.id = a.posted_by
             WHERE a.department_id IS NULL
                OR a.department_id = (SELECT department_id FROM employees WHERE id = :employee_id)
             ORDER BY a.created_at DESC'
        );
        $stmt->execute(['employee_id' => $claims['sub']]);

        Response::success($stmt->fetchAll());
    }

    public function store(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body['title']) || empty($body['body'])) {
            Response::error('VALIDATION_ERROR', 'title and body are required', 422);
        }

        // Managers can only post to their own department, never company-wide
        $departmentId = $body['department_id'] ?? null;
        if ($claims['role'] === 'manager') {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT department_id FROM employees WHERE id = :id');
            $stmt->execute(['id' => $claims['sub']]);
            $departmentId = $stmt->fetch()['department_id'];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO announcements (title, body, posted_by, department_id)
             VALUES (:title, :body, :posted_by, :department_id)'
        );
        $stmt->execute([
            'title' => $body['title'],
            'body' => $body['body'],
            'posted_by' => $claims['sub'],
            'department_id' => $departmentId,
        ]);

        Response::success(['id' => (int) $db->lastInsertId()], 201);
    }

    public function destroy(int $id): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin', 'manager']);

        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT posted_by FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $announcement = $stmt->fetch();

        if (!$announcement) {
            Response::error('NOT_FOUND', 'Announcement not found', 404);
        }

        // Managers can only delete their own posts; admin can delete any
        if ($claims['role'] === 'manager' && (int) $announcement['posted_by'] !== (int) $claims['sub']) {
            Response::error('FORBIDDEN', 'You can only delete your own announcements', 403);
        }

        $stmt = $db->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success(['deleted' => true]);
    }
}
