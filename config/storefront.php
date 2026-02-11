<?php

return [
    /*
     * Fonte de catalogo / stock para a vitrine.
     *
     * - telepecas: API TelePeças (implementacao atual)
     * - tpsoftware: API TP Software (api.tp.software)
     */
    'catalog_provider' => env('STOREFRONT_CATALOG_PROVIDER', 'telepecas'),

    'consult_email' => env('STOREFRONT_CONSULT_EMAIL', 'marketing@autorcpecas.pt'),
];
