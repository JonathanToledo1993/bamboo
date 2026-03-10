<?php
// api/config/ai.php
// Configuración para proveedores externos de IA
// Las credenciales se leen desde el archivo .env en la raíz del proyecto.

require_once __DIR__ . '/env.php';

return [
    'gemini' => [
        'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
        'model' => $_ENV['GEMINI_MODEL'] ?? 'gemini-2.5-flash',
    ]
];
