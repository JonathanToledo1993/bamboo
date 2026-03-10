<?php
// api/admin/catalog/ai_rebuild_questions.php
// RECONSTRUCCIÓN INTELIGENTE MASIVA CON IA.
// Este script analiza todas las preguntas y decide si deben ser reescritas por falta de coherencia.

require_once '../../config/db.php';
require_once '../../config/env.php';
require_once '../../utils/Responder.php';

Responder::setupCORS();

// Aumentar tiempo de ejecución para proceso masivo
set_time_limit(300);


try {
    // 1. Cargar Configuración de IA desde .env
    $aiConfig = require '../../config/ai.php';
    $apiKey = $aiConfig['gemini']['api_key'];
    $model = $aiConfig['gemini']['model'];

    if (empty($apiKey))
        throw new Exception("API Key de Gemini no configurada en .env");

    // 2. Obtener preguntas para analizar
    // Priorizamos preguntas que tengan opciones tipo Likert ("Muy en desacuerdo") pero que sean de tests de conocimiento o situacionales.
    // También procesamos preguntas con updatedAt NULL o antiguas.

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
    $forceAll = isset($_GET['force_all']) && $_GET['force_all'] == '1';

    $query = "
        SELECT q.id, q.testId, q.questionText, q.options, q.correctAnswer, t.name as testName 
        FROM catalog_questions q
        LEFT JOIN catalog_tests t ON (q.testId = t.id OR q.testId = t.key)
        WHERE (q.updatedAt IS NULL OR q.updatedAt < '2025-03-01' OR q.options LIKE '%Muy en desacuerdo%')
    ";

    // Si no es un sweep masivo, limitamos para evitar timeouts de la IA
    $query .= " LIMIT $limit";

    $stmt = $pdo->query($query);
    $questions = $stmt->fetchAll();

    if (count($questions) === 0) {
        Responder::success([], "No hay más preguntas que requieran análisis de coherencia.");
        exit;
    }

    $updatedCount = 0;
    $errors = [];

    foreach ($questions as $q) {
        $currentOpts = $q['options'];
        $testName = $q['testName'];

        $prompt = "Eres un experto en psicometría, reclutamiento y evaluación de talento.\n";
        $prompt .= "Estás auditando un banco de preguntas para la prueba: '{$testName}'\n";
        $prompt .= "PREGUNTA: \"{$q['questionText']}\"\n";
        $prompt .= "OPCIONES ACTUALES: \"$currentOpts\"\n\n";

        $prompt .= "PROBLEMA DETECTADO:\n";
        $prompt .= "Muchas preguntas tienen opciones genéricas como 'Muy en desacuerdo'/'Muy en acuerdo' que NO TIENEN SENTIDO para preguntas de conocimiento (matemáticas, lógica, inglés) o situaciones específicas de ventas/comportamiento.\n\n";

        $prompt .= "INSTRUCCIONES DE RECONSTRUCCIÓN:\n";
        $prompt .= "1. IDENTIFICA EL TIPO DE TEST: \n";
        $prompt .= "   - Si es 'Inteligencia', 'Lógica', 'Matemática' o 'Inglés': Debes crear 4 opciones de respuesta cerradas (A, B, C, D) donde solo una sea correcta.\n";
        $prompt .= "   - Si es 'Ventas', 'DISC' o 'Situacional': Debes crear 4 opciones de comportamiento realistas. NO USES escalas de acuerdo/desacuerdo a menos que sea un test de personalidad puro (ej. Big Five).\n";
        $prompt .= "2. COHERENCIA: Las opciones deben ser coherentes con la pregunta.\n";
        $prompt .= "3. RESPUESTA CORRECTA: Identifica cuál de las nuevas opciones es la correcta.\n";
        $prompt .= "4. FORMATO: Devuelve SOLO un objeto JSON válido con esta estructura exacta:\n";
        $prompt .= "{\"options\": [\"Opción 1\", \"Opción 2\", \"Opción 3\", \"Opción 4\"], \"correctAnswer\": \"Texto exacto de la opción correcta\"}";

        // Si el modelo en .env es el antiguo 'gemini-1.5-flash' (que causa 404), forzamos el uso de 'gemini-2.5-flash'
        $targetModel = ($model === 'gemini-1.5-flash' || empty($model)) ? 'gemini-2.5-flash' : $model;
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$apiKey}";

        $payload = json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => ["response_mime_type" => "application/json"]
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos por pregunta

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $resData = json_decode($result, true);
        curl_close($ch);

        if ($httpCode === 429) {
            // Error de Cuota (429). Devolvemos error al Responder para que el frontend espere.
            $msg = $resData['error']['message'] ?? 'Cuota de API Gemini excedida (429).';
            Responder::error($msg, 429, [
                'rebuilt_count' => $updatedCount,
                'errors' => $errors,
                'has_more' => true
            ]);
            exit;
        }

        if ($httpCode !== 200) {
            $errors[] = "Error API ($httpCode) en ID {$q['id']}: " . ($resData['error']['message'] ?? 'Unknown');
            continue;
        }

        $aiText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($aiText) {
            $parsed = json_decode($aiText, true);

            if ($parsed && isset($parsed['options']) && is_array($parsed['options'])) {
                $optionsJson = json_encode($parsed['options'], JSON_UNESCAPED_UNICODE);
                $upd = $pdo->prepare("UPDATE catalog_questions SET options = ?, correctAnswer = ?, updatedAt = NOW() WHERE id = ?");
                $upd->execute([$optionsJson, $parsed['correctAnswer'], $q['id']]);
                $updatedCount++;
            }
            else {
                $errors[] = "Error JSON de IA en ID {$q['id']}";
            }
        }
        else {
            $errors[] = "IA vacía en ID {$q['id']}";
        }
    }

    Responder::success([
        'batch_size' => count($questions),
        'rebuilt_count' => $updatedCount,
        'errors' => $errors,
        'has_more' => count($questions) >= $limit
    ], "Proceso completado. $updatedCount preguntas saneadas.");

}
catch (Exception $e) {
    Responder::error($e->getMessage(), 500);
}
?>
