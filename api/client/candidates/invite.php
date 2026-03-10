<?php
// api/client/candidates/invite.php
// Invita un candidato a una evaluación por email.
// Crea el candidato en la tabla `candidates` si no existe,
// luego crea la relación en `evaluation_candidates` y devuelve el token de acceso.

require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$evaluationId = trim($data['evaluationId'] ?? '');
$firstName = trim($data['firstName'] ?? '');
$lastName = trim($data['lastName'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));

if (empty($evaluationId) || empty($firstName) || empty($email)) {
    Responder::error("Evaluación, nombre y email son obligatorios.");
}

try {
    // 1. Verificar que la evaluación pertenece a esta empresa
    $chk = $pdo->prepare("SELECT id FROM evaluations WHERE id = ? AND companyId = ?");
    $chk->execute([$evaluationId, $companyId]);
    if (!$chk->fetch()) {
        Responder::error("Evaluación no encontrada.", 404);
    }

    // 2. Buscar o crear el candidato
    $findCand = $pdo->prepare("SELECT id FROM candidates WHERE companyId = ? AND email = ?");
    $findCand->execute([$companyId, $email]);
    $candidate = $findCand->fetch();

    if ($candidate) {
        $candidateId = $candidate['id'];
    }
    else {
        $candidateId = 'CAND-' . strtoupper(substr(md5(uniqid()), 0, 12));
        $ins = $pdo->prepare("INSERT INTO candidates (id, companyId, firstName, lastName, email, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->execute([$candidateId, $companyId, $firstName, $lastName, $email]);
    }

    // 3. Verificar si ya fue invitado a esta evaluación
    $chkEc = $pdo->prepare("SELECT id FROM evaluation_candidates WHERE evaluationId = ? AND candidateId = ?");
    $chkEc->execute([$evaluationId, $candidateId]);
    if ($chkEc->fetch()) {
        Responder::error("Este candidato ya fue invitado a esta evaluación.");
    }

    // 4. Crear la relación evaluation_candidates con un token seguro único
    $ecId = 'EC-' . strtoupper(substr(md5(uniqid()), 0, 12));
    $secureToken = bin2hex(random_bytes(24));

    $insEc = $pdo->prepare("
        INSERT INTO evaluation_candidates (id, evaluationId, candidateId, status, secureToken, createdAt)
        VALUES (?, ?, ?, 'NEW', ?, NOW())
    ");
    $insEc->execute([$ecId, $evaluationId, $candidateId, $secureToken]);

    // 5. (Opcional) Enviar correo — ahora se envía usando la plantilla
    // Obtener la url base dinámicamente o dejarla fija si es necesario
    $inviteLink = "https://integritas.ec/app_admin/public/candidate/test.html?st={$secureToken}";

    // Obtener la plantilla (prioridad: empresa, luego global)
    $stmtTpl = $pdo->prepare("SELECT subject, bodyHtml FROM email_templates WHERE `key` = 'invitation_eval' AND companyId IN (?, 'global') ORDER BY companyId DESC LIMIT 1");
    $stmtTpl->execute([$companyId]);
    $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);

    // Obtener info de la empresa y evaluación
    $stmtEval = $pdo->prepare("SELECT e.cargo, c.name FROM evaluations e JOIN companies c ON e.companyId = c.id WHERE e.id = ?");
    $stmtEval->execute([$evaluationId]);
    $evalInfo = $stmtEval->fetch(PDO::FETCH_ASSOC);

    require_once '../../utils/Mailer.php';
    Mailer::sendCandidateInvite(
        $email, 
        $firstName, 
        $evalInfo['name'], // companyName
        $evalInfo['cargo'], // evalName
        $inviteLink, 
        "No requiere contraseña por defecto", // Or whatever logic dictates
        $template
    );

    Responder::success([
        "candidateId" => $candidateId,
        "inviteToken" => $secureToken,
        "inviteLink" => $inviteLink
    ], "Candidato invitado correctamente.");

}
catch (Exception $e) {
    Responder::error("Error al invitar candidato: " . $e->getMessage(), 500);
}
?>
