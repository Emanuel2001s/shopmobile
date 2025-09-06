<?php
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'ID do slider não fornecido']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM sliders WHERE id = ?");
    $stmt->execute([$id]);
    $slider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($slider) {
        echo json_encode([
            'success' => true,
            'slider' => $slider
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Slider não encontrado'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar slider: ' . $e->getMessage()
    ]);
}
?> 