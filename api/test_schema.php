<?php
require_once 'config/db.php';

function getTableSchema($pdo, $tableName) {
    try {
        $stmt = $pdo->query("DESCRIBE $tableName");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

$tables = ['users', 'catalog_questions', 'companies', 'custom_tests'];
$res = [];
foreach ($tables as $t) {
    $res[$t] = getTableSchema($pdo, $t);
}
header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);
?>
