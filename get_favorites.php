<?php
require_once 'database/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['ids']) || !is_array($input['ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'IDs inválidos']);
    exit;
}

$ids = array_filter($input['ids'], 'is_numeric');

if (empty($ids)) {
    echo json_encode([]);
    exit;
}

try {
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $sql = "SELECT id, nome, preco, foto_principal FROM produtos WHERE id IN ($placeholders) AND estoque > 0";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>

