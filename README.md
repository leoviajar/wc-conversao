# Plugin de Análise de Conversão para WooCommerce

Este plugin personalizado para WordPress + WooCommerce permite acompanhar e analisar a taxa de conversão da sua loja virtual. Ele monitora eventos como adição ao carrinho, início de checkout e finalização de pedidos, gerando um painel simples de acompanhamento por data no painel administrativo do WordPress.

## 📊 Funcionalidades

- Criação de um menu no admin com a página “Conversão”

- Contagem diária de:
  - Carrinhos iniciados
  - Checkouts iniciados
  - Pedidos concluídos
  
- Filtro por data para visualizar estatísticas específicas

## 🚀 Como Funciona

O plugin utiliza *hooks* nativos do WooCommerce para registrar os eventos:
- `woocommerce_add_to_cart`: Registra um novo carrinho
- `woocommerce_before_checkout_form`: Registra um novo início de checkout
- `woocommerce_checkout_order_processed`: Registra um novo pedido finalizado

Os dados são salvos na tabela `wp_options`, com a chave `analise_conversao_YYYY-MM-DD`.