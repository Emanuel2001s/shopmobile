<?php
// Proteção contra acesso direto
if (!defined('ADMIN_ACCESS') && basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Acesso negado');
}

require_once '../database/db_connect_env.php';

function debug_log($message) {
    $log_file = '../debug_status_message.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function send_status_message($pedido_id, $novo_status) {
    global $conn;
    
    try {
        // Buscar configurações
        $config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
        $config = $config_query->fetch(PDO::FETCH_ASSOC);
        
        // Verificar se mensagens de status estão ativas
        $mensagem_status_ativa = $config['mensagem_status_ativa'] ?? 0;
        if (!$mensagem_status_ativa) {
            debug_log("Mensagens de status desativadas");
            return false;
        }
        
        // Verificar se Evolution API está configurada
        $instance_name = $config['evolution_instance_name'] ?? '';
        $api_url = $config['evolution_api_url'] ?? '';
        $api_key = $config['evolution_api_token'] ?? '';
        
        if (!$instance_name || !$api_url || !$api_key) {
            debug_log("Evolution API não configurada");
            return false;
        }
        
        // Buscar dados do pedido
        $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            debug_log("Pedido não encontrado: $pedido_id");
            return false;
        }
        
        // Buscar itens do pedido
        $stmt = $conn->prepare("SELECT pi.*, p.nome as nome_produto FROM pedido_itens pi 
                               LEFT JOIN produtos p ON pi.produto_id = p.id 
                               WHERE pi.pedido_id = ?");
        $stmt->execute([$pedido_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Montar resumo dos produtos
        $produtos_summary = "";
        if (!empty($itens)) {
            foreach ($itens as $item) {
                $nome = $item['nome_produto'] ?? 'Produto não encontrado';
                $qtd = $item['quantidade'];
                $preco = $item['preco_unitario'];
                $total = $preco * $qtd;
                $produtos_summary .= "• *$nome* (x$qtd): R$ " . number_format($total, 2, ',', '.') . "\n";
            }
        } else {
            // Fallback para pedidos antigos que usam produto_id
            $produto_ids = array_filter(array_map('trim', explode(',', $pedido['produto_id'])));
            if (!empty($produto_ids)) {
                $placeholders = implode(',', array_fill(0, count($produto_ids), '?'));
                $stmt = $conn->prepare("SELECT id, nome, preco FROM produtos WHERE id IN ($placeholders)");
                $stmt->execute($produto_ids);
                $produtos_antigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($produtos_antigos as $prod) {
                    $produtos_summary .= "• *" . $prod['nome'] . "* (x1): R$ " . number_format($prod['preco'], 2, ',', '.') . "\n";
                }
            } else {
                $produtos_summary = "• Produtos não encontrados\n";
            }
        }
        
        // Determinar qual mensagem usar baseado no status
        $mensagem_template = '';
        if ($novo_status === 'confirmado') {
            $mensagem_template = $config['mensagem_status_confirmado'] ?? '';
        } elseif ($novo_status === 'cancelado') {
            $mensagem_template = $config['mensagem_status_cancelado'] ?? '';
        }
        
        if (empty($mensagem_template)) {
            debug_log("Mensagem template vazia para status: $novo_status");
            return false;
        }
        
        // Preparar dados para substituição
        $dados_substituicao = [
            '{nome_cliente}' => $pedido['nome_completo'],
            '{whatsapp_cliente}' => $pedido['whatsapp'],
            '{produtos}' => $produtos_summary,
            '{valor_total}' => number_format($pedido['valor_total'], 2, ',', '.'),
            '{taxa_entrega}' => $pedido['taxa_entrega'] > 0 ? 'R$ ' . number_format($pedido['taxa_entrega'], 2, ',', '.') : 'Grátis',
            '{id_pedido}' => '#' . $pedido_id,
            '{data_pedido}' => date('d/m/Y H:i', strtotime($pedido['data_pedido'])),
            '{nome_loja}' => $config['nome_loja'] ?? 'Loja Virtual'
        ];
        
        // Adicionar endereço se for entrega
        if ($pedido['entregar_endereco']) {
            $dados_substituicao['{endereco_entrega}'] = $pedido['rua'] . ', ' . $pedido['numero'] . ', ' . $pedido['bairro'] . ', ' . $pedido['cidade'] . ' - ' . $pedido['cep'];
        } else {
            $dados_substituicao['{endereco_entrega}'] = 'Retirada no local';
        }
        
        // Substituir variáveis na mensagem
        $mensagem = $mensagem_template;
        foreach ($dados_substituicao as $variavel => $valor) {
            $mensagem = str_replace($variavel, $valor, $mensagem);
        }
        
        // Formatar número do cliente
        $numero_cliente = preg_replace('/\D/', '', $pedido['whatsapp']);
        if (strlen($numero_cliente) == 11) {
            $numero_cliente = '55' . $numero_cliente;
        }
        
        // Enviar via Evolution API
        $url = rtrim($api_url, '/') . '/message/sendText/' . $instance_name;
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $api_key
        ];
        $payload = [
            'number' => $numero_cliente,
            'text' => $mensagem
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        debug_log("Status message sent - Pedido: $pedido_id, Status: $novo_status, HTTP: $http_code, Response: $response, Error: $error");
        debug_log("Produtos encontrados: " . count($itens) . " itens, Summary: " . $produtos_summary);
        
        // Verificar se a mensagem foi enviada com sucesso
        // Considera sucesso se não há erro de cURL e a requisição foi feita
        $success = empty($error) && !empty($response);
        
        debug_log("Status message sent - Pedido: $pedido_id, Status: $novo_status, HTTP: $http_code, Response: $response, Error: $error, Success: " . ($success ? 'YES' : 'NO'));
        
        return $success;
        
    } catch (Exception $e) {
        debug_log("Erro ao enviar mensagem de status: " . $e->getMessage());
        return false;
    }
}

// Este arquivo contém apenas a função send_status_message
// Não deve ser acessado diretamente via URL
?> 