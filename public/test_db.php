<?php
require_once 'api/config/db.php';

try {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    print_r($tables);

    $stmt = $pdo->query('DESCRIBE evaluations');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nColumns in evaluations table:\n";
    print_r($columns);
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
