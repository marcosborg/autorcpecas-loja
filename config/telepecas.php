<?php

return [
    'base_url' => env('TELEPECAS_BASE_URL', 'https://api.telepecas.com'),

    // Cache store dedicado (evita gravar payloads grandes na cache por DB do Laravel).
    'cache_store' => env('TELEPECAS_CACHE_STORE', 'file'),

    /*
     * Driver de autenticação:
     * - oauth2: docs oficiais (Bearer token via /auth/token)
     * - basic_token_body: manual do módulo (Basic fixo + token no body)
     */
    'auth_driver' => env('TELEPECAS_AUTH_DRIVER', 'basic_token_body'),

    /*
     * Manual do módulo (basic_token_body):
     * - Header: Authorization: Basic {BASIC_AUTH_TOKEN}
     * - Body: {"token":"TELEPECAS_PUBLIC_KEY"} em todas as requests JSON
     */
    'basic_auth_token' => env('TELEPECAS_BASIC_AUTH_TOKEN'),
    'public_key' => env('TELEPECAS_PUBLIC_KEY'),

    /*
     * Docs oficiais (oauth2):
     * - /auth/token com Basic base64(client_id:client_secret)
     * - Endpoints com Authorization: Bearer {access_token}
     */
    'client_id' => env('TELEPECAS_CLIENT_ID'),
    'client_secret' => env('TELEPECAS_CLIENT_SECRET'),
    // Alternativa: se só tiveres o header Basic já em base64 (sem "Basic "), coloca aqui.
    'oauth_basic_auth' => env('TELEPECAS_OAUTH_BASIC_AUTH'),
    'seller_token' => env('TELEPECAS_SELLER_TOKEN'),
    'seller_token_param' => env('TELEPECAS_SELLER_TOKEN_PARAM', 'token'),

    'timeout_seconds' => (int) env('TELEPECAS_TIMEOUT_SECONDS', 15),
    'retries' => (int) env('TELEPECAS_RETRIES', 1),
    'retry_sleep_ms' => (int) env('TELEPECAS_RETRY_SLEEP_MS', 250),

    'cache_enabled' => env('TELEPECAS_CACHE_ENABLED', true),
    'cache_ttl_seconds' => (int) env('TELEPECAS_CACHE_TTL_SECONDS', 600),

    'catalog' => [
        // "stock" (derivar do payload do stock) | "makes" (catalog/makes/getMakes)
        'categories_source' => env('TELEPECAS_CATEGORIES_SOURCE', 'stock'),
        // Cache do índice do catálogo (pode ser diferente do cache geral).
        'cache_store' => env('TELEPECAS_CATALOG_CACHE_STORE', env('TELEPECAS_CACHE_STORE', 'file')),
        'index_cache_ttl_seconds' => (int) env('TELEPECAS_CATALOG_INDEX_TTL_SECONDS', 600),
        'page_size' => (int) env('TELEPECAS_CATALOG_PAGE_SIZE', 100),
        'max_items' => (int) env('TELEPECAS_CATALOG_MAX_ITEMS', 2000),
        'category_paths' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TELEPECAS_CATEGORY_PATHS', 'partInfo.partGroup,partInfo.group,category,partInfo.partLocal')),
        ))),
        'make_id_paths' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TELEPECAS_MAKE_ID_PATHS', 'vehicleInfo.externalMakeId,vehicleInfo.makeId,makeId,externalMakeId')),
        ))),
        'make_name_paths' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TELEPECAS_MAKE_NAME_PATHS', 'vehicleInfo.makeDescription,vehicleInfo.makeName,makeDescription,makeName,make')),
        ))),
        'model_id_paths' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TELEPECAS_MODEL_ID_PATHS', 'vehicleInfo.externalModelId,vehicleInfo.modelId,modelId,externalModelId')),
        ))),
        'model_name_paths' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TELEPECAS_MODEL_NAME_PATHS', 'vehicleInfo.description,vehicleInfo.modelName,description,modelName,model')),
        ))),
        'models_make_filter_param' => env('TELEPECAS_MODELS_MAKE_FILTER_PARAM', 'externalMakeId'),
    ],

    /*
     * Lista de endpoints (paths) permitidos para o proxy interno.
     * Ex.: "stock/getStock", "catalog/parts/getParts", etc.
     */
    'allowed_endpoints' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('TELEPECAS_ALLOWED_ENDPOINTS', '')),
    ))),
];
