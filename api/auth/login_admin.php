<?php
// api/auth/login_admin.php
require_once '../config/db.php';
require_once '../utils/jwt.php';
require_once '../utils/Responder.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

// Recibir input crudo JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['email']) || !isset($data['password'])) {
    Responder::error("Debe proporcionar email y clave.");
}

$email = trim($data['email']);
$password = $data['password'];

// Buscar el Admin
$stmt = $pdo->prepare("SELECT id, email, password, name, role, isActive FROM admins WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin) {
    Responder::error("Credenciales inválidas.", 401);
}

if (!$admin['isActive']) {
    Responder::error("Cuenta desactivada.", 403);
}

// Verificar clave: bcrypt hash generado por el Node.js anterior (compatible nativo con password_verify)
if (!password_verify($password, $admin['password'])) {
    Responder::error("Credenciales inválidas.", 401);
}

// Crear Token Session
$payload = [
    'id' => $admin['id'],
    'role' => $admin['role'],
    'type' => 'ADMIN',
    'exp' => time() + (86400 * 7) // 7 Días de vigencia
];

$token = JWT::encode($payload);

Responder::success([
    'token' => $token,
    'user' => [
        'id' => $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role']
    ]
], "Sesión iniciada correctamente");
?>
