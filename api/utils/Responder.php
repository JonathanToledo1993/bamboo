<?php
// api/utils/Responder.php

class Responder
{
    /**
     * Emite los headers CORS por defecto para permitir acceso de cualquier origen en la API
     */
    public static function setupCORS()
    {
        // Fuerza a PHP a no imprimir errores como HTML para no romper los parseos JSON
        ini_set('display_errors', '0');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        // Si es una petición pre-flight OPTIONS, terminar rápido
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        header("Content-Type: application/json; charset=UTF-8");
    }

    /**
     * Responde con un JSON de éxito
     */
    public static function success($data = [], $message = "Operación exitosa", $status = 200)
    {
        http_response_code($status);
        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => $message,
            "data" => $data
        ]);
        exit();
    }

    /**
     * Responde con un JSON de error
     */
    public static function error($message = "Error interno", $status = 400)
    {
        http_response_code($status);
        echo json_encode([
            "success" => false,
            "status" => "error",
            "error" => $message,
            "message" => $message
        ]);
        exit();
    }
}
?>
