<?php
// api/admin/catalog/fix_correct_answers.php
// Script para asignar una "respuesta correcta" por defecto a las preguntas migradas 
// para que se resalten en verde en el listado.
require_once '../../config/db.php';
require_once '../../utils/Responder.php';

Responder::setupCORS();

try {
    $pdo->beginTransaction();

    // 1. Para pruebas psicométricas con Escala Likert, marcamos "Muy de acuerdo" como la respuesta objetivo
    // Esto es para que aparezca resaltado en el listado de administración.
    $targetLikert = "Muy de acuerdo";
    $likertTests = ['cat_1', 'cat_2', 'cat_3', 'cat_4', 'cat_5', 'ct_bigfive', 'ct_eq'];

    foreach ($likertTests as $tId) {
        $stmt = $pdo->prepare("UPDATE catalog_questions SET correctAnswer = :target WHERE testId = :tId AND (correctAnswer IS NULL OR correctAnswer = '')");
        $stmt->execute(['target' => $targetLikert, 'tId' => $tId]);
    }

    // 2. Para DISC, podríamos marcar "Siempre" como objetivo por defecto
    $pdo->prepare("UPDATE catalog_questions SET correctAnswer = 'Siempre' WHERE testId = 'ct_disc' AND (correctAnswer IS NULL OR correctAnswer = '')")
        ->execute();

    $pdo->commit();

    Responder::success([], "Se han asignado respuestas correctas por defecto ('Muy de acuerdo' / 'Siempre') para resaltar el listado.");

}
catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    Responder::error("Error: " . $e->getMessage(), 500);
}
?>
