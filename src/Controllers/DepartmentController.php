<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

class DepartmentController
{
    public function index(): void
    {
        AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->query(
            'SELECT d.id, d.name, d.description, d.manager_id,
                CONCAT(e.first_name, " ", e.last_name) AS manager_name
         FROM departments d
         LEFT JOIN employees e ON e.id = d.manager_id
         ORDER BY d.name'
        );

        Response::success($stmt->fetchAll());
    }

    public function show(int $id): void
    {
        AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT d.id, d.name, d.description, d.manager_id,
                CONCAT(e.first_name, " ", e.last_name) AS manager_name
         FROM departments d
         LEFT JOIN employees e ON e.id = d.manager_id
         WHERE d.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $department = $stmt->fetch();

        if (!$department) {
            Response::error('NOT_FOUND', 'Department not found', 404);
        }

        Response::success($department);
    }

    public function store(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body['name'])) {
            Response::error('VALIDATION_ERROR', 'Department name is required', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO departments (name, description, manager_id) VALUES (:name, :description, :manager_id)'
        );
        $stmt->execute([
            'name' => $body['name'],
            'description' => $body['description'] ?? null,
            'manager_id' => $body['manager_id'] ?? null,
        ]);

        Response::success(['id' => (int) $db->lastInsertId()], 201);
    }

    public function update(int $id): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $allowedFields = ['name', 'description', 'manager_id'];
        $updates = array_intersect_key($body, array_flip($allowedFields));

        if (empty($updates)) {
            Response::error('VALIDATION_ERROR', 'No valid fields provided to update', 422);
        }

        $setClause = implode(', ', array_map(fn($f) => "{$f} = :{$f}", array_keys($updates)));
        $updates['id'] = $id;

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE departments SET {$setClause} WHERE id = :id");
        $stmt->execute($updates);

        Response::success(['updated' => true]);
    }

    public function destroy(int $id): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM departments WHERE id = :id');
        $stmt->execute(['id' => $id]);

        Response::success(['deleted' => true]);
    }
}
