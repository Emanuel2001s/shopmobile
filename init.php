<?php
/**
 * Script de Inicialização para Deploy no Dokploy
 * Este script configura automaticamente o projeto após o deploy
 */

// Configurações de erro para debug (remover em produção)
if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

echo "<h1>Inicialização do ShopMobile</h1>";
echo "<p>Configurando o projeto para o ambiente Dokploy...</p>";

// Verifica se as variáveis de ambiente estão configuradas
echo "<h2>1. Verificando Variáveis de Ambiente</h2>";
$requiredEnvVars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME'];
$envStatus = [];

foreach ($requiredEnvVars as $var) {
    $value = getenv($var) ?: $_ENV[$var] ?? null;
    $envStatus[$var] = $value !== null;
    $status = $envStatus[$var] ? '✅' : '❌';
    $displayValue = $envStatus[$var] ? (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) : 'NÃO DEFINIDA';
    echo "<p>{$status} {$var}: {$displayValue}</p>";
}

// Testa conexão com o banco de dados
echo "<h2>2. Testando Conexão com Banco de Dados</h2>";
try {
    require_once 'database/db_connect_env.php';
    echo "<p>✅ Conexão com banco de dados estabelecida com sucesso!</p>";
    
    // Verifica se as tabelas foram criadas
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>📊 Tabelas encontradas: " . count($tables) . "</p>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>{$table}</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão com banco de dados: " . $e->getMessage() . "</p>";
    echo "<p>Verifique as variáveis de ambiente DB_HOST, DB_USERNAME, DB_PASSWORD e DB_NAME</p>";
}

// Verifica permissões de diretórios
echo "<h2>3. Verificando Permissões de Diretórios</h2>";
$directories = [
    'uploads' => 'uploads',
    'uploads/sliders' => 'uploads/sliders'
];

foreach ($directories as $name => $path) {
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
        echo "<p>📁 Diretório {$name} criado</p>";
    }
    
    $writable = is_writable($path);
    $status = $writable ? '✅' : '❌';
    echo "<p>{$status} {$name}: " . ($writable ? 'Gravável' : 'Não gravável') . "</p>";
    
    if (!$writable) {
        chmod($path, 0777);
        echo "<p>🔧 Tentando corrigir permissões para {$name}</p>";
    }
}

// Verifica extensões PHP necessárias
echo "<h2>4. Verificando Extensões PHP</h2>";
$requiredExtensions = [
    'pdo' => 'PDO',
    'pdo_mysql' => 'PDO MySQL',
    'curl' => 'cURL',
    'gd' => 'GD (Imagens)',
    'json' => 'JSON',
    'mbstring' => 'Multibyte String',
    'zip' => 'ZIP'
];

foreach ($requiredExtensions as $ext => $name) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "<p>{$status} {$name}</p>";
}

// Informações do sistema
echo "<h2>5. Informações do Sistema</h2>";
echo "<p>🐘 Versão do PHP: " . PHP_VERSION . "</p>";
echo "<p>🖥️ Sistema Operacional: " . PHP_OS . "</p>";
echo "<p>💾 Limite de Memória: " . ini_get('memory_limit') . "</p>";
echo "<p>📤 Tamanho Máximo de Upload: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>📮 Tamanho Máximo de POST: " . ini_get('post_max_size') . "</p>";
echo "<p>⏱️ Tempo Máximo de Execução: " . ini_get('max_execution_time') . "s</p>";

// Configurações do Mercado Pago (se disponíveis)
echo "<h2>6. Configurações do Mercado Pago</h2>";
$mpPublicKey = getenv('MERCADOPAGO_PUBLIC_KEY') ?: $_ENV['MERCADOPAGO_PUBLIC_KEY'] ?? null;
$mpAccessToken = getenv('MERCADOPAGO_ACCESS_TOKEN') ?: $_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? null;
$mpEnabled = getenv('MERCADOPAGO_ENABLED') ?: $_ENV['MERCADOPAGO_ENABLED'] ?? '0';

if ($mpPublicKey && $mpAccessToken) {
    echo "<p>✅ Chaves do Mercado Pago configuradas</p>";
    echo "<p>📊 Status: " . ($mpEnabled === '1' ? 'Habilitado' : 'Desabilitado') . "</p>";
} else {
    echo "<p>⚠️ Chaves do Mercado Pago não configuradas (opcional)</p>";
}

// Links úteis
echo "<h2>7. Links Úteis</h2>";
echo "<p><a href='index.php' target='_blank'>🏠 Página Principal</a></p>";
echo "<p><a href='admin/' target='_blank'>⚙️ Painel Administrativo</a></p>";

// Status final
echo "<h2>8. Status da Inicialização</h2>";
$allEnvVarsSet = array_reduce($envStatus, function($carry, $item) { return $carry && $item; }, true);

if ($allEnvVarsSet && isset($conn)) {
    echo "<p style='color: green; font-weight: bold;'>✅ Inicialização concluída com sucesso!</p>";
    echo "<p>O projeto está pronto para uso.</p>";
    
    // Cria um arquivo de flag para indicar que a inicialização foi concluída
    file_put_contents('.initialized', date('Y-m-d H:i:s'));
    
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Inicialização incompleta</p>";
    echo "<p>Verifique as configurações acima e corrija os problemas encontrados.</p>";
}

echo "<hr>";
echo "<p><small>Script executado em: " . date('Y-m-d H:i:s') . "</small></p>";
echo "<p><small>Para segurança, remova ou renomeie este arquivo após a configuração inicial.</small></p>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f5f5f5;
}
h1, h2 {
    color: #333;
}
p {
    margin: 5px 0;
}
ul {
    margin: 10px 0;
}
a {
    color: #007cba;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
hr {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #ddd;
}
</style>