<?php
// api/client/report_download.php
require_once '../config/db.php';
require_once '../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

// Requerir Sesión Cliente
$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];
$userId = $clientData['id'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['inviteId'])) {
    Responder::error("Falta el ID del candidato/invitación.");
}

$inviteId = $data['inviteId'];

try {
    // Iniciar Transacción SQL para asegurar consistencia del Kardex
    $pdo->beginTransaction();

    // 1. Obtener Plan actual y Balance de Créditos
    $sqlCompany = "
        SELECT c.id, c.plan,
            (
                SELECT COALESCE(SUM(amount), 0) FROM credit_transactions ct 
                WHERE ct.companyId = c.id AND ct.type = 'CREDIT_ADD'
            ) - (
                SELECT COALESCE(SUM(amount), 0) FROM credit_transactions ct 
                WHERE ct.companyId = c.id AND ct.type = 'CREDIT_USE'
            ) as actualCredits
        FROM companies c
        WHERE c.id = ? FOR UPDATE
    ";

    $stmtC = $pdo->prepare($sqlCompany);
    $stmtC->execute([$companyId]);
    $company = $stmtC->fetch();

    if (!$company) {
        throw new Exception("Empresa no encontrada.");
    }

    $isPayPerUse = ($company['plan'] === 'PAY_PER_USE');

    // 2. Si es PAY_PER_USE, verificar fondos y descontar
    if ($isPayPerUse) {
        if ((int)$company['actualCredits'] < 1) {
            throw new Exception("Fondos insuficientes. No tienes créditos para descargar este reporte.");
        }

        // Registrar Descuento (CREDIT_USE)
        $useId = 'txn_use_' . uniqid();
        $sqlUse = "
            INSERT INTO credit_transactions (id, companyId, amount, type, reason, adminId, reportId, createdAt)
            VALUES (?, ?, 1, 'CREDIT_USE', 'Consumo por Reporte Desbloqueado', ?, ?, NOW())
        ";
        $stmtUse = $pdo->prepare($sqlUse);
        // Note: adminId is null here since it was a client action
        $stmtUse->execute([$useId, $companyId, null, $inviteId]);
    }

    // 3. Registrar el suceso en ReportDownload (Kardex histórico de reportes)
    // Usaremos inviteId as the candidateId for mock compatibility
    $downId = 'rep_' . uniqid();
    $sqlRep = "
        INSERT INTO report_downloads (id, evaluationId, candidateId, companyId, userId, costCredits, ipAddress, userAgent, createdAt)
        VALUES (?, 'eval_mock', ?, ?, ?, ?, ?, ?, NOW())
    ";

    $cost = $isPayPerUse ? 1 : 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmtRep = $pdo->prepare($sqlRep);
    $stmtRep->execute([
        $downId,
        $inviteId,
        $companyId,
        $userId,
        $cost,
        $ip,
        $ua
    ]);

    // Commit de la Transacción Segura
    $pdo->commit();

    Responder::success([
        "reportLink" => "/assets/reports/report_ok_demo.pdf", // Link Simulado a futuro archivo real
        "saldoRestante" => $isPayPerUse ? ((int)$company['actualCredits'] - 1) : 'Ilimitado'
    ], "Reporte desbloqueado exitosamente.");

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Responder::error($e->getMessage(), 400); // 400 Bad Request if insuficient funds
}
?>
