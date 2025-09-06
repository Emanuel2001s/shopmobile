<?php
// Iniciar buffer de saída para evitar output antes do header
ob_start();

require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

header('Content-Type: application/json');

try {
    // Receber dados do pedido via GET ou POST
    $pedido_id = intval($_GET['pedido_id'] ?? $_POST['pedido_id'] ?? 0);
    
    if ($pedido_id <= 0) {
        throw new Exception('Dados inválidos');
    }
    
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_public_key, mercadopago_access_token, mercadopago_enabled FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || !$config['mercadopago_enabled']) {
        throw new Exception('Mercado Pago não está configurado ou ativado');
    }
    
    if (empty($config['mercadopago_public_key']) || empty($config['mercadopago_access_token'])) {
        throw new Exception('Credenciais do Mercado Pago não configuradas');
    }
    
    // Buscar dados completos do pedido
    $stmt = $conn->prepare("
        SELECT p.*, c.whatsapp as cliente_whatsapp 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
    
    // Verificar se já existe um pagamento para este pedido
    $stmt = $conn->prepare("SELECT * FROM mercadopago WHERE pedido_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$pedido_id]);
    $pagamento_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pagamento_existente) {
        throw new Exception('Já existe um pagamento ativo para este pedido');
    }
    
    // Gerar ID único para referência externa
    $external_reference = 'PEDIDO_' . $pedido_id . '_' . time();
    
    // Extrair dados do pedido
    $valor_total = floatval($pedido['valor_total']);
    $nome_cliente = trim($pedido['nome_completo']);
    
    // Criar pagamento no Mercado Pago
    $payment_data = [
        'transaction_amount' => $valor_total,
        'description' => "Pedido #{$pedido_id} - {$nome_cliente}",
        'payment_method_id' => 'pix',
        'payer' => [
            'email' => 'cliente@exemplo.com',
            'first_name' => $nome_cliente,
            'identification' => [
                'type' => 'CPF',
                'number' => '00000000000'
            ]
        ],
        'external_reference' => $external_reference
    ];
    
    // Fazer requisição para o Mercado Pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['mercadopago_access_token']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('Erro na comunicação com Mercado Pago: ' . $curl_error);
    }
    
    if ($http_code !== 201) {
        throw new Exception('Erro ao criar pagamento no Mercado Pago. HTTP Code: ' . $http_code . '. Response: ' . $response);
    }
    
    $payment_response = json_decode($response, true);
    
    if (!$payment_response || !isset($payment_response['id'])) {
        throw new Exception('Resposta inválida do Mercado Pago');
    }
    
    // Salvar pagamento no banco
    $stmt = $conn->prepare("
        INSERT INTO mercadopago (
            pedido_id, payment_id, status, payment_method, amount, 
            qr_code, qr_code_base64, external_reference
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $qr_code = $payment_response['point_of_interaction']['transaction_data']['qr_code'] ?? '';
    $qr_code_base64 = $payment_response['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
    
    $stmt->execute([
        $pedido_id,
        $payment_response['id'],
        $payment_response['status'],
        $payment_response['payment_method_id'],
        $valor_total,
        $qr_code,
        $qr_code_base64,
        $external_reference
    ]);
    
    // Gerar URL da página de pagamento
    $payment_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_page.php?id=' . $pedido_id . '&ref=' . $external_reference;
    
    // Enviar mensagem via WhatsApp se tiver número
    $whatsapp_enviado = false;
    if (!empty($pedido['cliente_whatsapp'])) {
        $whatsapp_enviado = enviarWhatsApp($pedido['cliente_whatsapp'], $nome_cliente, $payment_url, $valor_total, $pedido_id);
    }
    
    // Limpar buffer e retornar JSON de sucesso
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => $whatsapp_enviado ? 'Pagamento criado e link enviado para WhatsApp do cliente!' : 'Pagamento criado com sucesso!',
        'pedido_id' => $pedido_id,
        'cliente' => $nome_cliente,
        'valor' => number_format($valor_total, 2, ',', '.'),
        'payment_url' => $payment_url,
        'whatsapp_enviado' => $whatsapp_enviado
    ]);
    
} catch (Exception $e) {
    // Limpar buffer e retornar JSON de erro
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Enviar mensagem via WhatsApp usando Evolution API
 */
function enviarWhatsApp($whatsapp, $nome_cliente, $payment_url, $valor_total, $pedido_id) {
    try {
        // Buscar configurações do WhatsApp
        $config_query = $GLOBALS['conn']->query("SELECT evolution_api_url, evolution_api_token, evolution_instance_name FROM configuracoes LIMIT 1");
        $config = $config_query->fetch(PDO::FETCH_ASSOC);
        
        if (empty($config['evolution_api_url']) || empty($config['evolution_api_token']) || empty($config['evolution_instance_name'])) {
            return false;
        }
        
        // Formatar número do WhatsApp
        $whatsapp_clean = preg_replace('/\D/', '', $whatsapp);
        if (strlen($whatsapp_clean) === 11) {
            $whatsapp_clean = '55' . $whatsapp_clean;
        }
        
        if (strlen($whatsapp_clean) < 12) {
            return false;
        }
        
        // Mensagem personalizada
        $mensagem = "🛍️ *Olá {$nome_cliente}!*\n\n";
        $mensagem .= "Seu pedido *#{$pedido_id}* está pronto para pagamento!\n\n";
        $mensagem .= "💰 *Valor:* R$ " . number_format($valor_total, 2, ',', '.') . "\n\n";
        $mensagem .= "💳 *Formas de Pagamento:*\n";
        $mensagem .= "• PIX (pagamento instantâneo)\n";
        $mensagem .= "• Cartão de Crédito/Débito\n\n";
        $mensagem .= "🔗 *Clique no link abaixo para pagar:*\n";
        $mensagem .= $payment_url . "\n\n";
        $mensagem .= "⚠️ *Importante:* Este link é exclusivo para seu pedido.\n";
        $mensagem .= "Após o pagamento, você receberá a confirmação automaticamente.";
        
        // Dados para enviar mensagem
        $message_data = [
            'number' => $whatsapp_clean,
            'text' => $mensagem
        ];
        
        // URL da API
        $api_url = rtrim($config['evolution_api_url'], '/') . '/message/sendText/' . $config['evolution_instance_name'];
        
        // Fazer requisição para Evolution API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $config['evolution_api_token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 || $http_code === 201) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}
?> 