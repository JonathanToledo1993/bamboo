<?php
require_once 'api/config/db.php';
$stmt = $pdo->query('SELECT id, `key`, name FROM catalog_tests');
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query('SELECT DISTINCT testId FROM catalog_questions');
$q_test_ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);

echo "TESTS IN DB:\n";
print_r($tests);

echo "\nTEST IDs IN QUESTIONS TABLE:\n";
print_r($q_test_ids);

$key = 'ENG_BASIC';
$id_test = 'ct_engbasic';

$sql = "SELECT id, testId as testKey, type, questionText as question
        FROM catalog_questions 
        WHERE testId = :k1 
           OR testId = :id1 
           OR testId = (SELECT id FROM catalog_tests WHERE `key` = :k2 LIMIT 1)
           OR testId = (SELECT `key` FROM catalog_tests WHERE id = :id2 LIMIT 1)";


$stmt = $pdo->prepare($sql);
$stmt->execute([
    'k1' => $key,
    'id1' => $id_test,
    'k2' => $key,
    'id2' => $id_test
]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nROBUST QUERY RESULTS FOR ENG_BASIC / ct_engbasic:\n";
print_r($questions);
