<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

$message = "";
$message_type = "";

// Processar lançamento de novo pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $nome_completo = $_POST['nome_completo'] ?? '';
    $whatsapp = $_POST['whatsapp'] ?? '';
    $entregar_endereco = isset($_POST['entregar_endereco']) ? 1 : 0;
    $rua = $_POST['rua'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $desconto = floatval($_POST['desconto'] ?? 0);
    $observacoes = $_POST['observacoes'] ?? '';
    $status = $_POST['status'] ?? 'pendente';
    
    // Receber os itens do pedido via JSON
    $itens_json = $_POST['itens_pedido'] ?? '';
    $itens_pedido = json_decode($itens_json, true);

    if (!empty($itens_pedido) && is_array($itens_pedido)) {
        try {
            $conn->beginTransaction();
            
            // Verificar estoque antes de inserir o pedido
            foreach ($itens_pedido as $item) {
                $produto_id = $item['produto_id'];
                $quantidade_solicitada = intval($item['quantidade']);

                $stmt = $conn->prepare("SELECT nome, estoque FROM produtos WHERE id = ?");
                $stmt->execute([$produto_id]);
                $produto_estoque = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$produto_estoque) {
                    throw new Exception("Produto com ID {$produto_id} não encontrado!");
                }

                if ($produto_estoque['estoque'] < $quantidade_solicitada) {
                    throw new Exception("Estoque insuficiente para o produto '{$produto_estoque['nome']}'. Disponível: {$produto_estoque['estoque']}, Solicitado: {$quantidade_solicitada}.");
                }
            }
            
            $total_produtos_preco = 0;
            
            // Verificar se cliente já existe
            $cliente_id = null;
            if (!empty($whatsapp)) {
                $stmt = $conn->prepare("SELECT id FROM clientes WHERE whatsapp = ?");
                $stmt->execute([$whatsapp]);
                $cliente_existente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cliente_existente) {
                    $cliente_id = $cliente_existente['id'];
                } else if (!empty($nome_completo)) {
                    // Criar novo cliente
                    $stmt = $conn->prepare("INSERT INTO clientes (nome_completo, whatsapp) VALUES (?, ?)");
                    $stmt->execute([$nome_completo, $whatsapp]);
                    $cliente_id = $conn->lastInsertId();
                }
            }
            
            // Calcular total dos produtos
            foreach ($itens_pedido as $item) {
                $produto_id = $item['produto_id'];
                $quantidade = intval($item['quantidade']);
                
                // Buscar preço do produto
                $stmt = $conn->prepare("SELECT preco, nome FROM produtos WHERE id = ?");
                $stmt->execute([$produto_id]);
                $produto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($produto) {
                    $subtotal = $produto['preco'] * $quantidade;
                    $total_produtos_preco += $subtotal;
                } else {
                    throw new Exception("Produto com ID {$produto_id} não encontrado!");
                }
            }
            
            $valor_total = $total_produtos_preco - $desconto;
            
            // Inserir pedido (sem produto_id, pois agora usamos a tabela pedido_itens)
            $stmt = $conn->prepare("INSERT INTO pedidos (nome_completo, whatsapp, entregar_endereco, rua, numero, bairro, cidade, cep, produto_id, desconto, valor_total, observacoes, status, cliente_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$nome_completo, $whatsapp, $entregar_endereco, $rua, $numero, $bairro, $cidade, $cep, '', $desconto, $valor_total, $observacoes, $status, $cliente_id])) {
                $pedido_id = $conn->lastInsertId();
                
                // Inserir itens do pedido
                foreach ($itens_pedido as $item) {
                    $produto_id = $item['produto_id'];
                    $quantidade = intval($item['quantidade']);
                    
                    // Buscar dados do produto
                    $stmt = $conn->prepare("SELECT preco, nome FROM produtos WHERE id = ?");
                    $stmt->execute([$produto_id]);
                    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $preco_unitario = $produto['preco'];
                    $nome_produto = $produto['nome'];
                    $subtotal = $preco_unitario * $quantidade;
                    
                    // Inserir item do pedido
                    $stmt = $conn->prepare("INSERT INTO pedido_itens (pedido_id, produto_id, nome_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$pedido_id, $produto_id, $nome_produto, $quantidade, $preco_unitario, $subtotal]);

                    // Descontar estoque do produto
                    $stmt = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
                    $stmt->execute([$quantidade, $produto_id]);
                }
                
                $conn->commit();
                $message = "Pedido lançado com sucesso!";
                $message_type = "success";
                
                // Atualizar estatísticas do cliente se existir
                if ($cliente_id) {
                    $stmt = $conn->prepare("UPDATE clientes SET total_pedidos = total_pedidos + 1, valor_total = valor_total + ? WHERE id = ?");
                    $stmt->execute([$valor_total, $cliente_id]);
                }
            } else {
                $conn->rollBack();
                $message = "Erro ao lançar pedido!";
                $message_type = "error";
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $message = "Erro ao lançar pedido: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Processar exclusão de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $pedido_id = $_POST['pedido_id'] ?? 0;
    
    if ($pedido_id > 0) {
        try {
            $conn->beginTransaction();
            
            // Excluir itens do pedido primeiro
            $stmt = $conn->prepare("DELETE FROM pedido_itens WHERE pedido_id = ?");
            $stmt->execute([$pedido_id]);
            
            // Excluir pedido
            $stmt = $conn->prepare("DELETE FROM pedidos WHERE id = ?");
            
            if ($stmt->execute([$pedido_id])) {
                $conn->commit();
                $message = "Pedido excluído com sucesso!";
                $message_type = "success";
            } else {
                $conn->rollBack();
                $message = "Erro ao excluir pedido!";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Erro ao excluir pedido: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Processar edição de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_order') {
    $pedido_id = $_POST['pedido_id'] ?? 0;
    $nome_completo = $_POST['nome_completo'] ?? '';
    $whatsapp = $_POST['whatsapp'] ?? '';
    $entregar_endereco = isset($_POST['entregar_endereco']) ? 1 : 0;
    $rua = $_POST['rua'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $desconto = floatval($_POST['desconto'] ?? 0);
    $observacoes = $_POST['observacoes'] ?? '';
    
    if ($pedido_id > 0) {
        try {
            // Recalcular valor total baseado nos itens
            $stmt = $conn->prepare("SELECT SUM(subtotal) as total_produtos FROM pedido_itens WHERE pedido_id = ?");
            $stmt->execute([$pedido_id]);
            $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_produtos = $total_result['total_produtos'] ?? 0;
            $valor_total = $total_produtos - $desconto;
            
            $stmt = $conn->prepare("UPDATE pedidos SET 
                nome_completo = ?, 
                whatsapp = ?, 
                entregar_endereco = ?, 
                rua = ?, 
                numero = ?, 
                bairro = ?, 
                cidade = ?, 
                cep = ?,
                desconto = ?,
                valor_total = ?,
                observacoes = ?
                WHERE id = ?");
            
            if ($stmt->execute([$nome_completo, $whatsapp, $entregar_endereco, $rua, $numero, $bairro, $cidade, $cep, $desconto, $valor_total, $observacoes, $pedido_id])) {
                $message = "Pedido editado com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao editar pedido!";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "Erro ao editar pedido: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Processar alteração de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $pedido_id = $_POST['pedido_id'] ?? 0;
    $novo_status = $_POST['status'] ?? '';
    
    if ($pedido_id > 0 && in_array($novo_status, ['pendente', 'confirmado', 'cancelado'])) {
        // Buscar dados do pedido antes de atualizar
        $stmt = $conn->prepare("SELECT whatsapp, nome_completo, valor_total FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        $pedido_dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        
        if ($stmt->execute([$novo_status, $pedido_id])) {
            $message = "Status do pedido atualizado com sucesso!";
            $message_type = "success";
            
            // Enviar mensagem de status se for confirmado ou cancelado
            if (in_array($novo_status, ['confirmado', 'cancelado'])) {
                define('ADMIN_ACCESS', true);
                require_once 'send_status_message.php';
                $mensagem_enviada = send_status_message($pedido_id, $novo_status);
                
                if ($mensagem_enviada) {
                    $message .= " ✅ Mensagem de status enviada para o cliente via WhatsApp.";
                } else {
                    $message .= " ⚠️ Status atualizado, mas houve um problema ao enviar a mensagem.";
                }
            }
        } else {
            $message = "Erro ao atualizar status do pedido!";
            $message_type = "error";
        }
    }
}

// Filtro de data
$filtro_data = $_GET['filtro'] ?? 'todos';
$where_clause = "";
$params = [];

switch ($filtro_data) {
    case 'hoje':
        $where_clause = "WHERE DATE(p.data_pedido) = CURDATE()";
        break;
    case 'ontem':
        $where_clause = "WHERE DATE(p.data_pedido) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'semana':
        $where_clause = "WHERE p.data_pedido >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'mes':
        $where_clause = "WHERE p.data_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'todos':
    default:
        $where_clause = "";
        break;
}

// Paginação
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

// Buscar total de pedidos para paginação
$count_sql = "SELECT COUNT(*) FROM pedidos p $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_pedidos = $stmt->fetchColumn();
$total_paginas = ceil($total_pedidos / $per_page);

// Buscar pedidos com paginação
$sql = "SELECT p.* FROM pedidos p $where_clause ORDER BY p.data_pedido DESC LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para cada pedido, buscar os itens da tabela pedido_itens ou do campo produto_id (compatibilidade)
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
unset($pedido); // Quebrar a referência do último elemento

// Estatísticas
$stats = [];
$stats['total'] = count($pedidos);
$stats['pendentes'] = count(array_filter($pedidos, fn($p) => $p['status'] === 'pendente'));
$stats['confirmados'] = count(array_filter($pedidos, fn($p) => $p['status'] === 'confirmado'));
$stats['cancelados'] = count(array_filter($pedidos, fn($p) => $p['status'] === 'cancelado'));

// Estatísticas por período para exibir no filtro
$stats_periodos = [];

// Hoje
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_pedido) = CURDATE()");
$stmt->execute();
$stats_periodos['hoje'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ontem
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_pedido) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$stmt->execute();
$stats_periodos['ontem'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Semana
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE data_pedido >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$stats_periodos['semana'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Mês
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE data_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute();
$stats_periodos['mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Todos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos");
$stmt->execute();
$stats_periodos['todos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Buscar produtos para o formulário de lançamento (incluindo estoque)
$stmt = $conn->prepare("SELECT id, nome, preco, estoque FROM produtos ORDER BY nome");
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Painel Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #ecf0f1;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            color: #bdc3c7;
            font-size: 0.9rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-item:hover {
            background: #34495e;
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-item.active {
            background: #34495e;
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-divider {
            height: 1px;
            background: #34495e;
            margin: 10px 20px;
        }

        .admin-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: white;
            margin-bottom: 5px;
        }

        .header p {
            color: #bdc3c7;
        }

        .container {
            max-width: 100%;
            margin: 0;
        }

        /* Botão hamburger para mobile */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 10px;
            position: relative;
            z-index: 1001;
            background: #34495e;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            width: 40px;
            height: 40px;
            justify-content: center;
            align-items: center;
        }

        .mobile-menu-toggle span {
            width: 20px;
            height: 2px;
            background: white;
            margin: 2px 0;
            transition: 0.3s;
            border-radius: 1px;
            display: block;
        }

        .mobile-menu-toggle.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .mobile-menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-toggle.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        /* Overlay para mobile */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .mobile-overlay.active {
            display: block;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 100%;
            margin: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .filters-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .filters-header h3 {
            color: #333;
            font-size: 1.1rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 20px;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .filter-count {
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .filter-btn.active .filter-count {
            background: rgba(255,255,255,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-card.total .number { color: #667eea; }
        .stat-card.pendentes .number { color: #ffc107; }
        .stat-card.confirmados .number { color: #28a745; }
        .stat-card.cancelados .number { color: #dc3545; }
        
        .orders-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            color: #333;
        }
        
        .period-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .order-card {
            border-bottom: 1px solid #dee2e6;
            padding: 1.5rem;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .order-id {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-group h4 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-group p {
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .product-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .discount-info {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .taxa-entrega-info {
            color: #e67e22;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .formas-pagamento-info {
            color: #2980b9;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .total-price {
            color: #667eea;
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }
        
        .status-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmado {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .btn-update {
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-update:hover {
            background: #5a6fd8;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-edit:hover {
            background: #218838;
        }
        
        .btn-payment {
            background: #007bff;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-payment:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }
        
        .actions-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .address-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
        }
        
        .address-info h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        .whatsapp-link {
            display: inline-flex;
            align-items: center;
            background: #25d366;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        
        .whatsapp-link:hover {
            background: #128c7e;
            color: white;
        }
        
        .whatsapp-link .icon {
            margin-right: 0.5rem;
        }
        
        .observations {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-top: 1rem;
        }
        
        .observations h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
        }
        
        .modal h3 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .modal p {
            margin-bottom: 2rem;
            color: #666;
            text-align: center;
        }
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn-confirm {
            background: #dc3545;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-confirm:hover {
            background: #c82333;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-save:hover {
            background: #218838;
        }
        
        /* Form styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Product search styles */
        .product-search-section {
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .search-input-container {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .search-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .search-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e1e5e9;
            border-radius: 5px;
            background: white;
            display: none;
        }
        
        .search-result-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .search-result-item:hover {
            background: #f8f9fa;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .product-info-search {
            flex: 1;
        }
        
        .product-name-search {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .product-price-search {
            color: #28a745;
            font-size: 0.9rem;
        }
        
        .product-stock-search {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .product-stock-search.in-stock {
            color: #28a745;
        }
        
        .product-stock-search.out-of-stock {
            color: #dc3545;
            font-weight: 600;
        }
        
        .search-result-item.out-of-stock {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f8f9fa;
        }
        
        .search-result-item.out-of-stock:hover {
            background: #f8f9fa;
        }
        
        .btn-add-to-cart:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .btn-add-to-cart:disabled:hover {
            background: #6c757d;
        }
        
        .btn-add-to-cart {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .btn-add-to-cart:hover {
            background: #218838;
        }
        
        .cart-section {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .cart-header {
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-items {
            min-height: 100px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            border: 1px solid #e1e5e9;
        }
        
        .cart-item:last-child {
            margin-bottom: 0;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .cart-item-price {
            color: #28a745;
            font-size: 0.9rem;
        }
        
        .cart-item-stock {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-quantity {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 3px;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .btn-quantity:hover {
            background: #5a6fd8;
        }
        
        .quantity-display {
            min-width: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .btn-remove-item {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 0.25rem 0.5rem;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .btn-remove-item:hover {
            background: #c82333;
        }
        
        .cart-total {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            text-align: center;
        }
        
        .cart-total h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .empty-cart {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
        }
        
        .product-list {
            margin-top: 1rem;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }
        
        .product-item-display {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #eee;
        }
        
        .product-item-display:last-child {
            border-bottom: none;
        }
        
        .product-item-name-display {
            font-weight: normal;
            color: #555;
        }
        
        .product-item-price-display {
            font-weight: normal;
            color: #28a745;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
                background: #2c3e50;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .admin-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
                min-height: 70px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
            
            .mobile-overlay {
                display: none;
            }
        }
        
        .pagination-admin {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            margin-bottom: 2rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .pagination-btn {
            background: #f1f1f1;
            color: #333;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            text-decoration: none;
            font-weight: 500;
            border: none;
            transition: background 0.2s, color 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            display: inline-block;
        }
        .pagination-btn.active, .pagination-btn:hover {
            background: #667eea;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Overlay para mobile -->
        <div class="mobile-overlay" id="mobile-overlay"></div>
        
                <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>🛍️ Painel Admin</h2>
                <p>Gerencie sua loja</p>
            </div>
            
            <nav>
                <a href="index.php" class="sidebar-item" data-tooltip="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="products.php" class="sidebar-item" data-tooltip="Produtos">
                    <i class="fas fa-box"></i>
                    <span>Produtos</span>
                </a>
                
                <a href="categories.php" class="sidebar-item" data-tooltip="Categorias">
                    <i class="fas fa-tags"></i>
                    <span>Categorias</span>
                </a>
                
                <a href="orders.php" class="sidebar-item active" data-tooltip="Pedidos">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Pedidos</span>
                </a>
                
                <a href="payment_monitor.php" class="sidebar-item" data-tooltip="Monitor de Pagamentos">
                    <i class="fas fa-credit-card"></i>
                    <span>Monitor de Pagamentos</span>
                </a>
                
                <a href="clientes.php" class="sidebar-item" data-tooltip="Clientes">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                
                <a href="sliders.php" class="sidebar-item" data-tooltip="Sliders Promocionais">
                    <i class="fas fa-images"></i>
                    <span>Sliders</span>
                </a>
                
                <a href="../index.php" class="sidebar-item" target="_blank" data-tooltip="Ver Loja">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Ver Loja</span>
                </a>
                
                <a href="configuracoes.php" class="sidebar-item" data-tooltip="Configurações">
                    <i class="fas fa-cog"></i>
                    <span>Configurações</span>
                </a>
                
                                <a href="usuarios.php" class="sidebar-item" data-tooltip="Usuários">
                    <i class="fas fa-users-cog"></i>
                    <span>Usuários</span>
                </a>
                
                <div class="sidebar-divider"></div>
                
                <a href="logout.php" class="sidebar-item logout" data-tooltip="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </nav>
        </div>
        
        <!-- Conteúdo Principal -->
        <div class="admin-content">
            <div class="container">
                <div class="header">
                    <div>
        <h1>🛒 Gerenciar Pedidos</h1>
                        <p>Visualize e gerencie todos os pedidos da sua loja</p>
                    </div>
                    <!-- Botão hamburger para mobile -->
                    <div class="mobile-menu-toggle" id="mobile-menu-toggle">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        ✅ <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        ❌ <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Botões de Ação -->
        <div class="action-buttons">
            <button type="button" class="btn-primary" onclick="openCreateModal()">
                ➕ Lançar Novo Pedido
            </button>
        </div>
        
        <!-- Filtros de Data -->
        <div class="filters-section">
            <div class="filters-header">
                <h3>📅 Filtrar por Período</h3>
            </div>
            <div class="filter-buttons">
                <a href="?filtro=hoje" class="filter-btn <?php echo $filtro_data === 'hoje' ? 'active' : ''; ?>">
                    📅 Hoje
                    <span class="filter-count"><?php echo $stats_periodos['hoje']; ?></span>
                </a>
                <a href="?filtro=ontem" class="filter-btn <?php echo $filtro_data === 'ontem' ? 'active' : ''; ?>">
                    📆 Ontem
                    <span class="filter-count"><?php echo $stats_periodos['ontem']; ?></span>
                </a>
                <a href="?filtro=semana" class="filter-btn <?php echo $filtro_data === 'semana' ? 'active' : ''; ?>">
                    📊 Última Semana
                    <span class="filter-count"><?php echo $stats_periodos['semana']; ?></span>
                </a>
                <a href="?filtro=mes" class="filter-btn <?php echo $filtro_data === 'mes' ? 'active' : ''; ?>">
                    📈 Último Mês
                    <span class="filter-count"><?php echo $stats_periodos['mes']; ?></span>
                </a>
                <a href="?filtro=todos" class="filter-btn <?php echo $filtro_data === 'todos' ? 'active' : ''; ?>">
                    🗂️ Todos os Tempos
                    <span class="filter-count"><?php echo $stats_periodos['todos']; ?></span>
                </a>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="number"><?php echo $stats['total']; ?></div>
                <div class="label">Total de Pedidos</div>
            </div>
            <div class="stat-card pendentes">
                <div class="number"><?php echo $stats['pendentes']; ?></div>
                <div class="label">Pendentes</div>
            </div>
            <div class="stat-card confirmados">
                <div class="number"><?php echo $stats['confirmados']; ?></div>
                <div class="label">Confirmados</div>
            </div>
            <div class="stat-card cancelados">
                <div class="number"><?php echo $stats['cancelados']; ?></div>
                <div class="label">Cancelados</div>
            </div>
        </div>
        
        <!-- Lista de pedidos -->
        <div class="orders-section">
            <div class="section-header">
                <h2>Lista de Pedidos</h2>
                <div class="period-info">
                    <?php
                    $periodo_texto = [
                        'hoje' => 'Pedidos de hoje',
                        'ontem' => 'Pedidos de ontem',
                        'semana' => 'Pedidos da última semana',
                        'mes' => 'Pedidos do último mês',
                        'todos' => 'Todos os pedidos'
                    ];
                    echo $periodo_texto[$filtro_data] ?? 'Todos os pedidos';
                    ?>
                </div>
            </div>
            
            <?php if (count($pedidos) > 0): ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">Pedido #<?php echo $pedido['id']; ?></div>
                            <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></div>
                        </div>
                        
                        <div class="product-info">
                            <h4>Produtos:</h4>
                            <div class="product-list">
                                <?php foreach ($pedido['itens'] as $item): ?>
                                    <div class="product-item-display">
                                        <span class="product-item-name-display">
                                            <?php echo htmlspecialchars($item['nome_produto']); ?>
                                            (Qtd: <?php echo $item['quantidade']; ?>)
                                        </span>
                                        <span class="product-item-price-display">
                                            R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($pedido['desconto'] > 0): ?>
                                <div class="discount-info">Desconto: R$ <?php echo number_format($pedido['desconto'], 2, ',', '.'); ?></div>
                            <?php endif; ?>
                            <div class="total-price">Total: R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></div>
                            <?php if (!empty($pedido['taxa_entrega']) && $pedido['taxa_entrega'] > 0): ?>
                                <div class="taxa-entrega-info">🚚 <strong>Taxa de Entrega:</strong> R$ <?php echo number_format($pedido['taxa_entrega'], 2, ',', '.'); ?></div>
                            <?php endif; ?>
                            <?php
                                $formas_pagamento = [];
                                if (!empty($pedido['pagamento_pix'])) $formas_pagamento[] = '<span title="Pix" style="color:#27ae60;font-weight:600;"><i class="fas fa-qrcode"></i> Pix</span>';
                                if (!empty($pedido['pagamento_cartao'])) $formas_pagamento[] = '<span title="Cartão" style="color:#2980b9;font-weight:600;"><i class="fas fa-credit-card"></i> Cartão</span>';
                                if (!empty($pedido['pagamento_dinheiro'])) $formas_pagamento[] = '<span title="Dinheiro" style="color:#e67e22;font-weight:600;"><i class="fas fa-coins"></i> Dinheiro</span>';
                            ?>
                            <?php if (!empty($formas_pagamento)): ?>
                                <div class="formas-pagamento-info">💳 <strong>Pagamento:</strong> <?php echo implode(' | ', $formas_pagamento); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-group">
                                <h4>Informações do Cliente</h4>
                                <?php if (!empty($pedido['nome_completo'])): ?>
                                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['nome_completo']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($pedido['whatsapp'])): ?>
                                    <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($pedido['whatsapp']); ?></p>
                                    <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $pedido['whatsapp']); ?>" 
                                       class="whatsapp-link" target="_blank">
                                        <span class="icon">📱</span>
                                        Conversar no WhatsApp
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="detail-group">
                                <h4>Entrega</h4>
                                <?php if ($pedido['entregar_endereco']): ?>
                                    <div class="address-info">
                                        <h4>📍 Endereço de Entrega:</h4>
                                        <?php if (!empty($pedido['rua'])): ?>
                                            <p><?php echo htmlspecialchars($pedido['rua']); ?><?php if (!empty($pedido['numero'])): ?>, <?php echo htmlspecialchars($pedido['numero']); ?><?php endif; ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($pedido['bairro']) || !empty($pedido['cidade'])): ?>
                                            <p><?php echo htmlspecialchars($pedido['bairro']); ?><?php if (!empty($pedido['cidade'])): ?> - <?php echo htmlspecialchars($pedido['cidade']); ?><?php endif; ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($pedido['cep'])): ?>
                                            <p>CEP: <?php echo htmlspecialchars($pedido['cep']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p>🏪 <strong>Retirada no local</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($pedido['observacoes'])): ?>
                            <div class="observations">
                                <h4>📝 Observações:</h4>
                                <p><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="status-section">
                            <span class="status-badge status-<?php echo $pedido['status']; ?>">
                                <?php echo ucfirst($pedido['status']); ?>
                            </span>
                            
                            <form method="POST" class="status-form">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                <select name="status" class="status-select">
                                    <option value="pendente" <?php echo $pedido['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="confirmado" <?php echo $pedido['status'] === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                    <option value="cancelado" <?php echo $pedido['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                                <button type="submit" class="btn-update">Atualizar</button>
                            </form>
                        </div>
                        
                                                <div class="actions-section">
                            <button type="button" class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($pedido)); ?>)">
                                ✏️ Editar Pedido
                            </button>
                            <?php if ($pedido['status'] === 'pendente'): ?>
                            <button type="button" class="btn-payment" onclick="enviarPagamento(<?php echo intval($pedido['id']); ?>)">
                                💳 Enviar Pagamento
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn-delete" onclick="confirmDelete(<?php echo $pedido['id']; ?>)">
                                🗑️ Excluir Pedido
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">🛒</div>
                    <h3>Nenhum pedido encontrado</h3>
                    <p>
                        <?php
                        $mensagens_vazias = [
                            'hoje' => 'Não há pedidos para hoje.',
                            'ontem' => 'Não houve pedidos ontem.',
                            'semana' => 'Nenhum pedido na última semana.',
                            'mes' => 'Nenhum pedido no último mês.',
                            'todos' => 'Os pedidos aparecerão aqui quando os clientes fizerem compras.'
                        ];
                        echo $mensagens_vazias[$filtro_data] ?? 'Nenhum pedido encontrado para este período.';
                        ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-admin">
                    <?php if ($page > 1): ?>
                        <a href="?filtro=<?php echo $filtro_data; ?>&page=<?php echo $page-1; ?>" class="pagination-btn">&laquo; Anterior</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="?filtro=<?php echo $filtro_data; ?>&page=<?php echo $i; ?>" class="pagination-btn<?php echo $i==$page?' active':''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_paginas): ?>
                        <a href="?filtro=<?php echo $filtro_data; ?>&page=<?php echo $page+1; ?>" class="pagination-btn">Próxima &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal de lançamento de pedido -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <h3>➕ Lançar Novo Pedido</h3>
            <form id="createForm" method="POST">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" name="itens_pedido" id="itens_pedido">
                
                <!-- Seção de busca de produtos -->
                <div class="form-group">
                    <label>Nome ou Código do Produto *</label>
                    <div class="product-search-section">
                        <div class="search-input-container">
                            <input type="text" id="productSearch" class="search-input" placeholder="Digite o nome ou código do produto..." autocomplete="off">
                            <span class="search-icon">🔍</span>
                        </div>
                        <div id="searchResults" class="search-results"></div>
                    </div>
                </div>
                
                <!-- Carrinho de produtos -->
                <div class="cart-section">
                    <div class="cart-header">
                        <span>Itens do Pedido</span>
                        <span id="cartItemCount">0 itens</span>
                    </div>
                    <div id="cartItems" class="cart-items">
                        <div class="empty-cart">Nenhum produto adicionado ainda.</div>
                    </div>
                    <div class="cart-total">
                        <h4>Total dos Produtos:</h4>
                        <div class="total-amount" id="cartTotal">R$ 0,00</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="createNomeCompleto">Nome Completo</label>
                        <input type="text" id="createNomeCompleto" name="nome_completo">
                    </div>
                    <div class="form-group">
                        <label for="createWhatsapp">WhatsApp</label>
                        <input type="text" id="createWhatsapp" name="whatsapp">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="createDesconto">Desconto (R$)</label>
                        <input type="number" id="createDesconto" name="desconto" step="0.01" min="0" value="0" onchange="updateCartTotal()">
                    </div>
                    <div class="form-group">
                        <label for="createTaxaEntrega">Taxa de Entrega (R$)</label>
                        <input type="number" id="createTaxaEntrega" name="taxa_entrega" step="0.01" min="0" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Formas de Pagamento</label>
                    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                        <label><input type="checkbox" name="pagamento_pix"> <i class="fas fa-qrcode"></i> Pix</label>
                        <label><input type="checkbox" name="pagamento_cartao"> <i class="fas fa-credit-card"></i> Cartão</label>
                        <label><input type="checkbox" name="pagamento_dinheiro"> <i class="fas fa-coins"></i> Dinheiro</label>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="createEntregarEndereco" name="entregar_endereco">
                    <label for="createEntregarEndereco">Entregar no endereço</label>
                </div>
                
                <div id="createAddressFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="createRua">Rua</label>
                            <input type="text" id="createRua" name="rua">
                        </div>
                        <div class="form-group">
                            <label for="createNumero">Número</label>
                            <input type="text" id="createNumero" name="numero">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="createBairro">Bairro</label>
                            <input type="text" id="createBairro" name="bairro">
                        </div>
                        <div class="form-group">
                            <label for="createCidade">Cidade</label>
                            <input type="text" id="createCidade" name="cidade">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="createCep">CEP</label>
                        <input type="text" id="createCep" name="cep">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="createObservacoes">Observações</label>
                    <textarea id="createObservacoes" name="observacoes" rows="3"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Lançar Pedido</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de confirmação de exclusão -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ Confirmar Exclusão</h3>
            <p>Tem certeza que deseja excluir este pedido? Esta ação não pode ser desfeita.</p>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancelar</button>
                <button type="button" class="btn-confirm" onclick="deleteOrder()">Excluir</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de edição -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ Editar Pedido</h3>
            <form id="editForm" method="POST">
                <input type="hidden" name="action" value="edit_order">
                <input type="hidden" name="pedido_id" id="editOrderId">
                
                <div class="form-group">
                    <label for="editNomeCompleto">Nome Completo</label>
                    <input type="text" id="editNomeCompleto" name="nome_completo">
                </div>
                
                <div class="form-group">
                    <label for="editWhatsapp">WhatsApp</label>
                    <input type="text" id="editWhatsapp" name="whatsapp">
                </div>
                
                <div class="form-group">
                    <label for="editDesconto">Desconto (R$)</label>
                    <input type="number" id="editDesconto" name="desconto" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="editTaxaEntrega">Taxa de Entrega (R$)</label>
                    <input type="number" id="editTaxaEntrega" name="taxa_entrega" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Formas de Pagamento</label>
                    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                        <label><input type="checkbox" name="pagamento_pix" id="editPagamentoPix"> <i class="fas fa-qrcode"></i> Pix</label>
                        <label><input type="checkbox" name="pagamento_cartao" id="editPagamentoCartao"> <i class="fas fa-credit-card"></i> Cartão</label>
                        <label><input type="checkbox" name="pagamento_dinheiro" id="editPagamentoDinheiro"> <i class="fas fa-coins"></i> Dinheiro</label>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="editEntregarEndereco" name="entregar_endereco">
                    <label for="editEntregarEndereco">Entregar no endereço</label>
                </div>
                
                <div id="editAddressFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editRua">Rua</label>
                            <input type="text" id="editRua" name="rua">
                        </div>
                        <div class="form-group">
                            <label for="editNumero">Número</label>
                            <input type="text" id="editNumero" name="numero">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editBairro">Bairro</label>
                            <input type="text" id="editBairro" name="bairro">
                        </div>
                        <div class="form-group">
                            <label for="editCidade">Cidade</label>
                            <input type="text" id="editCidade" name="cidade">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="editCep">CEP</label>
                        <input type="text" id="editCep" name="cep">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editObservacoes">Observações</label>
                    <textarea id="editObservacoes" name="observacoes" rows="3"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Formulário oculto para exclusão -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_order">
        <input type="hidden" name="pedido_id" id="deleteOrderId">
    </form>
    
    <script>
        let orderToDelete = null;
        let cart = [];
        let produtos = <?php echo json_encode($produtos); ?>;
        
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
            resetCart();
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.getElementById('createForm').reset();
            document.getElementById('createAddressFields').style.display = 'none';
            resetCart();
        }
        
        function resetCart() {
            cart = [];
            updateCartDisplay();
            document.getElementById('productSearch').value = '';
            document.getElementById('searchResults').style.display = 'none';
        }
        
        // Busca de produtos
        document.getElementById('productSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const resultsContainer = document.getElementById('searchResults');
            
            if (searchTerm.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            const filteredProducts = produtos.filter(produto => 
                produto.nome.toLowerCase().includes(searchTerm) || 
                produto.id.toString().includes(searchTerm)
            );
            
            if (filteredProducts.length > 0) {
                let html = '';
                filteredProducts.forEach(produto => {
                    const estoque = parseInt(produto.estoque) || 0;
                    const temEstoque = estoque > 0;
                    const estoqueClass = temEstoque ? 'in-stock' : 'out-of-stock';
                    const estoqueText = temEstoque ? `Estoque: ${estoque} un.` : 'Sem estoque';
                    const clickAction = temEstoque ? `onclick="addToCart(${produto.id})"` : '';
                    const buttonAction = temEstoque ? `onclick="event.stopPropagation(); addToCart(${produto.id})"` : 'disabled';
                    
                    html += `
                        <div class="search-result-item ${estoqueClass}" ${clickAction}>
                            <div class="product-info-search">
                                <div class="product-name-search">${produto.nome}</div>
                                <div class="product-price-search">R$ ${parseFloat(produto.preco).toFixed(2).replace('.', ',')}</div>
                                <div class="product-stock-search ${estoqueClass}">${estoqueText}</div>
                            </div>
                            <button type="button" class="btn-add-to-cart" ${buttonAction}>+</button>
                        </div>
                    `;
                });
                resultsContainer.innerHTML = html;
                resultsContainer.style.display = 'block';
            } else {
                resultsContainer.innerHTML = '<div class="search-result-item">Nenhum produto encontrado</div>';
                resultsContainer.style.display = 'block';
            }
        });
        
        function addToCart(productId) {
            const produto = produtos.find(p => p.id == productId);
            if (!produto) return;
            
            // Verificar estoque
            const estoque = parseInt(produto.estoque) || 0;
            if (estoque <= 0) {
                alert(`Produto "${produto.nome}" está sem estoque!`);
                return;
            }
            
            const existingItem = cart.find(item => item.produto_id == productId);
            
            if (existingItem) {
                // Verificar se não excede o estoque disponível
                if (existingItem.quantidade >= estoque) {
                    alert(`Quantidade máxima disponível para "${produto.nome}" é ${estoque} unidades!`);
                    return;
                }
                existingItem.quantidade++;
            } else {
                cart.push({
                    produto_id: productId,
                    nome: produto.nome,
                    preco: parseFloat(produto.preco),
                    quantidade: 1,
                    estoque: estoque
                });
            }
            
            updateCartDisplay();
            document.getElementById('productSearch').value = '';
            document.getElementById('searchResults').style.display = 'none';
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }
        
        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantidade + change;
            
            if (newQuantity <= 0) {
                removeFromCart(index);
            } else if (newQuantity > item.estoque) {
                alert(`Quantidade máxima disponível para "${item.nome}" é ${item.estoque} unidades!`);
            } else {
                item.quantidade = newQuantity;
                updateCartDisplay();
            }
        }
        
        function updateCartDisplay() {
            const cartContainer = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartItemCount');
            
            if (cart.length === 0) {
                cartContainer.innerHTML = '<div class="empty-cart">Nenhum produto adicionado ainda.</div>';
                cartCount.textContent = '0 itens';
            } else {
                let html = '';
                cart.forEach((item, index) => {
                    const subtotal = item.preco * item.quantidade;
                    const estoqueInfo = item.estoque ? `(Estoque: ${item.estoque} un.)` : '';
                    html += `
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.nome}</div>
                                <div class="cart-item-price">R$ ${item.preco.toFixed(2).replace('.', ',')} × ${item.quantidade} = R$ ${subtotal.toFixed(2).replace('.', ',')}</div>
                                <div class="cart-item-stock">${estoqueInfo}</div>
                            </div>
                            <div class="cart-item-controls">
                                <div class="quantity-controls">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity(${index}, -1)">-</button>
                                    <span class="quantity-display">${item.quantidade}</span>
                                    <button type="button" class="btn-quantity" onclick="updateQuantity(${index}, 1)">+</button>
                                </div>
                                <button type="button" class="btn-remove-item" onclick="removeFromCart(${index})">Remover</button>
                            </div>
                        </div>
                    `;
                });
                cartContainer.innerHTML = html;
                
                const totalItems = cart.reduce((sum, item) => sum + item.quantidade, 0);
                cartCount.textContent = `${totalItems} ${totalItems === 1 ? 'item' : 'itens'}`;
            }
            
            updateCartTotal();
        }
        
        function updateCartTotal() {
            const subtotal = cart.reduce((total, item) => total + (item.preco * item.quantidade), 0);
            const desconto = parseFloat(document.getElementById('createDesconto').value) || 0;
            const total = subtotal - desconto;
            
            document.getElementById('cartTotal').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
        }
        
        function confirmDelete(orderId) {
            orderToDelete = orderId;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            orderToDelete = null;
        }
        
        function deleteOrder() {
            if (orderToDelete) {
                document.getElementById('deleteOrderId').value = orderToDelete;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function openEditModal(pedido) {
            document.getElementById('editOrderId').value = pedido.id;
            document.getElementById('editNomeCompleto').value = pedido.nome_completo || '';
            document.getElementById('editWhatsapp').value = pedido.whatsapp || '';
            document.getElementById('editDesconto').value = pedido.desconto || 0;
            document.getElementById('editTaxaEntrega').value = pedido.taxa_entrega || 0;
            document.getElementById('editEntregarEndereco').checked = pedido.entregar_endereco == 1;
            document.getElementById('editRua').value = pedido.rua || '';
            document.getElementById('editNumero').value = pedido.numero || '';
            document.getElementById('editBairro').value = pedido.bairro || '';
            document.getElementById('editCidade').value = pedido.cidade || '';
            document.getElementById('editCep').value = pedido.cep || '';
            document.getElementById('editObservacoes').value = pedido.observacoes || '';
            
            // Setar formas de pagamento
            document.getElementById('editPagamentoPix').checked = pedido.pagamento_pix == 1;
            document.getElementById('editPagamentoCartao').checked = pedido.pagamento_cartao == 1;
            document.getElementById('editPagamentoDinheiro').checked = pedido.pagamento_dinheiro == 1;

            toggleEditAddressFields();
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function toggleCreateAddressFields() {
            const checkbox = document.getElementById('createEntregarEndereco');
            const addressFields = document.getElementById('createAddressFields');
            
            if (checkbox.checked) {
                addressFields.style.display = 'block';
            } else {
                addressFields.style.display = 'none';
            }
        }
        
        function toggleEditAddressFields() {
            const checkbox = document.getElementById('editEntregarEndereco');
            const addressFields = document.getElementById('editAddressFields');
            
            if (checkbox.checked) {
                addressFields.style.display = 'block';
            } else {
                addressFields.style.display = 'none';
            }
        }
        
        // Event listeners
        document.getElementById('createEntregarEndereco').addEventListener('change', toggleCreateAddressFields);
        document.getElementById('editEntregarEndereco').addEventListener('change', toggleEditAddressFields);
        
        // Event listener para o formulário de criação
        document.getElementById('createForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Adicione pelo menos um produto ao pedido!');
                return;
            }
            
            // Adicionar os produtos do carrinho ao campo hidden
            document.getElementById('itens_pedido').value = JSON.stringify(cart);
        });
        
        // Fechar resultados de busca ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.product-search-section')) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });
        
        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const editModal = document.getElementById('editModal');
            const createModal = document.getElementById('createModal');
            
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            
            if (event.target === editModal) {
                closeEditModal();
            }
            
            if (event.target === createModal) {
                closeCreateModal();
            }
        }
        
        // Preservar filtro após ações
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form[method="POST"]');
            const currentFilter = new URLSearchParams(window.location.search).get('filtro') || 'todos';
            
            forms.forEach(form => {
                if (!form.querySelector('input[name="filtro"]')) {
                    const filterInput = document.createElement('input');
                    filterInput.type = 'hidden';
                    filterInput.name = 'filtro';
                    filterInput.value = currentFilter;
                    form.appendChild(filterInput);
                }
            });
        });
        
        // Controle do menu mobile
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.getElementById('mobile-menu-toggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            // Toggle do menu
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                mobileToggle.classList.toggle('active');
            });
            
            // Fechar menu ao clicar em um link
            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    mobileToggle.classList.remove('active');
                });
            });
            
            // Fechar menu ao clicar no overlay
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                mobileToggle.classList.remove('active');
            });
        });

        // Função para enviar pagamento
        function enviarPagamento(pedidoId) {
            const modal = document.getElementById('paymentModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDetails = document.getElementById('modalDetails');
            
            // Mostrar modal de carregamento
            modalTitle.textContent = '💳 Enviando Pagamento...';
            modalMessage.textContent = 'Processando pagamento e enviando WhatsApp...';
            modalDetails.style.display = 'none';
            modal.style.display = 'block';
            
            // Fazer requisição AJAX
            fetch('mercadopago_create_payment.php?pedido_id=' + pedidoId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = '✅ Pagamento Criado!';
                        modalMessage.textContent = data.message;
                        
                        // Mostrar detalhes
                        document.getElementById('modalPedido').textContent = '#' + data.pedido_id;
                        document.getElementById('modalCliente').textContent = data.cliente;
                        document.getElementById('modalValor').textContent = 'R$ ' + data.valor;
                        document.getElementById('modalLink').textContent = data.payment_url;
                        document.getElementById('modalLink').href = data.payment_url;
                        modalDetails.style.display = 'block';
                        
                        // Recarregar página após 3 segundos
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        modalTitle.textContent = '❌ Erro!';
                        modalMessage.textContent = data.error;
                        modalDetails.style.display = 'none';
                    }
                })
                .catch(error => {
                    modalTitle.textContent = '❌ Erro!';
                    modalMessage.textContent = 'Erro ao processar pagamento: ' + error.message;
                    modalDetails.style.display = 'none';
                });
        }

        // Função para fechar modal
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const editModal = document.getElementById('editModal');
            const createModal = document.getElementById('createModal');
            const paymentModal = document.getElementById('paymentModal');
            
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            
            if (event.target === editModal) {
                closeEditModal();
            }
            
            if (event.target === createModal) {
                closeCreateModal();
            }
            
            if (event.target === paymentModal) {
                paymentModal.style.display = 'none';
            }
        }
    </script>

    <!-- Modal de Confirmação de Pagamento -->
    <div id="paymentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">💳 Enviando Pagamento...</h3>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalMessage">Processando...</div>
                <div id="modalDetails" style="margin-top: 15px; display: none;">
                    <p><strong>Pedido:</strong> <span id="modalPedido"></span></p>
                    <p><strong>Cliente:</strong> <span id="modalCliente"></span></p>
                    <p><strong>Valor:</strong> <span id="modalValor"></span></p>
                    <p><strong>Link:</strong> <a id="modalLink" href="#" target="_blank"></a></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closePaymentModal()">OK</button>
            </div>
        </div>
    </div>

    <style>
        /* Modal específico para pagamento */
        #paymentModal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        #paymentModal .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        #paymentModal .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        #paymentModal .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        #paymentModal .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        #paymentModal .close:hover {
            color: #000;
        }
        
        #paymentModal .modal-body {
            padding: 20px;
        }
        
        #paymentModal .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        
        #paymentModal .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        #paymentModal .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        #paymentModal .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</body>
</html>

