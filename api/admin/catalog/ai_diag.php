<?php
// api/admin/catalog/ai_diag.php
// HERRAMIENTA DE DIAGNÓSTICO PARA GEMINI
require_once '../../config/db.php';
require_once '../../config/env.php';
require_once '../../utils/Responder.php';

$aiConfig = require '../../config/ai.php';
$apiKey = $aiConfig['gemini']['api_key'];

if (empty($apiKey)) {
    echo "ERROR: API Key no configurada.";
    exit;
}

echo "--- DIAGNÓSTICO DE GEMINI ---\n";
echo "API Key (primeros 5): " . substr($apiKey, 0, 5) . "...\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "ERROR API ($httpCode): $result\n";
    exit;
}

$data = json_decode($result, true);
echo "MODELOS DISPONIBLES:\n";
foreach ($data['models'] as $m) {
    if (strpos($m['name'], 'gemini') !== false) {
        echo "- " . $m['name'] . " (Soporta: " . implode(', ', $m['supportedGenerationMethods']) . ")\n";
    }
}
?>
