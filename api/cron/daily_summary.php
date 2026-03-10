<?php
// api/cron/daily_summary.php
// Este script debe ser ejecutado diariamente por un cron job (ej. a las 00:00)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/Mailer.php';

// Asegurar ejecución solo desde CLI o IP permitida si aplica
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'cron_secure_token')) {
    http_response_code(403);
    die("Access denied.");
}

try {
    $currentHour = (int)date('H');

    // 1. Encontrar usuarios con recordatorio diario activado para estA HORA
    $stmtUsers = $pdo->prepare("
        SELECT u.id as userId, u.email, c.id as companyId, c.name as companyName 
        FROM users u
        JOIN notification_settings ns ON u.id = ns.userId
        JOIN companies c ON u.companyId = c.id
        WHERE ns.dailySummary = 1 AND ns.dailySummaryTime = ? AND u.isActive = 1
    ");
    $stmtUsers->execute([$currentHour]);
    $usersToNotify = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $sentCount = 0;

    foreach ($usersToNotify as $user) {
        $userId = $user['userId'];
        
        // 2. Buscar evaluaciones completadas en las últimas 24 hrs para este usuario
        $stmtEval = $pdo->prepare("
            SELECT cand.firstName, cand.lastName, cand.email, eval.cargo, ec.scoreGlobal, ec.completedAt
            FROM evaluation_candidates ec
            JOIN evaluations eval ON ec.evaluationId = eval.id
            JOIN candidates cand ON ec.candidateId = cand.id
            WHERE eval.creatorId = ? 
              AND ec.status = 'COMPLETED' 
              AND ec.completedAt >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY ec.completedAt DESC
        ");
        
        $stmtEval->execute([$userId]);
        $completeds = $stmtEval->fetchAll(PDO::FETCH_ASSOC);

        if (count($completeds) > 0) {
            // Construir HTML del resumen
            $html = "<table style='width:100%; border-collapse:collapse; text-align:left; font-family:sans-serif; font-size:14px;'>";
            $html .= "<tr style='background-color:#f4f7f9; border-bottom:2px solid #ddd;'>";
            $html .= "<th style='padding:10px;'>Postulante</th>";
            $html .= "<th style='padding:10px;'>Cargo Evaluado</th>";
            $html .= "<th style='padding:10px;'>Score Global</th>";
            $html .= "<th style='padding:10px;'>Completado</th>";
            $html .= "</tr>";

            foreach ($completeds as $c) {
                $score = ($c['scoreGlobal'] !== null) ? round($c['scoreGlobal']) . "%" : "N/A";
                $color = ($c['scoreGlobal'] >= 70) ? "color:green; font-weight:bold;" : "color:#333;";
                
                $html .= "<tr style='border-bottom:1px solid #eee;'>";
                $html .= "<td style='padding:10px;'>{$c['firstName']} {$c['lastName']} <br><small style='color:#777'>{$c['email']}</small></td>";
                $html .= "<td style='padding:10px;'>{$c['cargo']}</td>";
                $html .= "<td style='padding:10px; $color'>{$score}</td>";
                $html .= "<td style='padding:10px;'>{$c['completedAt']}</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
            $html .= "<p style='margin-top:20px;'><a href='https://integritas.ec/app_admin/public/dashboard/index.html' style='display:inline-block; padding:10px 20px; background:#000; color:#fff; text-decoration:none; border-radius:5px;'>Ir al Dashboard</a></p>";

            // Enviar correo
            Mailer::sendDailySummary($user['email'], $user['companyName'], $html);
            $sentCount++;
        }
    }

    echo "Cron ejecutado exitosamente. Se enviaron $sentCount correos de resumen diario.\n";

} catch (Exception $e) {
    echo "Error ejecutando cron de resumen diario: " . $e->getMessage() . "\n";
}
?>
