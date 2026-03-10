<?php
// api/candidate/upload_snapshot.php
// Receives base64 snapshot images from the candidate's camera during exams
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$token = $input['token'] ?? '';
$testId = $input['testId'] ?? '';
$snapshotIndex = intval($input['snapshotIndex'] ?? 0);
$imageData = $input['image'] ?? '';

if (empty($token) || empty($imageData)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Datos incompletos."]);
    exit;
}

// Create snapshots directory if it doesn't exist
$snapshotsDir = dirname(__DIR__, 2) . '/storage/snapshots/' . substr($token, 0, 16);
if (!is_dir($snapshotsDir)) {
    mkdir($snapshotsDir, 0755, true);
}

// Decode base64 image
$imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
$imageData = base64_decode($imageData);

if ($imageData === false) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Imagen inválida."]);
    exit;
}

// Save file
$filename = "snap_{$snapshotIndex}_" . date('Ymd_His') . ".jpg";
$filepath = $snapshotsDir . '/' . $filename;
file_put_contents($filepath, $imageData);

echo json_encode([
    "status" => "success",
    "message" => "Snapshot guardado.",
    "data" => [
        "snapshotIndex" => $snapshotIndex,
        "filename" => $filename
    ]
]);
?>
