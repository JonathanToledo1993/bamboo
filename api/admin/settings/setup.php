<?php
// api/admin/settings/setup.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();
AuthMiddleware::requireAdmin();

try {
    // Crear tabla si no existe
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` TEXT,
        `type` VARCHAR(50) DEFAULT 'string',
        `label` VARCHAR(255),
        `description` TEXT,
        `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Insertar valores por defecto si está vacía
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
    if ($stmt->fetchColumn() == 0) {
        $defaults = [
            ['key' => 'credit_unit_price', 'value' => '1.50', 'type' => 'number', 'label' => 'Precio Básico por Crédito (USD)', 'description' => 'Valor base utilizado para calcular la compra de créditos sueltos.'],
            ['key' => 'free_trial_credits', 'value' => '50', 'type' => 'number', 'label' => 'Créditos Gratuitos (Trial)', 'description' => 'Cantidad de créditos que se otorgan automáticamente a una cuenta nueva al registrarse de forma pública (si aplica).'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'label' => 'Modo Mantenimiento', 'description' => 'Si está en 1, el sistema de clientes mostrará una pantalla de mantenimiento y no permitirá logins.'],
            ['key' => 'support_contact_email', 'value' => 'soporte@kokoro.app', 'type' => 'string', 'label' => 'Correo de Soporte Técnico', 'description' => 'Correo mostrado en la plataforma de clientes para ayuda rápida.'],
            ['key' => 'enable_candidate_registration', 'value' => '1', 'type' => 'boolean', 'label' => 'Habilitar Registro Abierto de Candidatos', 'description' => 'Permite que los candidatos se puedan registrar antes de empezar sus pruebas.']
        ];

        $insertSql = "INSERT INTO system_settings (`key`, `value`, `type`, `label`, `description`) VALUES (?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($insertSql);

        foreach ($defaults as $d) {
            $stmtInsert->execute([$d['key'], $d['value'], $d['type'], $d['label'], $d['description']]);
        }
    }

    Responder::success(["message" => "Tabla de configuración lista y poblada."], "Setup listo");

} catch (Exception $e) {
    Responder::error("Error en el setup: " . $e->getMessage(), 500);
}
?>
