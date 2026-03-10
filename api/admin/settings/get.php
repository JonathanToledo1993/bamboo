<?php
// api/admin/settings/get.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();
AuthMiddleware::requireAdmin();

try {
    $stmt = $pdo->query("SELECT `key`, `value`, `type`, `label`, `description`, `updatedAt` FROM system_settings ORDER BY `label` ASC");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Responder::success(["settings" => $settings]);
} catch (Exception $e) {
    Responder::error("Error obteniendo configuraciones: " . $e->getMessage(), 500);
}
?>
