<?php
require_once 'database/db_connect_env.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$payment_method_id = $input['payment_method_id'] ?? '';

// Log de debug
error_log("GET_ISSUERS: Recebido payment_method_id: " . $payment_method_id);

if (empty($payment_method_id)) {
    error_log("GET_ISSUERS: Erro - Método de pagamento não informado");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Método de pagamento não informado']);
    exit;
}

try {
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_access_token FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['mercadopago_access_token'])) {
        error_log("GET_ISSUERS: Token não configurado");
        echo json_encode(['success' => false, 'error' => 'Token não configurado']);
        exit;
    }
    
    // Fazer requisição para API
    $url = "https://api.mercadopago.com/v1/payment_methods/{$payment_method_id}/card_issuers";
    error_log("GET_ISSUERS: Token configurado, fazendo requisição para: " . $url);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['mercadopago_access_token']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("GET_ISSUERS: HTTP Code: {$http_code}, Response: {$response}, Curl Error: {$curl_error}");
    
    if ($http_code === 200) {
        $issuers_data = json_decode($response, true);
        error_log("GET_ISSUERS: Sucesso - Bancos encontrados: " . count($issuers_data));
        
        echo json_encode([
            'success' => true,
            'issuers' => $issuers_data
        ]);
    } else {
        error_log("GET_ISSUERS: Erro HTTP {$http_code} - {$response}");
        echo json_encode(['success' => false, 'error' => 'Erro ao buscar bancos']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar bancos: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}
?> 