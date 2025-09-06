<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/auth.php';
require_once '../database/db_connect_env.php';

checkAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$cliente_id = $_POST['cliente_id'] ?? 0;
$nome_completo = trim($_POST['nome_completo'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');

// Validações
if (empty($cliente_id) || !is_numeric($cliente_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do cliente inválido']);
    exit;
}

if (empty($nome_completo)) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório']);
    exit;
}

if (empty($whatsapp)) {
    echo json_encode(['success' => false, 'message' => 'WhatsApp é obrigatório']);
    exit;
}

// Normalizar WhatsApp
function normalizarWhatsApp($whatsapp) {
    $numero = preg_replace('/[^0-9]/', '', $whatsapp);
    if (strlen($numero) > 11 && substr($numero, 0, 2) === '55') {
        $numero = substr($numero, 2);
    }
    return $numero;
}

$whatsapp_normalizado = normalizarWhatsApp($whatsapp);

if (strlen($whatsapp_normalizado) < 10) {
    echo json_encode(['success' => false, 'message' => 'WhatsApp inválido']);
    exit;
}

try {
    // Verificar se o cliente existe
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }

    // Verificar se já existe outro cliente com o mesmo WhatsApp
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE whatsapp = ? AND id != ?");
    $stmt->execute([$whatsapp, $cliente_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe um cliente com este WhatsApp']);
        exit;
    }

    // Atualizar o cliente
    $stmt = $conn->prepare("UPDATE clientes SET nome_completo = ?, whatsapp = ? WHERE id = ?");
    $result = $stmt->execute([$nome_completo, $whatsapp, $cliente_id]);

    if ($result) {
        // Atualizar também os pedidos relacionados
        $stmt = $conn->prepare("UPDATE pedidos SET nome_completo = ?, whatsapp = ? WHERE cliente_id = ?");
        $stmt->execute([$nome_completo, $whatsapp, $cliente_id]);

        echo json_encode(['success' => true, 'message' => 'Cliente atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar cliente']);
    }

} catch (PDOException $e) {
    error_log('Erro ao editar cliente: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
} catch (Exception $e) {
    error_log('Erro geral ao editar cliente: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?> 