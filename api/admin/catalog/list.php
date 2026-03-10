<?php
// api/admin/catalog/list.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

AuthMiddleware::requireAdmin();

try {
    $sql = "
        SELECT 
            id, 
            `key`, 
            name, 
            category, 
            description, 
            durationMins,
            isActive
        FROM catalog_tests 
        ORDER BY category ASC, name ASC
    ";

    $stmt = $pdo->query($sql);
    $tests = $stmt->fetchAll();

    Responder::success([
        "catalog" => $tests
    ], "Catálogo global de pruebas obtenido.");

} catch (Exception $e) {
    Responder::error("Error obteniendo catálogo global: " . $e->getMessage(), 500);
}
?>
