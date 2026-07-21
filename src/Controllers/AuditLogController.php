<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

class AuditLogController
{
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $db = Database::getConnection();

        $sql = 'SELECT al.id, al.employee_id,
                       CONCAT(e.first_name, " ", e.last_name) AS employee_name,
                       al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.created_at
                FROM audit_logs al
                LEFT JOIN employees e ON e.id = al.employee_id';
        $params = [];
        $conditions = [];

        if (!empty($_GET['action'])) {
            $conditions[] = 'al.action = :action';
            $params['action'] = $_GET['action'];
        }

        if (!empty($_GET['employee_id'])) {
            $conditions[] = 'al.employee_id = :employee_id';
            $params['employee_id'] = $_GET['employee_id'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY al.created_at DESC LIMIT 200';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll());
    }
}
