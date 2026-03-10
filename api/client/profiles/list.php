<?php
// api/client/profiles/list.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

// Proteger la ruta
$clientData = AuthMiddleware::requireClient();
$companyId = $clientData['companyId'];

try {
    // Listar todos los perfiles de esta compañía
    $sql = "
        SELECT id, name, hierarchy, area, testKeys, totalMinutes, createdAt 
        FROM profiles 
        WHERE companyId = ? 
        ORDER BY createdAt DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    $profiles = $stmt->fetchAll();

    // Parsear el JSON testKeys para que el frontend lo reciba como array nativo
    $formattedProfiles = array_map(function ($profile) {
        $profile['testKeys'] = json_decode($profile['testKeys'], true);
        return $profile;
    }, $profiles);

    Responder::success([
        "profiles" => $formattedProfiles
    ], "Perfiles obtenidos.");

}
catch (Exception $e) {
    Responder::error("Error obteniendo perfiles: " . $e->getMessage(), 500);
}
?>
