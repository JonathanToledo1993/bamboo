<?php
// api/client/candidates/update_stage.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$evaluationId = trim($data['evaluationId'] ?? '');
$candidateId = trim($data['candidateId'] ?? '');
$newStage = trim($data['stage'] ?? '');

if (empty($evaluationId) || empty($candidateId) || empty($newStage)) {
    Responder::error("evaluationId, candidateId y stage son obligatorios.", 400);
}

try {
    // Verificar que la evaluación pertenezca a la empresa
    $chk = $pdo->prepare("SELECT id FROM evaluations WHERE id = ? AND companyId = ?");
    $chk->execute([$evaluationId, $companyId]);
    if (!$chk->fetch()) {
        Responder::error("Evaluación no encontrada o acceso denegado.", 403);
    }

    $sql = "
        UPDATE evaluation_candidates 
        SET internalStage = ? 
        WHERE evaluationId = ? AND candidateId = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newStage, $evaluationId, $candidateId]);

    if ($stmt->rowCount() > 0) {
        Responder::success(null, "Etapa actualizada correctamente a '$newStage'.");
    }
    else {
        Responder::success(null, "No hubieron cambios (la etapa ya era '$newStage' o no existe el candidato).");
    }

}
catch (Exception $e) {
    Responder::error("Error al actualizar etapa: " . $e->getMessage(), 500);
}
?>
