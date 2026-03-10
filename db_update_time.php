<?php
require_once __DIR__ . '/api/config/db.php';

try {
    $pdo->exec("
        ALTER TABLE notification_settings
        ADD COLUMN dailySummaryTime TINYINT(2) DEFAULT 8 AFTER dailySummary;
    ");
    echo "Column dailySummaryTime added to notification_settings.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
         echo "Column already exists.";
    } else {
         echo "Error: " . $e->getMessage();
    }
}
?>
