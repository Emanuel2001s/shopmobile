<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

try {
    $search_term = '';
    $page = 1;
    $per_page = 5;
    
    // Receber parâmetros
    if (isset($_GET['search'])) {
        $search_term = trim($_GET['search']);
    }
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
        $page = (int)$_GET['page'];
    }
    
    $offset = ($page - 1) * $per_page;
    
    // Construir query base
    $where_conditions = [];
    $params = [];
    
    if (!empty($search_term)) {
        // Buscar por nome do cliente ou ID do pedido
        if (strpos($search_term, '#') === 0) {
            // Busca por ID do pedido
            $pedido_id = substr($search_term, 1);
            if (is_numeric($pedido_id)) {
                $where_conditions[] = "p.id = :pedido_id";
                $params[':pedido_id'] = $pedido_id;
            }
        } else {
            // Busca por nome do cliente
            $where_conditions[] = "p.nome_completo LIKE :search_term";
            $params[':search_term'] = '%' . $search_term . '%';
        }
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' OR ', $where_conditions);
    }
    
    // Buscar total de pedidos
    $count_sql = "SELECT COUNT(*) FROM pedidos p $where_clause";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_pedidos = $stmt->fetchColumn();
    $total_paginas = ceil($total_pedidos / $per_page);
    
    // Buscar pedidos com paginação
    $sql = "SELECT p.* FROM pedidos p $where_clause ORDER BY p.data_pedido DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada pedido, buscar os itens
    foreach ($pedidos as &$pedido) {
        // Primeiro tentar buscar da tabela pedido_itens
        $stmt = $conn->prepare("SELECT * FROM pedido_itens WHERE pedido_id = ?");
        $stmt->execute([$pedido['id']]);
        $itens_tabela = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($itens_tabela)) {
            // Usar itens da tabela pedido_itens (pedidos novos)
            $pedido['itens'] = $itens_tabela;
            $pedido['total_produtos_calculado'] = 0;
            foreach ($pedido['itens'] as $item) {
                $pedido['total_produtos_calculado'] += $item['subtotal'];
            }
        } else {
            // Fallback para pedidos antigos usando o campo produto_id
            $produto_ids = array_filter(array_map('trim', explode(',', $pedido['produto_id'])));
            $pedido['itens'] = [];
            $pedido['total_produtos_calculado'] = 0;

            if (!empty($produto_ids)) {
                $placeholders = implode(',', array_fill(0, count($produto_ids), '?'));
                $stmt = $conn->prepare("SELECT id, nome, preco FROM produtos WHERE id IN ($placeholders)");
                $stmt->execute($produto_ids);
                $produtos_do_pedido = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($produtos_do_pedido as $prod) {
                    $pedido['itens'][] = [
                        'produto_id' => $prod['id'],
                        'nome_produto' => $prod['nome'],
                        'quantidade' => 1,
                        'preco_unitario' => $prod['preco'],
                        'subtotal' => $prod['preco']
                    ];
                    $pedido['total_produtos_calculado'] += $prod['preco'];
                }
            }
        }
    }
    unset($pedido);
    
    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'paginacao' => [
            'pagina_atual' => $page,
            'total_paginas' => $total_paginas,
            'total_pedidos' => $total_pedidos,
            'por_pagina' => $per_page
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro: ' . $e->getMessage()
    ]);
}
?> 