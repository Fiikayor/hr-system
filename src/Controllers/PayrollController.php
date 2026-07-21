<?php

namespace App\Controllers;

use Config\Database;
use App\Helpers\Response;
use App\Helpers\AuditLogger;
use App\Middleware\AuthMiddleware;

class PayrollController
{
    public function store(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $required = ['employee_id', 'pay_period_start', 'pay_period_end'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error('VALIDATION_ERROR', "Field '{$field}' is required", 422);
            }
        }

        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT salary FROM employees WHERE id = :id AND status = "active"');
        $stmt->execute(['id' => $body['employee_id']]);
        $employee = $stmt->fetch();

        if (!$employee) {
            Response::error('NOT_FOUND', 'Active employee not found', 404);
        }

        // Auto-fill from the employee record unless the admin explicitly overrides it
        $baseSalary = isset($body['base_salary']) && $body['base_salary'] !== ''
            ? (float) $body['base_salary']
            : (float) $employee['salary'];

        $bonuses = (float) ($body['bonuses'] ?? 0);
        $deductions = (float) ($body['deductions'] ?? 0);
        $netPay = $baseSalary + $bonuses - $deductions;

        $stmt = $db->prepare(
            'INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, base_salary, bonuses, deductions, net_pay, status)
             VALUES (:employee_id, :start, :end, :base_salary, :bonuses, :deductions, :net_pay, "processed")'
        );
        $stmt->execute([
            'employee_id' => $body['employee_id'],
            'start' => $body['pay_period_start'],
            'end' => $body['pay_period_end'],
            'base_salary' => $baseSalary,
            'bonuses' => $bonuses,
            'deductions' => $deductions,
            'net_pay' => $netPay,
        ]);

        $payrollId = (int) $db->lastInsertId();

        AuditLogger::log($claims['sub'], 'payroll.generate', 'payroll', $payrollId, [
            'employee_id' => $body['employee_id'],
            'net_pay' => $netPay,
        ]);

        Response::success(['id' => $payrollId, 'net_pay' => $netPay, 'status' => 'processed'], 201);
    }

    /** Admin — all payroll records */
    public function index(): void
    {
        $claims = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($claims, ['admin']);

        $db = Database::getConnection();
        $stmt = $db->query(
            'SELECT p.id, p.employee_id, CONCAT(e.first_name, " ", e.last_name) AS employee_name,
                    e.pay_frequency, p.pay_period_start, p.pay_period_end,
                    p.base_salary, p.bonuses, p.deductions, p.net_pay, p.status, p.generated_at
             FROM payroll p
             JOIN employees e ON e.id = p.employee_id
             ORDER BY p.generated_at DESC'
        );

        Response::success($stmt->fetchAll());
    }

    /** Employee/manager/admin — own payroll history */
    public function me(): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, pay_period_start, pay_period_end, base_salary, bonuses, deductions, net_pay, status, generated_at
             FROM payroll
             WHERE employee_id = :employee_id
             ORDER BY generated_at DESC'
        );
        $stmt->execute(['employee_id' => $claims['sub']]);

        Response::success($stmt->fetchAll());
    }
    public function payslip(int $id): void
    {
        $claims = AuthMiddleware::authenticate();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT p.*, e.first_name, e.last_name, e.employee_code, e.job_title, d.name AS department_name
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $payslip = $stmt->fetch();

        if (!$payslip) {
            Response::error('NOT_FOUND', 'Payslip not found', 404);
        }

        // Employees can only view their own payslip; admin can view any
        if ($claims['role'] !== 'admin' && (int) $claims['sub'] !== (int) $payslip['employee_id']) {
            Response::error('FORBIDDEN', 'You can only view your own payslip', 403);
        }

        $html = $this->buildPayslipHtml($payslip);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'payslip-' . $payslip['employee_code'] . '-' . $payslip['pay_period_start'] . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }

    private function buildPayslipHtml(array $p): string
    {
        $fullName = htmlspecialchars($p['first_name'] . ' ' . $p['last_name']);
        $dept = htmlspecialchars($p['department_name'] ?? 'N/A');
        $title = htmlspecialchars($p['job_title'] ?? 'N/A');

        return <<<HTML
    <html>
    <head>
        <style>
            body { font-family: sans-serif; font-size: 13px; color: #222; }
            h1 { font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 8px; }
            table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            td { padding: 6px 4px; border-bottom: 1px solid #ddd; }
            .label { color: #666; width: 40%; }
            .total { font-weight: bold; font-size: 15px; border-top: 2px solid #333; }
        </style>
    </head>
    <body>
        <h1>Payslip</h1>
        <p><strong>{$fullName}</strong> ({$p['employee_code']})<br>
        {$title} — {$dept}</p>

        <table>
            <tr><td class="label">Pay Period</td><td>{$p['pay_period_start']} to {$p['pay_period_end']}</td></tr>
            <tr><td class="label">Base Salary</td><td>{$p['base_salary']}</td></tr>
            <tr><td class="label">Bonuses</td><td>{$p['bonuses']}</td></tr>
            <tr><td class="label">Deductions</td><td>-{$p['deductions']}</td></tr>
            <tr class="total"><td class="label">Net Pay</td><td>{$p['net_pay']}</td></tr>
        </table>
    </body>
    </html>
    HTML;
    }
}
