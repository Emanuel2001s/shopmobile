<?php
/**
 * Script de Diagnóstico para Erro Bad Gateway
 * Use este script para identificar problemas no deploy do Dokploy
 */

// Configurações de erro para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Diagnóstico ShopMobile</title></head><body>";
echo "<h1>🔍 Diagnóstico do ShopMobile</h1>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// 1. Verificar se o PHP está funcionando
echo "<h2>1. ✅ PHP Status</h2>";
echo "<p>Versão do PHP: " . phpversion() . "</p>";
echo "<p>Servidor: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Não definido' . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'Não definido' . "</p>";

// 2. Verificar extensões PHP necessárias
echo "<h2>2. Extensões PHP</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'gd', 'zip', 'mbstring', 'curl'];
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "<p>{$status} {$ext}</p>";
}

// 3. Verificar variáveis de ambiente
echo "<h2>3. Variáveis de Ambiente</h2>";
$envVars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME', 'MP_ACCESS_TOKEN', 'MP_PUBLIC_KEY'];
foreach ($envVars as $var) {
    $value = getenv($var) ?: $_ENV[$var] ?? null;
    $status = $value !== null ? '✅' : '❌';
    $display = $value ? (strlen($value) > 10 ? substr($value, 0, 10) . '...' : $value) : 'NÃO DEFINIDA';
    echo "<p>{$status} {$var}: {$display}</p>";
}

// 4. Verificar permissões de arquivos
echo "<h2>4. Permissões de Arquivos</h2>";
$paths = [
    '.' => 'Diretório raiz',
    './uploads' => 'Diretório uploads',
    './database' => 'Diretório database',
    './index.php' => 'Arquivo index.php'
];

foreach ($paths as $path => $description) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $readable = is_readable($path) ? '✅' : '❌';
        $writable = is_writable($path) ? '✅' : '❌';
        echo "<p>{$description}: Permissões {$perms} | Leitura {$readable} | Escrita {$writable}</p>";
    } else {
        echo "<p>❌ {$description}: Arquivo/diretório não encontrado</p>";
    }
}

// 5. Testar conexão com banco de dados
echo "<h2>5. Conexão com Banco de Dados</h2>";
try {
    $servername = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
    $username = getenv('DB_USERNAME') ?: $_ENV['DB_USERNAME'] ?? 'root';
    $password = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
    $dbname = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'shopmobile';
    
    echo "<p>Tentando conectar em: {$servername}</p>";
    echo "<p>Usuário: {$username}</p>";
    echo "<p>Banco: {$dbname}</p>";
    
    $dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "<p>✅ Conexão com banco estabelecida!</p>";
    
    // Verificar tabelas
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>📊 Tabelas encontradas: " . count($tables) . "</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão: " . $e->getMessage() . "</p>";
}

// 6. Verificar arquivos principais
echo "<h2>6. Arquivos Principais</h2>";
$mainFiles = [
    'index.php',
    'database/db_connect_env.php',
    '.htaccess',
    'admin/index.php'
];

foreach ($mainFiles as $file) {
    $status = file_exists($file) ? '✅' : '❌';
    $size = file_exists($file) ? filesize($file) . ' bytes' : 'N/A';
    echo "<p>{$status} {$file} ({$size})</p>";
}

// 7. Informações do servidor
echo "<h2>7. Informações do Servidor</h2>";
echo "<p>Sistema Operacional: " . php_uname() . "</p>";
echo "<p>Memória Limite: " . ini_get('memory_limit') . "</p>";
echo "<p>Tempo Máximo Execução: " . ini_get('max_execution_time') . "s</p>";
echo "<p>Upload Máximo: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post Máximo: " . ini_get('post_max_size') . "</p>";

// 8. Teste de escrita
echo "<h2>8. Teste de Escrita</h2>";
$testFile = './uploads/test_write.txt';
try {
    if (!is_dir('./uploads')) {
        mkdir('./uploads', 0777, true);
    }
    file_put_contents($testFile, 'Teste de escrita: ' . date('Y-m-d H:i:s'));
    if (file_exists($testFile)) {
        echo "<p>✅ Escrita no diretório uploads funcionando</p>";
        unlink($testFile);
    } else {
        echo "<p>❌ Falha na escrita no diretório uploads</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro no teste de escrita: " . $e->getMessage() . "</p>";
}

// 9. Logs de erro do PHP
echo "<h2>9. Configuração de Logs</h2>";
echo "<p>Log de erros habilitado: " . (ini_get('log_errors') ? 'Sim' : 'Não') . "</p>";
echo "<p>Arquivo de log: " . (ini_get('error_log') ?: 'Padrão do sistema') . "</p>";

echo "<hr>";
echo "<h2>🎯 Próximos Passos</h2>";
echo "<ol>";
echo "<li>Se houver ❌ em variáveis de ambiente, configure-as no Dokploy</li>";
echo "<li>Se houver ❌ na conexão do banco, verifique as credenciais</li>";
echo "<li>Se houver ❌ em permissões, ajuste no Dockerfile</li>";
echo "<li>Verifique os logs do container no Dokploy</li>";
echo "</ol>";

echo "</body></html>";
?>