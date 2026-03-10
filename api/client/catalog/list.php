<?php
// api/client/catalog/list.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

// Proteger la ruta
$user = AuthMiddleware::requireClient();
$companyId = $user['companyId'];

try {
    // Listar Catálogo Oficial + Pruebas Personalizadas del Cliente
    $sql = "
        SELECT 
            id, 
            `key`, 
            name, 
            category, 
            description, 
            durationMins,
            0 as isCustom
        FROM catalog_tests 
        WHERE isActive = 1 
        
        UNION ALL
        
        SELECT 
            id, 
            'custom' as `key`, 
            name, 
            'Personalizadas' as category, 
            description, 
            totalDuration as durationMins,
            1 as isCustom
        FROM custom_tests 
        WHERE companyId = ?
        
        ORDER BY category ASC, name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    $tests = $stmt->fetchAll();

    Responder::success([
        "catalog" => $tests
    ], "Catálogo de pruebas y personalizadas obtenido.");

}
catch (Exception $e) {
    Responder::error("Error obteniendo catálogo: " . $e->getMessage(), 500);
}
?>
