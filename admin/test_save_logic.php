<?php
require_once '../database/db_connect.php';

echo "<h2>Teste da Lógica de Salvamento (Versão Simplificada)</h2>";

// Verificar dados atuais no banco
echo "<h3>1. Dados atuais no banco:</h3>";
$config_query = $conn->query("SELECT evolution_instance_id, evolution_instance_name FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($config);
echo "</pre>";

// Testar a ação status diretamente
echo "<h3>2. Testando ação 'status':</h3>";
echo "<p><a href='evolution_qrcode.php?action=status' target='_blank'>Clique aqui para testar ação status</a></p>";

// Testar a ação check_instance diretamente
if (!empty($config['evolution_instance_name'])) {
    echo "<h3>3. Testando ação 'check_instance':</h3>";
    echo "<p><a href='evolution_qrcode.php?action=check_instance&instance_name=" . $config['evolution_instance_name'] . "' target='_blank'>Clique aqui para testar check_instance</a></p>";
}

// Verificar logs
echo "<h3>4. Logs de debug:</h3>";
if (file_exists('debug_evolution_detailed.log')) {
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars(file_get_contents('debug_evolution_detailed.log'));
    echo "</pre>";
} else {
    echo "<p>Arquivo debug_evolution_detailed.log não encontrado</p>";
}

// Botão para atualizar
echo "<h3>5. Atualizar dados:</h3>";
echo "<p><a href='test_save_logic.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Atualizar Página</a></p>";

// Instruções
echo "<h3>6. Nova Lógica Simplificada:</h3>";
echo "<ol>";
echo "<li><strong>Clique 'Conectar WhatsApp':</strong> Limpa banco + Cria instância + Salva imediatamente</li>";
echo "<li><strong>Escanear QR:</strong> Conecta ao WhatsApp</li>";
echo "<li><strong>Verificar Status:</strong> Apenas confirma se está conectado</li>";
echo "<li><strong>Dados já salvos:</strong> Não precisa verificar salvamento</li>";
echo "</ol>";

echo "<h3>7. Como testar:</h3>";
echo "<ol>";
echo "<li>Vá para <strong>configuracoes.php</strong> (aba WhatsApp)</li>";
echo "<li>Clique em <strong>'Conectar WhatsApp'</strong></li>";
echo "<li>Verifique se os dados foram salvos no banco</li>";
echo "<li>Escanee o QR Code para conectar</li>";
echo "</ol>";
?>
