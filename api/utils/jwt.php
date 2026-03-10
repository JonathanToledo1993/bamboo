<?php
// api/utils/jwt.php

require_once __DIR__ . '/../config/env.php';

class JWT
{
    private static function getSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'kokoro-php-super-secret-key-2026';
    }

    /**
     * Codifica un array en base64 de forma URL-safe
     */
    private static function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Decodifica un string base64 URL-safe
     */
    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    /**
     * Genera un nuevo JSON Web Token
     */
    public static function encode($payload)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Verifica y decodifica un JWT. Devuelve el Payload si es válido, falso si no.
     */
    public static function decode($jwt)
    {
        $tokenParts = explode('.', $jwt);

        if (count($tokenParts) != 3) {
            return false;
        }

        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $signature_provided = $tokenParts[2];

        // Expiración
        $decoded_payload = json_decode(self::base64UrlDecode($payload), true);
        if (isset($decoded_payload['exp']) && $decoded_payload['exp'] < time()) {
            return false; // Token expirado
        }

        // Re-firmar
        $signature = hash_hmac('sha256', $header . "." . $payload, self::getSecret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        if (hash_equals($base64UrlSignature, $signature_provided)) {
            return $decoded_payload;
        }

        return false;
    }
}
?>
