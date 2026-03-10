<?php
// api/admin/companies/list.php
require_once '../../config/db.php';
require_once '../../utils/auth_middleware.php';

Responder::setupCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error("Método no permitido.", 405);
}

// Proteger la ruta (Requerir Admin JWT)
$adminPayload = AuthMiddleware::requireAdmin();

try {
    // Consultar lista de empresas con conteos (similar a Prisma include)
    $sql = "
        SELECT 
            c.id, c.name, c.rfc, c.plan, c.isActive, c.createdAt,
            (SELECT COUNT(*) FROM users u WHERE u.companyId = c.id) as userCount,
            (
                SELECT COALESCE(SUM(amount), 0) FROM credit_transactions ct 
                WHERE ct.companyId = c.id AND ct.type = 'CREDIT_ADD'
            ) - (
                SELECT COALESCE(SUM(amount), 0) FROM credit_transactions ct 
                WHERE ct.companyId = c.id AND ct.type = 'CREDIT_USE'
            ) as actualCredits
        FROM companies c
        ORDER BY c.createdAt DESC
    ";

    $stmt = $pdo->query($sql);
    $companies = $stmt->fetchAll();

    // Mapeo forzado de tipos numéricos
    $companies = array_map(function ($c) {
        $c['userCount'] = (int)$c['userCount'];
        $c['actualCredits'] = (int)$c['actualCredits'];
        $c['isActive'] = (bool)$c['isActive'];
        return $c;
    }, $companies);

    Responder::success($companies, "Empresas obtenidas correctamente.");
}
catch (Exception $e) {
    Responder::error("Error recopilando empresas: " . $e->getMessage(), 500);
}
?>
