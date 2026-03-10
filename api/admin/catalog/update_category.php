<?php
// api/admin/catalog/update_category.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

AuthMiddleware::requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['category'])) {
    Responder::error("Faltan parámetros: id y category son obligatorios.", 400);
}

$allowedCategories = [
    "Inteligencia cognitiva",
    "Personalidad",
    "Habilidades",
    "Técnicas",
    "Idiomas",
    "Personalizadas"
];

if (!in_array($data['category'], $allowedCategories)) {
    Responder::error("Categoría no válida.", 400);
}

try {
    $stmt = $pdo->prepare("UPDATE catalog_tests SET category = ? WHERE id = ?");
    $stmt->execute([$data['category'], $data['id']]);

    if ($stmt->rowCount() > 0) {
        Responder::success(null, "Categoría actualizada correctamente.");
    }
    else {
        Responder::error("No se encontró la prueba o la categoría es la misma.", 404);
    }

}
catch (Exception $e) {
    Responder::error("Error actualizando categoría: " . $e->getMessage(), 500);
}
?>
