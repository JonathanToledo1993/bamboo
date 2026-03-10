<?php
// api/candidate/register.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Método no permitido."]));
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$evaluationId = trim($data['evaluationId'] ?? '');
$firstName = trim($data['firstName'] ?? '');
$lastName = trim($data['lastName'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));

if (empty($evaluationId) || empty($firstName) || empty($email)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Nombre, email e ID de evaluación son obligatorios."]));
}

try {
    $chkEval = $pdo->prepare("SELECT id, companyId FROM evaluations WHERE id = ? AND (archived = 0 OR archived IS NULL)");
    $chkEval->execute([$evaluationId]);
    $eval = $chkEval->fetch();
    if (!$eval) {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Evaluación no encontrada."]));
    }

    $findCand = $pdo->prepare("SELECT id FROM candidates WHERE companyId = ? AND email = ?");
    $findCand->execute([$eval['companyId'], $email]);
    $candidate = $findCand->fetch();

    $candidateId = $candidate ? $candidate['id'] : 'CAND-' . strtoupper(substr(md5(uniqid()), 0, 12));
    if (!$candidate) {
        $pdo->prepare("INSERT INTO candidates (id, companyId, firstName, lastName, email, createdAt) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$candidateId, $eval['companyId'], $firstName, $lastName, $email]);
    }

    $chkEc = $pdo->prepare("SELECT secureToken FROM evaluation_candidates WHERE evaluationId = ? AND candidateId = ?");
    $chkEc->execute([$evaluationId, $candidateId]);
    $ec = $chkEc->fetch();

    if ($ec) {
        die(json_encode(["status" => "success", "data" => ["secureToken" => $ec['secureToken']]]));
    }

    $secureToken = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO evaluation_candidates (id, evaluationId, candidateId, status, secureToken, createdAt) VALUES (?, ?, ?, 'NEW', ?, NOW())")
        ->execute(['EC-' . strtoupper(substr(md5(uniqid()), 0, 12)), $evaluationId, $candidateId, $secureToken]);

    echo json_encode(["status" => "success", "data" => ["secureToken" => $secureToken]]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
