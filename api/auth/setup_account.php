<?php
// api/auth/setup_account.php
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
$name = trim($input['name'] ?? '');
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
    // 1. Verificar la invitación
    $stmtInv = $pdo->prepare("SELECT * FROM users_invitations WHERE token = ? AND expiresAt > NOW()");
    $stmtInv->execute([$token]);
    $invite = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invite) {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "El enlace de invitación es inválido o ha expirado."]));
    }

    $pdo->beginTransaction();

    // 2. Crear el usuario
    $userId = uniqid('US-');
    $pwdHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Si el nombre no fue provisto, extraerlo del email por defecto
    if (empty($name)) {
        $nameParts = explode('@', $invite['email']);
        $name = ucfirst($nameParts[0]);
    }

    $stmtUser = $pdo->prepare("
        INSERT INTO users (id, companyId, name, role, email, password, isActive, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
    ");
    $stmtUser->execute([
        $userId, 
        $invite['companyId'], 
        $name, 
        $invite['role'], 
        $invite['email'], 
        $pwdHash
    ]);

    // 3. Eliminar la invitación
    $stmtDel = $pdo->prepare("DELETE FROM users_invitations WHERE id = ?");
    $stmtDel->execute([$invite['id']]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Cuenta configurada exitosamente."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
?>
