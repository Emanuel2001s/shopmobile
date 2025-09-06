<?php
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

// Buscar estatísticas de pagamentos
$stats = [];
try {
    // Total de pagamentos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mercadopago");
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pagamentos pendentes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mercadopago WHERE status = 'pending'");
    $stmt->execute();
    $stats['pendentes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pagamentos aprovados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mercadopago WHERE status = 'approved'");
    $stmt->execute();
    $stats['aprovados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pagamentos em processamento
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mercadopago WHERE status = 'in_process'");
    $stmt->execute();
    $stats['processamento'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pagamentos rejeitados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mercadopago WHERE status = 'rejected'");
    $stmt->execute();
    $stats['rejeitados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
}

// Buscar últimos pagamentos
$pagamentos = [];
try {
    $stmt = $conn->prepare("
        SELECT mp.*, p.nome_completo, p.valor_total 
        FROM mercadopago mp 
        INNER JOIN pedidos p ON mp.pedido_id = p.id 
        ORDER BY mp.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar pagamentos: " . $e->getMessage());
}

// Verificar status da cron
$cron_status = "Parado";
$cron_log = "";
try {
    $log_file = '/var/log/apache2/error.log';
    if (file_exists($log_file)) {
        $lines = file($log_file);
        $lines = array_reverse($lines);
        
        foreach ($lines as $line) {
            if (strpos($line, 'CRON:') !== false) {
                $cron_status = "Ativo";
                $cron_log = trim($line);
                break;
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao verificar logs da cron: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Pagamentos - Painel Admin</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-total .stat-number { color: #3498db; }
        .stat-pendentes .stat-number { color: #f39c12; }
        .stat-aprovados .stat-number { color: #27ae60; }
        .stat-processamento .stat-number { color: #9b59b6; }
        .stat-rejeitados .stat-number { color: #e74c3c; }

        .cron-status {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .cron-status h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-stopped {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-purple {
            background: #9b59b6;
        }

        .btn-purple:hover {
            background: #8e44ad;
        }

        .payments-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            overflow-x: auto;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-in_process {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
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
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .payments-table {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                font-size: 0.9rem;
                min-width: 600px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.85rem;
                white-space: nowrap;
            }
            
            .mobile-overlay {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
                
                <a href="payment_monitor.php" class="sidebar-item active" data-tooltip="Monitor de Pagamentos">
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
                        <h1>💳 Monitor de Pagamentos</h1>
                        <p>Acompanhe o status de todos os pagamentos do Mercado Pago</p>
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
                    <div class="stat-card stat-total">
                        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="stat-label">Total de Pagamentos</div>
                    </div>
                    
                    <div class="stat-card stat-pendentes">
                        <div class="stat-number"><?php echo $stats['pendentes'] ?? 0; ?></div>
                        <div class="stat-label">Pendentes</div>
                    </div>
                    
                    <div class="stat-card stat-aprovados">
                        <div class="stat-number"><?php echo $stats['aprovados'] ?? 0; ?></div>
                        <div class="stat-label">Aprovados</div>
                    </div>
                    
                    <div class="stat-card stat-processamento">
                        <div class="stat-number"><?php echo $stats['processamento'] ?? 0; ?></div>
                        <div class="stat-label">Em Processamento</div>
                    </div>
                    
                    <div class="stat-card stat-rejeitados">
                        <div class="stat-number"><?php echo $stats['rejeitados'] ?? 0; ?></div>
                        <div class="stat-label">Rejeitados</div>
                    </div>
                </div>

                <!-- Status da Cron -->
                <div class="cron-status">
                    <h3>
                        <i class="fas fa-robot"></i>
                        Status da Verificação Automática
                    </h3>
                    
                    <div class="status-indicator <?php echo $cron_status === 'Ativo' ? 'status-active' : 'status-stopped'; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo $cron_status; ?>
                    </div>
                    
                    <?php if ($cron_log): ?>
                        <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                            Última execução: <?php echo htmlspecialchars($cron_log); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <button class="btn" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar Dados
                        </button>
                        
                        <button class="btn btn-success" onclick="executarVerificacao()">
                            <i class="fas fa-robot"></i>
                            Executar Verificação Manual
                        </button>
                        
                        <a href="view_cron_logs.php" class="btn btn-purple">
                            <i class="fas fa-clipboard-list"></i>
                            Ver Logs da Cron
                        </a>
                    </div>
                </div>

                <!-- Tabela de Pagamentos -->
                <div class="payments-table">
                    <div class="table-header">
                        <h2>
                            <i class="fas fa-list"></i>
                            Últimos Pagamentos
                        </h2>
                    </div>
                    
                    <?php if (count($pagamentos) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Pedido</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagamentos as $pagamento): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pagamento['nome_completo']); ?></td>
                                        <td>#<?php echo $pagamento['pedido_id']; ?></td>
                                        <td>R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $pagamento['status']; ?>">
                                                <?php 
                                                switch($pagamento['status']) {
                                                    case 'pending': echo 'Pendente'; break;
                                                    case 'approved': echo 'Aprovado'; break;
                                                    case 'in_process': echo 'Em Processamento'; break;
                                                    case 'rejected': echo 'Rejeitado'; break;
                                                    default: echo ucfirst($pagamento['status']);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($pagamento['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: #666;">
                            Nenhum pagamento encontrado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
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

        // Função para executar verificação manual
        function executarVerificacao() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executando...';
            btn.style.background = '#f39c12';

            fetch('../cron_check_payments.php?token=shopmobile_cron_2024')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Verificação executada com sucesso!\n\n' +
                              'Total verificados: ' + data.total_verificados + '\n' +
                              'Atualizados: ' + data.atualizados + '\n' +
                              'Erros: ' + data.erros);
                    } else {
                        alert('❌ Erro na verificação: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    alert('❌ Erro ao executar verificação: ' + error.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    btn.style.background = '#27ae60';
                    setTimeout(() => { location.reload(); }, 1000);
                });
        }
    </script>
</body>
</html> 