<?php
// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php_error_reporting.php';
require_once 'database/db_connect.php';
require_once 'include/cart_functions.php';

// Buscar configurações da loja
$config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

// Valores padrão caso não existam configurações
$whatsapp_loja = $config ? $config['whatsapp'] : '5511999999999';

initialize_cart();
$cart_item_count = get_cart_item_count();

// Lógica para remover item do carrinho
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
    remove_from_cart($_GET['id']);
    header('Location: cart.php');
    exit();
}

// Lógica para atualizar quantidade
if (isset($_POST['action']) && $_POST['action'] === 'update_quantity' && isset($_POST['product_id']) && isset($_POST['quantity'])) {
    update_cart_quantity($_POST['product_id'], $_POST['quantity']);
    header('Location: cart.php');
    exit();
}

$cart_items = get_cart_items();
$cart_total = get_cart_total();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho - <?php echo isset($config) ? htmlspecialchars($config['nome_loja']) : 'Loja Virtual'; ?></title>
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos específicos da página do carrinho */
        .cart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .cart-header {
            background: white;
            padding: 1rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1rem;
            transition: color 0.3s;
        }
        
        .back-button:hover {
            color: #764ba2;
        }
        
        .back-button .icon {
            margin-right: 0.5rem;
        }
        
        .cart-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            gap: 1rem;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            flex-shrink: 0;
        }
        
        .cart-item-image.no-image {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }
        
        .cart-item-details {
            flex: 1;
            min-width: 0;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1rem;
            line-height: 1.3;
        }
        
        .cart-item-price {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .quantity-input {
            width: 60px;
            padding: 0.3rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .btn-update {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-update:hover {
            background: #0056b3;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        .cart-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .cart-total {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .cart-total-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .checkout-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            position: sticky;
            bottom: 1rem;
        }
        
        .checkout-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
            transform: scale(1.2);
        }
        
        .address-fields {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .address-fields.show {
            display: block;
        }
        
        .btn-whatsapp {
            width: 100%;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
            color: white;
        }
        
        .btn-whatsapp:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .empty-cart {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .empty-cart .icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-cart h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .empty-cart p {
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        
        .btn-continue {
            background: #667eea;
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-continue:hover {
            background: #764ba2;
            color: white;
        }
        
        @media (max-width: 767px) {
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }
            
            .cart-item-image {
                width: 100%;
                height: 200px;
                align-self: center;
                max-width: 200px;
            }
            
            .cart-item-details {
                width: 100%;
            }
            
            .cart-item-controls {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Container principal -->
    <main class="main-container">
        <!-- Header da página -->
        <div class="cart-header">
            <a href="index.php" class="back-button">
                <span class="icon"><i class="fas fa-arrow-left"></i></span>
                Continuar comprando
            </a>
            <h1 class="cart-title">
                <i class="fas fa-shopping-cart"></i>
                Meu Carrinho (<?php echo $cart_item_count; ?> <?php echo $cart_item_count == 1 ? 'item' : 'itens'; ?>)
            </h1>
        </div>

        <?php if (empty($cart_items)): ?>
            <!-- Carrinho vazio -->
            <div class="cart-container">
                <div class="empty-cart">
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Seu carrinho está vazio</h3>
                    <p>Adicione produtos ao seu carrinho para finalizar a compra.</p>
                    <a href="index.php" class="btn-continue">
                        <i class="fas fa-store"></i> Ir às compras
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Itens do carrinho -->
            <div class="cart-container">
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <?php if ($item['image']): ?>
                            <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="cart-item-image">
                        <?php else: ?>
                            <div class="cart-item-image no-image">
                                <i class="fas fa-camera"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cart-item-details">
                            <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="cart-item-price">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></div>
                            
                            <div class="cart-item-controls">
                                <form method="POST" class="quantity-control">
                                    <input type="hidden" name="action" value="update_quantity">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                    <label for="quantity_<?php echo $item['id']; ?>">Qtd:</label>
                                    <input type="number" 
                                           id="quantity_<?php echo $item['id']; ?>"
                                           name="quantity" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="1" 
                                           class="quantity-input">
                                    <button type="submit" class="btn-update">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                </form>
                                
                                <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" 
                                   class="btn-remove"
                                   onclick="return confirm('Tem certeza que deseja remover este item?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Resumo do carrinho -->
            <div class="cart-summary">
                <div class="cart-total">R$ <?php echo number_format($cart_total, 2, ',', '.'); ?></div>
                <div class="cart-total-label">Total do pedido</div>
            </div>

            <!-- Seção de checkout -->
            <div class="checkout-section">
                <h2 class="checkout-title"><i class="fas fa-check"></i> Finalizar Compra</h2>
                
                <form id="checkoutForm" onsubmit="handleCheckout(event)">
                    <div class="form-group">
                        <label for="nome_completo">Nome completo:</label>
                        <input type="text" id="nome_completo" name="nome_completo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="whatsapp">WhatsApp:</label>
                        <input type="tel" id="whatsapp" name="whatsapp" placeholder="(11) 99999-9999" required>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="entregar_endereco" name="entregar_endereco" onchange="toggleAddressFields()">
                        <label for="entregar_endereco">Entregar no meu endereço</label>
                    </div>
                    
                    <div class="address-fields" id="addressFields">
                        <div class="form-group">
                            <label for="rua">Rua:</label>
                            <input type="text" id="rua" name="rua">
                        </div>
                        
                        <div class="form-group">
                            <label for="numero">Número:</label>
                            <input type="text" id="numero" name="numero">
                        </div>
                        
                        <div class="form-group">
                            <label for="bairro">Bairro:</label>
                            <input type="text" id="bairro" name="bairro">
                        </div>
                        
                        <div class="form-group">
                            <label for="cidade">Cidade:</label>
                            <input type="text" id="cidade" name="cidade">
                        </div>
                        
                        <div class="form-group">
                            <label for="cep">CEP:</label>
                            <input type="text" id="cep" name="cep" placeholder="00000-000">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        Finalizar Pedido via WhatsApp
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Função para mostrar/ocultar campos de endereço
        function toggleAddressFields() {
            const checkbox = document.getElementById("entregar_endereco");
            const addressFields = document.getElementById("addressFields");
            
            if (checkbox.checked) {
                addressFields.classList.add("show");
                addressFields.querySelectorAll("input").forEach(input => {
                    input.required = true;
                });
            } else {
                addressFields.classList.remove("show");
                addressFields.querySelectorAll("input").forEach(input => {
                    input.required = false;
                    input.value = "";
                });
            }
        }

        // Função para processar o checkout
        async function handleCheckout(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            if (!data.nome_completo || !data.whatsapp) {
                alert("Por favor, preencha todos os campos obrigatórios.");
                return;
            }

            // Criar mensagem do WhatsApp com todos os itens do carrinho
            let orderSummary = "";
            <?php foreach ($cart_items as $item): ?>
                orderSummary += "- <?php echo htmlspecialchars($item["name"]); ?> (x<?php echo $item["quantity"]; ?>): R$ <?php echo number_format($item["price"] * $item["quantity"], 2, ",", "."); ?>\n";
            <?php endforeach; ?>

            let message = `🛒 *Novo Pedido - Resumo do Carrinho*\n\n` +
                          `*Itens do Pedido:*\n${orderSummary}\n` +
                          `*Total do Pedido: R$ <?php echo number_format($cart_total, 2, ",", "."); ?>*\n\n` +
                          `*Dados do Cliente:*\n` +
                          `Nome: ${data.nome_completo}\n` +
                          `WhatsApp: ${data.whatsapp}\n`;
            
            if (data.entregar_endereco) {
                message += `\n*Endereço de Entrega:*\n` +
                           `Rua: ${data.rua}, ${data.numero}\n` +
                           `Bairro: ${data.bairro}\n` +
                           `Cidade: ${data.cidade}\n` +
                           `CEP: ${data.cep}\n`;
            } else {
                message += `\n*Retirada no local*\n`;
            }
            
            const whatsappUrl = `https://api.whatsapp.com/send?phone=<?php echo $whatsapp_loja; ?>&text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, "_blank");
            
            // Opcional: Limpar carrinho após finalizar pedido
            if (confirm("Pedido enviado! Deseja limpar o carrinho?")) {
                // Aqui você pode fazer uma requisição para limpar o carrinho
                window.location.href = "cart.php?action=clear";
            }
        }

        // Formatação automática do WhatsApp
        document.getElementById('whatsapp').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 7) {
                value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
            }
            e.target.value = value;
        });

        // Formatação automática do CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{5})(\d{0,3})/, '$1-$2');
            }
            e.target.value = value;
        });
    </script>
</body>
</html>

