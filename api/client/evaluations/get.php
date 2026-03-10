<?php
// api/client/evaluations/get.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

if (!isset($_GET['id'])) {
    Responder::error("Se requiere el ID de la evaluación.", 400);
}
$evaluationId = trim($_GET['id']);

try {
    // 1. OBTENER DATOS DE LA EVALUACIÓN Y PERFIL
    $sqlEval = "
        SELECT 
            e.id,
            e.cargo,
            e.profileKey,
            p.name as profileName,
            p.testKeys,
            e.language,
            e.confidential,
            e.archived,
            e.expiresAt,
            e.createdAt,
            u.name as creatorName,
            (SELECT COUNT(*) FROM evaluation_candidates ec WHERE ec.evaluationId = e.id) as totalCandidates,
            (SELECT COUNT(*) FROM evaluation_candidates ec WHERE ec.evaluationId = e.id AND ec.status = 'COMPLETED') as finishedCandidates
        FROM evaluations e
        LEFT JOIN profiles p ON e.profileKey = p.id
        LEFT JOIN users u ON e.creatorId = u.id
        WHERE e.id = ? AND e.companyId = ?
    ";
    $stmtEval = $pdo->prepare($sqlEval);
    $stmtEval->execute([$evaluationId, $companyId]);
    $evaluation = $stmtEval->fetch(PDO::FETCH_ASSOC);

    if (!$evaluation) {
        Responder::error("Evaluación no encontrada o no pertenece a la empresa.", 404);
    }

    // 2. OBTENER DETALLE DE LAS PRUEBAS (CATÁLOGO)
    $testsAssigned = [];
    $profileKey = $evaluation['profileKey'] ?? '';

    // Intento 1: Buscar por tabla pivot (cuando el perfil fue creado desde el UI)
    if (!empty($profileKey)) {
        $sqlPivot = "
            SELECT ct.id, ct.`key`, ct.name, ct.description, ct.durationMins 
            FROM profile_tests pt
            INNER JOIN catalog_tests ct ON pt.testId = ct.id
            WHERE pt.profileId = ?
        ";
        $stmtPivot = $pdo->prepare($sqlPivot);
        $stmtPivot->execute([$profileKey]);
        $testsAssigned = $stmtPivot->fetchAll(PDO::FETCH_ASSOC);
    }

    // Intento 2: Compatibilidad hacia atrás para leer desde el JSON testKeys de profiles
    if (empty($testsAssigned) && !empty($evaluation['testKeys'])) {
        $keys = json_decode($evaluation['testKeys'], true);
        if (is_array($keys) && count($keys) > 0) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $sqlTests = "SELECT id, `key`, name, description, durationMins FROM catalog_tests WHERE `key` IN ($placeholders)";
            $stmtTests = $pdo->prepare($sqlTests);
            $stmtTests->execute($keys);
            $testsAssigned = $stmtTests->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $evaluation['tests'] = $testsAssigned;
    unset($evaluation['testKeys']); // Limpiamos la prop sin procesar

    // 3. OBTENER LOS POSTULANTES DE ESTA EVALUACIÓN
    $sqlCandidates = "
        SELECT 
            c.id as candidateId,
            c.firstName,
            c.lastName,
            c.email,
            ec.status,
            ec.scoreGlobal,
            ec.internalStage as stage,
            ec.createdAt as invitedAt,
            NULL as invitedBy
        FROM evaluation_candidates ec
        INNER JOIN candidates c ON ec.candidateId = c.id
        WHERE ec.evaluationId = ?
        ORDER BY ec.createdAt DESC
    ";
    $stmtCand = $pdo->prepare($sqlCandidates);
    $stmtCand->execute([$evaluationId]);
    $candidates = $stmtCand->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: Si invitedBy viene null, ponemos el creator de la eval
    foreach ($candidates as &$cand) {
        if (empty($cand['invitedBy'])) {
            $cand['invitedBy'] = $evaluation['creatorName'];
        }
    }

    $evaluation['candidates'] = $candidates;

    Responder::success([
        "evaluation" => $evaluation
    ]);

}
catch (Exception $e) {
    Responder::error("Error obteniendo detalles de evaluación: " . $e->getMessage(), 500);
}
?>
