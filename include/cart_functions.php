<?php

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inicializa o carrinho se ele não existir na sessão
function initialize_cart() {
    if (!isset($_SESSION["cart"])) {
        $_SESSION["cart"] = [];
    }
}

// Adiciona um produto ao carrinho
function add_to_cart($product_id, $name, $price, $quantity = 1, $image = null) {
    initialize_cart();

    if (isset($_SESSION["cart"][$product_id])) {
        // Se o produto já existe, apenas atualiza a quantidade
        $_SESSION["cart"][$product_id]["quantity"] += $quantity;
    } else {
        // Adiciona o novo produto
        $_SESSION["cart"][$product_id] = [
            "id" => $product_id,
            "name" => $name,
            "price" => $price,
            "quantity" => $quantity,
            "image" => $image
        ];
    }
}

// Remove um produto do carrinho
function remove_from_cart($product_id) {
    initialize_cart();
    if (isset($_SESSION["cart"][$product_id])) {
        unset($_SESSION["cart"][$product_id]);
    }
}

// Atualiza a quantidade de um produto no carrinho
function update_cart_quantity($product_id, $quantity) {
    initialize_cart();
    if (isset($_SESSION["cart"][$product_id])) {
        if ($quantity <= 0) {
            remove_from_cart($product_id);
        } else {
            $_SESSION["cart"][$product_id]["quantity"] = $quantity;
        }
    }
}

// Retorna todos os itens do carrinho
function get_cart_items() {
    initialize_cart();
    return $_SESSION["cart"];
}

// Calcula o total do carrinho
function get_cart_total() {
    initialize_cart();
    $total = 0;
    foreach ($_SESSION["cart"] as $item) {
        $total += $item["price"] * $item["quantity"];
    }
    return $total;
}

// Retorna o número total de itens no carrinho
function get_cart_item_count() {
    initialize_cart();
    $count = 0;
    foreach ($_SESSION["cart"] as $item) {
        $count += $item["quantity"];
    }
    return $count;
}

// Limpa o carrinho
function clear_cart() {
    $_SESSION["cart"] = [];
}

?>


