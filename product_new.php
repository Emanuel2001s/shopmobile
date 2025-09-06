<?php
// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php_error_reporting.php';
require_once 'database/db_connect_env.php';
require_once 'include/cart_functions.php';

// Buscar configurações da loja
$config_query = $conn->query("SELECT * FROM configuracoes LIMIT 1");
$config = $config_query->fetch(PDO::FETCH_ASSOC);

// Valores padrão caso não existam configurações
$whatsapp_loja = $config ? $config['whatsapp'] : '5511999999999';

initialize_cart();
$cart_item_count = get_cart_item_count();
$cart_items = get_cart_items();
$cart_total = get_cart_total();

$produto_id = $_GET['id'] ?? 0;

if (!$produto_id || !is_numeric($produto_id)) {
    header("Location: index.php");
    exit();
}

// Buscar produto
$stmt = $conn->prepare("SELECT p.*, c.nome as categoria_nome FROM produtos p 
                       LEFT JOIN categorias c ON p.categoria_id = c.id 
                       WHERE p.id = ? AND p.estoque > 0");
$stmt->execute([$produto_id]);
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header("Location: index.php");
    exit();
}

// Processar fotos adicionais
$fotos_adicionais = [];
if ($produto["fotos_adicionais"]) {
    $fotos_adicionais = explode(",", $produto["fotos_adicionais"]);
}

// Lógica para adicionar ao carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];
    $product_quantity = $_POST['quantity'];
    $product_image = $_POST['product_image'];

    add_to_cart($product_id, $product_name, $product_price, $product_quantity, $product_image);
    header("Location: product.php?id=" . $product_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($produto["nome"]); ?> - <?php echo isset($config) ? htmlspecialchars($config['nome_loja']) : 'Loja Virtual'; ?></title>
    <link rel="stylesheet" href="css/mobile-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos específicos da página do produto */
        .product-header {
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
        
        .product-gallery {
            margin-bottom: 1.5rem;
        }
        
        .main-image-container {
            width: 100%;
            padding-bottom: 100%;
            position: relative;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .main-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px;
        }
        
        .additional-images {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        
        .additional-images::-webkit-scrollbar {
            height: 4px;
        }
        
        .additional-images::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }
        
        .additional-images::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 2px;
        }
        
        .thumb-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            flex-shrink: 0;
            border: 2px solid transparent;
        }
        
        .thumb-image:hover,
        .thumb-image.active {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .product-details {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .product-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .product-category {
            color: #667eea;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .product-price {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 1.5rem;
        }
        
        .product-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .purchase-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            position: sticky;
            bottom: 1rem;
        }
        
        .purchase-title {
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
        
        .no-image-large {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #6c757d;
            font-size: 6rem;
            border-radius: 15px;
        }
        
        .mobile-purchase-toggle {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .mobile-purchase-toggle .toggle-text {
            font-weight: 600;
            font-size: 1rem;
            margin-right: 0.5rem;
        }
        
        .mobile-purchase-toggle .toggle-icon {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        
        .mobile-purchase-toggle.expanded .toggle-icon {
            transform: rotate(180deg);
        }
        
        .purchase-section.mobile-collapsed {
            transform: translateY(100%);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .purchase-section.mobile-expanded {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 767px) {
            .mobile-purchase-toggle {
                display: block;
            }
            
            .purchase-section {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 999;
                margin: 0;
                border-radius: 15px 15px 0 0;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 -10px 30px rgba(0,0,0,0.2);
            }
            
            .purchase-section.mobile-collapsed {
                transform: translateY(calc(100% - 60px));
            }
            
            .purchase-section.mobile-expanded {
                transform: translateY(0);
            }
            
            .product-content {
                padding-bottom: 200px;
            }
        }

        @media (min-width: 768px) {
            .main-container {
                display: grid;
                grid-template-columns: 1fr 400px;
                gap: 2rem;
                align-items: start;
            }
            
            .product-content {
                grid-column: 1;
            }
            
            .purchase-section {
                grid-column: 2;
                position: sticky;
                top: 100px;
            }
            
            .mobile-purchase-toggle {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Container principal -->
    <main class="main-container">
        <div class="product-content">
            <!-- Header da página -->
            <div class="product-header">
                <a href="index.php" class="back-button">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    Voltar para a loja
                </a>
            </div>

            <!-- Galeria de imagens -->
            <section class="product-gallery">
                <?php if ($produto["foto_principal"]): ?>
                    <div class="main-image-container">
                        <img src="uploads/<?php echo htmlspecialchars($produto["foto_principal"]); ?>" 
                             alt="<?php echo htmlspecialchars($produto["nome"]); ?>" 
                             class="main-image" id="mainImage">
                    </div>
                <?php else: ?>
                    <div class="main-image-container no-image-large"><i class="fas fa-camera"></i></div>
                <?php endif; ?>
                
                <?php if (count($fotos_adicionais) > 0): ?>
                    <div class="additional-images">
                        <?php if ($produto["foto_principal"]): ?>
                            <img src="uploads/<?php echo htmlspecialchars($produto["foto_principal"]); ?>" 
                                 alt="<?php echo htmlspecialchars($produto["nome"]); ?>" 
                                 class="thumb-image active"
                                 onclick="changeMainImage(this.src)">
                        <?php endif; ?>
                        
                        <?php foreach ($fotos_adicionais as $foto): ?>
                            <?php if (trim($foto)): ?>
                                <img src="uploads/<?php echo htmlspecialchars(trim($foto)); ?>" 
                                     alt="<?php echo htmlspecialchars($produto["nome"]); ?>" 
                                     class="thumb-image"
                                     onclick="changeMainImage(this.src)">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Detalhes do produto -->
            <section class="product-details">
                <h1 class="product-title"><?php echo htmlspecialchars($produto["nome"]); ?></h1>
                
                <?php if ($produto["categoria_nome"]): ?>
                    <div class="product-category"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($produto["categoria_nome"]); ?></div>
                <?php endif; ?>
                
                <div class="product-price">R$ <?php echo number_format($produto["preco"], 2, ",", "."); ?></div>
                
                <?php if ($produto["descricao"]): ?>
                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($produto["descricao"])); ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário para adicionar ao carrinho -->
                <form method="POST" action="product.php?id=<?php echo $produto_id; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $produto["id"]; ?>">
                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($produto["nome"]); ?>">
                    <input type="hidden" name="product_price" value="<?php echo $produto["preco"]; ?>">
                    <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($produto["foto_principal"]); ?>">
                    
                    <div class="form-group">
                        <label for="quantity">Quantidade:</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" class="form-control" style="width: 80px;">
                    </div>
                    <button type="submit" name="add_to_cart" class="btn-whatsapp" style="background: #007bff; margin-top: 1rem;">
                        <i class="fas fa-shopping-cart"></i> Adicionar ao Carrinho
                    </button>
                </form>
            </section>
        </div>

        <!-- Seção de compra -->
        <section class="purchase-section" id="purchaseFormSection">
            <h2 class="purchase-title"><i class="fas fa-shopping-cart"></i> Finalizar Compra</h2>
            
            <form id="purchaseForm" onsubmit="handlePurchase(event)">
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
                    Confirmar Pedido via WhatsApp
                </button>
            </form>
        </section>
    </main>

    <!-- Botão de toggle para dispositivos móveis -->
    <div class="mobile-purchase-toggle" id="mobilePurchaseToggle" onclick="toggleMobilePurchase()">
        <span class="toggle-text">Finalizar Compra</span>
        <i class="fas fa-chevron-up toggle-icon" id="toggleIcon"></i>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Função para alternar o container de compra em dispositivos móveis
        function toggleMobilePurchase() {
            const purchaseSection = document.querySelector(".purchase-section");
            const toggleButton = document.getElementById("mobilePurchaseToggle");
            const toggleIcon = document.getElementById("toggleIcon");

            if (purchaseSection.classList.contains("mobile-expanded")) {
                purchaseSection.classList.remove("mobile-expanded");
                purchaseSection.classList.add("mobile-collapsed");
                toggleButton.classList.remove("expanded");
                toggleIcon.className = "fas fa-chevron-up toggle-icon";
                toggleButton.style.display = "flex";
            } else {
                purchaseSection.classList.remove("mobile-collapsed");
                purchaseSection.classList.add("mobile-expanded");
                toggleButton.classList.add("expanded");
                toggleIcon.className = "fas fa-chevron-down toggle-icon";
                toggleButton.style.display = "none";
            }
        }

        // Inicializar o estado do container móvel
        document.addEventListener("DOMContentLoaded", function() {
            const purchaseSection = document.querySelector(".purchase-section");
            if (window.innerWidth <= 767) {
                purchaseSection.classList.add("mobile-collapsed");
            }
        });

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
        async function handlePurchase(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            if (!data.nome_completo || !data.whatsapp) {
                alert("Por favor, preencha todos os campos obrigatórios.");
                return;
            }

            // Preparar dados para enviar ao save_order.php
            const orderData = {
                nome_completo: data.nome_completo,
                whatsapp: data.whatsapp,
                entregar_endereco: data.entregar_endereco ? 1 : 0,
                rua: data.rua || '',
                numero: data.numero || '',
                bairro: data.bairro || '',
                cidade: data.cidade || '',
                cep: data.cep || '',
                produto_id: <?php echo $produto_id; ?>
            };

            try {
                const response = await fetch('save_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                });

                const result = await response.json();

                if (result.success) {
                    console.log('Pedido salvo com sucesso:', result.pedido_id);
                    
                    // Criar mensagem do WhatsApp
                    let message = `🛒 *Novo Pedido*\n\n` +
                                  `*Produto:* <?php echo htmlspecialchars($produto["nome"]); ?>\n` +
                                  `*Preço:* R$ <?php echo number_format($produto["preco"], 2, ",", "."); ?>\n\n` +
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

                } else {
                    alert('Erro ao salvar pedido: ' + result.message);
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro ao finalizar o pedido. Tente novamente.');
            }
        }

        // Função para mudar a imagem principal na galeria
        function changeMainImage(src) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumb-image').forEach(img => {
                img.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

