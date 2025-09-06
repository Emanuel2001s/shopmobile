<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

// Função para normalizar número de WhatsApp
function normalizarWhatsApp($whatsapp) {
    // Remove tudo que não é número
    $numero = preg_replace('/[^0-9]/', '', $whatsapp);
    
    // Se começa com 55 (código do Brasil), remove
    if (strlen($numero) > 11 && substr($numero, 0, 2) === '55') {
        $numero = substr($numero, 2);
    }
    
    // Se tem 11 dígitos (DDD + número), mantém
    if (strlen($numero) === 11) {
        return $numero;
    }
    
    // Se tem menos de 11 dígitos, pode estar incompleto
    return $numero;
}

// Buscar clientes únicos pelo WhatsApp normalizado
$stmt = $conn->prepare("
    SELECT 
        c.*,
        COUNT(p.id) as total_pedidos,
        COALESCE(SUM(p.valor_total), 0) as valor_total_pedidos
    FROM clientes c
    LEFT JOIN pedidos p ON c.id = p.cliente_id
    GROUP BY c.id
    ORDER BY c.nome_completo ASC
");
$stmt->execute();
$clientes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar clientes pelo WhatsApp normalizado
$clientes_agrupados = [];
foreach ($clientes_raw as $cliente) {
    $whatsapp_normalizado = normalizarWhatsApp($cliente['whatsapp']);
    
    if (!isset($clientes_agrupados[$whatsapp_normalizado])) {
        $clientes_agrupados[$whatsapp_normalizado] = [
            'nome_completo' => $cliente['nome_completo'],
            'whatsapp' => $cliente['whatsapp'],
            'whatsapp_normalizado' => $whatsapp_normalizado,
            'total_pedidos' => $cliente['total_pedidos'],
            'valor_total_pedidos' => $cliente['valor_total_pedidos'],
            'id' => $cliente['id']
        ];
    } else {
        // Se já existe, soma os valores
        $clientes_agrupados[$whatsapp_normalizado]['total_pedidos'] += $cliente['total_pedidos'];
        $clientes_agrupados[$whatsapp_normalizado]['valor_total_pedidos'] += $cliente['valor_total_pedidos'];
        
        // Se tem mais pedidos, usa o nome mais recente
        if ($cliente['total_pedidos'] > $clientes_agrupados[$whatsapp_normalizado]['total_pedidos']) {
            $clientes_agrupados[$whatsapp_normalizado]['nome_completo'] = $cliente['nome_completo'];
            $clientes_agrupados[$whatsapp_normalizado]['whatsapp'] = $cliente['whatsapp'];
            $clientes_agrupados[$whatsapp_normalizado]['id'] = $cliente['id'];
        }
    }
}

$clientes = array_values($clientes_agrupados);

// Buscar estatísticas gerais
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_clientes,
        (SELECT COUNT(*) FROM pedidos) as total_pedidos,
        (SELECT COALESCE(SUM(valor_total), 0) FROM pedidos) as receita_total
    FROM clientes
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Clientes - Painel Admin</title>
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

        /* Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.clients {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.orders {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.revenue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Seção de Clientes */
        .clients-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            margin: 0;
        }

        .section-title i {
            margin-right: 10px;
        }

        .clients-list {
            padding: 0;
        }

        .client-item {
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .client-item:hover {
            background-color: #f8f9fa;
        }

        .client-item:last-child {
            border-bottom: none;
        }

        .client-header {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .client-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-edit, .btn-delete {
            background: none;
            border: none;
            padding: 8px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-edit {
            color: #3498db;
        }

        .btn-edit:hover {
            background: #3498db;
            color: white;
        }

        .btn-delete {
            color: #e74c3c;
        }

        .btn-delete:hover {
            background: #e74c3c;
            color: white;
        }

        .client-info {
            flex: 1;
        }

        .client-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .client-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .client-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .client-detail i {
            color: #3498db;
        }

        .expand-icon {
            color: #bdc3c7;
            transition: transform 0.3s ease;
        }

        .client-item.expanded .expand-icon {
            transform: rotate(180deg);
        }

        .client-orders {
            display: none;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .client-item.expanded .client-orders {
            display: block;
        }

        .orders-header {
            padding: 15px 20px;
            background: #e9ecef;
            color: #2c3e50;
            font-weight: 600;
        }

        .orders-header i {
            margin-right: 8px;
        }

        .orders-content {
            padding: 20px;
        }

        .order-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .order-id {
            font-weight: 600;
            color: #2c3e50;
        }

        .order-date {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .order-products-list {
            margin-bottom: 10px;
        }

        .order-product-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .order-product-item:last-child {
            border-bottom: none;
        }

        .product-name-item {
            color: #2c3e50;
        }

        .product-subtotal-item {
            color: #27ae60;
            font-weight: 500;
        }

        .order-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
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

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
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

        /* Estilos para os modais */
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
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        .btn-primary, .btn-secondary, .btn-danger {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-card .stat-value {
                font-size: 1.5rem;
            }
            
            .client-details { 
                flex-direction: column; 
                gap: 0.5rem; 
            }
            
            .order-header { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 0.5rem; 
            }
            
            .mobile-overlay {
                display: none;
            }
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
                
                <a href="orders.php" class="sidebar-item" data-tooltip="Pedidos">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Pedidos</span>
                </a>
                
                <a href="payment_monitor.php" class="sidebar-item" data-tooltip="Monitor de Pagamentos">
                    <i class="fas fa-credit-card"></i>
                    <span>Monitor de Pagamentos</span>
                </a>
                
                <a href="clientes.php" class="sidebar-item active" data-tooltip="Clientes">
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
                        <h1><i class="fas fa-users" style="color: #3498db; margin-right: 10px;"></i>Gerenciar Clientes</h1>
                        <p>Visualize e gerencie todos os clientes da sua loja</p>
                    </div>
                    <!-- Botão hamburger para mobile -->
                    <div class="mobile-menu-toggle" id="mobile-menu-toggle">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon clients">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_clientes'] ?? 0; ?></div>
                        <div class="stat-label">Total de Clientes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_pedidos'] ?? 0; ?></div>
                        <div class="stat-label">Total de Pedidos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-value">R$ <?php echo number_format($stats['receita_total'] ?? 0, 2, ',', '.'); ?></div>
                        <div class="stat-label">Receita Total</div>
                    </div>
                </div>
                
                <!-- Lista de Clientes -->
                <div class="clients-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-list"></i> Lista de Clientes
                        </h2>
                        <span><?php echo count($clientes); ?> cliente(s)</span>
                    </div>
                    <div class="clients-list">
                        <?php if (count($clientes) > 0): ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <div class="client-item" data-id="<?php echo $cliente['id']; ?>" onclick="toggleClientOrders(this)">
                                    <div class="client-header">
                                        <div class="client-info">
                                            <div class="client-name"><?php echo htmlspecialchars($cliente['nome_completo']); ?></div>
                                            <div class="client-details">
                                                <div class="client-detail">
                                                    <i class="fab fa-whatsapp"></i>
                                                    <?php echo htmlspecialchars($cliente['whatsapp'] ?? ''); ?>
                                                </div>
                                                <div class="client-detail">
                                                    <i class="fas fa-shopping-cart"></i>
                                                    <?php echo $cliente['total_pedidos'] ?? 0; ?> pedido(s)
                                                </div>
                                                <div class="client-detail">
                                                    <i class="fas fa-dollar-sign"></i>
                                                    R$ <?php echo number_format($cliente['valor_total_pedidos'] ?? 0, 2, ',', '.'); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="client-actions">
                                            <button class="btn-edit" onclick="editCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nome_completo']); ?>', '<?php echo htmlspecialchars($cliente['whatsapp']); ?>')" title="Editar Cliente">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete" onclick="deleteCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nome_completo']); ?>')" title="Excluir Cliente">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <i class="fas fa-chevron-down expand-icon"></i>
                                        </div>
                                    </div>
                                    <div class="client-orders" id="orders-<?php echo $cliente['id']; ?>">
                                        <div class="orders-header">
                                            <i class="fas fa-history"></i> Histórico de Pedidos
                                        </div>
                                        <div class="orders-content"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>Nenhum cliente cadastrado</h3>
                                <p>Os clientes aparecerão aqui automaticamente quando fizerem pedidos</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Editar Cliente -->
    <div id="editClienteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Cliente</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editClienteForm">
                <div class="modal-body">
                    <input type="hidden" id="editClienteId" name="cliente_id">
                    <div class="form-group">
                        <label for="editNomeCompleto">Nome Completo:</label>
                        <input type="text" id="editNomeCompleto" name="nome_completo" required>
                    </div>
                    <div class="form-group">
                        <label for="editWhatsapp">WhatsApp:</label>
                        <input type="text" id="editWhatsapp" name="whatsapp" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Confirmar Exclusão -->
    <div id="deleteClienteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Confirmar Exclusão</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o cliente <strong id="deleteClienteNome"></strong>?</p>
                <p style="color: #e74c3c; font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-warning"></i> Esta ação não pode ser desfeita e também excluirá todos os pedidos associados a este cliente.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeDeleteModal()" class="btn-secondary">Cancelar</button>
                <button type="button" onclick="confirmDeleteCliente()" class="btn-danger">Excluir Cliente</button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleClientOrders(clientElement) {
            const clienteId = clientElement.getAttribute('data-id');
            const ordersDiv = clientElement.querySelector('.client-orders');
            const ordersContent = ordersDiv.querySelector('.orders-content');
            if (clientElement.classList.contains('expanded')) {
                clientElement.classList.remove('expanded');
            } else {
                document.querySelectorAll('.client-item.expanded').forEach(item => {
                    item.classList.remove('expanded');
                });
                clientElement.classList.add('expanded');
                if (ordersContent.innerHTML.trim() === '') {
                    loadClientOrders(clienteId, ordersContent);
                }
            }
        }
        
        async function loadClientOrders(clienteId, ordersContent) {
            ordersContent.innerHTML = '<div style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
            try {
                const response = await fetch(`get_client_orders.php?cliente_id=${encodeURIComponent(clienteId)}`);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const orders = await response.json();
                if (orders.length === 0) {
                    ordersContent.innerHTML = '<div style="text-align: center; padding: 1rem; color: #666;">Nenhum pedido encontrado</div>';
                    return;
                }
                ordersContent.innerHTML = orders.map(order => `
                    <div class="order-item">
                        <div class="order-header">
                            <span class="order-id">Pedido #${order.id}</span>
                            <span class="order-date">${order.data_pedido}</span>
                        </div>
                        <div class="order-products-list">
                            ${order.itens.map(item => `
                                <div class="order-product-item">
                                    <span class="product-name-item">${item.nome_produto} (x${item.quantidade})</span>
                                    <span class="product-subtotal-item">R$ ${parseFloat(item.subtotal).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                </div>
                            `).join('')}
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                            <span class="order-status status-${order.status}">${order.status}</span>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Erro ao carregar pedidos:', error);
                ordersContent.innerHTML = '<div style="text-align: center; padding: 1rem; color: #e74c3c;">Erro ao carregar pedidos</div>';
            }
        }
        
        // Funções para editar cliente
        function editCliente(id, nome, whatsapp) {
            event.stopPropagation(); // Evita que o clique propague para o toggle
            document.getElementById('editClienteId').value = id;
            document.getElementById('editNomeCompleto').value = nome;
            document.getElementById('editWhatsapp').value = whatsapp;
            document.getElementById('editClienteModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editClienteModal').style.display = 'none';
        }

        // Funções para excluir cliente
        function deleteCliente(id, nome) {
            event.stopPropagation(); // Evita que o clique propague para o toggle
            document.getElementById('deleteClienteNome').textContent = nome;
            document.getElementById('deleteClienteModal').setAttribute('data-cliente-id', id);
            document.getElementById('deleteClienteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteClienteModal').style.display = 'none';
        }

        function confirmDeleteCliente() {
            const clienteId = document.getElementById('deleteClienteModal').getAttribute('data-cliente-id');
            
            fetch('delete_cliente.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'cliente_id=' + encodeURIComponent(clienteId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cliente excluído com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao excluir cliente: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir cliente');
            });
        }

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

            // Configurar formulário de edição
            document.getElementById('editClienteForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('edit_cliente.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cliente atualizado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao atualizar cliente: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao atualizar cliente');
                });
            });

            // Fechar modais ao clicar fora deles
            window.onclick = function(event) {
                const editModal = document.getElementById('editClienteModal');
                const deleteModal = document.getElementById('deleteClienteModal');
                
                if (event.target === editModal) {
                    closeEditModal();
                }
                if (event.target === deleteModal) {
                    closeDeleteModal();
                }
            }
        });
    </script>
</body>
</html>

