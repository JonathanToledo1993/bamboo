<?php
// api/auth/do_reset_password.php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Método no permitido."]));
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($token) || empty($password)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Token y contraseña son obligatorios."]));
}

if (strlen($password) < 6) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "La contraseña debe tener al menos 6 caracteres."]));
}

try {
    // 1. Verificar el token de restablecimiento
    $stmtToken = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expiresAt > NOW()");
    $stmtToken->execute([$token]);
    $resetReq = $stmtToken->fetch(PDO::FETCH_ASSOC);

    if (!$resetReq) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "El enlace de restablecimiento es inválido o ha expirado."]));
    }

    $pdo->beginTransaction();

    // 2. Actualizar contraseña del usuario
    $pwdHash = password_hash($password, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("UPDATE users SET password = ?, updatedAt = NOW() WHERE email = ?");
    $stmtUser->execute([$pwdHash, $resetReq['email']]);

    // 3. Eliminar el token usado
    $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmtDel->execute([$resetReq['email']]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Contraseña actualizada exitosamente."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
?>
