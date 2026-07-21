<?php

namespace App\Middleware;

use App\Helpers\JwtHelper;
use App\Helpers\Response;

class AuthMiddleware
{
    /**
     * Verifies the JWT and returns the decoded claims (employee id, role, email).
     */
    public static function authenticate(): array
    {
        $token = JwtHelper::getBearerToken();

        if (!$token) {
            Response::error('UNAUTHORIZED', 'Missing bearer token', 401);
        }

        $claims = JwtHelper::verify($token);

        if (!$claims) {
            Response::error('UNAUTHORIZED', 'Invalid or expired token', 401);
        }

        return $claims;
    }

    /**
     * Call after authenticate(). 
     * AuthMiddleware::authorize($user, ['admin', 'manager']);
     */
    public static function authorize(array $claims, array $allowedRoles): void
    {
        if (!in_array($claims['role'], $allowedRoles, true)) {
            Response::error('FORBIDDEN', 'You do not have permission to perform this action', 403);
        }
    }
}
