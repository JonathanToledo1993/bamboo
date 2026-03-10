<?php
// api/utils/auth_middleware.php
require_once 'jwt.php';
require_once 'Responder.php';

class AuthMiddleware
{
    /**
     * Valida la sesión del token para cualquier usuario y devuelve su payload.
     */
    public static function authenticate()
    {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : $_SERVER;

        $authHeader = null;
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        }
        elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
        elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Responder::error("Acceso denegado. Token no proporcionado.", 401);
        }

        $jwt = $matches[1];

        $decoded = JWT::decode($jwt);
        if (!$decoded) {
            Responder::error("Token inválido o expirado. Por favor, inicie sesión nuevamente.", 401);
        }

        return $decoded; // Retorna el payload del token (id, role, type, companyId)
    }

    /**
     * Valida que el usuario sea estrictamente un Administrador del Sistema
     */
    public static function requireAdmin()
    {
        $user = self::authenticate();

        if ($user['type'] !== 'ADMIN') {
            Responder::error("Acceso prohibido. Se requiere rol de administrador.", 403);
        }

        return $user;
    }

    /**
     * Valida que el usuario sea estrictamente un Cliente (Empresa)
     */
    public static function requireClient()
    {
        $user = self::authenticate();

        if ($user['type'] !== 'CLIENT') {
            Responder::error("Acceso prohibido. Se requiere rol de empresa.", 403);
        }

        return $user;
    }
}
?>
