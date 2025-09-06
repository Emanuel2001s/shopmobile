<?php
// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'include/cart_functions.php';

// Função para log de debug
function debug_log($message) {
    $log_file = __DIR__ . '/debug_cart.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] CLEAR_CART: $message\n", FILE_APPEND | LOCK_EX);
}

debug_log("=== INÍCIO DA LIMPEZA DO CARRINHO ===");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("Erro: Método não é POST. Método recebido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    // Limpar o carrinho
    clear_cart();
    debug_log("Carrinho limpo com sucesso");
    
    echo json_encode(['success' => true, 'message' => 'Carrinho limpo com sucesso']);
    
} catch (Exception $e) {
    debug_log('Erro ao limpar carrinho: ' . $e->getMessage());
    debug_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor'
    ]);
}

debug_log("=== FIM DA LIMPEZA DO CARRINHO ===");
?>

