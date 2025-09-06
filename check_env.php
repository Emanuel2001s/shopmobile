<?php
/**
 * Script para verificar se as variáveis de ambiente estão sendo carregadas corretamente
 * Acesse este arquivo via browser para diagnosticar problemas de configuração
 */

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Verificação de Ambiente - ShopMobile</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; }\n.success { color: green; }\n.error { color: red; }\n.warning { color: orange; }\n.info { color: blue; }\npre { background: #f5f5f5; padding: 10px; border-radius: 5px; }\n</style>\n</head>\n<body>";

echo "<h1>🔍 Diagnóstico do Ambiente ShopMobile</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";

// 1. Verificar PHP
echo "<h2>📋 Informações do PHP</h2>";
echo "<p><span class='info'>Versão PHP:</span> " . phpversion() . "</p>";
echo "<p><span class='info'>SAPI:</span> " . php_sapi_name() . "</p>";

// 2. Verificar variáveis de ambiente
echo "<h2>🔧 Variáveis de Ambiente</h2>";
$required_vars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME'];

foreach ($required_vars as $var) {
    $value = getenv($var);
    if ($value !== false && !empty($value)) {
        echo "<p><span class='success'>✅ $var:</span> " . (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) . "</p>";
    } else {
        echo "<p><span class='error'>❌ $var:</span> NÃO DEFINIDA</p>";
    }
}

// 3. Verificar arquivo .env local (se existir)
echo "<h2>📄 Arquivo .env Local</h2>";
if (file_exists('.env')) {
    echo "<p><span class='warning'>⚠️ Arquivo .env encontrado (pode sobrescrever variáveis do Docker)</span></p>";
    $env_content = file_get_contents('.env');
    echo "<pre>" . htmlspecialchars(substr($env_content, 0, 500)) . (strlen($env_content) > 500 ? '\n...' : '') . "</pre>";
} else {
    echo "<p><span class='success'>✅ Nenhum arquivo .env local (correto para Docker)</span></p>";
}

// 4. Testar conexão com banco
echo "<h2>🗄️ Teste de Conexão com Banco</h2>";
try {
    require_once 'database/db_connect_env.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "<p><span class='success'>✅ Conexão com banco estabelecida com sucesso</span></p>";
        
        // Testar uma query simples
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p><span class='success'>✅ Query teste executada: {$result['total']} produtos encontrados</span></p>";
        }
    } else {
        echo "<p><span class='error'>❌ Objeto PDO não foi criado</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>❌ Erro na conexão: " . htmlspecialchars($e->getMessage()) . "</span></p>";
}

// 5. Verificar permissões de arquivos
echo "<h2>📁 Permissões de Arquivos</h2>";
$files_to_check = [
    'database/db_connect_env.php',
    'index.php',
    'uploads/',
    '.htaccess'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $readable = is_readable($file) ? '✅' : '❌';
        $writable = is_writable($file) ? '✅' : '❌';
        echo "<p><span class='info'>$file:</span> Leitura: $readable | Escrita: $writable</p>";
    } else {
        echo "<p><span class='error'>❌ $file: Não encontrado</span></p>";
    }
}

// 6. Verificar extensões PHP necessárias
echo "<h2>🔌 Extensões PHP</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p><span class='success'>✅ $ext</span></p>";
    } else {
        echo "<p><span class='error'>❌ $ext: NÃO CARREGADA</span></p>";
    }
}

// 7. Informações do servidor
echo "<h2>🖥️ Informações do Servidor</h2>";
echo "<p><span class='info'>SERVER_SOFTWARE:</span> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</p>";
echo "<p><span class='info'>DOCUMENT_ROOT:</span> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</p>";
echo "<p><span class='info'>HTTP_HOST:</span> " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "</p>";

// 8. Logs de erro PHP
echo "<h2>📝 Configuração de Logs</h2>";
echo "<p><span class='info'>log_errors:</span> " . (ini_get('log_errors') ? 'Ativado' : 'Desativado') . "</p>";
echo "<p><span class='info'>error_log:</span> " . (ini_get('error_log') ?: 'Padrão do sistema') . "</p>";
echo "<p><span class='info'>display_errors:</span> " . (ini_get('display_errors') ? 'Ativado' : 'Desativado') . "</p>";

echo "<hr>";
echo "<p><strong>💡 Próximos passos se houver problemas:</strong></p>";
echo "<ul>";
echo "<li>Verificar se as variáveis de ambiente estão configuradas no Dokploy</li>";
echo "<li>Verificar logs do container Docker</li>";
echo "<li>Verificar se o banco de dados está acessível</li>";
echo "<li>Verificar configurações do Apache/PHP no container</li>";
echo "</ul>";

echo "</body>\n</html>";
?>