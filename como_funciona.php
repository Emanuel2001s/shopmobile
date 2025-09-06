<?php
require_once 'database/db_connect_env.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Como Funciona - Shop Fazenda Rio Grande</title>
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos específicos da página Como Funciona */
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

        .content-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .content-section h2 {
            color: #667eea;
            font-size: 1.3rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-section p {
            color: #555;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .highlight-box {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 1.5rem 0;
            text-align: center;
            font-weight: 500;
        }

        .products-list {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 1.5rem 0;
        }

        .products-list h3 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .product-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
            color: #555;
            font-size: 0.95rem;
        }

        .product-item i {
            color: #667eea;
            width: 20px;
            text-align: center;
        }

        .steps-container {
            display: grid;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .step-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
            position: relative;
        }

        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            position: absolute;
            top: -15px;
            left: 1rem;
        }

        .step-title {
            color: #333;
            font-weight: bold;
            margin-bottom: 0.5rem;
            margin-top: 0.5rem;
        }

        .step-description {
            color: #666;
            font-size: 0.95rem;
        }

        .whatsapp-cta {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin: 1.5rem 0;
        }

        .whatsapp-cta h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .whatsapp-cta p {
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .btn-whatsapp {
            background: white;
            color: #25d366;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .location-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin: 1.5rem 0;
        }

        .location-info h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .location-info p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Responsividade */
        @media (min-width: 768px) {
            .steps-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
        }

        @media (min-width: 1024px) {
            .main-container {
                max-width: 800px;
                margin: 0 auto;
            }
        }

        /* Animações */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .content-section {
                background: #2d3748;
                color: #e2e8f0;
            }

            .content-section h2 {
                color: #90cdf4;
            }

            .content-section p {
                color: #cbd5e0;
            }

            .products-list {
                background: #4a5568;
            }

            .product-item {
                color: #cbd5e0;
            }

            .step-card {
                background: #2d3748;
                color: #e2e8f0;
            }

            .step-description {
                color: #a0aec0;
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
            <h1 class="hero-title">🛍️ Como Funciona</h1>
            <p class="hero-subtitle">Descubra como é fácil comprar na Shop Fazenda Rio Grande</p>
        </section>

        <!-- Sobre nós -->
        <section class="content-section fade-in">
            <h2><i class="fas fa-store"></i> Sobre Nós - Shop Fazenda Rio Grande</h2>
            <p>Somos uma loja online e local ao mesmo tempo 😄! Estamos situados em Fazenda Rio Grande - PR, mas funcionamos de forma 100% digital: você escolhe os produtos no nosso catálogo online e recebe tudo no conforto da sua casa, sem precisar sair.</p>
            
            <p>🚚 Entregamos em toda a região de Fazenda Rio Grande e Curitiba, com taxa de entrega acessível. Após a compra, você é direcionado para o WhatsApp, onde finalizamos seu pedido com todos os detalhes: valores, taxa de entrega, informações dos produtos, promoções e muito mais!</p>
        </section>

        <!-- Como funciona - Passos -->
        <section class="content-section fade-in">
            <h2><i class="fas fa-cogs"></i> Como Funciona</h2>
            
            <div class="steps-container">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-title">Navegue pelo Catálogo</div>
                    <div class="step-description">Explore nossos produtos organizados por categorias e encontre exatamente o que você precisa.</div>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-title">Escolha seus Produtos</div>
                    <div class="step-description">Clique em "Mais Detalhes" para ver informações completas e fotos dos produtos.</div>
                </div>

                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-title">Finalize no WhatsApp</div>
                    <div class="step-description">Clique no botão do WhatsApp para finalizar seu pedido com atendimento personalizado.</div>
                </div>

                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-title">Receba em Casa</div>
                    <div class="step-description">Aguarde a entrega no conforto da sua casa com nossa taxa de entrega acessível.</div>
                </div>
            </div>
        </section>

        <!-- Produtos disponíveis -->
        <section class="content-section fade-in">
            <h2><i class="fas fa-box-open"></i> Nosso Estoque</h2>
            <p>Nosso estoque está recheado com produtos úteis e populares da internet, incluindo:</p>
            
            <div class="products-list">
                <div class="product-item">
                    <i class="fas fa-tv"></i>
                    <span>TV Box e eletrônicos</span>
                </div>
                <div class="product-item">
                    <i class="fas fa-gamepad"></i>
                    <span>Acessórios para PC e videogames</span>
                </div>
                <div class="product-item">
                    <i class="fas fa-volume-up"></i>
                    <span>Caixas de som, fones de ouvido e headsets</span>
                </div>
                <div class="product-item">
                    <i class="fas fa-mug-hot"></i>
                    <span>Copos térmicos de qualidade</span>
                </div>
                <div class="product-item">
                    <i class="fas fa-plug"></i>
                    <span>Escovas elétricas, utensílios e carregadores</span>
                </div>
                <div class="product-item">
                    <i class="fas fa-seedling"></i>
                    <span>Artigos para jardim, mangueiras mágicas e muito mais</span>
                </div>
                <div class="product-item">
                    <i class="fas fa-lightbulb"></i>
                    <span>Achadinhos virais e novidades imperdíveis</span>
                </div>
            </div>
            
            <p><strong>Tudo com qualidade, agilidade e atendimento humanizado!</strong></p>
        </section>

        <!-- Localização -->
        <section class="location-info fade-in">
            <h3><i class="fas fa-map-marker-alt"></i> Nossa Localização</h3>
            <p>Fazenda Rio Grande - PR</p>
            <p>Entregamos em toda a região de Fazenda Rio Grande e Curitiba</p>
        </section>

        <!-- Aviso importante -->
        <section class="highlight-box fade-in">
            <h3><i class="fas fa-exclamation-triangle"></i> Importante</h3>
            <p>⚠️ Não finalizamos as vendas pelo site. Nosso site funciona como um catálogo online — simples, prático e direto. Você vê os produtos aqui e finaliza sua compra de forma rápida pelo WhatsApp ✅</p>
        </section>

        <!-- CTA WhatsApp -->
        <section class="whatsapp-cta fade-in">
            <h3><i class="fab fa-whatsapp"></i> Pronto para Comprar?</h3>
            <p>Entre em contato conosco pelo WhatsApp e finalize seu pedido com atendimento personalizado!</p>
            <a href="https://wa.me/5541984304401?text=Olá! Gostaria de fazer um pedido." class="btn-whatsapp" target="_blank">
                <i class="fab fa-whatsapp"></i>
                Falar no WhatsApp
            </a>
        </section>

        <!-- Botão para voltar à loja -->
        <section class="content-section fade-in" style="text-align: center;">
            <h3><i class="fas fa-shopping-bag"></i> Explore Nossos Produtos</h3>
            <p>Veja todos os produtos disponíveis em nosso catálogo online.</p>
            <a href="index.php" class="btn-whatsapp" style="background: #667eea; color: white;">
                <i class="fas fa-store"></i>
                Ver Catálogo
            </a>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Animação de entrada das seções
        function animateSections() {
            const sections = document.querySelectorAll('.fade-in');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            sections.forEach(section => {
                observer.observe(section);
            });
        }

        // Executar animação quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            animateSections();
            
            // Adicionar efeito de hover nos cards de passos
            const stepCards = document.querySelectorAll('.step-card');
            stepCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 3px 15px rgba(0,0,0,0.05)';
                });
            });
        });

        // Smooth scroll para links internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

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

