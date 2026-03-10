<?php
// api/admin/settings/update.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

AuthMiddleware::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['settings']) || !is_array($input['settings'])) {
    Responder::error("Payload inválido.", 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = ?");

    foreach ($input['settings'] as $key => $value) {
        $stmt->execute([$value, $key]);
    }

    $pdo->commit();

    Responder::success(["message" => "Configuraciones actualizadas exitosamente."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Responder::error("Error al actualizar: " . $e->getMessage(), 500);
}
?>
