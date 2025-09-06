<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'database/db_connect_env.php';

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    // Se não for AJAX, pode permitir GET também para testes
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        exit;
    }
}

try {
    // Obter parâmetros de busca
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
    
    // Limpar e validar entrada
    $search_term = trim($search_term);
    $categoria_id = is_numeric($categoria_id) ? intval($categoria_id) : '';
    
    // Construir query SQL
    $sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.estoque > 0";
    
    $params = [];
    
    // Adicionar filtro de busca por texto
    if (!empty($search_term)) {
        $sql .= " AND (p.nome LIKE :search_term OR c.nome LIKE :search_term OR p.descricao LIKE :search_term)";
        $params[':search_term'] = '%' . $search_term . '%';
    }
    
    // Adicionar filtro de categoria
    if (!empty($categoria_id)) {
        $sql .= " AND p.categoria_id = :categoria_id";
        $params[':categoria_id'] = $categoria_id;
    }
    
    $sql .= " ORDER BY p.id DESC";
    
    // Executar query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de produtos (sem filtros)
    $count_sql = "SELECT COUNT(*) FROM produtos WHERE estoque > 0";
    if (!empty($categoria_id)) {
        $count_stmt = $conn->prepare($count_sql . " AND categoria_id = :categoria_id");
        $count_stmt->execute([':categoria_id' => $categoria_id]);
    } else {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute();
    }
    $total_produtos = $count_stmt->fetchColumn();
    
    // Preparar resposta
    $response = [
        'success' => true,
        'produtos' => $produtos,
        'total_encontrados' => count($produtos),
        'total_produtos' => $total_produtos,
        'search_term' => $search_term,
        'categoria_id' => $categoria_id
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados',
        'message' => 'Erro interno do servidor'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno',
        'message' => 'Erro interno do servidor'
    ]);
}
?>

