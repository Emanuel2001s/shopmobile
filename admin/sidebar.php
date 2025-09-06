<!-- Sidebar Admin -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-cog"></i> Admin</h3>
        <button class="sidebar-close" onclick="toggleAdminSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="categories.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
            <i class="fas fa-folder"></i>
            <span>Gerenciar Categorias</span>
        </a>
        
        <a href="products.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i>
            <span>Gerenciar Produtos</span>
        </a>
        
        <a href="orders.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Gerenciar Pedidos</span>
        </a>
        
        <a href="clientes.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Clientes</span>
        </a>
        
        <a href="sliders.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'sliders.php' ? 'active' : ''; ?>">
            <i class="fas fa-images"></i>
            <span>Sliders Promocionais</span>
        </a>
        
        <a href="../index.php" class="sidebar-item" target="_blank">
            <i class="fas fa-external-link-alt"></i>
            <span>Ver Loja</span>
        </a>
        
        <a href="configuracoes.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'configuracoes.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Configurações</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <a href="logout.php" class="sidebar-item logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </nav>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleAdminSidebar()"></div>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" onclick="toggleAdminSidebar()">
    <i class="fas fa-bars"></i>
</button>

<style>
/* Estilos do Sidebar Admin */
.admin-sidebar {
    position: fixed;
    top: 0;
    left: -300px;
    width: 300px;
    height: 100vh;
    background: white;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000;
    transition: left 0.3s ease;
    overflow-y: auto;
}

.admin-sidebar.open {
    left: 0;
}

.sidebar-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.sidebar-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.sidebar-close:hover {
    background: rgba(255,255,255,0.2);
}

.sidebar-nav {
    padding: 1rem 0;
}

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-item:hover {
    background: #f8f9fa;
    color: #667eea;
    border-left-color: #667eea;
}

.sidebar-item.active {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    color: #667eea;
    border-left-color: #667eea;
    font-weight: 600;
}

.sidebar-item.logout {
    color: #e74c3c;
}

.sidebar-item.logout:hover {
    background: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
    border-left-color: #e74c3c;
}

.sidebar-item i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.sidebar-divider {
    height: 1px;
    background: #eee;
    margin: 1rem 1.5rem;
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
}

.sidebar-toggle {
    position: fixed;
    top: 20px;
    left: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    z-index: 998;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

/* Responsividade */
@media (min-width: 1024px) {
    .admin-sidebar {
        left: 0;
        position: relative;
        width: 250px;
        height: auto;
        box-shadow: none;
        border-right: 1px solid #eee;
    }
    
    .sidebar-toggle {
        display: none;
    }
    
    .sidebar-overlay {
        display: none;
    }
    
    .admin-layout {
        display: flex;
    }
    
    .admin-content {
        flex: 1;
        padding: 2rem;
    }
}

@media (max-width: 1023px) {
    .admin-layout {
        padding-left: 0;
    }
    
    .admin-content {
        padding: 80px 1rem 1rem 1rem;
    }
}
</style>

<script>
function toggleAdminSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

// Fechar sidebar ao pressionar ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    }
});

// Fechar sidebar ao clicar em um link (em dispositivos móveis)
document.querySelectorAll('.sidebar-item').forEach(item => {
    item.addEventListener('click', function() {
        if (window.innerWidth < 1024) {
            setTimeout(() => {
                toggleAdminSidebar();
            }, 100);
        }
    });
});
</script>

