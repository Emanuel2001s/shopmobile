<?php
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

$message = "";
$message_type = "";

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nome = trim($_POST['nome'] ?? '');
        $status = $_POST['status'] ?? 'ativo';
        
        if (!empty($nome)) {
            $stmt = $conn->prepare("INSERT INTO categorias (nome, status) VALUES (?, ?)");
            
            if ($stmt->execute([$nome, $status])) {
                $message = "Categoria adicionada com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao adicionar categoria!";
                $message_type = "error";
            }
        } else {
            $message = "Nome da categoria é obrigatório!";
            $message_type = "error";
        }
    }
    
    if ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $nome = trim($_POST['nome'] ?? '');
        $status = $_POST['status'] ?? 'ativo';
        
        if (!empty($nome) && $id > 0) {
            $stmt = $conn->prepare("UPDATE categorias SET nome = ?, status = ? WHERE id = ?");
            
            if ($stmt->execute([$nome, $status, $id])) {
                $message = "Categoria atualizada com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao atualizar categoria!";
                $message_type = "error";
            }
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        if ($id > 0) {
            // Verificar se há produtos vinculados
            $check = $conn->prepare("SELECT COUNT(*) as total FROM produtos WHERE categoria_id = ?");
            $check->execute([$id]);
            $count = $check->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($count > 0) {
                $message = "Não é possível excluir esta categoria pois há produtos vinculados a ela!";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
                
                if ($stmt->execute([$id])) {
                    $message = "Categoria excluída com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao excluir categoria!";
                    $message_type = "error";
                }
            }
        }
    }
}

// Buscar categorias
$stmt = $conn->prepare("SELECT * FROM categorias ORDER BY nome");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categoria para edição
$categoria_edit = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$edit_id]);
    $categoria_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias - Painel Admin</title>
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

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 200px auto;
            gap: 20px;
            align-items: end;
        }
        
        .form-grid .btn {
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 0;
            padding: 10px 20px;
        }
        
        .form-grid .form-group {
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            height: 42px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .categories-table {
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

        /* Coluna de ações com largura fixa */
        th:last-child,
        td:last-child {
            min-width: 140px;
            width: 140px;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-ativo {
            background: #d4edda;
            color: #155724;
        }

        .status-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 120px;
        }

        .actions .btn {
            white-space: nowrap;
            min-width: 60px;
            text-align: center;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .categories-table {
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
            
            /* Coluna de ações em mobile */
            th:last-child,
            td:last-child {
                min-width: 120px;
                width: 120px;
            }
            
            .actions {
                flex-direction: column;
                gap: 5px;
                min-width: auto;
                width: 100%;
            }
            
            .actions .btn {
                width: 100%;
                min-width: auto;
                padding: 8px 12px;
                font-size: 0.8rem;
                white-space: nowrap;
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
                
                <a href="categories.php" class="sidebar-item active" data-tooltip="Categorias">
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
                        <h1>📂 Gerenciar Categorias</h1>
                        <p>Organize os produtos da sua loja por categorias</p>
                    </div>
                    <!-- Botão hamburger para mobile -->
                    <div class="mobile-menu-toggle" id="mobile-menu-toggle">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <h2><?php echo $categoria_edit ? 'Editar Categoria' : 'Adicionar Nova Categoria'; ?></h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $categoria_edit ? 'edit' : 'add'; ?>">
                        <?php if ($categoria_edit): ?>
                            <input type="hidden" name="id" value="<?php echo $categoria_edit['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome">Nome da Categoria:</label>
                                <input type="text" id="nome" name="nome" 
                                       value="<?php echo $categoria_edit ? htmlspecialchars($categoria_edit['nome']) : ''; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select id="status" name="status">
                                    <option value="ativo" <?php echo ($categoria_edit && $categoria_edit['status'] === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="inativo" <?php echo ($categoria_edit && $categoria_edit['status'] === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <div>
                                <button type="submit" class="btn btn-success">
                                    <?php echo $categoria_edit ? 'Atualizar' : 'Adicionar'; ?>
                                </button>
                                <?php if ($categoria_edit): ?>
                                    <a href="categories.php" class="btn btn-secondary">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="categories-table">
                    <div class="table-header">
                        <h2>Categorias Cadastradas</h2>
                    </div>
                    
                    <?php if (count($categorias) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $categoria): ?>
                                    <tr>
                                        <td>#<?php echo $categoria['id']; ?></td>
                                        <td><?php echo htmlspecialchars($categoria['nome']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $categoria['status']; ?>">
                                                <?php echo ucfirst($categoria['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="?edit=<?php echo $categoria['id']; ?>" class="btn btn-secondary">Editar</a>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: #666;">
                            Nenhuma categoria cadastrada ainda.
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
    </script>
</body>
</html>


