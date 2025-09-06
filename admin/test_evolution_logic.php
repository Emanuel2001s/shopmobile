<?php
require_once '../database/db_connect_env.php';

echo "<h2>Teste da Lógica Evolution</h2>";

// Verificar dados atuais no banco
echo "<h3>1. Dados atuais no banco:</h3>";
$config_query = $conn->query("SELECT evolution_instance_id, evolution_instance_name, evolution_api_url, evolution_api_token FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($config);
echo "</pre>";

// Verificar se a API está configurada
if (empty($config['evolution_api_url']) || empty($config['evolution_api_token'])) {
    echo "<p style='color: red;'>❌ API Evolution não configurada!</p>";
    exit;
}

echo "<p style='color: green;'>✅ API Evolution configurada</p>";

// Função para testar a API
function test_evolution_api($url, $token, $endpoint, $method = 'GET', $data = []) {
    $full_url = rtrim($url, '/') . $endpoint;
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $token
    ];
    
    $ch = curl_init($full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'url' => $full_url,
        'method' => $method,
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error
    ];
}

// Testar conexão com a API
echo "<h3>2. Teste de conexão com a API:</h3>";
$test_result = test_evolution_api($config['evolution_api_url'], $config['evolution_api_token'], '/instance/fetchInstances', 'GET');

echo "<pre>";
print_r($test_result);
echo "</pre>";

if ($test_result['http_code'] === 200) {
    echo "<p style='color: green;'>✅ Conexão com API OK</p>";
} else {
    echo "<p style='color: red;'>❌ Erro na conexão com API: " . $test_result['http_code'] . "</p>";
    if (!empty($test_result['error'])) {
        echo "<p style='color: red;'>Erro: " . $test_result['error'] . "</p>";
    }
}

// Se existe instância no banco, verificar status
if (!empty($config['evolution_instance_name'])) {
    echo "<h3>3. Verificando instância existente:</h3>";
    echo "<p>Instance Name: " . $config['evolution_instance_name'] . "</p>";
    echo "<p>Instance ID: " . $config['evolution_instance_id'] . "</p>";
    
    $status_result = test_evolution_api($config['evolution_api_url'], $config['evolution_api_token'], '/instance/connectionState/' . $config['evolution_instance_name'], 'GET');
    
    echo "<pre>";
    print_r($status_result);
    echo "</pre>";
    
    if ($status_result['http_code'] === 200) {
        $data = json_decode($status_result['response'], true);
        $state = $data['instance']['state'] ?? 'unknown';
        
        echo "<p>Status da instância: <strong>" . $state . "</strong></p>";
        
        if ($state === 'open' || $state === 'connected') {
            echo "<p style='color: green;'>✅ Instância conectada - deve permanecer no banco</p>";
        } elseif ($state === 'connecting') {
            echo "<p style='color: orange;'>🔄 Instância conectando - deve permanecer no banco</p>";
        } elseif ($state === 'error' || $state === 'destroyed') {
            echo "<p style='color: red;'>❌ Instância em estado inválido - deve ser limpa</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Estado desconhecido: " . $state . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Instância não encontrada na API - deve ser limpa</p>";
    }
}

// Interface para gerar QR Code
echo "<h3>4. Gerar QR Code e Testar Conexão:</h3>";
echo "<div id='qrcode-container' style='text-align: center; margin: 20px 0;'>";
echo "<button id='generate-qrcode' style='background: #007cba; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>Gerar QR Code</button>";
echo "</div>";

echo "<div id='qrcode-result' style='display: none;'>";
echo "<h4>QR Code Gerado:</h4>";
echo "<div id='qrcode-image' style='text-align: center; margin: 20px 0;'></div>";
echo "<div id='instance-info' style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'></div>";
echo "<button id='check-status' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; display: none;'>Verificar Status</button>";
echo "<div id='status-result' style='margin: 10px 0;'></div>";
echo "</div>";

// Verificar logs
echo "<h3>5. Logs de debug:</h3>";
$log_files = [
    'debug_evolution_detailed.log',
    'debug_evo_create.log',
    'debug_evo_status.log'
];

foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        echo "<h4>Arquivo: " . $log_file . "</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;'>";
        echo htmlspecialchars(file_get_contents($log_file));
        echo "</pre>";
    } else {
        echo "<p>Arquivo " . $log_file . " não encontrado</p>";
    }
}

// Botões de teste
echo "<h3>6. Ações de teste:</h3>";
echo "<p><a href='evolution_qrcode.php?action=status' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verificar Status</a></p>";

// Se existe instância no banco, mostrar botão para deletar
if (!empty($config['evolution_instance_name'])) {
    echo "<p><a href='evolution_qrcode.php?action=delete' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;' onclick='return confirm(\"Tem certeza que deseja deletar a instância?\")'>Deletar Instância</a></p>";
}

// Formulário para testar verificação de instância específica
echo "<h3>7. Testar verificação de instância específica:</h3>";
echo "<form method='GET' action='evolution_qrcode.php' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='check_instance'>";
echo "<input type='text' name='instance_name' placeholder='Nome da instância' style='padding: 8px; margin-right: 10px;' required>";
echo "<input type='submit' value='Verificar Instância' style='background: #17a2b8; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;'>";
echo "</form>";

echo "<h3>8. Verificar banco após ação:</h3>";
echo "<p><a href='test_evolution_logic.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Atualizar Página</a></p>";

// Instruções de uso
echo "<h3>9. Como testar a nova lógica:</h3>";
echo "<ol>";
echo "<li><strong>Gerar QR Code:</strong> Clique no botão 'Gerar QR Code' acima</li>";
echo "<li><strong>Escanear QR:</strong> Use o WhatsApp para escanear o QR Code que aparecerá</li>";
echo "<li><strong>Verificar Conexão:</strong> Clique em 'Verificar Status' para ver se conectou</li>";
echo "<li><strong>Salvamento Automático:</strong> Quando conectada, será salva automaticamente no banco</li>";
echo "</ol>";

?>

<script>
let currentInstanceName = '';

document.getElementById('generate-qrcode').addEventListener('click', function() {
    this.disabled = true;
    this.textContent = 'Gerando...';
    
    fetch('evolution_qrcode.php?action=qrcode')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar QR Code
                const qrcodeContainer = document.getElementById('qrcode-image');
                qrcodeContainer.innerHTML = `<img src="${data.qrcode}" alt="QR Code" style="max-width: 300px; border: 2px solid #ddd;">`;
                
                // Mostrar informações da instância
                const instanceInfo = document.getElementById('instance-info');
                instanceInfo.innerHTML = `
                    <strong>Instance Name:</strong> ${data.instance_name}<br>
                    <strong>Instance ID:</strong> ${data.instance_id}<br>
                    <strong>Status:</strong> ${data.state}<br>
                    <strong>Mensagem:</strong> ${data.message}
                `;
                
                currentInstanceName = data.instance_name;
                
                // Mostrar botão de verificar status
                document.getElementById('check-status').style.display = 'inline-block';
                document.getElementById('qrcode-result').style.display = 'block';
                
                // Iniciar verificação automática
                startStatusCheck();
            } else {
                alert('Erro ao gerar QR Code: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao gerar QR Code');
        })
        .finally(() => {
            document.getElementById('generate-qrcode').disabled = false;
            document.getElementById('generate-qrcode').textContent = 'Gerar QR Code';
        });
});

document.getElementById('check-status').addEventListener('click', function() {
    checkInstanceStatus();
});

function checkInstanceStatus() {
    if (!currentInstanceName) {
        alert('Nenhuma instância para verificar');
        return;
    }
    
    const statusResult = document.getElementById('status-result');
    statusResult.innerHTML = '<p>Verificando status...</p>';
    
    fetch(`evolution_qrcode.php?action=check_instance&instance_name=${currentInstanceName}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let statusHtml = `<p><strong>Status:</strong> ${data.status}</p>`;
                statusHtml += `<p><strong>Mensagem:</strong> ${data.message}</p>`;
                
                if (data.status === 'open' || data.status === 'connected') {
                    statusHtml += '<p style="color: green; font-weight: bold;">✅ WhatsApp conectado com sucesso!</p>';
                    statusHtml += '<p>Verifique se os dados foram salvos no banco clicando em "Atualizar Página"</p>';
                } else if (data.status === 'connecting') {
                    statusHtml += '<p style="color: orange;">🔄 Aguardando conexão... Tente novamente em alguns segundos.</p>';
                } else {
                    statusHtml += '<p style="color: red;">❌ Status inválido</p>';
                }
                
                statusResult.innerHTML = statusHtml;
            } else {
                statusResult.innerHTML = `<p style="color: red;">Erro: ${data.message}</p>`;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            statusResult.innerHTML = '<p style="color: red;">Erro ao verificar status</p>';
        });
}

function startStatusCheck() {
    // Verificar status automaticamente a cada 5 segundos
    const interval = setInterval(() => {
        if (!currentInstanceName) {
            clearInterval(interval);
            return;
        }
        
        fetch(`evolution_qrcode.php?action=check_instance&instance_name=${currentInstanceName}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && (data.status === 'open' || data.status === 'connected')) {
                    clearInterval(interval);
                    const statusResult = document.getElementById('status-result');
                    statusResult.innerHTML = `
                        <p style="color: green; font-weight: bold;">✅ WhatsApp conectado automaticamente!</p>
                        <p><strong>Status:</strong> ${data.status}</p>
                        <p><strong>Mensagem:</strong> ${data.message}</p>
                        <p>Verifique se os dados foram salvos no banco clicando em "Atualizar Página"</p>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro na verificação automática:', error);
            });
    }, 5000);
    
    // Parar verificação automática após 2 minutos
    setTimeout(() => {
        clearInterval(interval);
    }, 120000);
}
</script>
