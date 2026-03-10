<?php
// fix_db_status.php
// Run this script once by visiting it in your browser to fix the status column
require_once 'api/config/db.php';

echo "<h2>Actualización de Base de Datos - Bamboo</h2>";

try {
    // 1. Cambiar el tipo de columna status para que acepte cualquier texto (VARCHAR) en lugar de un ENUM limitado
    echo "<li>Modificando columna 'status' en 'evaluation_candidates'...</li>";
    $pdo->exec("ALTER TABLE evaluation_candidates MODIFY COLUMN status VARCHAR(50) DEFAULT 'NEW'");
    echo "<b style='color:green'>[OK] Columna modificada exitosamente.</b><br><br>";

    // 2. Verificar si hay registros que deban ser marcados como INVALIDATED retrospectivamente
    // (Opcional, pero útil si se intentó marcar y quedó vacío)
    echo "<li>Buscando evaluaciones que debieron ser anuladas...</li>";
    // Si el ENUM rechazó el valor, es posible que el status haya quedado como '' (vacío) o el valor por defecto.
    // Pero como no sabemos cuál, simplemente dejaremos que el usuario lo vuelva a intentar o lo maneje el sistema.

    echo "<li>Verificando esquema actual:</li>";
    $stmt = $pdo->query("SHOW COLUMNS FROM evaluation_candidates LIKE 'status'");
    $col = $stmt->fetch();
    echo "<pre>";
    print_r($col);
    echo "</pre>";

    echo "<h3 style='color:blue'>¡Todo listo! Ya puedes borrar este archivo y probar nuevamente la anulación.</h3>";

}
catch (Exception $e) {
    echo "<b style='color:red'>Error: " . $e->getMessage() . "</b><br>";
    echo "Es posible que tu base de datos no sea MySQL o no tengas permisos suficientes.";
}
?>
