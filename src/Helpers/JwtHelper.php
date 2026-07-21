<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtHelper
{
    public static function generate(array $payload): string
    {
        $secret = $_ENV['JWT_SECRET'];
        $expiry = (int) ($_ENV['JWT_EXPIRY_SECONDS'] ?? 86400);

        $issuedAt = time();
        $claims = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $expiry,
        ]);

        return JWT::encode($claims, $secret, 'HS256');
    }

    /**
     * Returns decoded claims as array, or null if invalid/expired.
     */
    public static function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function getBearerToken(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
