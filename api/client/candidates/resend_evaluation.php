<?php
// api/client/candidates/resend_evaluation.php
// Resets a candidate's INVALIDATED status so they can retake the exam
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Método no permitido."]));
}

$input = json_decode(file_get_contents('php://input'), true);
$evaluationId = $input['evaluationId'] ?? '';
$candidateId = $input['candidateId'] ?? '';

if (empty($evaluationId) || empty($candidateId)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "evaluationId y candidateId son obligatorios."]));
}

try {
    // Find the evaluation_candidate record
    $stmt = $pdo->prepare("SELECT ec.id, ec.status FROM evaluation_candidates ec WHERE ec.evaluationId = ? AND ec.candidateId = ?");
    $stmt->execute([$evaluationId, $candidateId]);
    $ec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ec) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Candidato no encontrado en esta evaluación."]));
    }

    if ($ec['status'] !== 'INVALIDATED') {
        http_response_code(400);
        die(json_encode(["status" => "error", "message" => "El candidato no tiene una evaluación anulada."]));
    }

    $pdo->beginTransaction();

    // Delete the invalidated answers and security metadata
    $stmtDelete = $pdo->prepare("DELETE FROM candidate_answers WHERE evaluationCandidateId = ?");
    $stmtDelete->execute([$ec['id']]);

    // Reset the candidate status to IN_PROGRESS so they can retake
    $stmtUpdate = $pdo->prepare("UPDATE evaluation_candidates SET status = 'IN_PROGRESS', lastActivityAt = NOW(), completedAt = NULL WHERE id = ?");
    $stmtUpdate->execute([$ec['id']]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Evaluación reenviada. El candidato puede volver a tomar la prueba."]);

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}
?>
