<?php
// api/admin/catalog/create.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido.", 405);
}

// Proteger la ruta (Solo Admins)
$adminData = AuthMiddleware::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['name'], $input['category'], $input['description'], $input['durationMins'])) {
    Responder::error("Faltan datos obligatorios.", 400);
}

$name = trim($input['name']);
$category = trim($input['category']);
$description = trim($input['description']);
$duration = (int)$input['durationMins'];
// Generar un key amigable basado en el nombre (ej. "Prueba de Lógica" -> "prueba_de_logica")
$key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));

if (empty($name) || empty($category) || $duration <= 0) {
    Responder::error("Datos inválidos. Verifica el nombre, categoría y duración.", 400);
}

try {
    // Verificar si el key ya existe
    $stmtCheck = $pdo->prepare("SELECT id FROM catalog_tests WHERE `key` = ?");
    $stmtCheck->execute([$key]);
    if ($stmtCheck->fetchColumn()) {
        $key = $key . "_" . rand(100, 999); // Agregar random si ya existe
    }

    $id = uniqid('CT-');
    $sql = "INSERT INTO catalog_tests (id, `key`, name, category, description, durationMins, isActive) VALUES (?, ?, ?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $key, $name, $category, $description, $duration]);

    Responder::success([
        "message" => "Prueba global creada exitosamente.",
        "testId" => $id,
        "testKey" => $key
    ]);

} catch (Exception $e) {
    Responder::error("Error al crear prueba global: " . $e->getMessage(), 500);
}
?>
