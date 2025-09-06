<?php
// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'database/db_connect_env.php';
require_once 'include/cart_functions.php';

// Buscar configurações da loja
$config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

// Valores padrão caso não existam configurações
$nome_loja = $config ? $config['nome_loja'] : 'Loja Virtual';

initialize_cart();
$cart_item_count = get_cart_item_count();
?>

<!-- Header -->
<header class="header">
    <div class="header-content">
        <a href="index.php" class="logo">
            <i class="fas fa-store"></i> <?php echo htmlspecialchars($nome_loja); ?>
        </a>
        <div class="header-actions">
            <a href="favoritos.php" class="header-icon">
                <i class="fas fa-heart"></i>
            </a>
            <a href="cart.php" class="header-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_item_count > 0): ?>
                    <span class="cart-count"><?php echo $cart_item_count; ?></span>
                <?php endif; ?>
            </a>
            <button class="menu-toggle" onclick="toggleSidebar()">
                <span class="sr-only">Abrir menu</span>
                <i class="fas fa-bars"></i>
            </button>
        </div>
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
        <a href="#categorias" class="menu-item" onclick="scrollToCategories()">
            <span class="icon"><i class="fas fa-folder"></i></span>
            Categorias
        </a>
        <a href="favoritos.php" class="menu-item">
            <span class="icon"><i class="fas fa-heart"></i></span>
            Favoritos
        </a>
        <a href="cart.php" class="menu-item">
            <span class="icon"><i class="fas fa-shopping-cart"></i></span>
            Meu Carrinho
            <?php if ($cart_item_count > 0): ?>
                <span class="cart-count" style="position: static; margin-left: 5px;"><?php echo $cart_item_count; ?></span>
            <?php endif; ?>
        </a>
    </div>
</nav>

<!-- Overlay -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<script>
// Função para alternar o menu lateral
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

// Função para rolar até as categorias
function scrollToCategories() {
    const categoriesSection = document.getElementById('categorias');
    if (categoriesSection) {
        categoriesSection.scrollIntoView({ behavior: 'smooth' });
    }
    toggleSidebar(); // Fechar o menu após clicar
}

// Fechar menu ao pressionar ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    }
});
</script>

