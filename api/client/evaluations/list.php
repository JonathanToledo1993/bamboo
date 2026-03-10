<?php
// api/client/evaluations/list.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

try {
    // Columnas reales según schema Prisma:
    // e.id, e.companyId, e.creatorId, e.cargo, e.profileKey, e.language,
    // e.confidential, e.archived, e.expiresAt, e.createdAt, e.updatedAt
    $sql = "
        SELECT 
            e.id,
            e.cargo,
            e.profileKey,
            e.language,
            e.confidential,
            e.archived,
            e.expiresAt,
            e.createdAt,
            e.updatedAt,
            u.name as creatorName,
            (SELECT COUNT(*) FROM evaluation_candidates ec WHERE ec.evaluationId = e.id) as totalCandidates,
            (SELECT COUNT(*) FROM evaluation_candidates ec WHERE ec.evaluationId = e.id AND ec.status = 'COMPLETED') as finishedCandidates
        FROM evaluations e
        LEFT JOIN users u ON e.creatorId = u.id
        WHERE e.companyId = ?
        ORDER BY e.createdAt DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Responder::success([
        "evaluations" => $evaluations
    ]);

}
catch (Exception $e) {
    Responder::error("Error obteniendo evaluaciones: " . $e->getMessage(), 500);
}
?>
