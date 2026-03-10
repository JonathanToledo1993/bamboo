<?php
// api/config/db.php

// Cargar variables de entorno desde .env
require_once __DIR__ . '/env.php';

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? '';
$username = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;port=3306;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    die(json_encode([
        "status" => "error",
        "message" => "Error interno DB: " . $e->getMessage()
    ]));
}
?>
