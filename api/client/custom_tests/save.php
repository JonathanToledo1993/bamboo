<?php
// api/client/custom_tests/save.php
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

$name = trim($data['name'] ?? '');
$description = trim($data['description'] ?? '');
$isAiGenerated = isset($data['isAiGenerated']) ? (int)$data['isAiGenerated'] : 0;
$totalDuration = isset($data['totalDuration']) ? (int)$data['totalDuration'] : 0;
$testKeys = isset($data['testKeys']) ? json_encode($data['testKeys']) : json_encode([]);

if (empty($name)) {
    Responder::error("El nombre de la prueba es obligatorio.", 400);
}

$testId = 'ct_' . uniqid();

try {
    $sql = "
        INSERT INTO custom_tests (
            id, companyId, name, description, testKeys, totalDuration, 
            isAiGenerated, creatorId, createdAt, updatedAt
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $testId,
        $companyId,
        $name,
        $description,
        $testKeys,
        $totalDuration,
        $isAiGenerated,
        $userId
    ]);

    Responder::success([
        "testId" => $testId,
        "name" => $name
    ], "Prueba personalizada guardada con éxito.");

}
catch (Exception $e) {
    Responder::error("Error guardando la prueba: " . $e->getMessage(), 500);
}
?>
