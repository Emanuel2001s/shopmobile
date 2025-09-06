<?php
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

// Buscar produtos sem estoque
$result = $conn->query("SELECT p.*, c.nome as categoria_nome 
                       FROM produtos p 
                       LEFT JOIN categorias c ON p.categoria_id = c.id 
                       WHERE p.estoque = 0 
                       ORDER BY p.nome");
$produtos_sem_estoque = $result->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos Sem Estoque - Painel Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h2 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            transition: transform 0.3s;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #6c757d;
            font-size: 3rem;
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .product-category {
            color: #667eea;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .stock-alert {
            background: #f8d7da;
            color: #721c24;
            padding: 0.5rem;
            border-radius: 5px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .btn-edit {
            width: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            display: block;
            transition: transform 0.3s;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .empty-state p {
            color: #666;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛍️ Painel Administrativo</h1>
        <div class="user-info">
            <span>Bem-vindo, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</span>
            <a href="logout.php" class="btn-logout">Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>⚠️ Produtos Sem Estoque</h2>
            <a href="index.php" class="btn-back">← Voltar ao Dashboard</a>
        </div>
        
        <?php if (count($produtos_sem_estoque) > 0): ?>
            <div class="products-grid">
                <?php foreach ($produtos_sem_estoque as $produto): ?>
                    <div class="product-card">
                        <?php if ($produto['foto_principal']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($produto['foto_principal']); ?>" 
                                 alt="<?php echo htmlspecialchars($produto['nome']); ?>" 
                                 class="product-image">
                        <?php else: ?>
                            <div class="product-image no-image">📷</div>
                        <?php endif; ?>
                        
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></h3>
                            
                            <?php if ($produto['categoria_nome']): ?>
                                <div class="product-category">📂 <?php echo htmlspecialchars($produto['categoria_nome']); ?></div>
                            <?php endif; ?>
                            
                            <div class="product-price">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                            
                            <div class="stock-alert">
                                ⚠️ Produto sem estoque
                            </div>
                            
                            <a href="products.php?edit=<?php echo $produto['id']; ?>" class="btn-edit">
                                ✏️ Editar Produto
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">✅</div>
                <h3>Todos os produtos têm estoque!</h3>
                <p>Não há produtos sem estoque no momento.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

