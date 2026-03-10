<?php
// api/candidate/get_exam.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$token = trim($_GET['token'] ?? '');
$testId = trim($_GET['testId'] ?? '');

if (empty($token) || empty($testId)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Token y testId son obligatorios."]));
}

try {
    $stmt = $pdo->prepare("SELECT id, status FROM evaluation_candidates WHERE secureToken = ?");
    $stmt->execute([$token]);
    $ec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ec) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Token inválido."]));
    }

    $stmtTest = $pdo->prepare("SELECT id, `key`, name, description, durationMins FROM catalog_tests WHERE id = ?");
    $stmtTest->execute([$testId]);
    $test = $stmtTest->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Prueba no encontrada."]));
    }

    $stmtQ = $pdo->prepare("SELECT id, questionText, type, dimension, orderNum, options as optionsJson FROM catalog_questions WHERE testId = ? ORDER BY orderNum ASC, createdAt ASC");
    $stmtQ->execute([$testId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    // Also try with key fallback (same robust logic as questions.php)
    if (empty($questions)) {
        $stmtQ2 = $pdo->prepare("SELECT id, questionText, type, dimension, orderNum, options as optionsJson FROM catalog_questions WHERE testId = (SELECT `key` FROM catalog_tests WHERE id = ? LIMIT 1) OR testId = (SELECT id FROM catalog_tests WHERE `key` = ? LIMIT 1) ORDER BY orderNum ASC, createdAt ASC");
        $stmtQ2->execute([$testId, $testId]);
        $questions = $stmtQ2->fetchAll(PDO::FETCH_ASSOC);
    }

    $questionIds = array_column($questions, 'id');
    $answers = [];
    if (count($questionIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $stmtA = $pdo->prepare("SELECT id, questionId, text FROM catalog_answers WHERE questionId IN ($placeholders) ORDER BY questionId, id");
        $stmtA->execute($questionIds);
        $allAnswers = $stmtA->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allAnswers as $a) {
            $answers[$a['questionId']][] = ["id" => $a['id'], "text" => $a['text']];
        }
    }

    $result = [];
    foreach ($questions as $q) {
        // Use catalog_answers if available, otherwise fall back to JSON options column
        $opts = $answers[$q['id']] ?? [];
        if (empty($opts) && !empty($q['optionsJson'])) {
            $jsonOpts = json_decode($q['optionsJson'], true);
            if (is_array($jsonOpts)) {
                foreach ($jsonOpts as $i => $optItem) {
                    if (is_string($optItem)) {
                        $opts[] = ["id" => "opt_" . $i, "text" => $optItem];
                    }
                    elseif (is_array($optItem) && isset($optItem['text'])) {
                        $opts[] = ["id" => $optItem['id'] ?? ("opt_" . $i), "text" => $optItem['text']];
                    }
                }
            }
        }
        $result[] = ["id" => $q['id'], "questionText" => $q['questionText'], "type" => $q['type'], "options" => $opts];
    }

    if ($ec['status'] === 'NEW') {
        $pdo->prepare("UPDATE evaluation_candidates SET status = 'IN_PROGRESS', lastActivityAt = NOW() WHERE id = ?")->execute([$ec['id']]);
    }

    echo json_encode(["status" => "success", "data" => [
            "test" => ["id" => $test['id'], "key" => $test['key'], "name" => $test['name'], "description" => $test['description'], "durationMins" => (int)$test['durationMins'], "totalQuestions" => count($result)],
            "questions" => $result
        ]]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
