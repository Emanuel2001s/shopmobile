<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

// Função para buscar logs da cron job
function getCronLogs() {
    $log_file = '/var/log/apache2/error.log'; // Caminho padrão do Apache
    $logs = [];
    
    if (file_exists($log_file)) {
        $lines = file($log_file);
        $lines = array_reverse($lines); // Últimas linhas primeiro
        
        foreach ($lines as $line) {
            if (strpos($line, 'CRON:') !== false) {
                $logs[] = trim($line);
            }
        }
    }
    
    return array_slice($logs, 0, 50); // Últimos 50 logs
}

$cron_logs = getCronLogs();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs da Cron Job - Painel Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        
        .header h1 {
            margin-bottom: 5px;
        }
        
        .header p {
            color: #bdc3c7;
        }
        
        .content {
            padding: 20px;
        }
        
        .log-entry {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 0 5px 5px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-wrap: break-word;
        }
        
        .log-entry.error {
            border-left-color: #e74c3c;
            background: #fdf2f2;
        }
        
        .log-entry.success {
            border-left-color: #27ae60;
            background: #f0f9f0;
        }
        
        .log-entry.warning {
            border-left-color: #f39c12;
            background: #fef9e7;
        }
        
        .no-logs {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .refresh-btn:hover {
            background: #2980b9;
        }
        
        .back-btn {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-bottom: 20px;
            margin-right: 10px;
        }
        
        .back-btn:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Logs da Cron Job</h1>
            <p>Visualize os logs da verificação automática de pagamentos</p>
        </div>
        
        <div class="content">
            <button class="back-btn" onclick="window.location.href='payment_monitor.php'">
                ← Voltar ao Monitor
            </button>
            
            <button class="refresh-btn" onclick="location.reload()">
                🔄 Atualizar Logs
            </button>
            
            <?php if (empty($cron_logs)): ?>
                <div class="no-logs">
                    <h3>📭 Nenhum log encontrado</h3>
                    <p>Não foram encontrados logs da cron job.</p>
                    <p>Possíveis causas:</p>
                    <ul style="text-align: left; max-width: 400px; margin: 20px auto;">
                        <li>A cron job ainda não foi executada</li>
                        <li>O arquivo de log está em outro local</li>
                        <li>Não há permissão para ler o arquivo de log</li>
                    </ul>
                    <p><strong>Teste manual:</strong> Execute a verificação manual no Monitor de Pagamentos</p>
                </div>
            <?php else: ?>
                <h3>📋 Últimos 50 Logs da Cron Job</h3>
                
                <?php foreach ($cron_logs as $log): ?>
                    <?php
                    $class = 'log-entry';
                    if (strpos($log, 'ERRO') !== false || strpos($log, 'Erro') !== false) {
                        $class .= ' error';
                    } elseif (strpos($log, 'confirmado') !== false || strpos($log, 'sucesso') !== false) {
                        $class .= ' success';
                    } elseif (strpos($log, 'pendente') !== false || strpos($log, 'verificar') !== false) {
                        $class .= ' warning';
                    }
                    ?>
                    <div class="<?php echo $class; ?>">
                        <?php echo htmlspecialchars($log); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh a cada 30 segundos
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 