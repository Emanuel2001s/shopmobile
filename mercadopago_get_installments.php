<?php
require_once 'database/db_connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$amount = floatval($input['amount'] ?? 0);
$payment_method_id = $input['payment_method_id'] ?? '';
$issuer_id = $input['issuer_id'] ?? '';

if ($amount <= 0 || empty($payment_method_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

try {
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_access_token FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['mercadopago_access_token'])) {
        echo json_encode(['success' => false, 'error' => 'Token não configurado']);
        exit;
    }
    
    // Buscar parcelas da API do Mercado Pago
    $url = "https://api.mercadopago.com/v1/payment_methods/installments";
    $params = [
        'amount' => $amount,
        'payment_method_id' => $payment_method_id
    ];
    
    if (!empty($issuer_id)) {
        $params['issuer_id'] = $issuer_id;
    }
    
    $url .= '?' . http_build_query($params);
    
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
    
    error_log("GET_INSTALLMENTS: URL: {$url}");
    error_log("GET_INSTALLMENTS: HTTP Code: {$http_code}");
    error_log("GET_INSTALLMENTS: Response: " . substr($response, 0, 500));
    if ($curl_error) {
        error_log("GET_INSTALLMENTS: Curl Error: {$curl_error}");
    }
    
    $formatted_installments = [];
    
    if ($http_code === 200) {
        $installments_data = json_decode($response, true);
        
        if (isset($installments_data['payer_costs']) && !empty($installments_data['payer_costs'])) {
            foreach ($installments_data['payer_costs'] as $installment) {
                $installments = $installment['installments'];
                $installment_amount = $installment['installment_amount'];
                $total_amount = $installment['total_amount'];
                
                // CALCULAR JUROS REALISTAS BASEADO NO MERCADO BRASILEIRO
                $estimated_interest_rate = 0;
                $has_interest = false;
                
                if ($installments > 1) {
                    // Taxas de juros estimadas baseadas no mercado brasileiro
                    $interest_rates = [
                        2 => 1.99,   // 2x - 1.99% a.m.
                        3 => 2.49,   // 3x - 2.49% a.m.
                        4 => 2.99,   // 4x - 2.99% a.m.
                        5 => 3.49,   // 5x - 3.49% a.m.
                        6 => 3.99,   // 6x - 3.99% a.m.
                        7 => 4.49,   // 7x - 4.49% a.m.
                        8 => 4.99,   // 8x - 4.99% a.m.
                        9 => 5.49,   // 9x - 5.49% a.m.
                        10 => 5.99,  // 10x - 5.99% a.m.
                        11 => 6.49,  // 11x - 6.49% a.m.
                        12 => 6.99   // 12x - 6.99% a.m.
                    ];
                    
                    if (isset($interest_rates[$installments])) {
                        $estimated_interest_rate = $interest_rates[$installments];
                        $has_interest = true;
                        
                        // Calcular total estimado com juros
                        $total_with_interest = $amount * pow(1 + ($estimated_interest_rate / 100), $installments);
                        $installment_with_interest = $total_with_interest / $installments;
                        
                        // Usar valores calculados com juros estimados
                        $installment_amount = $installment_with_interest;
                        $total_amount = $total_with_interest;
                    }
                }
                
                $formatted_installments[] = [
                    'installments' => $installments,
                    'installment_rate' => $estimated_interest_rate,
                    'discount_rate' => $installment['discount_rate'] ?? 0,
                    'min_allowed_amount' => $installment['min_allowed_amount'] ?? $amount,
                    'max_allowed_amount' => $installment['max_allowed_amount'] ?? $amount,
                    'recommended_message' => $installments === 1 ? 'Pagamento à vista' : "{$installments}x",
                    'installment_amount' => $installment_amount,
                    'total_amount' => $total_amount,
                    'api_data' => true,
                    'has_interest' => $has_interest,
                    'interest_amount' => $total_amount - $amount,
                    'source' => 'installments_api_with_estimated_interest',
                    'is_estimated' => $has_interest
                ];
            }
        }
    }
    
    // Se não retornou parcelas da API, criar opções padrão com juros estimados
    if (empty($formatted_installments)) {
        $max_installments = min(12, max(1, intval($amount / 5)));
        
        for ($i = 1; $i <= $max_installments; $i++) {
            $installment_amount = $amount / $i;
            $total_amount = $amount;
            $estimated_interest_rate = 0;
            $has_interest = false;
            
            if ($i > 1) {
                // Taxas de juros estimadas
                $interest_rates = [
                    2 => 1.99, 3 => 2.49, 4 => 2.99, 5 => 3.49, 6 => 3.99,
                    7 => 4.49, 8 => 4.99, 9 => 5.49, 10 => 5.99, 11 => 6.49, 12 => 6.99
                ];
                
                if (isset($interest_rates[$i])) {
                    $estimated_interest_rate = $interest_rates[$i];
                    $has_interest = true;
                    
                    // Calcular total estimado com juros
                    $total_amount = $amount * pow(1 + ($estimated_interest_rate / 100), $i);
                    $installment_amount = $total_amount / $i;
                }
            }
            
            $formatted_installments[] = [
                'installments' => $i,
                'installment_rate' => $estimated_interest_rate,
                'discount_rate' => 0,
                'min_allowed_amount' => $amount,
                'max_allowed_amount' => $amount,
                'recommended_message' => $i === 1 ? 'Pagamento à vista' : "{$i}x",
                'installment_amount' => $installment_amount,
                'total_amount' => $total_amount,
                'api_data' => false,
                'has_interest' => $has_interest,
                'interest_amount' => $total_amount - $amount,
                'source' => 'fallback_with_estimated_interest',
                'is_estimated' => $has_interest
            ];
        }
    }
    
    // Remover duplicatas baseado no número de parcelas
    $unique_installments = [];
    foreach ($formatted_installments as $installment) {
        $key = $installment['installments'];
        if (!isset($unique_installments[$key])) {
            $unique_installments[$key] = $installment;
        }
    }
    
    $formatted_installments = array_values($unique_installments);
    
    // Ordenar por número de parcelas
    usort($formatted_installments, function($a, $b) {
        return $a['installments'] - $b['installments'];
    });
    
    echo json_encode([
        'success' => true,
        'installments' => $formatted_installments,
        'interest_note' => 'Juros são estimativas baseadas no mercado brasileiro. O valor final será calculado pelo Mercado Pago no momento do pagamento.'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar parcelas: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}
?> 