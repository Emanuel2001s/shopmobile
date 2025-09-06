<?php
require_once 'database/db_connect.php';

// Verificar se a tabela mercadopago existe, se não, criar
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'mercadopago'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $conn->exec("CREATE TABLE mercadopago (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pedido_id INT NOT NULL,
            payment_id VARCHAR(255) NULL,
            status VARCHAR(50) DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            amount DECIMAL(10,2) NOT NULL,
            qr_code TEXT NULL,
            qr_code_base64 TEXT NULL,
            external_reference VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pedido_id (pedido_id),
            INDEX idx_status (status),
            INDEX idx_payment_id (payment_id),
            INDEX idx_external_reference (external_reference)
        )");
        // Tabela mercadopago criada automaticamente
    } else {
        // Verificar estrutura da tabela existente
        $stmt = $conn->prepare("DESCRIBE mercadopago");
        $stmt->execute();
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Estrutura da tabela verificada
        
        // Verificar estrutura da tabela pedidos também
        $stmt = $conn->prepare("DESCRIBE pedidos");
        $stmt->execute();
        $colunas_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao verificar/criar tabela mercadopago: " . $e->getMessage());
}

// Validar parâmetros
$pedido_id = intval($_GET['id'] ?? 0);
$external_ref = trim($_GET['ref'] ?? '');

if ($pedido_id <= 0 || empty($external_ref)) {
    die('Link de pagamento inválido');
}

// Buscar configurações do site
try {
    $config_query = $conn->query("SELECT nome_loja FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    $nome_site = $config['nome_loja'] ?? 'Loja Virtual';
} catch (Exception $e) {
    $nome_site = 'Loja Virtual';
    error_log("Erro ao buscar configurações: " . $e->getMessage());
}

// Buscar dados do pagamento
try {
    // Primeiro, verificar se o pedido existe
    try {
        // Verificar estrutura da tabela pedidos primeiro
        $stmt_check = $conn->prepare("DESCRIBE pedidos");
        $stmt_check->execute();
        $colunas_pedidos = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
        // Buscar dados do pedido
        $stmt_pedido = $conn->prepare("SELECT id, nome_completo, valor_total, observacoes FROM pedidos WHERE id = ?");
        $stmt_pedido->execute([$pedido_id]);
        $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            die('Pedido não encontrado');
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar pedido: " . $e->getMessage());
        die('Erro ao buscar dados do pedido');
    }
    
    // Depois, buscar dados do pagamento
    try {
        // Verificar estrutura da tabela mercadopago primeiro
        $stmt_check = $conn->prepare("DESCRIBE mercadopago");
        $stmt_check->execute();
        $colunas_mercadopago = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
        // Estrutura da tabela mercadopago verificada
        
        // Primeiro, verificar se existe algum pagamento aprovado para este pedido
        $stmt = $conn->prepare("
            SELECT mp.id, mp.pedido_id, mp.payment_id, mp.status, mp.payment_method, 
                   mp.amount, mp.qr_code, mp.qr_code_base64, mp.external_reference,
                   p.nome_completo, p.valor_total, p.observacoes
            FROM mercadopago mp
            INNER JOIN pedidos p ON mp.pedido_id = p.id
            WHERE mp.pedido_id = ? AND mp.status = 'approved'
            ORDER BY mp.created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$pedido_id]);
        $pagamento_aprovado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não encontrar pagamento aprovado, buscar pelo external_reference específico
        if (!$pagamento_aprovado) {
            $stmt = $conn->prepare("
                SELECT mp.id, mp.pedido_id, mp.payment_id, mp.status, mp.payment_method, 
                       mp.amount, mp.qr_code, mp.qr_code_base64, mp.external_reference,
                       p.nome_completo, p.valor_total, p.observacoes
                FROM mercadopago mp
                INNER JOIN pedidos p ON mp.pedido_id = p.id
                WHERE mp.pedido_id = ? AND mp.external_reference = ?
            ");
            
            $stmt->execute([$pedido_id, $external_ref]);
            $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Se encontrou pagamento aprovado, usar ele
            $pagamento = $pagamento_aprovado;
        }
        
        // Verificar resultado
        if ($pagamento_aprovado) {
            // Pagamento aprovado encontrado
        } else {
            // Executando consulta de pagamento
        }
        
    } catch (PDOException $e) {
        error_log("Erro específico na consulta de pagamento: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        throw $e; // Re-throw para ser capturado pelo catch externo
    }
    
    // Se não encontrar na tabela mercadopago, usar dados do pedido
    if (!$pagamento) {
        $pagamento = [
            'pedido_id' => $pedido['id'],
            'nome_completo' => $pedido['nome_completo'],
            'valor_total' => $pedido['valor_total'],
            'observacoes' => $pedido['observacoes'],
            'status' => 'pending',
            'external_reference' => $external_ref
        ];
    }
} catch (PDOException $e) {
    error_log("Erro na consulta de pagamento: " . $e->getMessage());
    // Se der erro na consulta, usar dados do pedido
    $pagamento = [
        'pedido_id' => $pedido['id'],
        'nome_completo' => $pedido['nome_completo'],
        'valor_total' => $pedido['valor_total'],
        'observacoes' => $pedido['observacoes'],
        'status' => 'pending',
        'external_reference' => $external_ref
    ];
}

// Verificar se já foi pago
if ($pagamento['status'] === 'approved') {
    $status_pagamento = 'approved';
    $status_texto = 'Pagamento Aprovado!';
    $status_cor = '#27ae60';
                // Status do pagamento definido como APROVADO
} elseif ($pagamento['status'] === 'pending') {
    $status_pagamento = 'pending';
    $status_texto = 'Aguardando Pagamento';
    $status_cor = '#f39c12';
    // Status do pagamento definido como PENDENTE
} else {
    $status_pagamento = 'cancelled';
    $status_texto = 'Pagamento Cancelado';
    $status_cor = '#e74c3c';
    // Status do pagamento definido como CANCELADO
}

// Buscar configurações do Mercado Pago
try {
    $config_query = $conn->query("SELECT mercadopago_public_key FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    $public_key = $config ? $config['mercadopago_public_key'] : '';
} catch (PDOException $e) {
    error_log("Erro ao buscar configurações do Mercado Pago: " . $e->getMessage());
    $public_key = '';
}

// Buscar produtos do pedido da tabela pedido_itens
try {
    $stmt = $conn->prepare("
        SELECT pi.nome_produto, pi.quantidade, pi.preco_unitario, pi.subtotal
        FROM pedido_itens pi
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $produtos_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar produtos do pedido: " . $e->getMessage());
    $produtos_array = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - Pedido #<?php echo $pedido_id; ?></title>
    
    <!-- Mercado Pago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .site-name {
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-weight: 600;
            opacity: 0.95;
        }
        
        .payment-description {
            font-size: 1rem;
            margin-bottom: 20px;
            opacity: 0.8;
            font-weight: 300;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .header .order-info {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
        }
        
        .content {
            padding: 40px;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .order-details h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 1.1rem;
        }
        
        .products-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
        }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
        }
        
        .product-price {
            color: #667eea;
            font-weight: bold;
        }
        
        .total-section {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .total-amount {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .payment-methods {
            margin-bottom: 30px;
        }
        
        .payment-methods h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .payment-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 20px;
        }
        
        .payment-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .payment-tab.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .payment-tab:not(.active) {
            color: #666;
        }
        
        .payment-content {
            display: none;
        }
        
        .payment-content.active {
            display: block;
        }
        
        .pix-section {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .qr-code img {
            max-width: 200px;
            height: auto;
        }
        
        .pix-code {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .copy-btn:hover {
            background: #5a6fd8;
        }
        
        .card-section {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .card-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .pay-btn {
            width: 100%;
            background: #27ae60;
            color: white;
            border: none;
            padding: 18px;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 20px;
        }
        
        .pay-btn:hover {
            background: #229954;
        }
        
        .pay-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Estilos para campos de parcelamento */
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            background-color: white;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group select option {
            padding: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .customer-info {
                grid-template-columns: 1fr;
            }
            
            .card-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="site-name"><?php echo htmlspecialchars($nome_site); ?></div>
            <div class="payment-description">Pagamento via Mercado Pago Seguro</div>
            <h1>💳 Pagamento</h1>
            <div class="order-info">
                Pedido #<?php echo $pedido_id; ?> - <?php echo htmlspecialchars($pagamento['nome_completo']); ?>
            </div>
            <div class="status-badge" style="background-color: <?php echo $status_cor; ?>;">
                <?php echo $status_texto; ?>
            </div>
        </div>
        
        <div class="content">
            <!-- Detalhes do Pedido -->
            <div class="order-details">
                <h3>📋 Detalhes do Pedido</h3>
                
                <div class="customer-info">
                    <div class="info-item">
                        <div class="info-label">Cliente</div>
                        <div class="info-value"><?php echo htmlspecialchars($pagamento['nome_completo']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value"><?php echo $status_texto; ?></div>
                    </div>
                </div>
                
                                            <?php if (!empty($produtos_array)): ?>
                            <div class="products-list">
                                <h4 style="margin-bottom: 15px; color: #333;">Produtos:</h4>
                                <?php foreach ($produtos_array as $produto): ?>
                                <div class="product-item">
                                    <div class="product-name">
                                        <?php echo htmlspecialchars($produto['nome_produto']); ?>
                                        <span style="color: #666; font-size: 0.9rem;">(Qtd: <?php echo $produto['quantidade']; ?>)</span>
                                    </div>
                                    <div class="product-price">R$ <?php echo number_format($produto['subtotal'], 2, ',', '.'); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
            </div>
            
            <!-- Valor Total -->
            <div class="total-section">
                <div class="total-amount">R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?></div>
                <div>Valor Total do Pedido</div>
            </div>
            
            <?php if ($status_pagamento === 'pending'): ?>
            <!-- Métodos de Pagamento -->
            <div class="payment-methods">
                <h3>💳 Escolha a Forma de Pagamento</h3>
                
                <div class="payment-tabs">
                    <div class="payment-tab active" onclick="showPaymentMethod('pix')">
                        📱 PIX
                    </div>
                    <div class="payment-tab" onclick="showPaymentMethod('card')">
                        💳 Cartão
                    </div>
                </div>
                
                <!-- PIX -->
                <div id="pix-content" class="payment-content active">
                    <div class="pix-section">
                        <h4 style="margin-bottom: 20px; color: #333;">Pagamento PIX</h4>
                        
                        <?php if (!empty($pagamento['qr_code_base64'])): ?>
                        <div class="qr-code">
                            <img src="data:image/png;base64,<?php echo $pagamento['qr_code_base64']; ?>" alt="QR Code PIX">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pagamento['qr_code'])): ?>
                        <div class="pix-code" id="pix-code">
                            <?php echo htmlspecialchars($pagamento['qr_code']); ?>
                        </div>
                        <button class="copy-btn" onclick="copyPixCode()">
                            📋 Copiar Código PIX
                        </button>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px; color: #666; font-size: 0.9rem;">
                            <p>📱 Abra o app do seu banco e escaneie o QR Code</p>
                            <p>ou copie o código PIX acima</p>
                        </div>
                    </div>
                </div>
                
                <!-- Cartão -->
                <div id="card-content" class="payment-content">
                    <div class="card-section">
                        <h4 style="margin-bottom: 20px; color: #333;">Pagamento com Cartão</h4>
                        
                        <form id="card-form">
                            <div class="form-group">
                                <label for="cardNumber">Número do Cartão</label>
                                <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            
                            <div class="card-row">
                                <div class="form-group">
                                    <label for="cardExpiration">Validade</label>
                                    <input type="text" id="cardExpiration" placeholder="MM/AA" maxlength="5">
                                </div>
                                <div class="form-group">
                                    <label for="cardCvv">CVV</label>
                                    <input type="text" id="cardCvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="cardholderName">Nome no Cartão</label>
                                <input type="text" id="cardholderName" placeholder="Nome como está no cartão">
                            </div>
                            
                            <div class="form-group">
                                <label for="cardholderEmail">E-mail</label>
                                <input type="email" id="cardholderEmail" placeholder="seu@email.com">
                            </div>
                            
                            <!-- Campos de Parcelamento -->
                                            <!-- Campo de banco emissor removido temporariamente -->
                <input type="hidden" id="cardIssuer" value="">
                            
                            <div class="form-group">
                                <label for="cardInstallments">Parcelas</label>
                                <select id="cardInstallments" required>
                                    <option value="1">1x sem juros - R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?></option>
                                </select>
                                <small style="color: #666; font-size: 0.8rem; margin-top: 5px; display: block;">
                                    💳 Juros mostrados são estimativas baseadas no mercado brasileiro. O valor final será calculado pelo Mercado Pago no momento do pagamento.
                                </small>
                            </div>
                            
                            <!-- Botão de teste removido pois não temos mais campo de banco -->
                            
                            <button type="submit" class="pay-btn" id="pay-btn">
                                💳 Pagar R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Loading -->
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Processando pagamento...</p>
            </div>
            
            <!-- Mensagens -->
            <div id="success-message" class="success-message" style="display: none;">
                ✅ Pagamento aprovado! Você receberá a confirmação em breve.
            </div>
            
            <div id="error-message" class="error-message" style="display: none;">
                ❌ Erro no pagamento. Tente novamente.
            </div>
            
            <?php else: ?>
            <!-- Status Final -->
            <div style="text-align: center; padding: 40px;">
                <?php if ($status_pagamento === 'approved'): ?>
                <div style="font-size: 4rem; margin-bottom: 20px;">✅</div>
                <h3 style="color: #27ae60; margin-bottom: 15px;">Pagamento Aprovado!</h3>
                <p style="color: #666; margin-bottom: 30px;">Seu pedido foi confirmado e está sendo processado.</p>
                <?php else: ?>
                <div style="font-size: 4rem; margin-bottom: 20px;">❌</div>
                <h3 style="color: #e74c3c; margin-bottom: 15px;">Pagamento Cancelado</h3>
                <p style="color: #666; margin-bottom: 30px;">Este pagamento foi cancelado ou expirou.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Variáveis globais para parcelamento
        let currentPaymentMethod = '';
        let currentIssuer = '';
        
        // Função para detectar o tipo de cartão
        function detectCardType(cardNumber) {
            const number = cardNumber.replace(/\s/g, '');
            console.log('Número limpo:', number, 'Tamanho:', number.length);
            
            // Precisa de pelo menos 4 dígitos para detectar
            if (number.length < 4) return 'unknown';
            
            if (/^4/.test(number)) return 'visa';
            if (/^5[1-5]/.test(number)) return 'master';
            if (/^3[47]/.test(number)) return 'amex';
            if (/^6/.test(number)) return 'elo';
            if (/^3[0-6]/.test(number)) return 'hipercard';
            
            return 'unknown';
        }
        
        // Função para buscar bancos emissores (simplificada)
        function getCardIssuers(paymentMethodId) {
            console.log('Buscando parcelas para:', paymentMethodId);
            // Como a API de bancos não existe, vamos buscar parcelas diretamente
            getInstallments(paymentMethodId);
        }
        
        // Função para buscar parcelas
        function getInstallments(paymentMethodId, issuerId = null) {
            const amount = <?php echo $pagamento['valor_total']; ?>;
            
            const data = {
                amount: amount,
                payment_method_id: paymentMethodId
            };
            
            // Removido issuer_id pois a API de bancos não existe
            
            console.log('Buscando parcelas para:', paymentMethodId, 'com dados:', data);
            
            fetch('mercadopago_get_installments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta parcelas:', data);
                
                if (data.success) {
                    const installmentsSelect = document.getElementById('cardInstallments');
                    installmentsSelect.innerHTML = '';
                    
                    if (data.installments && data.installments.length > 0) {
                        data.installments.forEach(installment => {
                            const option = document.createElement('option');
                            option.value = installment.installments;
                            
                            const installmentAmount = installment.installment_amount;
                            const totalAmount = installment.total_amount;
                            
                            if (installment.installments === 1) {
                                option.textContent = `1x sem juros - R$ ${installmentAmount.toFixed(2).replace('.', ',')}`;
                            } else {
                                const hasInterest = installment.has_interest || installment.installment_rate > 0;
                                const interestText = hasInterest ? 'com juros' : 'sem juros';
                                
                                let interestInfo = '';
                                let estimatedNote = '';
                                
                                if (hasInterest) {
                                    if (installment.installment_rate > 0) {
                                        interestInfo = ` (${installment.installment_rate.toFixed(2)}% a.m.)`;
                                        if (installment.is_estimated) {
                                            estimatedNote = ' (estimativa)';
                                        }
                                    } else if (installment.interest_amount > 0) {
                                        interestInfo = ` (+R$ ${installment.interest_amount.toFixed(2).replace('.', ',')})`;
                                        if (installment.is_estimated) {
                                            estimatedNote = ' (estimativa)';
                                        }
                                    }
                                }
                                
                                option.textContent = `${installment.installments}x ${interestText}${interestInfo}${estimatedNote} - R$ ${installmentAmount.toFixed(2).replace('.', ',')} (Total: R$ ${totalAmount.toFixed(2).replace('.', ',')})`;
                            }
                            
                            installmentsSelect.appendChild(option);
                        });
                        
                        if (data.fallback) {
                            console.log('Usando opções de parcelamento padrão');
                        }
                    } else {
                        // Se não retornou parcelas, criar opção padrão
                        console.log('Nenhuma parcela retornada, criando opção padrão');
                        const installmentsSelect = document.getElementById('cardInstallments');
                        installmentsSelect.innerHTML = '<option value="1">1x sem juros - R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?></option>';
                    }
                } else {
                    console.error('Erro ao buscar parcelas:', data.error);
                    // Se não conseguir carregar parcelas, usar opção padrão
                    const installmentsSelect = document.getElementById('cardInstallments');
                    installmentsSelect.innerHTML = '<option value="1">1x sem juros - R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?></option>';
                }
            })
            .catch(error => {
                console.error('Erro ao buscar parcelas:', error);
                // Se der erro, usar opção padrão
                const installmentsSelect = document.getElementById('cardInstallments');
                installmentsSelect.innerHTML = '<option value="1">1x sem juros - R$ <?php echo number_format($pagamento['valor_total'], 2, ',', '.'); ?></option>';
            });
        }
        
        // Função updateInstallments removida pois não temos mais campo de banco
        
        // Alternar entre métodos de pagamento
        function showPaymentMethod(method) {
            // Atualizar tabs
            document.querySelectorAll('.payment-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.payment-content').forEach(content => content.classList.remove('active'));
            
            if (method === 'pix') {
                document.querySelector('.payment-tab:first-child').classList.add('active');
                document.getElementById('pix-content').classList.add('active');
            } else {
                document.querySelector('.payment-tab:last-child').classList.add('active');
                document.getElementById('card-content').classList.add('active');
            }
        }
        
        // Copiar código PIX
        function copyPixCode() {
            const pixCode = document.getElementById('pix-code').textContent;
            navigator.clipboard.writeText(pixCode).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✅ Copiado!';
                btn.style.background = '#27ae60';
                
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '#667eea';
                }, 2000);
            });
        }
        
        // Formatação de campos
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value;
        });
        
        document.getElementById('cardExpiration').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
        
        document.getElementById('cardCvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Detectar tipo de cartão quando digitar
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            const cardType = detectCardType(e.target.value);
            console.log('Tipo de cartão detectado:', cardType, 'Valor:', e.target.value);
            
            if (cardType !== 'unknown' && cardType !== currentPaymentMethod) {
                console.log('Novo tipo de cartão detectado, buscando bancos...');
                currentPaymentMethod = cardType;
                getCardIssuers(cardType);
                getInstallments(cardType);
            }
        });
        
        // Removido evento de atualizar parcelas pois não temos mais campo de banco
        
        // Função de teste removida pois não temos mais campo de banco
        
        // Processar pagamento com cartão
        document.getElementById('card-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Mostrar loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('pay-btn').disabled = true;
            document.getElementById('success-message').style.display = 'none';
            document.getElementById('error-message').style.display = 'none';
            
            // Obter dados do formulário
            const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
            const cardExpiration = document.getElementById('cardExpiration').value;
            const cardCvv = document.getElementById('cardCvv').value;
            const cardholderName = document.getElementById('cardholderName').value;
            const cardholderEmail = document.getElementById('cardholderEmail').value;
            const installments = document.getElementById('cardInstallments').value;
            
            // Validar dados
            if (!cardNumber || !cardExpiration || !cardCvv || !cardholderName || !cardholderEmail || !installments) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('error-message').textContent = 'Preencha todos os campos obrigatórios.';
                document.getElementById('error-message').style.display = 'block';
                document.getElementById('pay-btn').disabled = false;
                return;
            }
            
            // Removido tratamento de issuer_id pois não temos mais campo de banco
            
            // Criar token do cartão usando a API do Mercado Pago
            const cardData = {
                card_number: cardNumber,
                security_code: cardCvv,
                expiration_month: cardExpiration.split('/')[0],
                expiration_year: '20' + cardExpiration.split('/')[1],
                cardholder: {
                    name: cardholderName
                }
            };
            
            // Fazer requisição para criar token via nosso servidor
            fetch('mercadopago_create_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(cardData)
            })
            .then(response => {
                return response.json();
            })
            .then(tokenData => {
                if (tokenData.success && tokenData.token_id) {
                    // Token criado com sucesso, agora processar pagamento
                    return fetch('mercadopago_process_card.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            pedido_id: <?php echo $pedido_id; ?>,
                            payment_method_id: currentPaymentMethod || 'master',
                            payer_email: cardholderEmail,
                            transaction_amount: <?php echo $pagamento['valor_total']; ?>,
                            installments: parseInt(installments),
                            token: tokenData.token_id
                        })
                    });
                } else {
                    throw new Error(tokenData.error || 'Erro ao criar token do cartão');
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                
                if (data.success) {
                    document.getElementById('success-message').style.display = 'block';
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    document.getElementById('error-message').textContent = data.error || 'Erro no pagamento';
                    document.getElementById('error-message').style.display = 'block';
                    document.getElementById('pay-btn').disabled = false;
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('error-message').textContent = 'Erro ao processar pagamento. Verifique os dados do cartão e tente novamente.';
                document.getElementById('error-message').style.display = 'block';
                document.getElementById('pay-btn').disabled = false;
            });
        });
        
        // Verificar status do pagamento a cada 5 segundos (apenas para PIX)
        <?php if ($status_pagamento === 'pending'): ?>
        setInterval(() => {
            fetch('mercadopago_check_status.php?pedido_id=<?php echo $pedido_id; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'approved') {
                    location.reload();
                }
            })
            .catch(error => {});
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html> 