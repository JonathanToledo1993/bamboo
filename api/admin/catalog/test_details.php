<?php
// api/admin/catalog/test_details.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

AuthMiddleware::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!isset($_GET['testId'])) {
        Responder::error("Se requiere el testId.", 400);
    }

    $testId = $_GET['testId'];

    try {
        $stmt = $pdo->prepare("SELECT id, `key`, name, category, description, durationMins, isActive, psychometricsConfig, resultsConfig FROM catalog_tests WHERE `id` = ?");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            Responder::error("Prueba no encontrada.", 404);
        }

        // Decodificar JSON config
        $test['psychometricsConfig'] = json_decode($test['psychometricsConfig'], true) ?: [];
        $test['resultsConfig'] = json_decode($test['resultsConfig'], true) ?: [];

        Responder::success(["test" => $test]);
    }
    catch (Exception $e) {
        Responder::error("Error obteniendo prueba: " . $e->getMessage(), 500);
    }

}
elseif ($method === 'PUT') {
    // Editar configuración de la prueba
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['testId'])) {
        Responder::error("testId es requerido.", 400);
    }

    $testId = $input['testId'];

    // Obtener los datos a actualizar (pueden ser parciales)
    $updateFields = [];
    $updateParams = [];

    if (isset($input['name'])) {
        $updateFields[] = "name = ?";
        $updateParams[] = trim($input['name']);
    }
    if (isset($input['category'])) {
        $updateFields[] = "category = ?";
        $updateParams[] = trim($input['category']);
    }
    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $updateParams[] = trim($input['description']);
    }
    if (isset($input['durationMins'])) {
        $updateFields[] = "durationMins = ?";
        $updateParams[] = (int)$input['durationMins'];
    }
    if (isset($input['psychometricsConfig'])) {
        $updateFields[] = "psychometricsConfig = ?";
        $updateParams[] = json_encode($input['psychometricsConfig']);
    }
    if (isset($input['resultsConfig'])) {
        $updateFields[] = "resultsConfig = ?";
        $updateParams[] = json_encode($input['resultsConfig']);
    }

    if (empty($updateFields)) {
        Responder::error("No hay campos para actualizar.", 400);
    }

    try {
        // Agregar id al final de params
        $updateParams[] = $testId;

        $sql = "UPDATE catalog_tests SET " . implode(", ", $updateFields) . " WHERE `id` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateParams);

        Responder::success(["message" => "Configuración guardada correctamente."]);
    }
    catch (Exception $e) {
        Responder::error("Error actualizando prueba: " . $e->getMessage(), 500);
    }

}
elseif ($method === 'DELETE') {
    // Eliminar la prueba entera y cascada (manualmente, si no hay cascade config)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['testId'])) {
        Responder::error("testId de prueba requerido.", 400);
    }

    $testId = $input['testId'];

    try {
        // Iniciar transacción para borrar en ambas tablas
        $pdo->beginTransaction();

        $stmtQ = $pdo->prepare("DELETE FROM catalog_questions WHERE testId = ?");
        $stmtQ->execute([$testId]);

        $stmtT = $pdo->prepare("DELETE FROM catalog_tests WHERE `id` = ?");
        $stmtT->execute([$testId]);

        $pdo->commit();

        Responder::success(["message" => "Prueba eliminada completamente."]);
    }
    catch (Exception $e) {
        $pdo->rollBack();
        Responder::error("Error eliminando prueba: " . $e->getMessage(), 500);
    }
}
else {
    Responder::error("Método no permitido.", 405);
}
?>
