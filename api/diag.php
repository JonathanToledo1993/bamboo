<?php
// DIAGNOSTIC SCRIPT - DELETE AFTER USE
require_once 'config/db.php';

header('Content-Type: application/json');

$results = [];

// Test 1: Check companies table columns
try {
    $stmt = $pdo->query("DESCRIBE companies");
    $results['companies_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results['companies_columns_error'] = $e->getMessage();
}

// Test 2: Check users table columns
try {
    $stmt = $pdo->query("DESCRIBE users");
    $results['users_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results['users_columns_error'] = $e->getMessage();
}

// Test 3: Check credit_transactions table columns
try {
    $stmt = $pdo->query("DESCRIBE credit_transactions");
    $results['credit_transactions_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results['credit_transactions_columns_error'] = $e->getMessage();
}

// Test 4: Try a real insert and see what error comes
try {
    $pdo->beginTransaction();
    
    $companyId = 'CO-TEST-DIAG-001';
    $stmt = $pdo->prepare("INSERT INTO companies (id, name, rfc, country, plan, credits, isActive, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
    $stmt->execute([$companyId, 'Empresa Test Diag', 'RFC001', 'Ecuador', 'CREDITS', 50]);
    $results['company_insert'] = 'OK';
    
    $userId = 'US-TEST-DIAG-001';
    $pwdHash = password_hash('Test123!', PASSWORD_DEFAULT);
    $stmt2 = $pdo->prepare("INSERT INTO users (id, companyId, name, role, email, password, isActive, createdAt) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt2->execute([$userId, $companyId, 'Admin Test', 'Administrador Principal', 'testdiag@test.com', $pwdHash]);
    $results['user_insert'] = 'OK';
    
    $transId = 'TR-TEST-DIAG-001';
    $stmt3 = $pdo->prepare("INSERT INTO credit_transactions (id, companyId, amount, type, reason, adminId, createdAt) VALUES (?, ?, ?, 'CREDIT_ADD', 'Creditos iniciales', 'admin-test', NOW())");
    $stmt3->execute([$transId, $companyId, 50]);
    $results['transaction_insert'] = 'OK';
    
    $pdo->rollBack(); // Always roll back - this is just a test
    $results['note'] = 'Rollback OK - all inserts succeeded in test';
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $results['insert_error'] = $e->getMessage();
    $results['insert_error_code'] = $e->getCode();
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
