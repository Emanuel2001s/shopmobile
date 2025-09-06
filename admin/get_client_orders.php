<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../database/db_connect_env.php';

header('Content-Type: application/json');

// Função para normalizar número de WhatsApp
function normalizarWhatsApp($whatsapp) {
    $numero = preg_replace('/[^0-9]/', '', $whatsapp);
    if (strlen($numero) > 11 && substr($numero, 0, 2) === '55') {
        $numero = substr($numero, 2);
    }
    return $numero;
}

$cliente_id = $_GET['cliente_id'] ?? 0;

if ($cliente_id > 0) {
    try {
        // Primeiro, buscar o WhatsApp do cliente
        $stmt = $conn->prepare("SELECT whatsapp FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            echo json_encode([]);
            exit;
        }
        
        $whatsapp_normalizado = normalizarWhatsApp($cliente['whatsapp']);
        
        // Buscar todos os pedidos relacionados (por cliente_id ou WhatsApp similar)
        $stmt = $conn->prepare("
            SELECT DISTINCT p.id, p.data_pedido, p.status, p.whatsapp
            FROM pedidos p
            WHERE p.cliente_id = ? 
               OR p.whatsapp = ? 
               OR p.whatsapp = ?
               OR p.whatsapp = ?
            ORDER BY p.data_pedido DESC
        ");
        $stmt->execute([
            $cliente_id, 
            $cliente['whatsapp'],
            $whatsapp_normalizado,
            '55' . $whatsapp_normalizado
        ]);
        $pedidos_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pedidos = [];
        foreach ($pedidos_ids as $pedido) {
            // Para cada pedido, buscar os itens
            $stmt_itens = $conn->prepare("
                SELECT pi.nome_produto, pi.quantidade, pi.preco_unitario, pi.subtotal
                FROM pedido_itens pi
                WHERE pi.pedido_id = ?
                ORDER BY pi.id
            ");
            $stmt_itens->execute([$pedido['id']]);
            $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
            
            // Se não encontrou itens, buscar pelo produto_id (pedidos antigos)
            if (empty($itens)) {
                $stmt_produto = $conn->prepare("
                    SELECT p.produto_id, pr.nome, pr.preco
                    FROM pedidos p
                    JOIN produtos pr ON p.produto_id = pr.id
                    WHERE p.id = ?
                ");
                $stmt_produto->execute([$pedido['id']]);
                $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);
                
                if ($produto) {
                    $itens = [[
                        'nome_produto' => $produto['nome'],
                        'quantidade' => 1,
                        'preco_unitario' => $produto['preco'],
                        'subtotal' => $produto['preco']
                    ]];
                }
            }
            
            $pedidos[] = [
                'id' => $pedido['id'],
                'data_pedido' => $pedido['data_pedido'],
                'status' => $pedido['status'],
                'itens' => $itens
            ];
        }
        
        echo json_encode($pedidos);
        
    } catch (PDOException $e) {
        error_log('Erro ao buscar pedidos do cliente: ' . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar pedidos', 'detalhe' => $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro geral', 'detalhe' => $e->getMessage()]);
    }
} else {
    echo json_encode([]);
}
?>

