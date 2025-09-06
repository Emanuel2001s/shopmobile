<?php
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

echo "<h2>🔍 Teste das Configurações do Mercado Pago</h2>";

// Verificar configurações
$config_query = $conn->query("SELECT mercadopago_public_key, mercadopago_access_token, mercadopago_enabled FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

echo "<h3>📋 Configurações:</h3>";
echo "<p><strong>Mercado Pago Ativado:</strong> " . ($config['mercadopago_enabled'] ? '✅ SIM' : '❌ NÃO') . "</p>";
echo "<p><strong>Public Key:</strong> " . (empty($config['mercadopago_public_key']) ? '❌ VAZIA' : '✅ CONFIGURADA') . "</p>";
echo "<p><strong>Access Token:</strong> " . (empty($config['mercadopago_access_token']) ? '❌ VAZIO' : '✅ CONFIGURADO') . "</p>";

if (!$config['mercadopago_enabled']) {
    echo "<p style='color: red;'>❌ Mercado Pago não está ativado!</p>";
    exit;
}

if (empty($config['mercadopago_public_key']) || empty($config['mercadopago_access_token'])) {
    echo "<p style='color: red;'>❌ Credenciais não configuradas!</p>";
    exit;
}

// Testar conexão com Mercado Pago
echo "<h3>🌐 Teste de Conexão:</h3>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/payment_methods');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $config['mercadopago_access_token']
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<p style='color: red;'>❌ Erro de conexão: " . $curl_error . "</p>";
} else {
    if ($http_code === 200) {
        echo "<p style='color: green;'>✅ Conexão com Mercado Pago OK!</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro na API: HTTP " . $http_code . "</p>";
        echo "<p>Resposta: " . substr($response, 0, 200) . "...</p>";
    }
}

echo "<br><a href='orders.php'>← Voltar para Pedidos</a>";
?> 