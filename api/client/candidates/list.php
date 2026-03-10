<?php
// api/client/candidates/list.php
// Lista todos los candidatos invitados a evaluaciones de esta empresa.
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

try {
    $sql = "
        SELECT
            c.id                                            AS candidateId,
            c.firstName,
            c.lastName,
            c.email,
            ec.id                                          AS evalCandidateId,
            ec.status,
            ec.secureToken                                 AS inviteToken,
            ec.scoreGlobal,
            ec.completedAt,
            ec.createdAt                                   AS invitedAt,
            e.id                                           AS evaluationId,
            e.cargo                                        AS evaluationCargo,
            e.expiresAt,
            u.name                                         AS invitedByName,
            (
                SELECT COUNT(*)
                FROM evaluation_candidates ec2
                WHERE ec2.candidateId = c.id
                  AND ec2.status = 'COMPLETED'
            )                                              AS totalFinalizadas
        FROM candidates c
        JOIN evaluation_candidates ec ON ec.candidateId = c.id
        JOIN evaluations e            ON ec.evaluationId = e.id
        LEFT JOIN users u             ON e.creatorId = u.id
        WHERE c.companyId = ?
        ORDER BY ec.createdAt DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Responder::success([
        "candidates" => $candidates
    ], "Postulantes obtenidos.");

}
catch (Exception $e) {
    Responder::error("Error obteniendo postulantes: " . $e->getMessage(), 500);
}
?>
