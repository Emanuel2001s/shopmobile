<?php
require_once 'database/db_connect.php';

header('Content-Type: application/json');

// Receber dados do POST
$input = json_decode(file_get_contents('php://input'), true);

// Log para debug
error_log("Dados recebidos em mercadopago_create_token.php: " . json_encode($input));

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$card_number = $input['card_number'] ?? '';
$security_code = $input['security_code'] ?? '';
$expiration_month = $input['expiration_month'] ?? '';
$expiration_year = $input['expiration_year'] ?? '';
$cardholder_name = $input['cardholder']['name'] ?? '';

// Log para debug dos campos
error_log("Campos extraídos: card_number=" . $card_number . ", security_code=" . $security_code . ", expiration_month=" . $expiration_month . ", expiration_year=" . $expiration_year . ", cardholder_name=" . $cardholder_name);

// Validar dados
if (empty($card_number) || empty($security_code) || empty($expiration_month) || empty($expiration_year) || empty($cardholder_name)) {
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
    
    // Dados para criar o token
    $card_data = [
        'card_number' => $card_number,
        'security_code' => $security_code,
        'expiration_month' => $expiration_month,
        'expiration_year' => $expiration_year,
        'cardholder' => [
            'name' => $cardholder_name
        ]
    ];
    
    // Fazer requisição para API do Mercado Pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/card_tokens');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($card_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['mercadopago_access_token']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201) {
        error_log("Erro ao criar token: HTTP {$http_code} - {$response}");
        echo json_encode(['success' => false, 'error' => 'Erro ao processar dados do cartão']);
        exit;
    }
    
    $token_data = json_decode($response, true);
    
    if (!$token_data || !isset($token_data['id'])) {
        echo json_encode(['success' => false, 'error' => 'Erro ao decodificar resposta']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'token_id' => $token_data['id']
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao criar token: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}
?> 