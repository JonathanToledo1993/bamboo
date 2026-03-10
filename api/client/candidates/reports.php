<?php
// api/client/candidates/reports.php
// Retorna todas las evaluaciones en las que ha participado un candidato (por empresa)
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];
$candidateId = trim($_GET['candidateId'] ?? '');

if (empty($candidateId)) {
    Responder::error("candidateId requerido.");
}

try {
    // Verify candidate belongs to this company
    $chk = $pdo->prepare("SELECT id FROM candidates WHERE id = ? AND companyId = ?");
    $chk->execute([$candidateId, $companyId]);
    if (!$chk->fetch()) {
        Responder::error("Candidato no encontrado.", 404);
    }

    $sql = "
        SELECT
            ec.id               AS evalCandidateId,
            ec.status,
            ec.scoreGlobal,
            ec.completedAt,
            ec.createdAt        AS invitedAt,
            e.id                AS evaluationId,
            e.cargo             AS evaluationCargo,
            e.profileKey,
            e.language,
            e.expiresAt,
            u.name              AS invitedByName
        FROM evaluation_candidates ec
        JOIN evaluations e ON ec.evaluationId = e.id
        LEFT JOIN users u ON e.creatorId = u.id
        WHERE ec.candidateId = ?
          AND e.companyId = ?
        ORDER BY ec.createdAt DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$candidateId, $companyId]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Responder::success([
        "reports" => $reports
    ], "Reportes obtenidos.");

}
catch (Exception $e) {
    Responder::error("Error obteniendo reportes: " . $e->getMessage(), 500);
}
?>
