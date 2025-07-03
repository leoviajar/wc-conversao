# Plugin de Análise de Conversão para WooCommerce

Este plugin personalizado para WordPress + WooCommerce permite acompanhar e analisar a taxa de conversão da sua loja virtual. Ele monitora eventos como adição ao carrinho, início de checkout e finalização de pedidos, gerando um painel simples de acompanhamento por data no painel administrativo do WordPress.

## 📊 Funcionalidades

- **Dashboard Widget:** Visualize um resumo das estatísticas de conversão do dia diretamente no painel principal do WordPress.

- **Página de Análise Detalhada:** Acesse um menu dedicado no admin com a página “Conversão” para:
  - Filtrar e visualizar estatísticas por data.
  - Acompanhar a contagem diária de:
    - **Visitantes Únicos de Páginas de Produto** (contagem robusta contra cache e múltiplas abas/janelas anônimas).
    - **Carrinhos Iniciados** (contagem única por sessão/dia).
    - **Checkouts Iniciados** (contagem única por sessão/dia).
    - Pedidos Concluídos.
  - Calcular e exibir as seguintes taxas de conversão:
    - **Taxa de Conversão do Site** (Pedidos Concluídos / Visitantes Únicos de Produto).
    - Taxa de Conversão de Carrinho (Pedidos Concluídos / Carrinhos Iniciados).
    - Taxa de Conversão de Checkout (Pedidos Concluídos / Checkouts Iniciados).
  - Widgets no painel admin:
    - Análises de conversão (Hoje)
    - Principais locais visitados (Hoje)
    - Principais produtos visitados
    - Top 10 produtos mais vendidos
    - Principais localizações de pedidos

## 🚀 Como Funciona

O plugin utiliza *hooks* nativos do WooCommerce para registrar os eventos:
- `woocommerce_add_to_cart`: Registra um novo carrinho
- `woocommerce_before_checkout_form`: Registra um novo início de checkout
- `woocommerce_checkout_order_processed`: Registra um novo pedido finalizado

Os dados são salvos na tabela `wp_options`, com a chave `analise_conversao_YYYY-MM-DD`.