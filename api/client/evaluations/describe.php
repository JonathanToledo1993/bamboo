<?php
// ARCHIVO TEMPORAL DE DIAGNÓSTICO - ELIMINAR DESPUÉS DE USAR
header("Content-Type: text/plain; charset=UTF-8");
require_once '../config/db.php';

// Eliminar este archivo después de obtener el resultado
try {
    $stmt = $pdo->query("DESCRIBE evaluations");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== Columnas de la tabla evaluations ===\n";
    foreach ($cols as $col) {
        echo $col['Field'] . " | " . $col['Type'] . " | Null: " . $col['Null'] . " | Key: " . $col['Key'] . " | Default: " . $col['Default'] . "\n";
    }
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
