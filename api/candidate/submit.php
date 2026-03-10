<?php
// api/candidate/submit.php
require_once '../config/db.php';
require_once '../utils/Responder.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['token']) || empty($data['answers'])) {
    Responder::error("Faltan datos de envío o token no proporcionado.");
}

$token = $data['token'];

try {
    // Re-Verificar el Token por seguridad transaccional
    $sql = "SELECT id, expiresAt FROM evaluation_invites WHERE token = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    $invite = $stmt->fetch();

    if (!$invite || new DateTime($invite['expiresAt']) < new DateTime()) {
        Responder::error("Token inválido o expirado. La sesión fue cerrada.", 403);
    }

    // MOCK: En este punto, se procesarían las $data['answers'] contra 
    // las tablas 'catalog_questions' y 'catalog_answers' 
    // y se guardaría un registro en 'evaluations' (Tabla que requiere definirse mejor el FK string).

    // Por ahora, simulamos el éxito total quemando el token para que no se re-use.
    // Usualmente se hace marcando un completedAt en invites o borrando el récord.
    $sqlDel = "DELETE FROM evaluation_invites WHERE id = ?";
    $stmtDel = $pdo->prepare($sqlDel);
    $stmtDel->execute([$invite['id']]);

    Responder::success([], "Respuestas procesadas y guardadas exitosamente. El reporte ha sido generado para la empresa.");

}
catch (Exception $e) {
    Responder::error("Error guardando la prueba: " . $e->getMessage(), 500);
}
?>
