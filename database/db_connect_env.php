<?php

// Configuração do banco de dados usando variáveis de ambiente
// Para uso em produção com Dokploy
$servername = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
$username = getenv('DB_USERNAME') ?: $_ENV['DB_USERNAME'] ?? 'root';
$password = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$dbname = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'shopmobile';

// Fallback para desenvolvimento local (se as variáveis de ambiente não estiverem definidas)
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file_get_contents(__DIR__ . '/../.env');
    $lines = explode("\n", $envFile);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\' ');
            if (!getenv($key) && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    // Recarrega as variáveis após ler o .env
    $servername = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? $servername;
    $username = getenv('DB_USERNAME') ?: $_ENV['DB_USERNAME'] ?? $username;
    $password = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? $password;
    $dbname = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? $dbname;
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Define o modo de erro do PDO para exceção
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    //echo "Conexão bem-sucedida";
} catch(PDOException $e) {
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    die("Erro de conexão com o banco de dados. Verifique as configurações.");
}

// Sistema de Migração Automática Simples
// Adiciona novos campos automaticamente se não existirem
if (!defined('SKIP_MIGRATIONS')) {
    try {
        // Verifica se a tabela configuracoes existe, se não, cria
        $stmt = $conn->prepare("SHOW TABLES LIKE 'configuracoes'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Cria a tabela configuracoes
            $createTable = "
                CREATE TABLE configuracoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome_loja VARCHAR(255) DEFAULT 'Minha Loja',
                    whatsapp VARCHAR(20) DEFAULT '',
                    titulo_footer VARCHAR(255) DEFAULT 'Minha Loja - Todos os direitos reservados',
                    taxa_entrega DECIMAL(10,2) DEFAULT 0.00,
                    horario_atendimento VARCHAR(100) DEFAULT '08:00 às 18:00',
                    pix_enabled TINYINT(1) DEFAULT 1,
                    dinheiro_enabled TINYINT(1) DEFAULT 1,
                    cartao_enabled TINYINT(1) DEFAULT 1,
                    mercadopago_enabled TINYINT(1) DEFAULT 0,
                    garantia TEXT DEFAULT '3 meses',
                    politica_devolucao TEXT DEFAULT 'Política de devolução em até 7 dias',
                    instagram_url VARCHAR(255) DEFAULT '',
                    facebook_url VARCHAR(255) DEFAULT '',
                    youtube_url VARCHAR(255) DEFAULT '',
                    twitter_url VARCHAR(255) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            $conn->exec($createTable);
            
            // Insere configuração padrão
            $insertDefault = "
                INSERT INTO configuracoes (nome_loja, whatsapp, titulo_footer) 
                VALUES ('Minha Loja', '', 'Minha Loja - Todos os direitos reservados')
            ";
            $conn->exec($insertDefault);
        }
        
        // Lista de campos para verificar/adicionar
        $fieldsToCheck = [
            'garantia' => "ALTER TABLE configuracoes ADD COLUMN garantia TEXT DEFAULT '3 meses'",
            'politica_devolucao' => "ALTER TABLE configuracoes ADD COLUMN politica_devolucao TEXT DEFAULT 'Política de devolução em até 7 dias'",
            'instagram_url' => "ALTER TABLE configuracoes ADD COLUMN instagram_url VARCHAR(255) DEFAULT ''",
            'facebook_url' => "ALTER TABLE configuracoes ADD COLUMN facebook_url VARCHAR(255) DEFAULT ''",
            'youtube_url' => "ALTER TABLE configuracoes ADD COLUMN youtube_url VARCHAR(255) DEFAULT ''",
            'twitter_url' => "ALTER TABLE configuracoes ADD COLUMN twitter_url VARCHAR(255) DEFAULT ''"
        ];
        
        foreach ($fieldsToCheck as $field => $alterQuery) {
            $stmt = $conn->prepare("SHOW COLUMNS FROM configuracoes LIKE ?");
            $stmt->execute([$field]);
            if ($stmt->rowCount() == 0) {
                $conn->exec($alterQuery);
            }
        }
        
        // Verifica se existe pelo menos um registro na tabela configuracoes
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM configuracoes");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result['count'] == 0) {
            $insertDefault = "
                INSERT INTO configuracoes (nome_loja, whatsapp, titulo_footer) 
                VALUES ('Minha Loja', '', 'Minha Loja - Todos os direitos reservados')
            ";
            $conn->exec($insertDefault);
        }
        
    } catch(PDOException $e) {
        error_log("Erro na migração da tabela configuracoes: " . $e->getMessage());
    }
    
    // Migração para Mercado Pago
    try {
        // Adiciona campos do Mercado Pago na tabela configuracoes se não existirem
        $mercadopagoFields = [
            'mercadopago_public_key' => "ALTER TABLE configuracoes ADD COLUMN mercadopago_public_key VARCHAR(255) DEFAULT ''",
            'mercadopago_access_token' => "ALTER TABLE configuracoes ADD COLUMN mercadopago_access_token VARCHAR(255) DEFAULT ''"
        ];
        
        foreach ($mercadopagoFields as $field => $alterQuery) {
            $stmt = $conn->prepare("SHOW COLUMNS FROM configuracoes LIKE ?");
            $stmt->execute([$field]);
            if ($stmt->rowCount() == 0) {
                $conn->exec($alterQuery);
            }
        }
        
        // Verifica se a tabela mercadopago existe, se não, cria
        $stmt = $conn->prepare("SHOW TABLES LIKE 'mercadopago'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $createMercadopagoTable = "
                CREATE TABLE mercadopago (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payment_id VARCHAR(255) UNIQUE,
                    status VARCHAR(50),
                    status_detail VARCHAR(100),
                    payment_method_id VARCHAR(50),
                    payment_type_id VARCHAR(50),
                    amount DECIMAL(10,2),
                    currency_id VARCHAR(10),
                    description TEXT,
                    payer_email VARCHAR(255),
                    payer_name VARCHAR(255),
                    external_reference VARCHAR(255),
                    qr_code TEXT,
                    qr_code_base64 LONGTEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_payment_id (payment_id),
                    INDEX idx_external_reference (external_reference),
                    INDEX idx_status (status)
                )
            ";
            $conn->exec($createMercadopagoTable);
        }
        
    } catch(PDOException $e) {
        error_log("Erro na migração do Mercado Pago: " . $e->getMessage());
    }
}

?>