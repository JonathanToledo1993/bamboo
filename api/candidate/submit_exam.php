<?php
// api/candidate/submit_exam.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Método no permitido."]));
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['token']) || empty($data['testId']) || empty($data['answers']) || !is_array($data['answers'])) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Faltan datos de envío o formato incorrecto."]));
}

$token = trim($data['token']);
$testId = trim($data['testId']);
$answers = $data['answers'];

try {
    // 1. Obtener candidate y verificar token
    $stmtCand = $pdo->prepare("SELECT id FROM evaluation_candidates WHERE secureToken = ? AND status != 'COMPLETED'");
    $stmtCand->execute([$token]);
    $candidate = $stmtCand->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        http_response_code(403);
        die(json_encode(["status" => "error", "message" => "Token inválido o evaluación ya completada."]));
    }

    $evaluationCandidateId = $candidate['id'];

    // 2. Obtener el testKey del testId
    $stmtTest = $pdo->prepare("SELECT `key` FROM catalog_tests WHERE id = ?");
    $stmtTest->execute([$testId]);
    $test = $stmtTest->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Prueba no encontrada."]));
    }
    $testKey = $test['key'];

    // 3. Verificar si la prueba ya fue enviada (idempotencia)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM candidate_answers WHERE evaluationCandidateId = ? AND testKey = ?");
    $stmtCheck->execute([$evaluationCandidateId, $testKey]);
    $alreadySubmitted = $stmtCheck->fetchColumn() > 0;

    if ($alreadySubmitted) {
        // Podríamos retornar error, o retornar success si fue un reintento del frontend. 
        // Retornaremos success por idempotencia.
        echo json_encode(["status" => "success", "message" => "Las respuestas ya habían sido guardadas."]);
        exit;
    }

    // 4. Iniciar transacción e insertar respuestas
    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare("INSERT INTO candidate_answers (id, evaluationCandidateId, testKey, questionId, answerData, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

    foreach ($answers as $ans) {
        if (!isset($ans['qId']) || !isset($ans['aId']))
            continue;

        $newId = uniqid('ca_', true); // Generar ID único usando uniqid
        // El frontend actualmente nos manda 'aId' como el ID de la respuesta selecionada.
        // Lo guardaremos dentro de answerData como JSON para cumplir con el esquema (longtext).
        $answerDataJson = json_encode(['answerId' => $ans['aId']]);

        $stmtInsert->execute([$newId, $evaluationCandidateId, $testKey, $ans['qId'], $answerDataJson]);
    }

    // 5. Store security metadata (fullscreen exits, speed data, invalidation)
    $securityMeta = [
        'fullscreenExits' => intval($data['fullscreenExits'] ?? 0),
        'invalidated' => boolval($data['invalidated'] ?? false),
        'invalidReason' => $data['reason'] ?? null,
        'answerTimestamps' => $data['answerTimestamps'] ?? []
    ];
    $metaId = uniqid('meta_', true);
    $stmtMeta = $pdo->prepare("INSERT INTO candidate_answers (id, evaluationCandidateId, testKey, questionId, answerData, createdAt, updatedAt) VALUES (?, ?, ?, '__SECURITY_META__', ?, NOW(), NOW())");
    $stmtMeta->execute([$metaId, $evaluationCandidateId, $testKey, json_encode($securityMeta)]);

    // If invalidated, mark the status
    if (!empty($data['invalidated'])) {
        $stmtInvalid = $pdo->prepare("UPDATE evaluation_candidates SET status = 'INVALIDATED', lastActivityAt = NOW() WHERE id = ?");
        $stmtInvalid->execute([$evaluationCandidateId]);
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Prueba invalidada por respuestas rápidas."]);
        exit;
    }

    // Actualizar última actividad del candidato
    $stmtUpdate = $pdo->prepare("UPDATE evaluation_candidates SET lastActivityAt = NOW() WHERE id = ?");
    $stmtUpdate->execute([$evaluationCandidateId]);

    // Verificar si ya completó todas las pruebas asignadas
    $stmtProfKey = $pdo->prepare("SELECT e.profileKey FROM evaluation_candidates ec INNER JOIN evaluations e ON ec.evaluationId = e.id WHERE ec.id = ?");
    $stmtProfKey->execute([$evaluationCandidateId]);
    $profileKey = $stmtProfKey->fetchColumn();

    $testsAssignedKeys = [];
    if (!empty($profileKey)) {
        $stmtPivot = $pdo->prepare("SELECT ct.`key` FROM profile_tests pt INNER JOIN catalog_tests ct ON pt.testId = ct.id WHERE pt.profileId = ?");
        $stmtPivot->execute([$profileKey]);
        $testsAssignedKeys = $stmtPivot->fetchAll(PDO::FETCH_COLUMN);

        if (empty($testsAssignedKeys)) {
            $stmtProf = $pdo->prepare("SELECT testKeys FROM profiles WHERE id = ?");
            $stmtProf->execute([$profileKey]);
            $profJson = $stmtProf->fetchColumn();
            if (!empty($profJson)) {
                $keysArr = json_decode($profJson, true);
                if (is_array($keysArr)) {
                    $testsAssignedKeys = $keysArr;
                }
            }
        }
    }

    if (!empty($testsAssignedKeys)) {
        $stmtDone = $pdo->prepare("SELECT DISTINCT testKey FROM candidate_answers WHERE evaluationCandidateId = ?");
        $stmtDone->execute([$evaluationCandidateId]);
        $doneKeys = $stmtDone->fetchAll(PDO::FETCH_COLUMN);

        $allDone = true;
        foreach ($testsAssignedKeys as $tk) {
            if (!in_array($tk, $doneKeys)) {
                $allDone = false;
                break;
            }
        }

        if ($allDone && count($testsAssignedKeys) > 0) {
            $stmtComplete = $pdo->prepare("UPDATE evaluation_candidates SET status = 'COMPLETED', completedAt = NOW() WHERE id = ?");
            $stmtComplete->execute([$evaluationCandidateId]);
        }
    }

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Respuestas guardadas exitosamente."]);

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error al guardar el examen: " . $e->getMessage()]);
}
?>
