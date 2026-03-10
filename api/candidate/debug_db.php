<?php
header("Content-Type: application/json");
require_once '../config/db.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $details = [];
    if (in_array('candidate_answers', $tables)) {
        $stmt2 = $pdo->query("DESCRIBE candidate_answers");
        $details = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "status" => "success",
        "tables" => $tables,
        "candidate_answers_schema" => $details
    ]);

}
catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
