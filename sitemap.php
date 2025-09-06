<?php
/**
 * Gerador de Sitemap Dinâmico - Versão Corrigida
 * Gera sitemap.xml automaticamente baseado nos produtos do banco de dados
 */

// Incluir conexão com banco de dados ANTES de definir headers
require_once 'database/db_connect_env.php';

// Configurações do sitemap
$base_url = 'https://' . $_SERVER['HTTP_HOST']; // Ajuste conforme necessário
$sitemap_file = 'sitemap.xml';

// Função para escapar URLs para XML
function xmlEscape($string) {
    return htmlspecialchars($string, ENT_XML1, 'UTF-8');
}

// Função para formatar data no padrão ISO 8601
function formatDate($date) {
    if (empty($date)) {
        return date('c'); // Data atual se não houver data
    }
    return date('c', strtotime($date));
}

// Função para determinar prioridade baseada no tipo de página
function getPriority($type) {
    switch ($type) {
        case 'home':
            return '1.0';
        case 'category':
            return '0.8';
        case 'product':
            return '0.6';
        case 'page':
            return '0.5';
        default:
            return '0.5';
    }
}

// Função para determinar frequência de mudança
function getChangeFreq($type) {
    switch ($type) {
        case 'home':
            return 'daily';
        case 'category':
            return 'weekly';
        case 'product':
            return 'weekly';
        case 'page':
            return 'monthly';
        default:
            return 'monthly';
    }
}

try {
    // Verificar se a conexão existe
    if (!isset($conn)) {
        throw new Exception("Variável de conexão \$conn não encontrada");
    }

    // Iniciar XML
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    
    // Criar elemento raiz
    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $urlset->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
    $xml->appendChild($urlset);
    
    // Array para armazenar URLs já adicionadas (evitar duplicatas)
    $added_urls = [];
    
    // 1. Página inicial
    $url = $xml->createElement('url');
    $loc = $xml->createElement('loc', xmlEscape($base_url . '/'));
    $lastmod = $xml->createElement('lastmod', formatDate(date('Y-m-d H:i:s')));
    $changefreq = $xml->createElement('changefreq', getChangeFreq('home'));
    $priority = $xml->createElement('priority', getPriority('home'));
    
    $url->appendChild($loc);
    $url->appendChild($lastmod);
    $url->appendChild($changefreq);
    $url->appendChild($priority);
    $urlset->appendChild($url);
    
    $added_urls[] = $base_url . '/';
    
    // 2. Buscar e adicionar categorias (com verificação se a tabela existe)
    try {
        $stmt = $conn->prepare("SELECT * FROM categorias ORDER BY nome");
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categorias as $categoria) {
            $category_url = $base_url . '/?categoria=' . $categoria['id'];
            
            if (!in_array($category_url, $added_urls)) {
                $url = $xml->createElement('url');
                $loc = $xml->createElement('loc', xmlEscape($category_url));
                
                // Verificar se existe campo de data
                $date_field = null;
                if (isset($categoria['updated_at'])) {
                    $date_field = $categoria['updated_at'];
                } elseif (isset($categoria['created_at'])) {
                    $date_field = $categoria['created_at'];
                } elseif (isset($categoria['data_criacao'])) {
                    $date_field = $categoria['data_criacao'];
                }
                
                $lastmod = $xml->createElement('lastmod', formatDate($date_field));
                $changefreq = $xml->createElement('changefreq', getChangeFreq('category'));
                $priority = $xml->createElement('priority', getPriority('category'));
                
                $url->appendChild($loc);
                $url->appendChild($lastmod);
                $url->appendChild($changefreq);
                $url->appendChild($priority);
                $urlset->appendChild($url);
                
                $added_urls[] = $category_url;
            }
        }
    } catch (PDOException $e) {
        // Se a tabela categorias não existir ou der erro, continuar sem ela
        error_log("Erro ao buscar categorias: " . $e->getMessage());
    }
    
    // 3. Buscar e adicionar produtos
    try {
        // Primeiro, tentar com JOIN
        $stmt = $conn->prepare("
            SELECT p.*, c.nome as categoria_nome 
            FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.estoque > 0 
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Se der erro no JOIN, tentar sem ele
        try {
            $stmt = $conn->prepare("SELECT * FROM produtos WHERE estoque > 0 ORDER BY id DESC");
            $stmt->execute();
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            // Se ainda der erro, tentar sem filtro de estoque
            $stmt = $conn->prepare("SELECT * FROM produtos ORDER BY id DESC");
            $stmt->execute();
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    foreach ($produtos as $produto) {
        $product_url = $base_url . '/product.php?id=' . $produto['id'];
        
        if (!in_array($product_url, $added_urls)) {
            $url = $xml->createElement('url');
            $loc = $xml->createElement('loc', xmlEscape($product_url));
            
            // Verificar se existe campo de data
            $date_field = null;
            if (isset($produto['updated_at'])) {
                $date_field = $produto['updated_at'];
            } elseif (isset($produto['created_at'])) {
                $date_field = $produto['created_at'];
            } elseif (isset($produto['data_criacao'])) {
                $date_field = $produto['data_criacao'];
            }
            
            $lastmod = $xml->createElement('lastmod', formatDate($date_field));
            $changefreq = $xml->createElement('changefreq', getChangeFreq('product'));
            $priority = $xml->createElement('priority', getPriority('product'));
            
            $url->appendChild($loc);
            $url->appendChild($lastmod);
            $url->appendChild($changefreq);
            $url->appendChild($priority);
            
            // Adicionar imagem do produto se existir
            $image_field = null;
            if (isset($produto['foto_principal']) && !empty($produto['foto_principal'])) {
                $image_field = $produto['foto_principal'];
            } elseif (isset($produto['imagem']) && !empty($produto['imagem'])) {
                $image_field = $produto['imagem'];
            } elseif (isset($produto['foto']) && !empty($produto['foto'])) {
                $image_field = $produto['foto'];
            }
            
            if ($image_field) {
                $image = $xml->createElement('image:image');
                $image_loc = $xml->createElement('image:loc', xmlEscape($base_url . '/uploads/' . $image_field));
                $image_title = $xml->createElement('image:title', xmlEscape($produto['nome']));
                
                $description = '';
                if (isset($produto['descricao'])) {
                    $description = $produto['descricao'];
                } elseif (isset($produto['description'])) {
                    $description = $produto['description'];
                } else {
                    $description = $produto['nome'];
                }
                
                $image_caption = $xml->createElement('image:caption', xmlEscape($description));
                
                $image->appendChild($image_loc);
                $image->appendChild($image_title);
                $image->appendChild($image_caption);
                $url->appendChild($image);
            }
            
            $urlset->appendChild($url);
            $added_urls[] = $product_url;
        }
    }
    
    // 4. Adicionar páginas estáticas (se existirem)
    $static_pages = [
        '/sobre.php' => 'Sobre Nós',
        '/contato.php' => 'Contato',
        '/politica-privacidade.php' => 'Política de Privacidade',
        '/termos-uso.php' => 'Termos de Uso'
    ];
    
    foreach ($static_pages as $page_path => $page_title) {
        $page_url = $base_url . $page_path;
        
        // Verificar se o arquivo existe
        if (file_exists('.' . $page_path) && !in_array($page_url, $added_urls)) {
            $url = $xml->createElement('url');
            $loc = $xml->createElement('loc', xmlEscape($page_url));
            $lastmod = $xml->createElement('lastmod', formatDate(date('c', filemtime('.' . $page_path))));
            $changefreq = $xml->createElement('changefreq', getChangeFreq('page'));
            $priority = $xml->createElement('priority', getPriority('page'));
            
            $url->appendChild($loc);
            $url->appendChild($lastmod);
            $url->appendChild($changefreq);
            $url->appendChild($priority);
            $urlset->appendChild($url);
            
            $added_urls[] = $page_url;
        }
    }
    
    // Verificar se é uma requisição para visualizar ou salvar
    if (isset($_GET['save']) && $_GET['save'] === 'true') {
        // Salvar sitemap em arquivo
        $xml->save($sitemap_file);
        
        // Definir header para resposta de texto
        header('Content-Type: text/plain; charset=utf-8');
        
        // Retornar resposta JSON para AJAX
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Sitemap gerado com sucesso!',
                'file' => $sitemap_file,
                'urls_count' => count($added_urls),
                'last_updated' => date('d/m/Y H:i:s')
            ]);
        } else {
            echo "Sitemap gerado com sucesso em: " . $sitemap_file . "\n";
            echo "Total de URLs: " . count($added_urls) . "\n";
            echo "Última atualização: " . date('d/m/Y H:i:s') . "\n";
            echo "\nProdutos encontrados: " . count($produtos) . "\n";
            if (isset($categorias)) {
                echo "Categorias encontradas: " . count($categorias) . "\n";
            }
        }
    } else {
        // Definir header para XML
        header('Content-Type: application/xml; charset=utf-8');
        // Exibir XML diretamente
        echo $xml->saveXML();
    }
    
} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=utf-8');
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Erro no banco de dados: ' . $e->getMessage(),
            'message' => 'Não foi possível gerar o sitemap'
        ]);
    } else {
        echo "Erro ao gerar sitemap: Erro no banco de dados\n";
        echo "Detalhes: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Erro interno: ' . $e->getMessage(),
            'message' => 'Não foi possível gerar o sitemap'
        ]);
    } else {
        echo "Erro ao gerar sitemap: " . $e->getMessage() . "\n";
    }
}
?>

