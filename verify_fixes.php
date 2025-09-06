<?php
/**
 * Script de Verificação das Correções
 * Verifica se os problemas identificados nos logs do Dokploy foram resolvidos
 */

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

echo "<h1>🔧 Verificação das Correções - ShopMobile</h1>";
echo "<p>Verificando se os problemas identificados nos logs foram resolvidos...</p>";
echo "<hr>";

// 1. Verificar configurações do Apache
echo "<h2>1. Configurações do Apache</h2>";
echo "<ul>";

// Verificar se mod_rewrite está habilitado
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<li>✅ mod_rewrite está habilitado</li>";
    } else {
        echo "<li>❌ mod_rewrite NÃO está habilitado</li>";
    }
    
    if (in_array('mod_deflate', $modules)) {
        echo "<li>✅ mod_deflate está habilitado</li>";
    } else {
        echo "<li>❌ mod_deflate NÃO está habilitado</li>";
    }
    
    if (in_array('mod_expires', $modules)) {
        echo "<li>✅ mod_expires está habilitado</li>";
    } else {
        echo "<li>❌ mod_expires NÃO está habilitado</li>";
    }
} else {
    echo "<li>⚠️ Função apache_get_modules() não disponível</li>";
}

// Verificar ServerName
if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
    echo "<li>✅ ServerName definido: " . htmlspecialchars($_SERVER['SERVER_NAME']) . "</li>";
} else {
    echo "<li>❌ ServerName NÃO definido</li>";
}

echo "</ul>";

// 2. Verificar configurações PHP
echo "<h2>2. Configurações PHP</h2>";
echo "<ul>";

$php_configs = [
    'upload_max_filesize' => '10M',
    'post_max_size' => '10M',
    'max_execution_time' => '300',
    'memory_limit' => '256M',
    'display_errors' => 'Off',
    'log_errors' => 'On'
];

foreach ($php_configs as $config => $expected) {
    $current = ini_get($config);
    if ($config === 'display_errors') {
        $status = ($current == '0' || strtolower($current) == 'off') ? '✅' : '❌';
    } elseif ($config === 'log_errors') {
        $status = ($current == '1' || strtolower($current) == 'on') ? '✅' : '❌';
    } else {
        $status = ($current >= $expected) ? '✅' : '❌';
    }
    echo "<li>{$status} {$config}: {$current} (esperado: {$expected})</li>";
}

echo "</ul>";

// 3. Verificar extensões PHP
echo "<h2>3. Extensões PHP</h2>";
echo "<ul>";

$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'gd', 'zip'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<li>✅ {$ext} está carregada</li>";
    } else {
        echo "<li>❌ {$ext} NÃO está carregada</li>";
    }
}

echo "</ul>";

// 4. Verificar variáveis de ambiente
echo "<h2>4. Variáveis de Ambiente</h2>";
echo "<ul>";

// Tentar carregar .env
if (file_exists('.env')) {
    echo "<li>✅ Arquivo .env encontrado</li>";
    $env_content = file_get_contents('.env');
    $env_lines = explode("\n", $env_content);
    $env_vars = [];
    
    foreach ($env_lines as $line) {
        if (strpos($line, '=') !== false && !empty(trim($line)) && substr(trim($line), 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $env_vars[trim($key)] = trim($value);
        }
    }
    
    $required_vars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME'];
    foreach ($required_vars as $var) {
        if (isset($env_vars[$var]) && !empty($env_vars[$var])) {
            echo "<li>✅ {$var} está definida</li>";
        } else {
            echo "<li>❌ {$var} NÃO está definida ou está vazia</li>";
        }
    }
} else {
    echo "<li>❌ Arquivo .env NÃO encontrado</li>";
}

echo "</ul>";

// 5. Verificar conexão com banco de dados
echo "<h2>5. Conexão com Banco de Dados</h2>";
echo "<ul>";

try {
    if (file_exists('database/db_connect_env.php')) {
        echo "<li>✅ Arquivo de conexão encontrado</li>";
        
        // Tentar incluir o arquivo de conexão
        ob_start();
        include 'database/db_connect_env.php';
        $output = ob_get_clean();
        
        if (isset($pdo) && $pdo instanceof PDO) {
            echo "<li>✅ Conexão PDO estabelecida com sucesso</li>";
            
            // Testar uma query simples
            try {
                $stmt = $pdo->query("SELECT 1");
                echo "<li>✅ Query de teste executada com sucesso</li>";
            } catch (Exception $e) {
                echo "<li>❌ Erro na query de teste: " . htmlspecialchars($e->getMessage()) . "</li>";
            }
        } else {
            echo "<li>❌ Falha ao estabelecer conexão PDO</li>";
        }
    } else {
        echo "<li>❌ Arquivo de conexão NÃO encontrado</li>";
    }
} catch (Exception $e) {
    echo "<li>❌ Erro ao testar conexão: " . htmlspecialchars($e->getMessage()) . "</li>";
}

echo "</ul>";

// 6. Verificar permissões de diretórios
echo "<h2>6. Permissões de Diretórios</h2>";
echo "<ul>";

$directories = ['uploads', 'uploads/sliders'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<li>✅ Diretório {$dir} existe e é gravável</li>";
        } else {
            echo "<li>❌ Diretório {$dir} existe mas NÃO é gravável</li>";
        }
    } else {
        echo "<li>❌ Diretório {$dir} NÃO existe</li>";
    }
}

echo "</ul>";

// 7. Verificar arquivos críticos
echo "<h2>7. Arquivos Críticos</h2>";
echo "<ul>";

$critical_files = [
    'index.php',
    'database/db_connect_env.php',
    '.htaccess',
    'apache-config.conf'
];

foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "<li>✅ {$file} existe</li>";
    } else {
        echo "<li>❌ {$file} NÃO existe</li>";
    }
}

echo "</ul>";

// Resumo final
echo "<hr>";
echo "<h2>📊 Resumo da Verificação</h2>";
echo "<p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p><strong>Versão PHP:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Sistema Operacional:</strong> " . PHP_OS . "</p>";
echo "<p><strong>Servidor Web:</strong> " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Não identificado') . "</p>";

echo "<div style='background-color: #f0f8ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>";
echo "<h3>🚀 Próximos Passos:</h3>";
echo "<ol>";
echo "<li>Se houver itens marcados com ❌, corrija-os antes do deploy</li>";
echo "<li>Configure as variáveis de ambiente no Dokploy usando o arquivo dokploy.env</li>";
echo "<li>Faça o deploy usando o Dockerfile otimizado</li>";
echo "<li>Monitore os logs após o deploy para verificar se os erros foram resolvidos</li>";
echo "</ol>";
echo "</div>";

echo "<p><em>Script executado em: " . date('d/m/Y H:i:s') . "</em></p>";
?>