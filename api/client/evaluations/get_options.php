<?php
// api/client/evaluations/get_options.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

try {
    // 1. Fetch Job Profiles
    $stmt1 = $pdo->prepare("SELECT id, name, 'profile' as type FROM profiles WHERE companyId = ?");
    $stmt1->execute([$companyId]);
    $profiles = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Custom Tests (Manual and AI Generated Bundles)
    $stmt2 = $pdo->prepare("SELECT id, name, 'custom' as type FROM custom_tests WHERE companyId = ?");
    $stmt2->execute([$companyId]);
    $customTests = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Merge options for the dropdown grouping
    $options = [
        "profiles" => $profiles,
        "custom_tests" => $customTests
    ];

    Responder::success($options, "Opciones cargadas.");

}
catch (Exception $e) {
    Responder::error("Error obteniendo opciones: " . $e->getMessage(), 500);
}
?>
