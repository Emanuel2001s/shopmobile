<?php
// Script de diagnóstico para Internal Server Error
// Criado para identificar a causa do erro 500

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Internal Server Error</h1>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// 1. Verificar se o PHP está funcionando
echo "<h2>1. Status do PHP</h2>";
echo "<p>✅ PHP está funcionando - Versão: " . phpversion() . "</p>";

// 2. Verificar extensões PHP necessárias
echo "<h2>2. Extensões PHP</h2>";
$required_extensions = ['mysqli', 'pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p>✅ {$ext}: Carregada</p>";
    } else {
        echo "<p>❌ {$ext}: NÃO CARREGADA</p>";
    }
}

// 3. Verificar variáveis de ambiente
echo "<h2>3. Variáveis de Ambiente</h2>";
$env_vars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME'];
foreach ($env_vars as $var) {
    $value = getenv($var);
    if ($value !== false && !empty($value)) {
        echo "<p>✅ {$var}: Definida</p>";
    } else {
        echo "<p>❌ {$var}: NÃO DEFINIDA</p>";
    }
}

// 4. Testar conexão com banco de dados
echo "<h2>4. Conexão com Banco de Dados</h2>";
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'ecommerce';
    
    $pdo = new PDO("mysql:host={$host};dbname={$database}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Conexão com banco de dados: SUCESSO</p>";
    
    // Testar uma query simples
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products LIMIT 1");
    $result = $stmt->fetch();
    echo "<p>✅ Query de teste: SUCESSO</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão: " . $e->getMessage() . "</p>";
}

// 5. Verificar arquivos críticos
echo "<h2>5. Arquivos Críticos</h2>";
$critical_files = [
    'database/db_connect_env.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/auth.php',
    '.htaccess'
];

foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "<p>✅ {$file}: Existe</p>";
        
        // Verificar se o arquivo tem sintaxe válida (apenas para arquivos PHP)
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $syntax_check = shell_exec("php -l {$file} 2>&1");
            if (strpos($syntax_check, 'No syntax errors') !== false) {
                echo "<p>✅ {$file}: Sintaxe válida</p>";
            } else {
                echo "<p>❌ {$file}: ERRO DE SINTAXE - {$syntax_check}</p>";
            }
        }
    } else {
        echo "<p>❌ {$file}: NÃO ENCONTRADO</p>";
    }
}

// 6. Verificar permissões de diretórios
echo "<h2>6. Permissões de Diretórios</h2>";
$directories = ['uploads', 'css', 'js', 'admin'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        echo "<p>✅ {$dir}: Existe (Permissões: {$perms})</p>";
        
        if (is_writable($dir)) {
            echo "<p>✅ {$dir}: Gravável</p>";
        } else {
            echo "<p>❌ {$dir}: NÃO GRAVÁVEL</p>";
        }
    } else {
        echo "<p>❌ {$dir}: NÃO ENCONTRADO</p>";
    }
}

// 7. Verificar configurações do servidor
echo "<h2>7. Configurações do Servidor</h2>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Não disponível') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Não disponível') . "</p>";
echo "<p>Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Não disponível') . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "s</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";

// 8. Testar include de arquivos críticos
echo "<h2>8. Teste de Includes</h2>";
try {
    if (file_exists('database/db_connect_env.php')) {
        ob_start();
        include_once 'database/db_connect_env.php';
        $output = ob_get_clean();
        echo "<p>✅ database/db_connect_env.php: Include SUCESSO</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro no include database/db_connect_env.php: " . $e->getMessage() . "</p>";
}

// 9. Verificar logs de erro do PHP (se acessíveis)
echo "<h2>9. Logs de Erro</h2>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "<p>Log de erro: {$error_log}</p>";
    $recent_errors = shell_exec("tail -20 {$error_log} 2>/dev/null");
    if ($recent_errors) {
        echo "<h3>Últimos 20 erros:</h3>";
        echo "<pre>" . htmlspecialchars($recent_errors) . "</pre>";
    }
} else {
    echo "<p>Log de erro não encontrado ou não acessível</p>";
}

// 10. Informações do sistema
echo "<h2>10. Informações do Sistema</h2>";
echo "<p>OS: " . php_uname() . "</p>";
echo "<p>PHP SAPI: " . php_sapi_name() . "</p>";
echo "<p>Loaded Extensions: " . implode(', ', get_loaded_extensions()) . "</p>";

echo "<hr>";
echo "<p><strong>Diagnóstico concluído em " . date('Y-m-d H:i:s') . "</strong></p>";
echo "<p>Se você encontrou erros acima, eles podem ser a causa do Internal Server Error.</p>";
echo "<p>Verifique especialmente:</p>";
echo "<ul>";
echo "<li>Variáveis de ambiente não definidas</li>";
echo "<li>Erros de sintaxe em arquivos PHP</li>";
echo "<li>Problemas de conexão com banco de dados</li>";
echo "<li>Permissões de arquivos/diretórios</li>";
echo "<li>Extensões PHP faltando</li>";
echo "</ul>";
?>