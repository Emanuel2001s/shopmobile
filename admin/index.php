<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

// Filtro de período para vendas
$periodo = $_GET['periodo'] ?? 'hoje';
$data_filtro = '';

switch ($periodo) {
    case 'hoje':
        $data_filtro = "AND DATE(p.data_pedido) = CURDATE()";
        break;
    case 'semana':
        $data_filtro = "AND p.data_pedido >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'mes':
        $data_filtro = "AND p.data_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    default:
        $data_filtro = "";
}

// Buscar estatísticas
$stats = [];

// Total de produtos
$result = $conn->query("SELECT COUNT(*) as total FROM produtos");
$stats["produtos"] = $result->fetch(PDO::FETCH_ASSOC)["total"];

// Total de categorias
$result = $conn->query("SELECT COUNT(*) as total FROM categorias");
$stats["categorias"] = $result->fetch(PDO::FETCH_ASSOC)["total"];

// Total de pedidos
$result = $conn->query("SELECT COUNT(*) as total FROM pedidos");
$stats["pedidos"] = $result->fetch(PDO::FETCH_ASSOC)["total"];

// Pedidos confirmados
$result = $conn->query("SELECT COUNT(*) as total FROM pedidos WHERE status = 'confirmado'");
$stats["vendas"] = $result->fetch(PDO::FETCH_ASSOC)["total"];

// Produtos sem estoque
$result = $conn->query("SELECT COUNT(*) as total FROM produtos WHERE estoque = 0");
$stats["sem_estoque"] = $result->fetch(PDO::FETCH_ASSOC)["total"];

// Valor total em vendas (corrigido para usar pedido_itens e valor_total dos pedidos)
$sql_vendas = "SELECT 
                SUM(p.valor_total) as total_vendas, 
                COUNT(*) as qtd_vendas 
               FROM pedidos p 
               WHERE p.status = 'confirmado' $data_filtro";
$result = $conn->query($sql_vendas);
$vendas_data = $result->fetch(PDO::FETCH_ASSOC);
$stats["valor_vendas"] = $vendas_data["total_vendas"] ?? 0;
$stats["qtd_vendas_periodo"] = $vendas_data["qtd_vendas"] ?? 0;

// Últimos pedidos (corrigido para mostrar produtos da tabela pedido_itens)
$sql_ultimos_pedidos = "SELECT p.*, 
                        GROUP_CONCAT(DISTINCT pi.nome_produto SEPARATOR ', ') as produtos_nomes,
                        p.valor_total as valor_pedido
                        FROM pedidos p 
                        LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
                        GROUP BY p.id
                        ORDER BY p.data_pedido DESC 
                        LIMIT 5";

$result = $conn->query($sql_ultimos_pedidos);
$ultimos_pedidos = $result->fetchAll(PDO::FETCH_ASSOC);

// Para pedidos que não têm itens na tabela pedido_itens (compatibilidade com pedidos antigos)
foreach ($ultimos_pedidos as &$pedido) {
    if (empty($pedido['produtos_nomes']) && !empty($pedido['produto_id'])) {
        // Buscar produtos antigos usando o campo produto_id
        $produto_ids = array_filter(array_map('trim', explode(',', $pedido['produto_id'])));
        if (!empty($produto_ids)) {
            $placeholders = implode(',', array_fill(0, count($produto_ids), '?'));
            $stmt = $conn->prepare("SELECT GROUP_CONCAT(nome SEPARATOR ', ') as nomes FROM produtos WHERE id IN ($placeholders)");
            $stmt->execute($produto_ids);
            $produtos_antigos = $stmt->fetch(PDO::FETCH_ASSOC);
            $pedido['produtos_nomes'] = $produtos_antigos['nomes'] ?? 'Produto não encontrado';
        }
    }
    
    // Se ainda não tem produtos, mostrar mensagem padrão
    if (empty($pedido['produtos_nomes'])) {
        $pedido['produtos_nomes'] = 'Sem produtos';
    }
}
unset($pedido); // Quebrar a referência do último elemento
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Painel Admin</title>
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
        
        /* Header */
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
            z-index: 1;
        }

        .header h1 {
            color: white;
            margin-bottom: 5px;
        }

        .header p {
            color: #bdc3c7;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 0.5rem;
            box-shadow: 0 2px 4px rgba(39, 174, 96, 0.3);
        }

        .status-indicator i {
            color: #2ecc71;
            font-size: 0.8rem;
        }
        
        /* Menu hambúrguer para mobile */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 0.8rem;
            background: #34495e;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 45px;
            height: 45px;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
            z-index: 1001;
            border: none;
            outline: none;
            position: relative;
            pointer-events: auto;
        }

        .mobile-menu-toggle span {
            width: 20px;
            height: 2px;
            background: white;
            margin: 2px 0;
            transition: 0.3s ease;
            border-radius: 1px;
            display: block;
        }

        .mobile-menu-toggle.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-3px, 3px);
        }

        .mobile-menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-toggle.active span:nth-child(3) {
            transform: rotate(45deg) translate(-3px, -3px);
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
            z-index: 998;
            pointer-events: none;
        }
        
        .mobile-overlay.active {
            display: block;
            pointer-events: auto;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .admin-sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .admin-sidebar.collapsed .sidebar-header {
            padding: 1.5rem 0.5rem;
            justify-content: center;
        }
        
        .sidebar-header h3 {
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
        
        /* Conteúdo Principal */
        .admin-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.clickable {
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            position: relative;
            z-index: 1;
            pointer-events: auto;
        }
        
        .stat-card.alert::before {
            background: linear-gradient(90deg, #ff6b6b, #ee5a24);
        }
        
        .stat-card.sales::before {
            background: linear-gradient(90deg, #00d2d3, #54a0ff);
        }
        
        .stat-card .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-card .label {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .stat-card .sublabel {
            color: #95a5a6;
            font-size: 0.85rem;
            font-style: italic;
        }
        
        .sales-filter {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .sales-filter label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.6rem 1.2rem;
            border: 2px solid #e9ecef;
            background: white;
            color: #6c757d;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            cursor: pointer;
            pointer-events: auto;
            position: relative;
            z-index: 1;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .recent-orders {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .recent-orders h2 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .orders-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pendente {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            color: #d63031;
        }
        
        .status-confirmado {
            background: linear-gradient(135deg, #55efc4 0%, #00b894 100%);
            color: #00695c;
        }
        
        .status-cancelado {
            background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);
            color: #ffffff;
        }
        
        .price-tag {
            font-weight: 600;
            color: #00b894;
            font-size: 0.95rem;
        }
        

        
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .admin-sidebar {
                position: fixed;
                left: -100%;
                z-index: 1000;
            }
            
            .admin-sidebar.active {
                left: 0;
            }
            
            .admin-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .header p {
                margin-bottom: 0.5rem;
            }
            
            .status-indicator {
                align-self: center;
            }
            
            .mobile-menu-toggle {
                display: flex !important;
                position: absolute;
                top: 1rem;
                right: 1rem;
                z-index: 1001;
                pointer-events: auto;
                touch-action: manipulation;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-card .number {
                font-size: 2rem;
            }
            
            .sales-filter {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (min-width: 769px) {
            .admin-sidebar {
                display: block !important;
                left: 0 !important;
                top: 0 !important;
            }
        }
        
        @media (max-width: 480px) {
            .filter-buttons {
                width: 100%;
            }
            
            .filter-btn {
                flex: 1;
                text-align: center;
            }
            
            .admin-sidebar {
                width: 260px;
            }
        }
        
        @media (max-width: 768px) {
            .orders-table {
                width: 100%;
                border-collapse: collapse;
                display: block;
                overflow-x: auto;
            }

            .orders-table thead {
                display: none;
            }

            .orders-table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px;
                background: #fff;
            }

            .orders-table tbody td {
                display: flex;
                justify-content: space-between;
                padding: 8px 10px;
                font-size: 14px;
                border: none;
                border-bottom: 1px solid #eee;
            }

            .orders-table tbody td::before {
                content: attr(data-label);
                font-weight: bold;
                color: #555;
            }

            .orders-table tbody td:last-child {
                border-bottom: none;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>
    

    

    
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <h3>🛍️ Painel Admin</h3>
                <p>Gerencie sua loja</p>
            </div>
            
            <nav>
                <a href="index.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                                <a href="products.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" data-tooltip="Gerenciar Produtos">
                    <i class="fas fa-box"></i>
                    <span>Produtos</span>
                </a>
                
                <a href="categories.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" data-tooltip="Gerenciar Categorias">
                    <i class="fas fa-folder"></i>
                    <span>Categorias</span>
                </a>
                
                
                <a href="orders.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" data-tooltip="Gerenciar Pedidos">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Pedidos</span>
                </a>
                
                <a href="payment_monitor.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'payment_monitor.php' ? 'active' : ''; ?>" data-tooltip="Monitor de Pagamentos">
                    <i class="fas fa-credit-card"></i>
                    <span>Monitor de Pagamentos</span>
                </a>
                
                <a href="clientes.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>" data-tooltip="Clientes">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                
                <a href="sliders.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'sliders.php' ? 'active' : ''; ?>" data-tooltip="Sliders">
                    <i class="fas fa-images"></i>
                    <span>Sliders</span>
                </a>
                
                <a href="../index.php" class="sidebar-item" target="_blank" data-tooltip="Ver Loja">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Ver Loja</span>
                </a>
                
                <a href="configuracoes.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'configuracoes.php' ? 'active' : ''; ?>" data-tooltip="Configurações">
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
            <div class="header">
                <div>
                    <h1>🛍️ Painel Administrativo</h1>
                    <p>Gerencie sua loja de forma simples e eficiente</p>
                    <div class="status-indicator">
                        <i class="fas fa-circle"></i>
                        <span>Conectado</span>
                    </div>
                </div>
                <!-- Menu hambúrguer para mobile -->
                <div class="mobile-menu-toggle" id="mobile-menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            
            <!-- Overlay para mobile -->
            <div class="mobile-overlay" id="mobile-overlay"></div>

            <div class="container">
                <div class="sales-filter">
                    <label>💰 Filtrar vendas por período:</label>
                    <div class="filter-buttons">
                        <a href="?periodo=hoje" class="filter-btn <?php echo $periodo === 'hoje' ? 'active' : ''; ?>">Hoje</a>
                        <a href="?periodo=semana" class="filter-btn <?php echo $periodo === 'semana' ? 'active' : ''; ?>">Esta Semana</a>
                        <a href="?periodo=mes" class="filter-btn <?php echo $periodo === 'mes' ? 'active' : ''; ?>">Este Mês</a>
                        <a href="?periodo=total" class="filter-btn <?php echo $periodo === 'total' ? 'active' : ''; ?>">Total</a>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon">📦</div>
                        <div class="number"><?php echo $stats['produtos']; ?></div>
                        <div class="label">Produtos Cadastrados</div>
                        <div class="sublabel">Total no sistema</div>
                    </div>
                    
                    <div class="stat-card sales">
                        <div class="icon">💰</div>
                        <div class="number">R$ <?php echo number_format($stats['valor_vendas'], 2, ',', '.'); ?></div>
                        <div class="label">Valor em Vendas</div>
                        <div class="sublabel">
                            <?php 
                            $periodo_label = [
                                'hoje' => 'Hoje',
                                'semana' => 'Esta semana',
                                'mes' => 'Este mês',
                                'total' => 'Total geral'
                            ];
                            echo $periodo_label[$periodo] . ' • ' . $stats['qtd_vendas_periodo'] . ' vendas';
                            ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon">✅</div>
                        <div class="number"><?php echo $stats['vendas']; ?></div>
                        <div class="label">Vendas Confirmadas</div>
                        <div class="sublabel">Total confirmado</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon">📂</div>
                        <div class="number"><?php echo $stats['categorias']; ?></div>
                        <div class="label">Categorias Ativas</div>
                        <div class="sublabel">Organizando produtos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon">🛒</div>
                        <div class="number"><?php echo $stats['pedidos']; ?></div>
                        <div class="label">Pedidos Realizados</div>
                        <div class="sublabel">Todos os status</div>
                    </div>
                    
                    <a href="stock_alert.php" class="stat-card clickable alert">
                        <div class="icon" style="color: #e74c3c;">⚠️</div>
                        <div class="number" style="color: #e74c3c;"><?php echo $stats['sem_estoque']; ?></div>
                        <div class="label">Produtos Sem Estoque</div>
                        <div class="sublabel">Requer atenção</div>
                    </a>
                </div>
                
                <div class="recent-orders">
                    <h2>📋 Últimos Pedidos</h2>
                    <?php if (count($ultimos_pedidos) > 0): ?>
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Produto(s)</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_pedidos as $pedido): ?>
                                <tr>
                                    <td data-label="ID"><strong>#<?php echo $pedido['id']; ?></strong></td>
                                    <td data-label="Cliente"><?php echo htmlspecialchars($pedido['nome_completo']); ?></td>
                                    <td data-label="Produto(s)"><?php echo htmlspecialchars($pedido['produtos_nomes']); ?></td>
                                    <td data-label="Valor"><span class="price-tag">R$ <?php echo number_format($pedido['valor_pedido'], 2, ',', '.'); ?></span></td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo $pedido['status']; ?>">
                                            <?php echo ucfirst($pedido['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; color: #7f8c8d; padding: 3rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                            <p style="font-size: 1.1rem;">Nenhum pedido encontrado ainda.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Menu hambúrguer para mobile
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const sidebar = document.getElementById('adminSidebar');
        const mobileOverlay = document.getElementById('mobile-overlay');
        
        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', function() {
                mobileMenuToggle.classList.toggle('active');
                sidebar.classList.toggle('active');
                if (mobileOverlay) {
                    mobileOverlay.classList.toggle('active');
                }
            });
            
            // Fechar menu ao clicar no overlay
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function() {
                    mobileMenuToggle.classList.remove('active');
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                });
            }
            
            // Fechar menu ao clicar em um link
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenuToggle.classList.remove('active');
                    sidebar.classList.remove('active');
                    if (mobileOverlay) {
                        mobileOverlay.classList.remove('active');
                    }
                });
            });
        }
    </script>
</body>
</html>

