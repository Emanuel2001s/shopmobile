<?php
require_once 'database/db_connect.php';

/**
 * Script de Cron Job para verificar pagamentos pendentes do Mercado Pago
 * Pode ser executado via:
 * 1. URL: https://SEU_DOMINIO.com/cron_check_payments.php?token=SEU_TOKEN_SECRETO
 * 2. Cron Job: A cada 5 minutos usando curl
 */

// Verificar token de segurança
$token_secreto = 'shopmobile_cron_2024'; // Altere para um token mais seguro
$token_recebido = $_GET['token'] ?? '';

if ($token_recebido !== $token_secreto) {
    http_response_code(403);
    die('Acesso negado');
}

// Log de início
error_log("=== INÍCIO DA VERIFICAÇÃO AUTOMÁTICA DE PAGAMENTOS ===");

// Headers para resposta web
header('Content-Type: application/json; charset=utf-8');

try {
    // Buscar configurações do Mercado Pago
    $config_query = $conn->query("SELECT mercadopago_access_token FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['mercadopago_access_token'])) {
        error_log("CRON: Token de acesso do Mercado Pago não configurado");
        exit;
    }
    
    // Buscar todos os pagamentos aprovados que ainda não confirmaram o pedido
    $stmt = $conn->prepare("
        SELECT mp.*, p.nome_completo, p.valor_total, p.status as pedido_status
        FROM mercadopago mp
        INNER JOIN pedidos p ON mp.pedido_id = p.id
        WHERE mp.status = 'approved'
        AND mp.payment_id IS NOT NULL
        AND mp.payment_id != ''
        AND p.status != 'confirmado'
        ORDER BY mp.created_at ASC
    ");
    $stmt->execute();
    $pagamentos_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("CRON: Encontrados " . count($pagamentos_pendentes) . " pagamentos para verificar");
    
    // Log detalhado dos pagamentos encontrados
    foreach ($pagamentos_pendentes as $pag) {
        error_log("CRON: Pagamento ID {$pag['payment_id']} - Status: {$pag['status']} - Pedido: {$pag['pedido_id']} - Status Pedido: {$pag['pedido_status']}");
    }
    
    $atualizados = 0;
    $erros = 0;
    
    foreach ($pagamentos_pendentes as $pagamento) {
        try {
            // Como já filtramos apenas pagamentos aprovados, vamos direto para confirmar o pedido
            error_log("CRON: Processando pagamento aprovado {$pagamento['payment_id']} para pedido {$pagamento['pedido_id']}");
            
            // Atualizar status do pedido
            $stmt_pedido = $conn->prepare("UPDATE pedidos SET status_pagamento = 'pago', valor_pago = valor_total, status = 'confirmado' WHERE id = ?");
            $stmt_pedido->execute([$pagamento['pedido_id']]);
            
            error_log("CRON: Pedido {$pagamento['pedido_id']} confirmado com sucesso");
            
            // Enviar WhatsApp
            error_log("CRON: Tentando enviar WhatsApp para pedido {$pagamento['pedido_id']}");
            
            try {
                // Buscar configurações
                $config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
                $config = $config_query->fetch(PDO::FETCH_ASSOC);
                
                // Verificar se mensagens de status estão ativas
                if ($config['mensagem_status_ativa'] ?? 0) {
                    // Buscar dados do pedido
                    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
                    $stmt->execute([$pagamento['pedido_id']]);
                    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pedido && !empty($pedido['whatsapp'])) {
                        // Buscar itens do pedido
                        $stmt = $conn->prepare("SELECT pi.*, p.nome as nome_produto FROM pedido_itens pi 
                                               LEFT JOIN produtos p ON pi.produto_id = p.id 
                                               WHERE pi.pedido_id = ?");
                        $stmt->execute([$pagamento['pedido_id']]);
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
                            // Fallback para pedidos antigos
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
                        
                        // Buscar mensagem template
                        $mensagem_template = $config['mensagem_status_confirmado'] ?? '';
                        
                        if (!empty($mensagem_template)) {
                            // Preparar dados para substituição
                            $dados_substituicao = [
                                '{nome_cliente}' => $pedido['nome_completo'],
                                '{whatsapp_cliente}' => $pedido['whatsapp'],
                                '{produtos}' => $produtos_summary,
                                '{valor_total}' => number_format($pedido['valor_total'], 2, ',', '.'),
                                '{taxa_entrega}' => $pedido['taxa_entrega'] > 0 ? 'R$ ' . number_format($pedido['taxa_entrega'], 2, ',', '.') : 'Grátis',
                                '{id_pedido}' => '#' . $pagamento['pedido_id'],
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
                            
                            // Verificar se Evolution API está configurada
                            $instance_name = $config['evolution_instance_name'] ?? '';
                            $api_url = $config['evolution_api_url'] ?? '';
                            $api_key = $config['evolution_api_token'] ?? '';
                            
                            if ($instance_name && $api_url && $api_key) {
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
                                curl_close($ch);
                                
                                if ($http_code === 200 || $http_code === 201) {
                                    error_log("CRON: WhatsApp enviado com sucesso para pedido {$pagamento['pedido_id']}");
                                } else {
                                    error_log("CRON: Falha ao enviar WhatsApp - HTTP {$http_code} - Response: {$response}");
                                }
                            } else {
                                error_log("CRON: Evolution API não configurada");
                            }
                        } else {
                            error_log("CRON: Mensagem template vazia");
                        }
                    } else {
                        error_log("CRON: Pedido não encontrado ou sem WhatsApp");
                    }
                } else {
                    error_log("CRON: Mensagens de status desativadas");
                }
            } catch (Exception $e) {
                error_log("CRON: Erro ao enviar WhatsApp: " . $e->getMessage());
            }
            
                                     $atualizados++;
             
         } catch (Exception $e) {
             $erros++;
             error_log("CRON: Erro ao processar pagamento {$pagamento['payment_id']}: " . $e->getMessage());
         }
     }
    
    // Log do resultado
    $resultado = [
        'success' => true,
        'total_verificados' => count($pagamentos_pendentes),
        'atualizados' => $atualizados,
        'erros' => $erros,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => "Verificação concluída com sucesso"
    ];
    
    error_log("CRON: Resultado da verificação - " . json_encode($resultado));
    error_log("=== FIM DA VERIFICAÇÃO AUTOMÁTICA DE PAGAMENTOS ===");
    
    // Retornar resultado em JSON
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("CRON: Erro geral na verificação - " . $e->getMessage());
    
    $erro_resultado = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($erro_resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?> 