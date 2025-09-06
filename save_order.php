<?php
require_once 'database/db_connect_env.php';

// Função para log de debug
function debug_log($message) {
    $log_file = __DIR__ . '/debug_cart.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

debug_log("=== INÍCIO DA REQUISIÇÃO ===");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("Erro: Método não é POST. Método recebido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Ler dados JSON
$input = file_get_contents('php://input');
debug_log("Dados recebidos (raw): " . $input);

$data = json_decode($input, true);
debug_log("Dados decodificados: " . print_r($data, true));

if (!$data) {
    debug_log("Erro: Dados JSON inválidos");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

// Validar campos obrigatórios
$required_fields = ['nome_completo', 'whatsapp', 'cart_items'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        debug_log("Erro: Campo obrigatório ausente: $field");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Campo obrigatório: $field"]);
        exit();
    }
}

debug_log("Validação de campos obrigatórios passou");

try {
    // Preparar dados para inserção
    $nome_completo = trim($data['nome_completo']);
    $whatsapp = trim($data['whatsapp']);
    $cart_items = $data['cart_items']; // Array com dados completos dos produtos
    $entregar_endereco = isset($data['entregar_endereco']) && $data['entregar_endereco'] ? 1 : 0;
    
    debug_log("Dados preparados - Nome: $nome_completo, WhatsApp: $whatsapp");
    debug_log("Itens do carrinho: " . print_r($cart_items, true));
    
    // Campos de endereço (opcionais)
    $rua = isset($data['rua']) ? trim($data['rua']) : '';
    $numero = isset($data['numero']) ? trim($data['numero']) : '';
    $bairro = isset($data['bairro']) ? trim($data['bairro']) : '';
    $cidade = isset($data['cidade']) ? trim($data['cidade']) : '';
    $cep = isset($data['cep']) ? trim($data['cep']) : '';
    
    // Validar se os produtos existem e calcular o valor total
    $produto_ids_array = [];
    $valor_total = 0;
    
    foreach ($cart_items as $item) {
        if (!isset($item['id']) || !isset($item['price']) || !isset($item['quantity'])) {
            debug_log("Erro: Item do carrinho inválido: " . print_r($item, true));
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados do carrinho inválidos']);
            exit();
        }
        
        $produto_ids_array[] = $item['id'];
        $valor_total += $item['price'] * $item['quantity'];
    }
    
    debug_log("Array de produto IDs: " . print_r($produto_ids_array, true));
    debug_log("Valor total calculado: $valor_total");
    
    if (empty($produto_ids_array)) {
        debug_log("Erro: Nenhum produto válido encontrado");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhum produto válido encontrado']);
        exit();
    }
    
    // Criar placeholders para a consulta IN
    $placeholders = str_repeat('?,', count($produto_ids_array) - 1) . '?';
    debug_log("Placeholders para consulta: $placeholders");
    
    $stmt = $conn->prepare("SELECT id, preco FROM produtos WHERE id IN ($placeholders)");
    debug_log("Query preparada: SELECT id, preco FROM produtos WHERE id IN ($placeholders)");
    
    $stmt->execute($produto_ids_array);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    debug_log("Produtos encontrados: " . print_r($produtos, true));
    
    if (count($produtos) !== count($produto_ids_array)) {
        debug_log("Erro: Nem todos os produtos foram encontrados. Esperados: " . count($produto_ids_array) . ", Encontrados: " . count($produtos));
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Um ou mais produtos não foram encontrados']);
        exit();
    }
    
    debug_log("Valor total calculado: $valor_total");
    
    // Verificar se o cliente já existe pelo WhatsApp
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE whatsapp = ?");
    $stmt->execute([$whatsapp]);
    $cliente_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    debug_log("Cliente existente: " . print_r($cliente_existente, true));
    
    if ($cliente_existente) {
        $cliente_id = $cliente_existente['id'];
        
        // Atualizar dados do cliente (nome pode ter mudado)
        $stmt = $conn->prepare("UPDATE clientes SET nome_completo = ?, total_pedidos = total_pedidos + 1, valor_total = valor_total + ? WHERE id = ?");
        $stmt->execute([$nome_completo, $valor_total, $cliente_id]);
        debug_log("Cliente atualizado. ID: $cliente_id");
    } else {
        // Criar novo cliente
        $stmt = $conn->prepare("INSERT INTO clientes (nome_completo, whatsapp, total_pedidos, valor_total) VALUES (?, ?, 1, ?)");
        $stmt->execute([$nome_completo, $whatsapp, $valor_total]);
        $cliente_id = $conn->lastInsertId();
        debug_log("Novo cliente criado. ID: $cliente_id");
    }
    
    // Buscar taxa de entrega do banco
    $config_query_taxa = $conn->query("SELECT taxa_entrega FROM configuracoes LIMIT 1");
    $config_taxa = $config_query_taxa->fetch(PDO::FETCH_ASSOC);
    $taxa_entrega = isset($config_taxa['taxa_entrega']) ? floatval($config_taxa['taxa_entrega']) : 0;

    // Se for entrega, soma a taxa ao valor total
    $taxa_aplicada = 0;
    if ($entregar_endereco && $taxa_entrega > 0) {
        $valor_total += $taxa_entrega;
        $taxa_aplicada = $taxa_entrega;
    }
    debug_log("Taxa de entrega aplicada: $taxa_aplicada | Valor total com taxa: $valor_total");
    
    // Inserir pedido com múltiplos produtos (IDs separados por vírgula)
    $produto_ids_string = implode(',', $produto_ids_array);
    $stmt = $conn->prepare("INSERT INTO pedidos (nome_completo, whatsapp, entregar_endereco, rua, numero, bairro, cidade, cep, produto_id, valor_total, cliente_id, taxa_entrega, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
    
    debug_log("Inserindo pedido com dados: nome=$nome_completo, whatsapp=$whatsapp, endereco=$entregar_endereco, produto_id=$produto_ids_string, valor_total=$valor_total, cliente_id=$cliente_id, taxa_entrega=$taxa_aplicada");
    
    $stmt->execute([$nome_completo, $whatsapp, $entregar_endereco, $rua, $numero, $bairro, $cidade, $cep, $produto_ids_string, $valor_total, $cliente_id, $taxa_aplicada]);
    
    $pedido_id = $conn->lastInsertId();
    
    debug_log("Pedido inserido com sucesso. ID: $pedido_id");

    // === INTEGRAR EVOLUTION API ===
    // Buscar configurações Evolution
    $config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
    $config_evo = $config_query->fetch(PDO::FETCH_ASSOC);
    $instance_name = $config_evo['evolution_instance_name'] ?? '';
    $api_url = $config_evo['evolution_api_url'] ?? '';
    $api_key = $config_evo['evolution_api_token'] ?? '';

    // Formatar número do cliente
    $numero_cliente = preg_replace('/\D/', '', $whatsapp);
    if (strlen($numero_cliente) == 11) {
        $numero_cliente = '55' . $numero_cliente;
    }

    // Montar resumo dos produtos com dados completos do carrinho
    $orderSummary = "";
    foreach ($cart_items as $item) {
        $nome = $item['name'];
        $qtd = $item['quantity'];
        $preco_unitario = $item['price'];
        $preco_total = $preco_unitario * $qtd;
        $orderSummary .= "• *$nome* (x$qtd): R$ " . number_format($preco_total, 2, ',', '.') . "\n";
    }

    // Verificar se há mensagem personalizada ativa
    $mensagem_personalizada = $config_evo['mensagem_pedido_personalizada'] ?? '';
    $mensagem_ativa = $config_evo['mensagem_pedido_ativa'] ?? 0;
    
    if ($mensagem_ativa && !empty($mensagem_personalizada)) {
        // Usar mensagem personalizada
        $mensagem = $mensagem_personalizada;
        
        // Preparar dados para substituição
        $dados_substituicao = [
            '{nome_cliente}' => $nome_completo,
            '{whatsapp_cliente}' => $whatsapp,
            '{produtos}' => $orderSummary,
            '{valor_total}' => 'R$ ' . number_format($valor_total, 2, ',', '.'),
            '{taxa_entrega}' => $taxa_aplicada > 0 ? 'R$ ' . number_format($taxa_aplicada, 2, ',', '.') : 'Grátis',
            '{id_pedido}' => '#' . $pedido_id,
            '{data_pedido}' => date('d/m/Y H:i'),
            '{nome_loja}' => $config_evo['nome_loja'] ?? 'Loja Virtual'
        ];
        
        // Adicionar endereço se for entrega
        if ($entregar_endereco) {
            $dados_substituicao['{endereco_entrega}'] = "$rua, $numero, $bairro, $cidade - $cep";
        } else {
            $dados_substituicao['{endereco_entrega}'] = 'Retirada no local';
        }
        
        // Substituir variáveis na mensagem personalizada
        foreach ($dados_substituicao as $variavel => $valor) {
            $mensagem = str_replace($variavel, $valor, $mensagem);
        }
    } else {
        // Usar mensagem padrão
        $mensagem =
            "🛒 *Novo Pedido Recebido!*\n\n" .
            "📋 *Resumo do Pedido:*\n" .
            $orderSummary .
            "━━━━━━━━━━━━━━━━━━━━\n";
        if ($entregar_endereco && $taxa_aplicada > 0) {
            $mensagem .= "🚚 *Taxa de Entrega:* R$ " . number_format($taxa_aplicada, 2, ',', '.') . "\n";
        }
        $mensagem .=
            "💰 *Total:* R$ " . number_format($valor_total, 2, ',', '.') . "\n\n" .
            "👤 *Dados do Cliente:*\n" .
            "• *Nome:* $nome_completo\n" .
            "• *WhatsApp:* $whatsapp\n\n";
        if ($entregar_endereco) {
            $mensagem .=
                "📍 *Endereço de Entrega:*\n" .
                "• *Rua:* $rua, $numero\n" .
                "• *Bairro:* $bairro\n" .
                "• *Cidade:* $cidade\n" .
                "• *CEP:* $cep\n\n";
        } else {
            $mensagem .= "🏪 *Retirada no local*\n\n";
        }
        $mensagem .=
            "📝 *ID do Pedido:* #$pedido_id\n" .
            "⏰ *Data:* " . date('d/m/Y H:i') . "\n\n" .
            "✅ Pedido registrado com sucesso!";
    }

    // Função Evolution API
    function send_whatsapp_message($instance_name, $api_url, $api_key, $number, $message) {
        $url = rtrim($api_url, '/') . '/message/sendText/' . $instance_name;
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $api_key
        ];
        $payload = [
            'number' => $number,
            'textMessage' => [
                'text' => $message
            ]
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        return $error ? $error : $response;
    }

    // Disparar mensagem Evolution
    $evo_result = '';
    if ($instance_name && $api_url && $api_key && $numero_cliente) {
        $url = rtrim($api_url, '/') . '/message/sendText/' . $instance_name;
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $api_key
        ];
        $payload = [
            'number' => $numero_cliente,
            'text' => $mensagem,
            'textMessage' => [
                'text' => $mensagem
            ]
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
        debug_log('Evolution API envio: HTTP_CODE=' . $http_code . ' | ERROR=' . $error . ' | RESPONSE=' . $response . ' | URL=' . $url . ' | PAYLOAD=' . json_encode($payload));
    } else {
        debug_log('Evolution API não disparada: dados incompletos. instance_name=' . $instance_name . ' api_url=' . $api_url . ' api_key=' . $api_key . ' numero=' . $numero_cliente);
    }

    $response = [
        'success' => true, 
        'message' => 'Pedido salvo com sucesso',
        'pedido_id' => $pedido_id,
        'cliente_id' => $cliente_id,
        'valor_total' => $valor_total,
        'produtos_count' => count($produto_ids_array)
    ];
    
    debug_log("Resposta de sucesso: " . print_r($response, true));
    echo json_encode($response);
    
} catch (PDOException $e) {
    debug_log('Erro PDO: ' . $e->getMessage());
    debug_log('Stack trace: ' . $e->getTraceAsString());
    
    error_log('Erro PDO ao salvar pedido do carrinho: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    debug_log('Erro geral: ' . $e->getMessage());
    debug_log('Stack trace: ' . $e->getTraceAsString());
    
    error_log('Erro geral ao salvar pedido do carrinho: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor'
    ]);
}

debug_log("=== FIM DA REQUISIÇÃO ===");
?>

