<?php

return [
    'base_url' => env('TPSOFTWARE_BASE_URL', 'https://api.tp.software/api/v1'),

    /*
     * Token gerado no painel Super Admin (TP Software).
     * Nota: alguns endpoints usam query param `tokens=...` nos exemplos.
     */
    'token' => env('TPSOFTWARE_TOKEN'),
    'token_param' => env('TPSOFTWARE_TOKEN_PARAM', 'tokens'),

    /*
     * Alguns ambientes também aceitam token no header Authorization.
     * Quando ativo: Authorization: Bearer <token>
     */
    'use_auth_header' => env('TPSOFTWARE_USE_AUTH_HEADER', false),

    'timeout_seconds' => (int) env('TPSOFTWARE_TIMEOUT_SECONDS', 15),
    'retries' => (int) env('TPSOFTWARE_RETRIES', 1),
    'retry_sleep_ms' => (int) env('TPSOFTWARE_RETRY_SLEEP_MS', 250),

    'cache_store' => env('TPSOFTWARE_CACHE_STORE', 'file'),
    'cache_ttl_seconds' => (int) env('TPSOFTWARE_CACHE_TTL_SECONDS', 600),

    'catalog' => [
        'language' => env('TPSOFTWARE_LANGUAGE', 'pt'),
        'order_field' => env('TPSOFTWARE_ORDER_FIELD', 'created_at'),
        'order_type' => env('TPSOFTWARE_ORDER_TYPE', 'asc'),

        // Scan de categorias (para descobrir marcas usadas sem BD)
        'category_scan_limit' => (int) env('TPSOFTWARE_CATEGORY_SCAN_LIMIT', 200),
        'category_scan_max_pages' => (int) env('TPSOFTWARE_CATEGORY_SCAN_MAX_PAGES', 50),
        'category_scan_max_seconds' => (int) env('TPSOFTWARE_CATEGORY_SCAN_MAX_SECONDS', 20),

        // Índice local (cache) para permitir filtros/paginação por marca/modelo sem BD.
        'index_enabled' => env('TPSOFTWARE_INDEX_ENABLED', true),
        'index_page_size' => (int) env('TPSOFTWARE_INDEX_PAGE_SIZE', 200),
        // TTL aplica-se aos caches derivados; o índice em si é persistido em ficheiro.
        'index_ttl_seconds' => (int) env('TPSOFTWARE_INDEX_TTL_SECONDS', 1800),
        'index_path' => env('TPSOFTWARE_INDEX_PATH', storage_path('app/tpsoftware/index.json')),
        'index_meta_path' => env('TPSOFTWARE_INDEX_META_PATH', storage_path('app/tpsoftware/index.meta.json')),
        'price_fallback_enabled' => env('TPSOFTWARE_PRICE_FALLBACK_ENABLED', true),
        'price_fallback_max_lookups' => (int) env('TPSOFTWARE_PRICE_FALLBACK_MAX_LOOKUPS', 12),

        // Campo usado como "categoria" na vitrine (por defeito marca do veículo)
        'category_field' => env('TPSOFTWARE_CATEGORY_FIELD', 'vehicle_make_name'),

        // Campo usado para "modelo" (filtro opcional)
        'model_field' => env('TPSOFTWARE_MODEL_FIELD', 'vehicle_model_name'),
    ],
];

