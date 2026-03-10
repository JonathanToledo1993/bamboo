<?php
// api/admin/catalog/questions.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

AuthMiddleware::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Obtener preguntas de una prueba global
    if (!isset($_GET['testKey'])) {
        Responder::error("Se requiere el testKey.", 400);
    }
    
    $testKey = $_GET['testKey'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, testId as testKey, type, questionText as question, options, correctAnswer, points, isActive FROM catalog_questions WHERE testId = ? ORDER BY createdAt ASC");
        $stmt->execute([$testKey]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON de opciones
        foreach ($questions as &$q) {
            $q['options'] = json_decode($q['options'], true) ?: [];
        }
        
        Responder::success(["questions" => $questions]);
    } catch(Exception $e) {
        Responder::error("Error obteniendo preguntas: " . $e->getMessage(), 500);
    }

} elseif ($method === 'POST') {
    // Agregar nueva pregunta a la prueba
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['testKey'], $input['question'])) {
        Responder::error("Faltan datos obligatorios (testKey, question).", 400);
    }

    $testKey = $input['testKey'];
    $type = $input['type'] ?? 'MULTIPLE_CHOICE';
    $questionTxt = trim($input['question']);
    $optionsArr = $input['options'] ?? [];
    $correctAnswer = $input['correctAnswer'] ?? '';
    $points = (float)($input['points'] ?? 1);

    if (empty($questionTxt)) {
        Responder::error("La pregunta no puede estar vacía.", 400);
    }

    try {
        $id = uniqid('CQ-');
        $optsJson = json_encode($optionsArr);
        
        $sql = "INSERT INTO catalog_questions (id, testId, type, questionText, options, correctAnswer, points, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $testKey, $type, $questionTxt, $optsJson, $correctAnswer, $points]);

        // Retornar la pregunta creada
        $newQuestion = [
            "id" => $id,
            "testKey" => $testKey,
            "type" => $type,
            "question" => $questionTxt,
            "options" => $optionsArr,
            "correctAnswer" => $correctAnswer,
            "points" => $points,
            "isActive" => 1
        ];

        Responder::success(["message" => "Pregunta agregada.", "question" => $newQuestion]);

    } catch (Exception $e) {
        Responder::error("Error creando pregunta: " . $e->getMessage(), 500);
    }

} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) {
        Responder::error("ID de pregunta requerido.", 400);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM catalog_questions WHERE id = ?");
        $stmt->execute([$input['id']]);
        Responder::success(["message" => "Pregunta eliminada correctamente."]);
    } catch (Exception $e) {
        Responder::error("Error eliminando pregunta: " . $e->getMessage(), 500);
    }
} else {
    Responder::error("Método no permitido.", 405);
}
?>
