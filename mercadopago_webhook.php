<?php
require_once 'database/db_connect.php';

// Log do webhook
error_log("Webhook Mercado Pago recebido: " . json_encode($_POST));

// Verificar se é uma notificação do Mercado Pago
if (!isset($_POST['type']) || $_POST['type'] !== 'payment') {
    error_log("Webhook: Tipo de notificação inválido");
    http_response_code(400);
    die('Tipo de notificação inválido');
}

// Verificar se temos o ID do pagamento
if (!isset($_POST['data']['id'])) {
    error_log("Webhook: ID do pagamento não encontrado");
    http_response_code(400);
    die('ID do pagamento não encontrado');
}

$payment_id = $_POST['data']['id'];

try {
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_access_token FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['mercadopago_access_token'])) {
        error_log("Webhook: Token de acesso não configurado");
        http_response_code(500);
        die('Token de acesso não configurado');
    }
    
    // Buscar detalhes do pagamento na API do Mercado Pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$payment_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['mercadopago_access_token']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Webhook: Erro ao buscar pagamento na API - HTTP {$http_code}");
        http_response_code(500);
        die('Erro ao buscar pagamento');
    }
    
    $payment_data = json_decode($response, true);
    
    if (!$payment_data) {
        error_log("Webhook: Erro ao decodificar resposta da API");
        http_response_code(500);
        die('Erro ao decodificar resposta');
    }
    
    // Log dos dados do pagamento
    error_log("Webhook: Dados do pagamento - " . json_encode($payment_data));
    
    // Buscar registro na tabela mercadopago
    $stmt = $conn->prepare("SELECT * FROM mercadopago WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $mercadopago_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mercadopago_record) {
        error_log("Webhook: Registro não encontrado para payment_id: {$payment_id}");
        http_response_code(404);
        die('Registro não encontrado');
    }
    
    $pedido_id = $mercadopago_record['pedido_id'];
    $old_status = $mercadopago_record['status'];
    $new_status = $payment_data['status'];
    
    // Atualizar status na tabela mercadopago
    $stmt = $conn->prepare("UPDATE mercadopago SET status = ?, updated_at = NOW() WHERE payment_id = ?");
    $stmt->execute([$new_status, $payment_id]);
    
    // Se o pagamento foi aprovado, atualizar o pedido
    if ($new_status === 'approved' && $old_status !== 'approved') {
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
        
        error_log("Webhook: Pagamento aprovado para pedido {$pedido_id}");
    }
    
    // Log do resultado
    error_log("Webhook: Status atualizado de '{$old_status}' para '{$new_status}' - Pedido: {$pedido_id}");
    
    // Responder com sucesso
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log("Webhook: Erro - " . $e->getMessage());
    http_response_code(500);
    die('Erro interno');
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