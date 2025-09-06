<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$cliente_id = $_POST['cliente_id'] ?? 0;

// Validações
if (empty($cliente_id) || !is_numeric($cliente_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do cliente inválido']);
    exit;
}

try {
    // Verificar se o cliente existe
    $stmt = $conn->prepare("SELECT id, nome_completo FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }

    // Iniciar transação
    $conn->beginTransaction();

    try {
        // Buscar todos os pedidos do cliente
        $stmt = $conn->prepare("SELECT id FROM pedidos WHERE cliente_id = ?");
        $stmt->execute([$cliente_id]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Excluir itens dos pedidos
        foreach ($pedidos as $pedido) {
            $stmt = $conn->prepare("DELETE FROM pedido_itens WHERE pedido_id = ?");
            $stmt->execute([$pedido['id']]);
        }

        // Excluir pagamentos dos pedidos
        foreach ($pedidos as $pedido) {
            $stmt = $conn->prepare("DELETE FROM pagamentos WHERE pedido_id = ?");
            $stmt->execute([$pedido['id']]);
        }

        // Excluir registros do Mercado Pago
        foreach ($pedidos as $pedido) {
            $stmt = $conn->prepare("DELETE FROM mercadopago WHERE pedido_id = ?");
            $stmt->execute([$pedido['id']]);
        }

        // Excluir os pedidos
        $stmt = $conn->prepare("DELETE FROM pedidos WHERE cliente_id = ?");
        $stmt->execute([$cliente_id]);

        // Excluir o cliente
        $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);

        // Confirmar transação
        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Cliente "' . $cliente['nome_completo'] . '" excluído com sucesso'
        ]);

    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log('Erro ao excluir cliente: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
} catch (Exception $e) {
    error_log('Erro geral ao excluir cliente: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?> 