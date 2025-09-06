<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

$message = "";
$message_type = "";

// Processar exclusão de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $pedido_id = $_POST['pedido_id'] ?? 0;
    
    if ($pedido_id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM pedidos WHERE id = ?");
            
            if ($stmt->execute([$pedido_id])) {
                $message = "Pedido excluído com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao excluir pedido!";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "Erro ao excluir pedido: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Processar alteração de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $pedido_id = $_POST['pedido_id'] ?? 0;
    $novo_status = $_POST['status'] ?? '';
    
    if ($pedido_id > 0 && in_array($novo_status, ['pendente', 'confirmado', 'cancelado'])) {
        $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        
        if ($stmt->execute([$novo_status, $pedido_id])) {
            $message = "Status do pedido atualizado com sucesso!";
            $message_type = "success";
        } else {
            $message = "Erro ao atualizar status do pedido!";
            $message_type = "error";
        }
    }
}

// Buscar pedidos com informações do produto
$stmt = $conn->prepare("SELECT p.*, pr.nome as produto_nome, pr.preco as produto_preco 
                       FROM pedidos p 
                       LEFT JOIN produtos pr ON p.produto_id = pr.id 
                       ORDER BY p.data_pedido DESC");
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats = [];
$stats['total'] = count($pedidos);
$stats['pendentes'] = count(array_filter($pedidos, fn($p) => $p['status'] === 'pendente'));
$stats['confirmados'] = count(array_filter($pedidos, fn($p) => $p['status'] === 'confirmado'));
$stats['cancelados'] = count(array_filter($pedidos, fn($p) => $p['status'] === 'cancelado'));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Painel Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
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
        }
        
        .section-header h2 {
            color: #333;
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
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .orders-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
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
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        
        .modal h3 {
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        .modal p {
            margin-bottom: 2rem;
            color: #666;
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
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .status-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-form {
                justify-content: stretch;
            }
            
            .status-select {
                flex: 1;
            }
            
            .actions-section {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛒 Gerenciar Pedidos</h1>
        <a href="index.php" class="btn-back">← Voltar ao Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
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
            </div>
            
            <?php if (count($pedidos) > 0): ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">Pedido #<?php echo $pedido['id']; ?></div>
                            <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></div>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($pedido['produto_nome']); ?></div>
                            <div class="product-price">R$ <?php echo number_format($pedido['produto_preco'], 2, ',', '.'); ?></div>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-group">
                                <h4>Informações do Cliente</h4>
                                <p><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['nome_completo']); ?></p>
                                <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($pedido['whatsapp']); ?></p>
                                <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $pedido['whatsapp']); ?>" 
                                   class="whatsapp-link" target="_blank">
                                    <span class="icon">📱</span>
                                    Conversar no WhatsApp
                                </a>
                            </div>
                            
                            <div class="detail-group">
                                <h4>Entrega</h4>
                                <?php if ($pedido['entregar_endereco']): ?>
                                    <div class="address-info">
                                        <h4>📍 Endereço de Entrega:</h4>
                                        <p><?php echo htmlspecialchars($pedido['rua']); ?>, <?php echo htmlspecialchars($pedido['numero']); ?></p>
                                        <p><?php echo htmlspecialchars($pedido['bairro']); ?> - <?php echo htmlspecialchars($pedido['cidade']); ?></p>
                                        <p>CEP: <?php echo htmlspecialchars($pedido['cep']); ?></p>
                                    </div>
                                <?php else: ?>
                                    <p>🏪 <strong>Retirada no local</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
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
                            <button type="button" class="btn-delete" onclick="confirmDelete(<?php echo $pedido['id']; ?>)">
                                🗑️ Excluir Pedido
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 3rem; text-align: center; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">🛒</div>
                    <h3>Nenhum pedido encontrado</h3>
                    <p>Os pedidos aparecerão aqui quando os clientes fizerem compras.</p>
                </div>
            <?php endif; ?>
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
    
    <!-- Formulário oculto para exclusão -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_order">
        <input type="hidden" name="pedido_id" id="deleteOrderId">
    </form>
    
    <script>
        let orderToDelete = null;
        
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
        
        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>

