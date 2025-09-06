<?php
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

$message = '';
$message_type = '';

// Verificar mensagens de sucesso via GET
if (isset($_GET['success'])) {
    $message = "Produto adicionado com sucesso!";
    $message_type = "success";
}

if (isset($_GET['deleted'])) {
    $message = "Produto excluído com sucesso!";
    $message_type = "success";
}

// Processar upload de imagem
function uploadImage($file, $prefix = 'produto_') {
    $upload_dir = '../uploads/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

// Download de imagem da web
function downloadWebImage($url, $prefix = 'produto_') {
    $upload_dir = '../uploads/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Verificar se é uma data URL (SVG)
    if (strpos($url, 'data:image/svg+xml;base64,') === 0) {
        $base64_data = substr($url, 26); // Remove o prefixo data:image/svg+xml;base64,
        $svg_content = base64_decode($base64_data);
        
        if ($svg_content !== false) {
            $filename = $prefix . time() . '_' . rand(1000, 9999) . '.svg';
            $filepath = $upload_dir . $filename;
            
            if (file_put_contents($filepath, $svg_content) !== false) {
                return $filename;
            }
        }
        return false;
    }
    
    // Verificar se é uma URL válida
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Obter informações da imagem
    $headers = get_headers($url, 1);
    if (!$headers || strpos($headers[0], '200') === false) {
        return false;
    }
    
    // Verificar se o Content-Type é realmente uma imagem
    if (isset($headers['Content-Type'])) {
        $content_type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
        if (!preg_match('/^image\/(jpeg|jpg|png|gif|webp)$/i', $content_type)) {
            return false;
        }
    }
    
    // Determinar extensão
    $extension = 'jpg'; // padrão
    if (isset($headers['Content-Type'])) {
        $content_type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
        switch ($content_type) {
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp':
                $extension = 'webp';
                break;
            case 'image/svg+xml':
                $extension = 'svg';
                break;
            case 'image/jpeg':
            case 'image/jpg':
            default:
                $extension = 'jpg';
                break;
        }
    }
    
    // Gerar nome do arquivo
    $filename = $prefix . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Download da imagem
    $image_content = file_get_contents($url);
    if ($image_content === false || empty($image_content)) {
        return false;
    }
    
    // Verificar se o conteúdo é realmente uma imagem válida
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_buffer($finfo, $image_content);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])) {
        return false;
    }
    
    // Salvar arquivo
    if (file_put_contents($filepath, $image_content) !== false) {
        // Verificar se o arquivo foi salvo corretamente
        if (filesize($filepath) > 0) {
            return $filename;
        }
    }
    
    return false;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco = $_POST['preco'] ?? 0;
        $categoria_id = $_POST['categoria_id'] ?? 0;
        $estoque = $_POST['estoque'] ?? 0;
        
        $foto_principal = '';
        $fotos_adicionais = '';
        
        // Upload da foto principal
        if (isset($_FILES['foto_principal']) && $_FILES['foto_principal']['error'] === 0) {
            $foto_principal = uploadImage($_FILES['foto_principal'], 'principal_');
            if (!$foto_principal) {
                $message = "Erro ao fazer upload da foto principal!";
                $message_type = "error";
            }
        } elseif (isset($_POST['web_image_principal']) && !empty($_POST['web_image_principal'])) {
            // Download da imagem da web
            $web_image_url = $_POST['web_image_principal'];
            $foto_principal = downloadWebImage($web_image_url, 'principal_');
            if (!$foto_principal) {
                $message = "Erro ao baixar imagem da web!";
                $message_type = "error";
            }
        }
        
        // Upload das fotos adicionais
        if (isset($_FILES['fotos_adicionais']) && is_array($_FILES['fotos_adicionais']['name'])) {
            $fotos_array = [];
            for ($i = 0; $i < count($_FILES['fotos_adicionais']['name']); $i++) {
                if ($_FILES['fotos_adicionais']['error'][$i] === 0) {
                    $file = [
                        'name' => $_FILES['fotos_adicionais']['name'][$i],
                        'type' => $_FILES['fotos_adicionais']['type'][$i],
                        'tmp_name' => $_FILES['fotos_adicionais']['tmp_name'][$i],
                        'error' => $_FILES['fotos_adicionais']['error'][$i],
                        'size' => $_FILES['fotos_adicionais']['size'][$i]
                    ];
                    $uploaded = uploadImage($file, 'adicional_');
                    if ($uploaded) {
                        $fotos_array[] = $uploaded;
                    }
                }
            }
            $fotos_adicionais = implode(',', $fotos_array);
        }
        
        // Processar imagens adicionais da web
        if (isset($_POST['web_images_adicionais']) && is_array($_POST['web_images_adicionais'])) {
            $web_fotos_array = [];
            foreach ($_POST['web_images_adicionais'] as $web_image_url) {
                if (!empty($web_image_url)) {
                    $downloaded = downloadWebImage($web_image_url, 'adicional_');
                    if ($downloaded) {
                        $web_fotos_array[] = $downloaded;
                    }
                }
            }
            if (!empty($web_fotos_array)) {
                $fotos_adicionais = (!empty($fotos_adicionais) ? $fotos_adicionais . ',' : '') . implode(',', $web_fotos_array);
            }
        }
        
        if (!empty($nome) && $preco > 0 && $categoria_id > 0 && $estoque >= 0 && empty($message)) {
            $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, foto_principal, fotos_adicionais, categoria_id, estoque) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$nome, $descricao, $preco, $foto_principal, $fotos_adicionais, $categoria_id, $estoque])) {
                // Redirecionar com mensagem de sucesso
                header("Location: products.php?success=1");
                exit();
            } else {
                $message = "Erro ao adicionar produto!";
                $message_type = "error";
            }
        } elseif (empty($message)) {
            $message = "Todos os campos obrigatórios devem ser preenchidos!";
            $message_type = "error";
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        if ($id > 0) {
            // Buscar imagens para deletar
            $stmt = $conn->prepare("SELECT foto_principal, fotos_adicionais FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($produto) {
                // Deletar imagens do servidor
                if (!empty($produto['foto_principal'])) {
                    @unlink('../uploads/' . $produto['foto_principal']);
                }
                
                if (!empty($produto['fotos_adicionais'])) {
                    $fotos = explode(',', $produto['fotos_adicionais']);
                    foreach ($fotos as $foto) {
                        @unlink('../uploads/' . trim($foto));
                    }
                }
                
                // Deletar produto do banco
                $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
                        if ($stmt->execute([$id])) {
            // Redirecionar com mensagem de sucesso
            header("Location: products.php?deleted=1");
            exit();
        } else {
            $message = "Erro ao excluir produto!";
            $message_type = "error";
        }
            }
        }
    }
}

// Buscar categorias para o select
$stmt = $conn->prepare("SELECT id, nome FROM categorias WHERE status = 'ativo' ORDER BY nome");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar produtos com paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

// Busca por filtro
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE p.nome LIKE ? OR c.nome LIKE ? OR p.descricao LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

// Contar total de produtos
$count_sql = "SELECT COUNT(*) as total FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_produtos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_produtos / $limit);

// Buscar produtos
$sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        $where_clause 
        ORDER BY p.id DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Painel Admin</title>
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
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
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

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .products-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            min-width: 250px;
        }
        
        @media (max-width: 768px) {
            .search-box {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box input {
                min-width: auto;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .pagination a,
            .pagination span {
                padding: 6px 10px;
                font-size: 14px;
            }
        }

        .search-box button {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        /* Responsividade da tabela */
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                border-radius: 5px;
                font-size: 14px;
            }
            
            th, td {
                padding: 10px 8px;
                min-width: 120px;
            }
            
            /* Coluna de imagem menor no mobile */
            th:first-child, td:first-child {
                min-width: 80px;
                width: 80px;
            }
            
            /* Coluna de nome mais larga */
            th:nth-child(2), td:nth-child(2) {
                min-width: 150px;
            }
            
            /* Coluna de categoria */
            th:nth-child(3), td:nth-child(3) {
                min-width: 100px;
            }
            
            /* Coluna de preço */
            th:nth-child(4), td:nth-child(4) {
                min-width: 100px;
            }
            
            /* Coluna de estoque */
            th:nth-child(5), td:nth-child(5) {
                min-width: 120px;
            }
            
            /* Coluna de ações */
            th:nth-child(6), td:nth-child(6) {
                min-width: 140px;
            }
            
            .product-image {
                width: 50px;
                height: 50px;
            }
            
            .actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .actions .btn {
                font-size: 12px;
                padding: 5px 8px;
            }
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
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
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            background: white;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
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
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-box input {
                min-width: auto;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
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
                
                <a href="products.php" class="sidebar-item active" data-tooltip="Produtos">
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
                        <h1>📦 Gerenciar Produtos</h1>
                        <p>Adicione e gerencie os produtos da sua loja</p>
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

                <div class="form-section" id="add-product-form" style="display: none;">
                    <h2>+ Adicionar Novo Produto</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome">Nome do Produto:</label>
                                <input type="text" id="nome" name="nome" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="categoria_id">Categoria:</label>
                                <select id="categoria_id" name="categoria_id" required>
                                    <option value="">Selecione uma categoria</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>">
                                            <?php echo htmlspecialchars($categoria['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="preco">Preço (R$):</label>
                                <input type="number" id="preco" name="preco" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="estoque">Estoque:</label>
                                <input type="number" id="estoque" name="estoque" min="0" value="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="foto_principal">Foto Principal:</label>
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                    <input type="file" id="foto_principal" name="foto_principal" accept="image/*">
                                    <button type="button" class="btn btn-secondary" onclick="openImageSearch('principal')">
                                        <i class="fas fa-search"></i> Buscar na Web
                                    </button>
                                </div>
                                <small>Formatos aceitos: JPG, PNG, GIF, WebP</small>
                                <div id="principal-image-preview" style="margin-top: 10px;"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="fotos_adicionais">Fotos Adicionais:</label>
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                    <input type="file" id="fotos_adicionais" name="fotos_adicionais[]" accept="image/*" multiple>
                                    <button type="button" class="btn btn-secondary" onclick="openImageSearch('adicional')">
                                        <i class="fas fa-search"></i> Buscar na Web
                                    </button>
                                </div>
                                <small>Formatos aceitos: JPG, PNG, GIF, WebP (múltiplas imagens)</small>
                                <div id="adicional-images-preview" style="margin-top: 10px;"></div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="descricao">Descrição:</label>
                                <textarea id="descricao" name="descricao" rows="4"></textarea>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Adicionar Produto
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancel-add">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                <div class="form-section" id="add-product-button">
                    <div style="text-align: center; padding: 40px;">
                        <button type="button" class="btn btn-success" id="show-add-form" style="font-size: 16px; padding: 15px 30px;">
                            <i class="fas fa-plus"></i> Adicionar Novo Produto
                        </button>
                    </div>
                </div>

                <div class="products-table">
                    <div class="table-header">
                        <h2>Produtos Cadastrados</h2>
                        <div class="search-box">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="text" id="product-search" placeholder="Buscar por nome, categoria, descrição..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="button" id="search-btn">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <button type="button" id="clear-search" class="btn btn-secondary" style="display: none;">
                                    <i class="fas fa-times"></i> Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($produtos) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Imagem</th>
                                    <th>Nome</th>
                                    <th>Categoria</th>
                                    <th>Preço</th>
                                    <th>Estoque</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($produto['foto_principal'])): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($produto['foto_principal']); ?>" 
                                                     alt="<?php echo htmlspecialchars($produto['nome']); ?>" 
                                                     class="product-image">
                                            <?php else: ?>
                                                <div style="width: 60px; height: 60px; background: #f8f9fa; border-radius: 5px; display: flex; align-items: center; justify-content: center; color: #999;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>
                                            <?php if (!empty($produto['descricao'])): ?>
                                                <br><small style="color: #666;"><?php echo htmlspecialchars(substr($produto['descricao'], 0, 50)) . (strlen($produto['descricao']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($produto['categoria_nome'] ?? 'Sem categoria'); ?></td>
                                        <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $produto['estoque'] > 0 ? 'status-ativo' : 'status-inativo'; ?>">
                                                <?php echo $produto['estoque']; ?> unidades
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="edit_product.php?id=<?php echo $produto['id']; ?>" class="btn btn-secondary">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Tem certeza que deseja excluir este produto?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $produto['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Próxima <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: #666;">
                            <?php if (!empty($search)): ?>
                                Nenhum produto encontrado para "<?php echo htmlspecialchars($search); ?>".
                            <?php else: ?>
                                Nenhum produto cadastrado ainda.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para busca de imagens -->
    <div id="image-search-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; overflow-y: auto;">
        <div style="background: white; margin: 20px auto; max-width: 800px; border-radius: 10px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>🔍 Buscar Imagens na Web</h3>
                <button type="button" onclick="closeImageSearch()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <input type="text" id="image-search-term" placeholder="Digite o que você quer buscar..." 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;">
                <button type="button" onclick="searchImages()" class="btn btn-success" style="margin-top: 10px;">
                    <i class="fas fa-search"></i> Buscar Imagens
                </button>
            </div>
            
            <div id="image-search-results" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; max-height: 400px; overflow-y: auto;">
                <!-- Resultados aparecerão aqui -->
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <button type="button" onclick="closeImageSearch()" class="btn btn-secondary">
                    Fechar
                </button>
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

            // Busca AJAX de produtos
            const searchInput = document.getElementById('product-search');
            const searchBtn = document.getElementById('search-btn');
            const clearSearchBtn = document.getElementById('clear-search');
            const productsTable = document.querySelector('.products-table table tbody');
            const pagination = document.querySelector('.pagination');

            function performSearch() {
                const searchTerm = searchInput.value.trim();
                
                if (searchTerm === '') {
                    // Se não há termo de busca, recarregar a página
                    window.location.href = 'products.php';
                    return;
                }

                // Mostrar loading
                productsTable.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">Buscando...</td></tr>';
                
                // Fazer requisição AJAX
                fetch(`search_products_admin.php?search=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.produtos.length > 0) {
                            // Renderizar resultados
                            productsTable.innerHTML = data.produtos.map(produto => `
                                <tr>
                                    <td>
                                        ${produto.foto_principal ? 
                                            `<img src="../uploads/${produto.foto_principal}" alt="${produto.nome}" class="product-image">` :
                                            `<div style="width: 60px; height: 60px; background: #f8f9fa; border-radius: 5px; display: flex; align-items: center; justify-content: center; color: #999;">
                                                <i class="fas fa-image"></i>
                                            </div>`
                                        }
                                    </td>
                                    <td>
                                        <strong>${produto.nome}</strong>
                                        ${produto.descricao ? `<br><small style="color: #666;">${produto.descricao.substring(0, 50)}${produto.descricao.length > 50 ? '...' : ''}</small>` : ''}
                                    </td>
                                    <td>${produto.categoria_nome || 'Sem categoria'}</td>
                                    <td>R$ ${parseFloat(produto.preco).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    <td>
                                        <span class="status-badge ${produto.estoque > 0 ? 'status-ativo' : 'status-inativo'}">
                                            ${produto.estoque} unidades
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="edit_product.php?id=${produto.id}" class="btn btn-secondary">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Tem certeza que deseja excluir este produto?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="${produto.id}">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            `).join('');
                            
                            // Ocultar paginação durante busca
                            if (pagination) pagination.style.display = 'none';
                            
                            // Mostrar botão limpar
                            clearSearchBtn.style.display = 'inline-block';
                        } else {
                            // Nenhum resultado encontrado
                            productsTable.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 2rem; color: #666;">Nenhum produto encontrado para "${searchTerm}".</td></tr>`;
                            
                            // Ocultar paginação
                            if (pagination) pagination.style.display = 'none';
                            
                            // Mostrar botão limpar
                            clearSearchBtn.style.display = 'inline-block';
                        }
                    })
                    .catch(error => {
                        console.error('Erro na busca:', error);
                        productsTable.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: #e74c3c;">Erro ao buscar produtos.</td></tr>';
                    });
            }

            // Buscar ao clicar no botão
            searchBtn.addEventListener('click', performSearch);

            // Buscar ao pressionar Enter
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });

            // Busca automática enquanto digita (com debounce)
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = this.value.trim();
                
                if (searchTerm === '') {
                    // Se campo vazio, mostrar todos os produtos
                    window.location.href = 'products.php';
                    return;
                }
                
                // Aguardar 500ms após parar de digitar
                searchTimeout = setTimeout(() => {
                    performSearch();
                }, 500);
            });

            // Limpar busca
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                window.location.href = 'products.php';
            });

            // Variáveis para busca de imagens
            let currentImageType = 'principal';
            let selectedImages = [];

            // Função para abrir modal de busca de imagens
            window.openImageSearch = function(type) {
                currentImageType = type;
                document.getElementById('image-search-modal').style.display = 'block';
                document.getElementById('image-search-term').focus();
            };

            // Função para fechar modal
            window.closeImageSearch = function() {
                document.getElementById('image-search-modal').style.display = 'none';
                document.getElementById('image-search-results').innerHTML = '';
            };

            // Função para buscar imagens
            window.searchImages = function() {
                const searchTerm = document.getElementById('image-search-term').value.trim();
                if (!searchTerm) return;

                const resultsDiv = document.getElementById('image-search-results');
                resultsDiv.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px;">Buscando imagens...</div>';

                // Usar API do DuckDuckGo (via proxy para evitar CORS)
                fetch(`search_images.php?q=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.images.length > 0) {
                            resultsDiv.innerHTML = data.images.map((image, index) => `
                                <div style="border: 1px solid #ddd; border-radius: 5px; padding: 10px; text-align: center; cursor: pointer;" 
                                     onclick="selectImage('${image.url}', '${image.title}')">
                                    <img src="${image.url}" alt="${image.title}" 
                                         style="width: 100%; height: 120px; object-fit: cover; border-radius: 3px; margin-bottom: 5px;">
                                    <div style="font-size: 12px; color: #666; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        ${image.title}
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            resultsDiv.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #666;">Nenhuma imagem encontrada.</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Erro na busca:', error);
                        resultsDiv.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #e74c3c;">Erro ao buscar imagens.</div>';
                    });
            };

            // Função para selecionar imagem
            window.selectImage = function(imageUrl, imageTitle) {
                if (currentImageType === 'principal') {
                    // Para foto principal, limpar o input file e adicionar a URL da web
                    const input = document.getElementById('foto_principal');
                    const preview = document.getElementById('principal-image-preview');
                    
                    // Limpar o input file
                    input.value = '';
                    
                    // Mostrar preview
                    preview.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <img src="${imageUrl}" alt="${imageTitle}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 3px;">
                            <div>
                                <strong>Imagem da Web:</strong><br>
                                <small>${imageTitle}</small>
                            </div>
                            <input type="hidden" name="web_image_principal" value="${imageUrl}">
                        </div>
                    `;
                } else {
                    // Para fotos adicionais
                    const preview = document.getElementById('adicional-images-preview');
                    const imageId = 'web_img_' + Date.now();
                    
                    preview.innerHTML += `
                        <div id="${imageId}" style="display: inline-block; margin: 5px; padding: 10px; background: #f8f9fa; border-radius: 5px; text-align: center;">
                            <img src="${imageUrl}" alt="${imageTitle}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 3px; margin-bottom: 5px;">
                            <div style="font-size: 12px; color: #666; margin-bottom: 5px;">${imageTitle}</div>
                            <button type="button" onclick="removeWebImage('${imageId}')" class="btn btn-danger" style="font-size: 10px; padding: 5px 10px;">
                                <i class="fas fa-times"></i> Remover
                            </button>
                            <input type="hidden" name="web_images_adicionais[]" value="${imageUrl}">
                        </div>
                    `;
                }
                
                closeImageSearch();
            };

            // Função para remover imagem da web
            window.removeWebImage = function(imageId) {
                document.getElementById(imageId).remove();
            };

            // Buscar imagens ao pressionar Enter
            document.getElementById('image-search-term').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchImages();
                }
            });

            // Controle do formulário de adicionar produto
            const showAddFormBtn = document.getElementById('show-add-form');
            const addProductForm = document.getElementById('add-product-form');
            const addProductButton = document.getElementById('add-product-button');
            const cancelAddBtn = document.getElementById('cancel-add');

            // Mostrar formulário
            showAddFormBtn.addEventListener('click', function() {
                addProductForm.style.display = 'block';
                addProductButton.style.display = 'none';
                // Focar no primeiro campo
                document.getElementById('nome').focus();
            });

            // Cancelar e ocultar formulário
            cancelAddBtn.addEventListener('click', function() {
                addProductForm.style.display = 'none';
                addProductButton.style.display = 'block';
                // Limpar formulário
                document.querySelector('#add-product-form form').reset();
            });
            
            // Validação do formulário
            document.querySelector('#add-product-form form').addEventListener('submit', function(e) {
                const fotoPrincipal = document.getElementById('foto_principal');
                const webImagePrincipal = document.querySelector('input[name="web_image_principal"]');
                
                // Verificar se tem arquivo OU imagem da web
                if (!fotoPrincipal.files.length && (!webImagePrincipal || !webImagePrincipal.value)) {
                    e.preventDefault();
                    alert('Por favor, selecione uma foto principal (arquivo ou imagem da web).');
                    return false;
                }
            });
        });
    </script>
</body>
</html>


