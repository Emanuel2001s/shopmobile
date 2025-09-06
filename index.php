<?php
require_once 'database/db_connect.php';

// Buscar categoria selecionada
$categoria_id = $_GET['categoria'] ?? '';

// Configurações de paginação
$produtos_por_pagina = 9;
$pagina_atual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $produtos_por_pagina;

// Contar o total de produtos para a paginação
$count_sql = "SELECT COUNT(*) FROM produtos WHERE estoque > 0";
if ($categoria_id && is_numeric($categoria_id)) {
    $count_sql .= " AND categoria_id = " . intval($categoria_id);
}
$total_produtos = $conn->query($count_sql)->fetchColumn();
$total_paginas = ceil($total_produtos / $produtos_por_pagina);

// Buscar produtos com paginação
$sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.estoque > 0";

if ($categoria_id && is_numeric($categoria_id)) {
    $sql .= " AND p.categoria_id = " . intval($categoria_id);
}

$sql .= " ORDER BY p.id DESC LIMIT {$produtos_por_pagina} OFFSET {$offset}";

$result = $conn->query($sql);
$produtos = $result->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para o filtro
$result = $conn->query("SELECT * FROM categorias WHERE status = 'ativo' ORDER BY nome");
$categorias = $result->fetchAll(PDO::FETCH_ASSOC);

// Buscar sliders ativos
$sliders_query = $conn->query("SELECT * FROM sliders WHERE ativo = 1 ORDER BY ordem ASC, id ASC");
$sliders = $sliders_query->fetchAll(PDO::FETCH_ASSOC);

// Debug: Verificar quantos sliders foram encontrados
error_log("Sliders encontrados: " . count($sliders));
foreach ($sliders as $slider) {
    error_log("Slider ID: " . $slider['id'] . ", Link: " . $slider['link'] . ", Ativo: " . $slider['ativo'] . ", Ordem: " . $slider['ordem']);
}

// Verificar se há sliders
if (empty($sliders)) {
    error_log("NENHUM SLIDER ATIVO ENCONTRADO!");
}
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
            border-radius: 8px;
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
            line-height: 1.4;
        }

        /* Estilos do filtro de busca */
        .search-section {
            margin: 1.5rem 0;
            padding: 0 1rem;
        }

        .search-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 25px;
            font-size: 1rem;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            outline: none;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 5px 25px rgba(102, 126, 234, 0.15);
        }

        .search-input::placeholder {
            color: #999;
            font-style: italic;
        }

        .search-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.1rem;
            pointer-events: none;
        }

        .search-clear {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
        }

        .search-clear.show {
            opacity: 1;
            visibility: visible;
        }

        .search-clear:hover {
            background: #f5f5f5;
            color: #667eea;
        }

        .search-results-info {
            text-align: center;
            margin: 1rem 0;
            color: #666;
            font-size: 0.9rem;
        }

        .search-results-info.searching {
            color: #667eea;
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Tema escuro */
        @media (prefers-color-scheme: dark) {
            .search-input {
                background: #2d3748;
                border-color: #4a5568;
                color: white;
            }

            .search-input::placeholder {
                color: #a0aec0;
            }

            .search-input:focus {
                border-color: #667eea;
                background: #2d3748;
            }

            .search-clear:hover {
                background: #4a5568;
            }

            .search-results-info {
                color: #a0aec0;
            }

            .stats-bar {
                background: #2d3748;
                color: white;
            }

            .stats-bar .label {
                color: #a0aec0;
            }
        }

        /* Estilos de Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem 0;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            display: block;
            padding: 0.7rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            background-color: #fff;
            border: 1px solid #e1e5e9;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .pagination a:hover {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination span.current-page {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
            font-weight: bold;
        }

        .pagination .disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .pagination .disabled:hover {
            background-color: #fff;
            color: #667eea;
            border-color: #e1e5e9;
        }

        /* Animações */
        .product-card {
            transition: all 0.3s ease;
        }

        .product-card.hidden {
            opacity: 0;
            transform: scale(0.8);
            pointer-events: none;
        }

        .fade-in {
            animation: fadeInUp 0.5s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsividade mobile */
        @media (max-width: 768px) {
            .search-section {
                padding: 0 0.5rem;
            }

            .search-input {
                padding: 0.9rem 0.9rem 0.9rem 3rem;
                font-size: 0.95rem;
            }

            .search-icon {
                left: 1rem;
                font-size: 1rem;
            }

            .search-clear {
                right: 0.8rem;
                font-size: 1rem;
            }
        }
        /* Estilos do Slider */
        .slider-container {
            position: relative;
            width: 100%;
            height: 400px;
            overflow: hidden;
            margin: 2rem 0 1.5rem 0; /* Espaço acima e abaixo */
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .slider-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        .slider-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .slider-slide.active {
            opacity: 1 !important;
            z-index: 10;
        }
        
        .slider-slide:not(.active) {
            opacity: 0 !important;
            z-index: 1;
            pointer-events: none !important;
            visibility: hidden;
        }
        
        .slider-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .slider-content {
            position: absolute;
            bottom: 50px;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.22); /* Mais translúcido */
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            color: white;
            padding: 1.5rem 1rem;
            text-align: center;
            border-radius: 10px;
            margin: 0 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        }
        
        .slider-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            color: #ffffff;
        }
        
        .slider-description {
            font-size: 1rem;
            opacity: 0.95;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
            color: #f0f0f0;
        }
        
        .slider-controls {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 1rem;
            pointer-events: none;
            z-index: 20;
        }
        
        .slider-btn {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            pointer-events: auto;
            font-size: 1.2rem;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        
        .slider-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .slider-indicators {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.5rem;
            z-index: 10;
        }
        
        .slider-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .slider-indicator.active {
            background: white;
        }
        
        .slider-indicator:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .slider-link {
            display: block;
            width: 100%;
            height: 100%;
            text-decoration: none;
            position: relative;
            color: inherit;
        }
        
        .slider-link:hover {
            text-decoration: none;
            color: inherit;
        }
        @media (max-width: 768px) {
            .slider-container {
                height: 220px;
                margin-top: 1.2rem; /* Espaço entre header e slider */
            }
            .slider-content {
                bottom: 30px;
                padding: 1rem 0.5rem;
                margin: 0 0.5rem;
                background: rgba(0,0,0,0.16); /* Ainda mais leve no mobile */
            }
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

        <?php if (!empty($sliders)): ?>
        <!-- Debug: Verificar dados dos sliders -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === 'sliders'): ?>
            <div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">
                <h4>Debug - Dados dos Sliders:</h4>
                <?php foreach ($sliders as $index => $slider): ?>
                    <p><strong>Slider <?php echo $index + 1; ?>:</strong></p>
                    <ul>
                        <li>ID: <?php echo $slider['id']; ?></li>
                        <li>Título: <?php echo htmlspecialchars($slider['titulo']); ?></li>
                        <li>Link: <?php echo htmlspecialchars($slider['link']); ?></li>
                        <li>Imagem: <?php echo htmlspecialchars($slider['imagem']); ?></li>
                        <li>Ativo: <?php echo $slider['ativo']; ?></li>
                        <li>Ordem: <?php echo $slider['ordem']; ?></li>
                    </ul>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="slider-container">
            <div class="slider-wrapper">
                <?php foreach ($sliders as $index => $slider): ?>
                <!-- Debug: Slide <?php echo $index + 1; ?> - ID: <?php echo $slider['id']; ?> - Link: <?php echo htmlspecialchars($slider['link']); ?> -->
                <div class="slider-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>" data-slider-id="<?php echo $slider['id']; ?>">
                    <?php if (!empty($slider['link']) && trim($slider['link']) !== ''): ?>
                        <a href="<?php echo htmlspecialchars(trim($slider['link'])); ?>" class="slider-link" data-link="<?php echo htmlspecialchars(trim($slider['link'])); ?>">
                            <img src="uploads/sliders/<?php echo htmlspecialchars($slider['imagem']); ?>"
                                 alt="<?php echo htmlspecialchars($slider['titulo']); ?>"
                                 class="slider-image">
                            <div class="slider-content">
                                <h2 class="slider-title"><?php echo htmlspecialchars($slider['titulo']); ?></h2>
                                <?php if ($slider['descricao']): ?>
                                <p class="slider-description"><?php echo htmlspecialchars($slider['descricao']); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php else: ?>
                        <img src="uploads/sliders/<?php echo htmlspecialchars($slider['imagem']); ?>"
                             alt="<?php echo htmlspecialchars($slider['titulo']); ?>"
                             class="slider-image">
                        <div class="slider-content">
                            <h2 class="slider-title"><?php echo htmlspecialchars($slider['titulo']); ?></h2>
                            <?php if ($slider['descricao']): ?>
                            <p class="slider-description"><?php echo htmlspecialchars($slider['descricao']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Controles do slider -->
            <div class="slider-controls">
                <button class="slider-btn slider-prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="slider-btn slider-next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="slider-indicators">
                <?php foreach ($sliders as $index => $slider): ?>
                <button class="slider-indicator <?php echo $index === 0 ? 'active' : ''; ?>"
                        onclick="goToSlide(<?php echo $index; ?>)"></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Barra de estatísticas -->
        <div class="stats-bar">
            <div class="count" id="products-count"><?php echo $total_produtos; ?></div>
            <div class="label">
                produtos disponíveis | 
                Categoria: 
                <?php 
                if (empty($categoria_id)) {
                    echo "Todas";
                } else {
                    // Buscar nome da categoria selecionada
                    $categoria_nome = "Desconhecida";
                    foreach ($categorias as $cat) {
                        if ($cat['id'] == $categoria_id) {
                            $categoria_nome = htmlspecialchars($cat['nome']);
                            break;
                        }
                    }
                    echo $categoria_nome;
                }
                ?>
            </div>
        </div>

        <!-- Filtros de categoria -->
        <?php if (count($categorias) > 0): ?>
            <section class="category-filters" id="categorias">
                <h3><i class="fas fa-folder"></i> Filtrar por categoria:</h3>
                <div class="filter-buttons">
                    <a href="index.php" class="filter-btn <?php echo empty($categoria_id) ? 'active' : ''; ?>" data-categoria="">
                        Todas
                    </a>
                    <?php foreach ($categorias as $categoria): ?>
                        <a href="?categoria=<?php echo $categoria['id']; ?>" 
                           class="filter-btn <?php echo $categoria_id == $categoria['id'] ? 'active' : ''; ?>"
                           data-categoria="<?php echo $categoria['id']; ?>">
                            <?php echo htmlspecialchars($categoria['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Seção de busca -->
        <section class="search-section">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="search-input" 
                       class="search-input" 
                       placeholder="Buscar produtos por nome..."
                       autocomplete="off">
                <button type="button" id="search-clear" class="search-clear">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="search-results-info" class="search-results-info"></div>
        </section>

        <!-- Grid de produtos -->
        <section class="products-section">
            <div class="products-grid" id="products-grid">
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $produto): ?>
                        <article class="product-card fade-in">
                            <?php if ($produto['foto_principal']): ?>
                                <a href="product.php?id=<?php echo $produto["id"]; ?>">
                                <img src="uploads/<?php echo htmlspecialchars($produto["foto_principal"]); ?>" 
                                     alt="<?php echo htmlspecialchars($produto["nome"]); ?>" 
                                     class="product-image">
                                </a>
                            <?php else: ?>
                                <div class="product-image no-image"><i class="fas fa-camera"></i></div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <h2 class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></h2>
                                <div class="product-price">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                                <a href="product.php?id=<?php echo $produto['id']; ?>" class="btn-buy">
                                    <i class="fas fa-info-circle"></i> Mais Detalhes
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Estado vazio -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <div class="icon"><i class="fas fa-search"></i></div>
                <h3>Nenhum produto encontrado</h3>
                <p>Tente buscar com outras palavras-chave.</p>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination" id="pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?><?php echo $categoria_id ? '&categoria=' . $categoria_id : ''; ?>">&laquo; Anterior</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Anterior</span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="?pagina=<?php echo $i; ?><?php echo $categoria_id ? '&categoria=' . $categoria_id : ''; ?>" 
                           class="<?php echo $i == $pagina_atual ? 'current-page' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?><?php echo $categoria_id ? '&categoria=' . $categoria_id : ''; ?>">Próxima &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Próxima &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Variáveis globais
        let searchTimeout;
        let currentCategoryId = '<?php echo $categoria_id; ?>';
        let isSearching = false;
        
        const searchInput = document.getElementById('search-input');
        const searchClear = document.getElementById('search-clear');
        const searchResultsInfo = document.getElementById('search-results-info');
        const productsGrid = document.getElementById('products-grid');
        const productsCount = document.getElementById('products-count');
        const pagination = document.getElementById('pagination');
        const emptyState = document.getElementById('empty-state');

        // Função para buscar produtos via AJAX
        async function searchProducts(query) {
            const searchTerm = query.toLowerCase().trim();
            
            if (searchTerm === '') {
                resetToOriginalState();
                return;
            }

            isSearching = true;
            showLoadingState();

            try {
                const response = await fetch('search_products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        search: searchTerm,
                        categoria: currentCategoryId
                    })
                });

                if (!response.ok) {
                    throw new Error('Erro na requisição');
                }

                const data = await response.json();
                
                if (data.success) {
                    displaySearchResults(data.produtos, searchTerm, data.total_encontrados);
                } else {
                    showErrorState('Erro ao buscar produtos');
                }
            } catch (error) {
                console.error('Erro na busca:', error);
                showErrorState('Erro de conexão');
            } finally {
                isSearching = false;
            }
        }

        // Mostrar estado de carregamento
        function showLoadingState() {
            searchResultsInfo.innerHTML = '<span class="loading-spinner"></span>Buscando produtos...';
            searchResultsInfo.classList.add('searching');
        }

        // Exibir resultados da busca
        function displaySearchResults(produtos, searchTerm, totalEncontrados) {
            // Limpar grid atual
            productsGrid.innerHTML = '';

            if (produtos.length === 0) {
                showNoResultsState(searchTerm);
                return;
            }

            // Criar cards dos produtos encontrados
            produtos.forEach(produto => {
                const productCard = createProductCard(produto);
                productsGrid.appendChild(productCard);
            });

            // Atualizar informações
            updateResultsInfo(searchTerm, totalEncontrados);
            
            // Esconder paginação e estado vazio
            if (pagination) pagination.style.display = 'none';
            if (emptyState) emptyState.style.display = 'none';

            // Animar entrada dos produtos
            animateProducts();
        }

        // Criar card de produto
        function createProductCard(produto) {
            const article = document.createElement('article');
            article.className = 'product-card fade-in';
            
            const imageHtml = produto.foto_principal 
                ? `<a href="product.php?id=${produto.id}">
                     <img src="uploads/${produto.foto_principal}" 
                          alt="${produto.nome}" 
                          class="product-image">
                   </a>`
                : `<div class="product-image no-image"><i class="fas fa-camera"></i></div>`;

            article.innerHTML = `
                ${imageHtml}
                <div class="product-info">
                    <h2 class="product-name">${produto.nome}</h2>
                    <div class="product-price">R$ ${parseFloat(produto.preco).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                    <a href="product.php?id=${produto.id}" class="btn-buy">
                        <i class="fas fa-info-circle"></i> Mais Detalhes
                    </a>
                </div>
            `;

            return article;
        }

        // Mostrar estado de nenhum resultado
        function showNoResultsState(searchTerm) {
            productsGrid.innerHTML = '';
            if (emptyState) {
                emptyState.style.display = 'block';
                emptyState.innerHTML = `
                    <div class="icon"><i class="fas fa-search"></i></div>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Nenhum resultado para "${searchTerm}". Tente buscar com outras palavras-chave.</p>
                `;
            }
            
            if (pagination) pagination.style.display = 'none';
            updateResultsInfo(searchTerm, 0);
        }

        // Mostrar estado de erro
        function showErrorState(message) {
            searchResultsInfo.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-triangle"></i> ${message}</span>`;
        }

        // Resetar para estado original
        function resetToOriginalState() {
            // Recarregar a página para mostrar produtos originais com paginação
            if (isSearching) return; // Evitar reload durante busca
            
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.delete('search');
            window.location.href = currentUrl.toString();
        }

        // Atualizar informações dos resultados
        function updateResultsInfo(searchTerm, count) {
            productsCount.textContent = count;
            
            // Atualizar label da categoria
            const labelElement = document.querySelector('.stats-bar .label');
            if (labelElement) {
                let categoriaNome = "Todas";
                if (currentCategoryId) {
                    // Buscar nome da categoria atual
                    const categoriaButtons = document.querySelectorAll('.filter-btn');
                    categoriaButtons.forEach(btn => {
                        if (btn.dataset.categoria === currentCategoryId) {
                            categoriaNome = btn.textContent.trim();
                        }
                    });
                }
                labelElement.innerHTML = `produtos disponíveis | Categoria: ${categoriaNome}`;
            }
            
            if (count === 0) {
                searchResultsInfo.textContent = `Nenhum resultado para "${searchTerm}"`;
            } else if (count === 1) {
                searchResultsInfo.textContent = `1 produto encontrado para "${searchTerm}"`;
            } else {
                searchResultsInfo.textContent = `${count} produtos encontrados para "${searchTerm}"`;
            }
            
            searchResultsInfo.classList.add('searching');
        }

        // Event listeners
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value;
            
            // Mostrar/esconder botão de limpar
            if (query.length > 0) {
                searchClear.classList.add('show');
            } else {
                searchClear.classList.remove('show');
            }

            // Debounce da busca
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchProducts(query);
            }, 500); // Aumentado para 500ms para reduzir requisições
        });

        // Limpar busca
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchClear.classList.remove('show');
            resetToOriginalState();
        });

        // Limpar busca com ESC
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                searchClear.classList.remove('show');
                resetToOriginalState();
            }
        });

        // Atualizar categoria atual quando filtros são clicados
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                currentCategoryId = this.dataset.categoria || '';
                
                // Se há busca ativa, refazer a busca com nova categoria
                if (searchInput.value.trim() !== '') {
                    e.preventDefault();
                    searchProducts(searchInput.value);
                }
            });
        });

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

        // JavaScript do Slider - SIMPLES
        document.addEventListener('DOMContentLoaded', function() {
            let currentSlide = 0;
            let slideInterval;
            let slides = document.querySelectorAll('.slider-slide');
            let indicators = document.querySelectorAll('.slider-indicator');

            // Funções globais para o slider
            window.changeSlide = function(direction) {
                let newSlide = currentSlide + direction;
                
                if (newSlide >= slides.length) {
                    newSlide = 0;
                } else if (newSlide < 0) {
                    newSlide = slides.length - 1;
                }
                
                showSlide(newSlide);
            };

            window.goToSlide = function(n) {
                showSlide(n);
            };

            function showSlide(n) {
                // Esconder todos os slides
                slides.forEach(slide => slide.classList.remove('active'));
                indicators.forEach(indicator => indicator.classList.remove('active'));
                
                // Mostrar slide atual
                if (slides[n]) {
                    slides[n].classList.add('active');
                    indicators[n].classList.add('active');
                    currentSlide = n;
                }
            }

            function startAutoSlide() {
                slideInterval = setInterval(() => {
                    changeSlide(1);
                }, 5000);
            }

            function stopAutoSlide() {
                clearInterval(slideInterval);
            }

            // Inicializar slider se existir
            if (slides.length > 0) {
                showSlide(0);
                startAutoSlide();
                
                // Pausar auto-slide quando mouse está sobre o slider
                const sliderContainer = document.querySelector('.slider-container');
                if (sliderContainer) {
                    sliderContainer.addEventListener('mouseenter', stopAutoSlide);
                    sliderContainer.addEventListener('mouseleave', startAutoSlide);
                }
            }
        });

        // Executar quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            animateProducts();
            
            // Verificar se há parâmetro de busca na URL
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                searchInput.value = searchParam;
                searchClear.classList.add('show');
                searchProducts(searchParam);
            }
        });

        // Lazy loading para imagens
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            // Observar imagens existentes e futuras
            function observeImages() {
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            observeImages();
        }
    </script>
</body>
</html>

