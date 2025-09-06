<?php
require_once 'database/db_connect.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favoritos - Loja Virtual</title>
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .favorites-container {
            padding: 1rem;
        }
        
        .favorites-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .favorites-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .favorites-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .empty-favorites {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .empty-favorites i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-favorites h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .empty-favorites p {
            margin-bottom: 2rem;
        }
        
        .btn-continue-shopping {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: transform 0.2s ease;
        }
        
        .btn-continue-shopping:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .favorite-item {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .favorite-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .favorite-info {
            flex: 1;
        }
        
        .favorite-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .favorite-price {
            color: #667eea;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .btn-remove-favorite {
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .btn-remove-favorite:hover {
            background: #ff3742;
        }
        
        .btn-view-product {
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            display: inline-block;
            transition: background 0.2s ease;
        }
        
        .btn-view-product:hover {
            background: #5a6fd8;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="main-container">
        <div class="favorites-container">
            <div class="favorites-header">
                <h1><i class="fas fa-heart"></i> Meus Favoritos</h1>
                <p>Produtos que você salvou para comprar depois</p>
            </div>

            <div id="favorites-list">
                <!-- Os favoritos serão carregados aqui via JavaScript -->
            </div>

            <div id="empty-favorites" class="empty-favorites" style="display: none;">
                <i class="fas fa-heart-broken"></i>
                <h3>Nenhum favorito ainda</h3>
                <p>Adicione produtos aos seus favoritos para vê-los aqui</p>
                <a href="index.php" class="btn-continue-shopping">
                    <i class="fas fa-shopping-bag"></i> Continuar Comprando
                </a>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Função para carregar favoritos do localStorage
        function loadFavorites() {
            const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            const favoritesList = document.getElementById('favorites-list');
            const emptyFavorites = document.getElementById('empty-favorites');

            if (favorites.length === 0) {
                favoritesList.style.display = 'none';
                emptyFavorites.style.display = 'block';
                return;
            }

            favoritesList.style.display = 'block';
            emptyFavorites.style.display = 'none';

            // Buscar dados dos produtos favoritos
            fetchFavoriteProducts(favorites);
        }

        // Função para buscar dados dos produtos favoritos
        async function fetchFavoriteProducts(favoriteIds) {
            try {
                const response = await fetch('get_favorites.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids: favoriteIds })
                });

                const products = await response.json();
                displayFavorites(products);
            } catch (error) {
                console.error('Erro ao carregar favoritos:', error);
                // Fallback: mostrar apenas os IDs se não conseguir carregar os dados
                displayFavoritesFromStorage(favoriteIds);
            }
        }

        // Função para exibir favoritos
        function displayFavorites(products) {
            const favoritesList = document.getElementById('favorites-list');
            
            favoritesList.innerHTML = products.map(product => `
                <div class="favorite-item" data-id="${product.id}">
                    <img src="uploads/${product.foto_principal || 'no-image.jpg'}" 
                         alt="${product.nome}" 
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik00MCAzMEM0My4zMTM3IDMwIDQ2IDMyLjY4NjMgNDYgMzZDNDYgMzkuMzEzNyA0My4zMTM3IDQyIDQwIDQyQzM2LjY4NjMgNDIgMzQgMzkuMzEzNyAzNCAzNkMzNCAzMi42ODYzIDM2LjY4NjMgMzAgNDAgMzBaIiBmaWxsPSIjOUI5QkEwIi8+CjxwYXRoIGQ9Ik0yOCA0OEg1MkM1My4xMDQ2IDQ4IDU0IDQ4Ljg5NTQgNTQgNTBWNTJDNTQgNTMuMTA0NiA1My4xMDQ2IDU0IDUyIDU0SDI4QzI2Ljg5NTQgNTQgMjYgNTMuMTA0NiAyNiA1MlY1MEMyNiA0OC44OTU0IDI2Ljg5NTQgNDggMjggNDhaIiBmaWxsPSIjOUI5QkEwIi8+Cjwvc3ZnPgo='">
                    <div class="favorite-info">
                        <div class="favorite-name">${product.nome}</div>
                        <div class="favorite-price">R$ ${parseFloat(product.preco).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                        <a href="product.php?id=${product.id}" class="btn-view-product">
                            <i class="fas fa-eye"></i> Ver Produto
                        </a>
                    </div>
                    <button class="btn-remove-favorite" onclick="removeFavorite(${product.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
        }

        // Função para remover favorito
        function removeFavorite(productId) {
            let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            favorites = favorites.filter(id => id !== productId);
            localStorage.setItem('favorites', JSON.stringify(favorites));
            
            // Recarregar a lista
            loadFavorites();
            
            // Mostrar feedback
            showToast('Produto removido dos favoritos!', 'success');
        }

        // Função para mostrar toast
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i>
                ${message}
            `;
            
            // Adicionar estilos do toast se não existirem
            if (!document.querySelector('#toast-styles')) {
                const style = document.createElement('style');
                style.id = 'toast-styles';
                style.textContent = `
                    .toast {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: white;
                        padding: 1rem 1.5rem;
                        border-radius: 10px;
                        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                        z-index: 1000;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        transform: translateX(100%);
                        transition: transform 0.3s ease;
                    }
                    .toast.show {
                        transform: translateX(0);
                    }
                    .toast-success {
                        border-left: 4px solid #27ae60;
                        color: #27ae60;
                    }
                    .toast-info {
                        border-left: 4px solid #3498db;
                        color: #3498db;
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        // Carregar favoritos quando a página carregar
        document.addEventListener('DOMContentLoaded', loadFavorites);
    </script>
</body>
</html>

