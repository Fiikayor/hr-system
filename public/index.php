<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Response;
use App\Controllers\AuthController;
use App\Controllers\EmployeeController;
use App\Controllers\DepartmentController;
use App\Controllers\AttendanceController;
use App\Controllers\LeaveRequestController;
use App\Controllers\AnnouncementController;
use App\Controllers\PerformanceReviewController;
use App\Controllers\AuditLogController;
use App\Controllers\PayrollController;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse path: everything after /public/api
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/hr-system/public/api';
$path = trim(str_replace($basePath, '', $uri), '/');
$segments = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- /health ---
    if ($segments === ['health']) {
        Response::success(['status' => 'ok', 'time' => date('c')]);
    }

    // --- /auth/* ---
    if (($segments[0] ?? null) === 'auth') {
        $auth = new AuthController();
        match (true) {
            $segments[1] === 'login' && $method === 'POST' => $auth->login(),
            $segments[1] === 'register' && $method === 'POST' => $auth->register(),
            $segments[1] === 'me' && $method === 'GET' => $auth->me(),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /employees ---
    if (($segments[0] ?? null) === 'employees') {
        $controller = new EmployeeController();
        $id = isset($segments[1]) ? (int) $segments[1] : null;

        match (true) {
            $method === 'GET' && $id === null => $controller->index(),
            $method === 'GET' && $id !== null => $controller->show($id),
            $method === 'PUT' && $id !== null => $controller->update($id),
            $method === 'DELETE' && $id !== null => $controller->destroy($id),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /departments ---
    if (($segments[0] ?? null) === 'departments') {
        $controller = new DepartmentController();
        $id = isset($segments[1]) ? (int) $segments[1] : null;

        match (true) {
            $method === 'GET' && $id === null => $controller->index(),
            $method === 'POST' && $id === null => $controller->store(),
            $method === 'PUT' && $id !== null => $controller->update($id),
            $method === 'DELETE' && $id !== null => $controller->destroy($id),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /attendance ---
    if (($segments[0] ?? null) === 'attendance') {
        $controller = new AttendanceController();

        match (true) {
            $method === 'POST' && ($segments[1] ?? null) === 'check-in' => $controller->checkIn(),
            $method === 'POST' && ($segments[1] ?? null) === 'check-out' => $controller->checkOut(),
            $method === 'GET' && ($segments[1] ?? null) === 'me' => $controller->me(),
            $method === 'GET' && !isset($segments[1]) => $controller->index(),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /leave-requests ---
    if (($segments[0] ?? null) === 'leave-requests') {
        $controller = new LeaveRequestController();
        $id = isset($segments[1]) && is_numeric($segments[1]) ? (int) $segments[1] : null;

        match (true) {
            $method === 'POST' && $id === null => $controller->store(),
            $method === 'GET' && ($segments[1] ?? null) === 'me' => $controller->me(),
            $method === 'GET' && $id === null => $controller->index(),
            $method === 'PUT' && $id !== null && ($segments[2] ?? null) === 'review' => $controller->review($id),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /announcements ---
    if (($segments[0] ?? null) === 'announcements') {
        $controller = new AnnouncementController();
        $id = isset($segments[1]) ? (int) $segments[1] : null;

        match (true) {
            $method === 'GET' && $id === null => $controller->index(),
            $method === 'POST' && $id === null => $controller->store(),
            $method === 'DELETE' && $id !== null => $controller->destroy($id),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /reviews ---
    if (($segments[0] ?? null) === 'reviews') {
        $controller = new PerformanceReviewController();

        match (true) {
            $method === 'POST' && !isset($segments[1]) => $controller->store(),
            $method === 'GET' && ($segments[1] ?? null) === 'me' => $controller->me(),
            $method === 'GET' && !isset($segments[1]) => $controller->index(),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /audit-logs ---
    if (($segments[0] ?? null) === 'audit-logs') {
        $controller = new AuditLogController();

        match (true) {
            $method === 'GET' => $controller->index(),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    // --- /payroll ---
    if (($segments[0] ?? null) === 'payroll') {
        $controller = new PayrollController();
        $id = isset($segments[1]) && is_numeric($segments[1]) ? (int) $segments[1] : null;

        match (true) {
            $method === 'POST' && !isset($segments[1]) => $controller->store(),
            $method === 'GET' && ($segments[1] ?? null) === 'me' => $controller->me(),
            $method === 'GET' && $id !== null && ($segments[2] ?? null) === 'payslip' => $controller->payslip($id),
            $method === 'GET' && !isset($segments[1]) => $controller->index(),
            default => Response::error('NOT_FOUND', 'Route not found', 404),
        };
    }

    Response::error('NOT_FOUND', 'Route not found', 404);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    Response::error('SERVER_ERROR', 'Something went wrong', 500);
}
