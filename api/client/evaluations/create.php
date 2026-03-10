<?php
// api/client/evaluations/create.php
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

// Campos que envía el frontend
// profileId puede ser el ID de un perfil del catálogo que se usa como profileKey
$profileKey = trim($data['profileId'] ?? $data['profileKey'] ?? '');
$cargo = trim($data['cargo'] ?? '');
$confidential = !empty($data['isConfidential']) ? 1 : 0;
$expiresAt = !empty($data['expiresAt']) ? trim($data['expiresAt']) : null;
$language = trim($data['language'] ?? 'Español');

if (empty($profileKey) || empty($cargo)) {
    Responder::error("Perfil y Cargo son campos obligatorios.");
}

try {
    $evalId = 'EV-' . rand(10000, 99999);

    // Columnas reales según el schema Prisma de producción:
    // id, companyId, creatorId, cargo, profileKey, language, confidential, expiresAt, createdAt, updatedAt
    $sql = "
        INSERT INTO evaluations (id, companyId, creatorId, cargo, profileKey, language, confidential, expiresAt, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $evalId,
        $companyId,
        $clientData['id'], // creatorId
        $cargo,
        $profileKey,
        $language,
        $confidential,
        $expiresAt
    ]);

    Responder::success([
        "evaluationId" => $evalId
    ], "Evaluación creada exitosamente.");

}
catch (Exception $e) {
    Responder::error("Error guardando la evaluación: " . $e->getMessage(), 500);
}
?>
