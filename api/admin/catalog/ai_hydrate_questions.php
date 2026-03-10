<?php
// api/admin/catalog/ai_hydrate_questions.php
// Script de HIDRATACIÓN MASIVA con IA.
// Este script lee preguntas vacías y usa Gemini para deducir las opciones y la respuesta correcta real.

require_once '../../config/db.php';
require_once '../../config/env.php';
require_once '../../utils/Responder.php';

Responder::setupCORS();

// Aumentar tiempo de ejecución para proceso masivo
set_time_limit(300);


try {
    // 1. Cargar Configuración de IA
    $aiConfig = require '../../config/ai.php';
    $apiKey = $aiConfig['gemini']['api_key'];
    $model = $aiConfig['gemini']['model'];

    if (empty($apiKey))
        throw new Exception("API Key de Gemini no configurada en .env");

    // 2. Obtener preguntas que necesitan hidratación
    // Solo tomamos un lote (batch) para no saturar la API en un solo hit
    $stmt = $pdo->query("
        SELECT q.id, q.testId, q.questionText, t.name as testName 
        FROM catalog_questions q
        LEFT JOIN catalog_tests t ON (q.testId = t.id OR q.testId = t.key)
        WHERE (q.options IS NULL OR q.options = '' OR q.options = 'null' OR q.correctAnswer IS NULL OR q.correctAnswer = '')
        LIMIT 20
    ");
    $questions = $stmt->fetchAll();

    if (count($questions) === 0) {
        Responder::success([], "No hay más preguntas pendientes de hidratación.");
        exit;
    }

    $updatedCount = 0;

    foreach ($questions as $q) {
        $prompt = "Eres un experto en psicometría y educación. Analiza la siguiente pregunta de la prueba llamada '{$q['testName']}':\n\n";
        $prompt .= "PREGUNTA: \"{$q['questionText']}\"\n\n";
        $prompt .= "Tus tareas:\n";
        $prompt .= "1. Si es una prueba de conocimiento (Inglés, Lógica, etc.), encuentra la respuesta correcta REAL.\n";
        $prompt .= "2. Genera 4 opciones de respuesta creíbles en el idioma de la pregunta.\n";
        $prompt .= "3. Devuelve estrictamente un JSON con esta estructura:\n";
        $prompt .= "{\"options\": [\"opcion1\", \"opcion2\", \"opcion3\", \"opcion4\"], \"correctAnswer\": \"el texto de la opcion correcta\"}";

        // Llamada a Gemini
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}");
        $payload = json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]]
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $resData = json_decode($response, true);
        curl_close($ch);

        $aiText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($aiText) {
            // Limpiar JSON
            $aiText = preg_replace('/```json/i', '', $aiText);
            $aiText = preg_replace('/```/', '', $aiText);
            $parsed = json_decode(trim($aiText), true);

            if ($parsed && isset($parsed['options'], $parsed['correctAnswer'])) {
                $upd = $pdo->prepare("UPDATE catalog_questions SET options = ?, correctAnswer = ?, updatedAt = NOW() WHERE id = ?");
                $upd->execute([json_encode($parsed['options']), $parsed['correctAnswer'], $q['id']]);
                $updatedCount++;
            }
        }
    }

    Responder::success([
        'processed' => count($questions),
        'updated' => $updatedCount,
        'remaining' => "Refresca la página para procesar el siguiente lote de 20"
    ], "Se han procesado $updatedCount preguntas con Inteligencia Artificial.");

}
catch (Exception $e) {
    Responder::error($e->getMessage(), 500);
}
?>
