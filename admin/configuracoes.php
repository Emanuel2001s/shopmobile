<?php
session_start();
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aba_atual = $_POST['aba_atual'] ?? 'gerais';
    $message = '';
    $message_type = '';
    if ($aba_atual === 'gerais') {
        $nome_loja = trim($_POST['nome_loja']);
        $whatsapp = trim($_POST['whatsapp']);
        $titulo_footer = trim($_POST['titulo_footer']);
        if (empty($nome_loja)) {
            $message = 'Nome da loja é obrigatório!';
            $message_type = 'error';
        } elseif (empty($whatsapp)) {
            $message = 'WhatsApp é obrigatório!';
            $message_type = 'error';
        } elseif (empty($titulo_footer)) {
            $message = 'Título do footer é obrigatório!';
            $message_type = 'error';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE configuracoes SET nome_loja = ?, whatsapp = ?, titulo_footer = ? WHERE id = 1");
                $stmt->execute([$nome_loja, $whatsapp, $titulo_footer]);
                $message = 'Configurações gerais salvas com sucesso!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao salvar configurações: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($aba_atual === 'personalizacao') {
        $taxa_entrega = isset($_POST['taxa_entrega']) ? floatval($_POST['taxa_entrega']) : 0;
        $horario_atendimento = trim($_POST['horario_atendimento'] ?? '');
        $cidade_entrega = trim($_POST['cidade_entrega'] ?? '');
        $endereco_loja = trim($_POST['endereco_loja'] ?? '');
        $pagamento_pix = isset($_POST['pagamento_pix']) ? 1 : 0;
        $pagamento_cartao = isset($_POST['pagamento_cartao']) ? 1 : 0;
        $pagamento_dinheiro = isset($_POST['pagamento_dinheiro']) ? 1 : 0;
        $garantia = trim($_POST['garantia'] ?? '3 meses');
        $politica_devolucao = trim($_POST['politica_devolucao'] ?? '');
        $instagram_url = trim($_POST['instagram_url'] ?? '');
        $facebook_url = trim($_POST['facebook_url'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $x_twitter_url = trim($_POST['x_twitter_url'] ?? '');
        try {
            $stmt = $conn->prepare("UPDATE configuracoes SET taxa_entrega = ?, horario_atendimento = ?, cidade_entrega = ?, endereco_loja = ?, pagamento_pix = ?, pagamento_cartao = ?, pagamento_dinheiro = ?, garantia = ?, politica_devolucao = ?, instagram_url = ?, facebook_url = ?, youtube_url = ?, x_twitter_url = ? WHERE id = 1");
            $stmt->execute([
                $taxa_entrega, $horario_atendimento, $cidade_entrega, $endereco_loja,
                $pagamento_pix, $pagamento_cartao, $pagamento_dinheiro, $garantia, $politica_devolucao,
                $instagram_url, $facebook_url, $youtube_url, $x_twitter_url
            ]);
            $message = 'Personalização da loja salva com sucesso!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao salvar personalização: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($aba_atual === 'whatsapp') {
        $evolution_api_url = $_POST['evolution_api_url'] ?? '';
        $evolution_api_token = $_POST['evolution_api_token'] ?? '';
        try {
            $stmt = $conn->prepare("UPDATE configuracoes SET evolution_api_url = ?, evolution_api_token = ? WHERE id = 1");
            $stmt->execute([$evolution_api_url, $evolution_api_token]);
            $message = 'Configurações do WhatsApp salvas com sucesso!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao salvar configurações do WhatsApp: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($aba_atual === 'mensagem') {
        $mensagem_pedido_personalizada = trim($_POST['mensagem_pedido_personalizada'] ?? '');
        $mensagem_pedido_ativa = isset($_POST['mensagem_pedido_ativa']) ? 1 : 0;
        $mensagem_status_confirmado = trim($_POST['mensagem_status_confirmado'] ?? '');
        $mensagem_status_cancelado = trim($_POST['mensagem_status_cancelado'] ?? '');
        $mensagem_status_ativa = isset($_POST['mensagem_status_ativa']) ? 1 : 0;
        try {
            $stmt = $conn->prepare("UPDATE configuracoes SET mensagem_pedido_personalizada = ?, mensagem_pedido_ativa = ?, mensagem_status_confirmado = ?, mensagem_status_cancelado = ?, mensagem_status_ativa = ? WHERE id = 1");
            $stmt->execute([$mensagem_pedido_personalizada, $mensagem_pedido_ativa, $mensagem_status_confirmado, $mensagem_status_cancelado, $mensagem_status_ativa]);
            $message = 'Mensagens personalizadas salvas com sucesso!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao salvar mensagens personalizadas: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($aba_atual === 'mercadopago') {
        $mercadopago_enabled = isset($_POST['mercadopago_enabled']) ? 1 : 0;
        $mercadopago_public_key = trim($_POST['mercadopago_public_key'] ?? '');
        $mercadopago_access_token = trim($_POST['mercadopago_access_token'] ?? '');
        try {
            $stmt = $conn->prepare("UPDATE configuracoes SET mercadopago_enabled = ?, mercadopago_public_key = ?, mercadopago_access_token = ? WHERE id = 1");
            $stmt->execute([$mercadopago_enabled, $mercadopago_public_key, $mercadopago_access_token]);
            $message = 'Configurações do Mercado Pago salvas com sucesso!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao salvar configurações do Mercado Pago: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Buscar configurações atuais
$config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

// Valores padrão caso não existam configurações
$nome_loja = $config ? $config['nome_loja'] : 'Loja Virtual';
$whatsapp = $config ? $config['whatsapp'] : '';
$titulo_footer = $config ? $config['titulo_footer'] : 'Loja Virtual - Todos os direitos reservados';
// Campos Evolution
$evolution_api_url = $config ? $config['evolution_api_url'] : '';
$evolution_api_token = $config ? $config['evolution_api_token'] : '';
// Campos Mensagem Personalizada
$mensagem_pedido_personalizada = $config ? $config['mensagem_pedido_personalizada'] : '';
$mensagem_pedido_ativa = $config ? $config['mensagem_pedido_ativa'] : 0;
// Campos Mensagem Status
$mensagem_status_confirmado = $config ? $config['mensagem_status_confirmado'] : '';
$mensagem_status_cancelado = $config ? $config['mensagem_status_cancelado'] : '';
$mensagem_status_ativa = $config ? $config['mensagem_status_ativa'] : 0;
// Campos Mercado Pago
$mercadopago_enabled = $config ? $config['mercadopago_enabled'] : 0;
$mercadopago_public_key = $config ? $config['mercadopago_public_key'] : '';
$mercadopago_access_token = $config ? $config['mercadopago_access_token'] : '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações da Empresa - Admin</title>
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
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: #ecf0f1;
        }

        .sidebar-header p {
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
            color: #bdc3c7;
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

        .container {
            max-width: 100%;
            margin: 0;
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
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .header-content {
            flex: 1;
        }
        
        .header p {
            opacity: 0.8;
            font-size: 0.9rem;
            margin: 0;
        }

        .nav-menu {
            background: #34495e;
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-size: 0.9rem;
        }

        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-menu a.active {
            background: #3498db;
        }
        
        /* Menu hambúrguer para mobile */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 0.8rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 45px;
            height: 45px;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
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
        }
        
        .mobile-overlay.active {
            display: block;
        }

        .content {
            padding: 2rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
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

        .form-group {
            margin-bottom: 2rem;
        }
        


        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .help-text {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
            line-height: 1.5;
            background: #f8f9fa;
            padding: 0.8rem;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        
        .help-text code {
            background: #e9ecef;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #495057;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }

        .btn i {
            font-size: 0.9rem;
        }

        .config-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .config-info h3 {
            color: #2c3e50;
            margin-bottom: 0.8rem;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .config-info p {
            color: #495057;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .tabs-config {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 2rem;
            justify-content: center;
            flex-wrap: nowrap;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .tab-btn {
            background: white;
            color: #495057;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            min-width: 160px;
            position: relative;
            overflow: hidden;
        }
        .tab-btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                position: fixed;
                left: -100%;
                z-index: 1000;
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
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
            
            .tabs-config {
                flex-wrap: wrap;
                gap: 0.5rem;
                padding: 0.8rem;
            }
            
            .tab-btn {
                min-width: 140px;
                padding: 0.8rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        @media (min-width: 769px) {
            .sidebar {
                display: block !important;
                left: 0 !important;
                top: 0 !important;
            }
            
            .mobile-menu-toggle {
                display: none !important;
            }
            
            .mobile-overlay {
                display: none !important;
            }
        }
        
        @media (max-width: 480px) {
            .tabs-config {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .tab-btn {
                min-width: auto;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>🛍️ Painel Admin</h2>
                <p>Gerencie sua loja</p>
            </div>
            
            <a href="index.php" class="sidebar-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <a href="products.php" class="sidebar-item">
                <i class="fas fa-box"></i> Produtos
            </a>
            
            <a href="categories.php" class="sidebar-item">
                <i class="fas fa-folder"></i> Categorias
            </a>
            
            <a href="orders.php" class="sidebar-item">
                <i class="fas fa-shopping-cart"></i> Pedidos
            </a>
            
            <a href="clientes.php" class="sidebar-item">
                <i class="fas fa-users"></i> Clientes
            </a>
            
            <a href="sliders.php" class="sidebar-item">
                <i class="fas fa-images"></i> Sliders
            </a>
            
            <a href="../index.php" class="sidebar-item" target="_blank">
                <i class="fas fa-external-link-alt"></i> Ver Loja
            </a>
            
            <a href="configuracoes.php" class="sidebar-item active">
                <i class="fas fa-cog"></i> Configurações
            </a>
            
            <a href="usuarios.php" class="sidebar-item">
                <i class="fas fa-users-cog"></i> Usuários
            </a>
            
            <div class="sidebar-divider"></div>
            
            <a href="logout.php" class="sidebar-item">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
        
        <!-- Conteúdo Principal -->
        <div class="admin-content">
            <div class="header">
                <div class="header-content">
                    <h1><i class="fas fa-cog"></i> Configurações da Empresa</h1>
                    <p>Gerencie as configurações gerais da sua loja</p>
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
                <div class="content">
            <div class="tabs-config">
                <button class="tab-btn active" onclick="showTab('tab-gerais', this)"><i class="fas fa-cogs"></i> Gerais</button>
                <button class="tab-btn" onclick="showTab('tab-personalizacao', this)"><i class="fas fa-paint-brush"></i> Personalização da Loja</button>
                <button class="tab-btn" onclick="showTab('tab-mensagem', this)"><i class="fas fa-comment-dots"></i> Mensagem de Pedido</button>
                <button class="tab-btn" onclick="showTab('tab-whatsapp', this)"><i class="fab fa-whatsapp"></i> WhatsApp</button>
                <button class="tab-btn" onclick="showTab('tab-mercadopago', this)"><i class="fas fa-credit-card"></i> Mercado Pago</button>
            </div>
            <div id="tab-gerais" class="tab-content" style="display:block;">
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <div class="config-info">
                    <h3><i class="fas fa-info-circle"></i> Sobre as Configurações</h3>
                    <p>
                        Estas configurações afetam toda a loja. O nome da loja aparecerá no cabeçalho de todas as páginas, 
                        o WhatsApp será usado para finalizar vendas e o título do footer aparecerá no rodapé de todas as páginas.
                    </p>
                </div>
                <form method="POST">
                    <input type="hidden" name="aba_atual" value="gerais">
                    <div class="form-group">
                        <label for="nome_loja">
                            <i class="fas fa-store"></i> Nome da Loja
                        </label>
                        <input type="text" 
                               id="nome_loja" 
                               name="nome_loja" 
                               value="<?php echo htmlspecialchars($nome_loja); ?>" 
                               required
                               maxlength="255">
                        <div class="help-text">
                            Este nome aparecerá no cabeçalho de todas as páginas da loja
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="whatsapp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </label>
                        <input type="text" 
                               id="whatsapp" 
                               name="whatsapp" 
                               value="<?php echo htmlspecialchars($whatsapp); ?>" 
                               required
                               maxlength="20"
                               placeholder="5511999999999">
                        <div class="help-text">
                            Número do WhatsApp com código do país (ex: 5511999999999). Será usado para finalizar vendas.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="titulo_footer">
                            <i class="fas fa-copyright"></i> Título do Footer
                        </label>
                        <input type="text" 
                               id="titulo_footer" 
                               name="titulo_footer" 
                               value="<?php echo htmlspecialchars($titulo_footer); ?>" 
                               required
                               maxlength="255">
                        <div class="help-text">
                            Texto que aparecerá no rodapé de todas as páginas
                        </div>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </form>
            </div>
            <div id="tab-personalizacao" class="tab-content" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="aba_atual" value="personalizacao">
                    <div class="form-group">
                        <label for="taxa_entrega"><i class="fas fa-truck"></i> Taxa de Entrega (R$)</label>
                        <input type="number" step="0.01" min="0" id="taxa_entrega" name="taxa_entrega" placeholder="Ex: 5.00" value="<?php echo htmlspecialchars($config['taxa_entrega'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="horario_atendimento"><i class="fas fa-clock"></i> Horário de Atendimento</label>
                        <input type="text" id="horario_atendimento" name="horario_atendimento" placeholder="Ex: Seg a Sex, 8h às 18h" value="<?php echo htmlspecialchars($config['horario_atendimento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="cidade_entrega"><i class="fas fa-map-marker-alt"></i> Cidade/Região de Entrega</label>
                        <input type="text" id="cidade_entrega" name="cidade_entrega" placeholder="Ex: Curitiba e região" value="<?php echo htmlspecialchars($config['cidade_entrega'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="endereco_loja"><i class="fas fa-map-pin"></i> Endereço da Loja</label>
                        <input type="text" id="endereco_loja" name="endereco_loja" placeholder="Rua, número, bairro, cidade, CEP" value="<?php echo htmlspecialchars($config['endereco_loja'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-money-bill-wave"></i> Formas de Pagamento Aceitas</label>
                        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                            <label><input type="checkbox" name="pagamento_pix" <?php if (!empty($config['pagamento_pix'])) echo 'checked'; ?>> Pix</label>
                            <label><input type="checkbox" name="pagamento_cartao" <?php if (!empty($config['pagamento_cartao'])) echo 'checked'; ?>> Cartão</label>
                            <label><input type="checkbox" name="pagamento_dinheiro" <?php if (!empty($config['pagamento_dinheiro'])) echo 'checked'; ?>> Dinheiro</label>
                        </div>
                    </div>
                    
                    <hr style="margin: 2rem 0; border: none; border-top: 2px solid #e9ecef;">
                    
                    <h3><i class="fas fa-shield-alt"></i> Informações de Produto</h3>
                    <div class="config-info">
                        <h4><i class="fas fa-info-circle"></i> Sobre as Informações de Produto</h4>
                        <p>
                            Configure a garantia e política de devolução que aparecerão na página de produtos para os clientes.
                            A garantia será exibida diretamente, e a política de devolução abrirá em um modal quando clicada.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="garantia">
                            <i class="fas fa-shield-alt"></i> Garantia
                        </label>
                        <input type="text" 
                               id="garantia" 
                               name="garantia" 
                               placeholder="Ex: 3 meses, 1 ano, 6 meses"
                               value="<?php echo htmlspecialchars($config['garantia'] ?? '3 meses'); ?>"
                               maxlength="100">
                        <div class="help-text">
                            Período de garantia que aparecerá na página de produtos (ex: "3 meses", "1 ano")
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="politica_devolucao">
                            <i class="fas fa-undo"></i> Política de Devolução
                        </label>
                        <textarea id="politica_devolucao" 
                                  name="politica_devolucao" 
                                  rows="8"
                                  placeholder="Digite a política de devolução completa que será exibida no modal quando o cliente clicar..."><?php echo htmlspecialchars($config['politica_devolucao'] ?? ''); ?></textarea>
                        <div class="help-text">
                            Política completa de devolução. Será exibida em um modal quando o cliente clicar em "Política de devolução" na página de produtos.
                        </div>
                    </div>
                    
                    <hr style="margin: 2rem 0; border: none; border-top: 2px solid #e9ecef;">
                    
                    <h3><i class="fab fa-instagram"></i> Redes Sociais</h3>
                    <div class="config-info">
                        <h4><i class="fas fa-info-circle"></i> Sobre as Redes Sociais</h4>
                        <p>
                            Configure os links das suas redes sociais. Os ícones aparecerão no rodapé do site.
                            Se deixar um campo vazio, o ícone ficará inativo (cinza) no rodapé.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="instagram_url">
                            <i class="fab fa-instagram"></i> Instagram
                        </label>
                        <input type="url" 
                               id="instagram_url" 
                               name="instagram_url" 
                               placeholder="https://instagram.com/sua_loja"
                               value="<?php echo htmlspecialchars($config['instagram_url'] ?? ''); ?>"
                               maxlength="255">
                        <div class="help-text">
                            URL completa do seu perfil do Instagram
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="facebook_url">
                            <i class="fab fa-facebook"></i> Facebook
                        </label>
                        <input type="url" 
                               id="facebook_url" 
                               name="facebook_url" 
                               placeholder="https://facebook.com/sua_loja"
                               value="<?php echo htmlspecialchars($config['facebook_url'] ?? ''); ?>"
                               maxlength="255">
                        <div class="help-text">
                            URL completa da sua página do Facebook
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="youtube_url">
                            <i class="fab fa-youtube"></i> YouTube
                        </label>
                        <input type="url" 
                               id="youtube_url" 
                               name="youtube_url" 
                               placeholder="https://youtube.com/@sua_loja"
                               value="<?php echo htmlspecialchars($config['youtube_url'] ?? ''); ?>"
                               maxlength="255">
                        <div class="help-text">
                            URL completa do seu canal do YouTube
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="x_twitter_url">
                            <i class="fab fa-x-twitter"></i> X (Twitter)
                        </label>
                        <input type="url" 
                               id="x_twitter_url" 
                               name="x_twitter_url" 
                               placeholder="https://x.com/sua_loja"
                               value="<?php echo htmlspecialchars($config['x_twitter_url'] ?? ''); ?>"
                               maxlength="255">
                        <div class="help-text">
                            URL completa do seu perfil do X (Twitter)
                        </div>
                    </div>
                    
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Salvar Personalização</button>
                </form>
            </div>
            <div id="tab-mensagem" class="tab-content" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="aba_atual" value="mensagem">
                    <h2><i class="fas fa-comment-dots"></i> Personalizar Mensagem de Pedido</h2>
                    <div class="config-info">
                        <h3><i class="fas fa-info-circle"></i> Sobre a Mensagem Personalizada</h3>
                        <p>
                            Personalize a mensagem que será enviada automaticamente para o cliente quando ele finalizar uma compra.
                            Se não configurar uma mensagem personalizada, será usada a mensagem padrão do sistema.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="mensagem_pedido_ativa" <?php if ($mensagem_pedido_ativa) echo 'checked'; ?>>
                            <strong>Ativar mensagem personalizada</strong>
                        </label>
                        <div class="help-text">
                            Marque esta opção para usar a mensagem personalizada em vez da mensagem padrão
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mensagem_pedido_personalizada">
                            <i class="fas fa-edit"></i> Mensagem Personalizada
                        </label>
                        <textarea 
                            id="mensagem_pedido_personalizada" 
                            name="mensagem_pedido_personalizada" 
                            rows="15" 
                            placeholder="Digite sua mensagem personalizada aqui..."
                            style="font-family: monospace; font-size: 14px; line-height: 1.4;"
                        ><?php echo htmlspecialchars($mensagem_pedido_personalizada); ?></textarea>
                        <div class="help-text">
                            <strong>Variáveis disponíveis:</strong><br>
                            <code>{nome_cliente}</code> - Nome do cliente<br>
                            <code>{whatsapp_cliente}</code> - WhatsApp do cliente<br>
                            <code>{produtos}</code> - Lista dos produtos com quantidades e preços<br>
                            <code>{valor_total}</code> - Valor total do pedido<br>
                            <code>{taxa_entrega}</code> - Taxa de entrega (se houver)<br>
                            <code>{id_pedido}</code> - ID do pedido<br>
                            <code>{data_pedido}</code> - Data e hora do pedido<br>
                            <code>{endereco_entrega}</code> - Endereço completo (se entrega)<br>
                            <code>{nome_loja}</code> - Nome da sua loja
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <h3><i class="fas fa-eye"></i> Preview da Mensagem</h3>
                        <div id="mensagem-preview" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;">
                            <em>Digite sua mensagem acima para ver o preview...</em>
                        </div>
                    </div>
                    
                    <hr style="margin: 2rem 0; border: none; border-top: 2px solid #e9ecef;">
                    
                    <h2><i class="fas fa-bell"></i> Mensagens de Status do Pedido</h2>
                    <div class="config-info">
                        <h3><i class="fas fa-info-circle"></i> Sobre as Mensagens de Status</h3>
                        <p>
                            Configure mensagens que serão enviadas automaticamente quando o status do pedido for alterado.
                            Estas mensagens ajudam a manter o cliente informado sobre o progresso do seu pedido.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="mensagem_status_ativa" <?php if ($mensagem_status_ativa) echo 'checked'; ?>>
                            <strong>Ativar mensagens de status</strong>
                        </label>
                        <div class="help-text">
                            Marque esta opção para enviar mensagens quando o status do pedido for alterado
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mensagem_status_confirmado">
                            <i class="fas fa-check-circle"></i> Mensagem - Pedido Confirmado
                        </label>
                        <textarea 
                            id="mensagem_status_confirmado" 
                            name="mensagem_status_confirmado" 
                            rows="8" 
                            placeholder="Digite a mensagem para pedidos confirmados..."
                            style="font-family: monospace; font-size: 14px; line-height: 1.4;"
                        ><?php echo htmlspecialchars($mensagem_status_confirmado); ?></textarea>
                        <div class="help-text">
                            <strong>Variáveis disponíveis:</strong><br>
                            <code>{nome_cliente}</code> - Nome do cliente<br>
                            <code>{whatsapp_cliente}</code> - WhatsApp do cliente<br>
                            <code>{produtos}</code> - Lista dos produtos com quantidades e preços<br>
                            <code>{valor_total}</code> - Valor total do pedido<br>
                            <code>{taxa_entrega}</code> - Taxa de entrega (se houver)<br>
                            <code>{id_pedido}</code> - ID do pedido<br>
                            <code>{data_pedido}</code> - Data e hora do pedido<br>
                            <code>{endereco_entrega}</code> - Endereço completo (se entrega)<br>
                            <code>{nome_loja}</code> - Nome da sua loja
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <h3><i class="fas fa-eye"></i> Preview - Pedido Confirmado</h3>
                        <div id="status-confirmado-preview" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; font-family: monospace; white-space: pre-wrap; max-height: 200px; overflow-y: auto;">
                            <em>Digite sua mensagem acima para ver o preview...</em>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mensagem_status_cancelado">
                            <i class="fas fa-times-circle"></i> Mensagem - Pedido Cancelado
                        </label>
                        <textarea 
                            id="mensagem_status_cancelado" 
                            name="mensagem_status_cancelado" 
                            rows="8" 
                            placeholder="Digite a mensagem para pedidos cancelados..."
                            style="font-family: monospace; font-size: 14px; line-height: 1.4;"
                        ><?php echo htmlspecialchars($mensagem_status_cancelado); ?></textarea>
                        <div class="help-text">
                            <strong>Variáveis disponíveis:</strong><br>
                            <code>{nome_cliente}</code> - Nome do cliente<br>
                            <code>{whatsapp_cliente}</code> - WhatsApp do cliente<br>
                            <code>{produtos}</code> - Lista dos produtos com quantidades e preços<br>
                            <code>{valor_total}</code> - Valor total do pedido<br>
                            <code>{taxa_entrega}</code> - Taxa de entrega (se houver)<br>
                            <code>{id_pedido}</code> - ID do pedido<br>
                            <code>{data_pedido}</code> - Data e hora do pedido<br>
                            <code>{endereco_entrega}</code> - Endereço completo (se entrega)<br>
                            <code>{nome_loja}</code> - Nome da sua loja
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <h3><i class="fas fa-eye"></i> Preview - Pedido Cancelado</h3>
                        <div id="status-cancelado-preview" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; font-family: monospace; white-space: pre-wrap; max-height: 200px; overflow-y: auto;">
                            <em>Digite sua mensagem acima para ver o preview...</em>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Salvar Todas as Mensagens
                    </button>
                </form>
            </div>
            
            <div id="tab-whatsapp" class="tab-content" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="aba_atual" value="whatsapp">
                    <h2>Configurações da API Evolution (WhatsApp)</h2>
                    <div class="form-group">
                        <label for="evolution_api_url">URL da API Evolution</label>
                        <input type="text" id="evolution_api_url" name="evolution_api_url" value="<?php echo htmlspecialchars($evolution_api_url); ?>" placeholder="https://seu-endpoint.com/api">
                    </div>
                    <div class="form-group">
                        <label for="evolution_api_token">Token de Autenticação</label>
                        <input type="text" id="evolution_api_token" name="evolution_api_token" value="<?php echo htmlspecialchars($evolution_api_token); ?>" placeholder="Seu token Evolution">
                    </div>
                    <h2>Conexão com WhatsApp Evolution</h2>
                    <div id="evolution-panel" style="background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #0001; padding: 1.5rem; margin-bottom: 2rem; max-width: 400px;">
                        <div id="evolution-status" style="margin-bottom: 1rem; font-weight: bold; color: #333;">
                            <span id="evo-status-text">Status: <span style="color: #888;">Carregando...</span></span>
                        </div>
                        <button type="button" id="btn-evolution-connect" class="btn" style="margin-bottom: 1rem;">Conectar WhatsApp</button>
                        <button type="button" id="btn-evolution-disconnect" class="btn" style="display:none;margin-bottom:1rem;background:#e74c3c;color:#fff;">Desconectar WhatsApp</button>
                        <div id="evolution-qrcode" style="text-align: center;"></div>
                        <div id="evolution-feedback" style="margin-top: 1rem; color: #e67e22;"></div>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Salvar Configurações WhatsApp
                    </button>
                </form>
            </div>
            
            <div id="tab-mercadopago" class="tab-content" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="aba_atual" value="mercadopago">
                    <h2><i class="fas fa-credit-card"></i> Configurações do Mercado Pago</h2>
                    
                    <div class="config-info">
                        <h3><i class="fas fa-info-circle"></i> Sobre o Mercado Pago</h3>
                        <p>
                            Configure o Mercado Pago para receber pagamentos via PIX e cartão de crédito. 
                            Os clientes receberão um link personalizado para pagar diretamente pelo WhatsApp.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="mercadopago_enabled" value="1" <?php echo $mercadopago_enabled ? 'checked' : ''; ?>>
                            <i class="fas fa-toggle-on"></i> Ativar Mercado Pago
                        </label>
                        <div class="help-text">
                            Marque esta opção para habilitar o sistema de pagamento do Mercado Pago
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mercadopago_public_key">
                            <i class="fas fa-key"></i> Public Key
                        </label>
                        <input type="text" 
                               id="mercadopago_public_key" 
                               name="mercadopago_public_key" 
                               value="<?php echo htmlspecialchars($mercadopago_public_key); ?>" 
                               placeholder="APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                               maxlength="255">
                        <div class="help-text">
                            Chave pública do Mercado Pago (encontrada no painel do MP)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mercadopago_access_token">
                            <i class="fas fa-lock"></i> Access Token
                        </label>
                        <input type="text" 
                               id="mercadopago_access_token" 
                               name="mercadopago_access_token" 
                               value="<?php echo htmlspecialchars($mercadopago_access_token); ?>" 
                               placeholder="APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                               maxlength="255">
                        <div class="help-text">
                            Token de acesso do Mercado Pago (encontrado no painel do MP)
                        </div>
                    </div>
                    
                    <div class="config-info" style="background: #fff3cd; border-left-color: #ffc107;">
                        <h3><i class="fas fa-exclamation-triangle"></i> Importante</h3>
                        <p>
                            <strong>Public Key:</strong> Usada no frontend para criar pagamentos<br>
                            <strong>Access Token:</strong> Usado no backend para processar pagamentos<br>
                            <strong>Segurança:</strong> Nunca compartilhe suas credenciais
                        </p>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Salvar Configurações Mercado Pago
                    </button>
                </form>
            </div>
        </div>
    </div>
    </div>
    </div>

    <script>
        // Formatação automática do WhatsApp
        document.getElementById('whatsapp').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });

        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const whatsapp = document.getElementById('whatsapp').value;
            
            if (whatsapp.length < 10) {
                e.preventDefault();
                alert('Por favor, insira um número de WhatsApp válido com pelo menos 10 dígitos.');
                return false;
            }
        });


    </script>
    <script>
function updateEvolutionStatus() {
    fetch('evolution_qrcode.php?action=status')
        .then(r => r.json())
        .then(data => {
            const statusText = document.getElementById('evo-status-text');
            const btnDisconnect = document.getElementById('btn-evolution-disconnect');
            const btnConnect = document.getElementById('btn-evolution-connect');
            const qrcodeDiv = document.getElementById('evolution-qrcode');
            const feedbackDiv = document.getElementById('evolution-feedback');
            
            console.log('Status Evolution:', data); // Debug
            
            if (data.success) {
                let status = (data.status || '').toLowerCase();
                let color = '#888';
                let msg = 'Desconectado';
                
                if (status === 'connected' || status === 'open') {
                    color = '#27ae60';
                    msg = 'Conectado';
                    if (btnDisconnect) btnDisconnect.style.display = 'inline-block';
                    if (btnConnect) btnConnect.style.display = 'none';
                    if (qrcodeDiv) qrcodeDiv.innerHTML = '';
                    if (feedbackDiv) feedbackDiv.innerHTML = '';
                } else if (status === 'connecting' || status === 'qrcode' || status === 'not_authenticated') {
                    color = '#e67e22';
                    msg = 'Aguardando conexão (escaneie o QR Code)';
                    if (btnDisconnect) btnDisconnect.style.display = 'none';
                    if (btnConnect) btnConnect.style.display = 'inline-block';
                } else {
                    if (btnDisconnect) btnDisconnect.style.display = 'none';
                    if (btnConnect) btnConnect.style.display = 'inline-block';
                }
                statusText.innerHTML = 'Status: <span style="color:' + color + '">' + msg + '</span>';
            } else {
                statusText.innerHTML = 'Status: <span style=\"color:#e74c3c\">Erro ao consultar status</span>';
                if (btnDisconnect) btnDisconnect.style.display = 'none';
                if (btnConnect) btnConnect.style.display = 'inline-block';
            }
        })
        .catch(error => {
            console.error('Erro ao verificar status:', error);
            const statusText = document.getElementById('evo-status-text');
            statusText.innerHTML = 'Status: <span style="color:#e74c3c">Erro ao consultar status</span>';
        });
}

function showEvolutionQRCode() {
    const qrcodeDiv = document.getElementById('evolution-qrcode');
    const feedbackDiv = document.getElementById('evolution-feedback');
    qrcodeDiv.innerHTML = '<div style="text-align:center;padding:1rem"><i class="fas fa-spinner fa-spin"></i> Gerando QR Code...</div>';
    feedbackDiv.innerHTML = '';
    
    fetch('evolution_qrcode.php?action=qrcode')
        .then(r => r.json())
        .then(data => {
            console.log('Resposta Evolution QRCode:', data); // Debug
            if (data.success && data.qrcode) {
                // Usa o valor de data.qrcode diretamente, sem adicionar prefixo
                qrcodeDiv.innerHTML = '<img src="' + data.qrcode + '" style="max-width: 220px; margin: 0 auto; display: block; border-radius: 8px; box-shadow: 0 2px 8px #0002;">';
                feedbackDiv.innerHTML = 'Escaneie o QR Code com o app do WhatsApp para conectar.';
                
                // Se temos o nome da instância, iniciar verificação automática
                if (data.instance_name) {
                    startAutoCheck(data.instance_name);
                }
            } else {
                qrcodeDiv.innerHTML = '';
                feedbackDiv.innerHTML = 'Erro ao gerar QR Code: ' + (data.error || 'Desconhecido');
            }
        })
        .catch(error => {
            console.error('Erro ao gerar QR Code:', error);
            qrcodeDiv.innerHTML = '';
            feedbackDiv.innerHTML = 'Erro ao gerar QR Code: ' + error.message;
        });
}

function startAutoCheck(instanceName) {
    console.log('Iniciando verificação automática para:', instanceName);
    
    const checkInterval = setInterval(() => {
        fetch(`evolution_qrcode.php?action=check_instance&instance_name=${instanceName}`)
            .then(r => r.json())
            .then(data => {
                console.log('Verificação automática:', data);
                
                if (data.success && (data.status === 'open' || data.status === 'connected')) {
                    clearInterval(checkInterval);
                    console.log('WhatsApp conectado! Atualizando status...');
                    
                    // Atualizar a interface
                    const feedbackDiv = document.getElementById('evolution-feedback');
                    if (feedbackDiv) {
                        feedbackDiv.innerHTML = '<span style="color: #27ae60;">✅ WhatsApp conectado com sucesso!</span>';
                    }
                    
                    // Atualizar status
                    updateEvolutionStatus();
                }
            })
            .catch(error => {
                console.error('Erro na verificação automática:', error);
            });
    }, 3000); // Verificar a cada 3 segundos
    
    // Parar verificação após 2 minutos
    setTimeout(() => {
        clearInterval(checkInterval);
        console.log('Verificação automática finalizada');
    }, 120000);
}

function disconnectEvolution() {
    const qrcodeDiv = document.getElementById('evolution-qrcode');
    const feedbackDiv = document.getElementById('evolution-feedback');
    fetch('evolution_qrcode.php?action=delete')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                qrcodeDiv.innerHTML = '';
                feedbackDiv.innerHTML = 'Instância desconectada e removida com sucesso!';
                updateEvolutionStatus();
            } else {
                feedbackDiv.innerHTML = 'Erro ao desconectar: ' + (data.error || 'Desconhecido');
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    updateEvolutionStatus();
    setInterval(updateEvolutionStatus, 5000); // Atualiza status a cada 5s
    document.getElementById('btn-evolution-connect').onclick = function() {
        showEvolutionQRCode();
    };
    // Botão de desconectar
    const btnDisconnect = document.getElementById('btn-evolution-disconnect');
    if (btnDisconnect) {
        btnDisconnect.onclick = function() {
            if (confirm('Tem certeza que deseja desconectar e remover a instância do WhatsApp?')) {
                disconnectEvolution();
            }
        };
    }
    
    // Preview da mensagem personalizada
    const mensagemTextarea = document.getElementById('mensagem_pedido_personalizada');
    const previewDiv = document.getElementById('mensagem-preview');
    
    if (mensagemTextarea && previewDiv) {
        function updatePreview() {
            let mensagem = mensagemTextarea.value;
            
            // Dados de exemplo para o preview
            const dadosExemplo = {
                '{nome_cliente}': 'João Silva',
                '{whatsapp_cliente}': '(11) 99999-9999',
                '{produtos}': '• *Produto Exemplo* (x2): R$ 50,00\n• *Outro Produto* (x1): R$ 30,00',
                '{valor_total}': 'R$ 130,00',
                '{taxa_entrega}': 'R$ 5,00',
                '{id_pedido}': '#12345',
                '{data_pedido}': '25/01/2025 14:30',
                '{endereco_entrega}': 'Rua das Flores, 123, Centro, São Paulo - SP, 01234-567',
                '{nome_loja}': 'Minha Loja'
            };
            
            // Substituir variáveis por dados de exemplo
            Object.keys(dadosExemplo).forEach(variavel => {
                mensagem = mensagem.replace(new RegExp(variavel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), dadosExemplo[variavel]);
            });
            
            if (mensagem.trim()) {
                previewDiv.innerHTML = mensagem;
            } else {
                previewDiv.innerHTML = '<em>Digite sua mensagem acima para ver o preview...</em>';
            }
        }
        
        mensagemTextarea.addEventListener('input', updatePreview);
        updatePreview(); // Preview inicial
    }
    
    // Preview das mensagens de status
    const statusConfirmadoTextarea = document.getElementById('mensagem_status_confirmado');
    const statusConfirmadoPreview = document.getElementById('status-confirmado-preview');
    const statusCanceladoTextarea = document.getElementById('mensagem_status_cancelado');
    const statusCanceladoPreview = document.getElementById('status-cancelado-preview');
    
    function updateStatusPreview(textarea, previewDiv) {
        if (textarea && previewDiv) {
            function updatePreview() {
                let mensagem = textarea.value;
                
                // Dados de exemplo para o preview de status
                const dadosExemplo = {
                    '{nome_cliente}': 'João Silva',
                    '{whatsapp_cliente}': '(11) 99999-9999',
                    '{produtos}': '• *Produto Exemplo* (x2): R$ 50,00\n• *Outro Produto* (x1): R$ 30,00',
                    '{valor_total}': '130,00',
                    '{taxa_entrega}': 'R$ 5,00',
                    '{id_pedido}': '#12345',
                    '{data_pedido}': '25/01/2025 14:30',
                    '{endereco_entrega}': 'Rua das Flores, 123, Centro, São Paulo - SP, 01234-567',
                    '{nome_loja}': 'Minha Loja'
                };
                
                // Substituir variáveis por dados de exemplo
                Object.keys(dadosExemplo).forEach(variavel => {
                    mensagem = mensagem.replace(new RegExp(variavel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), dadosExemplo[variavel]);
                });
                
                if (mensagem.trim()) {
                    previewDiv.innerHTML = mensagem;
                } else {
                    previewDiv.innerHTML = '<em>Digite sua mensagem acima para ver o preview...</em>';
                }
            }
            
            textarea.addEventListener('input', updatePreview);
            updatePreview(); // Preview inicial
        }
    }
    
    updateStatusPreview(statusConfirmadoTextarea, statusConfirmadoPreview);
    updateStatusPreview(statusCanceladoTextarea, statusCanceladoPreview);
    
    // Menu hambúrguer para mobile
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.getElementById('sidebar');
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
});
</script>
<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
</script>
</body>
</html>

