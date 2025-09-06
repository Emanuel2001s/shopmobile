<?php
// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php_error_reporting.php';
require_once 'database/db_connect_env.php';
require_once 'include/cart_functions.php';

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
    <title>Meu Carrinho - Loja Virtual</title>
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
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
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
            margin-bottom: 0.5rem;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .cart-item-quantity {
            width: 60px;
            padding: 0.4rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        .remove-item-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .cart-summary {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: right;
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 1.5rem;
        }
        .checkout-btn {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            margin-top: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
            color: white;
        }
        .empty-cart-message {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-size: 1.1rem;
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
        <div class="product-content">
            <div class="product-header">
                <a href="index.php" class="back-button">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    Continuar Comprando
                </a>
                <h1 style="text-align: center; margin-top: 1rem;">Meu Carrinho</h1>
            </div>

            <div class="cart-container">
                <?php if (empty($cart_items)): ?>
                    <p class="empty-cart-message">Seu carrinho está vazio.</p>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <?php if ($item['image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                            <?php else: ?>
                                <div class="cart-item-image no-image-large" style="font-size: 2rem; display: flex; align-items: center; justify-content: center; background: #f8f9fa;"><i class="fas fa-camera"></i></div>
                            <?php endif; ?>
                            <div class="cart-item-details">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="cart-item-price">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></div>
                                <div class="cart-item-actions">
                                    <form method="POST" action="cart.php">
                                        <input type="hidden" name="action" value="update_quantity">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="cart-item-quantity" onchange="this.form.submit()">
                                    </form>
                                    <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" class="remove-item-btn"><i class="fas fa-trash"></i> Remover</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="cart-summary">
                        Total: R$ <?php echo number_format($cart_total, 2, ',', '.'); ?>
                    </div>
                    <a href="checkout.php" class="checkout-btn"><i class="fas fa-credit-card"></i> Finalizar Compra</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Função para alternar o menu lateral
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const overlay = document.getElementById("overlay");
            
            sidebar.classList.toggle("open");
            overlay.classList.toggle("active");
        }
    </script>
</body>
</html>

