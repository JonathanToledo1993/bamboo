<?php
// api/admin/companies/create.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

// Ensure caller is AdminOps
$adminData = AuthMiddleware::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['company'], $input['user'], $input['contract'])) {
    Responder::error("Faltan datos en la petición.", 400);
}

$cName    = trim($input['company']['name'] ?? '');
$cRfc     = trim($input['company']['rfc'] ?? '');
$cCountry = trim($input['company']['country'] ?? '');

$uName  = trim($input['user']['name'] ?? '');
$uEmail = strtolower(trim($input['user']['email'] ?? ''));
$uPass  = trim($input['user']['password'] ?? '');

// companies.plan ENUM: 'PAY_PER_USE' | 'UNLIMITED'
$rawPlan  = trim($input['contract']['planType'] ?? 'PAY_PER_USE');
$ctPlan   = ($rawPlan === 'UNLIMITED') ? 'UNLIMITED' : 'PAY_PER_USE';
$ctCredits = (int)($input['contract']['initialCredits'] ?? 50);

if (empty($cName) || empty($uName) || empty($uEmail) || empty($uPass)) {
    Responder::error("Por favor complete todos los campos obligatorios.", 400);
}

if (!filter_var($uEmail, FILTER_VALIDATE_EMAIL)) {
    Responder::error("El correo del administrador principal no es válido.", 400);
}

try {
    // Check if email already exists
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtCheck->execute([$uEmail]);
    if ($stmtCheck->fetchColumn()) {
        Responder::error("El correo ya está registrado en la plataforma.", 409);
    }

    $pdo->beginTransaction();

    // 1. Create Company
    $companyId = uniqid('CO-');
    $initialCredits = ($ctPlan === 'PAY_PER_USE') ? $ctCredits : 0;
    $stmtCompany = $pdo->prepare("
        INSERT INTO companies (id, name, rfc, country, plan, credits, isActive, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
    ");
    $stmtCompany->execute([$companyId, $cName, $cRfc, $cCountry, $ctPlan, $initialCredits]);

    // 2. Create Root User
    // users.role ENUM: 'CLIENT' | 'CLIENT_ADMIN' — use CLIENT_ADMIN for root user
    $userId  = uniqid('US-');
    $pwdHash = password_hash($uPass, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("
        INSERT INTO users (id, companyId, name, role, email, password, isActive, createdAt, updatedAt)
        VALUES (?, ?, ?, 'CLIENT_ADMIN', ?, ?, 1, NOW(), NOW())
    ");
    $stmtUser->execute([$userId, $companyId, $uName, $uEmail, $pwdHash]);

    require_once '../../utils/Mailer.php';
    Mailer::sendFirstTimeCredentials($uEmail, $uName, $uPass);

    // 3. Record Initial Credits transaction (only for PAY_PER_USE with credits > 0)
    if ($ctPlan === 'PAY_PER_USE' && $initialCredits > 0) {
        $transId = uniqid('TR-');
        $stmtTrans = $pdo->prepare("
            INSERT INTO credit_transactions (id, companyId, amount, type, reason, adminId, createdAt)
            VALUES (?, ?, ?, 'CREDIT_ADD', 'Créditos iniciales por apertura de cuenta', ?, NOW())
        ");
        $stmtTrans->execute([$transId, $companyId, $initialCredits, $adminData['id']]);
    }

    $pdo->commit();

    Responder::success([
        "message"   => "Empresa y usuario administrador creados exitosamente.",
        "companyId" => $companyId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in admin create company: " . $e->getMessage());
    Responder::error("Error interno: " . $e->getMessage(), 500);
}
?>
