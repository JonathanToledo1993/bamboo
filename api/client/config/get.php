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
    // Verificamos si existe lastName/status antes de consultar
    $colsUser = "id, name as firstName, email, role, isActive as status";
    try {
        $pdo->query("SELECT lastName, phone, emailPersonal FROM users LIMIT 1");
        $colsUser = "id, name as firstName, lastName, email, phone, emailPersonal, role, isActive as status";
    } catch (Exception $e) {}

    $stmtU = $pdo->prepare("SELECT $colsUser FROM users WHERE id = ?");
    $stmtU->execute([$userId]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);

    // 2. Info Empresa (Mi Cuenta)
    $stmtC = $pdo->prepare("SELECT name, rfc, country, logoUrl FROM companies WHERE id = ?");
    $stmtC->execute([$companyId]);
    $company = $stmtC->fetch(PDO::FETCH_ASSOC);

    // 3. Info Equipo (Todos los usuarios de la misma empresa)
    $stmtTeam = $pdo->prepare("SELECT $colsUser FROM users WHERE companyId = ?");
    $stmtTeam->execute([$companyId]);
    $team = $stmtTeam->fetchAll(PDO::FETCH_ASSOC);

    // Obtener invitaciones pendientes
    try {
        $stmtInv = $pdo->prepare("SELECT id, '' as firstName, '' as lastName, email as emailWork, '' as phone, role, 'PENDING' as status FROM users_invitations WHERE companyId = ?");
        $stmtInv->execute([$companyId]);
        $invites = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
        if ($invites && count($invites) > 0) {
            foreach($invites as &$inv) {
                // Generar nombre temporal a partir de email
                $parts = explode('@', $inv['emailWork']);
                $inv['firstName'] = ucfirst($parts[0]);
            }
            $team = array_merge($team, $invites);
        }
    } catch(Exception $e) {}

    // Ejecutar migraciones automáticamente si no existen
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `email_templates` (
              `id` varchar(50) NOT NULL PRIMARY KEY,
              `companyId` varchar(50) NOT NULL,
              `key` varchar(50) NOT NULL,
              `name` varchar(100) NOT NULL,
              `subject` varchar(255) NOT NULL,
              `bodyHtml` text NOT NULL,
              `createdAt` timestamp NULL DEFAULT current_timestamp(),
              `updatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `notification_settings` (
              `id` varchar(50) NOT NULL PRIMARY KEY,
              `userId` varchar(50) NOT NULL,
              `emailOnEvalCompleted` tinyint(1) DEFAULT 1,
              `dailySummary` tinyint(1) DEFAULT 0,
              `dailySummaryTime` tinyint(2) DEFAULT 8,
              `createdAt` timestamp NULL DEFAULT current_timestamp(),
              `updatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              UNIQUE KEY `userId` (`userId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Agregar columna dailySummaryTime por si acaso se creó antes
        try {
            $pdo->exec("ALTER TABLE notification_settings ADD COLUMN dailySummaryTime TINYINT(2) DEFAULT 8 AFTER dailySummary;");
        } catch (PDOException $e) { } // Ignore if already exists

        // Agregar columns a users si no existen (haciendo fallback para la interfaz Mi Cuenta)
        try { $pdo->exec("ALTER TABLE users ADD COLUMN lastName varchar(150) NULL AFTER name"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN phone varchar(50) NULL AFTER email"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN emailPersonal varchar(150) NULL AFTER phone"); } catch(Exception $e){}
    } catch (Exception $e) {}

    // 4. Info Notificaciones
    $notif = [
        "emailOnEvalCompleted" => true,
        "dailySummary" => false,
        "dailySummaryTime" => 8
    ];
    $stmtN = $pdo->prepare("SELECT emailOnEvalCompleted, dailySummary, dailySummaryTime FROM notification_settings WHERE userId = ?");
    $stmtN->execute([$userId]);
    $dbNotif = $stmtN->fetch(PDO::FETCH_ASSOC);

    if ($dbNotif) {
        $notif['emailOnEvalCompleted'] = (bool)$dbNotif['emailOnEvalCompleted'];
        $notif['dailySummary'] = (bool)$dbNotif['dailySummary'];
        $notif['dailySummaryTime'] = (int)$dbNotif['dailySummaryTime'];
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
