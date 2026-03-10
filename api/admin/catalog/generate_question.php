<?php
// api/admin/catalog/generate_question.php

require_once '../../config/db.php';
require_once '../../utils/Responder.php';
require_once '../../utils/jwt.php';

Responder::setupCORS();

try {
    // 1. Authenticate Request
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        throw new Exception("Token no proporcionado o inválido.", 401);
    }

    $token = $matches[1];
    $decoded = JWT::decode($token);

    if (!$decoded || ($decoded['role'] ?? '') !== 'admin') {
        throw new Exception("No autorizado para generar preguntas.", 403);
    }

    // 2. Parse Input
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);

    if (!$data || !isset($data['topic']) || !isset($data['type'])) {
        throw new Exception("Datos de entrada inválidos. Faltan topic o type.", 400);
    }

    $topic = trim($data['topic']);
    $type = trim($data['type']);
    $testId = isset($data['testId']) ? $data['testId'] : null;

    if (empty($topic)) {
        throw new Exception("El tema (topic) no puede estar vacío.", 400);
    }

    // 3. Load API Key
    $aiConfig = require '../../config/ai.php';
    $apiKey = $aiConfig['gemini']['api_key'];
    $model = $aiConfig['gemini']['model'];

    if (empty($apiKey)) {
        throw new Exception("Ajustes de IA no configurados en el servidor.", 500);
    }

    // 4. Fetch Existing Questions (Context for Deduplication)
    $existingContext = "";
    if ($testId) {
        $stmt = $pdo->prepare("SELECT IFNULL(questionText, question) AS q FROM catalog_questions WHERE testId = :testId OR testKey = :testId");
        $stmt->bindParam(':testId', $testId);
        $stmt->execute();
        $existingQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($existingQuestions) > 0) {
            $existingContext = "El examen actualmente YA TIENE las siguientes preguntas. POR FAVOR, NO generes una pregunta que sea igual o demasiado similar a ninguna de estas:\n";
            foreach ($existingQuestions as $i => $q) {
                $existingContext .= ($i + 1) . ". " . $q['q'] . "\n";
            }
        }
    }

    // 5. Build the Prompt
    $typeInstruction = "";
    switch ($type) {
        case 'MULTIPLE_CHOICE':
        case 'SINGLE_CHOICE':
            $typeInstruction = "Debes generar 4 opciones de respuesta y especificar cuál (o cuáles si es Múltiple) es la correcta como string (debe coincidir exactamente con el texto de la opción).";
            break;
        case 'TRUE_FALSE':
            $typeInstruction = "Las opciones deben ser estrictamente 'Verdadero' y 'Falso'. Indica la correcta.";
            break;
        case 'OPEN_ENDED':
        case 'TEXT':
            $typeInstruction = "No generes opciones. Las opciones deben ser un array vacío [], y correctAnswer un string vacío ''.";
            break;
        default:
            throw new Exception("Tipo de pregunta no soportado por la IA.", 400);
    }

    $prompt = "Eres un experto en la creación de exámenes y pruebas psicométricas/técnicas.\n";
    $prompt .= "Genera estrictamente UNA (1) pregunta en español sobre el siguiente tema: '$topic'.\n";
    $prompt .= "Instrucciones de formato: $typeInstruction\n\n";

    if (!empty($existingContext)) {
        $prompt .= "MUY IMPORTANTE - CONTEXTO PARA EVITAR DUPLICADOS:\n";
        $prompt .= $existingContext . "\n\n";
    }

    $prompt .= "TU RESPUESTA DEBE SER ESTRICTAMENTE UN OBJETO JSON VÁLIDO CON LA SIGUIENTE ESTRUCTURA, SIN TEXTO ADICIONAL ANTES NI DESPUÉS (sin backticks de markdown):
{
  \"question\": \"El texto de la pregunta aquí\",
  \"options\": [\"Opción 1\", \"Opción 2\", ...],
  \"correctAnswer\": \"El texto de la respuesta correcta aquí\"
}";

    // 6. Call Gemini API
    $ch = curl_init();
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7, // Some creativity, but mostly predictable
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => 1024,
        ]
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception("Error conectando con la IA: " . curl_error($ch), 500);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("Error de procesamiento en la IA (Status $httpCode).", 500);
    }

    // 7. Parse AI Response
    $geminiData = json_decode($response, true);

    if (!isset($geminiData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("No se pudo extraer la respuesta de la IA.", 500);
    }

    $aiText = $geminiData['candidates'][0]['content']['parts'][0]['text'];

    // Clean potential markdown blocks like ```json ... ```
    $aiText = preg_replace('/```json/i', '', $aiText);
    $aiText = preg_replace('/```/', '', $aiText);
    $aiText = trim($aiText);

    $parsedQuestion = json_decode($aiText, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($parsedQuestion['question'])) {
        throw new Exception("La IA no devolvió un formato JSON válido.", 500);
    }

    // Normalize output
    $options = isset($parsedQuestion['options']) && is_array($parsedQuestion['options']) ? $parsedQuestion['options'] : [];
    $correctAnswer = isset($parsedQuestion['correctAnswer']) ? $parsedQuestion['correctAnswer'] : null;

    Responder::success([
        'question' => $parsedQuestion['question'],
        'options' => $options,
        'correctAnswer' => $correctAnswer,
        'raw_text' => $aiText // useful for debugging
    ], "Pregunta generada con éxito");

}
catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code >= 600)
        $code = 400; // default back to 400 if PDO/Exception throws weird codes
    Responder::error($e->getMessage(), $code);
}
