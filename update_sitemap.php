<?php
/**
 * Script para Atualização Automática do Sitemap
 * Pode ser executado via cron job ou chamada manual
 */

// Configurações
$sitemap_url = 'https://' . $_SERVER['HTTP_HOST'] . '/sitemap.php?save=true';
$log_file = 'sitemap_update.log';
$max_log_size = 1024 * 1024; // 1MB

// Função para escrever no log
function writeLog($message) {
    global $log_file, $max_log_size;
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
    
    // Verificar tamanho do log e rotacionar se necessário
    if (file_exists($log_file) && filesize($log_file) > $max_log_size) {
        if (file_exists($log_file . '.old')) {
            unlink($log_file . '.old');
        }
        rename($log_file, $log_file . '.old');
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// Função para verificar se precisa atualizar
function needsUpdate() {
    $sitemap_file = 'sitemap.xml';
    
    if (!file_exists($sitemap_file)) {
        return true; // Arquivo não existe, precisa criar
    }
    
    $sitemap_time = filemtime($sitemap_file);
    $current_time = time();
    
    // Atualizar se o sitemap tem mais de 24 horas
    return ($current_time - $sitemap_time) > (24 * 60 * 60);
}

// Função para verificar mudanças no banco
function hasChanges() {
    try {
        require_once 'database/db_connect_env.php';
        
        $sitemap_file = 'sitemap.xml';
        $sitemap_time = file_exists($sitemap_file) ? filemtime($sitemap_file) : 0;
        
        // Verificar produtos novos ou atualizados
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM produtos 
            WHERE estoque > 0 
            AND (
                UNIX_TIMESTAMP(created_at) > ? 
                OR UNIX_TIMESTAMP(updated_at) > ?
            )
        ");
        $stmt->execute([$sitemap_time, $sitemap_time]);
        $product_changes = $stmt->fetchColumn();
        
        // Verificar categorias novas ou atualizadas
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM categorias 
            WHERE status = 'ativo' 
            AND (
                UNIX_TIMESTAMP(created_at) > ? 
                OR UNIX_TIMESTAMP(updated_at) > ?
            )
        ");
        $stmt->execute([$sitemap_time, $sitemap_time]);
        $category_changes = $stmt->fetchColumn();
        
        return ($product_changes > 0 || $category_changes > 0);
        
    } catch (Exception $e) {
        writeLog("Erro ao verificar mudanças: " . $e->getMessage());
        return true; // Em caso de erro, forçar atualização
    }
}

// Função para atualizar sitemap
function updateSitemap() {
    global $sitemap_url;
    
    try {
        // Usar cURL para fazer a requisição
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sitemap_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP Error: " . $http_code);
        }
        
        writeLog("Sitemap atualizado com sucesso");
        return true;
        
    } catch (Exception $e) {
        writeLog("Erro ao atualizar sitemap: " . $e->getMessage());
        return false;
    }
}

// Função para notificar motores de busca
function notifySearchEngines() {
    $sitemap_url = 'https://' . $_SERVER['HTTP_HOST'] . '/sitemap.xml';
    
    $search_engines = [
        'Google' => 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url),
        'Bing' => 'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url)
    ];
    
    foreach ($search_engines as $engine => $ping_url) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $ping_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                writeLog("Notificação enviada para {$engine} com sucesso");
            } else {
                writeLog("Falha ao notificar {$engine} (HTTP {$http_code})");
            }
            
        } catch (Exception $e) {
            writeLog("Erro ao notificar {$engine}: " . $e->getMessage());
        }
        
        // Pequena pausa entre requisições
        sleep(1);
    }
}

// Função principal
function main() {
    writeLog("Iniciando verificação de atualização do sitemap");
    
    // Verificar se precisa atualizar
    if (!needsUpdate() && !hasChanges()) {
        writeLog("Sitemap está atualizado, nenhuma ação necessária");
        return;
    }
    
    writeLog("Atualizando sitemap...");
    
    // Atualizar sitemap
    if (updateSitemap()) {
        writeLog("Sitemap atualizado com sucesso");
        
        // Notificar motores de busca (opcional)
        if (isset($_GET['notify']) || isset($argv[1]) && $argv[1] === '--notify') {
            writeLog("Notificando motores de busca...");
            notifySearchEngines();
        }
    } else {
        writeLog("Falha ao atualizar sitemap");
    }
    
    writeLog("Processo finalizado");
}

// Verificar se é execução via linha de comando ou web
if (php_sapi_name() === 'cli') {
    // Execução via linha de comando (cron job)
    main();
} else {
    // Execução via web
    header('Content-Type: application/json');
    
    // Verificar token de segurança (opcional)
    $security_token = 'seu_token_secreto_aqui'; // Altere este token
    
    if (isset($_GET['token']) && $_GET['token'] === $security_token) {
        ob_start();
        main();
        $output = ob_get_clean();
        
        echo json_encode([
            'success' => true,
            'message' => 'Atualização executada com sucesso',
            'log' => $output
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Token de segurança inválido'
        ]);
    }
}
?>

