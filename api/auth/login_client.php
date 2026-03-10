<?php
// api/auth/login_client.php
require_once '../config/db.php';
require_once '../utils/jwt.php';
require_once '../utils/Responder.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['email']) || !isset($data['password'])) {
    Responder::error("Faltan datos de sesión.");
}

$email = trim($data['email']);
$password = $data['password'];

// Buscar Evaluador y datos de la empresa unida
$sql = "
    SELECT u.id, u.email, u.password, u.name, u.role, u.isActive, u.companyId,
           c.isActive as companyActive, c.plan, c.name as companyName 
    FROM users u 
    JOIN companies c ON u.companyId = c.id
    WHERE u.email = ? LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    Responder::error("Correo o contraseña incorrectos.", 401);
}

if (!$user['isActive'] || !$user['companyActive']) {
    Responder::error("Esta cuenta de empresa se encuentra desactivada temporalmente.", 403);
}

// Expedir Token Session
$payload = [
    'id' => $user['id'],
    'role' => $user['role'],
    'type' => 'CLIENT',
    'companyId' => $user['companyId'],
    'exp' => time() + (86400 * 7)
];

$token = JWT::encode($payload);

Responder::success([
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'companyId' => $user['companyId'],
        'companyName' => $user['companyName'],
        'plan' => $user['plan']
    ]
], "Bienvenido, " . $user['name']);
?>
