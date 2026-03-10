<?php
require_once '../../config/db.php';
require_once '../../utils/Responder.php';

try {
    // Check catalog_questions schema
    $stmt = $pdo->query("DESCRIBE catalog_questions");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get some sample data
    $stmt = $pdo->query("SELECT testId, COUNT(*) as count FROM catalog_questions GROUP BY testId");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get tests list
    $stmt = $pdo->query("SELECT id, `key`, name FROM catalog_tests");
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Responder::success([
        "schema" => $schema,
        "questions_summary" => $samples,
        "tests" => $tests
    ]);
}
catch (Exception $e) {
    Responder::error($e->getMessage());
}
