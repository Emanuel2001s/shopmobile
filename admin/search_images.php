<?php
require_once '../includes/auth.php';
require_once '../database/db_connect.php';

checkAdminAuth();

// Verificar se é uma requisição AJAX (mais flexível)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // Se não tem o header, verificar se é uma requisição GET válida
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(403);
        exit('Acesso negado');
    }
}

$search = $_GET['q'] ?? '';
$response = ['success' => false, 'images' => []];

if (!empty($search)) {
    try {
        // DuckDuckGo precisa de um token vqd para buscar imagens
        $searchUrl = "https://duckduckgo.com/?q=" . urlencode($search) . "&iax=images&ia=images";
        
        // Usar cURL com User-Agent mais realista
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $searchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $searchHtml = curl_exec($ch);
        curl_close($ch);
        
        if (!preg_match('/vqd=([\\d-]+)/', $searchHtml, $matches)) {
            throw new Exception('Não foi possível obter o token vqd');
        }
        $vqd = $matches[1];

        // Agora busca as imagens via endpoint JSON da DuckDuckGo
        $apiUrl = "https://duckduckgo.com/i.js?l=pt-br&o=json&q=" . urlencode($search) . "&vqd=$vqd";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => [
                'Referer: https://duckduckgo.com/'
            ]
        ]);
        
        $json = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($json, true);
        if (!$data) {
            throw new Exception('Resposta inválida da API');
        }

        $images = [];
        if (!empty($data['results'])) {
            foreach ($data['results'] as $img) {
                $images[] = [
                    'url' => $img['image'],
                    'title' => $img['title'] ?? 'Imagem DuckDuckGo'
                ];
            }
        }

        if (!empty($images)) {
            $response['success'] = true;
            $response['images'] = $images;
        } else {
            // Fallback para placeholders se não encontrar imagens
            $response['success'] = true;
            $response['images'] = [
                [
                    'url' => 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#3498db"/><text x="150" y="100" font-family="Arial" font-size="16" fill="white" text-anchor="middle">' . htmlspecialchars($search) . '</text><text x="150" y="120" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Nenhuma imagem encontrada</text></svg>'),
                    'title' => 'Nenhuma imagem encontrada - ' . $search
                ],
                [
                    'url' => 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#e74c3c"/><text x="150" y="100" font-family="Arial" font-size="16" fill="white" text-anchor="middle">' . htmlspecialchars($search) . '</text><text x="150" y="120" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Placeholder Vermelho</text></svg>'),
                    'title' => 'Placeholder Vermelho - ' . $search
                ],
                [
                    'url' => 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#27ae60"/><text x="150" y="100" font-family="Arial" font-size="16" fill="white" text-anchor="middle">' . htmlspecialchars($search) . '</text><text x="150" y="120" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Placeholder Verde</text></svg>'),
                    'title' => 'Placeholder Verde - ' . $search
                ]
            ];
        }
        
    } catch (Exception $e) {
        // Fallback para placeholders em caso de erro
        $response['success'] = true;
        $response['images'] = [
            [
                'url' => 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#f39c12"/><text x="150" y="100" font-family="Arial" font-size="16" fill="white" text-anchor="middle">' . htmlspecialchars($search) . '</text><text x="150" y="120" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Erro: ' . htmlspecialchars($e->getMessage()) . '</text></svg>'),
                'title' => 'Erro - ' . $search
            ]
        ];
    }
}

// Retornar resposta em JSON
header('Content-Type: application/json');
echo json_encode($response);
?> 