<?php
// api/admin/users/reset_password.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

AuthMiddleware::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['userId'], $input['newPassword'])) {
    Responder::error("Faltan datos en la petición.", 400);
}

$userId = $input['userId'];
$newPass = trim($input['newPassword']);

if (strlen($newPass) < 6) {
    Responder::error("La nueva contraseña debe tener al menos 6 caracteres.", 400);
}

try {
    $pwdHash = password_hash($newPass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ?, updatedAt = NOW() WHERE id = ?");
    $stmt->execute([$pwdHash, $userId]);

    if ($stmt->rowCount() > 0) {
        Responder::success(["message" => "Contraseña restablecida exitosamente."]);
    } else {
        Responder::error("Usuario no encontrado o no hubo cambios.", 404);
    }

} catch (Exception $e) {
    Responder::error("Error restableciendo contraseña: " . $e->getMessage(), 500);
}
?>
