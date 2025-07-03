<?php
/**
 * Plugin Name: Taxa de Conversão
 * Description: Análises de taxa de conversão para seu e-commerce!
 * Version: 1.3.0
 * Author: Leonardo
 */

require "plugin-update-checker/plugin-update-checker.php";
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker("https://github.com/leoviajar/wc-conversao", __FILE__, "wc-conversao.php" );

/**
 * Adiciona a página de menu para Análise de Conversão.
 */
function adicionar_menu_analise_conversao()
{
    add_menu_page("Conversão", "Conversão", "manage_options", "analise_conversao", "renderizar_pagina_analise", "dashicons-chart-line", 3);
}
add_action("admin_menu", "adicionar_menu_analise_conversao");

/**
 * Função auxiliar para obter as estatísticas diárias com valores padrão.
 * Garante que todos os campos necessários estejam presentes.
 *
 * @param string $date A data no formato 'YYYY-MM-DD'.
 * @return array As estatísticas para a data especificada.
 */
function wc_conversao_get_daily_stats_option($date) {
    return get_option("analise_conversao_" . $date, [
        "carrinhos" => 0,
        "checkouts" => 0,
        "pedidos" => 0,
        "visitantes_produto" => 0,
    ]);
}

/**
 * Busca o número de pedidos concluídos para uma data específica.
 *
 * @param string $data A data no formato 'YYYY-MM-DD'.
 * @return int O número de pedidos concluídos.
 */
function buscar_pedidos_do_dia($data)
{
    $args = [
        "limit" => - 1,
        "status" => ["wc-completed", "wc-processing", "wc-on-hold"],
        "date_created" => $data . " ... " . $data,
        "return" => "ids",
    ];

    $query = new WC_Order_Query($args);
    $orders = $query->get_orders();
    return count($orders);
}

/**
 * Renderiza a página de Análise de Conversão no painel administrativo.
 */
function renderizar_pagina_analise()
{
    $selected_date = isset($_GET["data"]) ? $_GET["data"] : current_time("Y-m-d");
    $stats = wc_conversao_get_daily_stats_option($selected_date);

    // Atualiza a contagem de pedidos diretamente do banco de dados para a data selecionada
    $stats["pedidos"] = buscar_pedidos_do_dia($selected_date);

    // Formulário para selecionar a data
    echo '<div class="wrap"><h1>Análises de Conversão</h1>
        <form method="get">
            <input type="hidden" name="page" value="analise_conversao"/>
            <input type="date" name="data" value="' . esc_attr($selected_date) . '"/>
            <input type="submit" value="Filtrar" class="button"/>
        </form>';

    // Exibir estatísticas
    $taxa_carrinho = $stats["carrinhos"] > 0 ? round(($stats["pedidos"] / $stats["carrinhos"]) * 100, 2) : 0;
    $taxa_checkout = $stats["checkouts"] > 0 ? round(($stats["pedidos"] / $stats["checkouts"]) * 100, 2) : 0;
    $taxa_conversao_site = $stats["visitantes_produto"] > 0 ? round(($stats["pedidos"] / $stats["visitantes_produto"]) * 100, 2) : 0;

    echo "<p>Visitantes Únicos de Produto: <strong>" . $stats["visitantes_produto"] . '</strong></p>
          <p>Carrinhos Iniciados: <strong>' . $stats["carrinhos"] . '</strong></p>
          <p>Checkouts Iniciados: <strong>' . $stats["checkouts"] . '</strong></p>
          <p>Pedidos Concluídos: <strong>' . $stats["pedidos"] . '</strong></p>
          <p>Taxa de Conversão do Site: <strong>' . $taxa_conversao_site . '%</strong></p>
          <p>Taxa de Conversão de Carrinho: <strong>' . $taxa_carrinho . '%</strong></p>
          <p>Taxa de Conversão de Checkout: <strong>' . $taxa_checkout . '%</strong></p>
        </div>';
}

/**
 * Registra o início de um carrinho (item adicionado ao carrinho) de forma única por sessão/dia.
 */
function registrar_inicio_carrinho($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
{
    // Verifica se a sessão do WooCommerce está disponível
    if ( ! WC()->session ) {
        return;
    }

    $today = current_time("Y-m-d");
    $session_key = 'wc_conversao_cart_added_' . $today;

    // Verifica se esta sessão já registrou uma adição ao carrinho para hoje
    if ( WC()->session->get( $session_key ) ) {
        return;
    }

    $stats = wc_conversao_get_daily_stats_option($today);
    $stats["carrinhos"]++;
    update_option("analise_conversao_" . $today, $stats);

    // Marca esta sessão como tendo registrado uma adição ao carrinho para hoje
    WC()->session->set( $session_key, true );
}
add_action("woocommerce_add_to_cart", "registrar_inicio_carrinho", 10, 6);

/**
 * Registra o início de um checkout de forma única por sessão/dia.
 */
function registrar_inicio_checkout()
{
    // Verifica se a sessão do WooCommerce está disponível
    if ( ! WC()->session ) {
        return;
    }

    $today = current_time("Y-m-d");
    $session_key = 'wc_conversao_checkout_initiated_' . $today;

    // Verifica se esta sessão já registrou um início de checkout para hoje
    if ( WC()->session->get( $session_key ) ) {
        return;
    }

    $stats = wc_conversao_get_daily_stats_option($today);
    $stats["checkouts"]++;
    update_option("analise_conversao_" . $today, $stats);

    // Marca esta sessão como tendo registrado um início de checkout para hoje
    WC()->session->set( $session_key, true );
}
add_action("woocommerce_before_checkout_form", "registrar_inicio_checkout");

/**
 * Registra um pedido concluído.
 */
function registrar_pedido_gerado($order_id, $posted_data, $order)
{
    $today = current_time("Y-m-d");
    $stats = wc_conversao_get_daily_stats_option($today);
    $stats["pedidos"]++;
    update_option("analise_conversao_" . $today, $stats);
}
add_action("woocommerce_checkout_order_processed", "registrar_pedido_gerado", 10, 3);


/*
 * FUNÇÕES PARA O WIDGET DO DASHBOARD
 */

/**
 * Adiciona o widget de Análise de Conversão ao Dashboard do WordPress.
 */
function wc_conversao_add_dashboard_widgets() {
    wp_add_dashboard_widget(
        'wc_conversao_dashboard_widget',
        'Análises de Conversão (Hoje)',
        'wc_conversao_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'wc_conversao_add_dashboard_widgets');

/**
 * Renderiza o conteúdo do widget de Análise de Conversão no Dashboard.
 */
function wc_conversao_dashboard_widget_content() {
    $today = current_time("Y-m-d");
    $stats = wc_conversao_get_daily_stats_option($today);

    // Garante que a contagem de pedidos no widget seja precisa, buscando diretamente do banco de dados para hoje
    $stats["pedidos"] = buscar_pedidos_do_dia($today);

    $taxa_carrinho = $stats["carrinhos"] > 0 ? round(($stats["pedidos"] / $stats["carrinhos"]) * 100, 2) : 0;
    $taxa_checkout = $stats["checkouts"] > 0 ? round(($stats["pedidos"] / $stats["checkouts"]) * 100, 2) : 0;
    $taxa_conversao_site = $stats["visitantes_produto"] > 0 ? round(($stats["pedidos"] / $stats["visitantes_produto"]) * 100, 2) : 0;

    echo '<div class="wc-conversao-dashboard-widget">';
    echo '<p><strong>Visitantes Únicos de Produto:</strong> ' . $stats["visitantes_produto"] . '</p>';
    echo '<p><strong>Carrinhos Iniciados:</strong> ' . $stats["carrinhos"] . '</p>';
    echo '<p><strong>Checkouts Iniciados:</strong> ' . $stats["checkouts"] . '</p>';
    echo '<p><strong>Pedidos Concluídos:</strong> ' . $stats["pedidos"] . '</p>';
    echo '<p><strong>Taxa de Conversão do Site:</strong> ' . $taxa_conversao_site . '%</p>';
    echo '<p><strong>Taxa de Conversão de Carrinho:</strong> ' . $taxa_carrinho . '%</p>';
    echo '<p><strong>Taxa de Conversão de Checkout:</strong> ' . $taxa_checkout . '%</p>';
    echo '</div>';
}

/*
 * LÓGICA PARA CONTAR VISITANTES ÚNICOS DE PRODUTO VIA AJAX (PARA CONTORNAR CACHE)
 */

/**
 * Adiciona o script JavaScript inline para a contagem de visitantes de produto.
 */
function wc_conversao_add_inline_product_visitor_script() {
    // Apenas adiciona o script em páginas de produto
    if ( is_product() ) {
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'wc_conversao_product_visitor_nonce' );
        $today    = current_time( 'Y-m-d' );

        $script = "
            function getCookie(name) {
                var nameEQ = name + '=';
                var ca = document.cookie.split(';');
                for(var i=0;i < ca.length;i++) {
                    var c = ca[i];
                    while (c.charAt(0)==' ') c = c.substring(1,c.length);
                    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
                }
                return null;
            }

            function setCookie(name, value, days) {
                var expires = '';
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = '; expires=' + date.toUTCString();
                }
                document.cookie = name + '=' + value + expires + '; path=/';
            }

            function generateUUID() {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            }

            jQuery(document).ready(function($) {
                var wc_conversao_ajax_product = {
                    ajax_url: '" . esc_url( $ajax_url ) . "',
                    nonce: '" . esc_attr( $nonce ) . "',
                    today: '" . esc_attr( $today ) . "'
                };

                var visitorCookieName = 'wc_conv_vid';
                var visitorCookie = getCookie(visitorCookieName);
                var visitorId = null;
                var lastCountedDate = null;

                if (visitorCookie) {
                    var parts = visitorCookie.split('|');
                    if (parts.length === 2) {
                        visitorId = parts[0];
                        lastCountedDate = parts[1];
                    }
                }

                // Se não tem ID ou a data é antiga, gera um novo ID ou atualiza a data
                if (!visitorId || lastCountedDate !== wc_conversao_ajax_product.today) {
                    if (!visitorId) {
                        visitorId = generateUUID();
                    }
                    // Define o cookie para expirar no final do dia atual
                    var endOfDay = new Date();
                    endOfDay.setHours(23, 59, 59, 999);
                    var expiresToday = (endOfDay.getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24); // Dias restantes até o fim do dia
                    setCookie(visitorCookieName, visitorId + '|' + wc_conversao_ajax_product.today, expiresToday);

                    // Faz a chamada AJAX para o backend
                    $.ajax({
                        url: wc_conversao_ajax_product.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wc_conversao_register_product_visitor',
                            nonce: wc_conversao_ajax_product.nonce,
                            visitor_id: visitorId // Envia o ID único do visitante
                        },
                        success: function(response) {
                            // console.log('Resposta do servidor:', response);
                        },
                        error: function(xhr, status, error) {
                            // console.error('Erro na requisição AJAX:', status, error);
                        }
                    });
                } else {
                    // console.log('Visitante de produto já contado para hoje por este navegador.');
                }
            });
        ";

        wp_enqueue_script( 'jquery' );
        wp_add_inline_script( 'jquery', $script );
    }
}
add_action( 'wp_enqueue_scripts', 'wc_conversao_add_inline_product_visitor_script' );

/**
 * Endpoint AJAX para registrar um visitante único de produto.
 */
function wc_conversao_register_product_visitor_ajax() {
    // Verifica o nonce para segurança
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wc_conversao_product_visitor_nonce' ) ) {
        wp_send_json_error( 'Nonce inválido!' );
    }

    // Verifica se o WooCommerce está ativo e a sessão disponível
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        wp_send_json_error( 'WooCommerce ou sessão não disponível.' );
    }

    $visitor_id = isset($_POST['visitor_id']) ? sanitize_text_field($_POST['visitor_id']) : '';
    if ( empty($visitor_id) ) {
        wp_send_json_error( 'ID do visitante ausente.' );
    }

    $today = current_time("Y-m-d");
    $counted_ids_option_name = 'wc_conversao_product_visitors_ids_' . $today;
    $counted_ids = get_option($counted_ids_option_name, []);

    // Verifica se o ID do visitante já foi contado para hoje no banco de dados
    if ( in_array($visitor_id, $counted_ids) ) {
        wp_send_json_success( 'Já contado no banco de dados.' );
    }

    // Se não foi contado, adiciona o ID e incrementa a contagem
    $counted_ids[] = $visitor_id;
    update_option($counted_ids_option_name, $counted_ids); // Salva a lista de IDs contados

    $stats = wc_conversao_get_daily_stats_option($today);
    $stats["visitantes_produto"]++;
    update_option("analise_conversao_" . $today, $stats); // Incrementa a contagem total

    wp_send_json_success( 'Visitante de produto contado com sucesso!' );
}
// Hook para usuários logados
add_action( 'wp_ajax_wc_conversao_register_product_visitor', 'wc_conversao_register_product_visitor_ajax' );
// Hook para usuários não logados
add_action( 'wp_ajax_nopriv_wc_conversao_register_product_visitor', 'wc_conversao_register_product_visitor_ajax' );


/**
 * Busca os produtos mais vendidos.
 *
 * @param int $limit O número máximo de produtos a retornar.
 * @return array Uma lista de objetos de produto WC_Product.
 */
function wc_conversao_get_top_selling_products($limit = 10) {
    $args = array(
        'post_type' => 'product',
        'meta_key' => 'total_sales',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'posts_per_page' => $limit,
    );
    $products = wc_get_products($args);
    return $products;
}

/**
 * Adiciona o widget de Top 10 Produtos Mais Vendidos ao Dashboard do WordPress.
 */
function wc_conversao_add_top_selling_products_widget() {
    wp_add_dashboard_widget(
        'wc_conversao_top_selling_products_widget',
        'Top 10 Produtos Mais Vendidos',
        'wc_conversao_top_selling_products_widget_content'
    );
}
add_action('wp_dashboard_setup', 'wc_conversao_add_top_selling_products_widget');

/**
 * Renderiza o conteúdo do widget de Top 10 Produtos Mais Vendidos no Dashboard.
 */
function wc_conversao_top_selling_products_widget_content() {
    $products = wc_conversao_get_top_selling_products();

    if (empty($products)) {
        echo '<p>Nenhum produto mais vendido encontrado.</p>';
        return;
    }

    echo '<div class="wc-conversao-dashboard-widget">';
    echo '<ol>';
    foreach ($products as $product) {
        echo '<li>' . esc_html($product->get_name()) . ' (' . esc_html($product->get_total_sales()) . ' vendas)</li>';
    }
    echo '</ol>';
    echo '</div>';
}




/**
 * Busca as principais localizações (país, estado, cidade) dos pedidos.
 *
 * @param int $limit O número máximo de localizações a retornar.
 * @return array Um array associativo com as localizações e suas contagens.
 */
function wc_conversao_get_top_locations($limit = 10) {
    $locations = [];
    $first_day_of_month = date('Y-m-01 00:00:00');
    $last_day_of_month = date('Y-m-t 23:59:59');

    $args = [
        'limit' => -1,
        'status' => ['wc-completed', 'wc-processing', 'wc-on-hold'],
        'return' => 'ids',
        'date_created' => $first_day_of_month . '...' . $last_day_of_month,
    ];

    $query = new WC_Order_Query($args);
    $order_ids = $query->get_orders();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $country = $order->get_billing_country();
            $state = $order->get_billing_state();
            $city = $order->get_billing_city();

            if (!empty($country) && !empty($state)) {
                $location_key = implode(", ", [$state, $country]);
                if (!isset($locations[$location_key])) {
                    $locations[$location_key] = 0;
                }
                $locations[$location_key]++;
            }
        }
    }

    arsort($locations);
    return array_slice($locations, 0, $limit, true);
}

/**
 * Adiciona o widget de Principais Localizações ao Dashboard do WordPress.
 */
function wc_conversao_add_top_locations_widget() {
    wp_add_dashboard_widget(
        'wc_conversao_top_locations_widget',
        'Principais Localizações de Pedidos',
        'wc_conversao_top_locations_widget_content'
    );
}
add_action('wp_dashboard_setup', 'wc_conversao_add_top_locations_widget');

/**
 * Renderiza o conteúdo do widget de Principais Localizações no Dashboard.
 */
function wc_conversao_top_locations_widget_content() {
    $locations = wc_conversao_get_top_locations();

    if (empty($locations)) {
        echo '<p>Nenhuma localização de pedido encontrada.</p>';
        return;
    }

    echo '<div class="wc-conversao-dashboard-widget">';
    echo '<ol>';
    foreach ($locations as $location => $count) {
        echo '<li>' . esc_html($location) . ' (' . esc_html($count) . ' pedidos)</li>';
    }
    echo '</ol>';
    echo '</div>';
}




/**
 * Registra a visualização de um produto.
 *
 * @param int $product_id O ID do produto visualizado.
 */
function wc_conversao_track_product_views($product_id) {
    if (is_singular("product")) {
        $views = (int) get_post_meta($product_id, "_wc_conversao_product_views_count", true);
        $views++;
        update_post_meta($product_id, "_wc_conversao_product_views_count", $views);
    }
}
add_action("template_redirect", function() {
    if (is_singular("product")) {
        global $post;
        wc_conversao_track_product_views($post->ID);
    }
});

/**
 * Busca os produtos mais visitados.
 *
 * @param int $limit O número máximo de produtos a retornar.
 * @return array Uma lista de objetos de produto WC_Product.
 */
function wc_conversao_get_most_viewed_products($limit = 10) {
    $args = array(
        "post_type" => "product",
        "meta_key" => "_wc_conversao_product_views_count",
        "orderby" => "meta_value_num",
        "order" => "DESC",
        "posts_per_page" => $limit,
        "meta_query" => array(
            array(
                "key" => "_wc_conversao_product_views_count",
                "value" => "0",
                "compare" => ">=",
                "type" => "NUMERIC"
            )
        )
    );
    $products = new WP_Query($args);
    return $products->posts;
}

/**
 * Adiciona o widget de Principais Produtos Visitados ao Dashboard do WordPress.
 */
function wc_conversao_add_most_viewed_products_widget() {
    wp_add_dashboard_widget(
        "wc_conversao_most_viewed_products_widget",
        "Principais Produtos Visitados",
        "wc_conversao_most_viewed_products_widget_content"
    );
}
add_action("wp_dashboard_setup", "wc_conversao_add_most_viewed_products_widget");

/**
 * Renderiza o conteúdo do widget de Principais Produtos Visitados no Dashboard.
 */
function wc_conversao_most_viewed_products_widget_content() {
    $products = wc_conversao_get_most_viewed_products();

    if (empty($products)) {
        echo "<p>Nenhum produto visitado encontrado.</p>";
        return;
    }

    echo "<div class=\"wc-conversao-dashboard-widget\">";
    echo "<ol>";
    foreach ($products as $product) {
        $product_obj = wc_get_product($product->ID);
        $views = (int) get_post_meta($product->ID, "_wc_conversao_product_views_count", true);
        echo "<li>" . esc_html($product_obj->get_name()) . " (" . esc_html($views) . " visualizações)</li>";
    }
    echo "</ol>";
    echo "</div>";
}




/*
 * FUNÇÕES PARA RASTREAMENTO DE LOCALIZAÇÃO POR IP
 */

/**
 * Obtém a localização do usuário baseada no IP.
 *
 * @param string $ip O endereço IP do usuário.
 * @return array|false Array com dados de localização ou false em caso de erro.
 */
function wc_conversao_get_location_by_ip($ip) {
    // Verifica se é um IP válido
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }

    // Cache da localização por 24 horas
    $cache_key = 'wc_conversao_location_' . md5($ip);
    $cached_location = get_transient($cache_key);
    
    if ($cached_location !== false) {
        return $cached_location;
    }

    // Faz a requisição para a API de geolocalização
    $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city", array(
        'timeout' => 10,
        'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || $data['status'] !== 'success') {
        return false;
    }

    $location_data = array(
        'country' => $data['country'],
        'state' => $data['regionName'],
        'city' => $data['city']
    );

    // Cache por 24 horas
    set_transient($cache_key, $location_data, 24 * HOUR_IN_SECONDS);

    return $location_data;
}

/**
 * Obtém o IP real do usuário considerando proxies e CDNs.
 *
 * @return string O endereço IP do usuário.
 */
function wc_conversao_get_user_ip() {
    $ip_keys = array(
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    );

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Registra a visita de um local baseado no IP do usuário.
 */
function wc_conversao_track_location_visit() {
    // Só executa no frontend
    if (is_admin()) {
        return;
    }

    $user_ip = wc_conversao_get_user_ip();
    $location = wc_conversao_get_location_by_ip($user_ip);

    if (!$location) {
        return;
    }

    $today = current_time('Y-m-d');
    $location_key = $location['state'] . ', ' . $location['country'];
    
    // Verifica se já foi contado hoje para este IP
    $counted_ips_option = 'wc_conversao_location_ips_' . $today;
    $counted_ips = get_option($counted_ips_option, array());
    
    if (in_array($user_ip, $counted_ips)) {
        return;
    }

    // Adiciona o IP à lista de IPs contados
    $counted_ips[] = $user_ip;
    update_option($counted_ips_option, $counted_ips);

    // Atualiza a contagem de visitas por localização
    $locations_option = 'wc_conversao_locations_' . $today;
    $locations_data = get_option($locations_option, array());
    
    if (!isset($locations_data[$location_key])) {
        $locations_data[$location_key] = 0;
    }
    
    $locations_data[$location_key]++;
    update_option($locations_option, $locations_data);
}
add_action('wp', 'wc_conversao_track_location_visit');

/**
 * Busca as principais localizações visitadas.
 *
 * @param int $limit O número máximo de localizações a retornar.
 * @param string $date A data para buscar (formato Y-m-d). Se não informada, usa hoje.
 * @return array Um array associativo com as localizações e suas contagens.
 */
function wc_conversao_get_top_visited_locations($limit = 10, $date = null) {
    if (!$date) {
        $date = current_time('Y-m-d');
    }

    $locations_option = 'wc_conversao_locations_' . $date;
    $locations_data = get_option($locations_option, array());

    if (empty($locations_data)) {
        return array();
    }

    // Ordena por número de visitas (decrescente)
    arsort($locations_data);

    // Retorna apenas o número limitado de resultados
    return array_slice($locations_data, 0, $limit, true);
}

/**
 * Adiciona o widget de Principais Locais Visitados ao Dashboard do WordPress.
 */
function wc_conversao_add_top_visited_locations_widget() {
    wp_add_dashboard_widget(
        'wc_conversao_top_visited_locations_widget',
        'Principais Locais Visitados (Hoje)',
        'wc_conversao_top_visited_locations_widget_content'
    );
}
add_action('wp_dashboard_setup', 'wc_conversao_add_top_visited_locations_widget');

/**
 * Renderiza o conteúdo do widget de Principais Locais Visitados no Dashboard.
 */
function wc_conversao_top_visited_locations_widget_content() {
    $locations = wc_conversao_get_top_visited_locations();

    if (empty($locations)) {
        echo '<p>Nenhuma localização visitada encontrada para hoje.</p>';
        return;
    }

    echo '<div class="wc-conversao-dashboard-widget">';
    echo '<ol>';
    foreach ($locations as $location => $count) {
        echo '<li>' . esc_html($location) . ' (' . esc_html($count) . ' visitas)</li>';
    }
    echo '</ol>';
    echo '</div>';
}

