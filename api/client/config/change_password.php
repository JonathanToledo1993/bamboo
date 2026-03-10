<?php
// api/client/config/change_password.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

// Proteger la ruta
$payloadData = AuthMiddleware::requireClient();
$userId = $payloadData['id'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$newPassword = $data['newPassword'] ?? '';
$confirmPassword = $data['confirmPassword'] ?? '';

if (empty($newPassword) || empty($confirmPassword)) {
    Responder::error("Debes ingresar la nueva contraseña y confirmarla.", 400);
}

if ($newPassword !== $confirmPassword) {
    Responder::error("Las contraseñas no coinciden.", 400);
}

if (strlen($newPassword) < 6) {
    Responder::error("La nueva contraseña debe tener al menos 6 caracteres.", 400);
}

try {
    // Hashear y actualizar
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = :pwd, updatedAt = NOW() WHERE id = :id");
    $updateStmt->execute([
        'pwd' => $newHash,
        'id' => $userId
    ]);

    Responder::success([], "Contraseña actualizada correctamente.");
}
catch (Exception $e) {
    Responder::error("Error actualizando la contraseña: " . $e->getMessage(), 500);
}
?>
