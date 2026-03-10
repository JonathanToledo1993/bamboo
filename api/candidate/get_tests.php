<?php
// api/candidate/get_tests.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Token no proporcionado."]));
}

try {
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
        die(json_encode(["status" => "error", "message" => "Token inválido."]));
    }

    $profileKey = $evalCand['profileKey'];
    $evaluationCandidateId = $evalCand['evaluationCandidateId'];

    $testsAssigned = [];
    if (!empty($profileKey)) {
        $sqlPivot = "SELECT ct.id, ct.`key`, ct.name, ct.description, ct.durationMins FROM profile_tests pt INNER JOIN catalog_tests ct ON pt.testId = ct.id WHERE pt.profileId = ?";
        $stmtPivot = $pdo->prepare($sqlPivot);
        $stmtPivot->execute([$profileKey]);
        $testsAssigned = $stmtPivot->fetchAll(PDO::FETCH_ASSOC);

        if (empty($testsAssigned)) {
            $stmtProf = $pdo->prepare("SELECT testKeys FROM profiles WHERE id = ?");
            $stmtProf->execute([$profileKey]);
            $prof = $stmtProf->fetch(PDO::FETCH_ASSOC);
            if ($prof && !empty($prof['testKeys'])) {
                $keys = json_decode($prof['testKeys'], true);
                if (is_array($keys) && count($keys) > 0) {
                    $placeholders = implode(',', array_fill(0, count($keys), '?'));
                    $stmtTests = $pdo->prepare("SELECT id, `key`, name, description, durationMins FROM catalog_tests WHERE `key` IN ($placeholders)");
                    $stmtTests->execute($keys);
                    $testsAssigned = $stmtTests->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
    }

    $stmtAns = $pdo->prepare("SELECT testKey FROM candidate_answers WHERE evaluationCandidateId = ?");
    $stmtAns->execute([$evaluationCandidateId]);
    $doneKeys = $stmtAns->fetchAll(PDO::FETCH_COLUMN);

    $testsList = [];
    foreach ($testsAssigned as $t) {
        $testsList[] = [
            "id" => $t['id'],
            "key" => $t['key'],
            "name" => $t['name'],
            "description" => $t['description'],
            "durationMins" => $t['durationMins'],
            "isCompleted" => in_array($t['key'], $doneKeys)
        ];
    }

    echo json_encode(["status" => "success", "data" => [
            "candidateFirstName" => $evalCand['firstName'],
            "cargo" => $evalCand['cargo'],
            "overallStatus" => $evalCand['status'],
            "tests" => $testsList
        ]]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
