<?php
require_once '../database/db_connect_env.php';
header('Content-Type: application/json');

// Função para log detalhado
function log_debug($message, $data = null) {
    $log_entry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log_entry .= " - " . json_encode($data);
    }
    $log_entry .= "\n";
    file_put_contents(__DIR__ . '/debug_evolution_detailed.log', $log_entry, FILE_APPEND);
}

// Buscar configurações
$config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

$evolution_api_url = $config['evolution_api_url'] ?? '';
$evolution_api_token = $config['evolution_api_token'] ?? '';
$instance_id = $config['evolution_instance_id'] ?? '';

log_debug("Iniciando requisição", [
    'action' => $_GET['action'] ?? 'none',
    'instance_id' => $instance_id,
    'instance_name' => $config['evolution_instance_name'] ?? 'null'
]);

function evolution_api($endpoint, $method = 'GET', $data = [], $query = []) {
    global $evolution_api_url, $evolution_api_token;
    $url = rtrim($evolution_api_url, '/') . $endpoint;
    if (!empty($query)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $evolution_api_token
    ];
    $ch = curl_init($url);
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
    
    log_debug("API Call", [
        'url' => $url,
        'method' => $method,
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error
    ]);
    
    return [
        'body' => $response,
        'http_code' => $http_code,
        'error' => $error
    ];
}

function verificar_instancia_existe($instance_name) {
    global $evolution_api_url, $evolution_api_token;
    if (empty($evolution_api_url) || empty($evolution_api_token) || empty($instance_name)) {
        return false;
    }
    
    $result = evolution_api('/instance/connectionState/' . $instance_name, 'GET');
    return $result['http_code'] !== 404;
}

function limpar_dados_instancia() {
    global $conn;
    log_debug("Limpando dados da instância no banco");
    $stmt = $conn->prepare("UPDATE configuracoes SET evolution_instance_id = NULL, evolution_instance_name = NULL WHERE id = 1");
    $result = $stmt->execute();
    log_debug("Resultado da limpeza", ['success' => $result]);
    return $result;
}

function salvar_instancia_banco($instance_id, $instance_name) {
    global $conn;
    log_debug("=== INICIANDO SALVAMENTO NO BANCO ===");
    log_debug("Dados para salvar", [
        'instance_id' => $instance_id,
        'instance_name' => $instance_name
    ]);
    
    try {
        // Verificar se a conexão está ativa
        if (!$conn) {
            log_debug("ERRO: Conexão com banco não está ativa");
            return false;
        }
        
        // Verificar se os dados não estão vazios
        if (empty($instance_id) || empty($instance_name)) {
            log_debug("ERRO: Dados vazios para salvar", [
                'instance_id' => $instance_id,
                'instance_name' => $instance_name
            ]);
            return false;
        }
        
        // Preparar a query
        $stmt = $conn->prepare("UPDATE configuracoes SET evolution_instance_id = ?, evolution_instance_name = ? WHERE id = 1");
        
        if (!$stmt) {
            log_debug("ERRO: Falha ao preparar statement");
            return false;
        }
        
        log_debug("Statement preparado com sucesso");
        
        // Executar a query
        $result = $stmt->execute([$instance_id, $instance_name]);
        
        log_debug("Resultado da execução", [
            'success' => $result,
            'rowCount' => $stmt->rowCount(),
            'errorInfo' => $stmt->errorInfo()
        ]);
        
        if ($result) {
            // Verificar se realmente foi salvo
            $verify_stmt = $conn->prepare("SELECT evolution_instance_id, evolution_instance_name FROM configuracoes WHERE id = 1");
            $verify_stmt->execute();
            $verify_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            log_debug("Verificação após salvamento", [
                'saved_instance_id' => $verify_data['evolution_instance_id'] ?? 'null',
                'saved_instance_name' => $verify_data['evolution_instance_name'] ?? 'null',
                'expected_instance_id' => $instance_id,
                'expected_instance_name' => $instance_name
            ]);
            
            if ($verify_data['evolution_instance_id'] === $instance_id && $verify_data['evolution_instance_name'] === $instance_name) {
                log_debug("✅ SALVAMENTO CONFIRMADO - Dados salvos corretamente");
                return true;
            } else {
                log_debug("❌ SALVAMENTO FALHOU - Dados não foram salvos corretamente");
                return false;
            }
        } else {
            log_debug("❌ ERRO NA EXECUÇÃO - " . implode(', ', $stmt->errorInfo()));
            return false;
        }
        
    } catch (Exception $e) {
        log_debug("❌ EXCEÇÃO CAPTURADA", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return false;
    }
}

function criar_nova_instancia() {
    global $conn;
    $nome_instancia = 'loja_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $payload = [
        "instanceName" => $nome_instancia,
        "qrcode" => true,
        "integration" => "WHATSAPP-BAILEYS"
    ];
    
    log_debug("Criando nova instância", $payload);
    
    $result = evolution_api('/instance/create', 'POST', $payload);
    $data = json_decode($result['body'], true);
    
    log_debug("Resposta da criação", $data);
    
    $instance_id = $data['instance']['instanceId'] ?? '';
    $instance_name = $data['instance']['instanceName'] ?? '';
    
    if ($instance_id && $instance_name) {
        // NÃO salvar no banco ainda - apenas retornar os dados
        log_debug("Instância criada com sucesso (não salva no banco ainda)", [
            'instance_id' => $instance_id,
            'instance_name' => $instance_name
        ]);
        
        return [
            'success' => true,
            'instance_id' => $instance_id,
            'instance_name' => $instance_name,
            'qrcode' => $data['qrcode']['base64'] ?? '',
            'state' => $data['instance']['state'] ?? 'connecting'
        ];
    }
    
    log_debug("Erro ao criar instância", $result);
    return [
        'success' => false,
        'error' => 'Erro ao criar nova instância: ' . $result['body']
    ];
}

$action = $_GET['action'] ?? '';

// Buscar do banco
$instance_id = $config['evolution_instance_id'] ?? '';
$instance_name = $config['evolution_instance_name'] ?? '';

// 0. Deletar/desconectar instância
if ($action === 'delete' && $instance_name) {
    log_debug("Ação: Deletar instância", ['instance_name' => $instance_name]);
    
    $result = evolution_api('/instance/delete/' . $instance_name, 'DELETE');
    $data = json_decode($result['body'], true);
    
    if (isset($data['status']) && $data['status'] === 'SUCCESS' && !$data['error']) {
        // Limpa os campos no banco
        limpar_dados_instancia();
        echo json_encode(['success' => true, 'message' => 'Instância desconectada e removida com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao deletar instância: ' . $result['body']]);
    }
    exit;
}

// 1. Se ambos existem, verificar se a instância ainda existe na API
if ($action === 'qrcode' && $instance_id && $instance_name) {
    log_debug("Ação: Gerar QR Code - Verificando instância existente", [
        'instance_id' => $instance_id,
        'instance_name' => $instance_name
    ]);
    
    $result = evolution_api('/instance/connectionState/' . $instance_name, 'GET');
    
    // Verificar se a instância existe na API
    if ($result['http_code'] === 404 || $result['http_code'] >= 500 || !empty($result['error'])) {
        log_debug("Instância não existe mais na API, limpando dados e criando nova");
        
        // Instância não existe mais na API, limpar dados do banco e criar nova
        limpar_dados_instancia();
        
        // Criar nova instância
        $nova_instancia = criar_nova_instancia();
        if (!$nova_instancia['success']) {
            echo json_encode($nova_instancia);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'qrcode' => $nova_instancia['qrcode'],
            'instance_id' => $nova_instancia['instance_id'],
            'instance_name' => $nova_instancia['instance_name'],
            'message' => 'Nova instância criada automaticamente'
        ]);
        exit;
    }
    
    // Instância existe, verificar status
    $data = json_decode($result['body'], true);
    $state = $data['instance']['state'] ?? 'unknown';
    
    log_debug("Status da instância", [
        'state' => $state,
        'instance_data' => $data
    ]);
    
    // CORREÇÃO: Só limpar se estiver em estado inválido, NÃO se estiver connecting
    if ($state === 'error' || $state === 'destroyed' || empty($data['instance'])) {
        log_debug("Instância em estado inválido, limpando dados e criando nova", ['state' => $state]);
        
        // Limpar dados do banco
        limpar_dados_instancia();
        
        // Criar nova instância
        $nova_instancia = criar_nova_instancia();
        if (!$nova_instancia['success']) {
            echo json_encode($nova_instancia);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'qrcode' => $nova_instancia['qrcode'],
            'instance_id' => $nova_instancia['instance_id'],
            'instance_name' => $nova_instancia['instance_name'],
            'message' => 'Nova instância criada automaticamente (estado: ' . $state . ')'
        ]);
        exit;
    }
    
    // Se está conectado, manter no banco
    if ($state === 'open' || $state === 'connected') {
        log_debug("Instância já conectada, mantendo no banco", [
            'state' => $state,
            'instance_id' => $instance_id,
            'instance_name' => $instance_name
        ]);
        
        echo json_encode([
            'success' => true,
            'already_connected' => true,
            'message' => 'Você já está conectado ao WhatsApp!',
            'instance_id' => $instance_id,
            'instance_name' => $instance_name
        ]);
        exit;
    }
    
    // Para outros estados válidos (como 'connecting'), manter no banco e retornar QR Code
    $qrcode = '';
    if (isset($data['qrcode']['base64'])) {
        $qrcode = $data['qrcode']['base64'];
    } elseif (isset($data['qrcode'])) {
        $qrcode = $data['qrcode'];
    }
    
    log_debug("Retornando QR Code para instância em estado válido", [
        'state' => $state,
        'instance_id' => $instance_id,
        'instance_name' => $instance_name,
        'has_qrcode' => !empty($qrcode)
    ]);
    
    echo json_encode([
        'success' => true,
        'qrcode' => $qrcode,
        'instance_id' => $instance_id,
        'instance_name' => $instance_name,
        'state' => $state
    ]);
    exit;
}

// 2. Se ambos NULL, só então criar nova instância
if ($action === 'qrcode' && !$instance_id && !$instance_name) {
    log_debug("Ação: Gerar QR Code - Criando nova instância (sem dados no banco)");
    
    $nova_instancia = criar_nova_instancia();
    if (!$nova_instancia['success']) {
        echo json_encode($nova_instancia);
        exit;
    }
    
    // SALVAR NO BANCO IMEDIATAMENTE após criar
    $salvou = salvar_instancia_banco($nova_instancia['instance_id'], $nova_instancia['instance_name']);
    
    if ($salvou) {
        log_debug("✅ Nova instância criada e salva no banco", [
            'instance_id' => $nova_instancia['instance_id'],
            'instance_name' => $nova_instancia['instance_name']
        ]);
        
        echo json_encode([
            'success' => true,
            'qrcode' => $nova_instancia['qrcode'],
            'instance_id' => $nova_instancia['instance_id'],
            'instance_name' => $nova_instancia['instance_name'],
            'state' => $nova_instancia['state'],
            'message' => 'QR Code gerado. Escaneie para conectar.'
        ]);
    } else {
        log_debug("❌ Erro ao salvar nova instância no banco");
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao salvar instância no banco de dados'
        ]);
    }
    exit;
}

// 2.1. Se existe instância no banco, limpar e criar nova
if ($action === 'qrcode' && ($instance_id || $instance_name)) {
    log_debug("Ação: Gerar QR Code - Limpando dados existentes e criando nova instância");
    
    // LIMPAR dados existentes do banco
    $limpeza = limpar_dados_instancia();
    
    if ($limpeza) {
        log_debug("✅ Dados existentes limpos do banco");
        
        // Criar nova instância
        $nova_instancia = criar_nova_instancia();
        if (!$nova_instancia['success']) {
            echo json_encode($nova_instancia);
            exit;
        }
        
        // SALVAR NO BANCO IMEDIATAMENTE após criar
        $salvou = salvar_instancia_banco($nova_instancia['instance_id'], $nova_instancia['instance_name']);
        
        if ($salvou) {
            log_debug("✅ Nova instância criada e salva no banco (após limpeza)", [
                'instance_id' => $nova_instancia['instance_id'],
                'instance_name' => $nova_instancia['instance_name']
            ]);
            
            echo json_encode([
                'success' => true,
                'qrcode' => $nova_instancia['qrcode'],
                'instance_id' => $nova_instancia['instance_id'],
                'instance_name' => $nova_instancia['instance_name'],
                'state' => $nova_instancia['state'],
                'message' => 'QR Code gerado. Escaneie para conectar.'
            ]);
        } else {
            log_debug("❌ Erro ao salvar nova instância no banco (após limpeza)");
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao salvar instância no banco de dados'
            ]);
        }
    } else {
        log_debug("❌ Erro ao limpar dados existentes");
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao limpar dados existentes'
        ]);
    }
    exit;
}

// 3. Só consulta status se já existir instance_name
if ($action === 'status') {
    log_debug("Ação: Verificar status", [
        'instance_id' => $instance_id,
        'instance_name' => $instance_name
    ]);
    
    // Se não há instância no banco, retornar status desconectado
    if (empty($instance_id) || empty($instance_name)) {
        log_debug("Nenhuma instância no banco, retornando status desconectado");
        
        echo json_encode([
            'success' => true,
            'status' => 'disconnected',
            'instance_id' => null,
            'instance_name' => null,
            'message' => 'Nenhuma instância configurada'
        ]);
        exit;
    }
    
    $result = evolution_api('/instance/connectionState/' . $instance_name, 'GET');
    
    // Verificar se a instância existe na API
    if ($result['http_code'] === 404 || $result['http_code'] >= 500 || !empty($result['error'])) {
        log_debug("Instância não existe mais na API (status), limpando dados");
        
        // Instância não existe mais na API, limpar dados do banco
        limpar_dados_instancia();
        
        echo json_encode([
            'success' => true,
            'status' => 'disconnected',
            'instance_id' => null,
            'instance_name' => null,
            'message' => 'Instância não existe mais, dados limpos'
        ]);
        exit;
    }
    
    $data = json_decode($result['body'], true);
    $state = $data['instance']['state'] ?? 'unknown';
    
    log_debug("Status da instância (consulta)", [
        'state' => $state,
        'instance_data' => $data
    ]);
    
    // CORREÇÃO: Só limpar se estiver em estado inválido, NÃO se estiver connecting
    if ($state === 'error' || $state === 'destroyed' || empty($data['instance'])) {
        log_debug("Instância em estado inválido no status, limpando dados", ['state' => $state]);
        
        // Limpar dados do banco
        limpar_dados_instancia();
        
        echo json_encode([
            'success' => true,
            'status' => 'disconnected',
            'instance_id' => null,
            'instance_name' => null,
            'message' => 'Instância em estado inválido (' . $state . '), dados limpos'
        ]);
        exit;
    }
    
    // Se já está no banco e conectado, apenas retornar status
    if ($state === 'open' || $state === 'connected') {
        log_debug("Instância já conectada e no banco", [
            'state' => $state,
            'instance_id' => $instance_id,
            'instance_name' => $instance_name
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'status' => $state,
        'instance_id' => $instance_id,
        'instance_name' => $instance_name
    ]);
    exit;
}

// 4. Verificar status de uma instância específica (para quando escaneia QR Code)
if ($action === 'check_instance' && isset($_GET['instance_name'])) {
    $check_instance_name = $_GET['instance_name'];
    
    log_debug("=== VERIFICANDO INSTÂNCIA ESPECÍFICA ===", [
        'instance_name' => $check_instance_name,
        'action' => 'check_instance'
    ]);
    
    $result = evolution_api('/instance/connectionState/' . $check_instance_name, 'GET');
    
    if ($result['http_code'] === 404 || $result['http_code'] >= 500 || !empty($result['error'])) {
        log_debug("Instância específica não encontrada", [
            'instance_name' => $check_instance_name,
            'http_code' => $result['http_code'],
            'error' => $result['error']
        ]);
        
        echo json_encode([
            'success' => false,
            'status' => 'not_found',
            'message' => 'Instância não encontrada'
        ]);
        exit;
    }
    
    $data = json_decode($result['body'], true);
    $state = $data['instance']['state'] ?? 'unknown';
    $instance_id_from_api = $data['instance']['instanceId'] ?? '';
    $instance_name_from_api = $data['instance']['instanceName'] ?? '';
    
    log_debug("Status da instância específica", [
        'instance_name' => $check_instance_name,
        'state' => $state,
        'instance_id' => $instance_id_from_api,
        'instance_name_from_api' => $instance_name_from_api,
        'full_response' => $data
    ]);
    
    // Retornar apenas o status atual
    echo json_encode([
        'success' => true,
        'status' => $state,
        'instance_id' => $instance_id_from_api,
        'instance_name' => $instance_name_from_api,
        'message' => 'Status: ' . $state
    ]);
    exit;
}

log_debug("Ação inválida ou instância não criada", [
    'action' => $action,
    'instance_id' => $instance_id,
    'instance_name' => $instance_name
]);

echo json_encode(['success' => false, 'error' => 'Ação inválida ou instância não criada']);