<?php
// Buscar categoria selecionada
$categoria_id = $_GET['categoria'] ?? '';

// Buscar produtos
$sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.estoque > 0";

if ($categoria_id && is_numeric($categoria_id)) {
    $sql .= " AND p.categoria_id = " . intval($categoria_id);
}

$sql .= " ORDER BY p.nome";

$result = $conn->query($sql);
$produtos = $result->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para o filtro
$result = $conn->query("SELECT * FROM categorias WHERE status = 'ativo' ORDER BY nome");
$categorias = $result->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($config) ? htmlspecialchars($config['nome_loja']) : 'Loja Virtual'; ?></title>
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos específicos da página inicial */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1rem;
            text-align: center;
            margin: -1rem -1rem 1.5rem -1rem;
        }
        
        .hero-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .hero-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .stats-bar {
            background: white;
            padding: 1rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .stats-bar .count {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stats-bar .label {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Container principal -->
    <main class="main-container">
        <!-- Seção hero -->
        <section class="hero-section">
            <h1 class="hero-title">Bem-vindo à nossa loja!</h1>
            <p class="hero-subtitle">Encontre os melhores produtos com qualidade e preço justo</p>
        </section>

        <!-- Barra de estatísticas -->
        <div class="stats-bar">
            <div class="count"><?php echo count($produtos); ?></div>
            <div class="label">produtos disponíveis</div>
        </div>

        <!-- Filtros de categoria -->
        <?php if (count($categorias) > 0): ?>
            <section class="category-filters" id="categorias">
                <h3><i class="fas fa-folder"></i> Filtrar por categoria:</h3>
                <div class="filter-buttons">
                    <a href="index.php" class="filter-btn <?php echo empty($categoria_id) ? 'active' : ''; ?>">
                        Todas
                    </a>
                    <?php foreach ($categorias as $categoria): ?>
                        <a href="?categoria=<?php echo $categoria['id']; ?>" 
                           class="filter-btn <?php echo $categoria_id == $categoria['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($categoria['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Grid de produtos -->
        <section class="products-section">
            <?php if (count($produtos) > 0): ?>
                <div class="products-grid">
                    <?php foreach ($produtos as $produto): ?>
                        <article class="product-card fade-in">
                            <?php if ($produto['foto_principal']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($produto['foto_principal']); ?>" 
                                     alt="<?php echo htmlspecialchars($produto['nome']); ?>" 
                                     class="product-image"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="product-image no-image"><i class="fas fa-camera"></i></div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <h2 class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></h2>
                                <div class="product-price">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                                <a href="product.php?id=<?php echo $produto['id']; ?>" class="btn-buy">
                                    <i class="fas fa-shopping-cart"></i> Comprar
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-box"></i></div>
                    <h3>Nenhum produto encontrado</h3>
                    <p>
                        <?php if ($categoria_id): ?>
                            Não há produtos nesta categoria no momento.
                        <?php else: ?>
                            Ainda não temos produtos cadastrados.
                        <?php endif; ?>
                    </p>
                    <?php if ($categoria_id): ?>
                        <a href="index.php" class="filter-btn" style="margin-top: 1rem;">Ver todos os produtos</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Animação de entrada dos produtos
        function animateProducts() {
            const products = document.querySelectorAll('.product-card');
            products.forEach((product, index) => {
                setTimeout(() => {
                    product.style.opacity = '0';
                    product.style.transform = 'translateY(20px)';
                    product.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        product.style.opacity = '1';
                        product.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        }

        // Executar animação quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            animateProducts();
        });

        // Lazy loading para imagens
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Melhorar performance em dispositivos móveis
        let ticking = false;

        function updateScrollPosition() {
            // Adicionar efeitos baseados no scroll se necessário
            ticking = false;
        }

        function requestTick() {
            if (!ticking) {
                requestAnimationFrame(updateScrollPosition);
                ticking = true;
            }
        }

        window.addEventListener('scroll', requestTick);
    </script>
</body>
</html>

