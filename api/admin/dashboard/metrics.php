<?php
// api/admin/dashboard/metrics.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido.", 405);
}

// Validar que el usuario sea AdminOps
$adminData = AuthMiddleware::requireAdmin();

try {
    $metrics = [
        "totalCompanies" => 0,
        "totalCreditsConsumed" => 0,
        "totalCandidates" => 0,
        "totalTestsCompleted" => 0
    ];

    // Total Empresas Activas
    $stmt1 = $pdo->query("SELECT COUNT(*) FROM companies WHERE isActive = 1");
    $metrics["totalCompanies"] = (int)$stmt1->fetchColumn();

    // Créditos Totales Consumidos (evaluaciones completadas por clientes de plan credits)
    // Para simplificar, asumimos cada invitacion a candidato consume 1 o que viene del log
    $stmt2 = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM credit_transactions WHERE type = 'DEDUCT'");
    $metrics["totalCreditsConsumed"] = abs((int)$stmt2->fetchColumn());

    // Total Candidatos Invitados
    $stmt3 = $pdo->query("SELECT COUNT(*) FROM evaluation_candidates");
    $metrics["totalCandidates"] = (int)$stmt3->fetchColumn();

    // Total Pruebas (Test individuales) Finalizadas
    $stmt4 = $pdo->query("SELECT COUNT(DISTINCT evaluationCandidateId, testKey) FROM candidate_answers");
    $metrics["totalTestsCompleted"] = (int)$stmt4->fetchColumn();

    Responder::success([
        "metrics" => $metrics
    ]);

} catch (Exception $e) {
    Responder::error("Error obteniendo métricas principales: " . $e->getMessage(), 500);
}
?>
