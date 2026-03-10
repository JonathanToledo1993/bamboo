<?php
// api/client/profiles/create.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

// Proteger la ruta
$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['name'])) {
    Responder::error("El nombre del perfil es requerido.");
}

$name = trim($data['name']);
$hierarchy = trim($data['hierarchy'] ?? '');
$area = trim($data['area'] ?? '');

$testsArray = $data['tests'] ?? [];
$totalMinutes = (int)($data['totalDurationMins'] ?? 0);

try {
    $pdo->beginTransaction();

    $profileId = 'prof_' . uniqid();

    // Insertar Perfil Maestro
    $sql = "
        INSERT INTO profiles (id, companyId, name, hierarchy, area, testKeys, totalMinutes, creatorId, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $profileId,
        $companyId,
        $name,
        $hierarchy,
        $area,
        json_encode([]), // Default empty json array to avoid 1364 Error
        $totalMinutes,
        $clientData['id'] // creatorId
    ]);

    // Insertar relación Pivot
    if (!empty($testsArray)) {
        $stmtPivot = $pdo->prepare("INSERT INTO profile_tests (profileId, testId, isCustom) VALUES (?, ?, ?)");
        foreach ($testsArray as $t) {
            $testId = $t['id'];
            $isCustom = (int)$t['isCustom'];
            $stmtPivot->execute([$profileId, $testId, $isCustom]);
        }
    }

    $pdo->commit();

    Responder::success([
        "profile" => [
            "id" => $profileId,
            "name" => $name,
            "hierarchy" => $hierarchy,
            "area" => $area,
            "totalDurationMins" => $totalMinutes,
            "assignedTests" => count($testsArray)
        ]
    ], "Perfil Creado Exitosamente.");

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Responder::error("Error guardando el perfil: " . $e->getMessage(), 500);
}
?>
