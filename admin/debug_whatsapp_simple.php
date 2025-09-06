<?php
echo "<h1>🔍 Debug Simples - WhatsApp</h1>";
echo "<p>Iniciando teste...</p>";

// Testar includes primeiro
try {
    echo "<h2>📁 Testando includes...</h2>";
    
    if (file_exists('../includes/auth.php')) {
        echo "<p>✅ auth.php existe</p>";
        require_once '../includes/auth.php';
        echo "<p>✅ auth.php incluído</p>";
    } else {
        echo "<p>❌ auth.php não existe</p>";
        exit;
    }
    
    if (file_exists('../database/db_connect_env.php')) {
        echo "<p>✅ db_connect.php existe</p>";
        require_once '../database/db_connect_env.php';
        echo "<p>✅ db_connect.php incluído</p>";
    } else {
        echo "<p>❌ db_connect.php não existe</p>";
        exit;
    }
    
    echo "<h2>🔐 Testando autenticação...</h2>";
    checkAdminAuth();
    echo "<p>✅ Autenticação OK</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro nos includes: " . $e->getMessage() . "</p>";
    exit;
}

// Buscar configurações do WhatsApp
$config_query = $conn->query("SELECT evolution_api_url, evolution_api_token, evolution_instance_name FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

echo "<h3>📋 Configurações:</h3>";
echo "<pre>";
print_r($config);
echo "</pre>";

// Primeiro, vamos ver a estrutura da tabela clientes
echo "<h3>🔍 Verificando estrutura da tabela clientes...</h3>";

try {
    $structure_stmt = $conn->query("DESCRIBE clientes");
    $structure = $structure_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>📋 Estrutura da tabela clientes:</h4>";
    echo "<pre>";
    print_r($structure);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar estrutura: " . $e->getMessage() . "</p>";
    exit;
}

// Agora vamos buscar um cliente com WhatsApp usando a estrutura correta
echo "<h3>🔍 Buscando cliente com WhatsApp...</h3>";

try {
    // Vamos tentar com nome_completo primeiro (baseado no que vimos em outros arquivos)
    $stmt = $conn->prepare("
        SELECT c.whatsapp, c.nome_completo 
        FROM clientes c 
        WHERE c.whatsapp IS NOT NULL AND c.whatsapp != '' 
        LIMIT 1
    ");
    echo "<p>✅ Query preparada</p>";
    
    $stmt->execute();
    echo "<p>✅ Query executada</p>";
    
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>✅ Dados buscados</p>";
    
    echo "<h3>📱 Cliente de Teste:</h3>";
    echo "<pre>";
    print_r($cliente);
    echo "</pre>";
    
    if (!$cliente) {
        echo "<p style='color: red;'>❌ Nenhum cliente com WhatsApp encontrado!</p>";
        
        // Verificar quantos clientes existem
        $count_stmt = $conn->query("SELECT COUNT(*) as total FROM clientes");
        $total_clientes = $count_stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>📊 Total de clientes: " . $total_clientes['total'] . "</p>";
        
        // Verificar quantos têm WhatsApp
        $whatsapp_stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE whatsapp IS NOT NULL AND whatsapp != ''");
        $total_whatsapp = $whatsapp_stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>📱 Clientes com WhatsApp: " . $total_whatsapp['total'] . "</p>";
        
        // Mostrar alguns clientes para debug
        $sample_stmt = $conn->query("SELECT * FROM clientes LIMIT 3");
        $sample_clientes = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>📋 Amostra de clientes:</h4>";
        echo "<pre>";
        print_r($sample_clientes);
        echo "</pre>";
        
        exit;
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao buscar cliente: " . $e->getMessage() . "</p>";
    exit;
}

// Testar a função enviarWhatsApp
echo "<h3>🚀 Testando Função enviarWhatsApp...</h3>";

// Simular os parâmetros que seriam passados
$whatsapp = $cliente['whatsapp'];
$nome_cliente = $cliente['nome_completo'];
$payment_url = 'https://exemplo.com/payment_page.php?id=110&ref=TESTE';
$valor_total = 35.00;
$pedido_id = 110;

// Chamar a função
$resultado = enviarWhatsApp($whatsapp, $nome_cliente, $payment_url, $valor_total, $pedido_id);

echo "<h3>📊 Resultado:</h3>";
echo "<p><strong>Retorno da função:</strong> " . ($resultado ? '✅ TRUE' : '❌ FALSE') . "</p>";

/**
 * Função copiada do mercadopago_create_payment.php
 */
function enviarWhatsApp($whatsapp, $nome_cliente, $payment_url, $valor_total, $pedido_id) {
    try {
        echo "<p>🔍 Iniciando função enviarWhatsApp...</p>";
        
        // Buscar configurações do WhatsApp
        $config_query = $GLOBALS['conn']->query("SELECT evolution_api_url, evolution_api_token, evolution_instance_name FROM configuracoes LIMIT 1");
        $config = $config_query->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>📋 Configurações carregadas: " . (!empty($config['evolution_api_url']) ? '✅' : '❌') . "</p>";
        
        if (empty($config['evolution_api_url']) || empty($config['evolution_api_token']) || empty($config['evolution_instance_name'])) {
            echo "<p style='color: red;'>❌ Configurações incompletas</p>";
            return false;
        }
        
        // Formatar número do WhatsApp
        $whatsapp_clean = preg_replace('/\D/', '', $whatsapp);
        if (strlen($whatsapp_clean) === 11) {
            $whatsapp_clean = '55' . $whatsapp_clean;
        }
        
        echo "<p>📱 Número original: {$whatsapp}</p>";
        echo "<p>📱 Número formatado: {$whatsapp_clean}</p>";
        
        // Verificar se o número está válido
        if (strlen($whatsapp_clean) < 12) {
            echo "<p style='color: red;'>❌ Número inválido: {$whatsapp_clean}</p>";
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
        
        echo "<p>💬 Mensagem preparada: " . strlen($mensagem) . " caracteres</p>";
        
        // Dados para enviar mensagem
        $message_data = [
            'number' => $whatsapp_clean,
            'text' => $mensagem
        ];
        
        echo "<p>📤 Dados para envio:</p>";
        echo "<pre>" . print_r($message_data, true) . "</pre>";
        
        // URL da API
        $api_url = rtrim($config['evolution_api_url'], '/') . '/message/sendText/' . $config['evolution_instance_name'];
        
        echo "<p>🌐 URL da API: {$api_url}</p>";
        
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
        
        echo "<p>🚀 Fazendo requisição cURL...</p>";
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<p>📊 HTTP Code: {$http_code}</p>";
        
        if ($curl_error) {
            echo "<p style='color: red;'>❌ Erro cURL: {$curl_error}</p>";
            return false;
        }
        
        echo "<p>📥 Resposta da API:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        echo htmlspecialchars($response);
        echo "</pre>";
        
        if ($http_code !== 200) {
            echo "<p style='color: red;'>❌ HTTP Error: {$http_code}</p>";
            return false;
        }
        
        // Verificar se a resposta indica sucesso
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['status']) && $response_data['status'] === 'success') {
            echo "<p style='color: green;'>✅ Mensagem enviada com sucesso!</p>";
            return true;
        } else {
            echo "<p style='color: orange;'>⚠️ Resposta inválida ou não indica sucesso</p>";
            return false;
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
        return false;
    }
}
?> 