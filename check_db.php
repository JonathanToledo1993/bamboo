<?php
require_once 'api/config/db.php';

try {
    echo "Checking evaluation_candidates status values:\n";
    $stmt = $pdo->query("SELECT id, candidateId, status FROM evaluation_candidates ORDER BY lastActivityAt DESC LIMIT 10");
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        echo "ID: {$row['id']} | CandidateID: {$row['candidateId']} | Status: [" . ($row['status'] ?? 'NULL') . "]\n";
    }

    echo "\nChecking table schema:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM evaluation_candidates LIKE 'status'");
    $col = $stmt->fetch();
    print_r($col);

}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
