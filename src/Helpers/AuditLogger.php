<?php
namespace App\Helpers;

use Config\Database;

class AuditLogger
{
    public static function log(?int $employeeId, string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'INSERT INTO audit_logs (employee_id, action, entity_type, entity_id, details, ip_address)
             VALUES (:employee_id, :action, :entity_type, :entity_id, :details, :ip_address)'
        );

        $stmt->execute([
            'employee_id' => $employeeId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
