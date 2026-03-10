<?php
// api/client/config/get.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];
$userId = $clientData['id'];

try {
    // 1. Info Usuario (Mi Cuenta)
    $stmtU = $pdo->prepare("SELECT id, name as firstName, lastName, email, phone, emailPersonal, role, status FROM users WHERE id = ?");
    $stmtU->execute([$userId]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);

    // 2. Info Empresa (Mi Cuenta)
    $stmtC = $pdo->prepare("SELECT name, rfc, country, logoUrl FROM companies WHERE id = ?");
    $stmtC->execute([$companyId]);
    $company = $stmtC->fetch(PDO::FETCH_ASSOC);

    // 3. Info Equipo (Todos los usuarios de la misma empresa)
    $stmtTeam = $pdo->prepare("SELECT id, name as firstName, lastName, email as emailWork, phone, role, status FROM users WHERE companyId = ?");
    $stmtTeam->execute([$companyId]);
    $team = $stmtTeam->fetchAll(PDO::FETCH_ASSOC);

    // 4. Info Notificaciones
    $notif = [
        "emailOnEvalCompleted" => true,
        "dailySummary" => false
    ];
    $stmtN = $pdo->prepare("SELECT emailOnEvalCompleted, dailySummary FROM notification_settings WHERE userId = ?");
    $stmtN->execute([$userId]);
    $dbNotif = $stmtN->fetch(PDO::FETCH_ASSOC);

    if ($dbNotif) {
        $notif['emailOnEvalCompleted'] = (bool)$dbNotif['emailOnEvalCompleted'];
        $notif['dailySummary'] = (bool)$dbNotif['dailySummary'];
    }

    // 5. Plantillas de Correo
    $stmtTpl = $pdo->prepare("SELECT `key`, name, subject, bodyHtml FROM email_templates WHERE companyId = ? OR companyId = 'global'");
    $stmtTpl->execute([$companyId]);
    $templates = $stmtTpl->fetchAll(PDO::FETCH_ASSOC);

    Responder::success([
        "user" => $user,
        "company" => $company,
        "team" => $team,
        "notifications" => $notif,
        "templates" => $templates
    ], "Configuración obtenida.");

}
catch (Exception $e) {
    Responder::error("Error obteniendo configuración: " . $e->getMessage(), 500);
}
?>
