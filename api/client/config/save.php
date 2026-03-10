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

            // Mail Simulator: En producción aquí despacharíamos SMTP mandrill/sendgrid
            break;

        case 'notifications':
            $emailOnEval = $data['emailOnEvalCompleted'] ?? true;
            $daily = $data['dailySummary'] ?? false;

            $stmt = $pdo->prepare("INSERT INTO notification_settings (id, userId, emailOnEvalCompleted, dailySummary) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE emailOnEvalCompleted=?, dailySummary=?");
            $nId = 'ns_' . uniqid();
            $stmt->execute([$nId, $userId, (int)$emailOnEval, (int)$daily, (int)$emailOnEval, (int)$daily]);
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
