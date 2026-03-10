<?php
// api/client/config/save.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];
$userId = $clientData['id'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$action = $data['action'] ?? '';

try {
    // Prevent crashes if DB schema is outdated
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
    try { $pdo->exec("ALTER TABLE users ADD COLUMN lastName varchar(150) NULL AFTER name"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN phone varchar(50) NULL AFTER email"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN emailPersonal varchar(150) NULL AFTER phone"); } catch(Exception $e){}
} catch (Exception $e) {}

try {
    switch ($action) {
        case 'mi_cuenta':
            $user = $data['user'] ?? [];
            $company = $data['company'] ?? [];

            $stmtU = $pdo->prepare("UPDATE users SET name=?, lastName=?, phone=?, emailPersonal=?, updatedAt=NOW() WHERE id=?");
            $stmtU->execute([$user['firstName'] ?? '', $user['lastName'] ?? '', $user['phone'] ?? '', $user['emailPersonal'] ?? '', $userId]);

            $stmtC = $pdo->prepare("UPDATE companies SET name=?, rfc=?, country=?, updatedAt=NOW() WHERE id=?");
            $stmtC->execute([$company['name'] ?? '', $company['rfc'] ?? '', $company['country'] ?? '', $companyId]);
            break;

        case 'add_team':
            $member = $data['member'] ?? [];
            $email = trim($member['email'] ?? '');

            if (!$email)
                Responder::error("Email obligatorio.", 400);

            // Generar token y guardar en users_invitations
            $tkn = bin2hex(random_bytes(16));
            $invId = 'inv_' . uniqid();
            $stmt = $pdo->prepare("INSERT INTO users_invitations (id, companyId, email, role, token, expiresAt) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
            $stmt->execute([$invId, $companyId, $email, $member['role'] ?? 'recruiter', $tkn]);

            // Obtener el nombre de la empresa
            $stmtC = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $compName = $stmtC->fetchColumn() ?: "Tu Empresa";

            // Mail Simulator: En producción aquí despacharíamos SMTP mandrill/sendgrid
            $inviteLink = "https://integritas.ec/app_admin/public/login.html?action=setup&token={$tkn}";
            require_once '../../utils/Mailer.php';
            Mailer::sendTeamInvite($email, $member['firstName'] ?? 'Usuario', $compName, $inviteLink);
            break;

        case 'notifications':
            $emailOnEval = $data['emailOnEvalCompleted'] ?? true;
            $daily = $data['dailySummary'] ?? false;
            $time = $data['dailySummaryTime'] ?? 8;

            $stmt = $pdo->prepare("INSERT INTO notification_settings (id, userId, emailOnEvalCompleted, dailySummary, dailySummaryTime) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE emailOnEvalCompleted=?, dailySummary=?, dailySummaryTime=?");
            $nId = 'ns_' . uniqid();
            $stmt->execute([$nId, $userId, (int)$emailOnEval, (int)$daily, (int)$time, (int)$emailOnEval, (int)$daily, (int)$time]);
            break;

        case 'template':
            $key = $data['key'] ?? 'invitation_eval';
            $subject = $data['subject'] ?? '';
            $bodyHtml = $data['bodyHtml'] ?? '';

            $stmt = $pdo->prepare("INSERT INTO email_templates (id, companyId, `key`, name, subject, bodyHtml) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE subject=?, bodyHtml=?, updatedAt=NOW()");
            $tId = 'tpl_' . uniqid();
            $stmt->execute([$tId, $companyId, $key, 'Plantilla Personalizada', $subject, $bodyHtml, $subject, $bodyHtml]);
            break;

        default:
            Responder::error("Acción no válida.", 400);
    }

    Responder::success([], "Configuración guardada exitosamente.");

}
catch (Exception $e) {
    Responder::error("Error guardando configuración: " . $e->getMessage(), 500);
}
?>
