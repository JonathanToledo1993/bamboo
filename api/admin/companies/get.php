<?php
// api/admin/companies/get.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido.", 405);
}

AuthMiddleware::requireAdmin();

if (!isset($_GET['id'])) {
    Responder::error("No se proporcionó el ID de la empresa.", 400);
}

$id = $_GET['id'];

try {
    // 1. Company Basic Info
    $stmtC = $pdo->prepare("SELECT id, name, rfc, country, plan, isActive, createdAt FROM companies WHERE id = ?");
    $stmtC->execute([$id]);
    $company = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        Responder::error("Empresa no encontrada.", 404);
    }

    // 2. Credits from companies table (direct column)
    $actualCredits = (int)($company['credits'] ?? 0);
    // Also add any credits from transactions that may not be reflected yet
    $stmtCredits = $pdo->prepare("
        SELECT 
            COALESCE((SELECT SUM(amount) FROM credit_transactions WHERE companyId = ? AND type = 'CREDIT_ADD'), 0) -
            COALESCE((SELECT SUM(amount) FROM credit_transactions WHERE companyId = ? AND type = 'CREDIT_USE'), 0)
    ");
    $stmtCredits->execute([$id, $id]);
    $txCredits = (int)$stmtCredits->fetchColumn();
    // Use transaction-based calculation if available, else fall back to company.credits
    if ($txCredits > 0) $actualCredits = $txCredits;

    $contract = [
        "plan" => $company['plan'],
        "actualCredits" => $actualCredits,
        "endDate" => "N/A"
    ];

    // 3. User 1 / Root Admin (CLIENT_ADMIN role is the top-level user per company)
    $stmtU = $pdo->prepare("SELECT id, name, role, email FROM users WHERE companyId = ? AND role = 'CLIENT_ADMIN' LIMIT 1");
    $stmtU->execute([$id]);
    $rootUser = $stmtU->fetch(PDO::FETCH_ASSOC);

    // Fallback: if no CLIENT_ADMIN found, grab first active user
    if (!$rootUser) {
        $stmtUFallback = $pdo->prepare("SELECT id, name, role, email FROM users WHERE companyId = ? AND isActive = 1 LIMIT 1");
        $stmtUFallback->execute([$id]);
        $rootUser = $stmtUFallback->fetch(PDO::FETCH_ASSOC);
    }

    // 4. Counts — evaluation_candidates has no companyId, join through evaluations
    $stmtTCount = $pdo->prepare("
        SELECT COUNT(*) FROM evaluation_candidates ec
        JOIN evaluations e ON ec.evaluationId = e.id
        WHERE e.companyId = ?
    ");
    $stmtTCount->execute([$id]);
    $candidatesCount = (int)$stmtTCount->fetchColumn();

    $payload = [
        "company" => $company,
        "contract" => $contract ?: ["plan" => "UNKNOWN", "actualCredits" => 0, "endDate" => "N/A"],
        "rootUser" => $rootUser ?: ["name" => "Sin Asignar", "email" => ""],
        "stats" => [
            "totalCandidates" => $candidatesCount
        ]
    ];

    Responder::success($payload);

} catch (Exception $e) {
    Responder::error("Error al obtener la información de la empresa: " . $e->getMessage(), 500);
}
?>
