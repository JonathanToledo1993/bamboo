<?php
// api/auth/forgot_password.php
require_once '../config/db.php';
require_once '../utils/Mailer.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Método no permitido."]));
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($input['email'] ?? ''));

if (empty($email)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Email obligatorio."]));
}

try {
    // 1. Create table if not exists (lazy migration)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
          `email` varchar(100) NOT NULL,
          `token` varchar(255) NOT NULL,
          `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `expiresAt` timestamp NOT NULL,
          KEY `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Check if user exists
    $stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmtUser->execute([$email]);
    $user = $stmtUser->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        
        $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmtDel->execute([$email]);

        $stmtIns = $pdo->prepare("INSERT INTO password_resets (email, token, expiresAt) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $stmtIns->execute([$email, $token]);

        $resetLink = "https://integritas.ec/app_admin/public/login.html?action=reset&token={$token}";
        Mailer::sendPasswordReset($email, $resetLink);
    }

    // Always return success to prevent email enumeration
    echo json_encode(["status" => "success", "message" => "Si el correo existe, se enviaron instrucciones para restablecer la contraseña."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
?>
