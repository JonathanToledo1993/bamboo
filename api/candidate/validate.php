<?php
// api/candidate/validate.php
require_once '../config/db.php';
require_once '../utils/Responder.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido.", 405);
}

if (empty($_GET['token'])) {
    Responder::error("Token de invitación extraviado o no proporcionado.");
}

$token = trim($_GET['token']);

try {
    // Buscar la invitación por token
    $sql = "SELECT id, name, email, expiresAt FROM evaluation_invites WHERE token = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    $invite = $stmt->fetch();

    if (!$invite) {
        Responder::error("El enlace proporcionado no es válido o no existe.", 404);
    }

    // Verificar expiración
    $expiresAt = new DateTime($invite['expiresAt']);
    $now = new DateTime();

    if ($now > $expiresAt) {
        Responder::error("Este enlace de evaluación ha expirado.", 403);
    }

    // Retorno exitoso
    Responder::success([
        "candidate" => [
            "id" => $invite['id'],
            "name" => $invite['name'],
            "email" => $invite['email']
        ],
        "test" => [
            "name" => "Evaluación de Comportamiento Ventas",
            "durationMins" => 15,
            "questionsCount" => 20
        ]
    ], "Token válido. Bienvenido.");

}
catch (Exception $e) {
    Responder::error("Error de servidor validando token.", 500);
}
?>
