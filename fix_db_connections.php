<?php
/**
 * Script para corrigir todas as referências de db_connect.php para db_connect_env.php
 * Execute este script uma vez para atualizar todos os arquivos
 */

echo "Iniciando correção das conexões de banco...\n";

// Lista de arquivos para atualizar (principais)
$files_to_update = [
    // Arquivos raiz
    'mercadopago_webhook.php',
    'get_favorites.php', 
    'checkout.php',
    'save_order.php',
    'cart.php',
    'search_products.php',
    'como_funciona.php',
    'update_sitemap.php',
    'cron_check_payments.php',
    'cart_new.php',
    'product_original.php',
    'mercadopago_process_card.php',
    'mercadopago_create_token.php',
    'sitemap.php',
    'mercadopago_check_status.php',
    'cart_original.php',
    'mercadopago_get_issuers.php',
    'favoritos.php',
    'product_new.php',
    'payment_page.php',
    'product.php',
    'mercadopago_get_installments.php',
    
    // Arquivos includes
    'includes/header.php',
    
    // Arquivos admin principais
    'admin/evolution_qrcode.php',
    'admin/edit_product.php',
    'admin/usuarios.php',
    'admin/categories.php',
    'admin/edit_cliente.php',
    'admin/mercadopago_create_payment.php',
    'admin/configuracoes.php',
    'admin/view_cron_logs.php',
    'admin/orders.php',
    'admin/stock_alert.php',
    'admin/products.php',
    'admin/search_orders_admin.php',
    'admin/payment_monitor.php',
    'admin/get_slider.php',
    'admin/search_products_admin.php',
    'admin/search_images.php',
    'admin/orders_with_delete.php',
    'admin/login.php',
    'admin/sliders.php',
    'admin/test_save_logic.php',
    'admin/send_status_message.php',
    'admin/get_client_orders.php',
    'admin/delete_cliente.php',
    'admin/debug_whatsapp_simple.php',
    'admin/index.php',
    'admin/test_evolution_logic.php',
    'admin/clientes.php',
    'admin/test_mp_config.php'
];

$updated_count = 0;
$error_count = 0;

foreach ($files_to_update as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original_content = $content;
        
        // Substituir as referências
        $content = str_replace(
            "require_once 'database/db_connect.php';",
            "require_once 'database/db_connect_env.php';",
            $content
        );
        
        $content = str_replace(
            "require_once '../database/db_connect.php';",
            "require_once '../database/db_connect_env.php';",
            $content
        );
        
        // Para o arquivo debug_whatsapp_simple.php que tem uma verificação especial
        $content = str_replace(
            "if (file_exists('../database/db_connect.php')) {",
            "if (file_exists('../database/db_connect_env.php')) {",
            $content
        );
        
        if ($content !== $original_content) {
            if (file_put_contents($file, $content)) {
                echo "✅ Atualizado: $file\n";
                $updated_count++;
            } else {
                echo "❌ Erro ao atualizar: $file\n";
                $error_count++;
            }
        } else {
            echo "⚪ Sem alterações: $file\n";
        }
    } else {
        echo "⚠️ Arquivo não encontrado: $file\n";
    }
}

echo "\n=== RESUMO ===\n";
echo "Arquivos atualizados: $updated_count\n";
echo "Erros: $error_count\n";
echo "\nTodos os arquivos principais foram atualizados para usar db_connect_env.php\n";
echo "Agora você pode fazer o commit e push das alterações.\n";
?>