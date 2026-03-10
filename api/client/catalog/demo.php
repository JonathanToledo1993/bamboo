<?php
// api/client/catalog/demo.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido. Use GET.", 405);
}

// Proteger la ruta
$user = AuthMiddleware::requireClient();
$companyId = $user['companyId'];

$testId = $_GET['testId'] ?? '';
$isCustom = $_GET['isCustom'] ?? '0';

if (empty($testId)) {
    Responder::error("El ID de la prueba (testId) es requerido.", 400);
}

try {
    if ($isCustom === '1') {
        // Fetch from custom tests
        $stmt = $pdo->prepare("SELECT id, name, 'Personalizadas' as category, totalDuration as durationMins FROM custom_tests WHERE id = ? AND companyId = ?");
        $stmt->execute([$testId, $companyId]);
        $test = $stmt->fetch();

        if (!$test)
            Responder::error("Prueba personalizada no encontrada.", 404);

        // Fetch demo limit 5 
        $stmtQ = $pdo->prepare("SELECT id, type, question as questionText FROM test_questions WHERE customTestId = ? LIMIT 5");
        $stmtQ->execute([$testId]);
        $questions = $stmtQ->fetchAll();

        foreach ($questions as &$q) {
            $stmtA = $pdo->prepare("SELECT id, text FROM question_answers WHERE questionId = ?");
            $stmtA->execute([$q['id']]);
            $q['options'] = $stmtA->fetchAll();
        }
    }
    else {
        // Fetch from catalog
        $stmt = $pdo->prepare("SELECT id, name, category, durationMins FROM catalog_tests WHERE id = ? AND isActive = 1");
        $stmt->execute([$testId]);
        $test = $stmt->fetch();

        if (!$test)
            Responder::error("Prueba no encontrada en el catálogo oficial.", 404);

        $stmtQ = $pdo->prepare("SELECT id, type, questionText FROM catalog_questions WHERE testId = ? LIMIT 5");
        $stmtQ->execute([$testId]);
        $questions = $stmtQ->fetchAll();

        foreach ($questions as &$q) {
            $stmtA = $pdo->prepare("SELECT id, text FROM catalog_answers WHERE questionId = ?");
            $stmtA->execute([$q['id']]);
            $q['options'] = $stmtA->fetchAll();
        }
    }

    // Verify if it has questions
    if (empty($questions)) {
        // Return fake dummy for custom generated ones without manual questions parsed yet
        $questions = [
            [
                "id" => "fake_1",
                "questionText" => "¿Esta prueba personalizada aún no tiene preguntas renderizables para la Demo. Agregue manualmente?",
                "options" => [
                    ["id" => "o1", "text" => "Entendido"]
                ]
            ]
        ];
    }

    Responder::success([
        "test" => $test,
        "questions" => $questions
    ], "Demo cargada exitosamente.");

}
catch (Exception $e) {
    Responder::error("Error cargando demo: " . $e->getMessage(), 500);
}
?>
