<?php
require_once 'php_error_reporting.php';
require_once 'database/db_connect.php';
require_once 'include/cart_functions.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

initialize_cart();
$cart_item_count = get_cart_item_count();

$cart_items = get_cart_items();
$cart_total = get_cart_total();

// Redireciona para o carrinho se estiver vazio
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - Loja Virtual</title>
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos específicos da página de checkout */
        .checkout-header {
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
        .checkout-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .checkout-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .cart-summary-checkout {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
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
        .form-group select,
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
        .cart-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .cart-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 1rem;
        }
        .cart-item-details {
            flex-grow: 1;
        }
        .cart-item-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        .cart-item-price {
            color: #28a745;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .cart-item-quantity-display {
            font-size: 0.9rem;
            color: #666;
        }
        .no-image-large {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #6c757d;
            font-size: 2rem;
            border-radius: 5px;
        }

        /* Estilos para a seção de checkout */
        .checkout-form-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            display: none; /* Oculta por padrão */
        }

        .checkout-form-section.show {
            display: block; /* Mostra quando a classe 'show' é adicionada */
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Loja Virtual</a>
            <a href="cart.php" class="cart-icon-header">
                <i class="fas fa-shopping-cart" style="color: white;"></i>
                <?php if ($cart_item_count > 0): ?>
                    <span class="cart-count"><?php echo $cart_item_count; ?></span>
                <?php endif; ?>
            </a>
            <button class="menu-toggle" onclick="toggleSidebar()">
                <span class="sr-only">Abrir menu</span>
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Menu lateral (Sidebar) -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Menu</span>
            <button class="close-sidebar" onclick="toggleSidebar()">
                <span class="sr-only">Fechar menu</span>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item">
                <span class="icon"><i class="fas fa-home"></i></span>
                Início
            </a>
            <a href="cart.php" class="menu-item">
                <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                Meu Carrinho
                <?php if ($cart_item_count > 0): ?>
                    <span class="cart-count" style="position: static; margin-left: 5px;"><?php echo $cart_item_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="../admin/login.php" class="menu-item">
                <span class="icon"><i class="fas fa-cog"></i></span>
                Painel Admin
            </a>
        </div>
    </nav>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Container principal -->
    <main class="main-container">
        <div class="checkout-header">
            <a href="cart.php" class="back-button">
                <span class="icon"><i class="fas fa-arrow-left"></i></span>
                Voltar para o Carrinho
            </a>
            <h1 class="checkout-title">Finalizar Compra</h1>
        </div>

        <section class="checkout-section">
            <h3>Itens no Carrinho:</h3>
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <?php if ($item["image"]): ?>
                        <img src="uploads/<?php echo htmlspecialchars($item["image"]); ?>" alt="<?php echo htmlspecialchars($item["name"]); ?>" class="cart-item-image">
                    <?php else: ?>
                        <div class="cart-item-image no-image-large"><i class="fas fa-camera"></i></div>
                    <?php endif; ?>
                    <div class="cart-item-details">
                        <div class="cart-item-name"><?php echo htmlspecialchars($item["name"]); ?></div>
                        <div class="cart-item-price">R$ <?php echo number_format($item["price"], 2, ",", "."); ?> x <?php echo $item["quantity"]; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cart-summary-checkout">
                Total do Pedido: R$ <?php echo number_format($cart_total, 2, ",", "."); ?>
            </div>
        </section>

        <section class="checkout-form-section" id="checkoutFormSection">
            <h2 class="checkout-title">Informações para Contato e Entrega</h2>
            
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
                    <i class="fas fa-check"></i>
                    Confirmar Pedido via WhatsApp
                </button>
            </form>
        </section>
    </main>

    <div class="floating-checkout-btn" id="toggleCheckoutBtn">
        Finalizar Compra <i class="fas fa-chevron-up"></i>
    </div>

    <script>
        // Função para alternar o menu lateral
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const overlay = document.getElementById("overlay");
            
            sidebar.classList.toggle("open");
            overlay.classList.toggle("active");
        }

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

        // Função para processar a compra
        function handleCheckout(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            if (!data.nome_completo || !data.whatsapp) {
                alert("Por favor, preencha todos os campos obrigatórios.");
                return;
            }

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
            
            const whatsappUrl = `https://api.whatsapp.com/send?phone=55${data.whatsapp.replace(/\D/g, '')}&text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, "_blank");

            // Limpar o carrinho após a finalização da compra (opcional, pode ser feito no backend)
            // clear_cart(); // Isso exigiria uma requisição AJAX ou um redirecionamento para uma página de sucesso que limpe o carrinho
        }

        // Função para alternar a visibilidade da seção de checkout
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleCheckoutBtn');
            const checkoutFormSection = document.getElementById('checkoutFormSection');
            const toggleIcon = toggleBtn.querySelector('i');
            const body = document.body;

            toggleBtn.addEventListener('click', function() {
                checkoutFormSection.classList.toggle('show');
                
                if (checkoutFormSection.classList.contains('show')) {
                    // Formulário expandido
                    toggleIcon.classList.remove('fa-chevron-up');
                    toggleIcon.classList.add('fa-chevron-down');
                    toggleBtn.innerHTML = 'Ocultar Formulário <i class="fas fa-chevron-down"></i>';
                    body.classList.add('form-expanded');
                } else {
                    // Formulário oculto
                    toggleIcon.classList.remove('fa-chevron-down');
                    toggleIcon.classList.add('fa-chevron-up');
                    toggleBtn.innerHTML = 'Finalizar Compra <i class="fas fa-chevron-up"></i>';
                    body.classList.remove('form-expanded');
                }
            });
        });
    </script>
</body>
</html>


