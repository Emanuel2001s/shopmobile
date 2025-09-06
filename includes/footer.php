<?php
// Buscar configurações da loja
if (!isset($config)) {
    $config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
}

// Valores padrão caso não existam configurações
$titulo_footer = $config ? $config['titulo_footer'] : 'Loja Virtual - Todos os direitos reservados';

// Verificar formas de pagamento aceitas
$pagamento_pix = $config ? $config['pagamento_pix'] : 0;
$pagamento_cartao = $config ? $config['pagamento_cartao'] : 0;
$pagamento_dinheiro = $config ? $config['pagamento_dinheiro'] : 0;

// Buscar os 3 produtos mais comprados (simulando com os mais recentes por enquanto)
$produtos_populares_query = $conn->query("
    SELECT p.*, COUNT(pe.id) as total_pedidos 
    FROM produtos p 
    LEFT JOIN pedidos pe ON p.id = pe.produto_id 
    WHERE p.estoque > 0 
    GROUP BY p.id 
    ORDER BY total_pedidos DESC, p.id DESC 
    LIMIT 3
");
$produtos_populares = $produtos_populares_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Footer -->
<footer class="footer">
    <?php if (count($produtos_populares) > 0): ?>
        <div class="footer-products">
            <h3><i class="fas fa-fire"></i> Produtos Mais Comprados</h3>
            <div class="popular-products">
                <?php foreach ($produtos_populares as $produto): ?>
                    <div class="popular-product">
                        <a href="product.php?id=<?php echo $produto['id']; ?>" class="popular-product-link">
                            <?php if ($produto['foto_principal']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($produto['foto_principal']); ?>" 
                                     alt="<?php echo htmlspecialchars($produto['nome']); ?>" 
                                     class="popular-product-image">
                            <?php else: ?>
                                <div class="popular-product-image no-image">
                                    <i class="fas fa-camera"></i>
                                </div>
                            <?php endif; ?>
                            <div class="popular-product-info">
                                <h4><?php echo htmlspecialchars($produto['nome']); ?></h4>
                                <div class="popular-product-price">
                                    R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Formas de Pagamento no Footer -->
    <?php if ($pagamento_pix || $pagamento_cartao || $pagamento_dinheiro): ?>
    <div class="footer-payment-methods">
        <h3><i class="fas fa-credit-card"></i> Formas de Pagamento Aceitas</h3>
        <div class="footer-payment-icons">
            <?php if ($pagamento_pix): ?>
                <div class="footer-payment-item">
                    <i class="fas fa-qrcode"></i>
                    <span>PIX</span>
                </div>
            <?php endif; ?>
            
            <?php if ($pagamento_cartao): ?>
                <div class="footer-payment-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Cartão de Crédito/Débito</span>
                </div>
            <?php endif; ?>
            
            <?php if ($pagamento_dinheiro): ?>
                <div class="footer-payment-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Dinheiro</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Redes Sociais no Footer -->
    <?php 
    // Buscar URLs das redes sociais
    $instagram_url = $config ? ($config['instagram_url'] ?? '') : '';
    $facebook_url = $config ? ($config['facebook_url'] ?? '') : '';
    $youtube_url = $config ? ($config['youtube_url'] ?? '') : '';
    $x_twitter_url = $config ? ($config['x_twitter_url'] ?? '') : '';
    
    // Verificar se pelo menos uma rede social está configurada
    $tem_redes_sociais = !empty($instagram_url) || !empty($facebook_url) || !empty($youtube_url) || !empty($x_twitter_url);
    ?>
    
    <?php if ($tem_redes_sociais): ?>
    <div class="footer-social-media">
        <h3><i class="fas fa-share-alt"></i> Siga-nos nas Redes Sociais</h3>
        <div class="social-icons">
            <?php if (!empty($instagram_url)): ?>
                <a href="<?php echo htmlspecialchars($instagram_url); ?>" 
                   target="_blank" 
                   class="social-icon" 
                   title="Siga-nos no Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
            <?php endif; ?>
            
            <?php if (!empty($facebook_url)): ?>
                <a href="<?php echo htmlspecialchars($facebook_url); ?>" 
                   target="_blank" 
                   class="social-icon" 
                   title="Curta nossa página no Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
            <?php endif; ?>
            
            <?php if (!empty($youtube_url)): ?>
                <a href="<?php echo htmlspecialchars($youtube_url); ?>" 
                   target="_blank" 
                   class="social-icon" 
                   title="Inscreva-se no nosso canal">
                    <i class="fab fa-youtube"></i>
                </a>
            <?php endif; ?>
            
            <?php if (!empty($x_twitter_url)): ?>
                <a href="<?php echo htmlspecialchars($x_twitter_url); ?>" 
                   target="_blank" 
                   class="social-icon" 
                   title="Siga-nos no X (Twitter)">
                    <i class="fab fa-twitter"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($titulo_footer); ?></p>
    </div>
</footer>

<style>
/* Estilos do Footer */
.footer {
    background: #2c3e50;
    color: white;
    margin-top: 2rem;
    padding: 2rem 1rem 1rem;
}

.footer-products {
    margin-bottom: 2rem;
}

.footer-products h3 {
    color: #ecf0f1;
    font-size: 1.2rem;
    margin-bottom: 1rem;
    text-align: center;
}

.footer-products h3 i {
    color: #e74c3c;
    margin-right: 0.5rem;
}

.popular-products {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    max-width: 900px;
    margin: 0 auto;
}

.popular-product {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.popular-product:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.popular-product-link {
    display: flex;
    align-items: center;
    padding: 1rem;
    text-decoration: none;
    color: inherit;
}

.popular-product-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    margin-right: 1rem;
    flex-shrink: 0;
}

.popular-product-image.no-image {
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #bdc3c7;
}

.popular-product-info h4 {
    font-size: 0.9rem;
    margin: 0 0 0.5rem 0;
    color: #ecf0f1;
    line-height: 1.3;
}

    .popular-product-price {
        font-weight: bold;
        color: #f39c12;
        font-size: 1rem;
    }
    
    /* Estilos para Formas de Pagamento no Footer */
    .footer-payment-methods {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .footer-payment-methods h3 {
        color: #ecf0f1;
        font-size: 1.1rem;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .footer-payment-methods h3 i {
        color: #3498db;
        margin-right: 0.5rem;
    }
    
    .footer-payment-icons {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
    }
    
    .footer-payment-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        min-width: 120px;
        transition: all 0.3s ease;
    }
    
    .footer-payment-item:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
    }
    
    .footer-payment-item i {
        font-size: 2rem;
        color: #3498db;
    }
    
    .footer-payment-item span {
        font-size: 0.9rem;
        font-weight: 500;
        color: #ecf0f1;
        text-align: center;
    }

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1rem;
    text-align: center;
}

.footer-bottom p {
    margin: 0;
    color: #bdc3c7;
    font-size: 0.9rem;
}

/* Responsividade para mobile */
@media (max-width: 768px) {
    .popular-products {
        grid-template-columns: 1fr;
        gap: 0.8rem;
    }
    
    .popular-product-link {
        padding: 0.8rem;
    }
    
    .popular-product-image {
        width: 50px;
        height: 50px;
    }
    
    .popular-product-info h4 {
        font-size: 0.85rem;
    }
    
    .popular-product-price {
        font-size: 0.9rem;
    }
    
    .footer-payment-icons {
        gap: 1rem;
    }
    
    .footer-payment-item {
        min-width: 100px;
        padding: 0.8rem;
    }
    
    .footer-payment-item i {
        font-size: 1.5rem;
    }
    
    .footer-payment-item span {
        font-size: 0.8rem;
    }
}

/* Estilos para Redes Sociais no Footer */
.footer-social-media {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
    width: 100%;
}

.footer-social-media h3 {
    color: #ecf0f1;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    text-align: center;
}

.footer-social-media h3 i {
    color: #e74c3c;
    margin-right: 0.5rem;
}

.social-icons {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    width: 100%;
}

.social-icon {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #3498db;
    font-size: 2rem;
    transition: all 0.3s ease;
}

.social-icon:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

/* Responsividade para redes sociais */
@media (max-width: 768px) {
    .social-icons {
        gap: 0.8rem;
        justify-content: center;
    }
    
    .social-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
}
}
</style>

