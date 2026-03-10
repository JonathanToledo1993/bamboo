<?php
// api/config/env.php
// Cargador de variables de entorno desde el archivo .env
// No requiere librerías externas. Úsalo con: require_once __DIR__ . '/../../config/env.php';

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        // En producción, si no hay .env, intentamos continuar con variables del sistema
        // (útil si el hosting las inyecta via CPanel / php.ini)
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios
        $line = trim($line);
        if (str_starts_with($line, '#') || $line === '') {
            continue;
        }

        // Parsear KEY=VALUE
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Quitar comillas opcionales del valor: "valor" o 'valor'
        if (
        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Solo establecer si no existe ya (respeta variables del sistema)
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Ruta al .env en la raíz del proyecto (un nivel arriba de /api/)
$envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
loadEnv($envPath);
