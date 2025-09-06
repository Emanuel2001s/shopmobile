<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

// ========================================
// CONFIGURAÇÃO DE PERMISSÕES
// ========================================
// Defina aqui o ID do usuário que pode gerenciar outros usuários
// Por exemplo: se o admin principal tem ID = 1, deixe $ADMIN_USER_ID = 1
// Apenas este usuário poderá criar, editar e excluir outros usuários
$ADMIN_USER_ID = 1; // ⚠️ ALTERE ESTE VALOR para o ID do usuário administrador principal
// ========================================

// Verificar se o usuário logado tem permissão para gerenciar usuários
$can_manage_users = ($_SESSION['user_id'] == $ADMIN_USER_ID);

$message = "";
$message_type = "";

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verificar permissão antes de processar qualquer ação
    if (!$can_manage_users) {
        $message = "Você não tem permissão para gerenciar usuários!";
        $message_type = "error";
    } else {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (!empty($username) && !empty($password)) {
                if ($password !== $confirm_password) {
                    $message = "As senhas não coincidem!";
                    $message_type = "error";
                } elseif (strlen($password) < 6) {
                    $message = "A senha deve ter pelo menos 6 caracteres!";
                    $message_type = "error";
                } else {
                    // Verificar se o usuário já existe
                    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->fetch()) {
                        $message = "Este nome de usuário já existe!";
                        $message_type = "error";
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO usuarios (username, password) VALUES (?, ?)");
                        
                        if ($stmt->execute([$username, $password_hash])) {
                            $message = "Usuário adicionado com sucesso!";
                            $message_type = "success";
                        } else {
                            $message = "Erro ao adicionar usuário!";
                            $message_type = "error";
                        }
                    }
                }
            } else {
                $message = "Todos os campos são obrigatórios!";
                $message_type = "error";
            }
        }
        
        if ($action === 'edit') {
            $id = $_POST['id'] ?? 0;
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (!empty($username) && $id > 0) {
                // Verificar se o usuário já existe (exceto o atual)
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                
                if ($stmt->fetch()) {
                    $message = "Este nome de usuário já existe!";
                    $message_type = "error";
                } else {
                    if (!empty($password)) {
                        // Atualizar com nova senha
                        if ($password !== $confirm_password) {
                            $message = "As senhas não coincidem!";
                            $message_type = "error";
                        } elseif (strlen($password) < 6) {
                            $message = "A senha deve ter pelo menos 6 caracteres!";
                            $message_type = "error";
                        } else {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE usuarios SET username = ?, password = ? WHERE id = ?");
                            
                            if ($stmt->execute([$username, $password_hash, $id])) {
                                $message = "Usuário atualizado com sucesso!";
                                $message_type = "success";
                            } else {
                                $message = "Erro ao atualizar usuário!";
                                $message_type = "error";
                            }
                        }
                    } else {
                        // Atualizar apenas o username
                        $stmt = $conn->prepare("UPDATE usuarios SET username = ? WHERE id = ?");
                        
                        if ($stmt->execute([$username, $id])) {
                            $message = "Usuário atualizado com sucesso!";
                            $message_type = "success";
                        } else {
                            $message = "Erro ao atualizar usuário!";
                            $message_type = "error";
                        }
                    }
                }
            }
        }
        
        if ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            if ($id > 0) {
                // Não permitir excluir o próprio usuário logado
                if ($id == $_SESSION['user_id']) {
                    $message = "Você não pode excluir seu próprio usuário!";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
                    
                    if ($stmt->execute([$id])) {
                        $message = "Usuário excluído com sucesso!";
                        $message_type = "success";
                    } else {
                        $message = "Erro ao excluir usuário!";
                        $message_type = "error";
                    }
                }
            }
        }
    }
}

// Buscar usuários
$stmt = $conn->prepare("SELECT * FROM usuarios ORDER BY username");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Painel Admin</title>
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
            padding: 12px 24px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(149, 165, 166, 0.3);
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
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            align-items: end;
            margin-bottom: 25px;
        }
        
        .form-grid .form-group {
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            height: 48px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .usuarios-table {
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

        /* Modal de Edição */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-form .form-group {
            margin-bottom: 25px;
        }

        .modal-form .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .modal-form .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .modal-form .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
            padding: 20px 30px 30px;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #e9ecef;
        }

        .modal-footer .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .modal-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
                 gap: 15px;
             }
             
             .form-group input,
             .form-group select {
                 height: 44px;
                 padding: 10px 12px;
             }
            
            .usuarios-table {
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

            .modal-content {
                margin: 10% auto;
                width: 95%;
                max-width: none;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 20px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer .btn {
                width: 100%;
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
                
                <a href="usuarios.php" class="sidebar-item active" data-tooltip="Usuários">
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
                        <h1>👥 Gerenciar Usuários</h1>
                        <p>Gerencie os usuários do sistema administrativo</p>
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

                <?php if ($can_manage_users): ?>
                <div class="form-section" id="add-user-form" style="display: none;">
                    <h2>+ Adicionar Novo Usuário</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">Nome de Usuário:</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Senha:</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirmar Senha:</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                                                 <div style="display: flex; gap: 15px; align-items: center; margin-top: 10px;">
                             <button type="submit" class="btn btn-success">
                                 <i class="fas fa-plus"></i> Adicionar Usuário
                             </button>
                             <button type="button" class="btn btn-secondary" id="cancel-add">
                                 <i class="fas fa-times"></i> Cancelar
                             </button>
                         </div>
                    </form>
                </div>

                <div class="form-section" id="add-user-button">
                    <div style="text-align: center; padding: 40px;">
                        <button type="button" class="btn btn-success" id="show-add-form" style="font-size: 16px; padding: 15px 30px;">
                            <i class="fas fa-plus"></i> Adicionar Novo Usuário
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$can_manage_users): ?>
                    <div class="message" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Apenas visualização:</strong> Você pode visualizar os usuários cadastrados, mas não tem permissão para criar, editar ou excluir usuários.
                    </div>
                <?php endif; ?>

                <div class="usuarios-table">
                    <div class="table-header">
                        <h2>Usuários Cadastrados</h2>
                    </div>
                    
                    <?php if (count($usuarios) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome de Usuário</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td>#<?php echo $usuario['id']; ?></td>
                                        <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                        <td>
                                            <?php if ($can_manage_users): ?>
                                                <div class="actions">
                                                    <button class="btn btn-secondary" onclick="openEditModal(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')">
                                                        <i class="fas fa-edit"></i> Editar
                                                    </button>
                                                    <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Tem certeza que deseja excluir este usuário?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-trash"></i> Excluir
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="btn btn-secondary" style="cursor: not-allowed; opacity: 0.6;">
                                                            <i class="fas fa-user"></i> Usuário Atual
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #95a5a6; font-style: italic;">
                                                    <i class="fas fa-eye"></i> Apenas visualização
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: #666;">
                            Nenhum usuário cadastrado ainda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Editar Usuário</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_username">Nome de Usuário:</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">Nova Senha (deixe em branco para manter a atual):</label>
                        <input type="password" id="edit_password" name="password">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_confirm_password">Confirmar Nova Senha:</label>
                        <input type="password" id="edit_confirm_password" name="confirm_password">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Funções do modal
        function openEditModal(id, username) {
            <?php if ($can_manage_users): ?>
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            <?php else: ?>
            alert('Você não tem permissão para editar usuários!');
            <?php endif; ?>
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
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

            // Controle do formulário de adicionar usuário
            <?php if ($can_manage_users): ?>
            const showAddFormBtn = document.getElementById('show-add-form');
            const addUserForm = document.getElementById('add-user-form');
            const addUserButton = document.getElementById('add-user-button');
            const cancelAddBtn = document.getElementById('cancel-add');

            // Mostrar formulário
            showAddFormBtn.addEventListener('click', function() {
                addUserForm.style.display = 'block';
                addUserButton.style.display = 'none';
                // Focar no primeiro campo
                document.getElementById('username').focus();
            });

            // Cancelar e ocultar formulário
            cancelAddBtn.addEventListener('click', function() {
                addUserForm.style.display = 'none';
                addUserButton.style.display = 'block';
                // Limpar formulário
                document.querySelector('#add-user-form form').reset();
            });
            <?php endif; ?>
        });
    </script>
</body>
</html> 