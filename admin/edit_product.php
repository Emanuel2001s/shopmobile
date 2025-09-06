<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

$message = '';
$message_type = '';
$produto = null;

// Verificar se foi passado um ID
$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    header('Location: products.php');
    exit;
}

// Buscar o produto
$stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header('Location: products.php');
    exit;
}

// Processar upload de imagem
function uploadImage($file, $prefix = 'produto_') {
    $upload_dir = '../uploads/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = $_POST['preco'] ?? 0;
    $categoria_id = $_POST['categoria_id'] ?? 0;
    $estoque = $_POST['estoque'] ?? 0;
    
    $foto_principal = $produto['foto_principal'];
    $fotos_adicionais = $produto['fotos_adicionais'];
    
    // Upload da nova foto principal se fornecida
    if (isset($_FILES['foto_principal']) && $_FILES['foto_principal']['error'] === 0) {
        $nova_foto = uploadImage($_FILES['foto_principal'], 'principal_');
        if ($nova_foto) {
            // Deletar foto antiga se existir
            if ($foto_principal && file_exists('../uploads/' . $foto_principal)) {
                unlink('../uploads/' . $foto_principal);
            }
            $foto_principal = $nova_foto;
        } else {
            $message = "Erro ao fazer upload da nova foto principal!";
            $message_type = "error";
        }
    }
    
    // Upload das novas fotos adicionais se fornecidas
    if (isset($_FILES['fotos_adicionais']) && is_array($_FILES['fotos_adicionais']['name'])) {
        $fotos_array = [];
        $tem_upload = false;
        
        for ($i = 0; $i < count($_FILES['fotos_adicionais']['name']); $i++) {
            if ($_FILES['fotos_adicionais']['error'][$i] === 0) {
                $tem_upload = true;
                $file = [
                    'name' => $_FILES['fotos_adicionais']['name'][$i],
                    'type' => $_FILES['fotos_adicionais']['type'][$i],
                    'tmp_name' => $_FILES['fotos_adicionais']['tmp_name'][$i],
                    'error' => $_FILES['fotos_adicionais']['error'][$i],
                    'size' => $_FILES['fotos_adicionais']['size'][$i]
                ];
                $uploaded = uploadImage($file, 'adicional_');
                if ($uploaded) {
                    $fotos_array[] = $uploaded;
                }
            }
        }
        
        if ($tem_upload && count($fotos_array) > 0) {
            // Deletar fotos antigas se existirem
            if ($fotos_adicionais) {
                $fotos_antigas = explode(',', $fotos_adicionais);
                foreach ($fotos_antigas as $foto) {
                    if (file_exists('../uploads/' . $foto)) {
                        unlink('../uploads/' . $foto);
                    }
                }
            }
            $fotos_adicionais = implode(',', $fotos_array);
        }
    }
    
    if (!empty($nome) && $preco > 0 && $categoria_id > 0 && $estoque >= 0 && empty($message)) {
        $stmt = $conn->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, foto_principal = ?, fotos_adicionais = ?, categoria_id = ?, estoque = ? WHERE id = ?");
        if ($stmt->execute([$nome, $descricao, $preco, $foto_principal, $fotos_adicionais, $categoria_id, $estoque, $id])) {
            $message = "Produto atualizado com sucesso!";
            $message_type = "success";
            
            // Recarregar dados do produto
            $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Erro ao atualizar produto!";
            $message_type = "error";
        }
    } elseif (empty($message)) {
        $message = "Todos os campos obrigatórios devem ser preenchidos!";
        $message_type = "error";
    }
}

// Buscar categorias para o formulário
$stmt = $conn->prepare("SELECT * FROM categorias WHERE status = 'ativo' ORDER BY nome");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - Painel Admin</title>
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
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .form-section h2 {
            margin-bottom: 1.5rem;
            color: #333;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-right: 1rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .current-images {
            margin-bottom: 1rem;
        }
        
        .current-images h4 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .image-preview {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .image-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #e1e5e9;
        }
        
        .stock-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .stock-info h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stock-current {
            font-size: 1.1rem;
            font-weight: 600;
            color: #28a745;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✏️ Editar Produto</h1>
        <a href="products.php" class="btn-back">← Voltar aos Produtos</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2>Editar: <?php echo htmlspecialchars($produto['nome']); ?></h2>
            
            <div class="stock-info">
                <h4>📦 Estoque Atual</h4>
                <div class="stock-current" style="color: <?php echo $produto['estoque'] > 0 ? '#28a745' : '#dc3545'; ?>;">
                    <?php echo $produto['estoque']; ?> unidades
                    <?php if ($produto['estoque'] == 0): ?>
                        <span style="color: #dc3545; font-size: 0.9rem;">(⚠️ Produto sem estoque)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome">Nome do Produto:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($produto['nome']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="preco">Preço (R$):</label>
                        <input type="number" id="preco" name="preco" step="0.01" min="0" value="<?php echo $produto['preco']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria_id">Categoria:</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                        <?php echo $categoria['id'] == $produto['categoria_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estoque">Estoque Disponível:</label>
                        <input type="number" id="estoque" name="estoque" min="0" value="<?php echo $produto['estoque']; ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="descricao">Descrição:</label>
                        <textarea id="descricao" name="descricao" placeholder="Descrição detalhada do produto"><?php echo htmlspecialchars($produto['descricao']); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <?php if ($produto['foto_principal']): ?>
                            <div class="current-images">
                                <h4>Foto Principal Atual:</h4>
                                <div class="image-preview">
                                    <img src="../uploads/<?php echo htmlspecialchars($produto['foto_principal']); ?>" alt="Foto Principal">
                                </div>
                            </div>
                        <?php endif; ?>
                        <label for="foto_principal">Nova Foto Principal (opcional):</label>
                        <input type="file" id="foto_principal" name="foto_principal" accept="image/*">
                    </div>
                    
                    <div class="form-group full-width">
                        <?php if ($produto['fotos_adicionais']): ?>
                            <div class="current-images">
                                <h4>Fotos Adicionais Atuais:</h4>
                                <div class="image-preview">
                                    <?php 
                                    $fotos = explode(',', $produto['fotos_adicionais']);
                                    foreach ($fotos as $foto): 
                                        if ($foto):
                                    ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($foto); ?>" alt="Foto Adicional">
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <label for="fotos_adicionais">Novas Fotos Adicionais (opcional):</label>
                        <input type="file" id="fotos_adicionais" name="fotos_adicionais[]" accept="image/*" multiple>
                        <small style="color: #666;">Você pode selecionar múltiplas imagens. As fotos atuais serão substituídas.</small>
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="products.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Atualizar Produto</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

