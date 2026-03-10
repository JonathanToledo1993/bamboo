<?php
// api/public/candidate/register.php
// Endpoint PÚBLICO: el candidato se registra al abrir un link de invitación por evaluación.
// Crea el candidato en `candidates` y lo vincula a la evaluación en `evaluation_candidates`.

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/db.php';

if (isset($_GET['diag'])) {
    file_put_contents('diag.txt', 'OK - ' . date('Y-m-d H:i:s'));
    die(json_encode(["diag" => "written", "content" => file_get_contents('diag.txt')]));
}

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

$ok = function ($d, $m = '') {
    echo json_encode(["status" => "success", "data" => $d, "message" => $m]);
    exit;
};
$err = function ($m, $c = 400) {
    http_response_code($c);
    echo json_encode(["status" => "error", "message" => $m]);
    exit;
};

if (empty($evaluationId) || empty($firstName) || empty($email)) {
    $err("Nombre, email e ID de evaluación son obligatorios.");
}

try {
    // 1. Verificar que la evaluación existe y está activa
    $chkEval = $pdo->prepare("SELECT id, companyId FROM evaluations WHERE id = ? AND (archived = 0 OR archived IS NULL)");
    $chkEval->execute([$evaluationId]);
    $eval = $chkEval->fetch();
    if (!$eval)
        $err("Evaluación no encontrada o ya cerrada.", 404);

    $companyId = $eval['companyId'];

    // 2. Buscar o crear el candidato
    $findCand = $pdo->prepare("SELECT id FROM candidates WHERE companyId = ? AND email = ?");
    $findCand->execute([$companyId, $email]);
    $candidate = $findCand->fetch();

    if ($candidate) {
        $candidateId = $candidate['id'];
    }
    else {
        $candidateId = 'CAND-' . strtoupper(substr(md5(uniqid()), 0, 12));
        $ins = $pdo->prepare("INSERT INTO candidates (id, companyId, firstName, lastName, email, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->execute([$candidateId, $companyId, $firstName, $lastName, $email]);
    }

    // 3. Buscar o crear la relación evaluation_candidates
    $chkEc = $pdo->prepare("SELECT id, secureToken FROM evaluation_candidates WHERE evaluationId = ? AND candidateId = ?");
    $chkEc->execute([$evaluationId, $candidateId]);
    $ec = $chkEc->fetch();

    if ($ec) {
        // Ya registrado, devolver su token
        $ok(["secureToken" => $ec['secureToken'], "candidateId" => $candidateId], "Ya registrado.");
    }

    $ecId = 'EC-' . strtoupper(substr(md5(uniqid()), 0, 12));
    $secureToken = bin2hex(random_bytes(24));

    $insEc = $pdo->prepare("
        INSERT INTO evaluation_candidates (id, evaluationId, candidateId, status, secureToken, createdAt)
        VALUES (?, ?, ?, 'NEW', ?, NOW())
    ");
    $insEc->execute([$ecId, $evaluationId, $candidateId, $secureToken]);

    $ok(["secureToken" => $secureToken, "candidateId" => $candidateId], "Registro exitoso.");

}
catch (Exception $e) {
    $err("Error en el servidor: " . $e->getMessage(), 500);
}
?>
