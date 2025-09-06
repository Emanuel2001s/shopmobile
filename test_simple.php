<?php
// Teste simples para verificar se o PHP está funcionando
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste Simples PHP</h1>";
echo "<p>PHP está funcionando!</p>";
echo "<p>Versão do PHP: " . phpversion() . "</p>";
echo "<p>Data/Hora: " . date('Y-m-d H:i:s') . "</p>";

// Teste básico de variáveis
$teste = "Variável funcionando";
echo "<p>Teste de variável: {$teste}</p>";

// Teste de array
$array_teste = [1, 2, 3];
echo "<p>Teste de array: " . implode(', ', $array_teste) . "</p>";

// Teste de função
function teste_funcao() {
    return "Função funcionando";
}
echo "<p>Teste de função: " . teste_funcao() . "</p>";

echo "<p><strong>Se você está vendo esta mensagem, o PHP básico está funcionando!</strong></p>";
?>