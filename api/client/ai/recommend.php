<?php
// api/client/ai/recommend.php
// Usa Google Gemini para recomendar un paquete de pruebas basado en descripción del cargo.
// Soporta conversación multi-turno via historial enviado desde el frontend.

require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Responder::error("Método no permitido. Use POST.", 405);
}

$clientData = AuthMiddleware::requireClient();

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$jobDescription = trim($data['jobDescription'] ?? '');
$history = $data['history'] ?? []; // Array of [{role,text}]

if (empty($jobDescription)) {
    Responder::error("Debes ingresar una descripción del cargo.", 400);
}

// ==============================
//  CATALOG (DB + fallback)
// ==============================
try {
    $stmt = $pdo->query("SELECT id, `key`, name, category, description, durationMins FROM catalog_tests WHERE isActive = 1 ORDER BY name");
    $catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e) {
    $catalog = [];
}

// Built-in fallback catalog if DB is empty
if (empty($catalog)) {
    $catalog = [
        ["id" => "cat_001", "key" => "DISC", "name" => "DISC", "category" => "Personalidad", "description" => "Perfil de comportamiento conductual (Dominancia, Influencia, Estabilidad, Conciencia).", "durationMins" => 15],
        ["id" => "cat_002", "key" => "MACHIAVELISMO", "name" => "Maquiavelismo", "category" => "Personalidad", "description" => "Evalúa la tendencia a manipulación e inteligencia social.", "durationMins" => 12],
        ["id" => "cat_003", "key" => "LIDERAZGO", "name" => "Estilo de Liderazgo", "category" => "Competencias", "description" => "Identifica el estilo de liderazgo predominante del candidato.", "durationMins" => 20],
        ["id" => "cat_004", "key" => "INTELIGENCIA", "name" => "Inteligencia General (IQ)", "category" => "Cognitivo", "description" => "Mide capacidades cognitivas: razonamiento lógico, verbal y numérico.", "durationMins" => 30],
        ["id" => "cat_005", "key" => "VENTAS", "name" => "Aptitud Comercial", "category" => "Habilidades", "description" => "Mide habilidades de ventas, negociación y orientación al cliente.", "durationMins" => 18],
        ["id" => "cat_006", "key" => "ESTRES", "name" => "Tolerancia al Estrés", "category" => "Competencias", "description" => "Evalúa resiliencia y manejo bajo presión.", "durationMins" => 15],
        ["id" => "cat_007", "key" => "CREATIVIDAD", "name" => "Creatividad e Innovación", "category" => "Competencias", "description" => "Mide pensamiento lateral, innovación y resolución de problemas.", "durationMins" => 20],
        ["id" => "cat_008", "key" => "ATENCION", "name" => "Atención al Detalle", "category" => "Cognitivo", "description" => "Evalúa precisión, concentración y cometidos de detalle.", "durationMins" => 15],
        ["id" => "cat_009", "key" => "SERVICIO", "name" => "Orientación al Servicio", "category" => "Habilidades", "description" => "Mide empatía, escucha activa y vocación de servicio al cliente.", "durationMins" => 12],
        ["id" => "cat_010", "key" => "16PF", "name" => "16 Factores de Personalidad", "category" => "Personalidad", "description" => "Perfil de personalidad profundo con 16 dimensiones.", "durationMins" => 40],
    ];
}

// ==============================
//  BUILD GEMINI PROMPT
// ==============================
$catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

$systemInstruction = "Eres 'Kokoro AI', un experto en Recursos Humanos y psicología organizacional. "
    . "Tu objetivo es ayudar a los reclutadores a crear la batería de pruebas ideal para evaluar candidatos. "
    . "Responde siempre en español. Sé conciso, profesional y cálido. "
    . "Cuando recomiendes pruebas, usa ÚNICAMENTE las del siguiente catálogo: $catalogJson. "
    . "Al dar tu recomendación final, SIEMPRE incluye al final un bloque JSON con esta estructura exacta (entre triple backticks json): "
    . '```json{"bundleName":"Nombre","description":"Por qué este paquete","selectedTests":[{"id":"cat_001","key":"DISC","name":"DISC","reason":"Razón breve","durationMins":15}]}```'
    . " No incluyas ese bloque JSON hasta que tengas suficiente información para hacer una recomendación completa. "
    . "Primero haz preguntas de seguimiento si necesitas más contexto (área, nivel jerárquico, número de candidatos, cultura de empresa).";

// Build Gemini contents array (multi-turn)
$contents = [];

// Add system instruction as first user message
$contents[] = [
    "role" => "user",
    "parts" => [["text" => $systemInstruction]]
];
$contents[] = [
    "role" => "model",
    "parts" => [["text" => "Entendido. Estoy listo para ayudarte a diseñar la batería de pruebas perfecta. ¿Cuéntame sobre el cargo que deseas evaluar?"]]
];

// Add conversation history
foreach ($history as $msg) {
    $role = ($msg['role'] === 'user') ? 'user' : 'model';
    $contents[] = [
        "role" => $role,
        "parts" => [["text" => $msg['text']]]
    ];
}

// Add current message
$contents[] = [
    "role" => "user",
    "parts" => [["text" => $jobDescription]]
];

// ==============================
//  CALL GEMINI API
// ==============================
$geminiApiKey = 'AIzaSyDHoTEBjYQ7FKOs-oin4USshmVA7SbHhDc';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $geminiApiKey;

$payload = [
    "contents" => $contents,
    "generationConfig" => [
        "temperature" => 0.4,
        "maxOutputTokens" => 1024
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    $errBody = $response ? substr($response, 0, 500) : 'sin respuesta';
    Responder::error("Error Gemini AI HTTP $httpCode: $errBody", 500);
}

$geminiData = json_decode($response, true);
$replyText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$replyText) {
    Responder::error("El motor IA no devolvió respuesta.", 500);
}

// ==============================
//  PARSE BUNDLE (if present)
// ==============================
$recommendation = null;

if (preg_match('/```json\s*(\{.*?\})\s*```/s', $replyText, $matches)) {
    $parsedBundle = json_decode($matches[1], true);
    if ($parsedBundle && isset($parsedBundle['selectedTests']) && count($parsedBundle['selectedTests']) > 0) {
        $totalMins = array_sum(array_column($parsedBundle['selectedTests'], 'durationMins'));
        $recommendation = [
            "bundleName" => $parsedBundle['bundleName'] ?? 'Batería Personalizada',
            "description" => $parsedBundle['description'] ?? '',
            "totalMins" => $totalMins,
            "tests" => $parsedBundle['selectedTests']
        ];
    }
    // Remove the JSON block from the reply so the UI shows clean text
    $replyText = trim(preg_replace('/```json.*?```/s', '', $replyText));
}

Responder::success([
    "reply" => $replyText,
    "recommendation" => $recommendation // null = still gathering info, not null = ready to save
], "OK");
?>
