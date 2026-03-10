<?php
// api/client/dashboard.php
require_once '../config/db.php';
require_once '../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido.", 405);
}

// Proteger la ruta (Requerir Cliente JWT)
$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

try {
    // 1. Obtener la Compañía y calcular saldo
    $sqlCompany = "
        SELECT c.id, c.name, c.plan,
            (
                SELECT COALESCE(SUM(amount), 0) FROM credit_transactions ct 
                WHERE ct.companyId = c.id AND ct.type = 'CREDIT_ADD'
            ) - (
                SELECT COALESCE(SUM(amount), 0) FROM credit_transactions ct 
                WHERE ct.companyId = c.id AND ct.type = 'CREDIT_USE'
            ) as actualCredits
        FROM companies c
        WHERE c.id = ? LIMIT 1
    ";

    $stmtC = $pdo->prepare($sqlCompany);
    $stmtC->execute([$companyId]);
    $company = $stmtC->fetch();

    if (!$company) {
        Responder::error("No se encontró la empresa asosiada a su cuenta.", 404);
    }

    $company['actualCredits'] = (int)$company['actualCredits'];

    // 2. Obtener Evaluaciones recientes enviadas por la empresa
    // Por simplicidad en la BD actual usaremos evaluation_invites 
    // pero como no tiene companyId se requerirá cruzar o obtener directo de la tabla de invites si la asociamos
    // (A falta de tabla de Evaluaciones completa, traemos las últimas invitaciones generales como Demo)
    $sqlEvals = "
        SELECT id, name, email, expiresAt, createdAt 
        FROM evaluation_invites 
        ORDER BY createdAt DESC LIMIT 10
    ";

    $stmtE = $pdo->query($sqlEvals);
    $evaluations = $stmtE->fetchAll();

    Responder::success([
        "company" => $company,
        "recent_evaluations" => $evaluations
    ], "Dashboard del Cliente Cargado.");

}
catch (Exception $e) {
    Responder::error("Error recopilando recursos: " . $e->getMessage(), 500);
}
?>
