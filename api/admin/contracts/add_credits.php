<?php
// api/admin/contracts/add_credits.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

$adminData = AuthMiddleware::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['companyId'], $input['amount'])) {
    Responder::error("Faltan datos en la petición.", 400);
}

$companyId = $input['companyId'];
$amount = (int)$input['amount'];
$note = trim($input['note'] ?? 'Recarga manual de créditos');

if ($amount <= 0) {
    Responder::error("La cantidad de créditos debe ser mayor a 0.", 400);
}

try {
    $pdo->beginTransaction();

    // Verify company
    $stmtFind = $pdo->prepare("SELECT id, plan FROM companies WHERE id = ?");
    $stmtFind->execute([$companyId]);
    $company = $stmtFind->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("No se encontró la empresa.");
    }

    if ($company['plan'] === 'UNLIMITED') {
        throw new Exception("Esta empresa tiene un plan ILIMITADO, no necesita recarga de créditos.");
    }

    // Insert transaction
    $transId = uniqid('TR-');
    $stmtTrans = $pdo->prepare("INSERT INTO credit_transactions (id, companyId, amount, type, reason, adminId, createdAt) VALUES (?, ?, ?, 'CREDIT_ADD', ?, ?, NOW())");
    $stmtTrans->execute([$transId, $companyId, $amount, $note, $adminData['id']]);

    $pdo->commit();

    Responder::success([
        "message" => "Créditos agregados exitosamente"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Responder::error("Ocurrió un error: " . $e->getMessage(), 500);
}
?>
