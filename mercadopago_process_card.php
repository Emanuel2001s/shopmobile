<?php
require_once 'database/db_connect.php';

// Receber dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$pedido_id = intval($input['pedido_id'] ?? 0);
$payment_method_id = $input['payment_method_id'] ?? '';
$payer_email = $input['payer_email'] ?? '';
$transaction_amount = floatval($input['transaction_amount'] ?? 0);
$installments = intval($input['installments'] ?? 1); // NOVO: Número de parcelas

// Validar dados
if ($pedido_id <= 0 || empty($payment_method_id) || empty($payer_email) || $transaction_amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados obrigatórios não fornecidos']);
    exit;
}

try {
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_access_token FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['mercadopago_access_token'])) {
        echo json_encode(['success' => false, 'error' => 'Token de acesso não configurado']);
        exit;
    }
    
    // Buscar dados do pedido
    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'error' => 'Pedido não encontrado']);
        exit;
    }
    
    // Verificar se já existe um pagamento aprovado
    $stmt = $conn->prepare("SELECT * FROM mercadopago WHERE pedido_id = ? AND status = 'approved'");
    $stmt->execute([$pedido_id]);
    $pagamento_aprovado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pagamento_aprovado) {
        echo json_encode(['success' => false, 'error' => 'Pedido já foi pago']);
        exit;
    }
    
    // Gerar external reference
    $external_reference = 'PEDIDO_' . $pedido_id . '_' . time();
    
    // Dados para criar o pagamento
    $payment_data = [
        'transaction_amount' => $transaction_amount,
        'token' => $input['token'] ?? '',
        'description' => "Pedido #{$pedido_id} - " . $pedido['nome_completo'],
        'installments' => $installments, // USAR O VALOR RECEBIDO
        'payment_method_id' => $payment_method_id,
        'payer' => [
            'email' => $payer_email
        ],
        'external_reference' => $external_reference
    ];
    
    // Removido issuer_id pois a API de bancos não existe
    
    // Fazer requisição para API do Mercado Pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['mercadopago_access_token']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201) {
        error_log("Erro ao criar pagamento com cartão: HTTP {$http_code} - {$response}");
        echo json_encode(['success' => false, 'error' => 'Erro ao processar pagamento']);
        exit;
    }
    
    $payment_response = json_decode($response, true);
    
    if (!$payment_response) {
        echo json_encode(['success' => false, 'error' => 'Erro ao decodificar resposta']);
        exit;
    }
    
    // Salvar pagamento no banco
    $stmt = $conn->prepare("
        INSERT INTO mercadopago (
            pedido_id, payment_id, status, payment_method, amount, 
            external_reference
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $pedido_id,
        $payment_response['id'],
        $payment_response['status'],
        $payment_response['payment_method_id'],
        $transaction_amount,
        $external_reference
    ]);
    
    // Verificar status do pagamento
    $status = $payment_response['status'];
    
    if ($status === 'approved') {
        // Atualizar status do pedido
        $stmt = $conn->prepare("UPDATE pedidos SET status_pagamento = 'pago', valor_pago = valor_total, status = 'confirmado' WHERE id = ?");
        $stmt->execute([$pedido_id]);
        
        // Buscar dados do pedido para enviar WhatsApp
        $stmt = $conn->prepare("SELECT p.*, c.whatsapp as cliente_whatsapp FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
        $stmt->execute([$pedido_id]);
        $pedido_completo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedido_completo && !empty($pedido_completo['cliente_whatsapp'])) {
            // Enviar mensagem de confirmação via WhatsApp
            enviarConfirmacaoWhatsApp($pedido_completo['cliente_whatsapp'], $pedido_completo['nome_completo'], $pedido_id, $pedido_completo['valor_total']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento aprovado com sucesso!',
            'status' => 'approved',
            'payment_id' => $payment_response['id']
        ]);
        
    } elseif ($status === 'pending') {
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento em processamento',
            'status' => 'pending',
            'payment_id' => $payment_response['id']
        ]);
        
    } elseif ($status === 'in_process') {
        echo json_encode([
            'success' => true,
            'message' => 'Pagamento em análise',
            'status' => 'in_process',
            'payment_id' => $payment_response['id']
        ]);
        
    } else {
        // Pagamento rejeitado ou com erro
        $error_message = 'Pagamento não aprovado';
        if (isset($payment_response['error']['message'])) {
            $error_message = $payment_response['error']['message'];
        }
        
        echo json_encode([
            'success' => false,
            'error' => $error_message,
            'status' => $status,
            'payment_id' => $payment_response['id']
        ]);
    }
    
} catch (Exception $e) {
    error_log('Erro ao processar pagamento com cartão: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}

/**
 * Enviar mensagem de confirmação via WhatsApp
 */
function enviarConfirmacaoWhatsApp($whatsapp, $nome_cliente, $pedido_id, $valor_total) {
    try {
        // Buscar configurações do WhatsApp
        $config_query = $GLOBALS['conn']->query("SELECT evolution_api_url, evolution_api_token, evolution_instance_name FROM configuracoes LIMIT 1");
        $config = $config_query->fetch(PDO::FETCH_ASSOC);
        
        if (empty($config['evolution_api_url']) || empty($config['evolution_api_token']) || empty($config['evolution_instance_name'])) {
            error_log('WhatsApp: Configurações incompletas para confirmação');
            return false;
        }
        
        // Formatar número do WhatsApp
        $whatsapp_clean = preg_replace('/\D/', '', $whatsapp);
        if (strlen($whatsapp_clean) === 11) {
            $whatsapp_clean = '55' . $whatsapp_clean;
        }
        
        // Verificar se o número está válido
        if (strlen($whatsapp_clean) < 12) {
            error_log('WhatsApp: Número inválido para confirmação - ' . $whatsapp_clean);
            return false;
        }
        
        // Mensagem de confirmação
        $mensagem = "🎉 *Parabéns {$nome_cliente}!*\n\n";
        $mensagem .= "Seu pagamento foi *APROVADO*!\n\n";
        $mensagem .= "📦 *Pedido #{$pedido_id}*\n";
        $mensagem .= "💰 *Valor:* R$ " . number_format($valor_total, 2, ',', '.') . "\n\n";
        $mensagem .= "✅ Seu pedido está sendo processado e será enviado em breve.\n\n";
        $mensagem .= "📞 Em caso de dúvidas, entre em contato conosco.\n";
        $mensagem .= "Obrigado pela confiança! 🙏";
        
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
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['evolution_api_token']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            error_log("WhatsApp: Confirmação enviada com sucesso para pedido {$pedido_id}");
            return true;
        } else {
            error_log("WhatsApp: Erro ao enviar confirmação - HTTP {$http_code} - {$response}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("WhatsApp: Erro ao enviar confirmação - " . $e->getMessage());
        return false;
    }
}
?> 