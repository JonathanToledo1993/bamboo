<?php
// api/client/create_candidate.php
require_once '../config/db.php';
require_once '../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

// Proteger la ruta (Requerir Cliente JWT)
$clientData = AuthMiddleware::requireClient();

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['name']) || empty($data['email'])) {
    Responder::error("El nombre y el correo electrónico del candidato son requeridos.");
}

// TODO: Validate email format
$name = trim($data['name']);
$email = trim($data['email']);

// Generar el Token único
$token = bin2hex(random_bytes(16)); // 32 chars long unique token

// Expiración: Por defecto 7 días
$expiresAt = new DateTime();
$expiresAt->modify('+7 days');
$expiresAtStr = $expiresAt->format('Y-m-d H:i:s');

try {
    // Insertar en Base de Datos (Ideal asociar a la CompanyId si modificamos schema en el futuro,
    // actualmente insertamos tal cual Schema nativo)
    $sql = "
        INSERT INTO evaluation_invites (id, token, email, name, expiresAt, createdAt)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";

    // Generar un CUID mock para compatibilidad con Prisma CUIDs
    $cuid = 'inv_' . uniqid();

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $cuid,
        $token,
        $email,
        $name,
        $expiresAtStr
    ]);

    // Generar Link Mágico
    // En producción cambiar el localhost por $_SERVER['HTTP_HOST']
    $magicLink = "http://localhost:8000/public/candidate/index.html?token=" . $token;

    Responder::success([
        "inviteId" => $cuid,
        "token" => $token,
        "magicLink" => $magicLink,
        "expiresAt" => $expiresAtStr
    ], "Invitación creada exitosamente.");

}
catch (Exception $e) {
    // Puede lanzar error de FK si evaluation_invites esperara datos (actualmente es tabla aislada en schema Prisma para test)
    Responder::error("No se pudo crear el candidato: " . $e->getMessage(), 500);
}
?>
