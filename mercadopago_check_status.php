<?php
require_once 'database/db_connect_env.php';

// Verificar se foi passado o ID do pedido
$pedido_id = intval($_GET['pedido_id'] ?? 0);

if ($pedido_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do pedido inválido']);
    exit;
}

try {
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_access_token FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['mercadopago_access_token'])) {
        echo json_encode(['error' => 'Token de acesso não configurado']);
        exit;
    }
    
    // Buscar registro do pagamento
    $stmt = $conn->prepare("SELECT * FROM mercadopago WHERE pedido_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$pedido_id]);
    $mercadopago_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mercadopago_record) {
        echo json_encode(['error' => 'Pagamento não encontrado']);
        exit;
    }
    
    // Se já foi aprovado, retornar status
    if ($mercadopago_record['status'] === 'approved') {
        echo json_encode([
            'status' => 'approved',
            'message' => 'Pagamento já foi aprovado'
        ]);
        exit;
    }
    
    // Se não tem payment_id, não pode verificar
    if (empty($mercadopago_record['payment_id'])) {
        echo json_encode(['error' => 'ID do pagamento não disponível']);
        exit;
    }
    
    // Buscar status atual na API do Mercado Pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$mercadopago_record['payment_id']}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['mercadopago_access_token']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo json_encode(['error' => 'Erro ao consultar API do Mercado Pago']);
        exit;
    }
    
    $payment_data = json_decode($response, true);
    
    if (!$payment_data) {
        echo json_encode(['error' => 'Erro ao decodificar resposta da API']);
        exit;
    }
    
    $new_status = $payment_data['status'];
    $old_status = $mercadopago_record['status'];
    
    // Atualizar status na tabela mercadopago se mudou
    if ($new_status !== $old_status) {
        $stmt = $conn->prepare("UPDATE mercadopago SET status = ?, updated_at = NOW() WHERE payment_id = ?");
        $stmt->execute([$new_status, $mercadopago_record['payment_id']]);
        
        // Se foi aprovado, atualizar o pedido
        if ($new_status === 'approved') {
            // Atualizar status do pedido
            $stmt = $conn->prepare("UPDATE pedidos SET status_pagamento = 'pago', valor_pago = valor_total WHERE id = ?");
            $stmt->execute([$pedido_id]);
            
            // Buscar dados do pedido para enviar WhatsApp
            $stmt = $conn->prepare("SELECT p.*, c.whatsapp as cliente_whatsapp FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
            $stmt->execute([$pedido_id]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pedido && !empty($pedido['cliente_whatsapp'])) {
                // Enviar mensagem de confirmação via WhatsApp
                enviarConfirmacaoWhatsApp($pedido['cliente_whatsapp'], $pedido['nome_completo'], $pedido_id, $pedido['valor_total']);
            }
            
            error_log("Status Check: Pagamento aprovado para pedido {$pedido_id}");
        }
        
        error_log("Status Check: Status atualizado de '{$old_status}' para '{$new_status}' - Pedido: {$pedido_id}");
    }
    
    // Retornar status atual
    echo json_encode([
        'status' => $new_status,
        'message' => getStatusMessage($new_status)
    ]);
    
} catch (Exception $e) {
    error_log("Status Check: Erro - " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno']);
}

/**
 * Obter mensagem do status
 */
function getStatusMessage($status) {
    switch ($status) {
        case 'approved':
            return 'Pagamento aprovado!';
        case 'pending':
            return 'Aguardando pagamento';
        case 'in_process':
            return 'Pagamento em análise';
        case 'rejected':
            return 'Pagamento rejeitado';
        case 'cancelled':
            return 'Pagamento cancelado';
        default:
            return 'Status desconhecido';
    }
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