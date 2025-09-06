<?php
header('Content-Type: application/json');
require_once '../database/db_connect_env.php';

try {
    $search_term = '';
    $categoria_id = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $search_term = $input['search'] ?? '';
        $categoria_id = $input['categoria'] ?? '';
    } else {
        $search_term = $_GET['search'] ?? '';
        $categoria_id = $_GET['categoria'] ?? '';
    }
    $search_term = trim($search_term);
    $categoria_id = is_numeric($categoria_id) ? intval($categoria_id) : '';
    $sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE 1=1";
    $params = [];
    if (!empty($search_term)) {
        $sql .= " AND (p.nome LIKE :search_term OR c.nome LIKE :search_term OR p.descricao LIKE :search_term OR p.id = :search_id)";
        $params[':search_term'] = '%' . $search_term . '%';
        $params[':search_id'] = $search_term;
    }
    if (!empty($categoria_id)) {
        $sql .= " AND p.categoria_id = :categoria_id";
        $params[':categoria_id'] = $categoria_id;
    }
    $sql .= " ORDER BY p.nome";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response = [
        'success' => true,
        'produtos' => $produtos,
        'total_encontrados' => count($produtos),
        'search_term' => $search_term,
        'categoria_id' => $categoria_id
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno',
        'message' => $e->getMessage()
    ]);
} 