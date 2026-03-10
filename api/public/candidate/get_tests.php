<?php
// api/public/candidate/get_tests.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Método no permitido."]));
}

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Token de seguridad no proporcionado."]));
}

try {
    // 1. Validar Token de Candidato
    $sqlCand = "
        SELECT ec.id as evaluationCandidateId, ec.evaluationId, ec.status, e.profileKey, e.cargo, c.firstName
        FROM evaluation_candidates ec
        INNER JOIN evaluations e ON ec.evaluationId = e.id
        INNER JOIN candidates c ON ec.candidateId = c.id
        WHERE ec.secureToken = ?
    ";
    $stmtCand = $pdo->prepare($sqlCand);
    $stmtCand->execute([$token]);
    $evalCand = $stmtCand->fetch(PDO::FETCH_ASSOC);

    if (!$evalCand) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Token inválido o evaluación no encontrada."]));
    }

    $evaluationId = $evalCand['evaluationId'];
    $profileKey = $evalCand['profileKey'];
    $evaluationCandidateId = $evalCand['evaluationCandidateId'];

    // 2. Obtener las Pruebas Asignadas al Perfil
    $testsAssigned = [];

    // Intento 1: Buscar por tabla pivot profile_tests
    if (!empty($profileKey)) {
        $sqlPivot = "
            SELECT ct.id, ct.`key`, ct.name, ct.description, ct.durationMins 
            FROM profile_tests pt
            INNER JOIN catalog_tests ct ON pt.testId = ct.id
            WHERE pt.profileId = ?
        ";
        $stmtPivot = $pdo->prepare($sqlPivot);
        $stmtPivot->execute([$profileKey]);
        $testsAssigned = $stmtPivot->fetchAll(PDO::FETCH_ASSOC);
    }

    // Intento 2: Compatibilidad hacia atrás desde JSON testKeys en profiles
    if (empty($testsAssigned) && !empty($profileKey)) {
        $sqlProf = "SELECT testKeys FROM profiles WHERE id = ?";
        $stmtProf = $pdo->prepare($sqlProf);
        $stmtProf->execute([$profileKey]);
        $prof = $stmtProf->fetch(PDO::FETCH_ASSOC);

        if ($prof && !empty($prof['testKeys'])) {
            $keys = json_decode($prof['testKeys'], true);
            if (is_array($keys) && count($keys) > 0) {
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $sqlTests = "SELECT id, `key`, name, description, durationMins FROM catalog_tests WHERE `key` IN ($placeholders)";
                $stmtTests = $pdo->prepare($sqlTests);
                $stmtTests->execute($keys);
                $testsAssigned = $stmtTests->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    // 3. Revisar el estado de cada prueba (si ya la completó)
    // Para simplificar, si hay al menos una respuesta para ese testKey, lo marcamos como completado
    $sqlAns = "SELECT testKey, COUNT(*) as ansCount FROM candidate_answers WHERE evaluationCandidateId = ? GROUP BY testKey";
    $stmtAns = $pdo->prepare($sqlAns);
    $stmtAns->execute([$evaluationCandidateId]);
    $ansMap = [];
    while ($row = $stmtAns->fetch(PDO::FETCH_ASSOC)) {
        $ansMap[$row['testKey']] = (int)$row['ansCount'];
    }

    $testsList = [];
    $allCompleted = true;

    foreach ($testsAssigned as $t) {
        $isCompleted = isset($ansMap[$t['key']]) && $ansMap[$t['key']] > 0;
        if (!$isCompleted) {
            $allCompleted = false;
        }
        $testsList[] = [
            "id" => $t['id'],
            "key" => $t['key'],
            "name" => $t['name'],
            "description" => $t['description'],
            "durationMins" => $t['durationMins'],
            "isCompleted" => $isCompleted
        ];
    }

    // Si todas están completadas y el status no es COMPLETED, podemos actualizarlo de una vez (opcional)
    if ($allCompleted && count($testsList) > 0 && $evalCand['status'] !== 'COMPLETED') {
        $upd = $pdo->prepare("UPDATE evaluation_candidates SET status = 'COMPLETED', completedAt = NOW() WHERE id = ?");
        $upd->execute([$evaluationCandidateId]);
        $evalCand['status'] = 'COMPLETED';
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "candidateFirstName" => $evalCand['firstName'],
            "cargo" => $evalCand['cargo'],
            "overallStatus" => $evalCand['status'], // NEW, IN_PROGRESS, COMPLETED
            "tests" => $testsList
        ]
    ]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error del servidor: " . $e->getMessage()]);
}
?>
