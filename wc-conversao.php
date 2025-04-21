<?php

/**
 * Plugin Name: Taxa de Conversão
 * Description: Análises de taxa de conversão para seu e-commerce!
 * Version: 1.0.0
 * Author: Leonardo
 */

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/leoviajar/wc-conversao',
    __FILE__,
    'wc-conversao.php'
);

function adicionar_menu_analise_conversao() {
    add_menu_page(
        'Conversão',
        'Conversão', 
        'manage_options', 
        'analise_conversao', 
        'renderizar_pagina_analise', 
        'dashicons-chart-line',    
        3       
    );
}
add_action('admin_menu', 'adicionar_menu_analise_conversao');

function buscar_pedidos_do_dia($data) {
    $args = array(
        'limit'        => -1,
        'status'       => array('wc-completed', 'wc-processing', 'wc-on-hold'), // Adicione outros status conforme necessário
        'date_created' => $data . ' ... ' . $data,
        'return'       => 'ids',
    );

    $query = new WC_Order_Query($args);
    $orders = $query->get_orders();
    return count($orders);
}

function renderizar_pagina_analise() {
    $selected_date = isset($_GET['data']) ? $_GET['data'] : current_time('Y-m-d');
    $stats = get_option('analise_conversao_' . $selected_date, ['carrinhos' => 0, 'checkouts' => 0, 'pedidos' => 0]);

    // Atualiza a contagem de pedidos diretamente do banco de dados
    $stats['pedidos'] = buscar_pedidos_do_dia($selected_date);

    // Formulário para selecionar a data
    echo '<div class="wrap"><h1>Análises de Conversão</h1>
        <form method="get">
            <input type="hidden" name="page" value="analise_conversao"/>
            <input type="date" name="data" value="'. esc_attr($selected_date) .'"/>
            <input type="submit" value="Filtrar" class="button"/>
        </form>';

    // Exibir estatísticas
    $taxa_carrinho = $stats['carrinhos'] > 0 ? round(($stats['pedidos'] / $stats['carrinhos']) * 100, 2) : 0;
    $taxa_checkout = $stats['checkouts'] > 0 ? round(($stats['pedidos'] / $stats['checkouts']) * 100, 2) : 0;
    echo '<p>Carrinhos Iniciados: ' . $stats['carrinhos'] . '</p>
          <p>Checkouts Iniciados: ' . $stats['checkouts'] . '</p>
          <p>Pedidos Concluídos: ' . $stats['pedidos'] . '</p>
          <p>Taxa de Conversão de Carrinho: ' . $taxa_carrinho . '%</p>
          <p>Taxa de Conversão de Checkout: ' . $taxa_checkout . '%</p>
        </div>';
}

function registrar_inicio_carrinho($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    $today = current_time('Y-m-d');
    $stats = get_option('analise_conversao_' . $today, ['carrinhos' => 0, 'checkouts' => 0, 'pedidos' => 0]);
    $stats['carrinhos']++;
    update_option('analise_conversao_' . $today, $stats);
}
add_action('woocommerce_add_to_cart', 'registrar_inicio_carrinho', 10, 6);

function registrar_inicio_checkout() {
    $today = current_time('Y-m-d');
    $stats = get_option('analise_conversao_' . $today, ['carrinhos' => 0, 'checkouts' => 0, 'pedidos' => 0]);
    $stats['checkouts']++;
    update_option('analise_conversao_' . $today, $stats);
}
add_action('woocommerce_before_checkout_form', 'registrar_inicio_checkout');

function registrar_pedido_gerado($order_id, $posted_data, $order) {
    $today = current_time('Y-m-d');
    $stats = get_option('analise_conversao_' . $today, ['carrinhos' => 0, 'checkouts' => 0, 'pedidos' => 0]);
    $stats['pedidos']++;
    update_option('analise_conversao_' . $today, $stats);
}
add_action('woocommerce_checkout_order_processed', 'registrar_pedido_gerado', 10, 3);