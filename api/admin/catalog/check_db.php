<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';

try {
    $stmt = $pdo->query("SELECT testId, COUNT(*) as q_count FROM catalog_questions GROUP BY testId");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = "<h2>Resumen de preguntas por prueba:</h2><ul>";
    foreach ($groups as $g) {
        $html .= "<li>Test ID: <b>" . $g['testId'] . "</b> -> " . $g['q_count'] . " preguntas</li>";
    }
    $html .= "</ul>";

    // Muestra también los catalog_tests para ver si hay conflicto de nombres
    $stmt_tests = $pdo->query("SELECT id FROM catalog_tests");
    $tests = $stmt_tests->fetchAll(PDO::FETCH_ASSOC);

    $html .= "<h2>Pruebas registradas en catalog_tests:</h2><ul>";
    foreach ($tests as $t) {
        $html .= "<li>" . $t['id'] . "</li>";
    }
    $html .= "</ul>";

    echo $html;

}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
