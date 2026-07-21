<?php

namespace App\Helpers;

class Response
{
    public static function success($data = null, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public static function error(string $code, string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message]]);
        exit;
    }
}
