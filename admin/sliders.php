<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

// Verificar mensagens via GET (após redirecionamento)
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

// Processar formulário de adicionar/editar slider
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $titulo = $_POST['titulo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $link = $_POST['link'] ?? '';
            $ordem = $_POST['ordem'] ?? 1;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            // Upload da imagem
            $imagem = '';
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/sliders/';
                $file_extension = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $imagem = 'slider_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $imagem;
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_path)) {
                        $stmt = $conn->prepare("INSERT INTO sliders (titulo, descricao, imagem, link, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$titulo, $descricao, $imagem, $link, $ordem, $ativo])) {
                            $message = "Slider adicionado com sucesso!";
                            $message_type = "success";
                        } else {
                            $message = "Erro ao adicionar slider!";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Erro ao fazer upload da imagem!";
                        $message_type = "error";
                    }
                } else {
                    $message = "Formato de imagem não permitido!";
                    $message_type = "error";
                }
            } else {
                $message = "Selecione uma imagem!";
                $message_type = "error";
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'] ?? 0;
            $titulo = $_POST['titulo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $link = $_POST['link'] ?? '';
            $ordem = $_POST['ordem'] ?? 1;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            $imagem = '';
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/sliders/';
                $file_extension = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $imagem = 'slider_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $imagem;
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_path)) {
                        // Deletar imagem antiga se existir
                        $stmt_old = $conn->prepare("SELECT imagem FROM sliders WHERE id = ?");
                        $stmt_old->execute([$id]);
                        $old_slider = $stmt_old->fetch(PDO::FETCH_ASSOC);
                        
                        if ($old_slider && !empty($old_slider['imagem'])) {
                            $old_imagem_path = '../uploads/sliders/' . $old_slider['imagem'];
                            if (file_exists($old_imagem_path)) {
                                unlink($old_imagem_path);
                            }
                        }
                        
                        $stmt = $conn->prepare("UPDATE sliders SET titulo = ?, descricao = ?, imagem = ?, link = ?, ordem = ?, ativo = ? WHERE id = ?");
                        if ($stmt->execute([$titulo, $descricao, $imagem, $link, $ordem, $ativo, $id])) {
                            $message = "Slider atualizado com sucesso!";
                            $message_type = "success";
                            // Redirecionar para recarregar a página
                            header("Location: sliders.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
                            exit;
                        } else {
                            $message = "Erro ao atualizar slider!";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Erro ao fazer upload da imagem!";
                        $message_type = "error";
                    }
                } else {
                    $message = "Formato de imagem não permitido!";
                    $message_type = "error";
                }
            } else {
                // Atualizar sem nova imagem
                $stmt = $conn->prepare("UPDATE sliders SET titulo = ?, descricao = ?, link = ?, ordem = ?, ativo = ? WHERE id = ?");
                if ($stmt->execute([$titulo, $descricao, $link, $ordem, $ativo, $id])) {
                    $message = "Slider atualizado com sucesso!";
                    $message_type = "success";
                    // Redirecionar para recarregar a página
                    header("Location: sliders.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
                    exit;
                } else {
                    $message = "Erro ao atualizar slider!";
                    $message_type = "error";
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Buscar imagem atual para deletar
            $stmt = $conn->prepare("SELECT imagem FROM sliders WHERE id = ?");
            $stmt->execute([$id]);
            $slider = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($slider && !empty($slider['imagem'])) {
                $imagem_path = '../uploads/sliders/' . $slider['imagem'];
                if (file_exists($imagem_path)) {
                    unlink($imagem_path);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM sliders WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = "Slider removido com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao remover slider!";
                $message_type = "error";
            }
        }
    }
}

// Buscar todos os sliders
$stmt = $conn->query("SELECT * FROM sliders ORDER BY ordem ASC, id DESC");
$sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sliders Promocionais - Painel Admin</title>
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

        .sliders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .slider-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .slider-card:hover {
            transform: translateY(-5px);
        }

        .slider-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .slider-content {
            padding: 20px;
        }

        .slider-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .slider-description {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .slider-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #95a5a6;
        }

        .slider-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .slider-status.active {
            background: #d4edda;
            color: #155724;
        }

        .slider-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .slider-actions {
            display: flex;
            gap: 10px;
        }

        .add-slider-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Botão hamburger para mobile */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 10px;
            position: absolute;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
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

        .checkbox-group input[type="checkbox"] {
            width: auto;
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
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal-content form {
            padding: 20px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Mensagens de alerta */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
        }

        .message.success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
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
                
                <a href="clientes.php" class="sidebar-item" data-tooltip="Clientes">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                
                <a href="payment_monitor.php" class="sidebar-item" data-tooltip="Monitor de Pagamentos">
                    <i class="fas fa-credit-card"></i>
                    <span>Monitor de Pagamentos</span>
                </a>
                
                <a href="sliders.php" class="sidebar-item active" data-tooltip="Sliders Promocionais">
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
                        <h1>🖼️ Sliders Promocionais</h1>
                        <p>Gerencie os banners promocionais da sua loja</p>
                    </div>
                    <!-- Botão hamburger para mobile -->
                    <div class="mobile-menu-toggle" id="mobile-menu-toggle">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário para adicionar novo slider -->
                <div class="add-slider-form">
                    <h2><i class="fas fa-plus"></i> Adicionar Novo Slider</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="titulo">Título do Slider</label>
                                <input type="text" id="titulo" name="titulo" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="link">Link (opcional)</label>
                                <input type="url" id="link" name="link" placeholder="https://exemplo.com">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <textarea id="descricao" name="descricao" placeholder="Descrição do slider..."></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="imagem">Imagem do Slider</label>
                                <input type="file" id="imagem" name="imagem" accept="image/*" required>
                                <small>Formatos aceitos: JPG, PNG, GIF, WebP. Tamanho recomendado: 1200x400px</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="ordem">Ordem de Exibição</label>
                                <input type="number" id="ordem" name="ordem" value="1" min="1">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="ativo" name="ativo" checked>
                                <label for="ativo">Ativo (exibir na loja)</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Adicionar Slider
                        </button>
                    </form>
                </div>

                <!-- Lista de sliders existentes -->
                <h2><i class="fas fa-list"></i> Sliders Cadastrados</h2>
                
                <?php if (empty($sliders)): ?>
                    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <i class="fas fa-images" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 20px;"></i>
                        <h3 style="color: #7f8c8d; margin-bottom: 10px;">Nenhum slider cadastrado</h3>
                        <p style="color: #95a5a6;">Adicione seu primeiro slider promocional acima.</p>
                    </div>
                <?php else: ?>
                    <div class="sliders-grid">
                        <?php foreach ($sliders as $slider): ?>
                            <div class="slider-card">
                                <img src="../uploads/sliders/<?php echo htmlspecialchars($slider['imagem']); ?>" alt="<?php echo htmlspecialchars($slider['titulo']); ?>" class="slider-image">
                                
                                <div class="slider-content">
                                    <div class="slider-title"><?php echo htmlspecialchars($slider['titulo']); ?></div>
                                    
                                    <?php if (!empty($slider['descricao'])): ?>
                                        <div class="slider-description"><?php echo htmlspecialchars($slider['descricao']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="slider-meta">
                                        <span>Ordem: <?php echo $slider['ordem']; ?></span>
                                        <span class="slider-status <?php echo $slider['ativo'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $slider['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($slider['link'])): ?>
                                        <div style="margin-bottom: 15px;">
                                            <small><strong>Link:</strong> <a href="<?php echo htmlspecialchars($slider['link']); ?>" target="_blank"><?php echo htmlspecialchars($slider['link']); ?></a></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="slider-actions">
                                        <button class="btn btn-secondary" onclick="editSlider(<?php echo $slider['id']; ?>)">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover este slider?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $slider['id']; ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> Remover
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Slider</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label for="edit_titulo">Título do Slider</label>
                    <input type="text" id="edit_titulo" name="titulo" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_descricao">Descrição (opcional)</label>
                    <textarea id="edit_descricao" name="descricao" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_link">Link (opcional)</label>
                    <input type="url" id="edit_link" name="link" placeholder="https://exemplo.com">
                </div>
                
                <div class="form-group">
                    <label for="edit_ordem">Ordem de Exibição</label>
                    <input type="number" id="edit_ordem" name="ordem" min="1" value="1" required>
                </div>
                
                <div class="form-group">
                    <label>Imagem Atual</label>
                    <img id="current_image" src="" alt="Imagem atual" style="max-width: 200px; max-height: 150px; display: none; border-radius: 5px; margin: 10px 0;">
                    <input type="file" name="imagem" accept="image/*">
                    <small>Deixe em branco para manter a imagem atual</small>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_ativo" name="ativo">
                        <label for="edit_ativo">Ativo (exibir na loja)</label>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editSlider(id) {
            // Buscar dados do slider via AJAX
            fetch('get_slider.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const slider = data.slider;
                        
                        // Preencher o formulário com os dados do slider
                        document.getElementById('edit_id').value = slider.id;
                        document.getElementById('edit_titulo').value = slider.titulo;
                        document.getElementById('edit_descricao').value = slider.descricao;
                        document.getElementById('edit_link').value = slider.link;
                        document.getElementById('edit_ordem').value = slider.ordem;
                        document.getElementById('edit_ativo').checked = slider.ativo == 1;
                        
                        // Mostrar imagem atual
                        document.getElementById('current_image').src = '../uploads/sliders/' + slider.imagem;
                        document.getElementById('current_image').style.display = 'block';
                        
                        // Mostrar modal
                        document.getElementById('editModal').style.display = 'block';
                    } else {
                        alert('Erro ao carregar dados do slider!');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar dados do slider!');
                });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
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
        });
    </script>
</body>
</html> 
</html> 