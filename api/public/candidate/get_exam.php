<?php
// api/public/candidate/get_exam.php
// Devuelve las preguntas y opciones de una prueba específica para el candidato.
// NO devuelve el campo "score" ni "isCorrect" para evitar trampas.

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$token = trim($_GET['token'] ?? '');
$testId = trim($_GET['testId'] ?? '');

if (empty($token) || empty($testId)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Token y testId son obligatorios."]));
}

try {
    // 1. Validar token
    $sqlCand = "
        SELECT ec.id as ecId, ec.evaluationId, ec.status
        FROM evaluation_candidates ec
        WHERE ec.secureToken = ?
    ";
    $stmt = $pdo->prepare($sqlCand);
    $stmt->execute([$token]);
    $ec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ec) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Token inválido."]));
    }

    // 2. Traer info del test
    $stmtTest = $pdo->prepare("SELECT id, `key`, name, description, durationMins FROM catalog_tests WHERE id = ?");
    $stmtTest->execute([$testId]);
    $test = $stmtTest->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Prueba no encontrada."]));
    }

    // 3. Verificar si ya completó esta prueba
    $stmtChk = $pdo->prepare("SELECT COUNT(*) as cnt FROM candidate_answers WHERE evaluationCandidateId = ? AND testKey = ?");
    $stmtChk->execute([$ec['ecId'], $test['key']]);
    $chk = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if ((int)$chk['cnt'] > 0) {
        die(json_encode(["status" => "error", "message" => "Ya completaste esta prueba."]));
    }

    // 4. Traer preguntas ordenadas
    $stmtQ = $pdo->prepare("
        SELECT id, questionText, type, dimension, orderNum 
        FROM catalog_questions 
        WHERE testId = ? 
        ORDER BY orderNum ASC
    ");
    $stmtQ->execute([$testId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    // 5. Traer respuestas (sin score)
    $questionIds = array_column($questions, 'id');
    $answers = [];

    if (count($questionIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $stmtA = $pdo->prepare("SELECT id, questionId, text FROM catalog_answers WHERE questionId IN ($placeholders) ORDER BY questionId, id");
        $stmtA->execute($questionIds);
        $allAnswers = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allAnswers as $a) {
            $answers[$a['questionId']][] = [
                "id" => $a['id'],
                "text" => $a['text']
            ];
        }
    }

    // 6. Ensamblar
    $result = [];
    foreach ($questions as $q) {
        $result[] = [
            "id" => $q['id'],
            "questionText" => $q['questionText'],
            "type" => $q['type'],
            "options" => $answers[$q['id']] ?? []
        ];
    }

    // Actualizar status a IN_PROGRESS si era NEW
    if ($ec['status'] === 'NEW') {
        $pdo->prepare("UPDATE evaluation_candidates SET status = 'IN_PROGRESS', lastActivityAt = NOW() WHERE id = ?")->execute([$ec['ecId']]);
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "test" => [
                "id" => $test['id'],
                "key" => $test['key'],
                "name" => $test['name'],
                "description" => $test['description'],
                "durationMins" => (int)$test['durationMins'],
                "totalQuestions" => count($result)
            ],
            "questions" => $result
        ]
    ]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error del servidor: " . $e->getMessage()]);
}
?>
